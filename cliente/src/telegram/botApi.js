// Cliente de la Bot API de Telegram (https://core.telegram.org/bots/api).
// Crear el bot es gratis: se habla con @BotFather en Telegram y entrega el token.
//
// El token va en la URL de cada llamada (así lo exige Telegram), por eso los errores NUNCA incluyen la URL.

export class TelegramError extends Error {
  constructor(estado, detalle) {
    super(`Telegram rechazó la llamada (${estado}): ${detalle}`);
    this.name = 'TelegramError';
    this.estado = estado;
  }
}

/**
 * Los mensajes del bot usan *negrita* al estilo WhatsApp. Telegram lo muestra con HTML:
 * primero se escapa todo lo que venga de datos (nombres, etc.) y luego se convierte *x* en <b>x</b>.
 */
export function aHtml(texto) {
  return String(texto)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/\*([^*\n]+)\*/g, '<b>$1</b>');
}

export function crearTelegram({ token, fetchFn = fetch, timeoutMs = 15_000 }) {
  const base = `https://api.telegram.org/bot${token}`;

  async function llamar(metodo, cuerpo = {}, tiempoMs = timeoutMs) {
    let respuesta;
    try {
      respuesta = await fetchFn(`${base}/${metodo}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(cuerpo),
        signal: AbortSignal.timeout(tiempoMs),
      });
    } catch {
      throw new TelegramError(0, 'sin conexión con Telegram');
    }

    const datos = await respuesta.json().catch(() => ({}));
    if (!respuesta.ok || !datos.ok) throw new TelegramError(respuesta.status, datos.description ?? 'sin detalle');
    return datos.result;
  }

  return {
    enviarTexto(chatId, texto, reply_markup) {
      return llamar('sendMessage', { chat_id: chatId, text: aHtml(texto), parse_mode: 'HTML', reply_markup });
    },

    /** Muestra un botón que, al tocarlo, comparte el número de celular del propio usuario. */
    pedirContacto(chatId, texto) {
      return this.enviarTexto(chatId, texto, {
        keyboard: [[{ text: '📱 Compartir mi número', request_contact: true }]],
        resize_keyboard: true,
        one_time_keyboard: true,
      });
    },

    quitarTeclado(chatId, texto) {
      return this.enviarTexto(chatId, texto, { remove_keyboard: true });
    },

    /** Telegram avisará por POST a esta URL (HTTPS pública) y mandará el secreto en una cabecera. */
    configurarWebhook(url, secreto) {
      return llamar('setWebhook', { url, secret_token: secreto, allowed_updates: ['message'] });
    },

    /** Hace falta antes de usar getUpdates: Telegram no permite webhook y polling a la vez. */
    quitarWebhook() {
      return llamar('deleteWebhook', { drop_pending_updates: false });
    },

    /** Polling largo: Telegram deja la petición abierta hasta `esperaSeg` segundos esperando mensajes. */
    obtenerActualizaciones(offset, esperaSeg = 25) {
      return llamar('getUpdates', { offset, timeout: esperaSeg, allowed_updates: ['message'] }, (esperaSeg + 10) * 1000);
    },

    /** Menú de comandos que Telegram muestra al escribir "/". */
    registrarComandos() {
      return llamar('setMyCommands', {
        commands: [
          { command: 'menu', description: 'Ver el menú principal' },
          { command: 'start', description: 'Empezar' },
        ],
      });
    },
  };
}
