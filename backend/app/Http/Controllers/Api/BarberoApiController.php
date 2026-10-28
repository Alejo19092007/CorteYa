<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\DisponibilidadApiRequest;
use App\Http\Resources\BarberoResource;
use App\Models\Barbero;
use App\Repositories\BarberoRepository;
use App\Services\TurnoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Lo que el bot necesita saber de los barberos: quiénes reciben turnos y a qué horas están libres.
 */
class BarberoApiController extends Controller
{
    public function __construct(
        private readonly TurnoService $turnos,
        private readonly BarberoRepository $repositorio,
    ) {}

    public function index(): AnonymousResourceCollection
    {
        return BarberoResource::collection($this->repositorio->activos());
    }

    /** Horas de inicio libres ese día para los servicios elegidos. 404 si el barbero no existe. */
    public function disponibilidad(DisponibilidadApiRequest $request, Barbero $barbero): JsonResponse
    {
        $datos = $request->validated();
        $resultado = $this->turnos->disponibilidadParaServicios($barbero->id, $datos['fecha'], $datos['servicios']);

        return response()->json(['data' => [
            'barbero_id' => $barbero->id,
            'fecha' => $datos['fecha'],
            'duracion_min' => $resultado['duracion'],
            'horas' => $resultado['horas'],
        ]]);
    }
}
