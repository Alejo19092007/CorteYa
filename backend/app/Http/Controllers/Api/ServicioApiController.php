<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreServicioApiRequest;
use App\Http\Requests\UpdateServicioApiRequest;
use App\Http\Resources\ServicioResource;
use App\Models\Servicio;
use App\Repositories\ServicioRepository;
use App\Services\ServicioService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * API de servicios. Sin lógica de negocio: valida, delega en ServicioService (el mismo que usa el panel)
 * y responde con un API Resource.
 */
class ServicioApiController extends Controller
{
    public function __construct(
        private readonly ServicioService $servicios,
        private readonly ServicioRepository $repositorio,
    ) {}

    /** ?activo=1 devuelve solo los que se ofrecen (lo que muestra el bot); sin filtro, todos. */
    public function index(Request $request): AnonymousResourceCollection
    {
        $activo = $request->has('activo') ? $request->boolean('activo') : null;

        return ServicioResource::collection($this->repositorio->listar($activo));
    }

    public function store(StoreServicioApiRequest $request): ServicioResource
    {
        return new ServicioResource($this->servicios->crear($request->datos()));
    }

    public function update(UpdateServicioApiRequest $request, Servicio $servicio): ServicioResource
    {
        return new ServicioResource($this->servicios->actualizar($servicio, $request->datos()));
    }

    /** 204 si se borró; 422 si el servicio ya se usó en turnos (hay que desactivarlo). */
    public function destroy(Servicio $servicio): Response
    {
        $this->servicios->eliminar($servicio);

        return response()->noContent();
    }
}
