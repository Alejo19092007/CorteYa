@extends('layouts.app')

@section('titulo', 'Servicios · CorteYa')

@php
    $hayFiltros = collect($filtros)->filter(fn ($v) => $v !== null && $v !== '')->isNotEmpty();
@endphp

@section('contenido')
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-4">
        <div>
            <h1 class="h2 mb-1"><i class="bi bi-scissors"></i> Servicios</h1>
            <p class="text-secondary mb-0">Los servicios y precios que ofrece la barbería (también los ve el bot de WhatsApp).</p>
        </div>
        <a href="{{ route('servicios.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Nuevo servicio</a>
    </div>

    <form method="GET" action="{{ route('servicios.index') }}" class="cy-tarjeta p-3 mb-3">
        <div class="row g-2 align-items-end">
            <div class="col-12 col-md-6">
                <label for="q" class="form-label small mb-1">Buscar</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="search" id="q" name="q" class="form-control" placeholder="Nombre del servicio" value="{{ $filtros['q'] ?? '' }}">
                </div>
            </div>
            <div class="col-8 col-md-3">
                <label for="activo" class="form-label small mb-1">Estado</label>
                <select id="activo" name="activo" class="form-select">
                    <option value="">Todos</option>
                    <option value="1" @selected(($filtros['activo'] ?? '') === '1')>Activos</option>
                    <option value="0" @selected(($filtros['activo'] ?? '') === '0')>Inactivos</option>
                </select>
            </div>
            <div class="col-4 col-md-3 d-flex gap-1">
                <button type="submit" class="btn btn-primary flex-fill"><i class="bi bi-funnel"></i> Filtrar</button>
                @if ($hayFiltros)
                    <a href="{{ route('servicios.index') }}" class="btn btn-outline-secondary" title="Limpiar filtros" aria-label="Limpiar filtros"><i class="bi bi-x-lg"></i></a>
                @endif
            </div>
        </div>
    </form>

    @if ($servicios->isEmpty())
        <div class="cy-tarjeta p-5 text-center text-secondary">
            <i class="bi bi-scissors fs-1"></i>
            <p class="mb-0 mt-2">No hay servicios {{ $hayFiltros ? 'con esos filtros' : 'registrados' }}.</p>
        </div>
    @else
        {{-- Escritorio: tabla --}}
        <div class="cy-tarjeta d-none d-md-block">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Servicio</th>
                            <th>Duración</th>
                            <th class="text-end">Precio</th>
                            <th class="text-center">Turnos</th>
                            <th>Estado</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($servicios as $servicio)
                            <tr>
                                <td class="fw-semibold">{{ $servicio->nombre }}</td>
                                <td>{{ $servicio->duracion_min }} min</td>
                                <td class="text-end">${{ number_format($servicio->precio, 0, ',', '.') }}</td>
                                <td class="text-center">{{ $servicio->turnos_count }}</td>
                                <td>@include('servicios._estado', ['servicio' => $servicio])</td>
                                <td class="text-end">@include('servicios._acciones', ['servicio' => $servicio])</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Celular: tarjetas --}}
        <div class="d-md-none d-flex flex-column gap-2">
            @foreach ($servicios as $servicio)
                <div class="cy-tarjeta p-3">
                    <div class="d-flex justify-content-between align-items-start gap-2">
                        <div class="fw-semibold">{{ $servicio->nombre }}</div>
                        @include('servicios._estado', ['servicio' => $servicio])
                    </div>
                    <div class="text-secondary small mt-1">
                        {{ $servicio->duracion_min }} min · ${{ number_format($servicio->precio, 0, ',', '.') }} · {{ $servicio->turnos_count }} turnos
                    </div>
                    <div class="text-end mt-2">@include('servicios._acciones', ['servicio' => $servicio])</div>
                </div>
            @endforeach
        </div>

        <div class="d-flex flex-wrap justify-content-between align-items-center mt-3">
            {{ $servicios->links() }}
        </div>
    @endif

    @include('layouts.partials.modal_eliminar', ['titulo' => 'Eliminar servicio'])
@endsection

@push('scripts')
    <script src="{{ asset('js/modal-eliminar.js') }}"></script>
@endpush
