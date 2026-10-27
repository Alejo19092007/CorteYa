@php
    $s = $servicio ?? null;
@endphp

<form method="POST" action="{{ $accion }}" novalidate>
    @csrf
    @if ($s)
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

    <div class="cy-tarjeta p-3 p-lg-4" style="max-width: 640px;">
        <div class="mb-3">
            <label for="nombre" class="form-label fw-semibold">Nombre</label>
            <input type="text" id="nombre" name="nombre" class="form-control @error('nombre') is-invalid @enderror"
                   value="{{ old('nombre', $s?->nombre) }}" maxlength="80" required autofocus>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-6">
                <label for="duracion_min" class="form-label fw-semibold">Duración (minutos)</label>
                <input type="number" id="duracion_min" name="duracion_min" class="form-control @error('duracion_min') is-invalid @enderror"
                       value="{{ old('duracion_min', $s?->duracion_min) }}" min="1" max="480" step="5" required>
            </div>
            <div class="col-6">
                <label for="precio" class="form-label fw-semibold">Precio (COP)</label>
                <div class="input-group">
                    <span class="input-group-text">$</span>
                    <input type="number" id="precio" name="precio" class="form-control @error('precio') is-invalid @enderror"
                           value="{{ old('precio', $s ? (int) $s->precio : '') }}" min="0" step="500" required>
                </div>
            </div>
        </div>

        <div class="form-check form-switch mb-3">
            {{-- El campo oculto envía 0 cuando el interruptor está apagado --}}
            <input type="hidden" name="activo" value="0">
            <input type="checkbox" role="switch" id="activo" name="activo" value="1" class="form-check-input"
                   @checked(old('activo', $s?->activo ?? true))>
            <label for="activo" class="form-check-label">Activo (se ofrece en el panel y en el bot)</label>
        </div>

        @if ($s)
            <p class="small text-secondary mb-0">
                <i class="bi bi-info-circle"></i> Cambiar el precio o la duración no modifica los turnos ya reservados:
                cada turno conserva el valor que tenía al reservar.
            </p>
        @endif
    </div>

    <div class="d-flex justify-content-between mt-3" style="max-width: 640px;">
        <a href="{{ route('servicios.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Volver</a>
        <button type="submit" class="btn btn-primary px-4"><i class="bi bi-check-lg"></i> Guardar servicio</button>
    </div>
</form>
