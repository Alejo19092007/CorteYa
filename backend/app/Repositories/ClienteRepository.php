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

    /** Cliente del número dado; si es la primera vez que escribe, se registra con el nombre que trae WhatsApp. */
    public function buscarOCrear(string $telefono, string $nombre): Cliente
    {
        return Cliente::firstOrCreate(['telefono' => $telefono], ['nombre' => $nombre]);
    }

    /** Clientes registrados entre dos fechas (ambas incluidas). */
    public function contarNuevos(string $desde, string $hasta): int
    {
        return Cliente::whereBetween('created_at', ["{$desde} 00:00:00", "{$hasta} 23:59:59"])->count();
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
