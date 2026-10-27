@extends('layouts.app')

@section('titulo', 'Dashboard · CorteYa')

@php
    // [clave, texto, ícono, formato]
    $tarjetasDefinidas = [
        ['turnosHoy', 'Turnos de hoy', 'bi-calendar-check', 'numero'],
        ['ingresosMes', 'Ingresos del mes', 'bi-cash-stack', 'dinero'],
        ['clientesNuevos', 'Clientes nuevos', 'bi-person-plus', 'numero'],
        ['cancelaciones', 'Cancelaciones del mes', 'bi-x-circle', 'numero'],
    ];
    $hayIngresos = array_sum($grafico['valores']) > 0;
@endphp

@section('contenido')
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-4">
        <div>
            <h1 class="h2 mb-1">Dashboard</h1>
            <p class="text-secondary mb-0">¡Bienvenido, {{ auth()->user()->name }}! Aquí tienes un resumen de la actividad de la barbería.</p>
        </div>
        <div class="text-secondary">
            <i class="bi bi-calendar3"></i> {{ $fecha->locale('es')->isoFormat('ddd, D [de] MMM [de] YYYY') }}
        </div>
    </div>

    {{-- Las 4 tarjetas: 2 por fila en celular, 4 en escritorio --}}
    <div class="row g-3 mb-3">
        @foreach ($tarjetasDefinidas as [$clave, $titulo, $icono, $formato])
            @php $tarjeta = $tarjetas[$clave]; @endphp
            <div class="col-6 col-lg-3">
                <div class="cy-tarjeta p-3 h-100">
                    <div class="d-flex align-items-center gap-2 text-secondary small">
                        <i class="bi {{ $icono }} fs-5" aria-hidden="true"></i> {{ $titulo }}
                    </div>
                    <div class="cy-cifra my-1">
                        {{ $formato === 'dinero' ? '$'.number_format($tarjeta['valor'], 0, ',', '.') : $tarjeta['valor'] }}
                    </div>
                    @include('panel._variacion', ['v' => $tarjeta['variacion'], 'comparado' => $tarjeta['comparado']])
                </div>
            </div>
        @endforeach
    </div>

    <div class="row g-3">
        {{-- Gráfico de ingresos por barbero (Chart.js, barras doradas) --}}
        <div class="col-12 col-lg-7">
            <div class="cy-tarjeta p-3 p-lg-4 h-100">
                <h2 class="h5 mb-0">Ingresos del mes por barbero</h2>
                <p class="text-secondary small mb-3">{{ ucfirst($mes) }} · solo turnos atendidos</p>

                @if ($hayIngresos)
                    <div class="cy-grafico">
                        <canvas id="graficoIngresos" role="img"
                                aria-label="Gráfico de barras con los ingresos de cada barbero en {{ $mes }}"
                                data-grafico="{{ json_encode($grafico) }}"></canvas>
                    </div>

                    {{-- Alternativa para lectores de pantalla: los mismos datos en una tabla --}}
                    <table class="visually-hidden">
                        <caption>Ingresos por barbero</caption>
                        <thead><tr><th>Barbero</th><th>Ingresos</th></tr></thead>
                        <tbody>
                            @foreach ($grafico['etiquetas'] as $i => $nombre)
                                <tr><td>{{ $nombre }}</td><td>${{ number_format($grafico['valores'][$i], 0, ',', '.') }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                @else
                    <div class="text-center text-secondary py-5">
                        <i class="bi bi-bar-chart fs-1"></i>
                        <p class="mb-0 mt-2">Aún no hay turnos atendidos este mes.</p>
                    </div>
                @endif
            </div>
        </div>

        {{-- Turnos de hoy --}}
        <div class="col-12 col-lg-5">
            <div class="cy-tarjeta p-3 p-lg-4 h-100">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2 class="h5 mb-0">Turnos de hoy</h2>
                    <a href="{{ route('turnos.index', ['desde' => $fecha->toDateString(), 'hasta' => $fecha->toDateString()]) }}"
                       class="small">Ver todos <i class="bi bi-arrow-right"></i></a>
                </div>

                @if ($turnosDeHoy->isEmpty())
                    <div class="text-center text-secondary py-4">
                        <i class="bi bi-calendar-x fs-1"></i>
                        <p class="mb-0 mt-2">No hay turnos para hoy.</p>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr><th>Hora</th><th>Cliente</th><th>Barbero</th><th>Estado</th><th><span class="visually-hidden">Origen</span></th></tr>
                            </thead>
                            <tbody>
                                @foreach ($turnosDeHoy as $turno)
                                    <tr>
                                        <td class="text-nowrap">{{ $turno->horaInicioLegible() }}</td>
                                        <td>{{ $turno->cliente->nombre }}</td>
                                        <td>{{ $turno->barbero->user->name }}</td>
                                        <td><x-estado-turno :estado="$turno->estado" /></td>
                                        <td>
                                            @if ($turno->origen === 'whatsapp')
                                                <i class="bi bi-whatsapp text-success" title="WhatsApp" aria-label="WhatsApp"></i>
                                            @else
                                                <i class="bi bi-display text-secondary" title="Panel" aria-label="Panel"></i>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    @if ($hayIngresos)
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
        <script src="{{ asset('js/dashboard.js') }}"></script>
    @endif
@endpush
