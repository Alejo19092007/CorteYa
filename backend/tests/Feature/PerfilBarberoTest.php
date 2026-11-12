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
 * Perfil del barbero: ve sus datos y cambia SOLO su horario (nunca el de otro, ni su cuenta ni si está activo).
 * Reloj congelado en domingo 15 de noviembre de 2026, 10:00.
 */
class PerfilBarberoTest extends TestCase
{
    use RefreshDatabase;

    private Barbero $camilo;

    private Barbero $andres;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-11-15 10:00:00'));

        $this->camilo = Barbero::factory()->create(['telefono' => '573001112233', 'activo' => true]);
        $this->andres = Barbero::factory()->create();

        // Camilo trabaja de lunes a viernes de 9:00 a 18:00; Andrés, el lunes de 6:00 a 22:00
        foreach (range(1, 5) as $dia) {
            Horario::create(['barbero_id' => $this->camilo->id, 'dia_semana' => $dia, 'hora_inicio' => '09:00:00', 'hora_fin' => '18:00:00']);
        }
        Horario::create(['barbero_id' => $this->andres->id, 'dia_semana' => 1, 'hora_inicio' => '06:00:00', 'hora_fin' => '22:00:00']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** Cuerpo del formulario: dia => [inicio, fin] para los días que trabaja. */
    private function horario(array $dias): array
    {
        $horarios = [];
        foreach ($dias as $dia => [$inicio, $fin]) {
            $horarios[$dia] = ['trabaja' => 1, 'inicio' => $inicio, 'fin' => $fin];
        }

        return ['horarios' => $horarios];
    }

    private function horariosDe(Barbero $barbero): array
    {
        return $barbero->horarios()->orderBy('dia_semana')->get()
            ->mapWithKeys(fn ($h) => [$h->dia_semana => substr($h->hora_inicio, 0, 5).'-'.substr($h->hora_fin, 0, 5)])->all();
    }

    public function test_el_barbero_ve_sus_datos_y_su_horario(): void
    {
        $this->actingAs($this->camilo->user)
            ->get(route('mi-perfil'))
            ->assertOk()
            ->assertSee($this->camilo->user->name)
            ->assertSee($this->camilo->user->email)
            ->assertSee('573001112233')
            ->assertSee('Guardar mi horario')
            ->assertSee('value="09:00"', false);
    }

    public function test_el_menu_del_barbero_tiene_mi_perfil(): void
    {
        $this->actingAs($this->camilo->user)->get(route('agenda'))->assertSee('Mi perfil');
    }

    public function test_el_barbero_cambia_su_horario(): void
    {
        $this->actingAs($this->camilo->user)
            ->put(route('mi-perfil.horario'), $this->horario([1 => ['08:00', '12:00'], 6 => ['10:00', '16:00']]))
            ->assertRedirect(route('mi-perfil'))
            ->assertSessionHas('exito');

        $this->assertSame([1 => '08:00-12:00', 6 => '10:00-16:00'], $this->horariosDe($this->camilo));
    }

    public function test_no_toca_el_horario_de_otro_barbero(): void
    {
        $antes = $this->horariosDe($this->andres);

        $this->actingAs($this->camilo->user)
            ->put(route('mi-perfil.horario'), $this->horario([2 => ['08:00', '12:00']]))
            ->assertRedirect(route('mi-perfil'));

        $this->assertSame($antes, $this->horariosDe($this->andres));
    }

    public function test_no_puede_cambiar_su_cuenta_ni_su_estado_aunque_lo_mande(): void
    {
        $correo = $this->camilo->user->email;

        $this->actingAs($this->camilo->user)
            ->put(route('mi-perfil.horario'), $this->horario([1 => ['08:00', '12:00']]) + [
                'name' => 'Hackeado',
                'email' => 'otro@correo.com',
                'password' => 'nueva-clave-123',
                'telefono' => '573009999999',
                'activo' => 0,
                'user_id' => $this->andres->user_id,
            ])
            ->assertRedirect(route('mi-perfil'));

        $this->camilo->refresh()->load('user');
        $this->assertSame($correo, $this->camilo->user->email);
        $this->assertNotSame('Hackeado', $this->camilo->user->name);
        $this->assertSame('573001112233', $this->camilo->telefono);
        $this->assertTrue($this->camilo->activo);
    }

    public function test_un_horario_incoherente_no_se_guarda(): void
    {
        $antes = $this->horariosDe($this->camilo);

        $this->actingAs($this->camilo->user)
            ->put(route('mi-perfil.horario'), $this->horario([1 => ['18:00', '09:00']]))
            ->assertSessionHasErrors();

        $this->actingAs($this->camilo->user)
            ->put(route('mi-perfil.horario'), ['horarios' => []])
            ->assertSessionHasErrors();

        $this->assertSame($antes, $this->horariosDe($this->camilo));
    }

    public function test_no_deja_fuera_un_turno_ya_reservado(): void
    {
        $corte = Servicio::factory()->create(['duracion_min' => 45, 'precio' => 25000]);
        // Lunes 16 de noviembre a las 10:00, confirmado
        Turno::factory()->para($this->camilo, Cliente::factory()->create(), Carbon::parse('2026-11-16 10:00:00'), collect([$corte]))
            ->estado(Turno::CONFIRMADO)->create();

        $antes = $this->horariosDe($this->camilo);

        // Quitar el lunes dejaría el turno huérfano
        $this->actingAs($this->camilo->user)
            ->put(route('mi-perfil.horario'), $this->horario([2 => ['09:00', '18:00']]))
            ->assertSessionHas('error');

        $this->assertSame($antes, $this->horariosDe($this->camilo));

        // Mantener el lunes con el turno adentro sí se puede
        $this->actingAs($this->camilo->user)
            ->put(route('mi-perfil.horario'), $this->horario([1 => ['09:00', '12:00']]))
            ->assertSessionHas('exito');
    }

    public function test_el_admin_y_el_visitante_no_entran(): void
    {
        $this->actingAs(User::factory()->admin()->create())->get(route('mi-perfil'))->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->put(route('mi-perfil.horario'), $this->horario([1 => ['08:00', '12:00']]))->assertForbidden();

        auth()->logout();
        $this->get(route('mi-perfil'))->assertRedirect(route('login'));
    }

    public function test_el_formulario_del_dueno_sigue_mostrando_el_horario(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('barberos.edit', $this->camilo))
            ->assertOk()
            ->assertSee('Horario de trabajo')
            ->assertSee('value="09:00"', false)
            ->assertSee('barbero-form.js', false);
    }
}
