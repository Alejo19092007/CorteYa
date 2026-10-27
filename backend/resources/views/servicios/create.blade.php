@extends('layouts.app')

@section('titulo', 'Nuevo servicio · CorteYa')

@section('contenido')
    <div class="mb-4">
        <h1 class="h2 mb-1"><i class="bi bi-plus-circle"></i> Nuevo servicio</h1>
        <p class="text-secondary mb-0">Agrega un servicio al catálogo de la barbería.</p>
    </div>

    @include('servicios._form', ['accion' => route('servicios.store')])
@endsection
