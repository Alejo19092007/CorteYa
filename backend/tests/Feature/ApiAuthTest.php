<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Pruebas del login/logout de la API con Sanctum y de la protección de las rutas (401 y 403).
 */
class ApiAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        User::factory()->admin()->create(['email' => 'bot@corteya.test', 'password' => 'clave-del-bot-123']);
    }

    private function login(array $cambios = []): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/api/login', array_merge([
            'email' => 'bot@corteya.test',
            'password' => 'clave-del-bot-123',
        ], $cambios));
    }

    // ------------------------------------------------------------------- login

    public function test_el_login_devuelve_un_token_que_abre_las_rutas_protegidas(): void
    {
        $respuesta = $this->login(['device_name' => 'bot-whatsapp'])
            ->assertOk()
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('user.email', 'bot@corteya.test')
            ->assertJsonPath('user.rol', 'admin')
            ->assertJsonMissingPath('user.password');

        $token = $respuesta->json('token');
        $this->assertNotEmpty($token);

        $this->withToken($token)->getJson('/api/servicios')->assertOk();
    }

    public function test_credenciales_incorrectas_dan_401_con_el_mismo_mensaje(): void
    {
        $contrasenaMala = $this->login(['password' => 'otra-clave-123'])->assertStatus(401);
        $correoInexistente = $this->login(['email' => 'nadie@corteya.test'])->assertStatus(401);

        // No se revela si el correo existe o no
        $this->assertSame($contrasenaMala->json('message'), $correoInexistente->json('message'));
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_faltan_datos_en_el_login_da_422(): void
    {
        $this->postJson('/api/login', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'password']);

        $this->login(['email' => 'no-es-un-correo'])->assertStatus(422)->assertJsonValidationErrors(['email']);
    }

    public function test_un_barbero_no_recibe_token(): void
    {
        User::factory()->barbero()->create(['email' => 'barbero@corteya.test', 'password' => 'clave-barbero-1']);

        $this->login(['email' => 'barbero@corteya.test', 'password' => 'clave-barbero-1'])->assertStatus(403);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_iniciar_sesion_de_nuevo_con_el_mismo_nombre_reemplaza_el_token(): void
    {
        $this->login(['device_name' => 'bot-whatsapp'])->assertOk();
        $this->login(['device_name' => 'bot-whatsapp'])->assertOk();
        $this->assertDatabaseCount('personal_access_tokens', 1);

        // Otro nombre es otro dispositivo: convive con el anterior
        $this->login(['device_name' => 'postman'])->assertOk();
        $this->assertDatabaseCount('personal_access_tokens', 2);
    }

    public function test_el_login_se_limita_a_diez_intentos_por_minuto(): void
    {
        foreach (range(1, 10) as $i) {
            $this->login(['password' => 'mala-'.$i])->assertStatus(401);
        }

        $this->login()->assertStatus(429);
    }

    // ------------------------------------------------------------------ logout

    public function test_el_logout_revoca_el_token(): void
    {
        $token = $this->login()->json('token');
        $this->assertDatabaseCount('personal_access_tokens', 1);

        $this->withToken($token)->postJson('/api/logout')->assertNoContent();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_el_logout_sin_token_da_401(): void
    {
        $this->postJson('/api/logout')->assertStatus(401);
    }

    // ------------------------------------------------------- protección (401/403)

    public function test_sin_token_las_rutas_protegidas_responden_401_en_json(): void
    {
        foreach (['/api/servicios', '/api/turnos?telefono=573001234567', '/api/barberos'] as $ruta) {
            $this->getJson($ruta)->assertStatus(401)->assertJsonStructure(['message']);
        }

        // Aunque la petición no pida JSON, bajo /api nunca se redirige a /login
        $this->get('/api/servicios')->assertStatus(401);
    }

    public function test_sin_token_tampoco_se_puede_escribir(): void
    {
        $servicio = ['nombre' => 'X', 'duracion_min' => 10, 'precio' => 1000];

        $this->postJson('/api/servicios', $servicio)->assertStatus(401);
        $this->putJson('/api/servicios/1', $servicio)->assertStatus(401);
        $this->deleteJson('/api/servicios/1')->assertStatus(401);
        $this->postJson('/api/turnos', [])->assertStatus(401);
        $this->putJson('/api/turnos/1', [])->assertStatus(401);
        $this->deleteJson('/api/turnos/1')->assertStatus(401);
        $this->getJson('/api/barberos/1/disponibilidad?fecha=2026-11-03&servicios[]=1')->assertStatus(401);
    }

    public function test_un_token_invalido_da_401(): void
    {
        $this->withToken('1|token-inventado')->getJson('/api/servicios')->assertStatus(401);
    }

    public function test_un_token_de_un_barbero_no_tiene_permiso_en_la_api(): void
    {
        // Si alguien creara un token para un barbero, role:admin lo frena igual
        Sanctum::actingAs(User::factory()->barbero()->create());

        $this->getJson('/api/servicios')->assertStatus(403);
    }

    public function test_los_tokens_se_guardan_con_hash_y_no_en_claro(): void
    {
        $token = $this->login()->json('token');
        $textoPlano = explode('|', $token, 2)[1];

        $guardado = DB::table('personal_access_tokens')->value('token');
        $this->assertNotSame($textoPlano, $guardado);
        $this->assertSame(hash('sha256', $textoPlano), $guardado);
    }
}
