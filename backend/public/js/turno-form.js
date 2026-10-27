// Formulario de turno: buscador de clientes, horas libres y resumen en vivo.
// El resumen es solo una VISTA PREVIA: el servidor recalcula hora de fin y precio al guardar (R2).
document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('formTurno');

    if (!form) {
        return;
    }

    var barbero = form.querySelector('#barbero_id');
    var fecha = form.querySelector('#fecha');
    var hora = form.querySelector('#hora_inicio');
    var ayuda = form.querySelector('#horaAyuda');
    var servicios = Array.prototype.slice.call(form.querySelectorAll('.js-servicio'));
    var horaActual = form.dataset.horaActual || '';

    function dosDigitos(n) {
        return (n < 10 ? '0' : '') + n;
    }

    function dinero(n) {
        return '$' + Math.round(n).toLocaleString('es-CO');
    }

    function elegidos() {
        return servicios.filter(function (s) { return s.checked; });
    }

    // ---- Resumen en vivo ---------------------------------------------------
    function actualizarResumen() {
        var sel = elegidos();
        var duracion = sel.reduce(function (t, s) { return t + Number(s.dataset.duracion); }, 0);
        var precio = sel.reduce(function (t, s) { return t + Number(s.dataset.precio); }, 0);

        form.querySelector('#resDuracion').textContent = duracion ? duracion + ' min' : '—';
        form.querySelector('#resPrecio').textContent = dinero(precio);
        form.querySelector('#resInicio').textContent = hora.value || '—';

        var fin = '—';
        if (hora.value && duracion) {
            var partes = hora.value.split(':');
            var total = Number(partes[0]) * 60 + Number(partes[1]) + duracion;
            fin = dosDigitos(Math.floor(total / 60) % 24) + ':' + dosDigitos(total % 60);
        }
        form.querySelector('#resFin').textContent = fin;
    }

    // ---- Horas libres ------------------------------------------------------
    function vaciarHoras(mensaje) {
        hora.innerHTML = '';
        hora.appendChild(new Option(mensaje, ''));
        hora.disabled = true;
        ayuda.textContent = '';
        actualizarResumen();
    }

    function cargarHoras() {
        if (!barbero.value || !fecha.value || elegidos().length === 0) {
            vaciarHoras('Elige barbero, servicios y fecha');
            return;
        }

        var params = new URLSearchParams();
        params.set('barbero_id', barbero.value);
        params.set('fecha', fecha.value);
        elegidos().forEach(function (s) { params.append('servicios[]', s.value); });
        if (form.dataset.ignorar) {
            params.set('ignorar', form.dataset.ignorar);
        }

        vaciarHoras('Buscando horarios...');

        fetch(form.dataset.urlDisponibilidad + '?' + params.toString(), {
            headers: { 'Accept': 'application/json' },
            credentials: 'same-origin'
        })
            .then(function (respuesta) {
                if (!respuesta.ok) { throw new Error('HTTP ' + respuesta.status); }
                return respuesta.json();
            })
            .then(function (datos) {
                hora.innerHTML = '';

                if (datos.horas.length === 0) {
                    hora.appendChild(new Option('Sin horarios disponibles', ''));
                    ayuda.textContent = 'Prueba con otra fecha u otro barbero.';
                    return;
                }

                hora.appendChild(new Option('Elige una hora', ''));
                datos.horas.forEach(function (h) {
                    hora.appendChild(new Option(h, h, false, h === horaActual));
                });
                hora.disabled = false;
                ayuda.textContent = 'Duración: ' + datos.duracion + ' min. Solo se muestran horas libres.';
                actualizarResumen();
            })
            .catch(function () {
                vaciarHoras('No se pudieron cargar los horarios');
                ayuda.textContent = 'Revisa tu conexión e intenta de nuevo.';
            });
    }

    [barbero, fecha].forEach(function (campo) {
        campo.addEventListener('change', function () { horaActual = ''; cargarHoras(); });
    });
    servicios.forEach(function (s) {
        s.addEventListener('change', function () { horaActual = hora.value || horaActual; cargarHoras(); actualizarResumen(); });
    });
    hora.addEventListener('change', actualizarResumen);

    // ---- Buscador de clientes ---------------------------------------------
    var campoCliente = form.querySelector('#clienteBuscar');
    var idCliente = form.querySelector('#cliente_id');
    var lista = form.querySelector('#clienteResultados');
    var espera = null;

    function mostrarClientes(clientes) {
        lista.innerHTML = '';

        if (clientes.length === 0) {
            var vacio = document.createElement('div');
            vacio.className = 'list-group-item text-secondary';
            vacio.textContent = 'Sin resultados';
            lista.appendChild(vacio);
            return;
        }

        clientes.forEach(function (c) {
            var boton = document.createElement('button');
            boton.type = 'button';
            boton.className = 'list-group-item list-group-item-action';
            boton.textContent = c.nombre + ' · ' + c.telefono; // textContent: nunca se interpreta como HTML
            boton.addEventListener('click', function () {
                idCliente.value = c.id;
                campoCliente.value = c.nombre + ' · ' + c.telefono;
                lista.innerHTML = '';
            });
            lista.appendChild(boton);
        });
    }

    campoCliente.addEventListener('input', function () {
        idCliente.value = ''; // si cambia el texto, hay que volver a elegir un cliente de la lista
        clearTimeout(espera);

        var texto = campoCliente.value.trim();
        if (texto.length < 2) {
            lista.innerHTML = '';
            return;
        }

        espera = setTimeout(function () {
            fetch(form.dataset.urlClientes + '?q=' + encodeURIComponent(texto), {
                headers: { 'Accept': 'application/json' },
                credentials: 'same-origin'
            })
                .then(function (r) { return r.json(); })
                .then(mostrarClientes)
                .catch(function () { lista.innerHTML = ''; });
        }, 250);
    });

    document.addEventListener('click', function (evento) {
        if (!lista.contains(evento.target) && evento.target !== campoCliente) {
            lista.innerHTML = '';
        }
    });

    // Al abrir (editar, o volver tras un error de validación) se cargan las horas con lo que ya hay
    cargarHoras();
});
