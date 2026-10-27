<?php

namespace App\Repositories;

use App\Models\Servicio;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Consultas sobre servicios. Aquí no hay reglas de negocio: solo Eloquent.
 */
class ServicioRepository
{
    /**
     * Listado del panel con búsqueda por nombre, filtro por activo y paginación.
     * withCount('turnos') permite mostrar cuántos turnos usaron cada servicio.
     */
    public function buscar(array $filtros, int $porPagina = 10): LengthAwarePaginator
    {
        return Servicio::query()
            ->withCount('turnos')
            ->when($filtros['q'] ?? null, fn ($q, $texto) => $q->where('nombre', 'like', "%{$texto}%"))
            ->when(isset($filtros['activo']) && $filtros['activo'] !== '', fn ($q) => $q->where('activo', (bool) $filtros['activo']))
            ->orderBy('nombre')
            ->paginate($porPagina)
            ->withQueryString();
    }

    public function crear(array $datos): Servicio
    {
        return Servicio::create($datos);
    }

    public function actualizar(Servicio $servicio, array $datos): Servicio
    {
        $servicio->update($datos);

        return $servicio;
    }

    /** ¿Algún turno (pasado o futuro) incluye este servicio? */
    public function estaEnUso(Servicio $servicio): bool
    {
        return $servicio->turnos()->exists();
    }

    public function eliminar(Servicio $servicio): void
    {
        $servicio->delete();
    }
}
