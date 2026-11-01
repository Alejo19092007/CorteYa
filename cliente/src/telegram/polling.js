// Modo "polling": el bot le pregunta a Telegram cada pocos segundos si hay mensajes nuevos (getUpdates).
// Sirve para probar en tu propio computador SIN URL pública. En el servidor (Render) se usa el webhook.

const dormir = (ms) => new Promise((resolver) => setTimeout(resolver, ms));

/**
 * @param {object} opciones
 * @param {ReturnType<import('./botApi.js').crearTelegram>} opciones.telegram
 * @param {(actualizacion: object) => Promise<void>} opciones.atender
 * @param {ReturnType<import('../cola.js').crearCola>} opciones.cola
 */
export function iniciarPolling({ telegram, atender, cola, registrar = console, pausaErrorMs = 5000 }) {
  let activo = true;
  let offset;

  const terminado = (async () => {
    // Telegram no permite tener webhook y polling a la vez
    try {
      await telegram.quitarWebhook();
    } catch (error) {
      registrar.error(`[bot] No se pudo quitar el webhook: ${error.message}`);
    }

    while (activo) {
      try {
        const actualizaciones = await telegram.obtenerActualizaciones(offset);

        for (const actualizacion of actualizaciones) {
          offset = actualizacion.update_id + 1; // confirma que ya se recibió: Telegram no la vuelve a enviar
          const chatId = actualizacion.message?.chat?.id;
          if (chatId === undefined) continue;

          cola.encolar(`tg:${chatId}`, () => atender(actualizacion), 'un mensaje de Telegram');
        }
      } catch (error) {
        registrar.warn?.(`[bot] Telegram no respondió (${error.message}). Reintento en ${pausaErrorMs / 1000} s.`);
        await dormir(pausaErrorMs);
      }
    }
  })();

  return {
    detener() {
      activo = false;
    },
    terminado,
  };
}
