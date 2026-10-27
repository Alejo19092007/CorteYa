<?php

namespace App\Policies;

use App\Models\Turno;
use App\Models\User;

/**
 * Quién puede tocar un turno concreto. El middleware "role" decide qué PANTALLAS ve cada rol;
 * esta política decide sobre qué TURNO puede actuar: un barbero solo sobre los suyos.
 */
class TurnoPolicy
{
    /** Confirmar, marcar atendido o no asistió: el admin en cualquiera; el barbero solo en los suyos. */
    public function cambiarEstado(User $usuario, Turno $turno): bool
    {
        if ($usuario->esAdmin()) {
            return true;
        }

        return $usuario->esBarbero()
            && $usuario->barbero !== null
            && $usuario->barbero->id === $turno->barbero_id;
    }
}
