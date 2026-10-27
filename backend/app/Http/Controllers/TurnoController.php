<?php

namespace App\Http\Controllers;

use App\Http\Requests\CambiarEstadoTurnoRequest;
use App\Http\Requests\StoreTurnoRequest;
use App\Http\Requests\UpdateTurnoRequest;
use App\Models\Barbero;
use App\Models\Servicio;
use App\Models\Turno;
use App\Repositories\TurnoRepository;
use App\Services\TurnoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Controlador de recurso de los turnos del panel. No tiene lógica de negocio:
 * valida con un Form Request, delega en TurnoService y devuelve una vista o una redirección.
 * Si una regla falla, TurnoService lanza ReglaNegocioException y Laravel vuelve al formulario con la alerta.
 */
class TurnoController extends Controller
{
    public function __construct(
        private readonly TurnoService $turnos,
        private readonly TurnoRepository $repositorio,
    ) {}

    /** Listado del administrador: búsqueda, filtros y paginación. */
    public function index(Request $request): View
    {
        $filtros = $request->only(['q', 'barbero_id', 'estado', 'desde', 'hasta']);

        return view('turnos.index', [
            'turnos' => $this->repositorio->buscar($filtros),
            'filtros' => $filtros,
            'barberos' => Barbero::with('user')->orderBy('id')->get(),
        ]);
    }

    public function create(): View
    {
        return view('turnos.create', $this->datosDelFormulario());
    }

    public function store(StoreTurnoRequest $request): RedirectResponse
    {
        $turno = $this->turnos->crear($request->validated(), Turno::ORIGEN_PANEL);

        return redirect()->route('turnos.index')
            ->with('exito', "Turno creado para {$turno->cliente->nombre} el {$turno->fecha->format('d/m/Y')} a las {$turno->horaInicioLegible()}.");
    }

    public function show(Turno $turno): View
    {
        $turno->load(['barbero.user', 'cliente', 'servicios']);

        return view('turnos.show', ['turno' => $turno]);
    }

    public function edit(Turno $turno): View|RedirectResponse
    {
        if (! $turno->esEditable()) {
            return redirect()->route('turnos.show', $turno)
                ->with('error', 'Este turno ya no se puede modificar.');
        }

        $turno->load(['barbero.user', 'cliente', 'servicios']);

        return view('turnos.edit', $this->datosDelFormulario() + ['turno' => $turno]);
    }

    public function update(UpdateTurnoRequest $request, Turno $turno): RedirectResponse
    {
        $this->turnos->actualizar($turno, $request->validated());

        return redirect()->route('turnos.index')->with('exito', 'Turno actualizado.');
    }

    /** "Eliminar" un turno es CANCELARLO: cambia el estado, nunca se borra (queda el historial). */
    public function destroy(Turno $turno): RedirectResponse
    {
        $this->turnos->cancelar($turno, TurnoService::CANCELA_ADMIN);

        return redirect()->route('turnos.index')->with('exito', 'Turno cancelado.');
    }

    /** Agenda del día del barbero que inició sesión. */
    public function agenda(Request $request): View
    {
        $barbero = $request->user()->barbero;
        $hoy = now()->toDateString();

        return view('turnos.agenda', [
            'barbero' => $barbero,
            'turnos' => $barbero ? $this->repositorio->agendaDelBarbero($barbero->id, $hoy) : collect(),
        ]);
    }

    /** Confirmar, marcar atendido o no asistió. La política TurnoPolicy@cambiarEstado limita al barbero a sus turnos. */
    public function cambiarEstado(CambiarEstadoTurnoRequest $request, Turno $turno): RedirectResponse
    {
        $estado = $request->validated('estado');

        $this->turnos->cambiarEstado($turno, $estado);

        $mensajes = [
            Turno::CONFIRMADO => 'Turno confirmado.',
            Turno::ATENDIDO => 'Turno marcado como atendido.',
            Turno::NO_ASISTIO => 'Turno marcado como no asistió.',
        ];

        return back()->with('exito', $mensajes[$estado]);
    }

    /**
     * Horas libres para el formulario (lo consulta JavaScript). La duración se calcula en el
     * servidor a partir de los servicios elegidos, igual que en R2.
     */
    public function disponibilidad(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'barbero_id' => ['required', 'integer', 'exists:barberos,id'],
            'fecha' => ['required', 'date'],
            'servicios' => ['required', 'array', 'min:1'],
            'servicios.*' => ['integer'],
            'ignorar' => ['nullable', 'integer'],
        ]);

        $existente = isset($datos['ignorar']) ? Turno::with('servicios')->find($datos['ignorar']) : null;
        $duracion = $this->turnos->duracionDe($datos['servicios'], $existente);

        return response()->json([
            'duracion' => $duracion,
            'horas' => $this->turnos->disponibilidad(
                (int) $datos['barbero_id'],
                $datos['fecha'],
                $duracion,
                $existente?->id,
            ),
        ]);
    }

    private function datosDelFormulario(): array
    {
        return [
            'barberos' => Barbero::with('user')->where('activo', true)->orderBy('id')->get(),
            'servicios' => Servicio::where('activo', true)->orderBy('nombre')->get(),
        ];
    }
}
