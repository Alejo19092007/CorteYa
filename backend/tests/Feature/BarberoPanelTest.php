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
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Pruebas del CRUD de barberos (cuenta + perfil + horarios).
 * Reloj congelado en lunes 2 de noviembre de 2026, 10:00.
 */
class BarberoPanelTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-11-02 10:00:00'));
        $this->admin = User::factory()->admin()->create();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** Horarios de lunes a viernes, de 9:00 a 18:00, en el formato del formulario. */
    private function horarios(array $cambios = []): array
    {
        $horarios = [];
        foreach (range(1, 5) as $dia) {
            $horarios[$dia] = ['trabaja' => '1', 'inicio' => '09:00', 'fin' => '18:00'];
        }

        return $cambios + $horarios;
    }

    private function datos(array $cambios = []): array
    {
        return array_merge([
            'name' => 'Andrés Gómez',
            'email' => 'andres@corteya.com',
            'password' => 'clave-segura-1',
            'telefono' => '3101234567',
            'activo' => 1,
            'horarios' => $this->horarios(),
        ], $cambios);
    }

    /** Barbero ya guardado, con horario lunes a domingo de 6:00 a 22:00. */
    private function barbero(string $nombre = 'Camilo Rojas', string $correo = 'camilo@corteya.com'): Barbero
    {
        $user = User::factory()->barbero()->create(['name' => $nombre, 'email' => $correo]);
        $barbero = Barbero::factory()->create(['user_id' => $user->id, 'telefono' => '573109990000']);

        foreach (range(1, 7) as $dia) {
            Horario::factory()->create(['barbero_id' => $barbero->id, 'dia_semana' => $dia, 'hora_inicio' => '06:00:00', 'hora_fin' => '22:00:00']);
        }

        return $barbero;
    }

    private function turnoDe(Barbero $barbero, string $inicio, string $estado = Turno::CONFIRMADO): Turno
    {
        $servicio = Servicio::factory()->create(['duracion_min' => 45, 'precio' => 25000]);

        return Turno::factory()
            ->para($barbero, Cliente::factory()->create(), Carbon::parse($inicio), collect([$servicio]))
            ->estado($estado)
            ->create();
    }

    /** Datos de edición con el horario que se quiera (formato del formulario). */
    private function datosDeEdicion(Barbero $barbero, array $horarios): array
    {
        return [
            'name' => $barbero->user->name, 'email' => $barbero->user->email, 'telefono' => $barbero->telefono,
            'activo' => 1, 'horarios' => $horarios,
        ];
    }

    // ----------------------------------------------------------------- listado

    public function test_el_listado_muestra_los_barberos(): void
    {
        $this->barbero();

        $this->actingAs($this->admin)->get(route('barberos.index'))
            ->assertOk()
            ->assertSee('Camilo Rojas')
            ->assertSee('camilo@corteya.com')
            ->assertSee('573109990000');
    }

    public function test_busca_por_nombre_correo_o_telefono_y_filtra_por_estado(): void
    {
        $this->barbero('Camilo Rojas', 'camilo@corteya.com');
        $diego = $this->barbero('Diego Herrera', 'diego@corteya.com');
        $diego->update(['activo' => false, 'telefono' => '573205556666']);

        $this->actingAs($this->admin);

        $this->get(route('barberos.index', ['q' => 'camilo']))->assertSee('Camilo Rojas')->assertDontSee('Diego Herrera');
        $this->get(route('barberos.index', ['q' => 'diego@']))->assertSee('Diego Herrera')->assertDontSee('Camilo Rojas');
        $this->get(route('barberos.index', ['q' => '5556666']))->assertSee('Diego Herrera')->assertDontSee('Camilo Rojas');
        $this->get(route('barberos.index', ['activo' => '0']))->assertSee('Diego Herrera')->assertDontSee('Camilo Rojas');
    }

    public function test_pagina_y_conserva_los_filtros(): void
    {
        foreach (range(1, 12) as $i) {
            $this->barbero("Barbero {$i}", "barbero{$i}@corteya.com");
        }

        $this->actingAs($this->admin)->get(route('barberos.index', ['activo' => '1']))
            ->assertOk()
            ->assertSee('activo=1&amp;page=2', false);
    }

    public function test_el_barbero_no_entra_a_la_gestion_de_barberos(): void
    {
        $this->actingAs(User::factory()->barbero()->create());

        $this->get(route('barberos.index'))->assertForbidden();
        $this->post(route('barberos.store'), $this->datos())->assertForbidden();
    }

    // ------------------------------------------------------------------- crear

    public function test_crea_la_cuenta_el_perfil_y_los_horarios(): void
    {
        $this->actingAs($this->admin)->post(route('barberos.store'), $this->datos())
            ->assertRedirect(route('barberos.index'))
            ->assertSessionHas('exito', 'Barbero Andrés Gómez creado.');

        $user = User::where('email', 'andres@corteya.com')->firstOrFail();
        $this->assertSame('barbero', $user->rol);
        $this->assertTrue(Hash::check('clave-segura-1', $user->password)); // se guarda cifrada

        $barbero = $user->barbero;
        $this->assertSame('573101234567', $barbero->telefono); // se agrega el 57
        $this->assertTrue($barbero->activo);
        $this->assertCount(5, $barbero->horarios);
        $this->assertSame([1, 2, 3, 4, 5], $barbero->horarios->pluck('dia_semana')->sort()->values()->all());
        $this->assertSame('09:00:00', $barbero->horarios->first()->hora_inicio);
    }

    public function test_el_barbero_creado_puede_iniciar_sesion(): void
    {
        $this->actingAs($this->admin)->post(route('barberos.store'), $this->datos());
        auth()->logout();

        $this->post('/login', ['email' => 'andres@corteya.com', 'password' => 'clave-segura-1'])
            ->assertRedirect(route('agenda'));
    }

    public function test_el_telefono_acepta_formato_con_espacios_y_con_57(): void
    {
        $this->actingAs($this->admin);

        $this->post(route('barberos.store'), $this->datos(['email' => 'a@x.com', 'telefono' => '310 123 4567']));
        $this->post(route('barberos.store'), $this->datos(['email' => 'b@x.com', 'telefono' => '+57 310-123-4568']));

        $this->assertSame('573101234567', User::where('email', 'a@x.com')->first()->barbero->telefono);
        $this->assertSame('573101234568', User::where('email', 'b@x.com')->first()->barbero->telefono);
    }

    public function test_valida_los_campos_en_espanol(): void
    {
        $this->actingAs($this->admin)->from(route('barberos.create'))->post(route('barberos.store'), [])
            ->assertRedirect(route('barberos.create'))
            ->assertSessionHasErrors([
                'name' => 'El campo nombre es obligatorio.',
                'email' => 'El campo correo es obligatorio.',
                'password' => 'El campo contraseña es obligatorio.',
                'telefono' => 'El campo teléfono es obligatorio.',
                'horarios' => 'El campo horarios es obligatorio.',
            ]);
    }

    public function test_rechaza_correo_repetido_telefono_invalido_y_clave_corta(): void
    {
        $this->barbero('Camilo Rojas', 'camilo@corteya.com');

        $this->actingAs($this->admin)->post(route('barberos.store'), $this->datos([
            'email' => 'camilo@corteya.com', 'telefono' => '12345', 'password' => 'corta',
        ]))->assertSessionHasErrors(['email' => 'Ya existe un usuario con ese correo.', 'telefono', 'password']);

        $this->assertDatabaseCount('barberos', 1);
    }

    public function test_valida_los_horarios(): void
    {
        $this->actingAs($this->admin);

        // Fin antes del inicio
        $this->post(route('barberos.store'), $this->datos(['horarios' => $this->horarios([1 => ['trabaja' => '1', 'inicio' => '18:00', 'fin' => '09:00']])]))
            ->assertSessionHasErrors(['horarios.1' => 'Lunes: la hora de fin debe ser posterior a la de inicio.']);

        // Día marcado sin horas
        $this->post(route('barberos.store'), $this->datos(['horarios' => $this->horarios([2 => ['trabaja' => '1', 'inicio' => '', 'fin' => '']])]))
            ->assertSessionHasErrors(['horarios.2' => 'Martes: indica la hora de inicio y la de fin.']);

        // Ningún día
        $this->post(route('barberos.store'), $this->datos(['horarios' => [1 => ['inicio' => '09:00', 'fin' => '18:00']]]))
            ->assertSessionHasErrors(['horarios' => 'El barbero debe trabajar al menos un día.']);

        $this->assertDatabaseCount('barberos', 0);
        $this->assertDatabaseCount('users', 1); // solo el admin: no queda un usuario suelto
    }

    public function test_si_algo_falla_no_queda_un_usuario_suelto(): void
    {
        // Día inexistente (9): se ignora y el barbero queda con los 5 días válidos
        $this->actingAs($this->admin)->post(route('barberos.store'), $this->datos([
            'horarios' => $this->horarios([9 => ['trabaja' => '1', 'inicio' => '09:00', 'fin' => '18:00']]),
        ]));

        $this->assertCount(5, Barbero::firstOrFail()->horarios);
    }

    // ------------------------------------------------------------------ editar

    public function test_la_pantalla_de_edicion_trae_los_datos_y_el_horario(): void
    {
        $barbero = $this->barbero();

        $this->actingAs($this->admin)->get(route('barberos.edit', $barbero))
            ->assertOk()
            ->assertSee('Camilo Rojas')
            ->assertSee('camilo@corteya.com')
            ->assertSee('value="06:00"', false)
            ->assertSee('value="22:00"', false);
    }

    public function test_actualiza_los_datos_sin_cambiar_la_contrasena_si_va_vacia(): void
    {
        $barbero = $this->barbero();
        $claveAnterior = $barbero->user->password;

        $this->actingAs($this->admin)->put(route('barberos.update', $barbero), $this->datosDeEdicion($barbero, $this->horarios()) + [
            'password' => '',
        ])->assertRedirect(route('barberos.index'));

        $this->assertSame($claveAnterior, $barbero->user->fresh()->password);
    }

    public function test_cambia_nombre_correo_telefono_y_contrasena(): void
    {
        $barbero = $this->barbero();

        $this->actingAs($this->admin)->put(route('barberos.update', $barbero), [
            'name' => 'Camilo Andrés Rojas', 'email' => 'nuevo@corteya.com', 'password' => 'otra-clave-123',
            'telefono' => '3001112222', 'activo' => 1, 'horarios' => $this->horarios(),
        ])->assertRedirect(route('barberos.index'));

        $barbero->refresh();
        $this->assertSame('Camilo Andrés Rojas', $barbero->user->name);
        $this->assertSame('nuevo@corteya.com', $barbero->user->email);
        $this->assertTrue(Hash::check('otra-clave-123', $barbero->user->password));
        $this->assertSame('573001112222', $barbero->telefono);
    }

    public function test_al_editar_el_correo_puede_quedar_igual_pero_no_repetir_el_de_otro(): void
    {
        $camilo = $this->barbero('Camilo Rojas', 'camilo@corteya.com');
        $this->barbero('Diego Herrera', 'diego@corteya.com');

        $this->actingAs($this->admin);

        $this->put(route('barberos.update', $camilo), $this->datosDeEdicion($camilo, $this->horarios()))
            ->assertSessionHasNoErrors();

        $this->put(route('barberos.update', $camilo), ['email' => 'diego@corteya.com'] + $this->datosDeEdicion($camilo, $this->horarios()))
            ->assertSessionHasErrors(['email' => 'Ya existe un usuario con ese correo.']);
    }

    public function test_reemplaza_los_horarios(): void
    {
        $barbero = $this->barbero(); // 7 días de 6 a 22

        $this->actingAs($this->admin)->put(route('barberos.update', $barbero), $this->datosDeEdicion($barbero, [
            1 => ['trabaja' => '1', 'inicio' => '08:00', 'fin' => '12:00'],
            6 => ['trabaja' => '1', 'inicio' => '09:00', 'fin' => '15:00'],
        ]))->assertSessionHasNoErrors();

        $horarios = $barbero->fresh()->horarios->keyBy('dia_semana');
        $this->assertCount(2, $horarios);
        $this->assertSame('08:00:00', $horarios[1]->hora_inicio);
        $this->assertSame('15:00:00', $horarios[6]->hora_fin);
    }

    public function test_no_deja_cambiar_el_horario_si_un_turno_futuro_queda_fuera(): void
    {
        $barbero = $this->barbero();
        $this->turnoDe($barbero, '2026-11-03 19:00'); // martes 19:00–19:45

        // El nuevo horario del martes termina a las 18:00: el turno quedaría fuera
        $this->actingAs($this->admin)->put(route('barberos.update', $barbero), $this->datosDeEdicion($barbero, $this->horarios()))
            ->assertSessionHas('error');

        $this->assertStringContainsString('hay un turno el 03/11/2026 de 19:00 a 19:45', session('error'));
        $this->assertSame('22:00:00', $barbero->fresh()->horarios->firstWhere('dia_semana', 2)->hora_fin); // nada cambió
    }

    public function test_no_deja_quitar_un_dia_que_tiene_turnos_futuros(): void
    {
        $barbero = $this->barbero();
        $this->turnoDe($barbero, '2026-11-07 10:00'); // sábado

        // El nuevo horario es solo de lunes a viernes
        $this->actingAs($this->admin)->put(route('barberos.update', $barbero), $this->datosDeEdicion($barbero, $this->horarios()))
            ->assertSessionHas('error');
    }

    public function test_permite_cambiar_el_horario_si_los_turnos_siguen_dentro(): void
    {
        $barbero = $this->barbero();
        $this->turnoDe($barbero, '2026-11-03 10:00'); // martes 10:00–10:45, dentro de 9 a 18

        $this->actingAs($this->admin)->put(route('barberos.update', $barbero), $this->datosDeEdicion($barbero, $this->horarios()))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('barberos.index'));
    }

    public function test_los_turnos_cancelados_o_ya_atendidos_no_bloquean_el_cambio(): void
    {
        $barbero = $this->barbero();
        $this->turnoDe($barbero, '2026-11-03 19:00', Turno::CANCELADO);
        $this->turnoDe($barbero, '2026-11-02 08:00', Turno::ATENDIDO); // lunes, ya pasó

        $this->actingAs($this->admin)->put(route('barberos.update', $barbero), $this->datosDeEdicion($barbero, $this->horarios()))
            ->assertSessionHasNoErrors();
    }

    public function test_un_error_al_editar_no_deja_cambios_a_medias(): void
    {
        $barbero = $this->barbero();
        $this->turnoDe($barbero, '2026-11-03 19:00');

        $this->actingAs($this->admin)->put(route('barberos.update', $barbero), [
            'name' => 'Nombre nuevo', 'email' => $barbero->user->email, 'telefono' => $barbero->telefono,
            'activo' => 1, 'horarios' => $this->horarios(),
        ]);

        $this->assertSame('Camilo Rojas', $barbero->user->fresh()->name); // el nombre tampoco cambió
    }

    // --------------------------------------------------------- activar / eliminar

    public function test_activa_y_desactiva(): void
    {
        $barbero = $this->barbero();

        $this->actingAs($this->admin)->patch(route('barberos.activo', $barbero))->assertSessionHas('exito', 'Barbero desactivado.');
        $this->assertFalse($barbero->fresh()->activo);

        $this->patch(route('barberos.activo', $barbero))->assertSessionHas('exito', 'Barbero activado.');
        $this->assertTrue($barbero->fresh()->activo);
    }

    public function test_un_barbero_inactivo_no_aparece_para_reservar_turnos(): void
    {
        $this->barbero('Camilo Rojas', 'camilo@corteya.com')->update(['activo' => false]);
        $this->barbero('Diego Herrera', 'diego@corteya.com');

        $this->actingAs($this->admin)->get(route('turnos.create'))
            ->assertSee('Diego Herrera')
            ->assertDontSee('Camilo Rojas');
    }

    public function test_elimina_un_barbero_sin_turnos_junto_con_su_cuenta_y_horarios(): void
    {
        $barbero = $this->barbero();
        $userId = $barbero->user_id;

        $this->actingAs($this->admin)->delete(route('barberos.destroy', $barbero))
            ->assertRedirect(route('barberos.index'))
            ->assertSessionHas('exito', 'Barbero eliminado.');

        $this->assertDatabaseMissing('barberos', ['id' => $barbero->id]);
        $this->assertDatabaseMissing('users', ['id' => $userId]);
        $this->assertDatabaseCount('horarios', 0);
    }

    public function test_no_elimina_un_barbero_con_historial_de_turnos(): void
    {
        $barbero = $this->barbero();
        $this->turnoDe($barbero, '2026-10-20 10:00', Turno::ATENDIDO);

        $this->actingAs($this->admin)->delete(route('barberos.destroy', $barbero))
            ->assertSessionHas('error');

        $this->assertStringContainsString('Desactívalo', session('error'));
        $this->assertDatabaseHas('barberos', ['id' => $barbero->id]);
        $this->assertDatabaseHas('users', ['id' => $barbero->user_id]);
    }

    public function test_el_listado_solo_ofrece_eliminar_si_nunca_tuvo_turnos(): void
    {
        $conTurnos = $this->barbero('Camilo Rojas', 'camilo@corteya.com');
        $this->turnoDe($conTurnos, '2026-10-20 10:00', Turno::ATENDIDO);
        $libre = $this->barbero('Diego Herrera', 'diego@corteya.com');

        // Se busca el atributo exacto del botón: la URL a secas es prefijo de otras (editar, activar...)
        $this->actingAs($this->admin)->get(route('barberos.index'))
            ->assertDontSee('data-accion="'.route('barberos.destroy', $conTurnos).'"', false)
            ->assertSee('data-accion="'.route('barberos.destroy', $libre).'"', false);
    }

    public function test_las_pantallas_de_crear_abre_con_horario_sugerido(): void
    {
        $this->actingAs($this->admin)->get(route('barberos.create'))
            ->assertOk()
            ->assertSee('Nuevo barbero')
            ->assertSee('value="09:00"', false)
            ->assertSee('value="18:00"', false);
    }

    public function test_tras_un_error_un_dia_desmarcado_sigue_desmarcado(): void
    {
        // Se envía solo el lunes marcado y se omite el nombre para provocar el error
        $this->actingAs($this->admin)->from(route('barberos.create'))
            ->post(route('barberos.store'), $this->datos(['name' => '', 'horarios' => [1 => ['trabaja' => '1', 'inicio' => '09:00', 'fin' => '18:00']]]));

        $html = $this->get(route('barberos.create'))->getContent();

        // En el formulario recargado solo el lunes está marcado (los demás no vuelven a su valor por defecto)
        $this->assertSame(1, preg_match_all('/name="horarios\[\d\]\[trabaja\]"[^>]*checked/', $html));
    }
}
