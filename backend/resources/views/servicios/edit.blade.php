@extends('layouts.app')

@section('titulo', 'Editar servicio · CorteYa')

@section('contenido')
    <div class="mb-4">
        <h1 class="h2 mb-1"><i class="bi bi-pencil-square"></i> Editar servicio</h1>
        <p class="text-secondary mb-0">{{ $servicio->nombre }}</p>
    </div>

    @include('servicios._form', ['accion' => route('servicios.update', $servicio)])
@endsection
