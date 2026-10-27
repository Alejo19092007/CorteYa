<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Se lanza desde los Services cuando se incumple una regla de negocio (R1, R3, R4...).
 *
 * La misma excepción se presenta según quién la provocó:
 *  - API (/api/* o petición JSON): respuesta 422 con {"message": "..."}.
 *  - Panel web: vuelve al formulario conservando lo escrito y muestra el mensaje en una alerta.
 *
 * Así el Service no sabe si lo llama el panel o el bot: solo dice qué regla falló.
 */
class ReglaNegocioException extends RuntimeException
{
    /** Es un error esperado del usuario, no un fallo del sistema: no se guarda en el log. */
    public function report(): bool
    {
        return false;
    }

    public function render(Request $request): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json(['message' => $this->getMessage()], 422);
        }

        return back()->withInput()->with('error', $this->getMessage());
    }
}
