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
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Pruebas de la API de turnos y de la disponibilidad: lo que consume el bot de WhatsApp.
 * Reloj congelado en lunes 2 de noviembre de 2026, 10:00.
 */
class ApiTurnoTest extends TestCase
{
    use RefreshDatabase;

    private Barbero $barbero;

    private Servicio $corte;   // 45 min, $25.000

    private Servicio $barba;   // 30 min, $15.000

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-11-02 10:00:00'));
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->barbero = $this->nuevoBarbero();
        $this->corte = Servicio::factory()->create(['nombre' => 'Corte clásico', 'duracion_min' => 45, 'precio' => 25000]);
        $this->barba = Servicio::factory()->create(['nombre' => 'Barba', 'duracion_min' => 30, 'precio' => 15000]);
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

    /** Turno creado directamente (sin pasar por las reglas) para preparar escenarios. */
    private function turno(string $inicio, string $estado = Turno::CONFIRMADO, ?Cliente $cliente = null): Turno
    {
        return Turno::factory()
            ->para($this->barbero, $cliente ?? Cliente::factory()->create(), Carbon::parse($inicio), collect([$this->corte]))
            ->estado($estado)
            ->origen(Turno::ORIGEN_WHATSAPP)
            ->create();
    }

    private function datos(array $cambios = []): array
    {
        return array_merge([
            'telefono' => '573001234567',
            'nombre' => 'Daniela Silva',
            'barbero_id' => $this->barbero->id,
            'fecha' => '2026-11-03',
            'hora_inicio' => '10:00',
            'servicios' => [$this->corte->id],
        ], $cambios);
    }

    // --------------------------------------------------------------- POST (crear)

    public function test_crear_responde_201_calcula_hora_fin_y_precio_y_nace_pendiente(): void
    {
        $this->postJson('/api/turnos', $this->datos(['servicios' => [$this->corte->id, $this->barba->id]]))
            ->assertCreated()
            ->assertJsonPath('data.estado', 'pendiente')
            ->assertJsonPath('data.origen', 'whatsapp')
            ->assertJsonPath('data.fecha', '2026-11-03')
            ->assertJsonPath('data.hora_inicio', '10:00')
            ->assertJsonPath('data.hora_fin', '11:15')          // R2: 45 + 30 min
            ->assertJsonPath('data.precio_total', 40000)        // R2: 25.000 + 15.000
            ->assertJsonPath('data.cliente.telefono', '573001234567')
            ->assertJsonPath('data.cliente.nombre', 'Daniela Silva')
            ->assertJsonPath('data.barbero.id', $this->barbero->id)
            ->assertJsonCount(2, 'data.servicios');

        $this->assertDatabaseHas('turnos', ['estado' => 'pendiente', 'origen' => 'whatsapp', 'precio_total' => 40000]);
    }

    public function test_el_precio_y_la_duracion_quedan_congelados_en_el_turno(): void
    {
        $id = $this->postJson('/api/turnos', $this->datos())->json('data.id');

        $this->corte->update(['precio' => 99000, 'duracion_min' => 90]);

        $turno = Turno::with('servicios')->find($id);
        $this->assertEquals(25000, $turno->servicios->first()->pivot->precio);
        $this->assertSame(45, $turno->servicios->first()->pivot->duracion_min);
    }

    public function test_el_telefono_de_10_digitos_se_guarda_con_el_indicativo_57(): void
    {
        $this->postJson('/api/turnos', $this->datos(['telefono' => '300 123 4567']))
            ->assertCreated()
            ->assertJsonPath('data.cliente.telefono', '573001234567');

        $this->assertDatabaseHas('clientes', ['telefono' => '573001234567']);
    }

    public function test_un_cliente_que_ya_existe_se_reutiliza_y_no_necesita_nombre(): void
    {
        $existente = Cliente::factory()->create(['nombre' => 'Mario Díaz', 'telefono' => '573009998877']);

        $this->postJson('/api/turnos', $this->datos(['telefono' => '573009998877', 'nombre' => null]))
            ->assertCreated()
            ->assertJsonPath('data.cliente.id', $existente->id)
            ->assertJsonPath('data.cliente.nombre', 'Mario Díaz');

        $this->assertSame(1, Cliente::count());
    }

    public function test_un_cliente_nuevo_sin_nombre_responde_422_y_no_se_crea(): void
    {
        $this->postJson('/api/turnos', $this->datos(['nombre' => null]))
            ->assertStatus(422)
            ->assertJsonPath('message', 'Para registrar un cliente nuevo se necesita su nombre.');

        $this->assertSame(0, Cliente::count());
        $this->assertSame(0, Turno::count());
    }

    public function test_r1_un_turno_que_se_cruza_responde_422_y_no_deja_cliente_a_medias(): void
    {
        $this->turno('2026-11-03 10:00');   // ocupa 10:00 a 10:45

        $this->postJson('/api/turnos', $this->datos(['hora_inicio' => '10:30', 'telefono' => '573115550000', 'nombre' => 'Nuevo']))
            ->assertStatus(422)
            ->assertJsonPath('message', 'El barbero no está disponible en ese horario (ya tiene un turno de 10:00 a 10:45).');

        $this->assertSame(1, Turno::count());
        // La transacción deshizo también el registro del cliente
        $this->assertDatabaseMissing('clientes', ['telefono' => '573115550000']);
    }

    public function test_r1_fuera_de_la_jornada_del_barbero_responde_422(): void
    {
        $this->postJson('/api/turnos', $this->datos(['hora_inicio' => '21:30']))   // termina 22:15, él cierra a las 22:00
            ->assertStatus(422)
            ->assertJsonPath('message', 'El barbero no está disponible en ese horario (su jornada ese día es de 06:00 a 22:00).');
    }

    public function test_crear_con_datos_invalidos_responde_422(): void
    {
        $this->postJson('/api/turnos', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['telefono', 'barbero_id', 'fecha', 'hora_inicio', 'servicios']);

        $this->postJson('/api/turnos', $this->datos(['telefono' => '12345']))->assertStatus(422)->assertJsonValidationErrors(['telefono']);
        $this->postJson('/api/turnos', $this->datos(['fecha' => '2026-11-01']))->assertStatus(422)->assertJsonValidationErrors(['fecha']);
        $this->postJson('/api/turnos', $this->datos(['servicios' => [999]]))->assertStatus(422)->assertJsonValidationErrors(['servicios.0']);
        $this->postJson('/api/turnos', $this->datos(['barbero_id' => 999]))->assertStatus(422)->assertJsonValidationErrors(['barbero_id']);
    }

    public function test_un_servicio_inactivo_no_se_puede_reservar(): void
    {
        $this->barba->update(['activo' => false]);

        // Mezclado con uno activo: se rechaza todo el pedido
        $this->postJson('/api/turnos', $this->datos(['servicios' => [$this->corte->id, $this->barba->id]]))
            ->assertStatus(422)
            ->assertJsonPath('message', 'Uno o más servicios no están disponibles.');

        // Solo el inactivo: no queda ningún servicio que reservar
        $this->postJson('/api/turnos', $this->datos(['servicios' => [$this->barba->id]]))
            ->assertStatus(422)
            ->assertJsonPath('message', 'Debes elegir al menos un servicio.');

        $this->assertSame(0, Turno::count());
    }

    // ---------------------------------------------------------------- GET (listar)

    public function test_lista_solo_los_turnos_del_cliente_del_telefono(): void
    {
        $daniela = Cliente::factory()->create(['telefono' => '573001234567']);
        $otro = Cliente::factory()->create(['telefono' => '573007654321']);
        $suyo = $this->turno('2026-11-03 10:00', Turno::PENDIENTE, $daniela);
        $this->turno('2026-11-03 12:00', Turno::CONFIRMADO, $otro);

        $this->getJson('/api/turnos?telefono=573001234567')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $suyo->id)
            ->assertJsonPath('data.0.cliente.telefono', '573001234567');
    }

    public function test_acepta_el_telefono_sin_indicativo_y_ordena_del_mas_reciente_al_mas_antiguo(): void
    {
        $daniela = Cliente::factory()->create(['telefono' => '573001234567']);
        $antiguo = $this->turno('2026-11-03 10:00', Turno::CONFIRMADO, $daniela);
        $reciente = $this->turno('2026-11-05 10:00', Turno::CONFIRMADO, $daniela);

        $this->getJson('/api/turnos?telefono=3001234567')
            ->assertOk()
            ->assertJsonPath('data.0.id', $reciente->id)
            ->assertJsonPath('data.1.id', $antiguo->id);
    }

    public function test_un_telefono_que_nunca_ha_escrito_da_lista_vacia(): void
    {
        $this->getJson('/api/turnos?telefono=573119990000')->assertOk()->assertExactJson(['data' => []]);
    }

    public function test_el_filtro_de_estado_y_el_telefono_obligatorio(): void
    {
        $cliente = Cliente::factory()->create(['telefono' => '573001234567']);
        $this->turno('2026-11-03 10:00', Turno::PENDIENTE, $cliente);
        $this->turno('2026-11-04 10:00', Turno::CANCELADO, $cliente);

        $this->getJson('/api/turnos?telefono=573001234567&estado=pendiente')->assertJsonCount(1, 'data');
        $this->getJson('/api/turnos?telefono=573001234567&estado=inventado')->assertStatus(422)->assertJsonValidationErrors(['estado']);
        $this->getJson('/api/turnos')->assertStatus(422)->assertJsonValidationErrors(['telefono']);
    }

    // ---------------------------------------------------------------- PUT (editar)

    public function test_reprogramar_responde_200_y_recalcula(): void
    {
        $turno = $this->turno('2026-11-03 10:00', Turno::PENDIENTE);

        $this->putJson("/api/turnos/{$turno->id}", [
            'barbero_id' => $this->barbero->id, 'fecha' => '2026-11-04', 'hora_inicio' => '15:00',
            'servicios' => [$this->corte->id, $this->barba->id],
        ])
            ->assertOk()
            ->assertJsonPath('data.fecha', '2026-11-04')
            ->assertJsonPath('data.hora_inicio', '15:00')
            ->assertJsonPath('data.hora_fin', '16:15')
            ->assertJsonPath('data.precio_total', 40000)
            ->assertJsonPath('data.cliente.id', $turno->cliente_id);   // el cliente no cambia
    }

    public function test_un_turno_no_choca_consigo_mismo_al_reprogramarse(): void
    {
        $turno = $this->turno('2026-11-03 10:00');

        $this->putJson("/api/turnos/{$turno->id}", [
            'barbero_id' => $this->barbero->id, 'fecha' => '2026-11-03', 'hora_inicio' => '10:15', 'servicios' => [$this->corte->id],
        ])->assertOk();
    }

    public function test_reprogramar_sobre_otro_turno_responde_422(): void
    {
        $this->turno('2026-11-03 10:00');
        $otro = $this->turno('2026-11-03 14:00');

        $this->putJson("/api/turnos/{$otro->id}", [
            'barbero_id' => $this->barbero->id, 'fecha' => '2026-11-03', 'hora_inicio' => '10:30', 'servicios' => [$this->corte->id],
        ])->assertStatus(422);
    }

    public function test_un_turno_atendido_ya_no_se_puede_modificar(): void
    {
        $turno = $this->turno('2026-11-02 08:00', Turno::ATENDIDO);

        $this->putJson("/api/turnos/{$turno->id}", [
            'barbero_id' => $this->barbero->id, 'fecha' => '2026-11-03', 'hora_inicio' => '10:00', 'servicios' => [$this->corte->id],
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Este turno ya no se puede modificar.');
    }

    public function test_editar_un_turno_que_no_existe_responde_404(): void
    {
        $this->putJson('/api/turnos/999', [
            'barbero_id' => $this->barbero->id, 'fecha' => '2026-11-03', 'hora_inicio' => '10:00', 'servicios' => [$this->corte->id],
        ])->assertStatus(404);
    }

    // ------------------------------------------------------------ DELETE (cancelar)

    public function test_cancelar_con_mas_de_dos_horas_responde_204_y_no_borra_el_turno(): void
    {
        $turno = $this->turno('2026-11-02 13:00');   // faltan 3 h

        $this->deleteJson("/api/turnos/{$turno->id}")->assertNoContent();

        $this->assertSame(Turno::CANCELADO, $turno->fresh()->estado);   // se conserva el historial
    }

    public function test_r3_cancelar_con_menos_de_dos_horas_responde_422(): void
    {
        $turno = $this->turno('2026-11-02 11:00', Turno::PENDIENTE);   // falta 1 h

        $this->deleteJson("/api/turnos/{$turno->id}")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Ya no es posible cancelar este turno');

        $this->assertSame(Turno::PENDIENTE, $turno->fresh()->estado);
    }

    public function test_r3_el_limite_exacto_de_dos_horas_si_se_puede_cancelar(): void
    {
        $turno = $this->turno('2026-11-02 12:00');   // faltan exactamente 2 h

        $this->deleteJson("/api/turnos/{$turno->id}")->assertNoContent();
    }

    public function test_r4_un_turno_ya_cancelado_o_atendido_no_se_puede_cancelar(): void
    {
        $cancelado = $this->turno('2026-11-04 10:00', Turno::CANCELADO);
        $atendido = $this->turno('2026-11-02 07:00', Turno::ATENDIDO);

        $this->deleteJson("/api/turnos/{$cancelado->id}")->assertStatus(422)->assertJsonPath('message', 'Cambio de estado no permitido');
        $this->deleteJson("/api/turnos/{$atendido->id}")->assertStatus(422)->assertJsonPath('message', 'Cambio de estado no permitido');
    }

    public function test_cancelar_un_turno_que_no_existe_responde_404(): void
    {
        $this->deleteJson('/api/turnos/999')->assertStatus(404);
    }

    public function test_un_turno_cancelado_libera_el_horario(): void
    {
        $turno = $this->turno('2026-11-03 10:00');
        $this->deleteJson("/api/turnos/{$turno->id}")->assertNoContent();

        $this->postJson('/api/turnos', $this->datos())->assertCreated();
    }

    // ------------------------------------------------------------- disponibilidad

    public function test_disponibilidad_responde_200_con_las_horas_libres(): void
    {
        $this->turno('2026-11-03 10:00');   // ocupa 10:00 a 10:45

        $respuesta = $this->getJson("/api/barberos/{$this->barbero->id}/disponibilidad?fecha=2026-11-03&servicios[]={$this->corte->id}")
            ->assertOk()
            ->assertJsonPath('data.barbero_id', $this->barbero->id)
            ->assertJsonPath('data.fecha', '2026-11-03')
            ->assertJsonPath('data.duracion_min', 45);

        $horas = $respuesta->json('data.horas');

        $this->assertContains('06:00', $horas);
        $this->assertContains('09:15', $horas);       // 09:15 + 45 min termina justo a las 10:00
        $this->assertNotContains('09:30', $horas);    // terminaría 10:15: se cruza
        $this->assertNotContains('10:00', $horas);
        $this->assertNotContains('10:30', $horas);
        $this->assertContains('10:45', $horas);       // empieza justo cuando el otro termina
        $this->assertContains('21:15', $horas);       // 21:15 + 45 min = 22:00, justo al cierre
        $this->assertNotContains('21:30', $horas);    // terminaría 22:15, después del cierre
    }

    public function test_la_duracion_de_varios_servicios_se_suma(): void
    {
        $this->getJson("/api/barberos/{$this->barbero->id}/disponibilidad?fecha=2026-11-03&servicios[]={$this->corte->id}&servicios[]={$this->barba->id}")
            ->assertOk()
            ->assertJsonPath('data.duracion_min', 75);
    }

    public function test_no_se_ofrecen_horas_que_ya_pasaron(): void
    {
        // "Hoy" son las 10:00: las horas anteriores ya no se pueden reservar
        $horas = $this->getJson("/api/barberos/{$this->barbero->id}/disponibilidad?fecha=2026-11-02&servicios[]={$this->corte->id}")
            ->json('data.horas');

        $this->assertNotContains('09:45', $horas);
        $this->assertContains('10:00', $horas);
    }

    public function test_un_dia_que_el_barbero_no_trabaja_o_un_barbero_inactivo_dan_lista_vacia(): void
    {
        $this->barbero->horarios()->where('dia_semana', 2)->delete();   // martes 3 de noviembre

        $this->getJson("/api/barberos/{$this->barbero->id}/disponibilidad?fecha=2026-11-03&servicios[]={$this->corte->id}")
            ->assertOk()->assertJsonPath('data.horas', []);

        $this->barbero->update(['activo' => false]);

        $this->getJson("/api/barberos/{$this->barbero->id}/disponibilidad?fecha=2026-11-04&servicios[]={$this->corte->id}")
            ->assertOk()->assertJsonPath('data.horas', []);
    }

    public function test_disponibilidad_de_un_barbero_que_no_existe_responde_404(): void
    {
        $this->getJson("/api/barberos/999/disponibilidad?fecha=2026-11-03&servicios[]={$this->corte->id}")->assertStatus(404);
    }

    public function test_disponibilidad_con_parametros_invalidos_responde_422(): void
    {
        $base = "/api/barberos/{$this->barbero->id}/disponibilidad";

        $this->getJson($base)->assertStatus(422)->assertJsonValidationErrors(['fecha', 'servicios']);
        $this->getJson("{$base}?fecha=03-11-2026&servicios[]=1")->assertStatus(422)->assertJsonValidationErrors(['fecha']);

        $this->barba->update(['activo' => false]);
        $this->getJson("{$base}?fecha=2026-11-03&servicios[]={$this->barba->id}")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Uno o más servicios no están disponibles.');
    }

    // ------------------------------------------------------------------- barberos

    public function test_lista_solo_los_barberos_activos_sin_datos_de_su_cuenta(): void
    {
        $inactivo = Barbero::factory()->create(['activo' => false]);

        $respuesta = $this->getJson('/api/barberos')->assertOk()->assertJsonCount(1, 'data');

        $this->assertSame(['id', 'nombre'], array_keys($respuesta->json('data.0')));
        $this->assertNotContains($inactivo->id, array_column($respuesta->json('data'), 'id'));
    }
}
