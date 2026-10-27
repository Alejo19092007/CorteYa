{{--
    Acciones de un barbero. "Eliminar" solo aparece si nunca tuvo turnos;
    si ya tiene historial, se desactiva (deja de recibir turnos, pero sus turnos antiguos se conservan).
--}}
<div class="d-inline-flex gap-1">
    <a href="{{ route('barberos.edit', $barbero) }}" class="btn btn-sm btn-outline-primary" title="Editar" aria-label="Editar barbero">
        <i class="bi bi-pencil"></i>
    </a>

    <form method="POST" action="{{ route('barberos.activo', $barbero) }}" class="d-inline">
        @csrf
        @method('PATCH')
        <button type="submit" class="btn btn-sm btn-outline-secondary"
                title="{{ $barbero->activo ? 'Desactivar' : 'Activar' }}"
                aria-label="{{ $barbero->activo ? 'Desactivar barbero' : 'Activar barbero' }}">
            <i class="bi {{ $barbero->activo ? 'bi-toggle-on' : 'bi-toggle-off' }}"></i>
        </button>
    </form>

    @if ($barbero->turnos_count === 0)
        <button type="button" class="btn btn-sm btn-outline-danger" title="Eliminar" aria-label="Eliminar barbero"
                data-bs-toggle="modal" data-bs-target="#modalEliminar"
                data-accion="{{ route('barberos.destroy', $barbero) }}"
                data-resumen="{{ $barbero->user->name }}">
            <i class="bi bi-trash"></i>
        </button>
    @endif
</div>
