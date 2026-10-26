@extends('layouts.app')

@section('titulo', 'Dashboard · CorteYa')

@section('contenido')
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-4">
        <div>
            <h1 class="h2 mb-1">Dashboard</h1>
            <p class="text-secondary mb-0">¡Bienvenido, {{ auth()->user()->name }}! Aquí tendrás un resumen de la barbería.</p>
        </div>
        <div class="text-secondary">
            <i class="bi bi-calendar3"></i> {{ now()->locale('es')->isoFormat('ddd, D [de] MMM [de] YYYY') }}
        </div>
    </div>

    {{-- Marcador: las 4 tarjetas y el gráfico llegan con DashboardService --}}
    <div class="cy-tarjeta p-4 mb-4">
        <h2 class="h5"><i class="bi bi-tools text-secondary"></i> Panel en construcción</h2>
        <p class="text-secondary mb-0">Las tarjetas de turnos, ingresos y el gráfico por barbero se agregan en los siguientes bloques.</p>
    </div>

    <div class="cy-tarjeta p-4">
        <h2 class="h5 mb-3">Estados del turno</h2>
        <div class="d-flex flex-wrap gap-2">
            <x-estado-turno estado="pendiente" />
            <x-estado-turno estado="confirmado" />
            <x-estado-turno estado="atendido" />
            <x-estado-turno estado="cancelado" />
            <x-estado-turno estado="no_asistio" />
        </div>
    </div>
@endsection
