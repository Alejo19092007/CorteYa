// Botón del "ojo": muestra u oculta la contraseña en el login.
document.addEventListener('DOMContentLoaded', function () {
    var boton = document.getElementById('verClave');
    var campo = document.getElementById('password');

    if (!boton || !campo) {
        return;
    }

    boton.addEventListener('click', function () {
        var oculta = campo.type === 'password';
        campo.type = oculta ? 'text' : 'password';
        boton.setAttribute('aria-label', oculta ? 'Ocultar contraseña' : 'Mostrar contraseña');
        boton.querySelector('i').className = oculta ? 'bi bi-eye-slash' : 'bi bi-eye';
    });
});
