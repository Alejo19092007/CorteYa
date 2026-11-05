<?php

namespace Tests\Feature;

use App\Exceptions\ReglaNegocioException;
use App\Models\Barbero;
use App\Models\Cliente;
use App\Models\Servicio;
use App\Models\Turno;
use App\Models\User;
use App\Services\DashboardService;
use App\Services\TurnoService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * R6 · Pago del turno. El pago se REGISTRA a mano (el sistema no cobra en línea) y es independiente del estado:
 * solo se paga un turno atendido, una sola vez, y los ingresos del dashboard son lo realmente cobrado.
 * Reloj congelado en lunes 2 de noviembre de 2026, 10:00.
 */
class PagoTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Barbero $andres;

    private Barbero $camilo;

    private Servicio $corte;   // 45 min, $25.000

    private int $hora = 0;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-11-02 10:00:00'));

        $this->admin = User::factory()->admin()->create();
        $this->andres = Barbero::factory()->create();
        $this->camilo = Barbero::factory()->create();
        $this->corte = Servicio::factory()->create(['nombre' => 'Corte clásico', 'duracion_min' => 45, 'precio' => 25000]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ------------------------------------------------------------------ ayudas

    /** Turno directo en la base (sin pasar por las reglas). Cada uno a una hora distinta para no cruzarse. */
    private function turno(string $estado = Turno::ATENDIDO, ?Barbero $barbero = null, string $fecha = '2026-11-02', int $precio = 25000): Turno
    {
        $servicio = Servicio::factory()->create(['duracion_min' => 15, 'precio' => $precio]);
        $inicio = Carbon::parse($fecha.' 06:00')->addMinutes(15 * $this->hora++);

        return Turno::factory()
            ->para($barbero ?? $this->andres, Cliente::factory()->create(), $inicio, collect([$servicio]))
            ->estado($estado)
            ->create();
    }

    private function service(): TurnoService
    {
        return app(TurnoService::class);
    }

    private function assertRegla(callable $accion, string $mensajeEmpieza): void
    {
        try {
            $accion();
        } catch (ReglaNegocioException $e) {
            $this->assertStringStartsWith($mensajeEmpieza, $e->getMessage());

            return;
        }

        $this->fail("Debió lanzar ReglaNegocioException: {$mensajeEmpieza}");
    }

    // ------------------------------------------------------- servicio (la regla)

    public function test_registrar_el_pago_de_un_turno_atendido_anota_cuando_y_como(): void
    {
        $turno = $this->turno();

        $resultado = $this->service()->registrarPago($turno, 'nequi');

        $this->assertTrue($resultado->estaPagado());
        $this->assertSame('nequi', $resultado->metodo_pago);
        $this->assertSame('Nequi', $resultado->metodoPagoLegible());
        $this->assertTrue($resultado->pagado_en->equalTo(Carbon::parse('2026-11-02 10:00:00')));
        $this->assertSame(Turno::ATENDIDO, $resultado->fresh()->estado);   // pagar no cambia el estado
        $this->assertEquals(25000, $resultado->fresh()->precio_total);      // y el monto sigue siendo el calculado en R2
    }

    public function test_solo_se_paga_un_turno_atendido(): void
    {
        foreach ([Turno::PENDIENTE, Turno::CONFIRMADO, Turno::CANCELADO, Turno::NO_ASISTIO] as $estado) {
            $turno = $this->turno($estado);

            $this->assertRegla(fn () => $this->service()->registrarPago($turno, 'efectivo'), 'Solo se puede registrar el pago de un turno atendido');
            $this->assertFalse($turno->fresh()->estaPagado(), "un turno {$estado} no debe quedar pagado");
        }
    }

    public function test_un_turno_no_se_paga_dos_veces_y_conserva_el_primer_metodo(): void
    {
        $turno = $this->turno();
        $this->service()->registrarPago($turno, 'efectivo');

        $this->assertRegla(fn () => $this->service()->registrarPago($turno, 'tarjeta'), 'Este turno ya está pagado.');

        $this->assertSame('efectivo', $turno->fresh()->metodo_pago);
    }

    public function test_un_metodo_de_pago_desconocido_se_rechaza(): void
    {
        $turno = $this->turno();

        $this->assertRegla(fn () => $this->service()->registrarPago($turno, 'bitcoin'), 'Elige un método de pago válido.');
        $this->assertFalse($turno->fresh()->estaPagado());
    }

    public function test_anular_un_pago_deja_el_turno_por_cobrar_otra_vez(): void
    {
        $turno = $this->turno();
        $this->service()->registrarPago($turno, 'efectivo');

        $anulado = $this->service()->anularPago($turno);

        $this->assertFalse($anulado->estaPagado());
        $this->assertNull($anulado->metodo_pago);
        $this->assertTrue($anulado->estaPorCobrar());

        // Y se puede volver a registrar con el método correcto
        $this->assertSame('transferencia', $this->service()->registrarPago($turno, 'transferencia')->metodo_pago);
    }

    public function test_no_se_puede_anular_un_pago_que_no_existe(): void
    {
        $turno = $this->turno();

        $this->assertRegla(fn () => $this->service()->anularPago($turno), 'Este turno no tiene un pago registrado.');
    }

    // ------------------------------------------------------------ panel (permisos)

    public function test_el_admin_registra_el_pago_desde_el_panel(): void
    {
        $turno = $this->turno(precio: 25000);

        $this->actingAs($this->admin)->from(route('turnos.index'))
            ->patch(route('turnos.pago', $turno), ['metodo_pago' => 'efectivo'])
            ->assertRedirect(route('turnos.index'))
            ->assertSessionHas('exito', 'Pago registrado: $25.000 en efectivo.');

        $this->assertDatabaseHas('turnos', ['id' => $turno->id, 'metodo_pago' => 'efectivo']);
        $this->assertTrue($turno->fresh()->estaPagado());
    }

    public function test_el_barbero_registra_el_pago_de_sus_turnos(): void
    {
        $suyo = $this->turno(barbero: $this->andres);

        $this->actingAs($this->andres->user)
            ->patch(route('turnos.pago', $suyo), ['metodo_pago' => 'daviplata'])
            ->assertSessionHas('exito');

        $this->assertSame('daviplata', $suyo->fresh()->metodo_pago);
    }

    public function test_un_barbero_no_puede_registrar_el_pago_del_turno_de_otro(): void
    {
        $deCamilo = $this->turno(barbero: $this->camilo);

        $this->actingAs($this->andres->user)
            ->patch(route('turnos.pago', $deCamilo), ['metodo_pago' => 'efectivo'])
            ->assertForbidden();

        $this->assertFalse($deCamilo->fresh()->estaPagado());
    }

    public function test_solo_el_admin_puede_anular_un_pago(): void
    {
        $turno = $this->turno();
        $this->service()->registrarPago($turno, 'efectivo');

        // El barbero dueño del turno NO puede borrar dinero cobrado
        $this->actingAs($this->andres->user)->delete(route('turnos.pago.anular', $turno))->assertForbidden();
        $this->assertTrue($turno->fresh()->estaPagado());

        $this->actingAs($this->admin)->delete(route('turnos.pago.anular', $turno))
            ->assertSessionHas('exito', 'Pago anulado: el turno vuelve a quedar por cobrar.');
        $this->assertFalse($turno->fresh()->estaPagado());
    }

    public function test_sin_iniciar_sesion_no_se_puede_registrar_un_pago(): void
    {
        $turno = $this->turno();

        $this->patch(route('turnos.pago', $turno), ['metodo_pago' => 'efectivo'])->assertRedirect(route('login'));
        $this->assertFalse($turno->fresh()->estaPagado());
    }

    public function test_sin_metodo_o_con_uno_invalido_se_avisa_con_una_alerta(): void
    {
        $turno = $this->turno();

        $this->actingAs($this->admin)->from(route('turnos.index'))
            ->patch(route('turnos.pago', $turno), [])
            ->assertRedirect(route('turnos.index'))
            ->assertSessionHas('error', 'Elige cómo pagó el cliente.');

        $this->actingAs($this->admin)->from(route('turnos.index'))
            ->patch(route('turnos.pago', $turno), ['metodo_pago' => 'trueque'])
            ->assertSessionHas('error', 'Elige un método de pago válido.');

        $this->assertFalse($turno->fresh()->estaPagado());
    }

    public function test_pagar_un_turno_que_no_esta_atendido_muestra_el_motivo(): void
    {
        $turno = $this->turno(Turno::CONFIRMADO);

        $this->actingAs($this->admin)->from(route('turnos.index'))
            ->patch(route('turnos.pago', $turno), ['metodo_pago' => 'efectivo'])
            ->assertSessionHas('error');

        $this->assertFalse($turno->fresh()->estaPagado());
    }

    // ------------------------------------------------------------- panel (pantallas)

    public function test_el_listado_distingue_lo_pagado_de_lo_por_cobrar_y_filtra(): void
    {
        $pagado = $this->turno();
        $this->service()->registrarPago($pagado, 'nequi');
        $porCobrar = $this->turno();
        $this->turno(Turno::CONFIRMADO);   // no atendido: sin insignia de pago

        $this->actingAs($this->admin)->get(route('turnos.index'))
            ->assertOk()
            ->assertSee('Pagado · Nequi')
            ->assertSee('Por cobrar');

        // Filtro "por cobrar": solo el atendido sin pago
        $porCobrarIds = $this->actingAs($this->admin)->get(route('turnos.index', ['pago' => 'por_cobrar']))
            ->assertOk()->viewData('turnos')->pluck('id')->all();
        $this->assertSame([$porCobrar->id], $porCobrarIds);

        $pagadosIds = $this->actingAs($this->admin)->get(route('turnos.index', ['pago' => 'pagado']))
            ->viewData('turnos')->pluck('id')->all();
        $this->assertSame([$pagado->id], $pagadosIds);
    }

    public function test_el_boton_de_registrar_pago_solo_aparece_en_los_atendidos_sin_pagar(): void
    {
        $this->turno(Turno::CONFIRMADO);
        $this->actingAs($this->admin)->get(route('turnos.index'))->assertDontSee('Registrar pago del turno');

        $this->turno(Turno::ATENDIDO);
        $this->actingAs($this->admin)->get(route('turnos.index'))->assertSee('Registrar pago del turno');
    }

    public function test_el_detalle_ofrece_registrar_o_anular_segun_el_estado_del_pago(): void
    {
        $turno = $this->turno();

        $this->actingAs($this->admin)->get(route('turnos.show', $turno))
            ->assertOk()->assertSee('Faltan por cobrar $25.000')->assertSee('Registrar pago')->assertDontSee('Anular pago');

        $this->service()->registrarPago($turno, 'tarjeta');

        $this->actingAs($this->admin)->get(route('turnos.show', $turno))
            ->assertOk()->assertSee('Pagado · Tarjeta')->assertSee('Anular pago');
    }

    public function test_el_detalle_de_un_turno_no_atendido_no_muestra_la_seccion_de_pago(): void
    {
        $turno = $this->turno(Turno::CONFIRMADO);

        $this->actingAs($this->admin)->get(route('turnos.show', $turno))->assertOk()->assertDontSee('Por cobrar');
    }

    public function test_la_agenda_del_barbero_permite_cobrar_y_cuenta_lo_pendiente(): void
    {
        $this->turno(barbero: $this->andres);                                      // atendido sin pagar
        $pagado = $this->turno(barbero: $this->andres);                            // atendido y pagado
        $this->service()->registrarPago($pagado, 'efectivo');
        $this->turno(barbero: $this->camilo);                                      // de otro barbero: no aparece

        $respuesta = $this->actingAs($this->andres->user)->get(route('agenda'))->assertOk();

        $respuesta->assertSee('Cobrar $25.000')            // formulario del turno por cobrar
            ->assertSee('Elige el método')
            ->assertSee('Pagado · Efectivo');              // el pagado solo muestra la insignia
        $this->assertSame(1, substr_count($respuesta->getContent(), 'Elige el método'));
        $respuesta->assertSeeInOrder(['1', 'por cobrar']);
    }

    // ------------------------------------------------------------------- dashboard

    public function test_los_ingresos_son_lo_cobrado_y_un_atendido_sin_pagar_no_cuenta(): void
    {
        $pagado = $this->turno(precio: 50000);
        $this->service()->registrarPago($pagado, 'efectivo');
        $this->turno(precio: 30000);   // atendido pero por cobrar

        $resumen = app(DashboardService::class)->resumen();

        $this->assertEquals(50000, $resumen['tarjetas']['ingresosMes']['valor']);
        $this->assertEquals(['cantidad' => 1, 'total' => 30000.0], $resumen['porCobrar']);
    }

    public function test_el_ingreso_cuenta_en_el_mes_en_que_se_cobra_no_en_el_que_se_atendio(): void
    {
        // Se atendió el 28 de octubre pero se cobró hoy (2 de noviembre)
        $turno = $this->turno(fecha: '2026-10-28', precio: 40000);
        $this->service()->registrarPago($turno, 'transferencia');

        $resumen = app(DashboardService::class)->resumen();

        $this->assertEquals(40000, $resumen['tarjetas']['ingresosMes']['valor']);
    }

    public function test_lo_por_cobrar_no_desaparece_al_cambiar_de_mes(): void
    {
        $this->turno(fecha: '2026-09-15', precio: 20000);   // atendido hace dos meses y sin cobrar

        $this->assertEquals(['cantidad' => 1, 'total' => 20000.0], app(DashboardService::class)->resumen()['porCobrar']);
    }

    public function test_el_grafico_suma_los_pagos_de_cada_barbero(): void
    {
        $this->service()->registrarPago($this->turno(barbero: $this->andres, precio: 30000), 'efectivo');
        $this->service()->registrarPago($this->turno(barbero: $this->camilo, precio: 70000), 'nequi');
        $this->turno(barbero: $this->andres, precio: 999000);   // sin pagar: no suma

        $grafico = app(DashboardService::class)->resumen()['grafico'];

        $this->assertEquals([70000, 30000], $grafico['valores']);   // ordenado de mayor a menor
    }

    public function test_el_dashboard_muestra_lo_por_cobrar_con_enlace_a_esos_turnos(): void
    {
        $this->turno(precio: 25000);
        $this->turno(precio: 15000);

        $this->actingAs($this->admin)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Por cobrar:')
            ->assertSee('$40.000')
            ->assertSee('(2 turnos)')
            ->assertSee('pago=por_cobrar', false);
    }

    public function test_sin_nada_por_cobrar_el_dashboard_no_muestra_el_aviso(): void
    {
        $this->service()->registrarPago($this->turno(), 'efectivo');

        $this->actingAs($this->admin)->get(route('dashboard'))->assertOk()->assertDontSee('Por cobrar:');
    }

    // ------------------------------------------------------------------------- API

    public function test_la_api_informa_si_el_turno_esta_pagado(): void
    {
        Sanctum::actingAs($this->admin);
        $turno = $this->turno();

        $url = '/api/turnos?telefono='.$turno->cliente->telefono;
        $this->getJson($url)->assertOk()
            ->assertJsonPath('data.0.pagado', false)
            ->assertJsonPath('data.0.metodo_pago', null)
            ->assertJsonPath('data.0.pagado_en', null);

        $this->service()->registrarPago($turno, 'nequi');

        $this->getJson($url)->assertOk()
            ->assertJsonPath('data.0.pagado', true)
            ->assertJsonPath('data.0.metodo_pago', 'nequi')
            ->assertJsonPath('data.0.pagado_en', '2026-11-02 10:00');
    }

    // ------------------------------------------------------------------- migración

    public function test_la_migracion_deja_pagados_los_atendidos_que_ya_existian(): void
    {
        // Se vuelve al esquema anterior, se crean turnos "como estaban antes" y se aplica la migración otra vez
        // Solo se revierte la migración del pago (por su ruta); las demás no se tocan
        $this->artisan('migrate:rollback', ['--path' => 'database/migrations/2026_11_05_100000_agrega_pago_a_turnos.php'])->assertSuccessful();

        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasColumn('turnos', 'pagado_en'));

        $cliente = Cliente::factory()->create();
        $base = ['barbero_id' => $this->andres->id, 'cliente_id' => $cliente->id, 'precio_total' => 25000, 'origen' => 'panel', 'created_at' => now(), 'updated_at' => now()];
        DB::table('turnos')->insert([
            $base + ['fecha' => '2026-10-20', 'hora_inicio' => '09:00:00', 'hora_fin' => '09:45:00', 'estado' => 'atendido'],
            $base + ['fecha' => '2026-10-21', 'hora_inicio' => '09:00:00', 'hora_fin' => '09:45:00', 'estado' => 'confirmado'],
            $base + ['fecha' => '2026-10-22', 'hora_inicio' => '09:00:00', 'hora_fin' => '09:45:00', 'estado' => 'cancelado'],
        ]);

        $this->artisan('migrate')->assertSuccessful();

        $atendido = Turno::where('estado', 'atendido')->firstOrFail();
        $this->assertTrue($atendido->estaPagado());
        $this->assertSame('efectivo', $atendido->metodo_pago);
        $this->assertSame('2026-10-20 09:45:00', $atendido->pagado_en->format('Y-m-d H:i:s'));

        // Los que no se atendieron no tienen nada que cobrar
        $this->assertSame(0, Turno::where('estado', '!=', 'atendido')->whereNotNull('pagado_en')->count());
    }
}
