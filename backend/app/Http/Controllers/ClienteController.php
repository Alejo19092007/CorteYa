<?php

namespace App\Http\Controllers;

use App\Repositories\ClienteRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClienteController extends Controller
{
    public function __construct(private readonly ClienteRepository $clientes) {}

    /** Búsqueda por nombre o teléfono para el selector del formulario de turnos. */
    public function buscar(Request $request): JsonResponse
    {
        $texto = trim((string) $request->query('q', ''));

        if (mb_strlen($texto) < 2) {
            return response()->json([]);
        }

        return response()->json($this->clientes->buscar($texto));
    }
}
