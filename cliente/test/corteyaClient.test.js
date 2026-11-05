import assert from 'node:assert/strict';
import test from 'node:test';
import {
  ApiAuthError, ApiNoDisponible, ApiNoEncontrado, ApiRechazo, crearClienteApi,
} from '../src/api/corteyaClient.js';

const json = (estado, cuerpo) => new Response(JSON.stringify(cuerpo), { status: estado, headers: { 'Content-Type': 'application/json' } });
const loginOk = (token = 'tok-1') => json(200, { token, token_type: 'Bearer', user: { rol: 'admin' } });

/** fetch falso: devuelve las respuestas en orden y guarda cada llamada para poder revisarla. */
function fetchFalso(...respuestas) {
  const llamadas = [];
  const cola = [...respuestas];

  const fetchFn = async (url, opciones) => {
    llamadas.push({ url: new URL(url), metodo: opciones.method, cabeceras: opciones.headers, cuerpo: opciones.body ? JSON.parse(opciones.body) : null });
    const siguiente = cola.shift();
    if (siguiente instanceof Error) throw siguiente;
    if (!siguiente) throw new Error('El fetch falso se quedó sin respuestas');
    return siguiente;
  };

  return { fetchFn, llamadas };
}

// Las pruebas de siempre no usan reintentos (esperasReintentoMs: []); los reintentos tienen sus propias pruebas más abajo
const nuevoCliente = (fetchFn) => crearClienteApi({ baseUrl: 'https://corteya.example/', email: 'bot@corteya.com', password: 'clave', fetchFn, esperasReintentoMs: [] });

// Con dos reintentos sin pausa (para que la prueba sea instantánea)
const clienteConReintentos = (fetchFn) => crearClienteApi({ baseUrl: 'https://corteya.example/', email: 'bot@corteya.com', password: 'clave', fetchFn, esperasReintentoMs: [0, 0] });

test('inicia sesión una vez y manda el token Bearer en las llamadas siguientes', async () => {
  const { fetchFn, llamadas } = fetchFalso(loginOk('abc'), json(200, { data: [] }), json(200, { data: [] }));
  const api = nuevoCliente(fetchFn);

  await api.servicios();
  await api.servicios();

  assert.equal(llamadas.filter((l) => l.url.pathname === '/api/login').length, 1);
  assert.deepEqual(llamadas[0].cuerpo, { email: 'bot@corteya.com', password: 'clave', device_name: 'bot' });
  assert.equal(llamadas[0].cabeceras.Authorization, undefined); // el login no lleva token
  assert.equal(llamadas[1].cabeceras.Authorization, 'Bearer abc');
  assert.equal(llamadas[2].cabeceras.Authorization, 'Bearer abc');
  assert.equal(llamadas[1].url.href, 'https://corteya.example/api/servicios?activo=1'); // sin doble barra
});

test('varias llamadas simultáneas comparten un solo login', async () => {
  const { fetchFn, llamadas } = fetchFalso(loginOk(), json(200, { data: [] }), json(200, { data: [] }), json(200, { data: [] }));
  const api = nuevoCliente(fetchFn);

  await Promise.all([api.servicios(), api.barberos(), api.servicios()]);

  assert.equal(llamadas.filter((l) => l.url.pathname === '/api/login').length, 1);
});

test('los servicios se mandan como servicios[] que es lo que lee Laravel', async () => {
  const { fetchFn, llamadas } = fetchFalso(loginOk(), json(200, { data: { horas: ['10:00'] } }));

  const datos = await nuevoCliente(fetchFn).disponibilidad(4, '2026-11-03', [1, 2]);

  assert.deepEqual(datos.horas, ['10:00']);
  assert.equal(llamadas[1].url.pathname, '/api/barberos/4/disponibilidad');
  assert.equal(llamadas[1].url.searchParams.get('fecha'), '2026-11-03');
  assert.deepEqual(llamadas[1].url.searchParams.getAll('servicios[]'), ['1', '2']);
});

test('crearTurno envía los datos que espera la API y no manda un nombre vacío', async () => {
  const turno = { id: 9, estado: 'pendiente' };
  const { fetchFn, llamadas } = fetchFalso(loginOk(), json(201, { data: turno }), json(201, { data: turno }));
  const api = nuevoCliente(fetchFn);

  const datos = { telefono: '573001234567', barberoId: 2, fecha: '2026-11-03', horaInicio: '10:00', servicioIds: [1, 3] };
  assert.deepEqual(await api.crearTurno({ ...datos, nombre: 'Daniela' }), turno);
  await api.crearTurno({ ...datos, nombre: null });

  assert.equal(llamadas[1].metodo, 'POST');
  assert.deepEqual(llamadas[1].cuerpo, {
    telefono: '573001234567', nombre: 'Daniela', barbero_id: 2, fecha: '2026-11-03', hora_inicio: '10:00', servicios: [1, 3],
  });
  assert.equal('nombre' in llamadas[2].cuerpo, false);
});

test('crearTurno envía el canal de origen cuando se indica y lo omite si no', async () => {
  const turno = { id: 9, estado: 'pendiente' };
  const { fetchFn, llamadas } = fetchFalso(loginOk(), json(201, { data: turno }), json(201, { data: turno }));
  const api = nuevoCliente(fetchFn);
  const datos = { telefono: '573001234567', nombre: 'Daniela', barberoId: 2, fecha: '2026-11-03', horaInicio: '10:00', servicioIds: [1] };

  await api.crearTurno({ ...datos, origen: 'telegram' });
  await api.crearTurno(datos);

  assert.equal(llamadas[1].cuerpo.origen, 'telegram');
  assert.equal('origen' in llamadas[2].cuerpo, false);   // sin origen, la API asume Telegram
});

test('el nombre del token se puede personalizar (un token por canal)', async () => {
  const { fetchFn, llamadas } = fetchFalso(loginOk(), json(200, { data: [] }));
  const api = crearClienteApi({ baseUrl: 'https://corteya.example', email: 'a@b.c', password: 'x', fetchFn, deviceName: 'bot-telegram' });

  await api.servicios();

  assert.equal(llamadas[0].cuerpo.device_name, 'bot-telegram');
});

test('cancelarTurno usa DELETE y acepta el 204 sin cuerpo', async () => {
  const { fetchFn, llamadas } = fetchFalso(loginOk(), new Response(null, { status: 204 }));

  assert.equal(await nuevoCliente(fetchFn).cancelarTurno(15), undefined);
  assert.equal(llamadas[1].metodo, 'DELETE');
  assert.equal(llamadas[1].url.pathname, '/api/turnos/15');
});

// ------------------------------------------------------------- 401: el token venció

test('ante un 401 vuelve a iniciar sesión y repite la llamada una vez', async () => {
  const { fetchFn, llamadas } = fetchFalso(
    loginOk('viejo'), json(200, { data: ['uno'] }),               // primera llamada normal
    json(401, { message: 'No autenticado' }),                      // el token ya no sirve
    loginOk('nuevo'), json(200, { data: ['dos'] }),                // login nuevo y reintento
  );
  const api = nuevoCliente(fetchFn);

  await api.servicios();
  assert.deepEqual(await api.servicios(), ['dos']);

  assert.equal(llamadas.filter((l) => l.url.pathname === '/api/login').length, 2);
  assert.equal(llamadas.at(-1).cabeceras.Authorization, 'Bearer nuevo');
});

test('si después de iniciar sesión sigue el 401, lanza ApiAuthError (no se queda en un ciclo)', async () => {
  const { fetchFn, llamadas } = fetchFalso(loginOk(), json(401, {}), loginOk(), json(401, {}));

  await assert.rejects(() => nuevoCliente(fetchFn).servicios(), ApiAuthError);
  assert.equal(llamadas.length, 4);
});

test('credenciales del bot incorrectas (401 en el login) lanzan ApiAuthError', async () => {
  const { fetchFn } = fetchFalso(json(401, { message: 'Credenciales incorrectas.' }));

  await assert.rejects(() => nuevoCliente(fetchFn).servicios(), (error) => {
    assert.ok(error instanceof ApiAuthError);
    assert.match(error.message, /401/);
    return true;
  });
});

test('una cuenta que no es admin (403 en el login) lanza ApiAuthError', async () => {
  const { fetchFn } = fetchFalso(json(403, { message: 'Esta cuenta no tiene acceso a la API.' }));

  await assert.rejects(() => nuevoCliente(fetchFn).servicios(), ApiAuthError);
});

// ------------------------------------------------------------- 422: una regla de negocio dijo que no

test('un 422 se convierte en ApiRechazo con el mensaje de la API', async () => {
  const { fetchFn } = fetchFalso(
    loginOk(),
    json(422, { message: 'El barbero no está disponible en ese horario (ya tiene un turno de 10:00 a 10:45).' }),
  );

  await assert.rejects(
    () => nuevoCliente(fetchFn).crearTurno({ telefono: '573001234567', barberoId: 1, fecha: '2026-11-03', horaInicio: '10:00', servicioIds: [1] }),
    (error) => {
      assert.ok(error instanceof ApiRechazo);
      assert.match(error.message, /El barbero no está disponible en ese horario/);
      return true;
    },
  );
});

test('un 422 de validación conserva el detalle por campo', async () => {
  const { fetchFn } = fetchFalso(loginOk(), json(422, { message: 'invalid', errors: { fecha: ['La fecha no es válida.'] } }));

  await assert.rejects(() => nuevoCliente(fetchFn).turnos('123'), (error) => {
    assert.deepEqual(error.errores, { fecha: ['La fecha no es válida.'] });
    return true;
  });
});

test('un 404 se convierte en ApiNoEncontrado', async () => {
  const { fetchFn } = fetchFalso(loginOk(), json(404, { message: 'El recurso solicitado no existe.' }));

  await assert.rejects(() => nuevoCliente(fetchFn).cancelarTurno(999), ApiNoEncontrado);
});

// ------------------------------------------------------------- sin conexión

test('sin conexión lanza ApiNoDisponible', async () => {
  const { fetchFn } = fetchFalso(new TypeError('fetch failed'));

  await assert.rejects(() => nuevoCliente(fetchFn).servicios(), (error) => {
    assert.ok(error instanceof ApiNoDisponible);
    assert.match(error.message, /sin conexión/);
    return true;
  });
});

test('si la API tarda demasiado (Render dormido) lanza ApiNoDisponible por tiempo agotado', async () => {
  const { fetchFn } = fetchFalso(loginOk(), new DOMException('timeout', 'TimeoutError'));

  await assert.rejects(() => nuevoCliente(fetchFn).servicios(), (error) => {
    assert.ok(error instanceof ApiNoDisponible);
    assert.match(error.message, /tiempo de espera/);
    return true;
  });
});

test('un error 500 del servidor lanza ApiNoDisponible', async () => {
  const { fetchFn } = fetchFalso(loginOk(), json(500, { message: 'Server Error' }));

  await assert.rejects(() => nuevoCliente(fetchFn).servicios(), ApiNoDisponible);
});

test('una página HTML de error (proxy) no rompe: se trata como API no disponible', async () => {
  const { fetchFn } = fetchFalso(loginOk(), new Response('<html>502 Bad Gateway</html>', { status: 502 }));

  await assert.rejects(() => nuevoCliente(fetchFn).servicios(), ApiNoDisponible);
});

test('si el login responde 500 o 429 es ApiNoDisponible, no un problema de credenciales', async () => {
  for (const estado of [500, 429]) {
    const { fetchFn } = fetchFalso(json(estado, {}));
    await assert.rejects(() => nuevoCliente(fetchFn).servicios(), ApiNoDisponible, `estado ${estado}`);
  }
});

test('después de un fallo de conexión el siguiente intento sí funciona', async () => {
  const { fetchFn } = fetchFalso(loginOk(), new TypeError('fetch failed'), json(200, { data: ['ok'] }));
  const api = nuevoCliente(fetchFn);

  await assert.rejects(() => api.servicios(), ApiNoDisponible);
  assert.deepEqual(await api.servicios(), ['ok']);
});

// ------------------------------------------------------------- arranque en frío de Render (reintentos)

test('si el backend está despertando (sin conexión) el login se reintenta y la llamada sale bien', async () => {
  const { fetchFn, llamadas } = fetchFalso(new TypeError('fetch failed'), new TypeError('fetch failed'), loginOk(), json(200, { data: ['ok'] }));

  assert.deepEqual(await clienteConReintentos(fetchFn).servicios(), ['ok']);
  assert.equal(llamadas.filter((l) => l.url.pathname === '/api/login').length, 3);   // 2 fallidos + 1 bueno
});

test('un 503 del proxy de Render mientras despierta se reintenta en las lecturas', async () => {
  const { fetchFn, llamadas } = fetchFalso(loginOk(), json(503, {}), json(502, {}), json(200, { data: [1, 2] }));

  assert.deepEqual(await clienteConReintentos(fetchFn).servicios(), [1, 2]);
  assert.equal(llamadas.length, 4);
});

test('un tiempo de espera agotado también se reintenta', async () => {
  const { fetchFn } = fetchFalso(new DOMException('timeout', 'TimeoutError'), loginOk(), json(200, { data: ['ok'] }));

  assert.deepEqual(await clienteConReintentos(fetchFn).servicios(), ['ok']);
});

test('si tras los reintentos sigue sin responder, falla con ApiNoDisponible (3 intentos en total)', async () => {
  const { fetchFn, llamadas } = fetchFalso(new TypeError('x'), new TypeError('x'), new TypeError('x'));

  await assert.rejects(() => clienteConReintentos(fetchFn).servicios(), ApiNoDisponible);
  assert.equal(llamadas.length, 3);
});

test('las escrituras NO se repiten solas: un fallo al reservar se informa de inmediato', async () => {
  const { fetchFn, llamadas } = fetchFalso(loginOk(), json(503, {}), json(201, { data: { id: 1 } }));
  const datos = { telefono: '573001234567', nombre: 'A', barberoId: 1, fecha: '2026-11-03', horaInicio: '10:00', servicioIds: [1] };

  await assert.rejects(() => clienteConReintentos(fetchFn).crearTurno(datos), ApiNoDisponible);
  assert.equal(llamadas.filter((l) => l.metodo === 'POST' && l.url.pathname === '/api/turnos').length, 1);   // un solo intento

  const cancelar = fetchFalso(loginOk(), json(503, {}), new Response(null, { status: 204 }));
  await assert.rejects(() => clienteConReintentos(cancelar.fetchFn).cancelarTurno(5), ApiNoDisponible);
  assert.equal(cancelar.llamadas.filter((l) => l.metodo === 'DELETE').length, 1);
});

test('los reintentos no esconden las respuestas definitivas: 401, 404 y 422 se informan sin repetir', async () => {
  const rechazo = fetchFalso(loginOk(), json(422, { message: 'No' }));
  await assert.rejects(() => clienteConReintentos(rechazo.fetchFn).turnos('573001234567'), ApiRechazo);
  assert.equal(rechazo.llamadas.length, 2);   // login + 1 llamada, sin repeticiones

  const noExiste = fetchFalso(loginOk(), json(404, {}));
  await assert.rejects(() => clienteConReintentos(noExiste.fetchFn).turnos('573001234567'), ApiNoEncontrado);
  assert.equal(noExiste.llamadas.length, 2);

  const credenciales = fetchFalso(json(401, { message: 'Credenciales incorrectas.' }));
  await assert.rejects(() => clienteConReintentos(credenciales.fetchFn).servicios(), ApiAuthError);
  assert.equal(credenciales.llamadas.length, 1);   // un login mal NO se reintenta: no tiene sentido repetirlo
});

test('esperar entre reintentos usa las pausas configuradas', async () => {
  const { fetchFn } = fetchFalso(new TypeError('x'), loginOk(), json(200, { data: [] }));
  const api = crearClienteApi({ baseUrl: 'https://corteya.example', email: 'a@b.c', password: 'x', fetchFn, esperasReintentoMs: [60] });

  const inicio = Date.now();
  await api.servicios();

  assert.ok(Date.now() - inicio >= 50, 'debió esperar la pausa antes de reintentar');
});
