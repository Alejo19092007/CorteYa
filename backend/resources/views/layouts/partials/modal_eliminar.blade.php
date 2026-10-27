{{--
    Modal de confirmación para ELIMINAR de verdad (servicios y barberos sin historial).
    Un solo modal para todas las filas: el botón le pasa la URL y el resumen (modal-eliminar.js).
    Para turnos se usa otro, porque allí "cancelar" no borra.
--}}
<div class="modal fade" id="modalEliminar" tabindex="-1" aria-labelledby="modalEliminarTitulo" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" action="" class="modal-content">
            @csrf
            @method('DELETE')
            <div class="modal-header">
                <h2 class="modal-title h5" id="modalEliminarTitulo">{{ $titulo ?? 'Eliminar' }}</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <p class="mb-1">¿Seguro que quieres eliminarlo? Esta acción no se puede deshacer.</p>
                <p class="fw-semibold mb-0" id="eliminarResumen"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Volver</button>
                <button type="submit" class="btn btn-danger"><i class="bi bi-trash"></i> Sí, eliminar</button>
            </div>
        </form>
    </div>
</div>
