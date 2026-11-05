{{-- R6 · Un solo modal para todas las filas: el botón le pasa la URL, el resumen y el total (turnos-pago.js) --}}
<div class="modal fade" id="modalPago" tabindex="-1" aria-labelledby="modalPagoTitulo" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" action="" class="modal-content">
            @csrf
            @method('PATCH')
            <div class="modal-header">
                <h2 class="modal-title h5" id="modalPagoTitulo"><i class="bi bi-cash-coin"></i> Registrar pago</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <p class="fw-semibold mb-1" id="pagoResumen"></p>
                <p class="mb-3">Total a cobrar: <span class="fs-4 fw-bold" id="pagoTotal"></span></p>

                <fieldset>
                    <legend class="form-label small mb-2">¿Cómo pagó el cliente?</legend>
                    <div class="d-flex flex-wrap gap-2">
                        @foreach (\App\Models\Turno::METODOS_PAGO as $valor => $nombre)
                            <input type="radio" class="btn-check" name="metodo_pago" id="pago-{{ $valor }}" value="{{ $valor }}" required autocomplete="off">
                            <label class="btn btn-outline-primary" for="pago-{{ $valor }}">{{ $nombre }}</label>
                        @endforeach
                    </div>
                </fieldset>
                <p class="text-secondary small mt-3 mb-0">Solo se anota que ya pagó: el sistema no cobra. El monto es el total del turno.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Volver</button>
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Registrar pago</button>
            </div>
        </form>
    </div>
</div>
