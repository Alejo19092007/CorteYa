{{--
    Acciones de un turno en el listado. Según R4:
      - Editar y Cancelar: solo pendiente o confirmado.
      - Confirmar: solo pendiente.
      - Atendido / No asistió: solo confirmado, y únicamente cuando ya llegó la hora de inicio (R4 y decisión 4).
        Antes de esa hora los botones salen deshabilitados y dicen desde cuándo se pueden usar.
      - Registrar pago (R6): solo atendido y sin pago registrado.
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

    @if ($turno->estado === \App\Models\Turno::CONFIRMADO)
        @php $yaLlegoLaHora = now()->gte($turno->inicio()); @endphp
        <form method="POST" action="{{ route('turnos.estado', $turno) }}" class="d-inline">
            @csrf
            @method('PATCH')
            <input type="hidden" name="estado" value="atendido">
            <button type="submit" class="btn btn-sm btn-outline-success" aria-label="Marcar turno como atendido"
                    title="{{ $yaLlegoLaHora ? 'Marcar como atendido' : 'Disponible desde las '.$turno->horaInicioLegible() }}"
                    @disabled(! $yaLlegoLaHora)>
                <i class="bi bi-person-check"></i>
            </button>
        </form>
        <form method="POST" action="{{ route('turnos.estado', $turno) }}" class="d-inline">
            @csrf
            @method('PATCH')
            <input type="hidden" name="estado" value="no_asistio">
            <button type="submit" class="btn btn-sm btn-no-asistio" aria-label="Marcar que el cliente no asistió"
                    title="{{ $yaLlegoLaHora ? 'Marcar como no asistió' : 'Disponible desde las '.$turno->horaInicioLegible() }}"
                    @disabled(! $yaLlegoLaHora)>
                <i class="bi bi-person-x"></i>
            </button>
        </form>
    @endif

    {{-- R6: el pago se registra solo en un turno atendido que todavía no está pagado --}}
    @if ($turno->estaPorCobrar())
        <button type="button" class="btn btn-sm btn-outline-success" title="Registrar pago" aria-label="Registrar pago del turno"
                data-bs-toggle="modal" data-bs-target="#modalPago"
                data-accion="{{ route('turnos.pago', $turno) }}"
                data-resumen="{{ $turno->cliente->nombre }} · {{ $turno->fecha->format('d/m/Y') }} · {{ $turno->horaInicioLegible() }}"
                data-total="${{ number_format($turno->precio_total, 0, ',', '.') }}">
            <i class="bi bi-cash-coin"></i>
        </button>
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
