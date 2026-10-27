@extends('layouts.app')

@section('titulo', 'Editar barbero · CorteYa')

@section('contenido')
    <div class="mb-4">
        <h1 class="h2 mb-1"><i class="bi bi-pencil-square"></i> Editar barbero</h1>
        <p class="text-secondary mb-0">{{ $barbero->user->name }}</p>
    </div>

    @include('barberos._form', ['accion' => route('barberos.update', $barbero)])
@endsection
