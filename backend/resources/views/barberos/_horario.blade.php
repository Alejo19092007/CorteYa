@php
    $dias = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'];
@endphp

{{-- Horario semanal: una fila por día. Lo usan el formulario del dueño y el perfil del barbero (necesita $jornadas). --}}
<div class="{{ $columna ?? 'col-12 col-lg-6' }}">
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

@push('scripts')
    <script src="@recurso('js/barbero-form.js')"></script>
@endpush
