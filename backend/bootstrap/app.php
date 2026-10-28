<?php

use App\Http\Middleware\RoleMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Alias "role" para usar ->middleware('role:admin') en las rutas
        $middleware->alias([
            'role' => RoleMiddleware::class,
        ]);

        // Detrás del balanceador de Render la conexión interna es HTTP y el HTTPS lo termina Render.
        // Confiar en el proxy hace que Laravel lea X-Forwarded-Proto y sepa que la petición original
        // era https (si no, generaría enlaces y cookies como si fuera http).
        $middleware->trustProxies(at: '*');

        // Si alguien con sesión abre /login, va a su pantalla de inicio según el rol.
        // Sin esto, Laravel lo mandaría a "dashboard", que le daría 403 a un barbero.
        $middleware->redirectUsersTo(fn () => route('inicio'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
