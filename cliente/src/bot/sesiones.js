// Memoria de la conversación de cada cliente (en qué paso va y qué ha elegido).
//
// Se guarda en memoria del proceso, por número de teléfono, y caduca tras 30 minutos sin actividad.
// Si el servidor se reinicia (p. ej. Render lo duerme) la conversación vuelve al menú: no se pierde
// ningún dato importante, porque un turno solo existe cuando ya se creó en la API.

export function crearSesiones({ ttlMs = 30 * 60 * 1000, ahora = () => Date.now() } = {}) {
  const sesiones = new Map();

  return {
    /** @returns {{estado: string, datos: object} | null} */
    obtener(telefono) {
      const sesion = sesiones.get(telefono);
      if (!sesion) return null;
      if (ahora() - sesion.ultimaActividad > ttlMs) {
        sesiones.delete(telefono);
        return null;
      }
      return sesion;
    },

    guardar(telefono, estado, datos = {}) {
      sesiones.set(telefono, { estado, datos, ultimaActividad: ahora() });
    },

    borrar(telefono) {
      sesiones.delete(telefono);
    },

    /** Quita las caducadas (se llama de vez en cuando para no acumular memoria). */
    limpiar() {
      for (const telefono of [...sesiones.keys()]) this.obtener(telefono);
    },

    get cantidad() {
      return sesiones.size;
    },
  };
}
