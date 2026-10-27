@extends('layouts.app')

@section('titulo', 'Editar turno · CorteYa')

@section('contenido')
    <div class="mb-4">
        <h1 class="h2 mb-1"><i class="bi bi-pencil-square"></i> Editar turno #{{ $turno->id }}</h1>
        <p class="text-secondary mb-0">Solo se pueden editar los turnos pendientes o confirmados.</p>
    </div>

    @include('turnos._form', ['accion' => route('turnos.update', $turno)])
@endsection
