@extends('layouts.app')

@section('titulo', 'Mi perfil · CorteYa')

@section('contenido')
    <div class="mb-3">
        <h1 class="h2 mb-1"><i class="bi bi-person-circle"></i> Mi perfil</h1>
        <p class="text-secondary mb-0">Tus datos y el horario en el que recibes turnos.</p>
    </div>

    @unless ($barbero)
        <div class="alert alert-warning">Tu usuario no tiene un perfil de barbero asociado. Pídele al administrador que lo cree.</div>
    @else
        @if ($errors->any())
            <div class="alert alert-danger" role="alert">
                <ul class="mb-0">
                    @foreach ($errors->all() as $mensaje)
                        <li>{{ $mensaje }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="row g-3">
            {{-- Datos de la cuenta: solo lectura. Los cambia el dueño en Barberos. --}}
            <div class="col-12 col-lg-6">
                <div class="cy-tarjeta p-3 p-lg-4 h-100">
                    <h2 class="h5 mb-3">Mis datos</h2>
                    <dl class="mb-0">
                        <dt class="text-secondary small">Nombre</dt>
                        <dd class="fw-semibold">{{ $barbero->user->name }}</dd>
                        <dt class="text-secondary small">Correo (para iniciar sesión)</dt>
                        <dd class="fw-semibold">{{ $barbero->user->email }}</dd>
                        <dt class="text-secondary small">Teléfono</dt>
                        <dd class="fw-semibold">{{ $barbero->telefono }}</dd>
                        <dt class="text-secondary small">Estado</dt>
                        <dd class="mb-0">
                            @if ($barbero->activo)
                                <span class="badge bg-success-subtle text-success-emphasis border border-success-subtle">Activo · recibe turnos</span>
                            @else
                                <span class="badge bg-secondary-subtle text-secondary-emphasis border">Inactivo</span>
                            @endif
                        </dd>
                    </dl>
                    <p class="text-secondary small mt-3 mb-0"><i class="bi bi-info-circle"></i> Para cambiar estos datos, habla con el administrador.</p>
                </div>
            </div>

            {{-- Horario: lo cambia el propio barbero --}}
            <form method="POST" action="{{ route('mi-perfil.horario') }}" class="col-12 col-lg-6" novalidate>
                @csrf
                @method('PUT')
                <div class="row g-0 h-100">
                    @include('barberos._horario', ['columna' => 'col-12'])
                </div>
                <div class="text-end mt-3">
                    <button type="submit" class="btn btn-primary px-4"><i class="bi bi-check-lg"></i> Guardar mi horario</button>
                </div>
            </form>
        </div>
    @endunless
@endsection
