import assert from 'node:assert/strict';
import crypto from 'node:crypto';
import test from 'node:test';
import { crearApp, extraerMensajes } from '../src/app.js';

const silencio = { warn() {}, error() {} };
const SECRETO = 'secreto-de-la-app';

/** Levanta el servidor en un puerto libre y devuelve helpers para llamarlo. */
async function iniciar({ whatsapp = { verifyToken: 'verificador', appSecret: null }, simulador = false, procesar, enviarTexto } = {}) {
  const procesados = [];
  const enviados = [];

  const app = crearApp({
    config: { whatsapp, simulador },
    procesar: procesar ?? (async (mensaje) => { procesados.push(mensaje); return [`eco: ${mensaje.texto}`]; }),
    enviarTexto: enviarTexto ?? (async (para, texto) => { enviados.push({ para, texto }); }),
    registrar: silencio,
  });

  const servidor = await new Promise((resolver) => { const s = app.listen(0, () => resolver(s)); });
  const base = `http://127.0.0.1:${servidor.address().port}`;

  return {
    base, procesados, enviados, app,
    cerrar: () => new Promise((resolver) => servidor.close(resolver)),
    esperar: () => app.locals.esperarPendientes(),
  };
}

const mensajeMeta = (id, texto, { de = '573001234567', nombre = 'Daniela Silva', tipo = 'text' } = {}) => ({
  object: 'whatsapp_business_account',
  entry: [{
    changes: [{
      value: {
        contacts: [{ wa_id: de, profile: { name: nombre } }],
        messages: [{ id, from: de, type: tipo, ...(tipo === 'text' ? { text: { body: texto } } : {}) }],
      },
    }],
  }],
});

const firmar = (cuerpoTexto) => `sha256=${crypto.createHmac('sha256', SECRETO).update(cuerpoTexto).digest('hex')}`;

const publicar = (base, cuerpo, cabeceras = {}) => fetch(`${base}/webhook`, {
  method: 'POST',
  headers: { 'Content-Type': 'application/json', ...cabeceras },
  body: typeof cuerpo === 'string' ? cuerpo : JSON.stringify(cuerpo),
});

// ------------------------------------------------------------------- salud y verificación

test('/health responde ok sin depender de la API', async () => {
  const s = await iniciar();
  try {
    const respuesta = await fetch(`${s.base}/health`);
    assert.equal(respuesta.status, 200);
    assert.deepEqual(await respuesta.json(), { ok: true });
  } finally { await s.cerrar(); }
});

test('la verificación del webhook devuelve el challenge solo con el token correcto', async () => {
  const s = await iniciar();
  try {
    const bien = await fetch(`${s.base}/webhook?hub.mode=subscribe&hub.verify_token=verificador&hub.challenge=12345`);
    assert.equal(bien.status, 200);
    assert.equal(await bien.text(), '12345');

    for (const consulta of [
      'hub.mode=subscribe&hub.verify_token=otro&hub.challenge=1',
      'hub.mode=subscribe&hub.challenge=1',
      'hub.mode=unsubscribe&hub.verify_token=verificador&hub.challenge=1',
      '',
    ]) {
      assert.equal((await fetch(`${s.base}/webhook?${consulta}`)).status, 403, consulta);
    }
  } finally { await s.cerrar(); }
});

// ------------------------------------------------------------------- mensajes entrantes

test('un mensaje de texto se responde 200 y se procesa con teléfono, nombre y texto', async () => {
  const s = await iniciar();
  try {
    const respuesta = await publicar(s.base, mensajeMeta('wamid.1', 'hola'));
    assert.equal(respuesta.status, 200);

    await s.esperar();
    assert.deepEqual(s.procesados, [{ id: 'wamid.1', telefono: '573001234567', nombre: 'Daniela Silva', texto: 'hola' }]);
    assert.deepEqual(s.enviados, [{ para: '573001234567', texto: 'eco: hola' }]);
  } finally { await s.cerrar(); }
});

test('si el bot responde varios mensajes se envían todos, en orden', async () => {
  const s = await iniciar({ procesar: async () => ['uno', 'dos', 'tres'] });
  try {
    await publicar(s.base, mensajeMeta('wamid.2', 'hola'));
    await s.esperar();

    assert.deepEqual(s.enviados.map((e) => e.texto), ['uno', 'dos', 'tres']);
  } finally { await s.cerrar(); }
});

test('Meta reintenta el mismo mensaje: solo se procesa una vez', async () => {
  const s = await iniciar();
  try {
    await publicar(s.base, mensajeMeta('wamid.3', 'hola'));
    await publicar(s.base, mensajeMeta('wamid.3', 'hola'));
    await s.esperar();

    assert.equal(s.procesados.length, 1);
  } finally { await s.cerrar(); }
});

test('las notificaciones de estado (entregado, leído) se ignoran', async () => {
  const s = await iniciar();
  try {
    const estados = {
      object: 'whatsapp_business_account',
      entry: [{ changes: [{ value: { statuses: [{ id: 'wamid.x', status: 'delivered' }] } }] }],
    };
    assert.equal((await publicar(s.base, estados)).status, 200);
    await s.esperar();

    assert.equal(s.procesados.length, 0);
  } finally { await s.cerrar(); }
});

test('un mensaje que no es texto llega con texto nulo para que el bot lo explique', async () => {
  const s = await iniciar();
  try {
    await publicar(s.base, mensajeMeta('wamid.4', '', { tipo: 'image' }));
    await s.esperar();

    assert.equal(s.procesados[0].texto, null);
  } finally { await s.cerrar(); }
});

test('una carga que no es de WhatsApp se ignora sin error', async () => {
  const s = await iniciar();
  try {
    assert.equal((await publicar(s.base, { object: 'page', entry: [] })).status, 200);
    assert.equal((await publicar(s.base, {})).status, 200);
    await s.esperar();

    assert.equal(s.procesados.length, 0);
  } finally { await s.cerrar(); }
});

test('si falla el envío a WhatsApp no se cae el servidor y los siguientes mensajes se atienden', async () => {
  let intentos = 0;
  const atendidos = [];
  const s = await iniciar({
    procesar: async (m) => [`eco: ${m.texto}`],
    enviarTexto: async (para, texto) => {
      intentos += 1;
      if (intentos === 1) throw new Error('token vencido');
      atendidos.push(texto);
    },
  });
  try {
    await publicar(s.base, mensajeMeta('wamid.5', 'primero'));
    await s.esperar();
    await publicar(s.base, mensajeMeta('wamid.6', 'segundo'));
    await s.esperar();

    assert.deepEqual(atendidos, ['eco: segundo']);
    assert.equal((await fetch(`${s.base}/health`)).status, 200);
  } finally { await s.cerrar(); }
});

test('los mensajes seguidos de un mismo cliente se atienden en orden, aunque el primero tarde más', async () => {
  const orden = [];
  const s = await iniciar({
    procesar: async (m) => {
      if (m.texto === 'lento') await new Promise((r) => setTimeout(r, 60));
      orden.push(m.texto);
      return [];
    },
  });
  try {
    await publicar(s.base, mensajeMeta('wamid.7', 'lento'));
    await publicar(s.base, mensajeMeta('wamid.8', 'rapido'));
    await s.esperar();

    assert.deepEqual(orden, ['lento', 'rapido']);
  } finally { await s.cerrar(); }
});

test('clientes distintos no se bloquean entre sí', async () => {
  const orden = [];
  const s = await iniciar({
    procesar: async (m) => {
      if (m.telefono === '573001111111') await new Promise((r) => setTimeout(r, 80));
      orden.push(m.telefono);
      return [];
    },
  });
  try {
    await publicar(s.base, mensajeMeta('wamid.9', 'a', { de: '573001111111' }));
    await publicar(s.base, mensajeMeta('wamid.10', 'b', { de: '573002222222' }));
    await s.esperar();

    assert.deepEqual(orden, ['573002222222', '573001111111']);
  } finally { await s.cerrar(); }
});

// ------------------------------------------------------------------- firma de Meta

test('con secreto de la app, un mensaje sin firma o con firma falsa se rechaza con 401', async () => {
  const s = await iniciar({ whatsapp: { verifyToken: 'v', appSecret: SECRETO } });
  try {
    const cuerpo = JSON.stringify(mensajeMeta('wamid.11', 'hola'));

    assert.equal((await publicar(s.base, cuerpo)).status, 401);
    assert.equal((await publicar(s.base, cuerpo, { 'X-Hub-Signature-256': 'sha256=' + '0'.repeat(64) })).status, 401);
    assert.equal((await publicar(s.base, cuerpo, { 'X-Hub-Signature-256': 'basura' })).status, 401);

    await s.esperar();
    assert.equal(s.procesados.length, 0);
  } finally { await s.cerrar(); }
});

test('con secreto de la app, un mensaje con la firma correcta se procesa', async () => {
  const s = await iniciar({ whatsapp: { verifyToken: 'v', appSecret: SECRETO } });
  try {
    const cuerpo = JSON.stringify(mensajeMeta('wamid.12', 'hola'));

    assert.equal((await publicar(s.base, cuerpo, { 'X-Hub-Signature-256': firmar(cuerpo) })).status, 200);
    await s.esperar();

    assert.equal(s.procesados.length, 1);
  } finally { await s.cerrar(); }
});

test('la firma protege el contenido: alterar el cuerpo la invalida', async () => {
  const s = await iniciar({ whatsapp: { verifyToken: 'v', appSecret: SECRETO } });
  try {
    const original = JSON.stringify(mensajeMeta('wamid.13', 'hola'));
    const alterado = JSON.stringify(mensajeMeta('wamid.13', 'cancela todo'));

    assert.equal((await publicar(s.base, alterado, { 'X-Hub-Signature-256': firmar(original) })).status, 401);
  } finally { await s.cerrar(); }
});

// ------------------------------------------------------------------- simulador y errores

test('el simulador no existe cuando MODO_SIMULADOR está apagado', async () => {
  const s = await iniciar({ simulador: false });
  try {
    assert.equal((await fetch(`${s.base}/simulador`)).status, 404);
    const respuesta = await fetch(`${s.base}/simulador/mensaje`, {
      method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ texto: 'hola' }),
    });
    assert.equal(respuesta.status, 404);
    assert.equal(s.procesados.length, 0);
  } finally { await s.cerrar(); }
});

test('con el simulador encendido la página carga y devuelve las respuestas del bot', async () => {
  const s = await iniciar({ simulador: true });
  try {
    const pagina = await fetch(`${s.base}/simulador`);
    assert.equal(pagina.status, 200);
    assert.match(await pagina.text(), /Simulador del bot de CorteYa/);

    const respuesta = await fetch(`${s.base}/simulador/mensaje`, {
      method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ texto: 'hola', telefono: '573009998877' }),
    });
    assert.deepEqual(await respuesta.json(), { respuestas: ['eco: hola'] });
    assert.equal(s.procesados[0].telefono, '573009998877');
  } finally { await s.cerrar(); }
});

test('un JSON mal formado responde 400 sin revelar detalles internos', async () => {
  const s = await iniciar();
  try {
    const respuesta = await publicar(s.base, '{no es json');
    assert.equal(respuesta.status, 400);
    assert.deepEqual(await respuesta.json(), { mensaje: 'JSON inválido' });
  } finally { await s.cerrar(); }
});

test('una ruta inexistente responde 404 en JSON', async () => {
  const s = await iniciar();
  try {
    const respuesta = await fetch(`${s.base}/nada`);
    assert.equal(respuesta.status, 404);
    assert.deepEqual(await respuesta.json(), { mensaje: 'No encontrado' });
  } finally { await s.cerrar(); }
});

test('extraerMensajes lee varios mensajes y ninguno cuando no hay', () => {
  const doble = {
    object: 'whatsapp_business_account',
    entry: [{ changes: [{ value: { messages: [
      { id: 'a', from: '57300', type: 'text', text: { body: 'uno' } },
      { id: 'b', from: '57301', type: 'text', text: { body: 'dos' } },
    ] } }] }],
  };

  assert.deepEqual(extraerMensajes(doble).map((m) => m.texto), ['uno', 'dos']);
  assert.deepEqual(extraerMensajes(null), []);
  assert.deepEqual(extraerMensajes({ object: 'whatsapp_business_account' }), []);
});
