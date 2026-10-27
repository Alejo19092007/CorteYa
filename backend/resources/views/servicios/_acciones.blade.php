{{--
    Acciones de un servicio. "Eliminar" solo aparece si nunca se usó en un turno;
    si ya tiene historial, se desactiva (así deja de ofrecerse pero los turnos antiguos se conservan).
--}}
<div class="d-inline-flex gap-1">
    <a href="{{ route('servicios.edit', $servicio) }}" class="btn btn-sm btn-outline-primary" title="Editar" aria-label="Editar servicio">
        <i class="bi bi-pencil"></i>
    </a>

    <form method="POST" action="{{ route('servicios.activo', $servicio) }}" class="d-inline">
        @csrf
        @method('PATCH')
        <button type="submit" class="btn btn-sm btn-outline-secondary"
                title="{{ $servicio->activo ? 'Desactivar' : 'Activar' }}"
                aria-label="{{ $servicio->activo ? 'Desactivar servicio' : 'Activar servicio' }}">
            <i class="bi {{ $servicio->activo ? 'bi-toggle-on' : 'bi-toggle-off' }}"></i>
        </button>
    </form>

    @if ($servicio->turnos_count === 0)
        <button type="button" class="btn btn-sm btn-outline-danger" title="Eliminar" aria-label="Eliminar servicio"
                data-bs-toggle="modal" data-bs-target="#modalEliminar"
                data-accion="{{ route('servicios.destroy', $servicio) }}"
                data-resumen="{{ $servicio->nombre }}">
            <i class="bi bi-trash"></i>
        </button>
    @endif
</div>
