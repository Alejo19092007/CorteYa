{{--
    Cambio de una tarjeta frente al periodo anterior.
    El COLOR depende de si el cambio es bueno o malo, no de si sube o baja:
    más ingresos es verde, pero menos cancelaciones también es verde.
--}}
@php
    $clase = match (true) {
        $v['bueno'] === true => 'cy-var-bueno',
        $v['bueno'] === false => 'cy-var-malo',
        default => 'text-secondary',
    };
@endphp

<div class="small {{ $clase }}">
    @if ($v['direccion'] === 'igual')
        <i class="bi bi-dash" aria-hidden="true"></i> Sin cambios {{ $comparado }}
    @elseif ($v['direccion'] === 'nuevo')
        <i class="bi bi-arrow-up" aria-hidden="true"></i> Nuevo {{ $comparado }}
    @else
        <i class="bi {{ $v['direccion'] === 'sube' ? 'bi-arrow-up' : 'bi-arrow-down' }}" aria-hidden="true"></i>
        <span class="visually-hidden">{{ $v['direccion'] === 'sube' ? 'Subió' : 'Bajó' }}</span>
        {{ $v['valor'] }}{{ $v['porcentual'] ? '%' : '' }} {{ $comparado }}
    @endif
</div>
