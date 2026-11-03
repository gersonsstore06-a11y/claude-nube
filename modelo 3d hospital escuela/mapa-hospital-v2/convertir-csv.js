// Convierte levantamiento-salas.csv (Excel / Google Sheets) en salas.js, que el mapa lee solo.
// Uso:  node convertir-csv.js [archivo.csv]      (o doble clic en actualizar.bat)
// No necesita instalar nada, solo Node.js.
const fs = require('fs');
const path = require('path');
const vm = require('vm');

const csvPath = path.resolve(process.argv[2] || path.join(__dirname, 'levantamiento-salas.csv'));
const outPath = path.join(__dirname, 'salas.js');
const norm = s => String(s ?? '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().trim();

if (!fs.existsSync(csvPath)) { console.error('No encuentro el archivo: ' + csvPath); process.exit(1); }

// ---- leer el CSV (UTF-8; si Excel lo guardó en Windows-1252, también se entiende) ----
const raw = fs.readFileSync(csvPath);
let text;
try { text = new TextDecoder('utf-8', { fatal: true }).decode(raw); }
catch { text = new TextDecoder('windows-1252').decode(raw); console.log('Aviso: el archivo no estaba en UTF-8; se leyó como Windows-1252. Si ve letras raras, guárdelo como "CSV UTF-8".'); }
text = text.replace(/^﻿/, '');

const firstLine = text.split(/\r?\n/, 1)[0];
const delim = [';', ',', '\t'].map(d => [d, firstLine.split(d).length]).sort((a, b) => b[1] - a[1])[0][0];

function parse(t, d) {
  const rows = []; let row = [], cell = '', q = false;
  for (let i = 0; i < t.length; i++) {
    const c = t[i];
    if (q) { if (c === '"' && t[i + 1] === '"') { cell += '"'; i++; } else if (c === '"') q = false; else cell += c; }
    else if (c === '"') q = true;
    else if (c === d) { row.push(cell); cell = ''; }
    else if (c === '\n' || c === '\r') { if (c === '\r' && t[i + 1] === '\n') i++; row.push(cell); rows.push(row); row = []; cell = ''; }
    else cell += c;
  }
  if (cell || row.length) { row.push(cell); rows.push(row); }
  return rows;
}
const table = parse(text, delim).filter(r => r.some(c => c.trim()));
const head = table.shift().map(norm);
const col = n => head.indexOf(n);
for (const need of ['edificio', 'piso', 'nombre_sala']) if (col(need) < 0) { console.error(`Falta la columna "${need}". Encabezados leídos: ${head.join(' | ')}`); process.exit(1); }
const get = (r, n) => (col(n) >= 0 ? (r[col(n)] ?? '').trim() : '');

// ---- edificios del mapa (datos.js) ----
const ctx = { window: {} }; vm.runInNewContext(fs.readFileSync(path.join(__dirname, 'datos.js'), 'utf8'), ctx);
const D = ctx.window.DATOS;
const ALIAS = { 'hospital escuela': 'BMQ', 'bmq': 'BMQ', 'medico quirurgico': 'BMQ', 'materno infantil': 'BMI', 'bmi': 'BMI', 'lobby': 'LOB', 'patologia': 'PAT' };
function buildingOf(name) {
  const n = norm(name);
  if (ALIAS[n]) return D.edificios.find(b => b.id === ALIAS[n]);
  return D.edificios.find(b => [b.id, b.nombre, b.corto].some(x => norm(x) === n));
}

// ---- acceso ----
const VALID = ['publico', 'visitas', 'estudiantes', 'personal', 'restringido'];
function inferAcceso(nombre) {
  const n = norm(nombre);
  if (/quirofano|uci|cuidados intensivos|neonat|aislamiento|morgue|patolog|sala de operaciones|banco de sangre procesamiento/.test(n)) return 'restringido';
  if (/central de equipos|esteriliz|ceye|dosis unitaria|archivo|bodega|oficina|mantenimiento|lavander/.test(n)) return 'personal';
  if (/aula|posgrado|docencia|academic/.test(n)) return 'estudiantes';
  if (/hospitalizaci|sala de |encamado|pabellon/.test(n)) return 'visitas';
  return null;
}
const SI = ['si', 's', '1', 'true', 'x', 'yes', 'confirmado'];

const salas = [], errores = [], inferidas = [], sinAcceso = [];
let fotos = 0;
table.forEach((r, k) => {
  const linea = k + 2, nombre = get(r, 'nombre_sala');
  if (!nombre || /^ejemplo/i.test(nombre)) return;
  const b = buildingOf(get(r, 'edificio'));
  if (!b) { errores.push(`Línea ${linea}: edificio "${get(r, 'edificio')}" no existe en datos.js (use: ${D.edificios.map(x => x.nombre).join(', ')} o "Hospital Escuela").`); return; }
  let nivel = parseInt(get(r, 'piso'), 10);
  if (Number.isNaN(nivel)) { const i = b.niveles.findIndex(x => norm(x) === norm(get(r, 'piso'))); nivel = i < 0 ? NaN : i + 1; }
  if (!(nivel >= 1 && nivel <= b.niveles.length)) { errores.push(`Línea ${linea}: piso "${get(r, 'piso')}" no válido para ${b.nombre} (tiene ${b.niveles.length} niveles en datos.js).`); return; }
  let acceso = norm(get(r, 'acceso')).replace(/[^a-z]/g, '');
  if (acceso === 'restringida') acceso = 'restringido';
  if (acceso && !VALID.includes(acceso)) { errores.push(`Línea ${linea}: acceso "${get(r, 'acceso')}" no válido (use: ${VALID.join(', ')}).`); return; }
  if (!acceso) {
    const g = inferAcceso(nombre);
    if (g) { acceso = g; inferidas.push(`${nombre} → ${g}`); } else { acceso = 'publico'; sinAcceso.push(nombre); }
  }
  if (get(r, 'foto')) fotos++;
  const sala = { nombre, edificio: b.id, nivel, acceso, confirmado: SI.includes(norm(get(r, 'confirmado'))) };
  const set = (campo, v) => { if (v) sala[campo] = v; };
  set('descripcion', get(r, 'que_se_hace_ahi')); set('horario', get(r, 'horario'));
  set('referencia', get(r, 'como_llegar_referencia')); set('etiquetas', get(r, 'palabras') || get(r, 'etiquetas'));
  salas.push(sala);
});

fs.writeFileSync(outPath, `// GENERADO por convertir-csv.js a partir de ${path.basename(csvPath)}. No editar a mano: edite el CSV y vuelva a ejecutar.\n` +
  `// Las salas de un nivel reemplazan las hipótesis de ese mismo nivel en datos.js.\nwindow.SALAS = ${JSON.stringify(salas, null, 2)};\n`);

// ---- informe ----
console.log(`\nLeído: ${path.basename(csvPath)} (separador "${delim === '\t' ? 'tab' : delim}")`);
console.log(`Salas cargadas: ${salas.length}  →  ${path.basename(outPath)}`);
const niveles = new Map();
salas.forEach(s => { const k = `${s.edificio} · ${byId(s.edificio).niveles[s.nivel - 1]}`; niveles.set(k, (niveles.get(k) || 0) + 1); });
function byId(id) { return D.edificios.find(b => b.id === id); }
niveles.forEach((n, k) => {
  const [id, nivelNombre] = k.split(' · '); const b = byId(id), nv = b.niveles.indexOf(nivelNombre) + 1;
  const hip = D.areas.filter(a => a.edificio === id && a.nivel === nv).length;
  console.log(`  ${k}: ${n} sala(s)` + (hip ? `  (reemplaza ${hip} área(s) hipotéticas de datos.js)` : ''));
});
if (!salas.length) console.log('  (todavía no hay salas reales: las filas vacías y la de "Ejemplo" se ignoran; el mapa usa las hipótesis de datos.js)');
if (inferidas.length) console.log(`\nAcceso deducido por el nombre (revíselo y póngalo en la columna "acceso"):\n  ${inferidas.join('\n  ')}`);
if (sinAcceso.length) console.log(`\nSin "acceso" definido, se tratan como PÚBLICAS (tendrán ruta): ${sinAcceso.join(', ')}\n  → si alguna es restringida o solo del personal, rellene la columna "acceso".`);
if (salas.some(s => !s.confirmado)) console.log(`\n${salas.filter(s => !s.confirmado).length} sala(s) siguen "por confirmar" (columna "confirmado" distinta de "si").`);
if (fotos) console.log(`\nNota: la columna "foto" no se usa; las fotos no se publican en el mapa.`);
if (errores.length) { console.log('\nERRORES (esas filas NO se cargaron):'); errores.forEach(e => console.log('  ' + e)); process.exitCode = 1; }
