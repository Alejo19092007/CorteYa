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

    /** Registrar el pago (R6): igual que cambiar el estado, el admin en cualquiera y el barbero en los suyos. */
    public function registrarPago(User $usuario, Turno $turno): bool
    {
        return $this->cambiarEstado($usuario, $turno);
    }

    /** Anular un pago ya registrado: solo el dueño. Es una corrección, y el barbero no debe poder borrar dinero cobrado. */
    public function anularPago(User $usuario, Turno $turno): bool
    {
        return $usuario->esAdmin();
    }
}
