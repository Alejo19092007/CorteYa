import assert from 'node:assert/strict';
import test from 'node:test';
import { ApiAuthError, ApiNoDisponible, ApiNoEncontrado, ApiRechazo } from '../src/api/corteyaClient.js';
import { crearBot } from '../src/bot/conversacion.js';
import { crearSesiones } from '../src/bot/sesiones.js';

// Reloj fijo: lunes 2 de noviembre de 2026, 10:00 en Bogotá (15:00 UTC), igual que las pruebas del backend
const AHORA = new Date('2026-11-02T15:00:00Z');
const TEL = '573001234567';

const SERVICIOS = [
  { id: 1, nombre: 'Corte clásico', duracion_min: 45, precio: 25000, activo: true },
  { id: 2, nombre: 'Barba', duracion_min: 30, precio: 15000, activo: true },
  { id: 3, nombre: 'Corte infantil', duracion_min: 40, precio: 20000, activo: true },
];
const BARBEROS = [{ id: 1, nombre: 'Andrés Gómez' }, { id: 2, nombre: 'Camilo Rojas' }];
const HORAS = ['09:00', '09:15', '09:30', '09:45', '10:00', '10:15', '10:30', '10:45', '11:00', '11:15', '11:30', '11:45'];

const silencio = { warn() {}, error() {} };

/** API falsa: cada función se puede reemplazar en cada prueba y guarda sus llamadas. */
function apiFalsa(cambios = {}) {
  const llamadas = { crearTurno: [], cancelarTurno: [], disponibilidad: [] };

  const api = {
    servicios: async () => SERVICIOS,
    barberos: async () => BARBEROS,
    disponibilidad: async (barberoId, fecha, ids) => {
      llamadas.disponibilidad.push({ barberoId, fecha, ids });
      return { barbero_id: barberoId, fecha, duracion_min: 45, horas: HORAS };
    },
    turnos: async () => [],
    crearTurno: async (datos) => {
      llamadas.crearTurno.push(datos);
      return {
        id: 77, fecha: datos.fecha, hora_inicio: datos.horaInicio, hora_fin: '10:45', precio_total: 40000, estado: 'pendiente',
        barbero: { id: datos.barberoId, nombre: 'Andrés Gómez' },
      };
    },
    cancelarTurno: async (id) => { llamadas.cancelarTurno.push(id); },
    ...cambios,
  };

  return { api, llamadas };
}

function nuevoBot(cambios) {
  const { api, llamadas } = apiFalsa(cambios);
  const sesiones = crearSesiones();
  const bot = crearBot({ api, sesiones, ahora: () => AHORA, registrar: silencio });

  /** Envía los mensajes en orden y devuelve lo que respondió el bot al ÚLTIMO (todo junto en un texto). */
  const decir = async (...mensajes) => {
    let ultima = [];
    for (const texto of mensajes) ultima = await bot.procesar({ telefono: TEL, nombre: 'Daniela Silva', texto });
    return ultima.join('\n');
  };

  return { bot, decir, llamadas, sesiones };
}

const turno = (id, fecha, inicio, fin, estado = 'confirmado') => ({
  id, fecha, hora_inicio: inicio, hora_fin: fin, estado,
  servicios: [{ nombre: 'Corte clásico' }], barbero: { nombre: 'Andrés Gómez' },
});

// ------------------------------------------------------------------- menú

test('"hola" muestra el menú y saluda por el primer nombre', async () => {
  const { decir } = nuevoBot();
  const respuesta = await decir('Hola');

  assert.match(respuesta, /¡Hola, Daniela!/);
  for (const opcion of ['1. Ver servicios', '2. Reservar', '3. Ver mis turnos', '4. Cancelar']) assert.match(respuesta, new RegExp(opcion));
});

test('un mensaje que no entiende vuelve a mostrar el menú', async () => {
  const { decir } = nuevoBot();
  const respuesta = await decir('quiero pizza');

  assert.match(respuesta, /No entendí/);
  assert.match(respuesta, /1\. Ver servicios/);
});

test('un mensaje que no es texto (imagen, audio) recibe una explicación', async () => {
  const { bot } = nuevoBot();
  const [respuesta] = await bot.procesar({ telefono: TEL, nombre: null, texto: null });

  assert.match(respuesta, /solo entiendo mensajes de texto/);
});

test('"menu" en mitad de una reserva la descarta y vuelve al inicio', async () => {
  const { decir, sesiones } = nuevoBot();
  await decir('2', '1');
  assert.equal(sesiones.obtener(TEL).estado, 'reservar_barbero');

  const respuesta = await decir('menu');

  assert.match(respuesta, /1\. Ver servicios/);
  assert.equal(sesiones.obtener(TEL), null);
});

test('"1" lista los servicios con precio y duración', async () => {
  const { decir } = nuevoBot();
  const respuesta = await decir('1');

  assert.match(respuesta, /1\. Corte clásico — \$25\.000 \(45 min\)/);
  assert.match(respuesta, /2\. Barba — \$15\.000 \(30 min\)/);
});

// ------------------------------------------------------------------- reservar

test('reserva completa: servicios, barbero, fecha, hora y confirmación crean el turno', async () => {
  const { decir, llamadas, sesiones } = nuevoBot();

  assert.match(await decir('2'), /¿Qué servicio quieres\?/);
  assert.match(await decir('1,2'), /¿Con quién quieres tu turno\?[\s\S]*1\. Andrés Gómez[\s\S]*2\. Camilo Rojas/);
  assert.match(await decir('1'), /¿Para qué día\?/);

  const horarios = await decir('mañana');
  assert.match(horarios, /Horarios libres con \*Andrés Gómez\* el martes 3 de noviembre/);
  assert.match(horarios, /1\. 9:00 a\. m\./);
  assert.match(horarios, /10\. 11:15 a\. m\./);
  assert.doesNotMatch(horarios, /11\. /);              // solo 10 por página
  assert.match(horarios, /Escribe \*más\*/);

  const resumen = await decir('2');                       // la segunda hora: 9:15
  assert.match(resumen, /Servicios: Corte clásico \+ Barba/);
  assert.match(resumen, /Barbero: Andrés Gómez/);
  assert.match(resumen, /Día: martes 3 de noviembre/);
  assert.match(resumen, /Hora: 9:15 a\. m\./);
  assert.match(resumen, /Duración: 75 min · Total: \$40\.000/);
  assert.equal(llamadas.crearTurno.length, 0);            // todavía no se crea nada

  const final = await decir('sí');
  assert.match(final, /pendiente de confirmación/);
  assert.match(final, /Turno #77/);
  assert.deepEqual(llamadas.crearTurno, [{
    telefono: TEL, nombre: 'Daniela Silva', barberoId: 1, fecha: '2026-11-03', horaInicio: '09:15', servicioIds: [1, 2],
  }]);
  assert.equal(sesiones.obtener(TEL), null);              // la conversación terminó
});

test('la disponibilidad se consulta con el barbero, la fecha y los servicios elegidos', async () => {
  const { decir, llamadas } = nuevoBot();
  await decir('2', '3', '2', '05/11');

  assert.deepEqual(llamadas.disponibilidad, [{ barberoId: 2, fecha: '2026-11-05', ids: [3] }]);
});

test('"hoy" usa la fecha de Colombia', async () => {
  const { decir, llamadas } = nuevoBot();
  await decir('2', '1', '1', 'hoy');

  assert.equal(llamadas.disponibilidad[0].fecha, '2026-11-02');
});

test('con un solo barbero no se le pregunta: se asigna directamente', async () => {
  const { decir } = nuevoBot({ barberos: async () => [BARBEROS[0]] });

  const respuesta = await decir('2', '1');

  assert.match(respuesta, /Tu barbero será \*Andrés Gómez\*/);
  assert.match(respuesta, /¿Para qué día\?/);
});

test('un número fuera de rango o texto en un paso no avanza y explica qué escribir', async () => {
  const { decir, sesiones } = nuevoBot();
  await decir('2');

  assert.match(await decir('9'), /entre 1 y 3/);
  assert.match(await decir('corte'), /entre 1 y 3/);
  assert.equal(sesiones.obtener(TEL).estado, 'reservar_servicios');

  await decir('1');
  assert.match(await decir('7'), /entre 1 y 2/);
  assert.equal(sesiones.obtener(TEL).estado, 'reservar_barbero');
});

test('una fecha pasada o ilegible se rechaza y se vuelve a preguntar', async () => {
  const { decir, sesiones, llamadas } = nuevoBot();
  await decir('2', '1', '1');

  assert.match(await decir('01/11'), /ya pasó/);
  assert.match(await decir('cuando puedas'), /No entendí la fecha/);
  assert.equal(sesiones.obtener(TEL).estado, 'reservar_fecha');
  assert.equal(llamadas.disponibilidad.length, 0);
});

test('si no hay horarios libres ese día pide otra fecha y se queda en ese paso', async () => {
  const { decir, sesiones } = nuevoBot({
    disponibilidad: async (barberoId, fecha) => ({ barbero_id: barberoId, fecha, duracion_min: 45, horas: [] }),
  });
  await decir('2', '1', '1');

  const respuesta = await decir('mañana');

  assert.match(respuesta, /No hay horarios libres con \*Andrés Gómez\* el martes 3 de noviembre/);
  assert.equal(sesiones.obtener(TEL).estado, 'reservar_fecha');
});

test('"más" muestra la siguiente página de horarios y numera de corrido', async () => {
  const { decir } = nuevoBot();
  await decir('2', '1', '1', 'mañana');

  const pagina2 = await decir('más');

  assert.match(pagina2, /11\. 11:30 a\. m\./);
  assert.match(pagina2, /12\. 11:45 a\. m\./);
  assert.doesNotMatch(pagina2, /Escribe \*más\*/);       // ya no hay más páginas

  const resumen = await decir('11');                      // el número sigue contando desde la primera página
  assert.match(resumen, /Hora: 11:30 a\. m\./);
});

test('también se puede escribir la hora como 09:30', async () => {
  const { decir } = nuevoBot();
  await decir('2', '1', '1', 'mañana');

  assert.match(await decir('09:30'), /Hora: 9:30 a\. m\./);
});

test('una hora que no está en la lista se rechaza', async () => {
  const { decir, sesiones } = nuevoBot();
  await decir('2', '1', '1', 'mañana');

  assert.match(await decir('15:00'), /Elige el número de uno de los horarios/);
  assert.match(await decir('99'), /Elige el número de uno de los horarios/);
  assert.equal(sesiones.obtener(TEL).estado, 'reservar_hora');
});

test('si el cliente responde "no" al resumen no se crea ningún turno', async () => {
  const { decir, llamadas, sesiones } = nuevoBot();
  await decir('2', '1', '1', 'mañana', '1');

  assert.match(await decir('no'), /no reservé nada/);
  assert.equal(llamadas.crearTurno.length, 0);
  assert.equal(sesiones.obtener(TEL), null);
});

test('una respuesta que no es sí ni no en la confirmación se vuelve a preguntar', async () => {
  const { decir, llamadas } = nuevoBot();
  await decir('2', '1', '1', 'mañana', '1');

  assert.match(await decir('tal vez'), /Responde \*sí\*/);
  assert.equal(llamadas.crearTurno.length, 0);
});

test('R1: si alguien tomó el horario mientras tanto, se explica y se ofrecen los horarios de nuevo', async () => {
  const { decir, sesiones } = nuevoBot({
    crearTurno: async () => {
      throw new ApiRechazo('El barbero no está disponible en ese horario (ya tiene un turno de 09:00 a 09:45).');
    },
  });
  await decir('2', '1', '1', 'mañana', '1');

  const respuesta = await decir('sí');

  assert.match(respuesta, /⚠️ El barbero no está disponible en ese horario/);
  assert.match(respuesta, /Horarios libres con \*Andrés Gómez\*/);
  assert.equal(sesiones.obtener(TEL).estado, 'reservar_hora');
});

test('primera vez sin nombre en WhatsApp: el bot lo pregunta y lo manda a la API', async () => {
  let intento = 0;
  const { bot, llamadas, sesiones } = nuevoBot({
    crearTurno: async (datos) => {
      intento += 1;
      if (!datos.nombre) throw new ApiRechazo('Para registrar un cliente nuevo se necesita su nombre.');
      return {
        id: 5, fecha: datos.fecha, hora_inicio: datos.horaInicio, hora_fin: '09:45', precio_total: 25000, estado: 'pendiente',
        barbero: { nombre: 'Andrés Gómez' },
      };
    },
  });
  const decir = async (...mensajes) => {
    let ultima = [];
    for (const texto of mensajes) ultima = await bot.procesar({ telefono: TEL, nombre: null, texto });
    return ultima.join('\n');
  };

  await decir('2', '1', '1', 'mañana', '1');
  assert.match(await decir('sí'), /¿Cómo te llamas\?/);
  assert.equal(sesiones.obtener(TEL).estado, 'reservar_nombre');

  assert.match(await decir('x'), /Escribe tu nombre/);    // demasiado corto
  assert.match(await decir('Mario Díaz'), /Turno #5/);
  assert.equal(intento, 2);
  assert.equal(sesiones.obtener(TEL), null);
});

// ------------------------------------------------------------------- consultar

test('"3" muestra solo los turnos vigentes: pendientes y confirmados que aún no pasaron', async () => {
  const { decir } = nuevoBot({
    turnos: async () => [
      turno(1, '2026-11-03', '10:00', '10:45', 'pendiente'),
      turno(2, '2026-11-02', '14:00', '14:45', 'confirmado'),   // hoy, más tarde
      turno(3, '2026-11-02', '08:00', '08:45', 'confirmado'),   // hoy, ya pasó
      turno(4, '2026-10-20', '10:00', '10:45', 'atendido'),
      turno(5, '2026-11-05', '10:00', '10:45', 'cancelado'),
      turno(6, '2026-11-06', '10:00', '10:45', 'no_asistio'),
    ],
  });

  const respuesta = await decir('3');

  assert.match(respuesta, /lunes 2 de noviembre, 2:00 p\. m\./);
  assert.match(respuesta, /martes 3 de noviembre, 10:00 a\. m\./);
  assert.match(respuesta, /Estado: pendiente de confirmación/);
  assert.match(respuesta, /Estado: confirmado/);
  assert.equal((respuesta.match(/•/g) ?? []).length, 2);
  assert.ok(respuesta.indexOf('lunes 2') < respuesta.indexOf('martes 3'));   // en orden cronológico
});

test('sin turnos vigentes lo dice y ofrece reservar', async () => {
  const { decir } = nuevoBot();

  assert.match(await decir('3'), /No tienes turnos pendientes/);
});

// ------------------------------------------------------------------- cancelar

test('cancelar: elige el turno, confirma y se cancela en la API', async () => {
  const { decir, llamadas, sesiones } = nuevoBot({
    turnos: async () => [turno(11, '2026-11-03', '10:00', '10:45'), turno(12, '2026-11-04', '15:00', '15:45')],
  });

  const lista = await decir('4');
  assert.match(lista, /1\. martes 3 de noviembre, 10:00 a\. m\./);
  assert.match(lista, /2\. miércoles 4 de noviembre, 3:00 p\. m\./);
  assert.match(lista, /hasta 2 horas antes/);

  assert.match(await decir('2'), /¿Seguro que quieres cancelar este turno\?[\s\S]*miércoles 4 de noviembre/);
  assert.equal(llamadas.cancelarTurno.length, 0);

  assert.match(await decir('sí'), /Tu turno fue cancelado/);
  assert.deepEqual(llamadas.cancelarTurno, [12]);
  assert.equal(sesiones.obtener(TEL), null);
});

test('cancelar y responder "no" conserva el turno', async () => {
  const { decir, llamadas } = nuevoBot({ turnos: async () => [turno(11, '2026-11-03', '10:00', '10:45')] });
  await decir('4', '1');

  assert.match(await decir('no'), /sigue en pie/);
  assert.equal(llamadas.cancelarTurno.length, 0);
});

test('R3: si faltan menos de 2 horas la API responde 422 y el bot lo explica', async () => {
  const { decir, sesiones } = nuevoBot({
    turnos: async () => [turno(11, '2026-11-02', '11:00', '11:45')],
    cancelarTurno: async () => { throw new ApiRechazo('Ya no es posible cancelar este turno'); },
  });
  await decir('4', '1');

  const respuesta = await decir('sí');

  assert.match(respuesta, /⚠️ Ya no es posible cancelar este turno\./);
  assert.match(respuesta, /comunícate directamente con la barbería/);
  assert.doesNotMatch(respuesta, /\.\./);
  assert.equal(sesiones.obtener(TEL), null);
});

test('cancelar un turno que ya no existe (404) lo informa', async () => {
  const { decir } = nuevoBot({
    turnos: async () => [turno(11, '2026-11-03', '10:00', '10:45')],
    cancelarTurno: async () => { throw new ApiNoEncontrado(); },
  });
  await decir('4', '1');

  assert.match(await decir('sí'), /ya no existe/);
});

test('cancelar sin turnos vigentes no abre ningún flujo', async () => {
  const { decir, sesiones } = nuevoBot();

  assert.match(await decir('4'), /No tienes turnos para cancelar/);
  assert.equal(sesiones.obtener(TEL), null);
});

test('al elegir cancelar un número inválido se vuelve a pedir', async () => {
  const { decir } = nuevoBot({ turnos: async () => [turno(11, '2026-11-03', '10:00', '10:45')] });
  await decir('4');

  assert.match(await decir('5'), /entre 1 y 1/);
});

// ------------------------------------------------------------------- fallos de la API

test('sin conexión con la API el bot responde con amabilidad y conserva el paso para reintentar', async () => {
  let caida = true;
  const { decir, sesiones } = nuevoBot({
    servicios: async () => {
      if (caida) throw new ApiNoDisponible('sin conexión');
      return SERVICIOS;
    },
  });

  assert.match(await decir('2'), /No pude comunicarme con el sistema de turnos/);
  assert.equal(sesiones.obtener(TEL), null);

  caida = false;
  assert.match(await decir('2'), /¿Qué servicio quieres\?/);
});

test('si la API cae a mitad de una reserva, el cliente puede reintentar sin empezar de cero', async () => {
  let caida = true;
  const { decir, llamadas, sesiones } = nuevoBot({
    crearTurno: async (datos) => {
      if (caida) throw new ApiNoDisponible('tiempo de espera agotado');
      return {
        id: 9, fecha: datos.fecha, hora_inicio: datos.horaInicio, hora_fin: '09:45', precio_total: 25000, estado: 'pendiente',
        barbero: { nombre: 'Andrés Gómez' },
      };
    },
  });
  await decir('2', '1', '1', 'mañana', '1');

  assert.match(await decir('sí'), /No pude comunicarme/);
  assert.equal(sesiones.obtener(TEL).estado, 'reservar_confirmar');   // sigue en la confirmación

  caida = false;
  assert.match(await decir('sí'), /Turno #9/);
  assert.equal(llamadas.disponibilidad.length, 1);
});

test('si las credenciales del bot fallan avisa de un problema técnico y no se queda a medias', async () => {
  const { decir, sesiones } = nuevoBot({ servicios: async () => { throw new ApiAuthError('login 401'); } });

  assert.match(await decir('2'), /problema técnico/);
  assert.equal(sesiones.obtener(TEL), null);
});

test('un error inesperado no tumba la conversación ni filtra detalles', async () => {
  const { decir } = nuevoBot({ servicios: async () => { throw new Error('boom: contraseña=secreta'); } });

  const respuesta = await decir('1');

  assert.match(respuesta, /error inesperado/);
  assert.doesNotMatch(respuesta, /boom|secreta/);
});

// ------------------------------------------------------------------- sesiones

test('cada cliente tiene su propia conversación', async () => {
  const { bot, sesiones } = nuevoBot();

  await bot.procesar({ telefono: '573001111111', nombre: 'Ana', texto: '2' });
  await bot.procesar({ telefono: '573002222222', nombre: 'Luis', texto: '1' });

  assert.equal(sesiones.obtener('573001111111').estado, 'reservar_servicios');
  assert.equal(sesiones.obtener('573002222222'), null);
});

test('una conversación abandonada caduca y vuelve al menú', () => {
  let reloj = 0;
  const sesiones = crearSesiones({ ttlMs: 1000, ahora: () => reloj });

  sesiones.guardar(TEL, 'reservar_fecha', { a: 1 });
  reloj = 999;
  assert.equal(sesiones.obtener(TEL).estado, 'reservar_fecha');

  reloj = 2500;
  assert.equal(sesiones.obtener(TEL), null);
  assert.equal(sesiones.cantidad, 0);
});
