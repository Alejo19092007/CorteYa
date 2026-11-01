// Configuración del bot: todo viene de variables de entorno (nunca del código ni del repositorio).

const falta = (nombre) => {
  throw new Error(`Falta la variable de entorno ${nombre}. Revisa cliente/.env.example.`);
};

const CANALES = ['telegram', 'whatsapp'];

/**
 * @param {Record<string, string | undefined>} env
 */
export function cargarConfig(env = process.env) {
  const simulador = env.MODO_SIMULADOR === 'true';
  const produccion = env.NODE_ENV === 'production';
  const canal = env.CANAL ?? 'telegram';

  if (!CANALES.includes(canal)) throw new Error(`CANAL debe ser uno de: ${CANALES.join(', ')}.`);

  const apiUrl = (env.CORTEYA_API_URL ?? falta('CORTEYA_API_URL')).replace(/\/+$/, '');
  const host = new URL(apiUrl).hostname;

  // La rúbrica exige que el bot use la URL PÚBLICA (HTTPS), nunca localhost.
  // En el simulador local se permite para poder probar contra el backend de desarrollo.
  if (!simulador && (['localhost', '127.0.0.1', '::1'].includes(host) || !apiUrl.startsWith('https://'))) {
    throw new Error('CORTEYA_API_URL debe ser la URL pública con https:// (no localhost). Para pruebas locales usa MODO_SIMULADOR=true.');
  }
  if (simulador && produccion) {
    throw new Error('MODO_SIMULADOR no se puede activar con NODE_ENV=production: expondría el bot a cualquiera.');
  }

  const config = {
    canal,
    puerto: Number(env.PORT ?? 3000),
    apiUrl,
    botEmail: env.BOT_EMAIL ?? falta('BOT_EMAIL'),
    botPassword: env.BOT_PASSWORD ?? falta('BOT_PASSWORD'),
    simulador,
    telegram: null,
    whatsapp: null,
  };

  // En el simulador no se habla con ningún canal, así que sus datos no son obligatorios
  if (simulador) return config;

  if (canal === 'telegram') {
    // Webhook en el servidor (Telegram avisa por HTTPS); polling en el computador (el bot pregunta cada pocos segundos)
    const modo = env.TELEGRAM_MODO ?? (produccion ? 'webhook' : 'polling');
    if (!['webhook', 'polling'].includes(modo)) throw new Error('TELEGRAM_MODO debe ser webhook o polling.');

    config.telegram = {
      token: env.TELEGRAM_BOT_TOKEN ?? falta('TELEGRAM_BOT_TOKEN'),
      modo,
      webhookSecret: null,
      urlPublica: null,
    };

    if (modo === 'webhook') {
      const secreto = env.TELEGRAM_WEBHOOK_SECRET ?? falta('TELEGRAM_WEBHOOK_SECRET');
      // Telegram solo acepta letras, números, guion y guion bajo en el secreto
      if (!/^[A-Za-z0-9_-]{16,256}$/.test(secreto)) {
        throw new Error('TELEGRAM_WEBHOOK_SECRET debe tener entre 16 y 256 caracteres: letras, números, "-" o "_".');
      }

      // Render define RENDER_EXTERNAL_URL solo, con la URL pública del servicio
      const urlPublica = (env.BOT_PUBLIC_URL ?? env.RENDER_EXTERNAL_URL ?? falta('BOT_PUBLIC_URL')).replace(/\/+$/, '');
      if (!urlPublica.startsWith('https://')) throw new Error('BOT_PUBLIC_URL debe ser una URL pública con https://.');

      config.telegram.webhookSecret = secreto;
      config.telegram.urlPublica = urlPublica;
    }
  } else {
    config.whatsapp = {
      token: env.WHATSAPP_TOKEN ?? falta('WHATSAPP_TOKEN'),
      phoneNumberId: env.WHATSAPP_PHONE_NUMBER_ID ?? falta('WHATSAPP_PHONE_NUMBER_ID'),
      verifyToken: env.WHATSAPP_VERIFY_TOKEN ?? falta('WHATSAPP_VERIFY_TOKEN'),
      // Con el secreto de la app se comprueba que cada mensaje de verdad viene de Meta (firma HMAC)
      appSecret: env.WHATSAPP_APP_SECRET ?? (produccion ? falta('WHATSAPP_APP_SECRET') : null),
      graphVersion: env.WHATSAPP_GRAPH_VERSION ?? 'v22.0',
    };
  }

  return config;
}
