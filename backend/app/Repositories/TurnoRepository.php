<?php

namespace App\Repositories;

use App\Models\Servicio;
use App\Models\Turno;
use Illuminate\Support\Collection;

/**
 * Consultas y escrituras de turnos. Aquí no hay reglas de negocio: eso es del TurnoService.
 */
class TurnoRepository
{
    /** Servicios que se ofrecen (activos) entre los ids pedidos. */
    public function serviciosActivos(array $ids): Collection
    {
        return Servicio::whereIn('id', $ids)->where('activo', true)->get();
    }

    /**
     * Turnos de un barbero en una fecha que OCUPAN su agenda (los cancelados la liberan).
     */
    public function ocupadosDelBarbero(int $barberoId, string $fecha, ?int $ignorarTurnoId = null): Collection
    {
        return Turno::where('barbero_id', $barberoId)
            ->whereDate('fecha', $fecha)
            ->where('estado', '!=', Turno::CANCELADO)
            ->when($ignorarTurnoId, fn ($q) => $q->where('id', '!=', $ignorarTurnoId))
            ->orderBy('hora_inicio')
            ->get();
    }

    /**
     * Primer turno que se solapa con [horaInicio, horaFin), o null si el hueco está libre.
     * Dos intervalos se solapan cuando cada uno empieza antes de que termine el otro;
     * por eso un turno que empieza justo cuando otro termina NO se solapa.
     * $ignorarTurnoId sirve al editar: un turno no puede chocar consigo mismo.
     */
    public function turnoQueSeCruza(int $barberoId, string $fecha, string $horaInicio, string $horaFin, ?int $ignorarTurnoId = null): ?Turno
    {
        return Turno::where('barbero_id', $barberoId)
            ->whereDate('fecha', $fecha)
            ->where('estado', '!=', Turno::CANCELADO)
            ->where('hora_inicio', '<', $horaFin)
            ->where('hora_fin', '>', $horaInicio)
            ->when($ignorarTurnoId, fn ($q) => $q->where('id', '!=', $ignorarTurnoId))
            ->first();
    }

    /** Trae el turno bloqueado para que dos cambios simultáneos no se pisen. */
    public function bloquear(int $turnoId): Turno
    {
        return Turno::lockForUpdate()->findOrFail($turnoId);
    }

    /**
     * Crea el turno y sus líneas de servicio.
     *
     * @param  array<int, array{precio: mixed, duracion_min: int}>  $lineas  servicio_id => valores congelados
     */
    public function crear(array $datos, array $lineas): Turno
    {
        $turno = Turno::create($datos);
        $turno->servicios()->attach($lineas);

        return $turno;
    }

    /** Actualiza el turno y reemplaza sus líneas de servicio (sync quita las que ya no están). */
    public function actualizar(Turno $turno, array $datos, array $lineas): Turno
    {
        $turno->update($datos);
        $turno->servicios()->sync($lineas);

        return $turno;
    }

    public function cambiarEstado(Turno $turno, string $estado): Turno
    {
        $turno->update(['estado' => $estado]);

        return $turno;
    }
}
