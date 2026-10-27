<?php

namespace Tests\Feature;

use App\Models\Barbero;
use App\Models\Cliente;
use App\Models\Horario;
use App\Models\Servicio;
use App\Models\Turno;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pruebas del panel de turnos: listado, CRUD, cambios de estado, agenda del barbero y permisos.
 * Reloj congelado en lunes 2 de noviembre de 2026, 10:00.
 */
class TurnoPanelTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Barbero $barbero;

    private Servicio $corte;   // 45 min, $25.000

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-11-02 10:00:00'));

        $this->admin = User::factory()->admin()->create();
        $this->barbero = $this->nuevoBarbero();
        $this->corte = Servicio::factory()->create(['nombre' => 'Corte clásico', 'duracion_min' => 45, 'precio' => 25000]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ------------------------------------------------------------------ ayudas

    private function nuevoBarbero(): Barbero
    {
        $barbero = Barbero::factory()->create();

        foreach (range(1, 7) as $dia) {
            Horario::factory()->create([
                'barbero_id' => $barbero->id, 'dia_semana' => $dia,
                'hora_inicio' => '06:00:00', 'hora_fin' => '22:00:00',
            ]);
        }

        return $barbero;
    }

    /** Crea un turno directamente (sin pasar por las reglas) para preparar escenarios. */
    private function turno(string $inicio, string $estado = Turno::CONFIRMADO, ?Cliente $cliente = null, ?Barbero $barbero = null, string $origen = Turno::ORIGEN_PANEL): Turno
    {
        return Turno::factory()
            ->para($barbero ?? $this->barbero, $cliente ?? Cliente::factory()->create(), Carbon::parse($inicio), collect([$this->corte]))
            ->estado($estado)
            ->origen($origen)
            ->create();
    }

    private function datosNuevoTurno(array $cambios = []): array
    {
        return array_merge([
            'cliente_id' => Cliente::factory()->create()->id,
            'barbero_id' => $this->barbero->id,
            'fecha' => '2026-11-03',
            'hora_inicio' => '10:00',
            'servicios' => [$this->corte->id],
        ], $cambios);
    }

    // ----------------------------------------------------------------- listado

    public function test_el_listado_muestra_los_turnos(): void
    {
        $cliente = Cliente::factory()->create(['nombre' => 'Laura Martínez']);
        $this->turno('2026-11-03 10:00', cliente: $cliente);

        $this->actingAs($this->admin)->get(route('turnos.index'))
            ->assertOk()
            ->assertSee('Listado de turnos')
            ->assertSee('Laura Martínez')
            ->assertSee('Corte clásico')
            ->assertSee('Confirmado');
    }

    public function test_busca_por_nombre_o_por_telefono_del_cliente(): void
    {
        $ana = Cliente::factory()->create(['nombre' => 'Ana Ruiz', 'telefono' => '573001112222']);
        $beto = Cliente::factory()->create(['nombre' => 'Beto Gómez', 'telefono' => '573009998888']);
        $this->turno('2026-11-03 10:00', cliente: $ana);
        $this->turno('2026-11-03 12:00', cliente: $beto);

        $this->actingAs($this->admin)->get(route('turnos.index', ['q' => 'Ana']))
            ->assertSee('Ana Ruiz')->assertDontSee('Beto Gómez');

        $this->get(route('turnos.index', ['q' => '9998888']))
            ->assertSee('Beto Gómez')->assertDontSee('Ana Ruiz');
    }

    public function test_filtra_por_estado_barbero_y_rango_de_fechas(): void
    {
        $otro = $this->nuevoBarbero();
        $this->turno('2026-11-03 10:00', Turno::CANCELADO, Cliente::factory()->create(['nombre' => 'Cliente Cancelado']));
        $this->turno('2026-11-03 12:00', Turno::CONFIRMADO, Cliente::factory()->create(['nombre' => 'Cliente Confirmado']));
        $this->turno('2026-11-10 12:00', Turno::CONFIRMADO, Cliente::factory()->create(['nombre' => 'Cliente Lejano']));
        $this->turno('2026-11-03 15:00', Turno::CONFIRMADO, Cliente::factory()->create(['nombre' => 'Cliente Otro Barbero']), $otro);

        $this->actingAs($this->admin);

        $this->get(route('turnos.index', ['estado' => 'cancelado']))
            ->assertSee('Cliente Cancelado')->assertDontSee('Cliente Confirmado');

        $this->get(route('turnos.index', ['barbero_id' => $otro->id]))
            ->assertSee('Cliente Otro Barbero')->assertDontSee('Cliente Confirmado');

        $this->get(route('turnos.index', ['desde' => '2026-11-05']))
            ->assertSee('Cliente Lejano')->assertDontSee('Cliente Confirmado');

        $this->get(route('turnos.index', ['hasta' => '2026-11-05']))
            ->assertSee('Cliente Confirmado')->assertDontSee('Cliente Lejano');
    }

    public function test_la_paginacion_conserva_los_filtros(): void
    {
        foreach (range(0, 11) as $i) {
            $this->turno(Carbon::parse('2026-11-03 06:00')->addMinutes($i * 45)->toDateTimeString());
        }

        $this->actingAs($this->admin);

        $this->get(route('turnos.index', ['estado' => 'confirmado']))
            ->assertOk()
            ->assertSee('estado=confirmado&amp;page=2', false) // el enlace a la página 2 trae el filtro
            ->assertSee('Mostrando');

        $this->get(route('turnos.index', ['estado' => 'confirmado', 'page' => 2]))->assertOk();
    }

    public function test_el_listado_vacio_muestra_un_aviso(): void
    {
        $this->actingAs($this->admin)->get(route('turnos.index', ['q' => 'nadie-se-llama-asi']))
            ->assertOk()->assertSee('No hay turnos con esos filtros');
    }

    public function test_solo_se_ofrece_editar_y_cancelar_en_turnos_pendientes_o_confirmados(): void
    {
        $atendido = $this->turno('2026-11-03 10:00', Turno::ATENDIDO);

        $this->actingAs($this->admin)->get(route('turnos.index'))
            ->assertDontSee(route('turnos.edit', $atendido), false)
            ->assertDontSee('aria-label="Cancelar turno"', false);

        $confirmado = $this->turno('2026-11-03 12:00', Turno::CONFIRMADO);

        $this->get(route('turnos.index'))
            ->assertSee(route('turnos.edit', $confirmado), false)
            ->assertSee('aria-label="Cancelar turno"', false);
    }

    // --------------------------------------------------------------- permisos

    public function test_el_barbero_no_puede_entrar_a_la_gestion_de_turnos(): void
    {
        $this->actingAs($this->barbero->user);

        $this->get(route('turnos.index'))->assertForbidden();
        $this->get(route('turnos.create'))->assertForbidden();
        $this->post(route('turnos.store'), $this->datosNuevoTurno())->assertForbidden();
        $this->get(route('turnos.disponibilidad'))->assertForbidden();
        $this->get(route('clientes.buscar', ['q' => 'ana']))->assertForbidden();
    }

    // ------------------------------------------------------------------ crear

    public function test_crea_un_turno_desde_el_panel_ya_confirmado(): void
    {
        $datos = $this->datosNuevoTurno();

        $this->actingAs($this->admin)->post(route('turnos.store'), $datos)
            ->assertRedirect(route('turnos.index'))
            ->assertSessionHas('exito');

        $this->assertDatabaseHas('turnos', [
            'barbero_id' => $datos['barbero_id'],
            'cliente_id' => $datos['cliente_id'],
            'hora_inicio' => '10:00:00',
            'hora_fin' => '10:45:00',    // calculada (R2), no enviada
            'precio_total' => 25000,     // calculado (R2), no enviado
            'estado' => 'confirmado',    // decisión 2
            'origen' => 'panel',
        ]);
        $this->assertDatabaseCount('turno_servicio', 1);
    }

    public function test_no_acepta_hora_fin_ni_precio_enviados_a_mano(): void
    {
        $this->actingAs($this->admin)->post(route('turnos.store'), $this->datosNuevoTurno([
            'hora_fin' => '23:00', 'precio_total' => 1, 'estado' => 'atendido', 'origen' => 'whatsapp',
        ]));

        $turno = Turno::firstOrFail();
        $this->assertSame('10:45:00', $turno->hora_fin);
        $this->assertEquals(25000, $turno->precio_total);
        $this->assertSame('confirmado', $turno->estado);
        $this->assertSame('panel', $turno->origen);
    }

    public function test_valida_los_campos_en_espanol(): void
    {
        $this->actingAs($this->admin)->from(route('turnos.create'))->post(route('turnos.store'), [])
            ->assertRedirect(route('turnos.create'))
            ->assertSessionHasErrors([
                'cliente_id' => 'El campo cliente es obligatorio.',
                'barbero_id' => 'El campo barbero es obligatorio.',
                'fecha' => 'El campo fecha es obligatorio.',
                'hora_inicio' => 'El campo hora de inicio es obligatorio.',
                'servicios' => 'El campo servicios es obligatorio.',
            ]);
    }

    public function test_no_permite_una_fecha_pasada(): void
    {
        $this->actingAs($this->admin)->post(route('turnos.store'), $this->datosNuevoTurno(['fecha' => '2026-11-01']))
            ->assertSessionHasErrors('fecha');

        $this->assertDatabaseCount('turnos', 0);
    }

    public function test_r1_vuelve_al_formulario_con_la_alerta_y_conserva_lo_escrito(): void
    {
        $this->turno('2026-11-03 10:00'); // 10:00–10:45

        $this->actingAs($this->admin)->from(route('turnos.create'))
            ->post(route('turnos.store'), $this->datosNuevoTurno(['hora_inicio' => '10:30']))
            ->assertRedirect(route('turnos.create'))
            ->assertSessionHas('error')
            ->assertSessionHasInput('hora_inicio', '10:30');

        $this->assertStringStartsWith('El barbero no está disponible en ese horario', session('error'));
        $this->assertDatabaseCount('turnos', 1);
    }

    public function test_el_formulario_de_nuevo_turno_muestra_la_alerta_de_r1(): void
    {
        $this->actingAs($this->admin)
            ->withSession(['error' => 'El barbero no está disponible en ese horario (ya tiene un turno de 10:00 a 10:45).'])
            ->get(route('turnos.create'))
            ->assertOk()
            ->assertSee('El barbero no está disponible en ese horario')
            ->assertSee('Resumen del turno');
    }

    // ----------------------------------------------------------------- editar

    public function test_actualiza_un_turno(): void
    {
        $turno = $this->turno('2026-11-03 10:00');

        $this->actingAs($this->admin)->put(route('turnos.update', $turno), $this->datosNuevoTurno([
            'cliente_id' => $turno->cliente_id, 'hora_inicio' => '14:00',
        ]))->assertRedirect(route('turnos.index'));

        $this->assertSame('14:00:00', $turno->fresh()->hora_inicio);
        $this->assertSame('14:45:00', $turno->fresh()->hora_fin);
    }

    public function test_la_pantalla_de_edicion_abre_con_los_datos_del_turno(): void
    {
        $cliente = Cliente::factory()->create(['nombre' => 'Daniela Silva']);
        $turno = $this->turno('2026-11-03 10:00', cliente: $cliente);

        $this->actingAs($this->admin)->get(route('turnos.edit', $turno))
            ->assertOk()
            ->assertSee('Editar turno')
            ->assertSee('Daniela Silva');
    }

    public function test_no_se_edita_un_turno_atendido(): void
    {
        $atendido = $this->turno('2026-11-02 09:00', Turno::ATENDIDO);

        $this->actingAs($this->admin)->get(route('turnos.edit', $atendido))
            ->assertRedirect(route('turnos.show', $atendido))
            ->assertSessionHas('error', 'Este turno ya no se puede modificar.');

        $this->put(route('turnos.update', $atendido), $this->datosNuevoTurno(['fecha' => '2026-11-02', 'hora_inicio' => '09:00']))
            ->assertSessionHas('error', 'Este turno ya no se puede modificar.');
    }

    public function test_muestra_el_detalle_con_el_precio_congelado(): void
    {
        $turno = $this->turno('2026-11-03 10:00');
        $this->corte->update(['precio' => 99000]); // el precio actual cambia después

        $this->actingAs($this->admin)->get(route('turnos.show', $turno))
            ->assertOk()
            ->assertSee('$25.000')
            ->assertDontSee('$99.000');
    }

    // --------------------------------------------------------------- cancelar

    public function test_cancelar_cambia_el_estado_y_no_borra_el_turno(): void
    {
        $turno = $this->turno('2026-11-02 10:30'); // faltan 30 min: al admin no le aplica R3

        $this->actingAs($this->admin)->delete(route('turnos.destroy', $turno))
            ->assertRedirect(route('turnos.index'))
            ->assertSessionHas('exito', 'Turno cancelado.');

        $this->assertDatabaseHas('turnos', ['id' => $turno->id, 'estado' => 'cancelado']);
    }

    public function test_no_se_cancela_un_turno_ya_atendido(): void
    {
        $atendido = $this->turno('2026-11-02 09:00', Turno::ATENDIDO);

        $this->actingAs($this->admin)->delete(route('turnos.destroy', $atendido))
            ->assertSessionHas('error', 'Cambio de estado no permitido');

        $this->assertSame('atendido', $atendido->fresh()->estado);
    }

    // -------------------------------------------------------- cambios de estado

    public function test_el_admin_confirma_un_turno_pendiente(): void
    {
        $pendiente = $this->turno('2026-11-03 10:00', Turno::PENDIENTE, origen: Turno::ORIGEN_WHATSAPP);

        $this->actingAs($this->admin)->patch(route('turnos.estado', $pendiente), ['estado' => 'confirmado'])
            ->assertSessionHas('exito', 'Turno confirmado.');

        $this->assertSame('confirmado', $pendiente->fresh()->estado);
    }

    public function test_el_barbero_marca_atendido_su_turno_cuando_ya_paso_la_hora(): void
    {
        $turno = $this->turno('2026-11-02 09:00'); // empezó hace una hora

        $this->actingAs($this->barbero->user)->patch(route('turnos.estado', $turno), ['estado' => 'atendido'])
            ->assertSessionHas('exito', 'Turno marcado como atendido.');

        $this->assertSame('atendido', $turno->fresh()->estado);
    }

    public function test_no_se_marca_atendido_antes_de_la_hora_del_turno(): void
    {
        $turno = $this->turno('2026-11-02 15:00');

        $this->actingAs($this->barbero->user)->patch(route('turnos.estado', $turno), ['estado' => 'atendido'])
            ->assertSessionHas('error', 'Aún no ha llegado la hora del turno');

        $this->assertSame('confirmado', $turno->fresh()->estado);
    }

    public function test_el_barbero_no_puede_cambiar_el_estado_de_un_turno_ajeno(): void
    {
        $otro = $this->nuevoBarbero();
        $ajeno = $this->turno('2026-11-02 09:00', barbero: $otro);

        $this->actingAs($this->barbero->user)->patch(route('turnos.estado', $ajeno), ['estado' => 'atendido'])
            ->assertForbidden();

        $this->assertSame('confirmado', $ajeno->fresh()->estado);
    }

    public function test_el_cambio_de_estado_no_sirve_para_cancelar(): void
    {
        $turno = $this->turno('2026-11-02 09:00');

        $this->actingAs($this->admin)->patch(route('turnos.estado', $turno), ['estado' => 'cancelado'])
            ->assertSessionHasErrors('estado');

        $this->assertSame('confirmado', $turno->fresh()->estado);
    }

    // ------------------------------------------------------------------ agenda

    public function test_la_agenda_muestra_solo_los_turnos_de_hoy_del_barbero(): void
    {
        $otro = $this->nuevoBarbero();
        $this->turno('2026-11-02 11:00', cliente: Cliente::factory()->create(['nombre' => 'Mi Cliente de Hoy']));
        $this->turno('2026-11-03 11:00', cliente: Cliente::factory()->create(['nombre' => 'Cliente de Mañana']));
        $this->turno('2026-11-02 12:00', cliente: Cliente::factory()->create(['nombre' => 'Cliente de Otro']), barbero: $otro);

        $this->actingAs($this->barbero->user)->get(route('agenda'))
            ->assertOk()
            ->assertSee('Mi Cliente de Hoy')
            ->assertDontSee('Cliente de Mañana')
            ->assertDontSee('Cliente de Otro');
    }

    public function test_la_agenda_cuenta_los_turnos_sin_los_cancelados(): void
    {
        $this->turno('2026-11-02 09:00', Turno::ATENDIDO);
        $this->turno('2026-11-02 11:00', Turno::CONFIRMADO);
        $this->turno('2026-11-02 13:00', Turno::CANCELADO);

        $this->actingAs($this->barbero->user)->get(route('agenda'))
            ->assertSeeInOrder(['2', 'turnos hoy', '1', 'atendidos']);
    }

    public function test_los_botones_de_la_agenda_dependen_del_estado(): void
    {
        $this->actingAs($this->barbero->user);

        // Pendiente → solo "Confirmar"
        $pendiente = $this->turno('2026-11-02 13:00', Turno::PENDIENTE, origen: Turno::ORIGEN_WHATSAPP);
        $this->get(route('agenda'))
            ->assertSee('value="confirmado"', false)
            ->assertDontSee('value="atendido"', false)
            ->assertDontSee('value="no_asistio"', false);
        $pendiente->delete();

        // Confirmado cuya hora ya pasó → "Atendido" y "No asistió", sin aviso de espera
        $confirmado = $this->turno('2026-11-02 09:00', Turno::CONFIRMADO);
        $this->get(route('agenda'))
            ->assertSee('value="atendido"', false)
            ->assertSee('value="no_asistio"', false)
            ->assertDontSee('Podrás marcarlo desde');
        $confirmado->delete();

        // Confirmado cuya hora todavía no llega → botones desactivados con aviso
        $this->turno('2026-11-02 15:00', Turno::CONFIRMADO);
        $this->get(route('agenda'))->assertSee('Podrás marcarlo desde');
    }

    public function test_atendido_cancelado_y_no_asistio_no_tienen_botones(): void
    {
        foreach ([Turno::ATENDIDO, Turno::CANCELADO, Turno::NO_ASISTIO] as $i => $estado) {
            $this->turno('2026-11-02 '.(8 + $i * 2).':00', $estado);
        }

        $this->actingAs($this->barbero->user)->get(route('agenda'))
            ->assertOk()
            ->assertDontSee('name="estado"', false);
    }

    // ---------------------------------------------------------- JSON del formulario

    public function test_la_disponibilidad_responde_las_horas_libres_y_la_duracion(): void
    {
        $this->turno('2026-11-03 10:00'); // 10:00–10:45

        $json = $this->actingAs($this->admin)->getJson(route('turnos.disponibilidad', [
            'barbero_id' => $this->barbero->id, 'fecha' => '2026-11-03', 'servicios' => [$this->corte->id],
        ]))->assertOk()->json();

        $this->assertSame(45, $json['duracion']);
        $this->assertContains('06:00', $json['horas']);
        $this->assertContains('09:15', $json['horas']);      // 09:15–10:00: termina justo cuando empieza el otro
        $this->assertNotContains('10:00', $json['horas']);
        $this->assertContains('10:45', $json['horas']);
    }

    public function test_al_editar_la_disponibilidad_cuenta_el_propio_turno_como_libre(): void
    {
        $turno = $this->turno('2026-11-03 10:00');

        $json = $this->actingAs($this->admin)->getJson(route('turnos.disponibilidad', [
            'barbero_id' => $this->barbero->id, 'fecha' => '2026-11-03',
            'servicios' => [$this->corte->id], 'ignorar' => $turno->id,
        ]))->json();

        $this->assertContains('10:00', $json['horas']);
    }

    public function test_la_disponibilidad_exige_sus_datos(): void
    {
        $this->actingAs($this->admin)->getJson(route('turnos.disponibilidad'))->assertStatus(422);
    }

    public function test_busca_clientes_por_nombre_o_telefono(): void
    {
        Cliente::factory()->create(['nombre' => 'Ana Ruiz', 'telefono' => '573001112222']);
        Cliente::factory()->create(['nombre' => 'Beto Gómez', 'telefono' => '573009998888']);

        $this->actingAs($this->admin);

        $this->getJson(route('clientes.buscar', ['q' => 'ana']))
            ->assertOk()->assertJsonCount(1)->assertJsonPath('0.nombre', 'Ana Ruiz');

        $this->getJson(route('clientes.buscar', ['q' => '9998888']))
            ->assertJsonPath('0.nombre', 'Beto Gómez');

        // Con menos de 2 letras no se busca
        $this->getJson(route('clientes.buscar', ['q' => 'a']))->assertExactJson([]);
    }

    // --------------------------------------------------------------------- menú

    public function test_el_menu_del_admin_ya_incluye_turnos_y_el_del_barbero_no(): void
    {
        $this->actingAs($this->admin)->get(route('dashboard'))->assertSee('bi-calendar3', false)->assertSee('Turnos');

        $this->actingAs($this->barbero->user)->get(route('agenda'))
            ->assertSee('Mi agenda')
            ->assertDontSee(route('turnos.index'), false);
    }
}
