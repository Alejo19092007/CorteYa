<?php

namespace App\Http\Controllers;

use App\Http\Requests\ActualizarMiHorarioRequest;
use App\Services\BarberoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Perfil del barbero que inició sesión: ve sus datos y cambia su horario. Sin lógica de negocio.
 * No recibe ningún id: siempre trabaja con el barbero del usuario autenticado, así no puede tocar a otro.
 */
class PerfilController extends Controller
{
    public function __construct(private readonly BarberoService $barberos) {}

    public function show(Request $request): View
    {
        $barbero = $request->user()->barbero;
        $barbero?->load(['user', 'horarios']);

        return view('perfil.show', [
            'barbero' => $barbero,
            'jornadas' => $barbero?->jornadas() ?? [],
        ]);
    }

    public function actualizarHorario(ActualizarMiHorarioRequest $request): RedirectResponse
    {
        $barbero = $request->user()->barbero;
        abort_if($barbero === null, 403);

        $this->barberos->actualizarHorarios($barbero, $request->horarios());

        return redirect()->route('mi-perfil')->with('exito', 'Tu horario quedó actualizado.');
    }
}
