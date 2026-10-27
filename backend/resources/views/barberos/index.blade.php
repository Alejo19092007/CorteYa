@extends('layouts.app')

@section('titulo', 'Barberos · CorteYa')

@php
    $hayFiltros = collect($filtros)->filter(fn ($v) => $v !== null && $v !== '')->isNotEmpty();
@endphp

@section('contenido')
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-4">
        <div>
            <h1 class="h2 mb-1"><i class="bi bi-person"></i> Barberos</h1>
            <p class="text-secondary mb-0">El equipo de la barbería: sus cuentas de acceso y sus horarios de trabajo.</p>
        </div>
        <a href="{{ route('barberos.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Nuevo barbero</a>
    </div>

    <form method="GET" action="{{ route('barberos.index') }}" class="cy-tarjeta p-3 mb-3">
        <div class="row g-2 align-items-end">
            <div class="col-12 col-md-6">
                <label for="q" class="form-label small mb-1">Buscar</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="search" id="q" name="q" class="form-control" placeholder="Nombre, correo o teléfono" value="{{ $filtros['q'] ?? '' }}">
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
                    <a href="{{ route('barberos.index') }}" class="btn btn-outline-secondary" title="Limpiar filtros" aria-label="Limpiar filtros"><i class="bi bi-x-lg"></i></a>
                @endif
            </div>
        </div>
    </form>

    @if ($barberos->isEmpty())
        <div class="cy-tarjeta p-5 text-center text-secondary">
            <i class="bi bi-person-x fs-1"></i>
            <p class="mb-0 mt-2">No hay barberos {{ $hayFiltros ? 'con esos filtros' : 'registrados' }}.</p>
        </div>
    @else
        {{-- Escritorio: tabla --}}
        <div class="cy-tarjeta d-none d-md-block">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Barbero</th>
                            <th>Teléfono</th>
                            <th class="text-center">Turnos</th>
                            <th>Estado</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($barberos as $barbero)
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $barbero->user->name }}</div>
                                    <div class="small text-secondary">{{ $barbero->user->email }}</div>
                                </td>
                                <td>{{ $barbero->telefono }}</td>
                                <td class="text-center">{{ $barbero->turnos_count }}</td>
                                <td>@include('barberos._estado', ['barbero' => $barbero])</td>
                                <td class="text-end">@include('barberos._acciones', ['barbero' => $barbero])</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Celular: tarjetas --}}
        <div class="d-md-none d-flex flex-column gap-2">
            @foreach ($barberos as $barbero)
                <div class="cy-tarjeta p-3">
                    <div class="d-flex justify-content-between align-items-start gap-2">
                        <div>
                            <div class="fw-semibold">{{ $barbero->user->name }}</div>
                            <div class="small text-secondary">{{ $barbero->user->email }}</div>
                        </div>
                        @include('barberos._estado', ['barbero' => $barbero])
                    </div>
                    <div class="text-secondary small mt-1">{{ $barbero->telefono }} · {{ $barbero->turnos_count }} turnos</div>
                    <div class="text-end mt-2">@include('barberos._acciones', ['barbero' => $barbero])</div>
                </div>
            @endforeach
        </div>

        <div class="d-flex flex-wrap justify-content-between align-items-center mt-3">
            {{ $barberos->links() }}
        </div>
    @endif

    @include('layouts.partials.modal_eliminar', ['titulo' => 'Eliminar barbero'])
@endsection

@push('scripts')
    <script src="{{ asset('js/modal-eliminar.js') }}"></script>
@endpush
