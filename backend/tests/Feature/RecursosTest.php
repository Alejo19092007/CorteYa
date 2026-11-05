<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Los JS y CSS propios se enlazan con una versión (?v=fecha del archivo). Sin ella, tras un despliegue el navegador
 * puede seguir usando la copia vieja de un script y el formulario falla (pasó con el formulario de turnos).
 */
class RecursosTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_pagina_de_login_enlaza_sus_recursos_con_version(): void
    {
        $html = $this->get(route('login'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#/css/corteya\.css\?v=\d+#', $html);
        $this->assertMatchesRegularExpression('#/js/login\.js\?v=\d+#', $html);
    }

    public function test_el_formulario_de_turno_enlaza_su_javascript_con_version(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $html = $this->get(route('turnos.create'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#/js/turno-form\.js\?v=\d+#', $html);
    }

    public function test_la_version_es_la_fecha_del_archivo_y_cambia_cuando_el_archivo_cambia(): void
    {
        $archivo = public_path('js/turno-form.js');
        $original = filemtime($archivo);

        try {
            touch($archivo, $original + 1000);
            clearstatcache();

            $html = $this->actingAs(User::factory()->admin()->create())->get(route('turnos.create'))->getContent();

            $this->assertStringContainsString('/js/turno-form.js?v='.($original + 1000), $html);
        } finally {
            touch($archivo, $original);
            clearstatcache();
        }
    }

    /**
     * Regresión del despliegue: `composer dump-autoload` arranca la aplicación (package:discover) mientras se
     * construye la imagen Docker, ANTES de que exista storage/framework/views. Si el arranque crea el compilador
     * de Blade, falla con "Please provide a valid cache path" y Render no puede construir. Por eso la directiva
     * @recurso se registra diferida y el arranque NO debe resolver el compilador.
     */
    public function test_arrancar_la_aplicacion_no_crea_el_compilador_de_blade(): void
    {
        $app = $this->createApplication();   // aplicación nueva, ya arrancada (providers incluidos)

        $this->assertFalse($app->resolved('blade.compiler'));
    }

    public function test_la_directiva_recurso_existe_cuando_blade_se_usa(): void
    {
        $this->assertArrayHasKey('recurso', app('blade.compiler')->getCustomDirectives());
    }

    /** Guardia: ninguna vista debe volver a enlazar un JS o CSS propio sin versión. */
    public function test_ninguna_vista_enlaza_un_js_o_css_propio_sin_version(): void
    {
        $sinVersion = collect(File::allFiles(resource_path('views')))
            ->filter(fn ($archivo) => preg_match("#asset\(\s*['\"](js|css)/#", $archivo->getContents()))
            ->map(fn ($archivo) => $archivo->getRelativePathname())
            ->values()
            ->all();

        $this->assertSame([], $sinVersion, "Usa @recurso('js/archivo.js') en vez de asset(): estas vistas enlazan sin versión.");
    }
}
