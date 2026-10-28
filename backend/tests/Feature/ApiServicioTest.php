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
 * Pruebas de la API de servicios: GET, POST, PUT y DELETE con sus códigos (200, 201, 204, 401, 404, 422).
 */
class ApiServicioTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Sanctum::actingAs(User::factory()->admin()->create());
    }

    public function test_lista_los_servicios_con_la_forma_del_resource(): void
    {
        Servicio::factory()->create(['nombre' => 'Barba', 'duracion_min' => 30, 'precio' => 15000.50, 'activo' => true]);

        $this->getJson('/api/servicios')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertExactJson(['data' => [[
                'id' => Servicio::first()->id,
                'nombre' => 'Barba',
                'duracion_min' => 30,
                'precio' => 15000.5,
                'activo' => true,
            ]]]);
    }

    public function test_el_filtro_activo_deja_solo_los_que_se_ofrecen(): void
    {
        Servicio::factory()->create(['nombre' => 'Corte', 'activo' => true]);
        Servicio::factory()->create(['nombre' => 'Descontinuado', 'activo' => false]);

        $this->getJson('/api/servicios')->assertJsonCount(2, 'data');
        $this->getJson('/api/servicios?activo=1')->assertJsonCount(1, 'data')->assertJsonPath('data.0.nombre', 'Corte');
        $this->getJson('/api/servicios?activo=0')->assertJsonCount(1, 'data')->assertJsonPath('data.0.nombre', 'Descontinuado');
    }

    public function test_crear_responde_201_y_queda_activo_por_defecto(): void
    {
        $this->postJson('/api/servicios', ['nombre' => 'Mascarilla', 'duracion_min' => 20, 'precio' => 18000])
            ->assertCreated()
            ->assertJsonPath('data.nombre', 'Mascarilla')
            ->assertJsonPath('data.activo', true);

        $this->assertDatabaseHas('servicios', ['nombre' => 'Mascarilla', 'activo' => true]);
    }

    public function test_crear_con_datos_invalidos_responde_422(): void
    {
        Servicio::factory()->create(['nombre' => 'Barba']);

        $this->postJson('/api/servicios', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['nombre', 'duracion_min', 'precio']);

        $this->postJson('/api/servicios', ['nombre' => 'Barba', 'duracion_min' => 30, 'precio' => 1000])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['nombre']);

        $this->postJson('/api/servicios', ['nombre' => 'Nuevo', 'duracion_min' => 0, 'precio' => -5])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['duracion_min', 'precio']);
    }

    public function test_actualizar_responde_200_y_sin_activo_conserva_el_que_tenia(): void
    {
        $servicio = Servicio::factory()->create(['nombre' => 'Barba', 'precio' => 15000, 'activo' => false]);

        $this->putJson("/api/servicios/{$servicio->id}", ['nombre' => 'Barba', 'duracion_min' => 35, 'precio' => 17000])
            ->assertOk()
            ->assertJsonPath('data.precio', 17000)
            ->assertJsonPath('data.duracion_min', 35)
            ->assertJsonPath('data.activo', false);   // no se reactivó por no mandarlo

        $this->putJson("/api/servicios/{$servicio->id}", ['nombre' => 'Barba', 'duracion_min' => 35, 'precio' => 17000, 'activo' => true])
            ->assertOk()
            ->assertJsonPath('data.activo', true);
    }

    public function test_actualizar_puede_conservar_su_propio_nombre_pero_no_tomar_el_de_otro(): void
    {
        $barba = Servicio::factory()->create(['nombre' => 'Barba']);
        Servicio::factory()->create(['nombre' => 'Cejas']);

        $this->putJson("/api/servicios/{$barba->id}", ['nombre' => 'Barba', 'duracion_min' => 30, 'precio' => 1000])->assertOk();
        $this->putJson("/api/servicios/{$barba->id}", ['nombre' => 'Cejas', 'duracion_min' => 30, 'precio' => 1000])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['nombre']);
    }

    public function test_actualizar_o_borrar_un_servicio_que_no_existe_responde_404(): void
    {
        $this->putJson('/api/servicios/999', ['nombre' => 'X', 'duracion_min' => 10, 'precio' => 1])
            ->assertStatus(404)
            ->assertJsonPath('message', 'El recurso solicitado no existe.');
        $this->deleteJson('/api/servicios/999')->assertStatus(404);
    }

    public function test_borrar_un_servicio_sin_uso_responde_204(): void
    {
        $servicio = Servicio::factory()->create();

        $this->deleteJson("/api/servicios/{$servicio->id}")->assertNoContent();

        $this->assertDatabaseMissing('servicios', ['id' => $servicio->id]);
    }

    public function test_borrar_un_servicio_ya_usado_responde_422_y_no_lo_borra(): void
    {
        Carbon::setTestNow('2026-11-02 10:00:00');

        $servicio = Servicio::factory()->create(['duracion_min' => 30]);
        $barbero = Barbero::factory()->create();
        Horario::factory()->create(['barbero_id' => $barbero->id, 'dia_semana' => 2, 'hora_inicio' => '06:00:00', 'hora_fin' => '22:00:00']);
        Turno::factory()->para($barbero, Cliente::factory()->create(), Carbon::parse('2026-11-03 10:00'), collect([$servicio]))->create();

        $this->deleteJson("/api/servicios/{$servicio->id}")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Este servicio ya se usó en turnos y no se puede eliminar. Desactívalo para que deje de ofrecerse.');

        $this->assertDatabaseHas('servicios', ['id' => $servicio->id]);
        Carbon::setTestNow();
    }
}
