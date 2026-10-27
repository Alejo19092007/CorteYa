@extends('layouts.app')

@section('titulo', 'Turnos · CorteYa')

@php
    use App\Models\Turno;

    $estados = [
        Turno::PENDIENTE => 'Pendiente',
        Turno::CONFIRMADO => 'Confirmado',
        Turno::ATENDIDO => 'Atendido',
        Turno::CANCELADO => 'Cancelado',
        Turno::NO_ASISTIO => 'No asistió',
    ];
    $hayFiltros = collect($filtros)->filter()->isNotEmpty();
@endphp

@section('contenido')
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-4">
        <div>
            <h1 class="h2 mb-1"><i class="bi bi-calendar3"></i> Listado de turnos</h1>
            <p class="text-secondary mb-0">Gestiona los turnos de la barbería. Puedes ver, editar o cancelar cada cita.</p>
        </div>
        <a href="{{ route('turnos.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-lg"></i> Nuevo turno
        </a>
    </div>

    {{-- Búsqueda y filtros: se envían por GET, así la URL se puede copiar y la paginación los conserva --}}
    <form method="GET" action="{{ route('turnos.index') }}" class="cy-tarjeta p-3 mb-3">
        <div class="row g-2 align-items-end">
            <div class="col-12 col-lg-3">
                <label for="q" class="form-label small mb-1">Cliente</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="search" id="q" name="q" class="form-control" placeholder="Nombre o teléfono"
                           value="{{ $filtros['q'] ?? '' }}">
                </div>
            </div>
            <div class="col-6 col-lg-2">
                <label for="barbero_id" class="form-label small mb-1">Barbero</label>
                <select id="barbero_id" name="barbero_id" class="form-select">
                    <option value="">Todos</option>
                    @foreach ($barberos as $barbero)
                        <option value="{{ $barbero->id }}" @selected(($filtros['barbero_id'] ?? '') == $barbero->id)>
                            {{ $barbero->user->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-lg-2">
                <label for="estado" class="form-label small mb-1">Estado</label>
                <select id="estado" name="estado" class="form-select">
                    <option value="">Todos</option>
                    @foreach ($estados as $valor => $etiqueta)
                        <option value="{{ $valor }}" @selected(($filtros['estado'] ?? '') === $valor)>{{ $etiqueta }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-lg-2">
                <label for="desde" class="form-label small mb-1">Desde</label>
                <input type="date" id="desde" name="desde" class="form-control" value="{{ $filtros['desde'] ?? '' }}">
            </div>
            <div class="col-6 col-lg-2">
                <label for="hasta" class="form-label small mb-1">Hasta</label>
                <input type="date" id="hasta" name="hasta" class="form-control" value="{{ $filtros['hasta'] ?? '' }}">
            </div>
            <div class="col-12 col-lg-1 d-flex gap-1">
                <button type="submit" class="btn btn-primary flex-fill" title="Filtrar" aria-label="Filtrar">
                    <i class="bi bi-funnel"></i>
                </button>
                @if ($hayFiltros)
                    <a href="{{ route('turnos.index') }}" class="btn btn-outline-secondary" title="Limpiar filtros" aria-label="Limpiar filtros">
                        <i class="bi bi-x-lg"></i>
                    </a>
                @endif
            </div>
        </div>
    </form>

    @if ($turnos->isEmpty())
        <div class="cy-tarjeta p-5 text-center text-secondary">
            <i class="bi bi-calendar-x fs-1"></i>
            <p class="mb-0 mt-2">No hay turnos {{ $hayFiltros ? 'con esos filtros' : 'registrados' }}.</p>
        </div>
    @else
        {{-- Escritorio: tabla --}}
        <div class="cy-tarjeta d-none d-lg-block">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Hora</th>
                            <th>Cliente</th>
                            <th>Barbero</th>
                            <th>Servicios</th>
                            <th class="text-end">Total</th>
                            <th>Estado</th>
                            <th>Origen</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($turnos as $turno)
                            <tr>
                                <td>{{ $turno->fecha->format('d/m/Y') }}</td>
                                <td class="text-nowrap">{{ $turno->horaInicioLegible() }}</td>
                                <td>{{ $turno->cliente->nombre }}</td>
                                <td>{{ $turno->barbero->user->name }}</td>
                                <td>{{ $turno->serviciosTexto() }}</td>
                                <td class="text-end text-nowrap">${{ number_format($turno->precio_total, 0, ',', '.') }}</td>
                                <td><x-estado-turno :estado="$turno->estado" /></td>
                                <td class="text-nowrap">@include('turnos._origen', ['origen' => $turno->origen])</td>
                                <td class="text-end">@include('turnos._acciones', ['turno' => $turno])</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Celular: tarjetas apiladas --}}
        <div class="d-lg-none d-flex flex-column gap-2">
            @foreach ($turnos as $turno)
                <div class="cy-tarjeta p-3">
                    <div class="d-flex justify-content-between align-items-start gap-2">
                        <div>
                            <div class="fw-semibold">{{ $turno->fecha->format('d/m/Y') }} · {{ $turno->horaInicioLegible() }}</div>
                            <div>{{ $turno->cliente->nombre }}</div>
                        </div>
                        <x-estado-turno :estado="$turno->estado" />
                    </div>
                    <div class="text-secondary small mt-1">
                        {{ $turno->serviciosTexto() }} · {{ $turno->duracionMin() }} min · ${{ number_format($turno->precio_total, 0, ',', '.') }}
                    </div>
                    <div class="d-flex justify-content-between align-items-center mt-2">
                        <span class="small">@include('turnos._origen', ['origen' => $turno->origen]) · {{ $turno->barbero->user->name }}</span>
                        @include('turnos._acciones', ['turno' => $turno])
                    </div>
                </div>
            @endforeach
        </div>

        <div class="d-flex flex-wrap justify-content-between align-items-center mt-3">
            {{ $turnos->links() }}
        </div>
    @endif

    @include('turnos._modal_cancelar')
@endsection

@push('scripts')
    <script src="{{ asset('js/turnos-cancelar.js') }}"></script>
@endpush
