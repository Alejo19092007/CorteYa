// Cliente de la API REST de CorteYa (Laravel + Sanctum).
//
// Es la ÚNICA pieza del bot que habla con la API. Se encarga de:
//   - Iniciar sesión (POST /api/login) y guardar el token Sanctum.
//   - Mandarlo en cada llamada ("Authorization: Bearer <token>").
//   - Si el token venció o fue revocado (401): iniciar sesión de nuevo y repetir la llamada UNA vez.
//   - Traducir los fallos a errores que el resto del bot entiende:
//       ApiNoDisponible   -> sin conexión, tiempo agotado o error del servidor (5xx)
//       ApiRechazo        -> 422: una regla de negocio dijo que no (R1, R3, R4...) o datos inválidos
//       ApiNoEncontrado   -> 404
//       ApiAuthError      -> las credenciales del bot no sirven (problema de configuración)

export class ApiNoDisponible extends Error {
  constructor(detalle) {
    super(`La API no está disponible: ${detalle}`);
    this.name = 'ApiNoDisponible';
  }
}

export class ApiRechazo extends Error {
  /** @param {string} mensaje  el "message" que devolvió la API (ya viene en español) */
  constructor(mensaje, errores = {}) {
    super(mensaje);
    this.name = 'ApiRechazo';
    this.errores = errores;
  }
}

export class ApiNoEncontrado extends Error {
  constructor(mensaje = 'El recurso solicitado no existe.') {
    super(mensaje);
    this.name = 'ApiNoEncontrado';
  }
}

export class ApiAuthError extends Error {
  constructor(mensaje) {
    super(mensaje);
    this.name = 'ApiAuthError';
  }
}

/**
 * @param {object} opciones
 * @param {string} opciones.baseUrl     URL pública de la API, p. ej. https://corteya.onrender.com
 * @param {string} opciones.email       cuenta de administrador que usa el bot
 * @param {string} opciones.password
 * @param {typeof fetch} [opciones.fetchFn]  se puede reemplazar en las pruebas
 * @param {number} [opciones.timeoutMs]      el plan gratuito de Render tarda ~1 min en despertar
 */
export function crearClienteApi({ baseUrl, email, password, fetchFn = fetch, timeoutMs = 60_000, deviceName = 'bot-whatsapp' }) {
  const base = baseUrl.replace(/\/+$/, '');
  let token = null;
  let loginEnCurso = null;

  /** Una petición HTTP cruda. Devuelve { estado, datos } o lanza ApiNoDisponible si no hubo respuesta. */
  async function http(metodo, ruta, { query, cuerpo, conToken = true } = {}) {
    const url = new URL(base + ruta);
    for (const [clave, valor] of Object.entries(query ?? {})) {
      if (valor === undefined || valor === null) continue;
      // Laravel lee los arreglos como servicios[]=1&servicios[]=2
      if (Array.isArray(valor)) valor.forEach((v) => url.searchParams.append(`${clave}[]`, String(v)));
      else url.searchParams.set(clave, String(valor));
    }

    const cabeceras = { Accept: 'application/json' };
    if (cuerpo !== undefined) cabeceras['Content-Type'] = 'application/json';
    if (conToken && token) cabeceras.Authorization = `Bearer ${token}`;

    let respuesta;
    try {
      respuesta = await fetchFn(url, {
        method: metodo,
        headers: cabeceras,
        body: cuerpo !== undefined ? JSON.stringify(cuerpo) : undefined,
        signal: AbortSignal.timeout(timeoutMs),
      });
    } catch (error) {
      // Sin internet, DNS, conexión rechazada o tiempo agotado
      throw new ApiNoDisponible(error.name === 'TimeoutError' ? 'tiempo de espera agotado' : 'sin conexión');
    }

    const texto = await respuesta.text();
    let datos = null;
    try {
      datos = texto ? JSON.parse(texto) : null;
    } catch {
      datos = null; // p. ej. una página HTML de error del proxy
    }

    return { estado: respuesta.status, datos };
  }

  /** Inicia sesión y guarda el token. Si varias llamadas lo piden a la vez, comparten un solo login. */
  function iniciarSesion() {
    if (loginEnCurso) return loginEnCurso;

    loginEnCurso = (async () => {
      const { estado, datos } = await http('POST', '/api/login', {
        cuerpo: { email, password, device_name: deviceName },
        conToken: false,
      });

      if (estado === 200 && datos?.token) {
        token = datos.token;
        return;
      }
      if (estado >= 500 || estado === 429) throw new ApiNoDisponible(`el login respondió ${estado}`);

      // 401 (credenciales mal), 403 (la cuenta no es admin) o 422: es un problema de configuración del bot
      throw new ApiAuthError(`No se pudo iniciar sesión en la API (${estado}): ${datos?.message ?? 'sin detalle'}`);
    })().finally(() => {
      loginEnCurso = null;
    });

    return loginEnCurso;
  }

  /** Llamada autenticada: inicia sesión si hace falta y reintenta una vez si recibe 401. */
  async function llamar(metodo, ruta, opciones = {}) {
    if (!token) await iniciarSesion();

    let { estado, datos } = await http(metodo, ruta, opciones);

    if (estado === 401) {
      token = null;
      await iniciarSesion();
      ({ estado, datos } = await http(metodo, ruta, opciones));
      if (estado === 401) throw new ApiAuthError('La API rechazó el token recién emitido.');
    }

    if (estado >= 200 && estado < 300) return datos;
    if (estado === 404) throw new ApiNoEncontrado(datos?.message);
    if (estado === 422) throw new ApiRechazo(datos?.message ?? 'La solicitud no es válida.', datos?.errors);
    if (estado === 403) throw new ApiAuthError(datos?.message ?? 'La cuenta del bot no tiene permiso.');

    throw new ApiNoDisponible(`respondió ${estado}`);
  }

  return {
    /** GET: servicios que se ofrecen (activos). */
    async servicios() {
      return (await llamar('GET', '/api/servicios', { query: { activo: 1 } })).data;
    },

    async barberos() {
      return (await llamar('GET', '/api/barberos')).data;
    },

    /** Horas de inicio libres ("HH:MM") de un barbero un día para los servicios elegidos. */
    async disponibilidad(barberoId, fecha, servicioIds) {
      return (await llamar('GET', `/api/barberos/${barberoId}/disponibilidad`, {
        query: { fecha, servicios: servicioIds },
      })).data;
    },

    /** GET: turnos del cliente con ese teléfono. */
    async turnos(telefono) {
      return (await llamar('GET', '/api/turnos', { query: { telefono } })).data;
    },

    /** Escritura: reserva un turno (nace pendiente, origen whatsapp). Puede lanzar ApiRechazo (R1...). */
    async crearTurno({ telefono, nombre, barberoId, fecha, horaInicio, servicioIds }) {
      return (await llamar('POST', '/api/turnos', {
        cuerpo: {
          telefono,
          nombre: nombre || undefined,
          barbero_id: barberoId,
          fecha,
          hora_inicio: horaInicio,
          servicios: servicioIds,
        },
      })).data;
    },

    /** Cancela como cliente: la API aplica R3 (hasta 2 h antes) y responde 422 si ya no se puede. */
    async cancelarTurno(turnoId) {
      await llamar('DELETE', `/api/turnos/${turnoId}`);
    },
  };
}
