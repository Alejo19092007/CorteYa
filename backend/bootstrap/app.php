<?php

use App\Http\Middleware\RoleMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
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
        // Todo lo que falle bajo /api responde JSON (401, 404, 422...), nunca una página HTML ni una redirección a /login
        $exceptions->shouldRenderJsonWhen(fn (Request $request) => $request->is('api/*') || $request->expectsJson());

        // Mensajes en español para el bot (los de Laravel vienen en inglés y el 404 revelaría el nombre del modelo)
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            return $request->is('api/*') ? response()->json(['message' => 'No autenticado: falta el token o ya no es válido.'], 401) : null;
        });
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            return $request->is('api/*') ? response()->json(['message' => 'El recurso solicitado no existe.'], 404) : null;
        });
        $exceptions->render(function (AccessDeniedHttpException $e, Request $request) {
            return $request->is('api/*') ? response()->json(['message' => 'No tienes permiso para esta acción.'], 403) : null;
        });
    })->create();
