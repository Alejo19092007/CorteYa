<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBarberoRequest;
use App\Http\Requests\UpdateBarberoRequest;
use App\Models\Barbero;
use App\Repositories\BarberoRepository;
use App\Services\BarberoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Controlador de recurso de los barberos (cuenta + perfil + horarios). Sin lógica de negocio.
 */
class BarberoController extends Controller
{
    public function __construct(
        private readonly BarberoService $barberos,
        private readonly BarberoRepository $repositorio,
    ) {}

    public function index(Request $request): View
    {
        $filtros = $request->only(['q', 'activo']);

        return view('barberos.index', [
            'barberos' => $this->repositorio->buscar($filtros),
            'filtros' => $filtros,
        ]);
    }

    public function create(): View
    {
        return view('barberos.create', ['jornadas' => $this->jornadasPorDefecto()]);
    }

    public function store(StoreBarberoRequest $request): RedirectResponse
    {
        $barbero = $this->barberos->crear($request->datos());

        return redirect()->route('barberos.index')->with('exito', "Barbero {$barbero->user->name} creado.");
    }

    public function edit(Barbero $barbero): View
    {
        $barbero->load(['user', 'horarios']);

        $jornadas = $barbero->horarios->mapWithKeys(fn ($h) => [$h->dia_semana => [
            'trabaja' => true,
            'inicio' => substr($h->hora_inicio, 0, 5),
            'fin' => substr($h->hora_fin, 0, 5),
        ]])->all();

        return view('barberos.edit', ['barbero' => $barbero, 'jornadas' => $jornadas]);
    }

    public function update(UpdateBarberoRequest $request, Barbero $barbero): RedirectResponse
    {
        $this->barberos->actualizar($barbero, $request->datos());

        return redirect()->route('barberos.index')->with('exito', 'Barbero actualizado.');
    }

    /** Elimina al barbero solo si nunca tuvo turnos; si no, ReglaNegocioException pide desactivarlo. */
    public function destroy(Barbero $barbero): RedirectResponse
    {
        $this->barberos->eliminar($barbero);

        return redirect()->route('barberos.index')->with('exito', 'Barbero eliminado.');
    }

    public function alternarActivo(Barbero $barbero): RedirectResponse
    {
        $barbero = $this->barberos->alternarActivo($barbero);

        return back()->with('exito', $barbero->activo ? 'Barbero activado.' : 'Barbero desactivado.');
    }

    /** Jornada sugerida para un barbero nuevo: lunes a sábado de 9:00 a 18:00. */
    private function jornadasPorDefecto(): array
    {
        return collect(range(1, 6))
            ->mapWithKeys(fn ($dia) => [$dia => ['trabaja' => true, 'inicio' => '09:00', 'fin' => '18:00']])
            ->all();
    }
}
