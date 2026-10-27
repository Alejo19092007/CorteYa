@extends('layouts.app')

@section('titulo', 'Nuevo barbero · CorteYa')

@section('contenido')
    <div class="mb-4">
        <h1 class="h2 mb-1"><i class="bi bi-person-plus"></i> Nuevo barbero</h1>
        <p class="text-secondary mb-0">Crea su cuenta de acceso y define sus días y horas de trabajo.</p>
    </div>

    @include('barberos._form', ['accion' => route('barberos.store')])
@endsection
