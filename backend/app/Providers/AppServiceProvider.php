<?php

namespace App\Providers;

use Illuminate\Pagination\Paginator;
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

        // En producción todos los enlaces y recursos salen con https (el sitio público exige HTTPS)
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }
    }
}
