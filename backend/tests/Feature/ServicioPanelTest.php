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

/** Pruebas del CRUD de servicios del panel. */
class ServicioPanelTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
    }

    private function datos(array $cambios = []): array
    {
        return array_merge(['nombre' => 'Corte clásico', 'duracion_min' => 45, 'precio' => 25000, 'activo' => 1], $cambios);
    }

    /** Un turno que usa el servicio, para probar que no se puede borrar con historial. */
    private function usarEnUnTurno(Servicio $servicio): void
    {
        $barbero = Barbero::factory()->create();
        Horario::factory()->create(['barbero_id' => $barbero->id, 'dia_semana' => 1, 'hora_inicio' => '06:00:00', 'hora_fin' => '22:00:00']);

        Turno::factory()
            ->para($barbero, Cliente::factory()->create(), Carbon::parse('2026-11-02 10:00'), collect([$servicio]))
            ->create();
    }

    // ----------------------------------------------------------------- listado

    public function test_el_listado_muestra_los_servicios(): void
    {
        Servicio::factory()->create(['nombre' => 'Corte clásico', 'duracion_min' => 45, 'precio' => 25000]);

        $this->actingAs($this->admin)->get(route('servicios.index'))
            ->assertOk()
            ->assertSee('Corte clásico')
            ->assertSee('45 min')
            ->assertSee('$25.000');
    }

    public function test_busca_por_nombre_y_filtra_por_estado(): void
    {
        Servicio::factory()->create(['nombre' => 'Barba clásica', 'activo' => true]);
        Servicio::factory()->create(['nombre' => 'Corte infantil', 'activo' => true]);
        Servicio::factory()->create(['nombre' => 'Diseño antiguo', 'activo' => false]);

        $this->actingAs($this->admin);

        $this->get(route('servicios.index', ['q' => 'barba']))
            ->assertSee('Barba clásica')->assertDontSee('Corte infantil');

        $this->get(route('servicios.index', ['activo' => '0']))
            ->assertSee('Diseño antiguo')->assertDontSee('Barba clásica');

        $this->get(route('servicios.index', ['activo' => '1']))
            ->assertSee('Barba clásica')->assertDontSee('Diseño antiguo');
    }

    public function test_pagina_y_conserva_los_filtros(): void
    {
        Servicio::factory()->count(12)->create(['activo' => true]);

        $this->actingAs($this->admin)->get(route('servicios.index', ['activo' => '1']))
            ->assertOk()
            ->assertSee('activo=1&amp;page=2', false)
            ->assertSee('Mostrando');
    }

    public function test_el_barbero_no_entra_a_servicios(): void
    {
        $this->actingAs(User::factory()->barbero()->create());

        $this->get(route('servicios.index'))->assertForbidden();
        $this->post(route('servicios.store'), $this->datos())->assertForbidden();
    }

    // ------------------------------------------------------------------- crear

    public function test_crea_un_servicio(): void
    {
        $this->actingAs($this->admin)->post(route('servicios.store'), $this->datos(['nombre' => 'Barba']))
            ->assertRedirect(route('servicios.index'))
            ->assertSessionHas('exito', 'Servicio «Barba» creado.');

        $this->assertDatabaseHas('servicios', ['nombre' => 'Barba', 'duracion_min' => 45, 'activo' => true]);
    }

    public function test_el_interruptor_apagado_crea_el_servicio_inactivo(): void
    {
        $this->actingAs($this->admin)->post(route('servicios.store'), $this->datos(['activo' => 0]));

        $this->assertDatabaseHas('servicios', ['nombre' => 'Corte clásico', 'activo' => false]);
    }

    public function test_valida_los_campos_en_espanol(): void
    {
        $this->actingAs($this->admin)->from(route('servicios.create'))->post(route('servicios.store'), [])
            ->assertRedirect(route('servicios.create'))
            ->assertSessionHasErrors([
                'nombre' => 'El campo nombre es obligatorio.',
                'duracion_min' => 'El campo duración es obligatorio.',
                'precio' => 'El campo precio es obligatorio.',
            ]);
    }

    public function test_rechaza_valores_invalidos(): void
    {
        $this->actingAs($this->admin)
            ->post(route('servicios.store'), $this->datos(['duracion_min' => 0, 'precio' => -5]))
            ->assertSessionHasErrors(['duracion_min', 'precio']);

        $this->post(route('servicios.store'), $this->datos(['duracion_min' => 'abc']))
            ->assertSessionHasErrors('duracion_min');

        $this->assertDatabaseCount('servicios', 0);
    }

    public function test_el_nombre_no_se_repite(): void
    {
        Servicio::factory()->create(['nombre' => 'Barba']);

        $this->actingAs($this->admin)->post(route('servicios.store'), $this->datos(['nombre' => 'Barba']))
            ->assertSessionHasErrors(['nombre' => 'Ya existe un servicio con ese nombre.']);
    }

    // ------------------------------------------------------------------ editar

    public function test_actualiza_un_servicio(): void
    {
        $servicio = Servicio::factory()->create(['nombre' => 'Barba', 'precio' => 20000]);

        $this->actingAs($this->admin)->put(route('servicios.update', $servicio), $this->datos(['nombre' => 'Barba y bigote', 'precio' => 30000]))
            ->assertRedirect(route('servicios.index'));

        $this->assertDatabaseHas('servicios', ['id' => $servicio->id, 'nombre' => 'Barba y bigote', 'precio' => 30000]);
    }

    public function test_al_editar_el_nombre_puede_quedar_igual(): void
    {
        $servicio = Servicio::factory()->create(['nombre' => 'Barba']);

        // Mismo nombre que ya tiene: no debe contarse como repetido
        $this->actingAs($this->admin)->put(route('servicios.update', $servicio), $this->datos(['nombre' => 'Barba', 'precio' => 99000]))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('servicios', ['id' => $servicio->id, 'precio' => 99000]);
    }

    public function test_al_editar_no_se_puede_usar_el_nombre_de_otro(): void
    {
        Servicio::factory()->create(['nombre' => 'Barba']);
        $otro = Servicio::factory()->create(['nombre' => 'Cejas']);

        $this->actingAs($this->admin)->put(route('servicios.update', $otro), $this->datos(['nombre' => 'Barba']))
            ->assertSessionHasErrors('nombre');
    }

    public function test_cambiar_el_precio_no_altera_los_turnos_ya_reservados(): void
    {
        $servicio = Servicio::factory()->create(['nombre' => 'Corte', 'duracion_min' => 45, 'precio' => 25000]);
        $this->usarEnUnTurno($servicio);

        $this->actingAs($this->admin)->put(route('servicios.update', $servicio), $this->datos(['nombre' => 'Corte', 'duracion_min' => 60, 'precio' => 40000]));

        $turno = Turno::firstOrFail();
        $this->assertEquals(25000, $turno->precio_total);
        $this->assertEquals(25000, $turno->servicios->first()->pivot->precio);
        $this->assertSame(45, (int) $turno->servicios->first()->pivot->duracion_min);
    }

    // --------------------------------------------------------- activar / eliminar

    public function test_activa_y_desactiva(): void
    {
        $servicio = Servicio::factory()->create(['activo' => true]);

        $this->actingAs($this->admin)->patch(route('servicios.activo', $servicio))
            ->assertSessionHas('exito', 'Servicio desactivado.');
        $this->assertFalse($servicio->fresh()->activo);

        $this->patch(route('servicios.activo', $servicio))->assertSessionHas('exito', 'Servicio activado.');
        $this->assertTrue($servicio->fresh()->activo);
    }

    public function test_elimina_un_servicio_que_nunca_se_uso(): void
    {
        $servicio = Servicio::factory()->create();

        $this->actingAs($this->admin)->delete(route('servicios.destroy', $servicio))
            ->assertRedirect(route('servicios.index'))
            ->assertSessionHas('exito', 'Servicio eliminado.');

        $this->assertDatabaseMissing('servicios', ['id' => $servicio->id]);
    }

    public function test_no_elimina_un_servicio_con_historial_de_turnos(): void
    {
        $servicio = Servicio::factory()->create();
        $this->usarEnUnTurno($servicio);

        $this->actingAs($this->admin)->delete(route('servicios.destroy', $servicio))
            ->assertSessionHas('error');

        $this->assertStringContainsString('Desactívalo', session('error'));
        $this->assertDatabaseHas('servicios', ['id' => $servicio->id]);
    }

    public function test_el_listado_solo_ofrece_eliminar_si_nunca_se_uso(): void
    {
        $usado = Servicio::factory()->create(['nombre' => 'Servicio usado']);
        $this->usarEnUnTurno($usado);
        $libre = Servicio::factory()->create(['nombre' => 'Servicio libre']);

        // Se busca el atributo exacto del botón: la URL a secas es prefijo de otras (editar, activar...)
        $this->actingAs($this->admin)->get(route('servicios.index'))
            ->assertDontSee('data-accion="'.route('servicios.destroy', $usado).'"', false)
            ->assertSee('data-accion="'.route('servicios.destroy', $libre).'"', false);
    }

    public function test_un_servicio_inactivo_no_se_ofrece_en_el_formulario_de_turnos(): void
    {
        Servicio::factory()->create(['nombre' => 'Servicio apagado', 'activo' => false]);
        Servicio::factory()->create(['nombre' => 'Servicio visible', 'activo' => true]);

        $this->actingAs($this->admin)->get(route('turnos.create'))
            ->assertSee('Servicio visible')
            ->assertDontSee('Servicio apagado');
    }

    public function test_las_pantallas_de_crear_y_editar_abren(): void
    {
        $servicio = Servicio::factory()->create(['nombre' => 'Barba']);

        $this->actingAs($this->admin)->get(route('servicios.create'))->assertOk()->assertSee('Nuevo servicio');
        $this->get(route('servicios.edit', $servicio))->assertOk()->assertSee('Editar servicio')->assertSee('Barba');
    }
}
