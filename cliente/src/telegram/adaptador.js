// Adaptador de Telegram: traduce las "actualizaciones" de Telegram a { telefono, nombre, texto } para el bot,
// y lleva las respuestas de vuelta al chat. La lógica de la conversación NO está aquí (está en bot/conversacion.js).
//
// DIFERENCIA CON WHATSAPP: WhatsApp entrega el número de celular del cliente en cada mensaje; Telegram no.
// La API de turnos identifica al cliente por su teléfono, así que la primera vez el bot muestra un botón
// "Compartir mi número". Telegram envía entonces un "contacto" y se verifica que sea el del propio usuario.

const COMANDOS_COMO_TEXTO = {
  '/start': 'menu',
  '/menu': 'menu',
};

/** Deja solo dígitos y exige un celular colombiano: 57 + 10 dígitos que empiezan por 3. */
export function normalizarCelularColombiano(numero) {
  let digitos = String(numero ?? '').replace(/\D/g, '');
  if (digitos.length === 10) digitos = `57${digitos}`;
  return /^573\d{9}$/.test(digitos) ? digitos : null;
}

/** "/menu@CorteYaBot" -> "menu"; otros comandos se dejan como están para que el bot diga que no los entiende. */
function textoDeComando(texto) {
  const comando = texto.split(/[\s@]/)[0].toLowerCase();
  return COMANDOS_COMO_TEXTO[comando] ?? texto;
}

/**
 * @param {object} opciones
 * @param {ReturnType<import('./botApi.js').crearTelegram>} opciones.telegram
 * @param {(m: {telefono: string, nombre: string|null, texto: string|null}) => Promise<string[]>} opciones.procesar
 * @param {Map<number, string>} [opciones.telefonos]  chat -> teléfono ya verificado (en memoria)
 */
export function crearAdaptadorTelegram({ telegram, procesar, telefonos = new Map() }) {
  async function responder(chatId, respuestas) {
    for (const texto of respuestas) await telegram.enviarTexto(chatId, texto);
  }

  async function recibirContacto(mensaje, chatId, nombre) {
    // Un contacto reenviado de otra persona trae otro user_id: solo vale el número del propio usuario
    if (mensaje.contact.user_id !== mensaje.from?.id) {
      await telegram.pedirContacto(chatId, 'Ese contacto no es el tuyo. Toca el botón *Compartir mi número* para enviar el tuyo.');
      return;
    }

    const telefono = normalizarCelularColombiano(mensaje.contact.phone_number);
    if (!telefono) {
      await telegram.pedirContacto(chatId, 'Por ahora solo atendemos celulares de Colombia (+57). Toca el botón con un número colombiano.');
      return;
    }

    telefonos.set(chatId, telefono);
    await telegram.quitarTeclado(chatId, '¡Gracias! Tu número quedó registrado ✅');
    await responder(chatId, await procesar({ telefono, nombre, texto: 'menu' }));
  }

  return {
    /** Atiende una actualización de Telegram (un mensaje). Ignora todo lo que no sea un chat privado. */
    async manejarActualizacion(actualizacion) {
      const mensaje = actualizacion?.message;
      if (!mensaje || mensaje.chat?.type !== 'private') return;

      const chatId = mensaje.chat.id;
      const nombre = [mensaje.from?.first_name, mensaje.from?.last_name].filter(Boolean).join(' ') || null;

      if (mensaje.contact) return recibirContacto(mensaje, chatId, nombre);

      const telefono = telefonos.get(chatId);
      if (!telefono) {
        await telegram.pedirContacto(
          chatId,
          '¡Hola! Soy el asistente de *CorteYa* 💈\nPara reservar y consultar tus turnos necesito tu número de celular. Toca el botón de abajo para compartirlo.',
        );
        return;
      }

      const texto = typeof mensaje.text === 'string' ? textoDeComando(mensaje.text) : null;
      await responder(chatId, await procesar({ telefono, nombre, texto }));
    },

    /** Cuántos chats ya compartieron su número (útil para las pruebas y el registro). */
    get chatsVerificados() {
      return telefonos.size;
    },
  };
}
