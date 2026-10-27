// Formulario de barbero: las horas de un día solo se habilitan si ese día está marcado como "trabaja".
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.js-dia').forEach(function (fila) {
        var interruptor = fila.querySelector('.js-trabaja');
        var horas = fila.querySelectorAll('.js-hora');

        function actualizar() {
            horas.forEach(function (campo) { campo.disabled = !interruptor.checked; });
        }

        interruptor.addEventListener('change', actualizar);
        actualizar();
    });
});
