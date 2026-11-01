// Lógica de la conversación del bot. No sabe nada de WhatsApp ni de HTTP:
//   entra  { telefono, nombre, texto }   (lo que escribió el cliente)
//   sale   ['mensaje 1', 'mensaje 2']    (lo que el bot le responde)
//
// Por eso el mismo código lo usan el webhook de WhatsApp y el simulador local, y se prueba sin red.
// Cada cliente (por teléfono) va por pasos; el paso actual vive en `sesiones`:
//
//   menú → reservar_servicios → reservar_barbero → reservar_fecha → reservar_hora → reservar_confirmar
//        → (reservar_nombre, solo si es la primera vez y WhatsApp no trae su nombre)
//   menú → cancelar_elegir → cancelar_confirmar

import { ApiAuthError, ApiNoDisponible, ApiNoEncontrado, ApiRechazo } from '../api/corteyaClient.js';
import {
  esNo, esSi, fechaLarga, hora12, horaEnBogota, hoyEnBogota, interpretarFecha,
  interpretarNumeros, normalizarComando, pesos,
} from './formato.js';

const HORAS_POR_PAGINA = 10;
const COMANDOS_MENU = ['menu', 'inicio', 'hola', 'buenas', 'buenos dias', 'buenas tardes', 'buenas noches', 'salir', 'volver', '0'];

const ESTADOS_VISIBLES = {
  pendiente: 'pendiente de confirmación',
  confirmado: 'confirmado',
};

export function crearBot({ api, sesiones, origen = 'telegram', ahora = () => new Date(), registrar = console }) {
  const hoy = () => hoyEnBogota(ahora());

  // ---------------------------------------------------------------- textos

  const menu = (nombre) => [
    `¡Hola${nombre ? `, ${nombre.split(' ')[0]}` : ''}! Soy el asistente de *CorteYa* 💈`,
    '',
    '¿Qué necesitas?',
    '1. Ver servicios y precios',
    '2. Reservar un turno',
    '3. Ver mis turnos',
    '4. Cancelar un turno',
    '',
    'Responde con el número de la opción. Escribe *menu* en cualquier momento para volver aquí.',
  ].join('\n');

  const listaServicios = (servicios) =>
    servicios.map((s, i) => `${i + 1}. ${s.nombre} — ${pesos(s.precio)} (${s.duracion_min} min)`).join('\n');

  const textoTurno = (t) => {
    const servicios = t.servicios.map((s) => s.nombre).join(' + ');
    return `${fechaLarga(t.fecha)}, ${hora12(t.hora_inicio)} · ${servicios} · con ${t.barbero.nombre}`;
  };

  /** Turnos que todavía no han pasado y siguen vigentes (pendientes o confirmados). */
  const turnosActivos = (turnos) => {
    const fechaHoy = hoy();
    const horaAhora = horaEnBogota(ahora());
    return turnos
      .filter((t) => t.estado in ESTADOS_VISIBLES)
      .filter((t) => t.fecha > fechaHoy || (t.fecha === fechaHoy && t.hora_fin >= horaAhora))
      .sort((a, b) => `${a.fecha} ${a.hora_inicio}`.localeCompare(`${b.fecha} ${b.hora_inicio}`));
  };

  const paginaDeHoras = (datos) => {
    const inicio = datos.pagina * HORAS_POR_PAGINA;
    const lineas = datos.horas
      .slice(inicio, inicio + HORAS_POR_PAGINA)
      .map((h, i) => `${inicio + i + 1}. ${hora12(h)}`);
    const hayMas = inicio + HORAS_POR_PAGINA < datos.horas.length;

    return [
      `Horarios libres con *${datos.barbero.nombre}* el ${fechaLarga(datos.fecha)}:`,
      ...lineas,
      '',
      hayMas ? 'Escribe *más* para ver otros horarios.' : null,
      'Elige el número del horario que prefieras.',
    ].filter((l) => l !== null).join('\n');
  };

  const resumen = (datos) => {
    const minutos = datos.servicios.reduce((suma, s) => suma + s.duracion_min, 0);
    const total = datos.servicios.reduce((suma, s) => suma + Number(s.precio), 0);
    return [
      'Revisa tu turno:',
      `• Servicios: ${datos.servicios.map((s) => s.nombre).join(' + ')}`,
      `• Barbero: ${datos.barbero.nombre}`,
      `• Día: ${fechaLarga(datos.fecha)}`,
      `• Hora: ${hora12(datos.hora)}`,
      `• Duración: ${minutos} min · Total: ${pesos(total)}`,
      '',
      'Responde *sí* para confirmar o *no* para descartarlo.',
    ].join('\n');
  };

  // ---------------------------------------------------------------- entrada

  async function procesar({ telefono, nombre, texto }) {
    try {
      return await despachar({ telefono, nombre, texto });
    } catch (error) {
      return respuestaDeError(error, telefono);
    }
  }

  /** Convierte los fallos de la API en un mensaje amable. La sesión NO se borra: el cliente puede reintentar. */
  function respuestaDeError(error, telefono) {
    if (error instanceof ApiNoDisponible) {
      registrar.warn?.(`[bot] API no disponible: ${error.message}`);
      return ['😕 No pude comunicarme con el sistema de turnos. Puede estar despertando: intenta de nuevo en un minuto.'];
    }
    if (error instanceof ApiRechazo) {
      return [`⚠️ ${error.message}`];
    }
    if (error instanceof ApiAuthError) {
      registrar.error?.(`[bot] Problema de credenciales con la API: ${error.message}`);
      sesiones.borrar(telefono);
      return ['⚙️ El asistente tiene un problema técnico en este momento. Por favor comunícate directamente con la barbería.'];
    }
    registrar.error?.('[bot] Error inesperado', error);
    sesiones.borrar(telefono);
    return ['Ocurrió un error inesperado. Escribe *menu* para empezar de nuevo.'];
  }

  async function despachar({ telefono, nombre, texto }) {
    if (typeof texto !== 'string' || !texto.trim()) {
      return ['Por ahora solo entiendo mensajes de texto. Escribe *menu* para ver las opciones.'];
    }

    const comando = normalizarComando(texto);

    if (COMANDOS_MENU.includes(comando)) {
      sesiones.borrar(telefono);
      return [menu(nombre)];
    }

    const sesion = sesiones.obtener(telefono);
    if (!sesion) return desdeMenu({ telefono, nombre, comando });

    const contexto = { telefono, nombre, texto, comando, datos: sesion.datos };
    switch (sesion.estado) {
      case 'reservar_servicios': return elegirServicios(contexto);
      case 'reservar_barbero': return elegirBarbero(contexto);
      case 'reservar_fecha': return elegirFecha(contexto);
      case 'reservar_hora': return elegirHora(contexto);
      case 'reservar_confirmar': return confirmarReserva(contexto);
      case 'reservar_nombre': return pedirNombre(contexto);
      case 'cancelar_elegir': return elegirCancelacion(contexto);
      case 'cancelar_confirmar': return confirmarCancelacion(contexto);
      default:
        sesiones.borrar(telefono);
        return [menu(nombre)];
    }
  }

  // ---------------------------------------------------------------- menú

  async function desdeMenu({ telefono, nombre, comando }) {
    switch (comando) {
      case '1': case 'servicios': case 'precios':
        return verServicios();
      case '2': case 'reservar': case 'turno': case 'agendar':
        return iniciarReserva(telefono);
      case '3': case 'mis turnos': case 'turnos':
        return verTurnos(telefono);
      case '4': case 'cancelar':
        return iniciarCancelacion(telefono);
      default:
        return ['No entendí tu mensaje 😅', menu(nombre)];
    }
  }

  async function verServicios() {
    const servicios = await api.servicios();
    if (!servicios.length) return ['Por ahora no hay servicios disponibles. Intenta más tarde.'];

    return [`Nuestros servicios:\n${listaServicios(servicios)}\n\nPara reservar escribe *2*. Para volver escribe *menu*.`];
  }

  // ---------------------------------------------------------------- reservar

  async function iniciarReserva(telefono) {
    const servicios = await api.servicios();
    if (!servicios.length) return ['Por ahora no hay servicios disponibles. Intenta más tarde.'];

    sesiones.guardar(telefono, 'reservar_servicios', { servicios });
    return [`¿Qué servicio quieres?\n${listaServicios(servicios)}\n\nEscribe el número (o varios separados por coma, por ejemplo *1,3*).`];
  }

  async function elegirServicios({ telefono, texto, datos }) {
    const numeros = interpretarNumeros(texto);
    const valido = numeros && numeros.every((n) => n >= 1 && n <= datos.servicios.length);
    if (!valido) {
      return [`Escribe solo el número del servicio, entre 1 y ${datos.servicios.length}. Por ejemplo: *1* o *1,3*.`];
    }

    const elegidos = numeros.map((n) => datos.servicios[n - 1]);
    const barberos = await api.barberos();
    if (!barberos.length) {
      sesiones.borrar(telefono);
      return ['Por ahora no hay barberos disponibles. Intenta más tarde.'];
    }

    if (barberos.length === 1) {
      sesiones.guardar(telefono, 'reservar_fecha', { servicios: elegidos, barbero: barberos[0] });
      return [`Tu barbero será *${barberos[0].nombre}*.\n\n${preguntaFecha}`];
    }

    sesiones.guardar(telefono, 'reservar_barbero', { servicios: elegidos, barberos });
    const lista = barberos.map((b, i) => `${i + 1}. ${b.nombre}`).join('\n');
    return [`¿Con quién quieres tu turno?\n${lista}\n\nEscribe el número.`];
  }

  const preguntaFecha = '¿Para qué día? Escribe *hoy*, *mañana* o una fecha como *05/11*.';

  async function elegirBarbero({ telefono, texto, datos }) {
    const numeros = interpretarNumeros(texto);
    const n = numeros?.length === 1 ? numeros[0] : null;
    if (!n || n < 1 || n > datos.barberos.length) {
      return [`Escribe el número del barbero, entre 1 y ${datos.barberos.length}.`];
    }

    sesiones.guardar(telefono, 'reservar_fecha', { servicios: datos.servicios, barbero: datos.barberos[n - 1] });
    return [preguntaFecha];
  }

  async function elegirFecha({ telefono, texto, datos }) {
    const resultado = interpretarFecha(texto, hoy());
    if (resultado.error === 'pasada') return ['Esa fecha ya pasó. Escribe otra: *hoy*, *mañana* o una fecha como *05/11*.'];
    if (resultado.error) return ['No entendí la fecha. Escribe *hoy*, *mañana* o una fecha como *05/11*.'];

    return mostrarHoras(telefono, { ...datos, fecha: resultado.fecha });
  }

  /** Consulta la disponibilidad y muestra la primera página de horarios (o pide otra fecha si no hay). */
  async function mostrarHoras(telefono, datos) {
    const disponibilidad = await api.disponibilidad(datos.barbero.id, datos.fecha, datos.servicios.map((s) => s.id));

    if (!disponibilidad.horas.length) {
      sesiones.guardar(telefono, 'reservar_fecha', { servicios: datos.servicios, barbero: datos.barbero });
      return [`No hay horarios libres con *${datos.barbero.nombre}* el ${fechaLarga(datos.fecha)}. Escribe otra fecha, o *menu* para volver.`];
    }

    const nuevos = { ...datos, horas: disponibilidad.horas, pagina: 0 };
    sesiones.guardar(telefono, 'reservar_hora', nuevos);
    return [paginaDeHoras(nuevos)];
  }

  async function elegirHora({ telefono, texto, comando, datos }) {
    if (comando === 'mas') {
      const paginas = Math.ceil(datos.horas.length / HORAS_POR_PAGINA);
      const nuevos = { ...datos, pagina: (datos.pagina + 1) % paginas };
      sesiones.guardar(telefono, 'reservar_hora', nuevos);
      return [paginaDeHoras(nuevos)];
    }

    let hora = null;
    if (/^\d{1,2}:\d{2}$/.test(comando)) {
      const normal = comando.padStart(5, '0');
      hora = datos.horas.includes(normal) ? normal : null;
    } else {
      const numeros = interpretarNumeros(texto);
      const n = numeros?.length === 1 ? numeros[0] : null;
      if (n && n >= 1 && n <= datos.horas.length) hora = datos.horas[n - 1];
    }

    if (!hora) return ['Elige el número de uno de los horarios de la lista (o escribe *más* para ver otros).'];

    const nuevos = { ...datos, hora };
    sesiones.guardar(telefono, 'reservar_confirmar', nuevos);
    return [resumen(nuevos)];
  }

  async function confirmarReserva({ telefono, nombre, texto, datos }) {
    if (esNo(texto)) {
      sesiones.borrar(telefono);
      return ['Listo, no reservé nada. Escribe *menu* cuando quieras volver a empezar.'];
    }
    if (!esSi(texto)) return ['Responde *sí* para confirmar el turno o *no* para descartarlo.'];

    return crearTurno({ telefono, nombre: nombre || datos.nombreCliente, datos });
  }

  async function crearTurno({ telefono, nombre, datos }) {
    try {
      const turno = await api.crearTurno({
        telefono,
        nombre,
        barberoId: datos.barbero.id,
        fecha: datos.fecha,
        horaInicio: datos.hora,
        servicioIds: datos.servicios.map((s) => s.id),
        origen, // el panel muestra por qué canal llegó el turno
      });

      sesiones.borrar(telefono);
      return [[
        '✅ ¡Listo! Tu turno quedó registrado y está *pendiente de confirmación*.',
        `• ${fechaLarga(turno.fecha)}, de ${hora12(turno.hora_inicio)} a ${hora12(turno.hora_fin)}`,
        `• Con ${turno.barbero.nombre}`,
        `• Total: ${pesos(turno.precio_total)}`,
        `• Turno #${turno.id}`,
        '',
        'La barbería lo confirmará. Escribe *3* para ver tus turnos o *menu* para volver.',
      ].join('\n')];
    } catch (error) {
      if (!(error instanceof ApiRechazo)) throw error;

      // Primera vez que escribe y WhatsApp no trajo su nombre: se le pregunta
      if (error.message.includes('necesita su nombre')) {
        sesiones.guardar(telefono, 'reservar_nombre', datos);
        return ['Para registrarte necesito tu nombre. ¿Cómo te llamas?'];
      }

      // Una regla de negocio dijo que no (p. ej. R1: alguien tomó ese horario mientras tanto):
      // se explica y se vuelven a mostrar los horarios libres del mismo día.
      const horarios = await mostrarHoras(telefono, { servicios: datos.servicios, barbero: datos.barbero, fecha: datos.fecha });
      return [`⚠️ ${error.message}`, ...horarios];
    }
  }

  async function pedirNombre({ telefono, texto, datos }) {
    const nombreCliente = texto.trim();
    if (nombreCliente.length < 2 || nombreCliente.length > 100) {
      return ['Escribe tu nombre (entre 2 y 100 letras).'];
    }
    return crearTurno({ telefono, nombre: nombreCliente, datos: { ...datos, nombreCliente } });
  }

  // ---------------------------------------------------------------- consultar y cancelar

  async function verTurnos(telefono) {
    sesiones.borrar(telefono);
    const turnos = turnosActivos(await api.turnos(telefono));
    if (!turnos.length) return ['No tienes turnos pendientes. Escribe *2* para reservar uno.'];

    const lineas = turnos.map((t) => `• ${textoTurno(t)}\n  Estado: ${ESTADOS_VISIBLES[t.estado]}`);
    return [`Tus turnos:\n${lineas.join('\n')}\n\nPara cancelar uno escribe *4*. Para volver escribe *menu*.`];
  }

  async function iniciarCancelacion(telefono) {
    const turnos = turnosActivos(await api.turnos(telefono));
    if (!turnos.length) {
      sesiones.borrar(telefono);
      return ['No tienes turnos para cancelar.'];
    }

    sesiones.guardar(telefono, 'cancelar_elegir', { turnos });
    const lista = turnos.map((t, i) => `${i + 1}. ${textoTurno(t)}`).join('\n');
    return [`¿Cuál turno quieres cancelar?\n${lista}\n\nEscribe el número. Recuerda que solo se puede cancelar hasta 2 horas antes.`];
  }

  async function elegirCancelacion({ telefono, texto, datos }) {
    const numeros = interpretarNumeros(texto);
    const n = numeros?.length === 1 ? numeros[0] : null;
    if (!n || n < 1 || n > datos.turnos.length) {
      return [`Escribe el número del turno, entre 1 y ${datos.turnos.length}.`];
    }

    const turno = datos.turnos[n - 1];
    sesiones.guardar(telefono, 'cancelar_confirmar', { turno });
    return [`¿Seguro que quieres cancelar este turno?\n${textoTurno(turno)}\n\nResponde *sí* o *no*.`];
  }

  async function confirmarCancelacion({ telefono, texto, datos }) {
    if (esNo(texto)) {
      sesiones.borrar(telefono);
      return ['Perfecto, tu turno sigue en pie. Escribe *menu* para volver.'];
    }
    if (!esSi(texto)) return ['Responde *sí* para cancelar el turno o *no* para conservarlo.'];

    try {
      await api.cancelarTurno(datos.turno.id);
      sesiones.borrar(telefono);
      return ['✅ Tu turno fue cancelado. Escribe *menu* si necesitas algo más.'];
    } catch (error) {
      if (error instanceof ApiRechazo) {
        // R3: faltan menos de 2 horas, o el turno ya cambió de estado
        sesiones.borrar(telefono);
        const motivo = error.message.replace(/\.?$/, '.'); // la API no siempre termina el mensaje con punto
        return [`⚠️ ${motivo}\nSi necesitas ayuda, comunícate directamente con la barbería.`];
      }
      if (error instanceof ApiNoEncontrado) {
        sesiones.borrar(telefono);
        return ['Ese turno ya no existe. Escribe *3* para ver tus turnos actuales.'];
      }
      throw error;
    }
  }

  return { procesar };
}
