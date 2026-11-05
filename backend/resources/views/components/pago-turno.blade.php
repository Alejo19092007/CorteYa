{{--
    Insignia del pago de un turno (R6). Solo aplica a los turnos ATENDIDOS: los demás todavía no se pagan.
    "Por cobrar" = atendido y sin pago registrado.
    Uso: <x-pago-turno :turno="$turno" />
--}}
@props(['turno'])

@if ($turno->estado === \App\Models\Turno::ATENDIDO)
    @if ($turno->estaPagado())
        <span {{ $attributes->class(['badge', 'bg-success-subtle', 'text-success-emphasis', 'border', 'border-success-subtle']) }}
              title="Pagado el {{ $turno->pagado_en->format('d/m/Y h:i a') }}">
            <i class="bi bi-cash-coin" aria-hidden="true"></i> Pagado · {{ $turno->metodoPagoLegible() }}
        </span>
    @else
        <span {{ $attributes->class(['badge', 'bg-warning-subtle', 'text-warning-emphasis', 'border', 'border-warning-subtle']) }}>
            <i class="bi bi-hourglass-split" aria-hidden="true"></i> Por cobrar
        </span>
    @endif
@endif
