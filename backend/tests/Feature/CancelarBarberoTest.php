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
 * El barbero cancela SUS turnos desde "Mi agenda" (por ejemplo, si se enferma): sin el límite de 2 horas (R3,
 * que es solo del cliente), pero respetando R4 (solo pendiente o confirmado) y sin tocar turnos ajenos.
 * Reloj congelado en domingo 15 de noviembre de 2026, 10:00.
 */
class CancelarBarberoTest extends TestCase
{
    use RefreshDatabase;

    private Barbero $camilo;

    private Barbero $andres;

    private Cliente $cliente;

    private Servicio $corte;

    private int $contador = 0;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-11-15 10:00:00'));

        $this->camilo = Barbero::factory()->create();
        $this->andres = Barbero::factory()->create();
        $this->cliente = Cliente::factory()->create();
        $this->corte = Servicio::factory()->create(['duracion_min' => 45, 'precio' => 25000]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** Turno de hoy; $minutos es cuánto falta para que empiece (puede ser negativo). Cada uno a una hora distinta. */
    private function turno(Barbero $barbero, string $estado, int $minutos = 180): Turno
    {
        $inicio = now()->addMinutes($minutos)->addSeconds($this->contador++); // desfase mínimo para no repetir hora

        return Turno::factory()->para($barbero, $this->cliente, $inicio, collect([$this->corte]))->estado($estado)->create();
    }

    public function test_el_barbero_cancela_un_turno_suyo_pendiente_o_confirmado(): void
    {
        foreach ([Turno::PENDIENTE, Turno::CONFIRMADO] as $estado) {
            $turno = $this->turno($this->camilo, $estado);

            $this->actingAs($this->camilo->user)
                ->from(route('agenda'))
                ->delete(route('turnos.cancelar', $turno))
                ->assertRedirect(route('agenda'))
                ->assertSessionHas('exito');

            $this->assertSame(Turno::CANCELADO, $turno->fresh()->estado);
        }
    }

    public function test_puede_cancelar_aunque_falten_menos_de_dos_horas_porque_r3_es_solo_del_cliente(): void
    {
        $turno = $this->turno($this->camilo, Turno::CONFIRMADO, minutos: 30);

        $this->actingAs($this->camilo->user)->delete(route('turnos.cancelar', $turno))->assertSessionHas('exito');

        $this->assertSame(Turno::CANCELADO, $turno->fresh()->estado);
    }

    public function test_no_puede_cancelar_el_turno_de_otro_barbero(): void
    {
        $ajeno = $this->turno($this->andres, Turno::CONFIRMADO);

        $this->actingAs($this->camilo->user)->delete(route('turnos.cancelar', $ajeno))->assertForbidden();

        $this->assertSame(Turno::CONFIRMADO, $ajeno->fresh()->estado);
    }

    public function test_no_cancela_lo_que_ya_no_se_puede_cancelar_r4(): void
    {
        foreach ([Turno::ATENDIDO, Turno::NO_ASISTIO, Turno::CANCELADO] as $estado) {
            $turno = $this->turno($this->camilo, $estado, minutos: -240);

            $this->actingAs($this->camilo->user)
                ->delete(route('turnos.cancelar', $turno))
                ->assertSessionHas('error');

            $this->assertSame($estado, $turno->fresh()->estado);
        }
    }

    public function test_mi_agenda_ofrece_cancelar_solo_en_pendiente_y_confirmado(): void
    {
        $pendiente = $this->turno($this->camilo, Turno::PENDIENTE);
        $confirmado = $this->turno($this->camilo, Turno::CONFIRMADO, minutos: 240);
        $atendido = $this->turno($this->camilo, Turno::ATENDIDO, minutos: -300);
        $cancelado = $this->turno($this->camilo, Turno::CANCELADO, minutos: -240);

        $this->actingAs($this->camilo->user)
            ->get(route('agenda'))
            ->assertOk()
            ->assertSee(route('turnos.cancelar', $pendiente))
            ->assertSee(route('turnos.cancelar', $confirmado))
            ->assertDontSee(route('turnos.cancelar', $atendido))
            ->assertDontSee(route('turnos.cancelar', $cancelado))
            ->assertSee('turnos-cancelar.js', false);
    }

    /** Turno en otro día (días desde hoy; positivo = futuro) a las 10:00. */
    private function turnoEnDias(Barbero $barbero, string $estado, int $dias): Turno
    {
        $inicio = now()->startOfDay()->addDays($dias)->setTime(10, 0);

        return Turno::factory()->para($barbero, $this->cliente, $inicio, collect([$this->corte]))->estado($estado)->create();
    }

    public function test_mi_agenda_lista_los_proximos_dias_pendientes_y_confirmados_con_su_boton(): void
    {
        $manana = $this->turnoEnDias($this->camilo, Turno::CONFIRMADO, 1);
        $enUnaSemana = $this->turnoEnDias($this->camilo, Turno::PENDIENTE, 7);
        $lejano = $this->turnoEnDias($this->camilo, Turno::CONFIRMADO, 20);          // fuera de los 14 días
        $cancelado = $this->turnoEnDias($this->camilo, Turno::CANCELADO, 2);          // ya no se gestiona
        $ajeno = $this->turnoEnDias($this->andres, Turno::CONFIRMADO, 1);             // de otro barbero

        $this->actingAs($this->camilo->user)
            ->get(route('agenda'))
            ->assertOk()
            ->assertSee('Próximos días')
            ->assertSee(route('turnos.cancelar', $manana))
            ->assertSee(route('turnos.cancelar', $enUnaSemana))
            ->assertDontSee(route('turnos.cancelar', $lejano))
            ->assertDontSee(route('turnos.cancelar', $cancelado))
            ->assertDontSee(route('turnos.cancelar', $ajeno))
            ->assertSee('turnos-cancelar.js', false)
            ->assertSee('id="modalCancelar"', false);
    }

    public function test_los_proximos_dias_salen_aunque_hoy_no_haya_turnos_y_se_cancelan(): void
    {
        $manana = $this->turnoEnDias($this->camilo, Turno::PENDIENTE, 1);

        $this->actingAs($this->camilo->user)->get(route('agenda'))
            ->assertSee('No tienes turnos para hoy')
            ->assertSee(route('turnos.cancelar', $manana));

        $this->actingAs($this->camilo->user)->delete(route('turnos.cancelar', $manana))->assertSessionHas('exito');
        $this->assertSame(Turno::CANCELADO, $manana->fresh()->estado);

        $this->actingAs($this->camilo->user)->get(route('agenda'))->assertDontSee('Próximos días');
    }

    public function test_el_admin_y_el_visitante(): void
    {
        $turno = $this->turno($this->camilo, Turno::CONFIRMADO);

        $this->delete(route('turnos.cancelar', $turno))->assertRedirect(route('login'));

        // El admin sigue cancelando como siempre desde el listado
        $this->actingAs(User::factory()->admin()->create())->delete(route('turnos.destroy', $turno))->assertRedirect(route('turnos.index'));
        $this->assertSame(Turno::CANCELADO, $turno->fresh()->estado);
    }
}
