// Servidor Express del bot.
//
//   GET  /health               chequeo de salud (Render). No toca la API: así no la despierta.
//   POST /telegram/webhook     (canal Telegram, modo webhook) Telegram envía aquí cada mensaje del cliente.
//   GET  /webhook              (canal WhatsApp) Meta lo llama UNA vez al configurar el webhook.
//   POST /webhook              (canal WhatsApp) Meta envía aquí cada mensaje del cliente.
//   GET  /simulador            (solo con MODO_SIMULADOR=true) chat de prueba en el navegador, sin Telegram ni WhatsApp.
//   POST /simulador/mensaje    (solo con MODO_SIMULADOR=true) lo usa esa página.

import crypto from 'node:crypto';
import { readFileSync } from 'node:fs';
import express from 'express';
import { crearCola } from './cola.js';

const PAGINA_SIMULADOR = readFileSync(new URL('./simulador/pagina.html', import.meta.url), 'utf8');
const RETENCION_IDS_MS = 10 * 60 * 1000;

/** Compara dos textos en tiempo constante (evita adivinar un secreto por el tiempo de respuesta). */
function iguales(a, b) {
  const x = Buffer.from(String(a ?? ''));
  const y = Buffer.from(String(b ?? ''));
  return x.length === y.length && crypto.timingSafeEqual(x, y);
}

/** Saca de la carga de Meta los mensajes de los clientes: [{ id, telefono, nombre, texto }]. */
export function extraerMensajes(cuerpo) {
  if (cuerpo?.object !== 'whatsapp_business_account') return [];

  const mensajes = [];
  for (const entrada of cuerpo.entry ?? []) {
    for (const cambio of entrada.changes ?? []) {
      const valor = cambio.value ?? {};
      const nombres = new Map((valor.contacts ?? []).map((c) => [c.wa_id, c.profile?.name]));

      // "statuses" (entregado, leído...) también llegan por aquí y se ignoran: no traen "messages"
      for (const m of valor.messages ?? []) {
        mensajes.push({
          id: m.id,
          telefono: m.from,
          nombre: nombres.get(m.from) ?? null,
          texto: m.type === 'text' ? (m.text?.body ?? null) : null, // imágenes, audios... no se entienden
        });
      }
    }
  }
  return mensajes;
}

/**
 * @param {object} opciones
 * @param {object} opciones.config  { canal, whatsapp, telegram, simulador }
 * @param {(m: {telefono: string, nombre: string|null, texto: string|null}) => Promise<string[]>} opciones.procesar
 * @param {(para: string, texto: string) => Promise<void>} [opciones.enviarTexto]  canal WhatsApp
 * @param {{manejarActualizacion: (a: object) => Promise<void>}} [opciones.telegram]  canal Telegram
 * @param {ReturnType<typeof crearCola>} [opciones.cola]
 */
export function crearApp({ config, procesar, enviarTexto, telegram, cola = crearCola(), registrar = console }) {
  const app = express();
  app.disable('x-powered-by');

  // Se guarda el cuerpo SIN procesar: la firma de Meta se calcula sobre esos bytes exactos
  app.use(express.json({ limit: '1mb', verify: (req, _res, buf) => { req.rawBody = buf; } }));

  const vistos = new Map(); // id de mensaje -> cuándo llegó (Meta y Telegram reintentan si tardamos en responder 200)

  const enmascarar = (telefono) => `***${String(telefono).slice(-4)}`;

  function yaVisto(id) {
    const ahora = Date.now();
    for (const [clave, momento] of vistos) if (ahora - momento > RETENCION_IDS_MS) vistos.delete(clave);
    if (id && vistos.has(id)) return true;
    if (id) vistos.set(id, ahora);
    return false;
  }

  // ----------------------------------------------------------------- rutas

  app.get('/', (_req, res) => res.json({ servicio: 'corteya-bot', estado: 'ok' }));
  app.get('/health', (_req, res) => res.json({ ok: true }));

  // --- Telegram (webhook)
  if (config.telegram && telegram) {
    app.post('/telegram/webhook', (req, res) => {
      // Telegram repite en esta cabecera el secreto que le dimos al registrar el webhook
      if (!iguales(req.get('x-telegram-bot-api-secret-token'), config.telegram.webhookSecret)) return res.sendStatus(401);

      // Se responde 200 DE INMEDIATO y se procesa después: si tardamos, Telegram reintenta y llegarían duplicados
      res.sendStatus(200);

      const actualizacion = req.body ?? {};
      const chatId = actualizacion.message?.chat?.id;
      if (chatId === undefined || yaVisto(`tg:${actualizacion.update_id}`)) return;

      cola.encolar(`tg:${chatId}`, () => telegram.manejarActualizacion(actualizacion), 'un mensaje de Telegram');
    });
  }

  // --- WhatsApp (webhook de Meta)
  if (config.whatsapp && enviarTexto) {
    // Verificación del webhook: Meta envía hub.mode, hub.verify_token y hub.challenge
    app.get('/webhook', (req, res) => {
      const verifyToken = config.whatsapp.verifyToken;

      if (verifyToken && req.query['hub.mode'] === 'subscribe' && iguales(req.query['hub.verify_token'], verifyToken)) {
        return res.status(200).send(String(req.query['hub.challenge'] ?? ''));
      }
      return res.sendStatus(403);
    });

    app.post('/webhook', (req, res) => {
      // Comprueba que el mensaje de verdad lo firmó Meta con el secreto de la app
      const secreto = config.whatsapp.appSecret;
      if (secreto) {
        const esperada = `sha256=${crypto.createHmac('sha256', secreto).update(req.rawBody ?? '').digest('hex')}`;
        if (!iguales(req.get('x-hub-signature-256'), esperada)) return res.sendStatus(401);
      }

      res.sendStatus(200);

      for (const mensaje of extraerMensajes(req.body)) {
        if (yaVisto(mensaje.id)) continue;

        cola.encolar(mensaje.telefono, async () => {
          for (const texto of await procesar(mensaje)) await enviarTexto(mensaje.telefono, texto);
        }, `a ${enmascarar(mensaje.telefono)}`);
      }
    });
  }

  // --- Simulador (solo desarrollo)
  if (config.simulador) {
    app.get('/simulador', (_req, res) => res.type('html').send(PAGINA_SIMULADOR));

    app.post('/simulador/mensaje', async (req, res) => {
      const { telefono = '573000000000', nombre = 'Cliente de prueba', texto } = req.body ?? {};
      res.json({ respuestas: await procesar({ telefono: String(telefono), nombre, texto }) });
    });
  }

  app.use((_req, res) => res.status(404).json({ mensaje: 'No encontrado' }));

  // JSON mal formado u otros errores: nunca se devuelve el detalle interno
  app.use((error, _req, res, _next) => {
    if (error.type === 'entity.parse.failed') return res.status(400).json({ mensaje: 'JSON inválido' });
    registrar.error('[bot] Error en el servidor', error);
    return res.status(500).json({ mensaje: 'Error interno' });
  });

  // Solo para las pruebas: espera a que terminen los mensajes que se estaban atendiendo
  app.locals.esperarPendientes = () => cola.esperar();

  return app;
}
