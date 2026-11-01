import assert from 'node:assert/strict';
import test from 'node:test';
import {
  esNo, esSi, fechaLarga, hora12, horaEnBogota, hoyEnBogota, interpretarFecha, interpretarNumeros, pesos, sumarDias,
} from '../src/bot/formato.js';

test('pesos separa los miles con punto', () => {
  assert.equal(pesos(25000), '$25.000');
  assert.equal(pesos('40000.00'), '$40.000');
  assert.equal(pesos(0), '$0');
});

test('fechaLarga escribe el día de la semana y el mes en español', () => {
  assert.equal(fechaLarga('2026-11-03'), 'martes 3 de noviembre');
  assert.equal(fechaLarga('2026-12-25'), 'viernes 25 de diciembre');
});

test('hora12 convierte de 24 h a a. m./p. m.', () => {
  assert.equal(hora12('09:00'), '9:00 a. m.');
  assert.equal(hora12('15:30'), '3:30 p. m.');
  assert.equal(hora12('12:00'), '12:00 p. m.');
  assert.equal(hora12('00:15'), '12:15 a. m.');
  assert.equal(hora12('09:00:00'), '9:00 a. m.');
});

test('"hoy" se calcula en hora de Colombia, no en UTC', () => {
  // 03:00 UTC del 3 de noviembre = 22:00 del 2 de noviembre en Bogotá (UTC-5)
  const ahora = new Date('2026-11-03T03:00:00Z');
  assert.equal(hoyEnBogota(ahora), '2026-11-02');
  assert.equal(horaEnBogota(ahora), '22:00');

  // 15:00 UTC = 10:00 en Bogotá, el mismo día
  assert.equal(hoyEnBogota(new Date('2026-11-02T15:00:00Z')), '2026-11-02');
  assert.equal(horaEnBogota(new Date('2026-11-02T15:00:00Z')), '10:00');
});

test('sumarDias cruza el fin de mes y de año', () => {
  assert.equal(sumarDias('2026-11-30', 1), '2026-12-01');
  assert.equal(sumarDias('2026-12-31', 1), '2027-01-01');
  assert.equal(sumarDias('2026-11-02', 0), '2026-11-02');
});

test('interpretarFecha entiende hoy, mañana y pasado mañana (con o sin tilde)', () => {
  const hoy = '2026-11-02';
  assert.deepEqual(interpretarFecha('hoy', hoy), { fecha: '2026-11-02' });
  assert.deepEqual(interpretarFecha('Mañana', hoy), { fecha: '2026-11-03' });
  assert.deepEqual(interpretarFecha('manana', hoy), { fecha: '2026-11-03' });
  assert.deepEqual(interpretarFecha('pasado mañana', hoy), { fecha: '2026-11-04' });
});

test('interpretarFecha entiende dd/mm, dd-mm, dd/mm/aaaa y "5 de noviembre"', () => {
  const hoy = '2026-11-02';
  assert.deepEqual(interpretarFecha('05/11', hoy), { fecha: '2026-11-05' });
  assert.deepEqual(interpretarFecha('5-11', hoy), { fecha: '2026-11-05' });
  assert.deepEqual(interpretarFecha('05/11/2026', hoy), { fecha: '2026-11-05' });
  assert.deepEqual(interpretarFecha('5/11/26', hoy), { fecha: '2026-11-05' });
  assert.deepEqual(interpretarFecha('5 de noviembre', hoy), { fecha: '2026-11-05' });
  assert.deepEqual(interpretarFecha('hoy', hoy), { fecha: hoy });
});

test('interpretarFecha rechaza fechas pasadas, inexistentes o ilegibles', () => {
  const hoy = '2026-11-02';
  assert.deepEqual(interpretarFecha('01/11', hoy), { error: 'pasada' });
  assert.deepEqual(interpretarFecha('31/02', hoy), { error: 'formato' });
  assert.deepEqual(interpretarFecha('13/13', hoy), { error: 'formato' });
  assert.deepEqual(interpretarFecha('ayer', hoy), { error: 'formato' });
  assert.deepEqual(interpretarFecha('cuando puedas', hoy), { error: 'formato' });
});

test('interpretarNumeros acepta listas y rechaza texto', () => {
  assert.deepEqual(interpretarNumeros('1'), [1]);
  assert.deepEqual(interpretarNumeros('1,3'), [1, 3]);
  assert.deepEqual(interpretarNumeros('1 3'), [1, 3]);
  assert.deepEqual(interpretarNumeros('1 y 3'), [1, 3]);
  assert.deepEqual(interpretarNumeros('2,2'), [2]); // sin repetidos
  assert.equal(interpretarNumeros('uno'), null);
  assert.equal(interpretarNumeros('1,a'), null);
  assert.equal(interpretarNumeros(''), null);
});

test('esSi y esNo reconocen las respuestas habituales', () => {
  for (const texto of ['sí', 'Si', 'SI', ' dale ', 'ok']) assert.equal(esSi(texto), true, texto);
  for (const texto of ['no', 'No', 'N']) assert.equal(esNo(texto), true, texto);
  assert.equal(esSi('no'), false);
  assert.equal(esNo('sí'), false);
  assert.equal(esSi('quizás'), false);
});
