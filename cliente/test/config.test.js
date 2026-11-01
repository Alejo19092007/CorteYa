import assert from 'node:assert/strict';
import test from 'node:test';
import { cargarConfig } from '../src/config.js';
import { crearEnviador, EnvioWhatsAppError } from '../src/whatsapp/cloudApi.js';

// Base del canal WhatsApp (el canal por defecto es Telegram: ver las pruebas de Telegram más abajo)
const base = {
  CANAL: 'whatsapp',
  CORTEYA_API_URL: 'https://corteya.onrender.com/',
  BOT_EMAIL: 'bot@corteya.com',
  BOT_PASSWORD: 'clave',
  WHATSAPP_TOKEN: 'EAAtoken',
  WHATSAPP_PHONE_NUMBER_ID: '123456',
  WHATSAPP_VERIFY_TOKEN: 'verificador',
};

test('una configuración completa se carga y quita la barra final de la URL', () => {
  const config = cargarConfig(base);

  assert.equal(config.apiUrl, 'https://corteya.onrender.com');
  assert.equal(config.botEmail, 'bot@corteya.com');
  assert.equal(config.puerto, 3000);
  assert.equal(config.simulador, false);
  assert.equal(config.whatsapp.verifyToken, 'verificador');
  assert.equal(config.whatsapp.graphVersion, 'v22.0');
});

test('si falta una variable obligatoria el arranque falla diciendo cuál', () => {
  for (const variable of ['CORTEYA_API_URL', 'BOT_EMAIL', 'BOT_PASSWORD', 'WHATSAPP_TOKEN', 'WHATSAPP_PHONE_NUMBER_ID', 'WHATSAPP_VERIFY_TOKEN']) {
    const env = { ...base };
    delete env[variable];
    assert.throws(() => cargarConfig(env), new RegExp(variable), variable);
  }
});

test('la rúbrica exige URL pública: localhost y http se rechazan fuera del simulador', () => {
  assert.throws(() => cargarConfig({ ...base, CORTEYA_API_URL: 'http://localhost:8000' }), /URL pública/);
  assert.throws(() => cargarConfig({ ...base, CORTEYA_API_URL: 'https://127.0.0.1' }), /URL pública/);
  assert.throws(() => cargarConfig({ ...base, CORTEYA_API_URL: 'http://corteya.onrender.com' }), /https/);
});

test('el simulador permite apuntar a un backend local y no exige datos de Meta', () => {
  const config = cargarConfig({
    CORTEYA_API_URL: 'http://127.0.0.1:8000', BOT_EMAIL: 'a@b.c', BOT_PASSWORD: 'x', MODO_SIMULADOR: 'true',
  });

  assert.equal(config.simulador, true);
  assert.equal(config.whatsapp, null);
});

test('el simulador no se puede activar en producción', () => {
  assert.throws(
    () => cargarConfig({ ...base, MODO_SIMULADOR: 'true', NODE_ENV: 'production' }),
    /MODO_SIMULADOR/,
  );
});

test('en producción el secreto de la app es obligatorio (verifica la firma de Meta)', () => {
  assert.throws(() => cargarConfig({ ...base, NODE_ENV: 'production' }), /WHATSAPP_APP_SECRET/);

  const config = cargarConfig({ ...base, NODE_ENV: 'production', WHATSAPP_APP_SECRET: 's' });
  assert.equal(config.whatsapp.appSecret, 's');
});

// ------------------------------------------------------------------- canal Telegram

const telegramBase = {
  CORTEYA_API_URL: 'https://corteya.onrender.com',
  BOT_EMAIL: 'bot@corteya.com',
  BOT_PASSWORD: 'clave',
  TELEGRAM_BOT_TOKEN: '123456:ABC-token',
};

test('Telegram es el canal por defecto y en desarrollo usa polling', () => {
  const config = cargarConfig(telegramBase);

  assert.equal(config.canal, 'telegram');
  assert.equal(config.telegram.modo, 'polling');
  assert.equal(config.whatsapp, null);
});

test('en producción Telegram usa webhook y exige secreto y URL pública', () => {
  const produccion = { ...telegramBase, NODE_ENV: 'production' };

  assert.throws(() => cargarConfig(produccion), /TELEGRAM_WEBHOOK_SECRET/);
  assert.throws(() => cargarConfig({ ...produccion, TELEGRAM_WEBHOOK_SECRET: 'x'.repeat(20) }), /BOT_PUBLIC_URL/);

  const config = cargarConfig({ ...produccion, TELEGRAM_WEBHOOK_SECRET: 'secreto_largo-0123456', BOT_PUBLIC_URL: 'https://corteya-bot.onrender.com/' });
  assert.equal(config.telegram.modo, 'webhook');
  assert.equal(config.telegram.urlPublica, 'https://corteya-bot.onrender.com');
});

test('la URL pública del bot puede venir de RENDER_EXTERNAL_URL', () => {
  const config = cargarConfig({
    ...telegramBase, NODE_ENV: 'production', TELEGRAM_WEBHOOK_SECRET: 'secreto_largo-0123456', RENDER_EXTERNAL_URL: 'https://corteya-bot.onrender.com',
  });

  assert.equal(config.telegram.urlPublica, 'https://corteya-bot.onrender.com');
});

test('el secreto del webhook solo admite los caracteres que acepta Telegram', () => {
  const env = { ...telegramBase, TELEGRAM_MODO: 'webhook', BOT_PUBLIC_URL: 'https://x.onrender.com' };

  assert.throws(() => cargarConfig({ ...env, TELEGRAM_WEBHOOK_SECRET: 'corto' }), /entre 16 y 256/);
  assert.throws(() => cargarConfig({ ...env, TELEGRAM_WEBHOOK_SECRET: 'con espacios y símbolos!!' }), /entre 16 y 256/);
});

test('el webhook exige https', () => {
  assert.throws(
    () => cargarConfig({ ...telegramBase, TELEGRAM_MODO: 'webhook', TELEGRAM_WEBHOOK_SECRET: 'secreto_largo-0123456', BOT_PUBLIC_URL: 'http://x.onrender.com' }),
    /https/,
  );
});

test('falta el token del bot de Telegram', () => {
  const env = { ...telegramBase };
  delete env.TELEGRAM_BOT_TOKEN;

  assert.throws(() => cargarConfig(env), /TELEGRAM_BOT_TOKEN/);
});

test('un canal o modo desconocido se rechaza', () => {
  assert.throws(() => cargarConfig({ ...telegramBase, CANAL: 'signal' }), /CANAL/);
  assert.throws(() => cargarConfig({ ...telegramBase, TELEGRAM_MODO: 'magia' }), /TELEGRAM_MODO/);
});

// ------------------------------------------------------------------- envío a WhatsApp

test('enviarTexto manda el mensaje a la Graph API con el token en la cabecera', async () => {
  let recibido;
  const enviador = crearEnviador({
    token: 'EAAsecreto', phoneNumberId: '999', graphVersion: 'v22.0',
    fetchFn: async (url, opciones) => { recibido = { url, opciones }; return new Response('{}', { status: 200 }); },
  });

  await enviador.enviarTexto('573001234567', 'Hola');

  assert.equal(recibido.url, 'https://graph.facebook.com/v22.0/999/messages');
  assert.equal(recibido.opciones.headers.Authorization, 'Bearer EAAsecreto');
  assert.deepEqual(JSON.parse(recibido.opciones.body), {
    messaging_product: 'whatsapp', to: '573001234567', type: 'text', text: { body: 'Hola', preview_url: false },
  });
});

test('si Meta rechaza el envío el error explica el motivo pero nunca incluye el token', async () => {
  const enviador = crearEnviador({
    token: 'EAAsecreto', phoneNumberId: '999',
    fetchFn: async () => new Response(JSON.stringify({ error: { message: 'Error validating access token' } }), { status: 401 }),
  });

  await assert.rejects(() => enviador.enviarTexto('57300', 'x'), (error) => {
    assert.ok(error instanceof EnvioWhatsAppError);
    assert.equal(error.estado, 401);
    assert.match(error.message, /Error validating access token/);
    assert.doesNotMatch(error.message, /EAAsecreto/);
    return true;
  });
});

test('sin conexión con Meta lanza EnvioWhatsAppError', async () => {
  const enviador = crearEnviador({ token: 't', phoneNumberId: '1', fetchFn: async () => { throw new TypeError('fetch failed'); } });

  await assert.rejects(() => enviador.enviarTexto('57300', 'x'), EnvioWhatsAppError);
});
