<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreServicioRequest;
use App\Http\Requests\UpdateServicioRequest;
use App\Models\Servicio;
use App\Repositories\ServicioRepository;
use App\Services\ServicioService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Controlador de recurso de los servicios. Sin lógica de negocio: valida con un Form Request,
 * delega en ServicioService y devuelve una vista o una redirección.
 */
class ServicioController extends Controller
{
    public function __construct(
        private readonly ServicioService $servicios,
        private readonly ServicioRepository $repositorio,
    ) {}

    public function index(Request $request): View
    {
        $filtros = $request->only(['q', 'activo']);

        return view('servicios.index', [
            'servicios' => $this->repositorio->buscar($filtros),
            'filtros' => $filtros,
        ]);
    }

    public function create(): View
    {
        return view('servicios.create');
    }

    public function store(StoreServicioRequest $request): RedirectResponse
    {
        $servicio = $this->servicios->crear($request->datos());

        return redirect()->route('servicios.index')->with('exito', "Servicio «{$servicio->nombre}» creado.");
    }

    public function edit(Servicio $servicio): View
    {
        return view('servicios.edit', ['servicio' => $servicio]);
    }

    public function update(UpdateServicioRequest $request, Servicio $servicio): RedirectResponse
    {
        $this->servicios->actualizar($servicio, $request->datos());

        return redirect()->route('servicios.index')->with('exito', 'Servicio actualizado.');
    }

    /** Elimina el servicio solo si nunca se usó en un turno; si no, ReglaNegocioException pide desactivarlo. */
    public function destroy(Servicio $servicio): RedirectResponse
    {
        $this->servicios->eliminar($servicio);

        return redirect()->route('servicios.index')->with('exito', 'Servicio eliminado.');
    }

    public function alternarActivo(Servicio $servicio): RedirectResponse
    {
        $servicio = $this->servicios->alternarActivo($servicio);

        return back()->with('exito', $servicio->activo ? 'Servicio activado.' : 'Servicio desactivado.');
    }
}
