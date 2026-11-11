@extends('layouts.app')

@section('titulo', 'Mi mes · CorteYa')

@php
    $dinero = fn ($valor) => '$'.number_format($valor, 0, ',', '.');
@endphp

@section('contenido')
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
            <h1 class="h2 mb-1"><i class="bi bi-bar-chart-line"></i> Mi mes</h1>
            <p class="text-secondary mb-0">Tus turnos y lo que has cobrado, mes a mes.</p>
        </div>

        @if ($resumen)
            {{-- Navegación entre meses: nunca hacia el futuro (no hay nada que mostrar) --}}
            <div class="d-flex align-items-center gap-2">
                <a href="{{ route('mi-mes', ['mes' => $resumen['anterior']]) }}" class="btn btn-outline-secondary" aria-label="Mes anterior">
                    <i class="bi bi-chevron-left"></i>
                </a>
                <span class="fw-semibold text-capitalize px-1">{{ $resumen['mes'] }}</span>
                @if ($resumen['esMesActual'])
                    <span class="btn btn-outline-secondary disabled" aria-label="No hay meses futuros"><i class="bi bi-chevron-right"></i></span>
                @else
                    <a href="{{ route('mi-mes', ['mes' => $resumen['siguiente']]) }}" class="btn btn-outline-secondary" aria-label="Mes siguiente">
                        <i class="bi bi-chevron-right"></i>
                    </a>
                @endif
            </div>
        @endif
    </div>

    @unless ($resumen)
        <div class="alert alert-warning">Tu usuario no tiene un perfil de barbero asociado. Pídele al administrador que lo cree.</div>
    @else
        <div class="row g-3 mb-3">
            <div class="col-6 col-lg-3">
                <div class="cy-tarjeta p-3 h-100">
                    <div class="text-secondary small">Turnos del mes</div>
                    <div class="fs-3 fw-bold">{{ $resumen['total'] }}</div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="cy-tarjeta p-3 h-100">
                    <div class="text-secondary small">Atendidos</div>
                    <div class="fs-3 fw-bold text-success">{{ $resumen['atendidos'] }}</div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="cy-tarjeta p-3 h-100">
                    <div class="text-secondary small">Cobrado en el mes</div>
                    <div class="fs-3 fw-bold">{{ $dinero($resumen['cobrado']) }}</div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="cy-tarjeta p-3 h-100">
                    <div class="text-secondary small">Por cobrar</div>
                    <div class="fs-3 fw-bold {{ $resumen['porCobrar']['cantidad'] > 0 ? 'text-warning-emphasis' : 'text-secondary' }}">
                        {{ $dinero($resumen['porCobrar']['total']) }}
                    </div>
                    <div class="text-secondary small">{{ $resumen['porCobrar']['cantidad'] }} {{ $resumen['porCobrar']['cantidad'] === 1 ? 'turno' : 'turnos' }}</div>
                </div>
            </div>
        </div>

        @if ($resumen['turnos']->isEmpty())
            <div class="cy-tarjeta p-5 text-center text-secondary">
                <i class="bi bi-calendar-x fs-1"></i>
                <p class="mb-0 mt-2">No tuviste turnos en {{ $resumen['mes'] }}.</p>
            </div>
        @else
            <div class="cy-tarjeta">
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Cliente</th>
                                <th>Servicios</th>
                                <th>Estado</th>
                                <th>Pago</th>
                                <th class="text-end">Valor</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($resumen['turnos'] as $turno)
                                <tr>
                                    <td class="text-nowrap">
                                        {{ $turno->fecha->locale('es')->isoFormat('ddd D [de] MMM') }}
                                        <div class="text-secondary small">{{ $turno->horaInicioLegible() }}</div>
                                    </td>
                                    <td>{{ $turno->cliente->nombre }}</td>
                                    <td>{{ $turno->serviciosTexto() }}</td>
                                    <td><x-estado-turno :estado="$turno->estado" /></td>
                                    <td><x-pago-turno :turno="$turno" /></td>
                                    <td class="text-end text-nowrap">{{ $dinero($turno->precio_total) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="mt-3">
                {{ $resumen['turnos']->links() }}
            </div>
        @endif
    @endunless
@endsection
