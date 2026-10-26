@extends('layouts.app')

@section('titulo', 'Mi agenda · CorteYa')

@section('contenido')
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-4">
        <div>
            <h1 class="h2 mb-1">Mi agenda</h1>
            <p class="text-secondary mb-0">¡Hola, {{ auth()->user()->name }}! Aquí verás y gestionarás tus turnos del día.</p>
        </div>
        <div class="text-secondary">
            <i class="bi bi-calendar3"></i> {{ now()->locale('es')->isoFormat('ddd, D [de] MMM [de] YYYY') }}
        </div>
    </div>

    {{-- Marcador: la lista del día con botones por estado llega en el bloque de turnos --}}
    <div class="cy-tarjeta p-4">
        <h2 class="h5"><i class="bi bi-tools text-secondary"></i> Agenda en construcción</h2>
        <p class="text-secondary mb-0">Tus turnos del día aparecerán aquí.</p>
    </div>
@endsection
