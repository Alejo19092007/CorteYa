@extends('layouts.app')

@section('titulo', 'Mi agenda · CorteYa')

@php
    use App\Models\Turno;

    $ahora = now();
    // Los cancelados no cuentan como "turnos de hoy" (igual que en el dashboard del administrador)
    $deHoy = $turnos->where('estado', '!=', Turno::CANCELADO)->count();
    $atendidos = $turnos->where('estado', Turno::ATENDIDO)->count();
@endphp

@section('contenido')
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
            <h1 class="h2 mb-1"><i class="bi bi-calendar3"></i> Mi agenda</h1>
            <p class="text-secondary mb-0">¡Hola, {{ auth()->user()->name }}! Aquí puedes ver y gestionar tus turnos del día.</p>
        </div>
        <div class="text-secondary">
            <i class="bi bi-calendar3"></i> {{ $ahora->locale('es')->isoFormat('ddd, D [de] MMM [de] YYYY') }}
        </div>
    </div>

    @unless ($barbero)
        <div class="alert alert-warning">Tu usuario no tiene un perfil de barbero asociado. Pídele al administrador que lo cree.</div>
    @endunless

    <div class="cy-tarjeta p-3 mb-3 d-flex gap-4">
        <div><span class="fs-3 fw-bold">{{ $deHoy }}</span> <span class="text-secondary">turnos hoy</span></div>
        <div><span class="fs-3 fw-bold text-success">{{ $atendidos }}</span> <span class="text-secondary">atendidos</span></div>
    </div>

    @if ($turnos->isEmpty())
        <div class="cy-tarjeta p-5 text-center text-secondary">
            <i class="bi bi-calendar-x fs-1"></i>
            <p class="mb-0 mt-2">No tienes turnos para hoy.</p>
        </div>
    @else
        <div class="row g-3">
            @foreach ($turnos as $turno)
                @php
                    $iniciales = \Illuminate\Support\Str::of($turno->cliente->nombre)->explode(' ')->take(2)
                        ->map(fn ($p) => \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($p, 0, 1)))->implode('');
                    $yaPaso = $ahora->gte($turno->inicio());
                @endphp

                <div class="col-12 col-xl-6">
                    <div class="cy-tarjeta p-3 h-100 d-flex flex-column gap-3">
                        <div class="d-flex align-items-start gap-3">
                            <span class="cy-avatar" aria-hidden="true">{{ $iniciales }}</span>
                            <div class="flex-fill">
                                <div class="d-flex justify-content-between align-items-start gap-2">
                                    <div>
                                        <div class="fw-bold fs-5">{{ $turno->horaInicioLegible() }}</div>
                                        <div class="fw-semibold">{{ $turno->cliente->nombre }}</div>
                                    </div>
                                    <x-estado-turno :estado="$turno->estado" />
                                </div>
                                <div class="text-secondary small mt-1">
                                    {{ $turno->serviciosTexto() }} · <i class="bi bi-clock"></i> {{ $turno->duracionMin() }} min
                                </div>
                                <div class="small mt-1">@include('turnos._origen', ['origen' => $turno->origen])</div>
                            </div>
                        </div>

                        {{-- Botones según el estado (R4 y decisión 5) --}}
                        @if ($turno->estado === Turno::PENDIENTE)
                            <form method="POST" action="{{ route('turnos.estado', $turno) }}">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="estado" value="confirmado">
                                <button type="submit" class="btn btn-primary btn-lg w-100">
                                    <i class="bi bi-check2-circle"></i> Confirmar
                                </button>
                            </form>
                        @elseif ($turno->estado === Turno::CONFIRMADO)
                            <div class="row g-2">
                                <div class="col-6">
                                    <form method="POST" action="{{ route('turnos.estado', $turno) }}">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="estado" value="atendido">
                                        <button type="submit" class="btn btn-success btn-lg w-100" @disabled(! $yaPaso)>
                                            <i class="bi bi-check-lg"></i> Atendido
                                        </button>
                                    </form>
                                </div>
                                <div class="col-6">
                                    <form method="POST" action="{{ route('turnos.estado', $turno) }}">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="estado" value="no_asistio">
                                        <button type="submit" class="btn btn-no-asistio btn-lg w-100" @disabled(! $yaPaso)>
                                            <i class="bi bi-x-lg"></i> No asistió
                                        </button>
                                    </form>
                                </div>
                                @unless ($yaPaso)
                                    <div class="col-12 small text-secondary">
                                        <i class="bi bi-info-circle"></i> Podrás marcarlo desde las {{ $turno->horaInicioLegible() }}.
                                    </div>
                                @endunless
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif
@endsection
