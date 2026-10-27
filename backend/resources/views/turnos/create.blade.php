@extends('layouts.app')

@section('titulo', 'Nuevo turno · CorteYa')

@section('contenido')
    <div class="mb-4">
        <h1 class="h2 mb-1"><i class="bi bi-calendar-plus"></i> Nuevo turno</h1>
        <p class="text-secondary mb-0">Registra una nueva cita en el sistema.</p>
    </div>

    @include('turnos._form', ['accion' => route('turnos.store')])
@endsection
