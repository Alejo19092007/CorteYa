// Modal de "Registrar pago": al abrirse, toma la URL, el resumen y el total del botón que lo abrió
// y deja sin elegir el método de pago (el cliente debe indicarlo de forma explícita).
document.addEventListener('DOMContentLoaded', function () {
    var modal = document.getElementById('modalPago');

    if (!modal) {
        return;
    }

    modal.addEventListener('show.bs.modal', function (evento) {
        var boton = evento.relatedTarget;

        modal.querySelector('form').action = boton.dataset.accion;
        modal.querySelector('#pagoResumen').textContent = boton.dataset.resumen;
        modal.querySelector('#pagoTotal').textContent = boton.dataset.total;
        modal.querySelectorAll('input[name="metodo_pago"]').forEach(function (opcion) {
            opcion.checked = false;
        });
    });
});
