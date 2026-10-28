<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListarTurnosApiRequest;
use App\Http\Requests\StoreTurnoApiRequest;
use App\Http\Requests\UpdateTurnoApiRequest;
use App\Http\Resources\TurnoResource;
use App\Models\Turno;
use App\Repositories\ClienteRepository;
use App\Repositories\TurnoRepository;
use App\Services\TurnoService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * API de turnos para el bot de WhatsApp. Sin lógica de negocio: R1–R4 viven en TurnoService
 * y cuando una falla lanza ReglaNegocioException, que aquí se convierte en un 422.
 */
class TurnoApiController extends Controller
{
    public function __construct(
        private readonly TurnoService $turnos,
        private readonly TurnoRepository $repositorio,
        private readonly ClienteRepository $clientes,
    ) {}

    /** Turnos del cliente con ese teléfono (lista vacía si nunca ha escrito). */
    public function index(ListarTurnosApiRequest $request): AnonymousResourceCollection
    {
        $cliente = $this->clientes->buscarPorTelefono($request->validated('telefono'));

        $turnos = $cliente
            ? $this->repositorio->delCliente($cliente->id, $request->validated('estado'))
            : collect();

        return TurnoResource::collection($turnos);
    }

    /** Nace "pendiente" con origen "whatsapp": el dueño o el barbero lo confirman en el panel. */
    public function store(StoreTurnoApiRequest $request): TurnoResource
    {
        return new TurnoResource($this->turnos->crearDesdeWhatsapp($request->validated()));
    }

    /** Reprograma un turno pendiente o confirmado (R1 y R2 se vuelven a aplicar). */
    public function update(UpdateTurnoApiRequest $request, Turno $turno): TurnoResource
    {
        $datos = $request->validated() + ['cliente_id' => $turno->cliente_id];

        return new TurnoResource($this->turnos->actualizar($turno, $datos));
    }

    /** Cancela como CLIENTE: aplica R3 (hasta 2 h antes). El turno no se borra, pasa a "cancelado". */
    public function destroy(Turno $turno): Response
    {
        $this->turnos->cancelar($turno, TurnoService::CANCELA_CLIENTE);

        return response()->noContent();
    }
}
