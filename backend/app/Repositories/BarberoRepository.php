<?php

namespace App\Repositories;

use App\Models\Barbero;
use App\Models\Horario;

/**
 * Consultas sobre barberos y sus horarios. Aquí no hay reglas de negocio: solo Eloquent.
 */
class BarberoRepository
{
    /**
     * Trae al barbero BLOQUEANDO su fila hasta que termine la transacción.
     * Dos reservas simultáneas del mismo barbero (panel y bot) quedan en fila: la segunda
     * espera y ya ve el turno de la primera, así R1 no se puede saltar por una carrera.
     */
    public function bloquear(int $barberoId): Barbero
    {
        return Barbero::lockForUpdate()->findOrFail($barberoId);
    }

    /** Jornada del barbero en un día de la semana (1 = lunes ... 7 = domingo), o null si no trabaja. */
    public function horarioDelDia(int $barberoId, int $diaSemana): ?Horario
    {
        return Horario::where('barbero_id', $barberoId)
            ->where('dia_semana', $diaSemana)
            ->first();
    }

    public function encontrar(int $barberoId): ?Barbero
    {
        return Barbero::find($barberoId);
    }
}
