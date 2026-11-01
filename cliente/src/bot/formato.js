// Funciones de formato y de fechas para los mensajes del bot.
//
// IMPORTANTE: el servidor (Render) trabaja en UTC y la barbería está en Colombia (UTC-5, sin horario de verano).
// Por eso "hoy" y "mañana" se calculan SIEMPRE en la zona America/Bogota, nunca con la hora del servidor.

const ZONA = 'America/Bogota';

const MESES = {
  enero: 1, febrero: 2, marzo: 3, abril: 4, mayo: 5, junio: 6,
  julio: 7, agosto: 8, septiembre: 9, setiembre: 9, octubre: 10, noviembre: 11, diciembre: 12,
};

/** $25.000 */
export function pesos(valor) {
  // Puntos de miles puestos a mano (no con toLocaleString) para no depender de los datos de idioma de Node
  return `$${String(Math.round(Number(valor))).replace(/\B(?=(\d{3})+(?!\d))/g, '.')}`;
}

const DIAS_SEMANA = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];
const NOMBRES_MESES = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];

/**
 * "2026-11-03" -> "martes 3 de noviembre".
 * Se arma con nombres propios (no con Intl) para que el texto sea idéntico en cualquier versión de Node.
 */
export function fechaLarga(fecha) {
  const [a, m, d] = fecha.split('-').map(Number);
  const diaSemana = new Date(Date.UTC(a, m - 1, d)).getUTCDay();
  return `${DIAS_SEMANA[diaSemana]} ${d} de ${NOMBRES_MESES[m - 1]}`;
}

/** "15:30" -> "3:30 p. m." */
export function hora12(hora) {
  const [h, min] = hora.split(':').map(Number);
  const sufijo = h >= 12 ? 'p. m.' : 'a. m.';
  return `${h % 12 === 0 ? 12 : h % 12}:${String(min).padStart(2, '0')} ${sufijo}`;
}

/** Fecha de hoy en Colombia, "YYYY-MM-DD". */
export function hoyEnBogota(ahora = new Date()) {
  return new Intl.DateTimeFormat('en-CA', { timeZone: ZONA, year: 'numeric', month: '2-digit', day: '2-digit' }).format(ahora);
}

/** Hora actual en Colombia, "HH:MM" (24 h). */
export function horaEnBogota(ahora = new Date()) {
  return new Intl.DateTimeFormat('en-GB', { timeZone: ZONA, hour: '2-digit', minute: '2-digit', hourCycle: 'h23' }).format(ahora);
}

export function sumarDias(fecha, dias) {
  const [a, m, d] = fecha.split('-').map(Number);
  const nueva = new Date(Date.UTC(a, m - 1, d + dias));
  return nueva.toISOString().slice(0, 10);
}

function fechaValida(a, m, d) {
  const f = new Date(Date.UTC(a, m - 1, d));
  return f.getUTCFullYear() === a && f.getUTCMonth() === m - 1 && f.getUTCDate() === d;
}

const sinTildes = (texto) => texto.normalize('NFD').replace(/[̀-ͯ]/g, '');

/**
 * Entiende lo que escribe el cliente como fecha. Acepta: hoy, mañana, pasado mañana,
 * 05/11, 5-11, 05/11/2026, "5 de noviembre".
 *
 * @returns {{fecha: string} | {error: 'formato' | 'pasada'}}
 */
export function interpretarFecha(texto, hoy) {
  const limpio = sinTildes(texto.trim().toLowerCase());

  if (limpio === 'hoy') return { fecha: hoy };
  if (limpio === 'manana') return { fecha: sumarDias(hoy, 1) };
  if (limpio === 'pasado manana') return { fecha: sumarDias(hoy, 2) };

  const [anioHoy] = hoy.split('-').map(Number);
  let dia;
  let mes;
  let anio = anioHoy;

  let coincide = limpio.match(/^(\d{1,2})[/\-.](\d{1,2})(?:[/\-.](\d{2,4}))?$/);
  if (coincide) {
    dia = Number(coincide[1]);
    mes = Number(coincide[2]);
    if (coincide[3]) anio = coincide[3].length === 2 ? 2000 + Number(coincide[3]) : Number(coincide[3]);
  } else if ((coincide = limpio.match(/^(\d{1,2}) de ([a-z]+)$/)) && MESES[coincide[2]]) {
    dia = Number(coincide[1]);
    mes = MESES[coincide[2]];
  } else {
    return { error: 'formato' };
  }

  if (!fechaValida(anio, mes, dia)) return { error: 'formato' };

  const fecha = `${anio}-${String(mes).padStart(2, '0')}-${String(dia).padStart(2, '0')}`;
  return fecha < hoy ? { error: 'pasada' } : { fecha };
}

/** "1, 3" o "1 3" o "1 y 3" -> [1, 3]. Devuelve null si hay algo que no es un número. */
export function interpretarNumeros(texto) {
  const partes = texto.trim().toLowerCase().split(/[\s,;y]+/).filter(Boolean);
  if (partes.length === 0 || partes.some((p) => !/^\d+$/.test(p))) return null;
  return [...new Set(partes.map(Number))];
}

export function esSi(texto) {
  return ['si', 's', 'sii', 'dale', 'ok', 'confirmo', 'confirmar', 'claro', 'listo'].includes(sinTildes(texto.trim().toLowerCase()));
}

export function esNo(texto) {
  return ['no', 'n', 'nop', 'nel'].includes(sinTildes(texto.trim().toLowerCase()));
}

export function normalizarComando(texto) {
  return sinTildes(texto.trim().toLowerCase());
}
