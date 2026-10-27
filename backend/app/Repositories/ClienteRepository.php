<?php

namespace App\Repositories;

use App\Models\Cliente;
use Illuminate\Support\Collection;

/**
 * Consultas sobre clientes. El bot identifica al cliente por su número de WhatsApp.
 */
class ClienteRepository
{
    public function buscarPorTelefono(string $telefono): ?Cliente
    {
        return Cliente::where('telefono', $telefono)->first();
    }

    /** Búsqueda por nombre o teléfono para el selector del formulario de turnos. */
    public function buscar(string $texto, int $limite = 8): Collection
    {
        return Cliente::where('nombre', 'like', "%{$texto}%")
            ->orWhere('telefono', 'like', "%{$texto}%")
            ->orderBy('nombre')
            ->limit($limite)
            ->get(['id', 'nombre', 'telefono']);
    }
}
