{{--
    Formulario de turno (crear y editar). Orden de los campos: Cliente → Barbero → Servicios → Fecha → Hora.
    La hora va al final porque las horas libres dependen del barbero, la fecha y la DURACIÓN de los servicios.
    La hora de fin, la duración y el precio total NO se escriben: el resumen es solo una vista previa
    y el valor definitivo lo calcula TurnoService al guardar (R2).
--}}
@php
    $t = $turno ?? null;
    $clienteId = old('cliente_id', $t?->cliente_id);
    $clienteTexto = old('cliente_texto', $t ? $t->cliente->nombre.' · '.$t->cliente->telefono : '');
    $barberoId = old('barbero_id', $t?->barbero_id);
    $fecha = old('fecha', $t?->fecha->format('Y-m-d'));
    $horaActual = old('hora_inicio', $t ? substr($t->hora_inicio, 0, 5) : '');
    $seleccionados = collect(old('servicios', $t ? $t->servicios->pluck('id')->all() : []))->map(fn ($id) => (int) $id)->all();
@endphp

<form method="POST" action="{{ $accion }}" id="formTurno" novalidate
      data-url-disponibilidad="{{ route('turnos.disponibilidad') }}"
      data-url-clientes="{{ route('clientes.buscar') }}"
      data-ignorar="{{ $t?->id }}"
      data-hora-actual="{{ $horaActual }}">
    @csrf
    @if ($t)
        @method('PUT')
    @endif

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
        <div class="col-12 col-lg-8">
            <div class="cy-tarjeta p-3 p-lg-4">
                <div class="row g-3">
                    {{-- 1. Cliente: se busca por nombre o teléfono --}}
                    <div class="col-12 position-relative">
                        <label for="clienteBuscar" class="form-label fw-semibold">1. Cliente</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-person"></i></span>
                            <input type="text" id="clienteBuscar" name="cliente_texto" class="form-control"
                                   placeholder="Escribe el nombre o el teléfono" autocomplete="off"
                                   value="{{ $clienteTexto }}">
                        </div>
                        <input type="hidden" id="cliente_id" name="cliente_id" value="{{ $clienteId }}">
                        <div id="clienteResultados" class="list-group position-absolute w-100 shadow-sm" style="z-index: 5;"></div>
                    </div>

                    {{-- 2. Barbero --}}
                    <div class="col-12">
                        <label for="barbero_id" class="form-label fw-semibold">2. Barbero</label>
                        <select id="barbero_id" name="barbero_id" class="form-select">
                            <option value="">Elige un barbero</option>
                            @foreach ($barberos as $barbero)
                                <option value="{{ $barbero->id }}" @selected((int) $barberoId === $barbero->id)>
                                    {{ $barbero->user->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- 3. Servicios: uno o varios --}}
                    <div class="col-12">
                        <span class="form-label fw-semibold d-block">3. Servicios</span>
                        <div class="form-text mb-2">Selecciona uno o varios servicios.</div>
                        <div class="list-group">
                            @foreach ($servicios as $servicio)
                                <label class="list-group-item d-flex align-items-center gap-2">
                                    <input type="checkbox" name="servicios[]" value="{{ $servicio->id }}"
                                           class="form-check-input mt-0 js-servicio"
                                           data-precio="{{ (int) $servicio->precio }}"
                                           data-duracion="{{ $servicio->duracion_min }}"
                                           @checked(in_array($servicio->id, $seleccionados, true))>
                                    <span class="flex-fill">{{ $servicio->nombre }}</span>
                                    <span class="text-secondary small text-nowrap">
                                        ${{ number_format($servicio->precio, 0, ',', '.') }} · {{ $servicio->duracion_min }} min
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    {{-- 4. Fecha --}}
                    <div class="col-12 col-sm-6">
                        <label for="fecha" class="form-label fw-semibold">4. Fecha</label>
                        <input type="date" id="fecha" name="fecha" class="form-control"
                               min="{{ now()->toDateString() }}" value="{{ $fecha }}">
                    </div>

                    {{-- 5. Hora de inicio: se llena con las horas libres --}}
                    <div class="col-12 col-sm-6">
                        <label for="hora_inicio" class="form-label fw-semibold">5. Hora de inicio</label>
                        <select id="hora_inicio" name="hora_inicio" class="form-select" disabled>
                            <option value="">Elige barbero, servicios y fecha</option>
                        </select>
                        <div id="horaAyuda" class="form-text"></div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Resumen en vivo (R2) --}}
        <div class="col-12 col-lg-4">
            <div class="cy-tarjeta p-3 p-lg-4">
                <h2 class="h5 mb-3">Resumen del turno</h2>
                <dl class="mb-0">
                    <dt class="small text-secondary fw-normal">Hora de inicio</dt>
                    <dd class="fw-semibold" id="resInicio">—</dd>
                    <dt class="small text-secondary fw-normal">Hora de fin (estimada)</dt>
                    <dd class="fw-semibold" id="resFin">—</dd>
                    <dt class="small text-secondary fw-normal">Duración total</dt>
                    <dd class="fw-semibold" id="resDuracion">—</dd>
                    <dt class="small text-secondary fw-normal">Precio total</dt>
                    <dd class="fs-3 fw-bold mb-2" id="resPrecio">$0</dd>
                </dl>
                <p class="small text-secondary mb-0">Estos valores se calculan solos; no se digitan. Al guardar, el sistema los vuelve a calcular.</p>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-between mt-3">
        <a href="{{ route('turnos.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Volver</a>
        <button type="submit" class="btn btn-primary px-4"><i class="bi bi-calendar-check"></i> Guardar turno</button>
    </div>
</form>

@push('scripts')
    <script src="{{ asset('js/turno-form.js') }}"></script>
@endpush
