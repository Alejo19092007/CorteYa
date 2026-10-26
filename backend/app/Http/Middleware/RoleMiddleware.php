<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Deja pasar solo a los usuarios cuyo rol esté en la lista.
     * Uso en rutas: ->middleware('role:admin')  o  ->middleware('role:admin,barbero')
     *
     * Siempre va DESPUÉS de "auth": aquí ya se sabe que hay un usuario con sesión.
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $usuario = $request->user();

        if (! $usuario || ! in_array($usuario->rol, $roles, true)) {
            abort(403); // sesión válida pero sin permiso: página 403 propia
        }

        return $next($request);
    }
}
