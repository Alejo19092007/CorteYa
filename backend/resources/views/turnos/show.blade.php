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
                    <dd class="col-sm-8">{{ $turno->cliente->nombre }} <span class="text-secondary">· {{ $turno->cliente->telefono ?? 'sin teléfono' }}</span></dd>

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

    {{-- R6 · Pago: solo tiene sentido en un turno atendido --}}
    @if ($turno->estado === \App\Models\Turno::ATENDIDO)
        <div class="cy-tarjeta p-3 p-lg-4 mt-3">
            <h2 class="h5 mb-3"><i class="bi bi-cash-coin"></i> Pago</h2>

            @if ($turno->estaPagado())
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div>
                        <x-pago-turno :turno="$turno" class="fs-6" />
                        <div class="text-secondary small mt-1">
                            ${{ number_format($turno->precio_total, 0, ',', '.') }} recibidos el {{ $turno->pagado_en->format('d/m/Y') }} a las {{ $turno->pagado_en->format('h:i a') }}
                        </div>
                    </div>
                    {{-- Corregir un pago anotado por error: solo el dueño --}}
                    @can('anularPago', $turno)
                        <form method="POST" action="{{ route('turnos.pago.anular', $turno) }}"
                              onsubmit="return confirm('¿Anular el pago registrado? El turno volverá a quedar por cobrar.');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-outline-danger btn-sm"><i class="bi bi-arrow-counterclockwise"></i> Anular pago</button>
                        </form>
                    @endcan
                </div>
            @else
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div>
                        <x-pago-turno :turno="$turno" class="fs-6" />
                        <div class="text-secondary small mt-1">Faltan por cobrar ${{ number_format($turno->precio_total, 0, ',', '.') }}.</div>
                    </div>
                    @can('registrarPago', $turno)
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalPago"
                                data-accion="{{ route('turnos.pago', $turno) }}"
                                data-resumen="{{ $turno->cliente->nombre }} · {{ $turno->fecha->format('d/m/Y') }} · {{ $turno->horaInicioLegible() }}"
                                data-total="${{ number_format($turno->precio_total, 0, ',', '.') }}">
                            <i class="bi bi-cash-coin"></i> Registrar pago
                        </button>
                    @endcan
                </div>
            @endif
        </div>
    @endif

    <div class="d-flex flex-wrap justify-content-between gap-2 mt-3">
        <a href="{{ route('turnos.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Volver al listado</a>
        @include('turnos._acciones', ['turno' => $turno])
    </div>

    @include('turnos._modal_cancelar')
    @include('turnos._modal_pago')
@endsection

@push('scripts')
    <script src="{{ asset('js/turnos-cancelar.js') }}"></script>
    <script src="{{ asset('js/turnos-pago.js') }}"></script>
@endpush
