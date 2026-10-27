{{-- Un solo modal para todas las filas: el botón le pasa la URL y el resumen del turno (turnos-cancelar.js) --}}
<div class="modal fade" id="modalCancelar" tabindex="-1" aria-labelledby="modalCancelarTitulo" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" action="" class="modal-content">
            @csrf
            @method('DELETE')
            <div class="modal-header">
                <h2 class="modal-title h5" id="modalCancelarTitulo">Cancelar turno</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <p class="mb-1">¿Seguro que quieres cancelar este turno?</p>
                <p class="fw-semibold mb-2" id="cancelarResumen"></p>
                <p class="text-secondary small mb-0">El turno no se borra: queda en el historial como cancelado y el horario vuelve a quedar libre.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Volver</button>
                <button type="submit" class="btn btn-danger"><i class="bi bi-x-circle"></i> Sí, cancelar turno</button>
            </div>
        </form>
    </div>
</div>
