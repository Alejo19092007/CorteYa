<?php

namespace App\Repositories;

use App\Models\Servicio;
use App\Models\Turno;
use Carbon\Carbon;
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
        $hoy = today()->toDateString();   // zona horaria de la app: America/Bogota

        return Turno::query()
            ->with(['barbero.user', 'cliente', 'servicios'])
            ->when($filtros['q'] ?? null, fn ($q, $texto) => $q->whereHas('cliente', fn ($c) => $c
                ->where('nombre', 'like', "%{$texto}%")
                ->orWhere('telefono', 'like', "%{$texto}%")))
            ->when($filtros['barbero_id'] ?? null, fn ($q, $id) => $q->where('barbero_id', $id))
            ->when($filtros['estado'] ?? null, fn ($q, $estado) => $q->where('estado', $estado))
            // Pago (R6): "pagado" o "por_cobrar" (atendido sin pago registrado)
            ->when(($filtros['pago'] ?? null) === 'pagado', fn ($q) => $q->whereNotNull('pagado_en'))
            ->when(($filtros['pago'] ?? null) === 'por_cobrar', fn ($q) => $q->where('estado', Turno::ATENDIDO)->whereNull('pagado_en'))
            ->when($filtros['desde'] ?? null, fn ($q, $fecha) => $q->whereDate('fecha', '>=', $fecha))
            ->when($filtros['hasta'] ?? null, fn ($q, $fecha) => $q->whereDate('fecha', '<=', $fecha))
            // Orden de agenda: primero HOY y lo que viene (del día más cercano al más lejano y, dentro de cada día, de la
            // primera hora a la última); después los días PASADOS (del más reciente al más antiguo, y la hora igual).
            // Así lo que hay que atender siempre está arriba. Se hace con CASE (y no con dos consultas) para que la
            // paginación siga siendo una sola lista; DATE(fecha) porque SQLite guarda la fecha con hora y MySQL sin ella.
            ->orderByRaw('CASE WHEN DATE(fecha) >= ? THEN 0 ELSE 1 END', [$hoy])
            ->orderByRaw('CASE WHEN DATE(fecha) >= ? THEN fecha END ASC', [$hoy])
            ->orderByRaw('CASE WHEN DATE(fecha) < ? THEN fecha END DESC', [$hoy])
            ->orderByRaw('CASE WHEN DATE(fecha) >= ? THEN hora_inicio END ASC', [$hoy])
            ->orderByRaw('CASE WHEN DATE(fecha) < ? THEN hora_inicio END DESC', [$hoy])
            ->orderBy('id')   // desempate: dos turnos a la misma hora siempre salen en el mismo orden
            ->paginate($porPagina)
            ->withQueryString();
    }

    /** Turnos de un cliente, los más recientes primero. $estado (opcional) filtra por estado. */
    public function delCliente(int $clienteId, ?string $estado = null): Collection
    {
        return Turno::where('cliente_id', $clienteId)
            ->with(['barbero.user', 'cliente', 'servicios'])
            ->when($estado, fn ($q, $estado) => $q->where('estado', $estado))
            ->orderByDesc('fecha')
            ->orderByDesc('hora_inicio')
            ->limit(50)
            ->get();
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
     * Ingresos entre dos fechas (ambas incluidas): lo que de verdad se COBRÓ, es decir, los pagos
     * registrados (R6) en esas fechas. Un turno atendido que todavía no pagó no es dinero cobrado:
     * aparece aparte, como "por cobrar" (ver porCobrar()).
     */
    public function ingresosEntre(string $desde, string $hasta): float
    {
        // whereDate (y no whereBetween): compara solo el DÍA, así el último día del rango
        // entra completo sin importar cómo guarde la fecha cada motor de base de datos.
        return (float) Turno::whereNotNull('pagado_en')
            ->whereDate('pagado_en', '>=', $desde)
            ->whereDate('pagado_en', '<=', $hasta)
            ->sum('precio_total');
    }

    /**
     * Lo que se debe: turnos ATENDIDOS cuyo pago no se ha registrado (de cualquier fecha: una deuda
     * no desaparece al cambiar de mes).
     *
     * @return array{cantidad: int, total: float}
     */
    public function porCobrar(): array
    {
        $consulta = Turno::where('estado', Turno::ATENDIDO)->whereNull('pagado_en');

        return ['cantidad' => (int) $consulta->count(), 'total' => (float) $consulta->sum('precio_total')];
    }

    /**
     * Turnos de UN barbero cuya fecha cae en el rango (ambas incluidas), el más reciente primero. Paginado.
     * Incluye todos los estados (los cancelados y los que no asistieron también son parte de su mes).
     */
    public function delBarberoEntre(int $barberoId, string $desde, string $hasta, int $porPagina = 10): LengthAwarePaginator
    {
        return Turno::where('barbero_id', $barberoId)
            ->whereDate('fecha', '>=', $desde)
            ->whereDate('fecha', '<=', $hasta)
            ->with(['cliente', 'servicios'])
            ->orderByDesc('fecha')
            ->orderByDesc('hora_inicio')
            ->orderBy('id')
            ->paginate($porPagina)
            ->withQueryString();
    }

    /**
     * Cuántos turnos de un barbero hay por estado en el rango.
     *
     * @return array<string, int> estado => cantidad
     */
    public function contarPorEstadoDelBarbero(int $barberoId, string $desde, string $hasta): array
    {
        return Turno::where('barbero_id', $barberoId)
            ->whereDate('fecha', '>=', $desde)
            ->whereDate('fecha', '<=', $hasta)
            ->selectRaw('estado, COUNT(*) as total')
            ->groupBy('estado')
            ->pluck('total', 'estado')
            ->map(fn ($total) => (int) $total)
            ->all();
    }

    /** Lo que un barbero COBRÓ en el rango: pagos registrados (R6) por fecha de pago, igual que el dashboard. */
    public function cobradoPorBarberoEntre(int $barberoId, string $desde, string $hasta): float
    {
        return (float) Turno::where('barbero_id', $barberoId)
            ->whereNotNull('pagado_en')
            ->whereDate('pagado_en', '>=', $desde)
            ->whereDate('pagado_en', '<=', $hasta)
            ->sum('precio_total');
    }

    /**
     * Lo que le deben a un barbero: sus turnos atendidos sin pago (de cualquier fecha).
     *
     * @return array{cantidad: int, total: float}
     */
    public function porCobrarDelBarbero(int $barberoId): array
    {
        $consulta = Turno::where('barbero_id', $barberoId)->where('estado', Turno::ATENDIDO)->whereNull('pagado_en');

        return ['cantidad' => (int) $consulta->count(), 'total' => (float) $consulta->sum('precio_total')];
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

    /** Anota el pago (R6): cuándo y cómo. */
    public function registrarPago(Turno $turno, string $metodo, Carbon $cuando): Turno
    {
        $turno->update(['pagado_en' => $cuando, 'metodo_pago' => $metodo]);

        return $turno;
    }

    /** Quita el pago registrado (por si se anotó por error): el turno vuelve a "por cobrar". */
    public function anularPago(Turno $turno): Turno
    {
        $turno->update(['pagado_en' => null, 'metodo_pago' => null]);

        return $turno;
    }
}
