// Modal de "Eliminar": al abrirse, toma la URL y el resumen del botón que lo abrió.
document.addEventListener('DOMContentLoaded', function () {
    var modal = document.getElementById('modalEliminar');

    if (!modal) {
        return;
    }

    modal.addEventListener('show.bs.modal', function (evento) {
        var boton = evento.relatedTarget;

        modal.querySelector('form').action = boton.dataset.accion;
        modal.querySelector('#eliminarResumen').textContent = boton.dataset.resumen;
    });
});
