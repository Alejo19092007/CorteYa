@php
    $b = $barbero ?? null;
    $dias = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'];
@endphp

<form method="POST" action="{{ $accion }}" id="formBarbero" novalidate>
    @csrf
    @if ($b)
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
        {{-- Cuenta y perfil --}}
        <div class="col-12 col-lg-6">
            <div class="cy-tarjeta p-3 p-lg-4 h-100">
                <h2 class="h5 mb-3">Datos del barbero</h2>

                <div class="mb-3">
                    <label for="name" class="form-label fw-semibold">Nombre completo</label>
                    <input type="text" id="name" name="name" class="form-control @error('name') is-invalid @enderror"
                           value="{{ old('name', $b?->user->name) }}" maxlength="100" required autofocus>
                </div>

                <div class="mb-3">
                    <label for="email" class="form-label fw-semibold">Correo (para iniciar sesión)</label>
                    <input type="email" id="email" name="email" class="form-control @error('email') is-invalid @enderror"
                           value="{{ old('email', $b?->user->email) }}" maxlength="150" autocomplete="off" required>
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label fw-semibold">Contraseña</label>
                    <input type="password" id="password" name="password" class="form-control @error('password') is-invalid @enderror"
                           autocomplete="new-password" minlength="8" @required(! $b)>
                    <div class="form-text">
                        {{ $b ? 'Déjala vacía para no cambiarla.' : 'Mínimo 8 caracteres.' }}
                    </div>
                </div>

                <div class="mb-3">
                    <label for="telefono" class="form-label fw-semibold">Teléfono (celular)</label>
                    <input type="text" id="telefono" name="telefono" class="form-control @error('telefono') is-invalid @enderror"
                           value="{{ old('telefono', $b?->telefono) }}" inputmode="numeric" placeholder="3001234567" required>
                    <div class="form-text">10 dígitos; se guarda con el indicativo 57.</div>
                </div>

                <div class="form-check form-switch mb-0">
                    {{-- El campo oculto envía 0 cuando el interruptor está apagado --}}
                    <input type="hidden" name="activo" value="0">
                    <input type="checkbox" role="switch" id="activo" name="activo" value="1" class="form-check-input"
                           @checked(old('activo', $b?->activo ?? true))>
                    <label for="activo" class="form-check-label">Activo (recibe turnos nuevos)</label>
                </div>
            </div>
        </div>

        {{-- Horarios: una fila por día --}}
        <div class="col-12 col-lg-6">
            <div class="cy-tarjeta p-3 p-lg-4 h-100">
                <h2 class="h5 mb-1">Horario de trabajo</h2>
                <p class="text-secondary small mb-3">Marca los días que trabaja. Los turnos solo se pueden reservar dentro de este horario (R1).</p>

                @foreach ($dias as $numero => $nombre)
                    @php
                        $actual = $jornadas[$numero] ?? ['trabaja' => false, 'inicio' => '09:00', 'fin' => '18:00'];
                        // Tras un error de validación, un día desmarcado NO viaja en la petición:
                        // si hay datos viejos, "ausente" significa "no trabaja" (y no el valor por defecto).
                        $trabaja = session()->hasOldInput()
                            ? (bool) old("horarios.{$numero}.trabaja", false)
                            : (bool) $actual['trabaja'];
                    @endphp
                    <div class="row g-2 align-items-center mb-2 js-dia">
                        <div class="col-12 col-sm-4">
                            <div class="form-check form-switch">
                                <input type="checkbox" role="switch" class="form-check-input js-trabaja"
                                       id="trabaja{{ $numero }}" name="horarios[{{ $numero }}][trabaja]" value="1" @checked($trabaja)>
                                <label for="trabaja{{ $numero }}" class="form-check-label">{{ $nombre }}</label>
                            </div>
                        </div>
                        <div class="col-6 col-sm-4">
                            <label for="inicio{{ $numero }}" class="visually-hidden">Hora de inicio del {{ $nombre }}</label>
                            <input type="time" id="inicio{{ $numero }}" name="horarios[{{ $numero }}][inicio]" class="form-control js-hora"
                                   value="{{ old("horarios.{$numero}.inicio", $actual['inicio']) }}">
                        </div>
                        <div class="col-6 col-sm-4">
                            <label for="fin{{ $numero }}" class="visually-hidden">Hora de fin del {{ $nombre }}</label>
                            <input type="time" id="fin{{ $numero }}" name="horarios[{{ $numero }}][fin]" class="form-control js-hora"
                                   value="{{ old("horarios.{$numero}.fin", $actual['fin']) }}">
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-between mt-3">
        <a href="{{ route('barberos.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Volver</a>
        <button type="submit" class="btn btn-primary px-4"><i class="bi bi-check-lg"></i> Guardar barbero</button>
    </div>
</form>

@push('scripts')
    <script src="{{ asset('js/barbero-form.js') }}"></script>
@endpush
