<?php

namespace App\Repositories;

use App\Models\Cliente;

/**
 * Consultas sobre clientes. El bot identifica al cliente por su número de WhatsApp.
 */
class ClienteRepository
{
    public function buscarPorTelefono(string $telefono): ?Cliente
    {
        return Cliente::where('telefono', $telefono)->first();
    }
}
