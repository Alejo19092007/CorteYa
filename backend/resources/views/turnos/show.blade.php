@extends('layouts.app')

@section('titulo', 'Turno #'.$turno->id.' · CorteYa')

@section('contenido')
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-4">
        <div>
            <h1 class="h2 mb-1">Turno #{{ $turno->id }}</h1>
            <p class="text-secondary mb-0">{{ $turno->fecha->locale('es')->isoFormat('dddd D [de] MMMM [de] YYYY') }}</p>
        </div>
        <x-estado-turno :estado="$turno->estado" class="fs-6" />
    </div>

    <div class="row g-3">
        <div class="col-12 col-lg-7">
            <div class="cy-tarjeta p-3 p-lg-4 h-100">
                <dl class="row mb-0">
                    <dt class="col-sm-4 text-secondary fw-normal">Cliente</dt>
                    <dd class="col-sm-8">{{ $turno->cliente->nombre }} <span class="text-secondary">· {{ $turno->cliente->telefono }}</span></dd>

                    <dt class="col-sm-4 text-secondary fw-normal">Barbero</dt>
                    <dd class="col-sm-8">{{ $turno->barbero->user->name }}</dd>

                    <dt class="col-sm-4 text-secondary fw-normal">Horario</dt>
                    <dd class="col-sm-8">{{ $turno->horaInicioLegible() }} a {{ $turno->horaFinLegible() }} ({{ $turno->duracionMin() }} min)</dd>

                    <dt class="col-sm-4 text-secondary fw-normal">Origen</dt>
                    <dd class="col-sm-8">@include('turnos._origen', ['origen' => $turno->origen])</dd>

                    <dt class="col-sm-4 text-secondary fw-normal">Creado</dt>
                    <dd class="col-sm-8 mb-0">{{ $turno->created_at->format('d/m/Y h:i a') }}</dd>
                </dl>
            </div>
        </div>

        <div class="col-12 col-lg-5">
            <div class="cy-tarjeta p-3 p-lg-4 h-100">
                <h2 class="h5 mb-3">Servicios</h2>
                {{-- Se muestra el precio CONGELADO al reservar (pivote), no el precio actual del servicio --}}
                <ul class="list-unstyled mb-3">
                    @foreach ($turno->servicios as $servicio)
                        <li class="d-flex justify-content-between py-1 border-bottom">
                            <span>{{ $servicio->nombre }} <span class="text-secondary small">({{ $servicio->pivot->duracion_min }} min)</span></span>
                            <span>${{ number_format($servicio->pivot->precio, 0, ',', '.') }}</span>
                        </li>
                    @endforeach
                </ul>
                <div class="d-flex justify-content-between fs-5 fw-bold">
                    <span>Total</span>
                    <span>${{ number_format($turno->precio_total, 0, ',', '.') }}</span>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex flex-wrap justify-content-between gap-2 mt-3">
        <a href="{{ route('turnos.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Volver al listado</a>
        @include('turnos._acciones', ['turno' => $turno])
    </div>

    @include('turnos._modal_cancelar')
@endsection

@push('scripts')
    <script src="{{ asset('js/turnos-cancelar.js') }}"></script>
@endpush
