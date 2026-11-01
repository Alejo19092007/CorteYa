// Envío de mensajes por la WhatsApp Cloud API oficial de Meta (Graph API).
//
//   POST https://graph.facebook.com/<versión>/<PHONE_NUMBER_ID>/messages
//   Authorization: Bearer <token>
//
// Dentro de las 24 horas siguientes al último mensaje del cliente se puede responder con texto libre
// (nuestro caso: siempre es el cliente quien escribe primero).

export class EnvioWhatsAppError extends Error {
  constructor(estado, detalle) {
    super(`WhatsApp rechazó el envío (${estado}): ${detalle}`);
    this.name = 'EnvioWhatsAppError';
    this.estado = estado;
  }
}

export function crearEnviador({ token, phoneNumberId, graphVersion = 'v22.0', fetchFn = fetch, timeoutMs = 15_000 }) {
  const url = `https://graph.facebook.com/${graphVersion}/${phoneNumberId}/messages`;

  return {
    async enviarTexto(para, texto) {
      let respuesta;
      try {
        respuesta = await fetchFn(url, {
          method: 'POST',
          headers: { Authorization: `Bearer ${token}`, 'Content-Type': 'application/json' },
          body: JSON.stringify({
            messaging_product: 'whatsapp',
            to: para,
            type: 'text',
            text: { body: texto, preview_url: false },
          }),
          signal: AbortSignal.timeout(timeoutMs),
        });
      } catch {
        throw new EnvioWhatsAppError(0, 'sin conexión con WhatsApp');
      }

      if (!respuesta.ok) {
        // Meta explica el motivo en error.message (p. ej. token vencido, número no autorizado).
        // El token nunca se incluye en el mensaje de error.
        const cuerpo = await respuesta.json().catch(() => ({}));
        throw new EnvioWhatsAppError(respuesta.status, cuerpo?.error?.message ?? 'sin detalle');
      }
    },
  };
}
