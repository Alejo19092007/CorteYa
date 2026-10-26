@extends('layouts.guest')

@section('titulo', 'Iniciar sesión · CorteYa')

@section('contenido')
<div class="cy-login-card card border-0 shadow-lg">
    <div class="card-body p-4 p-sm-5">

        <div class="text-center mb-4">
            <i class="bi bi-scissors cy-logo-icono"></i>
            <h1 class="cy-logo-texto mb-0">CORTEYA</h1>
            <p class="cy-logo-sub mb-0">SISTEMA DE TURNOS</p>
        </div>

        <form method="POST" action="{{ route('login.store') }}" novalidate>
            @csrf

            {{-- Etiquetas ocultas visualmente: los lectores de pantalla las necesitan --}}
            <div class="mb-3">
                <label for="email" class="visually-hidden">Correo electrónico</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                    <input type="email" id="email" name="email"
                           class="form-control {{ $errors->any() ? 'border-danger' : '' }}"
                           placeholder="Correo electrónico" value="{{ old('email') }}"
                           autocomplete="username" required autofocus>
                </div>
            </div>

            <div class="mb-2">
                <label for="password" class="visually-hidden">Contraseña</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-lock"></i></span>
                    <input type="password" id="password" name="password"
                           class="form-control {{ $errors->any() ? 'border-danger' : '' }}"
                           placeholder="Contraseña" autocomplete="current-password" required>
                    <button type="button" id="verClave" class="btn btn-outline-secondary"
                            aria-label="Mostrar contraseña">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>
            </div>

            {{-- Un solo mensaje de error, debajo de la contraseña --}}
            @if ($errors->any())
                <p class="cy-error-texto mb-3" role="alert">
                    <i class="bi bi-exclamation-circle-fill"></i> {{ $errors->first() }}
                </p>
            @else
                <div class="mb-3"></div>
            @endif

            <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">Ingresar</button>
        </form>

    </div>
</div>
@endsection

@push('scripts')
    <script src="{{ asset('js/login.js') }}"></script>
@endpush
