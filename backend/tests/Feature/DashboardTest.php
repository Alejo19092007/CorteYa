<?php

namespace Tests\Feature;

use App\Models\Barbero;
use App\Models\Cliente;
use App\Models\Servicio;
use App\Models\Turno;
use App\Models\User;
use App\Services\DashboardService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pruebas del dashboard. Reloj congelado en domingo 15 de noviembre de 2026, 10:00:
 * el "periodo" del mes va del 1 al 15 de noviembre y se compara con del 1 al 15 de octubre.
 */
class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Barbero $barbero;

    private int $hora = 0;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-11-15 10:00:00'));

        $this->admin = User::factory()->admin()->create();
        $this->barbero = Barbero::factory()->create();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ------------------------------------------------------------------ ayudas

    /** Crea un turno de $precio pesos en la fecha dada (cada uno a una hora distinta). */
    private function turno(string $fecha, string $estado, int $precio = 25000, ?Barbero $barbero = null): Turno
    {
        $servicio = Servicio::factory()->create(['duracion_min' => 15, 'precio' => $precio]);
        $inicio = Carbon::parse($fecha.' 06:00')->addMinutes(15 * $this->hora++);

        return Turno::factory()
            ->para($barbero ?? $this->barbero, Cliente::factory()->create(), $inicio, collect([$servicio]))
            ->estado($estado)
            ->create();
    }

    private function resumen(): array
    {
        return app(DashboardService::class)->resumen();
    }

    // ------------------------------------------------------------ turnos de hoy

    public function test_turnos_de_hoy_no_cuenta_los_cancelados_y_compara_con_ayer(): void
    {
        $this->turno('2026-11-15', Turno::CONFIRMADO);
        $this->turno('2026-11-15', Turno::PENDIENTE);
        $this->turno('2026-11-15', Turno::ATENDIDO);
        $this->turno('2026-11-15', Turno::CANCELADO);   // no cuenta
        $this->turno('2026-11-14', Turno::ATENDIDO);    // ayer: 1

        $tarjeta = $this->resumen()['tarjetas']['turnosHoy'];

        $this->assertSame(3, $tarjeta['valor']);
        $this->assertSame(['direccion' => 'sube', 'valor' => 2, 'porcentual' => false, 'bueno' => true], $tarjeta['variacion']);
        $this->assertSame('vs. ayer', $tarjeta['comparado']);
    }

    // ------------------------------------------------------------------ ingresos

    public function test_los_ingresos_solo_cuentan_turnos_atendidos_del_periodo(): void
    {
        $this->turno('2026-11-03', Turno::ATENDIDO, 50000);
        $this->turno('2026-11-10', Turno::ATENDIDO, 30000);
        $this->turno('2026-11-12', Turno::PENDIENTE, 99000);     // todavía no se cobró
        $this->turno('2026-11-12', Turno::CONFIRMADO, 99000);
        $this->turno('2026-11-12', Turno::CANCELADO, 99000);
        $this->turno('2026-11-12', Turno::NO_ASISTIO, 99000);
        $this->turno('2026-10-10', Turno::ATENDIDO, 100000);     // mes anterior, dentro del periodo (1–15)
        $this->turno('2026-10-20', Turno::ATENDIDO, 700000);     // mes anterior pero DESPUÉS del día 15: no se compara

        $tarjeta = $this->resumen()['tarjetas']['ingresosMes'];

        $this->assertEquals(80000, $tarjeta['valor']);
        // 80.000 contra 100.000 = baja 20 %, y bajar ingresos es malo
        $this->assertSame(['direccion' => 'baja', 'valor' => 20, 'porcentual' => true, 'bueno' => false], $tarjeta['variacion']);
    }

    public function test_ingresos_que_suben_12_por_ciento_son_buena_noticia(): void
    {
        $this->turno('2026-11-05', Turno::ATENDIDO, 112000);
        $this->turno('2026-10-05', Turno::ATENDIDO, 100000);

        $this->assertSame(
            ['direccion' => 'sube', 'valor' => 12, 'porcentual' => true, 'bueno' => true],
            $this->resumen()['tarjetas']['ingresosMes']['variacion']
        );
    }

    public function test_sin_ingresos_el_mes_anterior_dice_nuevo_en_vez_de_dividir_por_cero(): void
    {
        $this->turno('2026-11-05', Turno::ATENDIDO, 50000);

        $this->assertSame(
            ['direccion' => 'nuevo', 'valor' => null, 'porcentual' => true, 'bueno' => true],
            $this->resumen()['tarjetas']['ingresosMes']['variacion']
        );
    }

    public function test_sin_cambios_cuando_los_dos_periodos_son_iguales(): void
    {
        $this->turno('2026-11-05', Turno::ATENDIDO, 40000);
        $this->turno('2026-10-05', Turno::ATENDIDO, 40000);

        $this->assertSame(
            ['direccion' => 'igual', 'valor' => 0, 'porcentual' => true, 'bueno' => null],
            $this->resumen()['tarjetas']['ingresosMes']['variacion']
        );
    }

    public function test_sin_datos_en_ningun_periodo_no_falla(): void
    {
        $tarjetas = $this->resumen()['tarjetas'];

        $this->assertEquals(0, $tarjetas['ingresosMes']['valor']);
        $this->assertSame('igual', $tarjetas['ingresosMes']['variacion']['direccion']);
        $this->assertSame('igual', $tarjetas['cancelaciones']['variacion']['direccion']);
    }

    public function test_el_periodo_anterior_respeta_un_mes_mas_corto(): void
    {
        // 31 de marzo: el periodo anterior es del 1 al 28 de febrero (no se desborda a marzo)
        Carbon::setTestNow(Carbon::parse('2026-03-31 10:00:00'));

        $this->turno('2026-03-10', Turno::ATENDIDO, 50000);
        $this->turno('2026-02-28', Turno::ATENDIDO, 100000);  // último día de febrero: cuenta
        $this->turno('2026-03-01', Turno::ATENDIDO, 900000);  // ya es marzo: no debe contarse en febrero

        $variacion = $this->resumen()['tarjetas']['ingresosMes']['variacion'];

        // Actual (marzo 1–31) = 50.000 + 900.000 = 950.000; anterior (feb 1–28) = 100.000
        $this->assertSame('sube', $variacion['direccion']);
        $this->assertSame(850, $variacion['valor']);
    }

    // ------------------------------------------------------------- cancelaciones

    public function test_menos_cancelaciones_es_una_buena_noticia(): void
    {
        foreach (['2026-10-02', '2026-10-04', '2026-10-06', '2026-10-08', '2026-10-10'] as $dia) {
            $this->turno($dia, Turno::CANCELADO);   // 5 el mes pasado
        }
        foreach (['2026-11-02', '2026-11-04', '2026-11-06'] as $dia) {
            $this->turno($dia, Turno::CANCELADO);   // 3 este mes
        }

        $tarjeta = $this->resumen()['tarjetas']['cancelaciones'];

        $this->assertSame(3, $tarjeta['valor']);
        // Baja 40 % y eso es BUENO (a diferencia de los ingresos)
        $this->assertSame(['direccion' => 'baja', 'valor' => 40, 'porcentual' => true, 'bueno' => true], $tarjeta['variacion']);
    }

    public function test_mas_cancelaciones_es_una_mala_noticia(): void
    {
        $this->turno('2026-10-02', Turno::CANCELADO);
        foreach (['2026-11-02', '2026-11-04', '2026-11-06'] as $dia) {
            $this->turno($dia, Turno::CANCELADO);
        }

        $variacion = $this->resumen()['tarjetas']['cancelaciones']['variacion'];

        $this->assertSame('sube', $variacion['direccion']);
        $this->assertSame(200, $variacion['valor']);
        $this->assertFalse($variacion['bueno']);
    }

    // ------------------------------------------------------------ clientes nuevos

    public function test_clientes_nuevos_del_mes_contra_el_mes_anterior(): void
    {
        Cliente::factory()->count(3)->create(['created_at' => '2026-11-03 09:00:00']);
        Cliente::factory()->count(1)->create(['created_at' => '2026-10-05 09:00:00']);
        Cliente::factory()->count(5)->create(['created_at' => '2026-10-20 09:00:00']);  // octubre, pero después del día 15

        $tarjeta = $this->resumen()['tarjetas']['clientesNuevos'];

        $this->assertSame(3, $tarjeta['valor']);
        $this->assertSame(['direccion' => 'sube', 'valor' => 2, 'porcentual' => false, 'bueno' => true], $tarjeta['variacion']);
    }

    // ------------------------------------------------------------------ gráfico

    public function test_el_grafico_ordena_por_ingresos_e_incluye_a_todos_los_barberos(): void
    {
        $camilo = Barbero::factory()->create();
        $diego = Barbero::factory()->create();
        $camilo->user->update(['name' => 'Camilo']);
        $diego->user->update(['name' => 'Diego']);
        $this->barbero->user->update(['name' => 'Andrés']);

        $this->turno('2026-11-03', Turno::ATENDIDO, 30000, $this->barbero);
        $this->turno('2026-11-04', Turno::ATENDIDO, 50000, $this->barbero);   // Andrés: 80.000
        $this->turno('2026-11-05', Turno::ATENDIDO, 120000, $camilo);          // Camilo: 120.000
        $this->turno('2026-11-06', Turno::CANCELADO, 999000, $diego);          // Diego no facturó
        $this->turno('2026-11-07', Turno::PENDIENTE, 999000, $diego);
        $this->turno('2026-10-05', Turno::ATENDIDO, 999000, $camilo);          // otro mes: no cuenta

        $grafico = $this->resumen()['grafico'];

        $this->assertSame(['Camilo', 'Andrés', 'Diego'], $grafico['etiquetas']);
        $this->assertEquals([120000, 80000, 0], $grafico['valores']);
    }

    // ------------------------------------------------------------- turnos de hoy

    public function test_lista_los_turnos_de_hoy_por_hora_con_cancelados_incluidos(): void
    {
        $tarde = $this->turno('2026-11-15', Turno::CONFIRMADO);
        $manana = $this->turno('2026-11-15', Turno::CANCELADO);
        $this->turno('2026-11-14', Turno::CONFIRMADO);   // ayer: no aparece

        $lista = $this->resumen()['turnosDeHoy'];

        $this->assertCount(2, $lista);
        $this->assertEquals([$tarde->id, $manana->id], $lista->pluck('id')->all()); // 06:00 y luego 06:15
    }

    // ----------------------------------------------------------------- la vista

    public function test_el_admin_ve_las_cuatro_tarjetas_y_el_grafico(): void
    {
        $this->turno('2026-11-05', Turno::ATENDIDO, 50000);
        $this->turno('2026-11-15', Turno::CONFIRMADO);

        $this->actingAs($this->admin)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Turnos de hoy')
            ->assertSee('Ingresos del mes')
            ->assertSee('Clientes nuevos')
            ->assertSee('Cancelaciones del mes')
            ->assertSee('$50.000')
            ->assertSee('id="graficoIngresos"', false)
            ->assertSee('chart.js@4.4.3', false)
            ->assertSee('nov. de 2026');
    }

    public function test_los_datos_del_grafico_viajan_a_la_pagina_en_dorado_y_en_orden(): void
    {
        $this->barbero->user->update(['name' => 'Andrés Gómez']);
        $this->turno('2026-11-05', Turno::ATENDIDO, 50000);

        $html = $this->actingAs($this->admin)->get(route('dashboard'))->getContent();

        // Se lee el JSON que recibe Chart.js desde el atributo data-grafico del <canvas>
        $this->assertSame(1, preg_match('/data-grafico="([^"]*)"/', $html, $coincidencia));
        $datos = json_decode(html_entity_decode($coincidencia[1]), true);

        $this->assertSame(['Andrés Gómez'], $datos['etiquetas']);
        $this->assertEquals([50000], $datos['valores']);
        $this->assertFileExists(public_path('js/dashboard.js'));
        $this->assertStringContainsString("'#C8A15A'", file_get_contents(public_path('js/dashboard.js'))); // barras doradas, nunca azules
    }

    public function test_sin_ingresos_se_muestra_un_aviso_y_no_se_carga_el_grafico(): void
    {
        $this->actingAs($this->admin)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Aún no hay turnos atendidos este mes')
            ->assertDontSee('id="graficoIngresos"', false)
            ->assertDontSee('chart.js@', false);
    }

    public function test_menos_cancelaciones_se_pinta_en_verde(): void
    {
        $this->turno('2026-10-02', Turno::CANCELADO);
        $this->turno('2026-10-03', Turno::CANCELADO);
        // este mes ninguna: baja 100 % → bueno

        $this->actingAs($this->admin)->get(route('dashboard'))
            ->assertSee('cy-var-bueno', false)
            ->assertSee('100% vs. mes anterior');
    }

    public function test_la_tabla_de_hoy_muestra_los_turnos_y_enlaza_al_listado(): void
    {
        $cliente = Cliente::factory()->create(['nombre' => 'Sebastián Rojas']);
        Turno::factory()
            ->para($this->barbero, $cliente, Carbon::parse('2026-11-15 11:00'), collect([Servicio::factory()->create(['duracion_min' => 30])]))
            ->estado(Turno::PENDIENTE)->origen(Turno::ORIGEN_WHATSAPP)->create();

        $this->actingAs($this->admin)->get(route('dashboard'))
            ->assertSee('Sebastián Rojas')
            ->assertSee('Pendiente')
            ->assertSee('desde=2026-11-15&amp;hasta=2026-11-15', false);
    }

    public function test_solo_el_admin_ve_el_dashboard(): void
    {
        $this->actingAs($this->barbero->user)->get(route('dashboard'))->assertForbidden();

        auth()->logout();
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }
}
