{{--
    Acciones de un turno en el listado. Según R4:
      - Editar y Cancelar: solo pendiente o confirmado.
      - Confirmar: solo pendiente.
    "Cancelar" cambia el estado (no borra), por eso lleva ícono de x y no de papelera.
--}}
<div class="d-inline-flex gap-1">
    <a href="{{ route('turnos.show', $turno) }}" class="btn btn-sm btn-outline-secondary" title="Ver" aria-label="Ver turno">
        <i class="bi bi-eye"></i>
    </a>

    @if ($turno->esEditable())
        <a href="{{ route('turnos.edit', $turno) }}" class="btn btn-sm btn-outline-primary" title="Editar" aria-label="Editar turno">
            <i class="bi bi-pencil"></i>
        </a>
    @endif

    @if ($turno->estado === \App\Models\Turno::PENDIENTE)
        <form method="POST" action="{{ route('turnos.estado', $turno) }}" class="d-inline">
            @csrf
            @method('PATCH')
            <input type="hidden" name="estado" value="confirmado">
            <button type="submit" class="btn btn-sm btn-outline-success" title="Confirmar" aria-label="Confirmar turno">
                <i class="bi bi-check-circle"></i>
            </button>
        </form>
    @endif

    @if ($turno->esEditable())
        <button type="button" class="btn btn-sm btn-outline-danger" title="Cancelar" aria-label="Cancelar turno"
                data-bs-toggle="modal" data-bs-target="#modalCancelar"
                data-accion="{{ route('turnos.destroy', $turno) }}"
                data-resumen="{{ $turno->cliente->nombre }} · {{ $turno->fecha->format('d/m/Y') }} · {{ $turno->horaInicioLegible() }}">
            <i class="bi bi-x-circle"></i>
        </button>
    @endif
</div>
