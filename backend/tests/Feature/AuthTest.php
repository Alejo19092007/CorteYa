<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_muestra_el_formulario_de_login(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('CORTEYA')
            ->assertDontSee('Olvidaste'); // fuera de alcance
    }

    public function test_el_admin_entra_al_dashboard(): void
    {
        $admin = User::factory()->admin()->create(['password' => 'secreto123']);

        $this->post('/login', ['email' => $admin->email, 'password' => 'secreto123'])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($admin);
    }

    public function test_el_barbero_entra_a_su_agenda(): void
    {
        $barbero = User::factory()->barbero()->create(['password' => 'secreto123']);

        $this->post('/login', ['email' => $barbero->email, 'password' => 'secreto123'])
            ->assertRedirect(route('agenda'));
    }

    public function test_contrasena_incorrecta_da_mensaje_generico(): void
    {
        $user = User::factory()->create(['password' => 'secreto123']);

        $this->from('/login')
            ->post('/login', ['email' => $user->email, 'password' => 'otra-clave'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors(['email' => 'Correo o contraseña incorrectos.']);

        $this->assertGuest();
    }

    public function test_correo_inexistente_da_el_mismo_mensaje(): void
    {
        // Mismo mensaje que con clave incorrecta: no revela qué correos existen
        $this->from('/login')
            ->post('/login', ['email' => 'nadie@corteya.com', 'password' => 'lo-que-sea'])
            ->assertSessionHasErrors(['email' => 'Correo o contraseña incorrectos.']);
    }

    public function test_campos_vacios_se_validan_en_espanol(): void
    {
        $this->from('/login')
            ->post('/login', [])
            ->assertSessionHasErrors(['email' => 'El campo correo es obligatorio.', 'password' => 'El campo contraseña es obligatorio.']);
    }

    public function test_bloquea_tras_cinco_intentos_fallidos(): void
    {
        $user = User::factory()->create(['password' => 'secreto123']);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => $user->email, 'password' => 'mala']);
        }

        // El sexto intento se bloquea, incluso con la clave correcta
        $respuesta = $this->post('/login', ['email' => $user->email, 'password' => 'secreto123']);

        $respuesta->assertSessionHasErrors('email');
        $this->assertStringContainsString('Demasiados intentos', session('errors')->first('email'));
        $this->assertGuest();
    }

    public function test_el_barbero_no_puede_ver_el_dashboard(): void
    {
        $this->actingAs(User::factory()->barbero()->create())
            ->get('/dashboard')
            ->assertForbidden()
            ->assertSee('Esta silla está reservada');
    }

    public function test_el_admin_no_puede_entrar_a_la_agenda_del_barbero(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get('/agenda')
            ->assertForbidden();
    }

    public function test_un_invitado_es_enviado_al_login(): void
    {
        $this->get('/dashboard')->assertRedirect(route('login'));
        $this->get('/')->assertRedirect(route('login'));
    }

    public function test_quien_ya_tiene_sesion_no_ve_el_login(): void
    {
        // Un barbero que abre /login va a su agenda (y no al dashboard, que le daría 403)
        $this->actingAs(User::factory()->barbero()->create())
            ->get('/login')
            ->assertRedirect(route('inicio'));

        $this->actingAs(User::factory()->barbero()->create())
            ->get('/')
            ->assertRedirect(route('agenda'));
    }

    public function test_cerrar_sesion(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post('/logout')
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_cada_rol_ve_solo_su_menu(): void
    {
        $this->actingAs(User::factory()->barbero()->create())
            ->get('/agenda')
            ->assertOk()
            ->assertSee('Mi agenda')
            ->assertDontSee('Dashboard')
            ->assertDontSee('Barberos');

        $this->actingAs(User::factory()->admin()->create())
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Dashboard')
            ->assertDontSee('Mi agenda');
    }

    public function test_pagina_404_propia(): void
    {
        $this->get('/esta-ruta-no-existe')
            ->assertNotFound()
            ->assertSee('Este corte no existe');
    }
}
