<?php

namespace App\Providers;

use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Laravel pagina con estilos de Tailwind por defecto; el proyecto usa Bootstrap
        Paginator::useBootstrapFive();

        // @recurso('js/turno-form.js') escribe /js/turno-form.js?v=<fecha del archivo>. Sin esa versión, el navegador
        // puede seguir usando la copia VIEJA de un JS o CSS después de un despliegue (y el formulario falla).
        // Si el archivo no existe, la versión es 0 en vez de lanzar un error.
        Blade::directive('recurso', fn (string $ruta) => "<?php echo e(asset({$ruta}).'?v='.(@filemtime(public_path({$ruta})) ?: '0')); ?>");

        // En producción todos los enlaces y recursos salen con https (el sitio público exige HTTPS)
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }
    }
}
