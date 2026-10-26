{{--
    Insignia del estado de un turno. Se usa SIEMPRE este componente para que los colores
    sean idénticos en agenda, listado y dashboard.
    Uso: <x-estado-turno :estado="$turno->estado" />
--}}
@props(['estado'])

@php
    $etiquetas = [
        'pendiente' => 'Pendiente',
        'confirmado' => 'Confirmado',
        'atendido' => 'Atendido',
        'cancelado' => 'Cancelado',
        'no_asistio' => 'No asistió',
    ];
@endphp

<span {{ $attributes->class(['badge', 'badge-estado', 'badge-estado-'.$estado]) }}>{{ $etiquetas[$estado] ?? $estado }}</span>
