// Cola por cliente: los mensajes de una misma persona se atienden UNO TRAS OTRO, en el orden en que llegaron.
// Sin esto, si alguien escribe "2" y "1" seguidos mientras el servidor está despertando la API,
// la segunda respuesta podría procesarse antes que la primera y la conversación quedaría desordenada.
// Clientes distintos no se bloquean entre sí.

export function crearCola(registrar = console) {
  const colas = new Map();

  return {
    /**
     * @param {string} clave  quién es el cliente (teléfono o id de chat)
     * @param {() => Promise<void>} tarea
     * @param {string} [descripcion]  para el registro si la tarea falla (sin datos personales)
     */
    encolar(clave, tarea, descripcion = 'mensaje') {
      const anterior = colas.get(clave) ?? Promise.resolve();
      const actual = anterior
        .then(tarea)
        .catch((error) => registrar.error(`[bot] No se pudo atender ${descripcion}: ${error.message}`));

      colas.set(clave, actual);
      actual.finally(() => {
        if (colas.get(clave) === actual) colas.delete(clave);
      });
    },

    /** Para las pruebas: espera a que terminen las tareas en curso. */
    esperar() {
      return Promise.all([...colas.values()]);
    },
  };
}
