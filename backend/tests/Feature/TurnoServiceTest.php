<?php

namespace Tests\Feature;

use App\Exceptions\ReglaNegocioException;
use App\Models\Barbero;
use App\Models\Cliente;
use App\Models\Horario;
use App\Models\Servicio;
use App\Models\Turno;
use App\Services\TurnoService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Pruebas de las reglas de negocio R1 a R4. El reloj se congela en
 * lunes 2 de noviembre de 2026, 10:00, para que los resultados no dependan de la hora real.
 */
class TurnoServiceTest extends TestCase
{
    use RefreshDatabase;

    private TurnoService $service;

    private Barbero $barbero;

    private Cliente $cliente;

    private Servicio $corte;   // 45 min, $25.000

    private Servicio $barba;   // 30 min, $25.000

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-11-02 10:00:00'));

        $this->service = app(TurnoService::class);
        $this->barbero = $this->barberoConJornadaCompleta();
        $this->cliente = Cliente::factory()->create();
        $this->corte = Servicio::factory()->create(['nombre' => 'Corte', 'duracion_min' => 45, 'precio' => 25000]);
        $this->barba = Servicio::factory()->create(['nombre' => 'Barba', 'duracion_min' => 30, 'precio' => 25000]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow(); // descongela el reloj
        parent::tearDown();
    }

    // ------------------------------------------------------------------ ayudas

    /** Barbero que trabaja los 7 días de 6:00 a 22:00. */
    private function barberoConJornadaCompleta(): Barbero
    {
        $barbero = Barbero::factory()->create();

        foreach (range(1, 7) as $dia) {
            Horario::factory()->create([
                'barbero_id' => $barbero->id,
                'dia_semana' => $dia,
                'hora_inicio' => '06:00:00',
                'hora_fin' => '22:00:00',
            ]);
        }

        return $barbero;
    }

    private function datos(string $hora = '10:00', ?array $servicios = null, string $fecha = '2026-11-03', ?Barbero $barbero = null): array
    {
        return [
            'barbero_id' => ($barbero ?? $this->barbero)->id,
            'cliente_id' => $this->cliente->id,
            'fecha' => $fecha,
            'hora_inicio' => $hora,
            'servicios' => $servicios ?? [$this->corte->id],
        ];
    }

    private function crear(string $hora = '10:00', ?array $servicios = null, string $fecha = '2026-11-03', string $origen = Turno::ORIGEN_PANEL): Turno
    {
        return $this->service->crear($this->datos($hora, $servicios, $fecha), $origen);
    }

    /** Comprueba que la acción lance ReglaNegocioException con un mensaje que empiece así. */
    private function assertRegla(callable $accion, string $mensajeEmpieza): void
    {
        try {
            $accion();
        } catch (ReglaNegocioException $e) {
            $this->assertStringStartsWith($mensajeEmpieza, $e->getMessage());

            return;
        }

        $this->fail("Se esperaba ReglaNegocioException: {$mensajeEmpieza}");
    }

    // ---------------------------------------------------------------------- R2

    public function test_r2_calcula_hora_fin_y_precio_total(): void
    {
        $turno = $this->crear('10:00', [$this->corte->id, $this->barba->id]);

        $this->assertSame('10:00:00', $turno->hora_inicio);
        $this->assertSame('11:15:00', $turno->hora_fin);   // 45 + 30 = 75 min
        $this->assertEquals(50000, $turno->precio_total);  // 25.000 + 25.000
        $this->assertCount(2, $turno->servicios);
    }

    public function test_r2_congela_precio_y_duracion_en_la_pivote(): void
    {
        $turno = $this->crear();

        // El precio del servicio sube después de reservar
        $this->corte->update(['precio' => 40000, 'duracion_min' => 60]);

        $linea = $turno->fresh()->servicios->first();
        $this->assertEquals(25000, $linea->pivot->precio);
        $this->assertSame(45, (int) $linea->pivot->duracion_min);
    }

    public function test_estado_inicial_segun_el_origen(): void
    {
        // Decisión 2: panel → confirmado; WhatsApp → pendiente
        $this->assertSame(Turno::CONFIRMADO, $this->crear('10:00', origen: Turno::ORIGEN_PANEL)->estado);
        $this->assertSame(Turno::PENDIENTE, $this->crear('12:00', origen: Turno::ORIGEN_WHATSAPP)->estado);
    }

    public function test_rechaza_pedir_un_turno_sin_servicios(): void
    {
        $this->assertRegla(fn () => $this->crear('10:00', []), 'Debes elegir al menos un servicio');
    }

    public function test_rechaza_un_servicio_inactivo(): void
    {
        $this->barba->update(['activo' => false]);

        $this->assertRegla(
            fn () => $this->crear('10:00', [$this->corte->id, $this->barba->id]),
            'Uno o más servicios no están disponibles'
        );
    }

    // ---------------------------------------------------------------------- R1

    public function test_r1_rechaza_un_turno_que_se_cruza(): void
    {
        $this->crear('10:00'); // 10:00–10:45

        $this->assertRegla(
            fn () => $this->crear('10:30'),
            'El barbero no está disponible en ese horario (ya tiene un turno de 10:00 a 10:45)'
        );
    }

    public function test_r1_rechaza_un_turno_que_contiene_a_otro(): void
    {
        $this->crear('10:15', [$this->barba->id]); // 10:15–10:45

        // 10:00–11:15 envuelve al turno existente
        $this->assertRegla(fn () => $this->crear('10:00', [$this->corte->id, $this->barba->id]), 'El barbero no está disponible');
    }

    public function test_r1_permite_turnos_pegados_por_los_dos_lados(): void
    {
        $this->crear('10:00'); // 10:00–10:45

        // Cada borde se prueba por separado: son dos condiciones distintas de la consulta
        $despues = $this->crear('10:45'); // empieza justo cuando el otro termina
        $antes = $this->crear('09:15');   // 09:15 + 45 min: termina justo cuando el otro empieza

        $this->assertSame('10:45:00', $despues->hora_inicio);
        $this->assertSame('10:00:00', $antes->hora_fin);
    }

    public function test_r1_otro_barbero_puede_tener_el_mismo_horario(): void
    {
        $this->crear('10:00');
        $otro = $this->barberoConJornadaCompleta();

        $turno = $this->service->crear($this->datos('10:00', barbero: $otro), Turno::ORIGEN_PANEL);

        $this->assertSame($otro->id, $turno->barbero_id);
    }

    public function test_r1_un_turno_cancelado_libera_el_horario(): void
    {
        $turno = $this->crear('10:00');
        $this->service->cancelar($turno, TurnoService::CANCELA_ADMIN);

        $nuevo = $this->crear('10:00'); // ya no choca

        $this->assertSame(Turno::CONFIRMADO, $nuevo->estado);
    }

    public function test_r1_rechaza_un_turno_fuera_de_la_jornada(): void
    {
        $mensaje = 'El barbero no está disponible en ese horario (su jornada ese día es de 06:00 a 22:00)';

        $this->assertRegla(fn () => $this->crear('05:00'), $mensaje);          // antes de abrir
        $this->assertRegla(fn () => $this->crear('21:30'), $mensaje);          // 21:30 + 45 min = 22:15 > 22:00
        $this->assertRegla(fn () => $this->crear('23:45', [$this->corte->id]), $mensaje); // cruza la medianoche
    }

    public function test_r1_permite_terminar_justo_al_cierre(): void
    {
        $turno = $this->crear('21:15'); // 21:15 + 45 min = 22:00

        $this->assertSame('22:00:00', $turno->hora_fin);
    }

    public function test_r1_rechaza_un_dia_en_que_el_barbero_no_trabaja(): void
    {
        // 2026-11-03 es martes (día 2): se le quita ese horario
        Horario::where('barbero_id', $this->barbero->id)->where('dia_semana', 2)->delete();

        $this->assertRegla(fn () => $this->crear('10:00'), 'El barbero no está disponible en ese horario (no trabaja ese día)');
    }

    public function test_rechaza_a_un_barbero_inactivo(): void
    {
        $this->barbero->update(['activo' => false]);

        $this->assertRegla(fn () => $this->crear('10:00'), 'El barbero no está activo');
    }

    // ---------------------------------------------------------------------- R4

    public function test_r4_transiciones_validas(): void
    {
        $turno = $this->crear('12:00', origen: Turno::ORIGEN_WHATSAPP); // pendiente
        $turno = $this->service->cambiarEstado($turno, Turno::CONFIRMADO);
        $this->assertSame(Turno::CONFIRMADO, $turno->estado);

        Carbon::setTestNow(Carbon::parse('2026-11-03 12:05:00')); // ya pasó la hora del turno
        $turno = $this->service->cambiarEstado($turno, Turno::ATENDIDO);
        $this->assertSame(Turno::ATENDIDO, $turno->estado);
    }

    public function test_r4_transiciones_invalidas(): void
    {
        $pendiente = $this->crear('09:00', origen: Turno::ORIGEN_WHATSAPP);
        $confirmado = $this->crear('12:00');
        Carbon::setTestNow(Carbon::parse('2026-11-04 08:00:00')); // todo el 3 de noviembre quedó atrás

        // pendiente no pasa directo a atendido ni a no asistió
        $this->assertRegla(fn () => $this->service->cambiarEstado($pendiente, Turno::ATENDIDO), 'Cambio de estado no permitido');
        $this->assertRegla(fn () => $this->service->cambiarEstado($pendiente, Turno::NO_ASISTIO), 'Cambio de estado no permitido');

        // confirmado no vuelve a pendiente
        $this->assertRegla(fn () => $this->service->cambiarEstado($confirmado, Turno::PENDIENTE), 'Cambio de estado no permitido');

        // de atendido no se sale
        $atendido = $this->service->cambiarEstado($confirmado, Turno::ATENDIDO);
        $this->assertRegla(fn () => $this->service->cambiarEstado($atendido, Turno::CONFIRMADO), 'Cambio de estado no permitido');
        $this->assertRegla(fn () => $this->service->cambiarEstado($atendido, Turno::NO_ASISTIO), 'Cambio de estado no permitido');
    }

    public function test_r4_de_cancelado_no_se_sale(): void
    {
        $turno = $this->service->cancelar($this->crear('10:00'), TurnoService::CANCELA_ADMIN);

        $this->assertRegla(fn () => $this->service->cambiarEstado($turno, Turno::CONFIRMADO), 'Cambio de estado no permitido');
    }

    public function test_cambiar_estado_no_sirve_para_cancelar(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service->cambiarEstado($this->crear('10:00'), Turno::CANCELADO);
    }

    public function test_atendido_y_no_asistio_esperan_la_hora_del_turno(): void
    {
        // Turno de HOY a las 11:00; ahora son las 10:00
        $turno = $this->crear('11:00', fecha: '2026-11-02');

        $this->assertRegla(fn () => $this->service->cambiarEstado($turno, Turno::ATENDIDO), 'Aún no ha llegado la hora del turno');
        $this->assertRegla(fn () => $this->service->cambiarEstado($turno, Turno::NO_ASISTIO), 'Aún no ha llegado la hora del turno');

        Carbon::setTestNow(Carbon::parse('2026-11-02 11:00:00')); // llegó la hora exacta

        $this->assertSame(Turno::NO_ASISTIO, $this->service->cambiarEstado($turno, Turno::NO_ASISTIO)->estado);
    }

    // ---------------------------------------------------------------------- R3

    public function test_r3_el_cliente_no_puede_cancelar_con_menos_de_2_horas(): void
    {
        $turno = $this->crear('11:30', fecha: '2026-11-02'); // faltan 1 h 30 min

        $this->assertRegla(
            fn () => $this->service->cancelar($turno, TurnoService::CANCELA_CLIENTE),
            'Ya no es posible cancelar este turno'
        );
        $this->assertSame(Turno::CONFIRMADO, $turno->fresh()->estado);
    }

    public function test_r3_el_cliente_si_puede_cancelar_con_2_horas_o_mas(): void
    {
        $justo = $this->crear('12:00', fecha: '2026-11-02');   // faltan exactamente 2 h
        $this->assertSame(Turno::CANCELADO, $this->service->cancelar($justo, TurnoService::CANCELA_CLIENTE)->estado);

        $lejos = $this->crear('15:00', fecha: '2026-11-02');   // faltan 5 h
        $this->assertSame(Turno::CANCELADO, $this->service->cancelar($lejos, TurnoService::CANCELA_CLIENTE)->estado);
    }

    public function test_r3_el_cliente_no_cancela_un_turno_que_ya_paso(): void
    {
        $turno = $this->crear('09:00', fecha: '2026-11-02'); // empezó hace una hora

        $this->assertRegla(
            fn () => $this->service->cancelar($turno, TurnoService::CANCELA_CLIENTE),
            'Ya no es posible cancelar este turno'
        );
    }

    public function test_r3_el_admin_cancela_en_cualquier_momento(): void
    {
        // Decisión 1: R3 no aplica al admin
        $turno = $this->crear('10:30', fecha: '2026-11-02'); // faltan 30 min

        $this->assertSame(Turno::CANCELADO, $this->service->cancelar($turno, TurnoService::CANCELA_ADMIN)->estado);
    }

    public function test_no_se_cancela_un_turno_atendido(): void
    {
        $turno = $this->crear('09:00', fecha: '2026-11-02');
        $turno = $this->service->cambiarEstado($turno, Turno::ATENDIDO);

        // Ni el admin: R4 lo impide
        $this->assertRegla(fn () => $this->service->cancelar($turno, TurnoService::CANCELA_ADMIN), 'Cambio de estado no permitido');
    }

    // --------------------------------------------------------------- actualizar

    public function test_actualizar_no_choca_consigo_mismo(): void
    {
        $turno = $this->crear('10:00'); // 10:00–10:45

        // Mismo horario, solo agrega un servicio: el turno se ignora a sí mismo en R1
        $editado = $this->service->actualizar($turno, $this->datos('10:00', [$this->corte->id, $this->barba->id]));

        $this->assertSame('11:15:00', $editado->hora_fin);
        $this->assertEquals(50000, $editado->precio_total);
        $this->assertCount(2, $editado->servicios);
    }

    public function test_actualizar_sigue_validando_los_cruces_con_otros_turnos(): void
    {
        $this->crear('10:00');                 // 10:00–10:45
        $otro = $this->crear('12:00');

        $this->assertRegla(fn () => $this->service->actualizar($otro, $this->datos('10:30')), 'El barbero no está disponible');
    }

    public function test_actualizar_conserva_el_precio_congelado(): void
    {
        $turno = $this->crear('10:00');
        $this->corte->update(['precio' => 40000]); // el precio actual sube

        // Solo se cambia la hora: el corte sigue costando lo pactado
        $editado = $this->service->actualizar($turno, $this->datos('11:00'));

        $this->assertEquals(25000, $editado->precio_total);
        $this->assertEquals(25000, $editado->servicios->first()->pivot->precio);
    }

    public function test_actualizar_un_servicio_nuevo_usa_el_precio_vigente(): void
    {
        $turno = $this->crear('10:00');
        $this->barba->update(['precio' => 30000]);

        $editado = $this->service->actualizar($turno, $this->datos('10:00', [$this->corte->id, $this->barba->id]));

        $this->assertEquals(55000, $editado->precio_total); // 25.000 (congelado) + 30.000 (vigente)
    }

    public function test_no_se_edita_un_turno_atendido_ni_cancelado(): void
    {
        $atendido = $this->service->cambiarEstado($this->crear('09:00', fecha: '2026-11-02'), Turno::ATENDIDO);
        $cancelado = $this->service->cancelar($this->crear('12:00'), TurnoService::CANCELA_ADMIN);

        $this->assertRegla(fn () => $this->service->actualizar($atendido, $this->datos('09:00', fecha: '2026-11-02')), 'Este turno ya no se puede modificar');
        $this->assertRegla(fn () => $this->service->actualizar($cancelado, $this->datos('12:00')), 'Este turno ya no se puede modificar');
    }

    // ------------------------------------------------------------ disponibilidad

    public function test_disponibilidad_excluye_las_horas_ocupadas(): void
    {
        $this->crear('10:00'); // ocupa 10:00–10:45

        $libres = $this->service->disponibilidad($this->barbero->id, '2026-11-03', 30);

        $this->assertSame('06:00', $libres[0]);              // abre a las 6
        $this->assertContains('09:30', $libres);             // 09:30–10:00: termina justo cuando empieza el otro
        $this->assertNotContains('09:45', $libres);          // 09:45–10:15 se cruza
        $this->assertNotContains('10:00', $libres);
        $this->assertNotContains('10:30', $libres);          // 10:30–11:00 se cruza
        $this->assertContains('10:45', $libres);             // empieza justo cuando el otro termina
        $this->assertContains('21:30', $libres);             // 21:30–22:00 todavía cabe
        $this->assertNotContains('21:45', $libres);          // 21:45–22:15 se pasa del cierre
    }

    public function test_disponibilidad_no_ofrece_horas_que_ya_pasaron(): void
    {
        // Hoy, a las 10:00 (reloj congelado)
        $libres = $this->service->disponibilidad($this->barbero->id, '2026-11-02', 30);

        $this->assertSame('10:00', $libres[0]);
        $this->assertNotContains('09:45', $libres);
    }

    public function test_disponibilidad_depende_de_la_duracion(): void
    {
        $this->crear('10:00'); // 10:00–10:45

        // Un combo de 75 min no cabe en el hueco de 09:00 a 10:00
        $this->assertNotContains('09:00', $this->service->disponibilidad($this->barbero->id, '2026-11-03', 75));
        $this->assertContains('09:00', $this->service->disponibilidad($this->barbero->id, '2026-11-03', 60));
    }

    public function test_disponibilidad_vacia_si_no_trabaja_o_esta_inactivo(): void
    {
        Horario::where('barbero_id', $this->barbero->id)->where('dia_semana', 2)->delete();
        $this->assertSame([], $this->service->disponibilidad($this->barbero->id, '2026-11-03', 30));

        $this->barbero->update(['activo' => false]);
        $this->assertSame([], $this->service->disponibilidad($this->barbero->id, '2026-11-04', 30));
    }

    // -------------------------------------------------- presentación de la excepción

    public function test_la_excepcion_es_422_en_la_api_y_alerta_en_el_panel(): void
    {
        Route::middleware('web')->get('/_regla', fn () => throw new ReglaNegocioException('Mensaje de prueba'));
        Route::middleware('web')->get('/api/_regla', fn () => throw new ReglaNegocioException('Mensaje de prueba'));

        // API: 422 con JSON
        $this->getJson('/_regla')->assertStatus(422)->assertExactJson(['message' => 'Mensaje de prueba']);
        $this->get('/api/_regla')->assertStatus(422)->assertExactJson(['message' => 'Mensaje de prueba']);

        // Panel: vuelve a la página anterior con el mensaje en la sesión
        $this->from('/login')->get('/_regla')
            ->assertRedirect('/login')
            ->assertSessionHas('error', 'Mensaje de prueba');
    }
}
