<?php

namespace Tests\Feature;

use App\Models\Barbero;
use App\Models\Cliente;
use App\Models\Servicio;
use App\Models\Turno;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "Mi mes": el barbero ve sus turnos del mes y lo que ha cobrado. Solo los suyos.
 * Reloj congelado en domingo 15 de noviembre de 2026, 10:00.
 */
class MiMesTest extends TestCase
{
    use RefreshDatabase;

    private Barbero $camilo;

    private Barbero $andres;

    private Cliente $cliente;

    private Servicio $corte;   // $25.000

    private int $contador = 0;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-11-15 10:00:00'));

        $this->camilo = Barbero::factory()->create();
        $this->andres = Barbero::factory()->create();
        $this->cliente = Cliente::factory()->create(['nombre' => 'Cliente Prueba']);
        $this->corte = Servicio::factory()->create(['nombre' => 'Corte clásico', 'duracion_min' => 45, 'precio' => 25000]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** Turno en la base. Cada uno a una hora distinta para no cruzarse. */
    private function turno(Barbero $barbero, string $fecha, string $estado = Turno::ATENDIDO, bool $pagado = false, ?Carbon $pagadoEn = null): Turno
    {
        $inicio = Carbon::parse($fecha.' 06:00:00')->addMinutes(60 * $this->contador++);
        $fabrica = Turno::factory()->para($barbero, $this->cliente, $inicio, collect([$this->corte]))->estado($estado);

        return ($pagado ? $fabrica->pagado('efectivo', $pagadoEn) : $fabrica)->create();
    }

    public function test_el_barbero_ve_su_mes_con_turnos_atendidos_cobrado_y_por_cobrar(): void
    {
        $this->turno($this->camilo, '2026-11-03', pagado: true);   // cobrado
        $this->turno($this->camilo, '2026-11-05', pagado: true);   // cobrado
        $this->turno($this->camilo, '2026-11-10');                 // atendido sin pago: por cobrar
        $this->turno($this->camilo, '2026-11-12', Turno::CANCELADO);

        $this->actingAs($this->camilo->user)
            ->get(route('mi-mes'))
            ->assertOk()
            ->assertSee('noviembre de 2026')
            ->assertSee('$50.000')   // cobrado: dos pagos de 25.000
            ->assertSee('$25.000')   // por cobrar: uno
            ->assertViewHas('resumen', fn ($r) => $r['total'] === 4 && $r['atendidos'] === 3
                && $r['cobrado'] === 50000.0 && $r['porCobrar']['cantidad'] === 1);
    }

    public function test_no_ve_ni_suma_los_turnos_de_otro_barbero(): void
    {
        $this->turno($this->camilo, '2026-11-03', pagado: true);
        $this->turno($this->andres, '2026-11-04', pagado: true);
        $this->turno($this->andres, '2026-11-06');

        $this->actingAs($this->camilo->user)
            ->get(route('mi-mes'))
            ->assertViewHas('resumen', fn ($r) => $r['total'] === 1 && $r['cobrado'] === 25000.0
                && $r['porCobrar']['cantidad'] === 0 && $r['turnos']->total() === 1);
    }

    public function test_el_cobrado_cuenta_por_fecha_de_pago_no_por_fecha_del_turno(): void
    {
        // Atendido el 30 de octubre pero cobrado el 2 de noviembre: es plata de noviembre
        $this->turno($this->camilo, '2026-10-30', pagado: true, pagadoEn: Carbon::parse('2026-11-02 09:00:00'));
        // Atendido el 3 de noviembre pero cobrado en diciembre no existe aún; este se cobró en octubre
        $this->turno($this->camilo, '2026-11-03', pagado: true, pagadoEn: Carbon::parse('2026-10-31 09:00:00'));

        $this->actingAs($this->camilo->user)
            ->get(route('mi-mes'))
            ->assertViewHas('resumen', fn ($r) => $r['cobrado'] === 25000.0 && $r['total'] === 1);
    }

    public function test_lo_por_cobrar_incluye_turnos_atendidos_de_meses_anteriores(): void
    {
        $this->turno($this->camilo, '2026-10-20');   // atendido en octubre y sin pagar

        $this->actingAs($this->camilo->user)
            ->get(route('mi-mes'))
            ->assertViewHas('resumen', fn ($r) => $r['total'] === 0 && $r['porCobrar']['cantidad'] === 1);
    }

    public function test_puede_consultar_un_mes_anterior(): void
    {
        $this->turno($this->camilo, '2026-10-20', pagado: true);
        $this->turno($this->camilo, '2026-11-03', pagado: true);

        $this->actingAs($this->camilo->user)
            ->get(route('mi-mes', ['mes' => '2026-10']))
            ->assertOk()
            ->assertSee('octubre de 2026')
            ->assertViewHas('resumen', fn ($r) => $r['total'] === 1 && $r['cobrado'] === 25000.0 && $r['esMesActual'] === false);
    }

    public function test_un_mes_futuro_o_mal_escrito_vuelve_al_mes_actual_con_aviso(): void
    {
        foreach (['2026-12', '2027-01', 'noviembre', '2026-13', '2026-1'] as $mes) {
            $this->actingAs($this->camilo->user)
                ->get(route('mi-mes', ['mes' => $mes]))
                ->assertRedirect(route('mi-mes'))
                ->assertSessionHas('error');
        }
    }

    public function test_el_listado_va_del_mas_reciente_al_mas_antiguo_y_se_pagina(): void
    {
        foreach (range(1, 12) as $dia) {
            $this->turno($this->camilo, sprintf('2026-11-%02d', $dia));
        }

        $respuesta = $this->actingAs($this->camilo->user)->get(route('mi-mes'));

        $respuesta->assertViewHas('resumen', function ($r) {
            return $r['turnos']->count() === 10 && $r['turnos']->total() === 12
                && $r['turnos']->first()->fecha->toDateString() === '2026-11-12';
        });
        $this->actingAs($this->camilo->user)->get(route('mi-mes', ['page' => 2]))
            ->assertViewHas('resumen', fn ($r) => $r['turnos']->count() === 2);
    }

    public function test_el_barbero_ve_el_enlace_en_su_menu_y_el_admin_no_puede_entrar(): void
    {
        $this->actingAs($this->camilo->user)->get(route('agenda'))->assertSee('Mi mes');

        $this->actingAs(User::factory()->admin()->create())->get(route('mi-mes'))->assertForbidden();
    }

    public function test_sin_iniciar_sesion_va_al_login(): void
    {
        $this->get(route('mi-mes'))->assertRedirect(route('login'));
    }

    public function test_un_mes_sin_turnos_lo_dice(): void
    {
        $this->actingAs($this->camilo->user)
            ->get(route('mi-mes', ['mes' => '2026-09']))
            ->assertOk()
            ->assertSee('No tuviste turnos en septiembre de 2026');
    }
}
