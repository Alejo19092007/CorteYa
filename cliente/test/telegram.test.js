import assert from 'node:assert/strict';
import test from 'node:test';
import { crearApp } from '../src/app.js';
import { crearCola } from '../src/cola.js';
import { crearAdaptadorTelegram, normalizarCelularColombiano } from '../src/telegram/adaptador.js';
import { aHtml, crearTelegram, TelegramError } from '../src/telegram/botApi.js';
import { iniciarPolling } from '../src/telegram/polling.js';

const silencio = { warn() {}, error() {} };
const CHAT = 4242;
const USUARIO = 4242; // en un chat privado el id del usuario es el del chat

// ------------------------------------------------------------------- utilidades

/** Telegram falso: guarda lo que el bot le manda. */
function telegramFalso() {
  const enviados = [];
  return {
    enviados,
    enviarTexto: async (chatId, texto) => { enviados.push({ tipo: 'texto', chatId, texto }); },
    pedirContacto: async (chatId, texto) => { enviados.push({ tipo: 'pedirContacto', chatId, texto }); },
    quitarTeclado: async (chatId, texto) => { enviados.push({ tipo: 'quitarTeclado', chatId, texto }); },
  };
}

const privado = (extra) => ({ update_id: 1, message: { message_id: 1, chat: { id: CHAT, type: 'private' }, from: { id: USUARIO, first_name: 'Daniela', last_name: 'Silva' }, ...extra } });
const texto = (t) => privado({ text: t });
const contacto = (telefono, userId = USUARIO) => privado({ contact: { phone_number: telefono, user_id: userId } });

function nuevoAdaptador() {
  const telegram = telegramFalso();
  const recibidos = [];
  const adaptador = crearAdaptadorTelegram({
    telegram,
    procesar: async (m) => { recibidos.push(m); return [`eco: ${m.texto}`]; },
  });
  return { adaptador, telegram, recibidos };
}

// ------------------------------------------------------------------- teléfono

test('normalizarCelularColombiano acepta formatos habituales y rechaza lo demás', () => {
  assert.equal(normalizarCelularColombiano('573001234567'), '573001234567');
  assert.equal(normalizarCelularColombiano('+57 300 123 4567'), '573001234567');
  assert.equal(normalizarCelularColombiano('3001234567'), '573001234567');
  assert.equal(normalizarCelularColombiano('+1 415 555 0100'), null);    // no es de Colombia
  assert.equal(normalizarCelularColombiano('5716012345'), null);         // fijo, no celular
  assert.equal(normalizarCelularColombiano(''), null);
  assert.equal(normalizarCelularColombiano(undefined), null);
});

// ------------------------------------------------------------------- adaptador

test('sin número registrado el bot pide compartirlo y NO procesa el mensaje', async () => {
  const { adaptador, telegram, recibidos } = nuevoAdaptador();

  await adaptador.manejarActualizacion(texto('hola'));

  assert.equal(recibidos.length, 0);
  assert.equal(telegram.enviados.length, 1);
  assert.equal(telegram.enviados[0].tipo, 'pedirContacto');
  assert.equal(telegram.enviados[0].chatId, CHAT);
});

test('al compartir su propio número queda registrado, se agradece y se muestra el menú', async () => {
  const { adaptador, telegram, recibidos } = nuevoAdaptador();

  await adaptador.manejarActualizacion(contacto('+57 300 123 4567'));

  assert.deepEqual(recibidos, [{ telefono: '573001234567', nombre: 'Daniela Silva', texto: 'menu' }]);
  assert.deepEqual(telegram.enviados.map((e) => e.tipo), ['quitarTeclado', 'texto']);
  assert.equal(adaptador.chatsVerificados, 1);
});

test('después de registrarse, los mensajes llegan al bot con el teléfono verificado', async () => {
  const { adaptador, telegram, recibidos } = nuevoAdaptador();
  await adaptador.manejarActualizacion(contacto('573001234567'));
  recibidos.length = 0;
  telegram.enviados.length = 0;

  await adaptador.manejarActualizacion(texto('2'));

  assert.deepEqual(recibidos, [{ telefono: '573001234567', nombre: 'Daniela Silva', texto: '2' }]);
  assert.deepEqual(telegram.enviados, [{ tipo: 'texto', chatId: CHAT, texto: 'eco: 2' }]);
});

test('SEGURIDAD: un contacto de otra persona no se acepta como número del usuario', async () => {
  const { adaptador, telegram, recibidos } = nuevoAdaptador();

  // El usuario reenvía la tarjeta de contacto de un tercero: su user_id no coincide con el de quien escribe
  await adaptador.manejarActualizacion(contacto('573009998877', 999999));

  assert.equal(recibidos.length, 0);
  assert.equal(adaptador.chatsVerificados, 0);
  assert.equal(telegram.enviados[0].tipo, 'pedirContacto');
  assert.match(telegram.enviados[0].texto, /no es el tuyo/);
});

test('un contacto sin user_id (tarjeta escrita a mano) tampoco se acepta', async () => {
  const { adaptador, recibidos } = nuevoAdaptador();

  // Se arma a mano: con el ayudante `contacto(...)` un `undefined` se reemplazaría por el user_id por defecto
  await adaptador.manejarActualizacion(privado({ contact: { phone_number: '573009998877', first_name: 'Otro' } }));

  assert.equal(recibidos.length, 0);
  assert.equal(adaptador.chatsVerificados, 0);
});

test('un número que no es de Colombia se rechaza con una explicación', async () => {
  const { adaptador, telegram, recibidos } = nuevoAdaptador();

  await adaptador.manejarActualizacion(contacto('+14155550100'));

  assert.equal(recibidos.length, 0);
  assert.match(telegram.enviados[0].texto, /celulares de Colombia/);
});

test('/start y /menu (incluso con @NombreDelBot) se tratan como "menu"', async () => {
  const { adaptador, recibidos } = nuevoAdaptador();
  await adaptador.manejarActualizacion(contacto('573001234567'));
  recibidos.length = 0;

  for (const comando of ['/start', '/menu', '/menu@CorteYaBot', '/START']) await adaptador.manejarActualizacion(texto(comando));

  assert.deepEqual(recibidos.map((m) => m.texto), ['menu', 'menu', 'menu', 'menu']);
});

test('un comando desconocido se deja tal cual para que el bot responda que no lo entiende', async () => {
  const { adaptador, recibidos } = nuevoAdaptador();
  await adaptador.manejarActualizacion(contacto('573001234567'));
  recibidos.length = 0;

  await adaptador.manejarActualizacion(texto('/borrar'));

  assert.equal(recibidos[0].texto, '/borrar');
});

test('una foto o un sticker llegan con texto nulo', async () => {
  const { adaptador, recibidos } = nuevoAdaptador();
  await adaptador.manejarActualizacion(contacto('573001234567'));
  recibidos.length = 0;

  await adaptador.manejarActualizacion(privado({ photo: [{ file_id: 'x' }] }));

  assert.equal(recibidos[0].texto, null);
});

test('los grupos y las actualizaciones sin mensaje se ignoran', async () => {
  const { adaptador, telegram, recibidos } = nuevoAdaptador();

  await adaptador.manejarActualizacion({ update_id: 2, message: { chat: { id: -100, type: 'group' }, from: { id: 1 }, text: 'hola' } });
  await adaptador.manejarActualizacion({ update_id: 3, edited_message: {} });
  await adaptador.manejarActualizacion({});
  await adaptador.manejarActualizacion(undefined);

  assert.equal(recibidos.length, 0);
  assert.equal(telegram.enviados.length, 0);
});

test('cada chat tiene su propio teléfono', async () => {
  const { adaptador, recibidos } = nuevoAdaptador();
  const deOtro = (extra) => ({ update_id: 9, message: { chat: { id: 7, type: 'private' }, from: { id: 7, first_name: 'Luis' }, ...extra } });

  await adaptador.manejarActualizacion(contacto('573001111111'));
  await adaptador.manejarActualizacion(deOtro({ contact: { phone_number: '573002222222', user_id: 7 } }));
  recibidos.length = 0;

  await adaptador.manejarActualizacion(texto('hola'));
  await adaptador.manejarActualizacion(deOtro({ text: 'hola' }));

  assert.deepEqual(recibidos.map((m) => m.telefono), ['573001111111', '573002222222']);
});

// ------------------------------------------------------------------- Bot API

test('aHtml escapa los datos y convierte *negrita* a <b>', () => {
  assert.equal(aHtml('Hola *Daniela*'), 'Hola <b>Daniela</b>');
  assert.equal(aHtml('Barbero: <script>alert(1)</script> & co'), 'Barbero: &lt;script&gt;alert(1)&lt;/script&gt; &amp; co');
  assert.equal(aHtml('*Corte* + *Barba*'), '<b>Corte</b> + <b>Barba</b>');
  assert.equal(aHtml('2 * 3'), '2 * 3');                       // un asterisco suelto no rompe nada
  assert.equal(aHtml('*<b>x</b>*'), '<b>&lt;b&gt;x&lt;/b&gt;</b>'); // el HTML de los datos nunca se interpreta
});

test('enviarTexto llama a sendMessage con el chat, el texto en HTML y el teclado pedido', async () => {
  let recibido;
  const telegram = crearTelegram({
    token: '123:SECRETO',
    fetchFn: async (url, opciones) => { recibido = { url, cuerpo: JSON.parse(opciones.body) }; return Response.json({ ok: true, result: {} }); },
  });

  await telegram.enviarTexto(55, 'Hola *tú*');

  assert.equal(recibido.url, 'https://api.telegram.org/bot123:SECRETO/sendMessage');
  assert.equal(recibido.cuerpo.chat_id, 55);
  assert.equal(recibido.cuerpo.text, 'Hola <b>tú</b>');
  assert.equal(recibido.cuerpo.parse_mode, 'HTML');
});

test('pedirContacto muestra un botón con request_contact y quitarTeclado lo oculta', async () => {
  const cuerpos = [];
  const telegram = crearTelegram({
    token: 't',
    fetchFn: async (_url, opciones) => { cuerpos.push(JSON.parse(opciones.body)); return Response.json({ ok: true, result: {} }); },
  });

  await telegram.pedirContacto(1, 'comparte');
  await telegram.quitarTeclado(1, 'gracias');

  assert.equal(cuerpos[0].reply_markup.keyboard[0][0].request_contact, true);
  assert.equal(cuerpos[0].reply_markup.one_time_keyboard, true);
  assert.deepEqual(cuerpos[1].reply_markup, { remove_keyboard: true });
});

test('configurarWebhook manda la URL y el secreto', async () => {
  let cuerpo;
  const telegram = crearTelegram({
    token: 't',
    fetchFn: async (_url, opciones) => { cuerpo = JSON.parse(opciones.body); return Response.json({ ok: true, result: true }); },
  });

  await telegram.configurarWebhook('https://bot.example/telegram/webhook', 'secreto_largo-0123456');

  assert.equal(cuerpo.url, 'https://bot.example/telegram/webhook');
  assert.equal(cuerpo.secret_token, 'secreto_largo-0123456');
  assert.deepEqual(cuerpo.allowed_updates, ['message']);
});

test('los errores de Telegram explican el motivo y NUNCA incluyen el token', async () => {
  const rechazo = crearTelegram({
    token: '123:SECRETO',
    fetchFn: async () => Response.json({ ok: false, description: 'Unauthorized' }, { status: 401 }),
  });
  const caido = crearTelegram({ token: '123:SECRETO', fetchFn: async () => { throw new TypeError('fetch failed'); } });

  await assert.rejects(() => rechazo.enviarTexto(1, 'x'), (error) => {
    assert.ok(error instanceof TelegramError);
    assert.equal(error.estado, 401);
    assert.match(error.message, /Unauthorized/);
    assert.doesNotMatch(error.message, /SECRETO/);
    return true;
  });
  await assert.rejects(() => caido.enviarTexto(1, 'x'), (error) => {
    assert.match(error.message, /sin conexión/);
    assert.doesNotMatch(error.message, /SECRETO/);
    return true;
  });
});

// ------------------------------------------------------------------- polling

test('el polling quita el webhook, entrega cada mensaje y avanza el offset para no repetirlos', async () => {
  const llamadas = [];
  const atendidas = [];
  const lotes = [
    [{ update_id: 10, message: { chat: { id: 1 } } }, { update_id: 11, message: { chat: { id: 2 } } }],
    [{ update_id: 12, message: { chat: { id: 1 } } }],
  ];
  const cola = crearCola(silencio);
  let polling;

  const telegram = {
    quitarWebhook: async () => { llamadas.push('quitarWebhook'); },
    obtenerActualizaciones: async (offset) => {
      llamadas.push(`getUpdates:${offset}`);
      const lote = lotes.shift();
      if (!lote) { polling.detener(); return []; }
      return lote;
    },
  };

  polling = iniciarPolling({ telegram, atender: async (a) => { atendidas.push(a.update_id); }, cola, registrar: silencio });
  await polling.terminado;
  await cola.esperar();

  assert.deepEqual(llamadas, ['quitarWebhook', 'getUpdates:undefined', 'getUpdates:12', 'getUpdates:13']);
  assert.deepEqual(atendidas.sort(), [10, 11, 12]);
});

test('si Telegram falla el polling reintenta sin caerse', async () => {
  let intentos = 0;
  let polling;
  const telegram = {
    quitarWebhook: async () => {},
    obtenerActualizaciones: async () => {
      intentos += 1;
      if (intentos === 1) throw new TelegramError(0, 'sin conexión con Telegram');
      polling.detener();
      return [];
    },
  };

  polling = iniciarPolling({ telegram, atender: async () => {}, cola: crearCola(silencio), registrar: silencio, pausaErrorMs: 5 });
  await polling.terminado;

  assert.equal(intentos, 2);
});

// ------------------------------------------------------------------- webhook de Telegram

const SECRETO = 'secreto_largo-0123456';

async function iniciarServidor() {
  const atendidas = [];
  const cola = crearCola(silencio);
  const app = crearApp({
    config: { telegram: { webhookSecret: SECRETO }, whatsapp: null, simulador: false },
    procesar: async () => [],
    telegram: { manejarActualizacion: async (a) => { atendidas.push(a); } },
    cola,
    registrar: silencio,
  });
  const servidor = await new Promise((resolver) => { const s = app.listen(0, () => resolver(s)); });

  return {
    atendidas,
    base: `http://127.0.0.1:${servidor.address().port}`,
    esperar: () => cola.esperar(),
    cerrar: () => new Promise((resolver) => servidor.close(resolver)),
  };
}

const enviarWebhook = (base, cuerpo, secreto) => fetch(`${base}/telegram/webhook`, {
  method: 'POST',
  headers: { 'Content-Type': 'application/json', ...(secreto ? { 'X-Telegram-Bot-Api-Secret-Token': secreto } : {}) },
  body: JSON.stringify(cuerpo),
});

test('el webhook acepta mensajes con el secreto correcto y los atiende', async () => {
  const s = await iniciarServidor();
  try {
    assert.equal((await enviarWebhook(s.base, texto('hola'), SECRETO)).status, 200);
    await s.esperar();

    assert.equal(s.atendidas.length, 1);
    assert.equal(s.atendidas[0].message.text, 'hola');
  } finally { await s.cerrar(); }
});

test('SEGURIDAD: sin secreto o con uno falso el webhook responde 401 y no procesa nada', async () => {
  const s = await iniciarServidor();
  try {
    assert.equal((await enviarWebhook(s.base, texto('hola'))).status, 401);
    assert.equal((await enviarWebhook(s.base, texto('hola'), 'otro_secreto_cualquiera')).status, 401);
    await s.esperar();

    assert.equal(s.atendidas.length, 0);
  } finally { await s.cerrar(); }
});

test('Telegram reintenta la misma actualización: solo se atiende una vez', async () => {
  const s = await iniciarServidor();
  try {
    await enviarWebhook(s.base, texto('hola'), SECRETO);
    await enviarWebhook(s.base, texto('hola'), SECRETO);
    await s.esperar();

    assert.equal(s.atendidas.length, 1);
  } finally { await s.cerrar(); }
});

test('una actualización sin mensaje responde 200 y se ignora', async () => {
  const s = await iniciarServidor();
  try {
    assert.equal((await enviarWebhook(s.base, { update_id: 99, edited_message: {} }, SECRETO)).status, 200);
    await s.esperar();

    assert.equal(s.atendidas.length, 0);
  } finally { await s.cerrar(); }
});

test('si el adaptador falla el servidor sigue vivo', async () => {
  const cola = crearCola(silencio);
  const app = crearApp({
    config: { telegram: { webhookSecret: SECRETO }, whatsapp: null, simulador: false },
    procesar: async () => [],
    telegram: { manejarActualizacion: async () => { throw new Error('Telegram caído'); } },
    cola,
    registrar: silencio,
  });
  const servidor = await new Promise((resolver) => { const s = app.listen(0, () => resolver(s)); });
  const base = `http://127.0.0.1:${servidor.address().port}`;

  try {
    assert.equal((await enviarWebhook(base, texto('hola'), SECRETO)).status, 200);
    await cola.esperar();
    assert.equal((await fetch(`${base}/health`)).status, 200);
  } finally { await new Promise((resolver) => servidor.close(resolver)); }
});

test('el webhook de WhatsApp no existe cuando el canal es Telegram', async () => {
  const s = await iniciarServidor();
  try {
    assert.equal((await fetch(`${s.base}/webhook?hub.mode=subscribe`)).status, 404);
  } finally { await s.cerrar(); }
});
