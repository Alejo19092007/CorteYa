<?php

namespace App\Repositories;

use App\Models\Servicio;
use App\Models\Turno;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Consultas y escrituras de turnos. Aquí no hay reglas de negocio: eso es del TurnoService.
 */
class TurnoRepository
{
    /**
     * Listado del panel con búsqueda, filtros y paginación.
     *
     * Filtros (todos opcionales): q (nombre o teléfono del cliente), barbero_id, estado, desde, hasta.
     * withQueryString() hace que los enlaces de paginación conserven los filtros.
     * with(...) trae las relaciones de una vez: sin eso, cada fila dispararía varias consultas extra.
     */
    public function buscar(array $filtros, int $porPagina = 10): LengthAwarePaginator
    {
        return Turno::query()
            ->with(['barbero.user', 'cliente', 'servicios'])
            ->when($filtros['q'] ?? null, fn ($q, $texto) => $q->whereHas('cliente', fn ($c) => $c
                ->where('nombre', 'like', "%{$texto}%")
                ->orWhere('telefono', 'like', "%{$texto}%")))
            ->when($filtros['barbero_id'] ?? null, fn ($q, $id) => $q->where('barbero_id', $id))
            ->when($filtros['estado'] ?? null, fn ($q, $estado) => $q->where('estado', $estado))
            ->when($filtros['desde'] ?? null, fn ($q, $fecha) => $q->whereDate('fecha', '>=', $fecha))
            ->when($filtros['hasta'] ?? null, fn ($q, $fecha) => $q->whereDate('fecha', '<=', $fecha))
            ->orderByDesc('fecha')
            ->orderBy('hora_inicio')
            ->paginate($porPagina)
            ->withQueryString();
    }

    /** Agenda de un barbero en una fecha, por hora (incluye cancelados para que se vean). */
    public function agendaDelBarbero(int $barberoId, string $fecha): Collection
    {
        return Turno::where('barbero_id', $barberoId)
            ->whereDate('fecha', $fecha)
            ->with(['cliente', 'servicios'])
            ->orderBy('hora_inicio')
            ->get();
    }

    /** Todos los turnos de una fecha, por hora (agenda general del día, con los cancelados incluidos). */
    public function delDia(string $fecha): Collection
    {
        return Turno::whereDate('fecha', $fecha)
            ->with(['barbero.user', 'cliente'])
            ->orderBy('hora_inicio')
            ->get();
    }

    /** Turnos de una fecha SIN contar los cancelados (los que de verdad se van a atender). */
    public function contarDelDia(string $fecha): int
    {
        return Turno::whereDate('fecha', $fecha)
            ->where('estado', '!=', Turno::CANCELADO)
            ->count();
    }

    /**
     * Ingresos entre dos fechas (ambas incluidas): suma de turnos ATENDIDOS.
     * Un turno pendiente, confirmado, cancelado o de inasistencia todavía no es dinero cobrado.
     */
    public function ingresosEntre(string $desde, string $hasta): float
    {
        // whereDate (y no whereBetween): compara solo el DÍA, así el último día del rango
        // entra completo sin importar cómo guarde la fecha cada motor de base de datos.
        return (float) Turno::where('estado', Turno::ATENDIDO)
            ->whereDate('fecha', '>=', $desde)
            ->whereDate('fecha', '<=', $hasta)
            ->sum('precio_total');
    }

    /** Turnos cancelados cuya fecha cae entre las dos fechas (ambas incluidas). */
    public function cancelacionesEntre(string $desde, string $hasta): int
    {
        return Turno::where('estado', Turno::CANCELADO)
            ->whereDate('fecha', '>=', $desde)
            ->whereDate('fecha', '<=', $hasta)
            ->count();
    }

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
