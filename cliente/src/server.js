// Punto de arranque: arma las piezas según el canal elegido (CANAL=telegram | whatsapp) y levanta el servidor.
// En desarrollo: `npm run dev` (lee cliente/.env). En Render las variables ya vienen del panel.

import { crearClienteApi } from './api/corteyaClient.js';
import { crearApp } from './app.js';
import { crearBot } from './bot/conversacion.js';
import { crearSesiones } from './bot/sesiones.js';
import { crearCola } from './cola.js';
import { cargarConfig } from './config.js';
import { crearAdaptadorTelegram } from './telegram/adaptador.js';
import { crearTelegram } from './telegram/botApi.js';
import { iniciarPolling } from './telegram/polling.js';
import { crearEnviador } from './whatsapp/cloudApi.js';

const config = cargarConfig();

const api = crearClienteApi({
  baseUrl: config.apiUrl,
  email: config.botEmail,
  password: config.botPassword,
  deviceName: `bot-${config.canal}`, // un token por canal: iniciar sesión de nuevo reemplaza solo el del mismo canal
});
const sesiones = crearSesiones();
setInterval(() => sesiones.limpiar(), 10 * 60 * 1000).unref();

// El origen de los turnos es el canal activo (telegram por defecto; también en el simulador)
const { procesar } = crearBot({ api, sesiones, origen: config.canal });
const cola = crearCola();

// Canal Telegram
const telegram = config.telegram ? crearTelegram({ token: config.telegram.token }) : null;
const adaptadorTelegram = telegram ? crearAdaptadorTelegram({ telegram, procesar }) : null;

// Canal WhatsApp
const enviador = config.whatsapp ? crearEnviador(config.whatsapp) : null;
const enviarTexto = enviador ? (para, texto) => enviador.enviarTexto(para, texto) : undefined;

const app = crearApp({ config, procesar, enviarTexto, telegram: adaptadorTelegram, cola });

app.listen(config.puerto, async () => {
  console.log(`[bot] Escuchando en el puerto ${config.puerto}`);
  console.log(`[bot] API: ${config.apiUrl}`);

  if (config.simulador) {
    console.log(`[bot] Simulador: http://localhost:${config.puerto}/simulador`);
    return;
  }
  console.log(`[bot] Canal: ${config.canal}`);

  if (!telegram) return;

  try {
    if (config.telegram.modo === 'webhook') {
      await telegram.configurarWebhook(`${config.telegram.urlPublica}/telegram/webhook`, config.telegram.webhookSecret);
      console.log(`[bot] Webhook de Telegram registrado en ${config.telegram.urlPublica}/telegram/webhook`);
    } else {
      iniciarPolling({ telegram, atender: (actualizacion) => adaptadorTelegram.manejarActualizacion(actualizacion), cola });
      console.log('[bot] Telegram en modo polling: escribe a tu bot en Telegram para probarlo.');
    }
    await telegram.registrarComandos();
  } catch (error) {
    // Sin esto el bot no recibiría nada: mejor que el despliegue falle a la vista que quedar "vivo" sin funcionar
    console.error(`[bot] No se pudo configurar Telegram: ${error.message}`);
    process.exit(1);
  }
});
