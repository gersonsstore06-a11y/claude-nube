// Servidor local para probar el mapa. Solo sirve esta carpeta y solo en este computador (127.0.0.1).
// Para compartirlo con otras personas no hace falta servidor: publique la carpeta como sitio estático.
const http = require('http');
const fs = require('fs');
const path = require('path');
const { exec } = require('child_process');

const PORT = Number(process.env.PORT) || 3000;
const MIME_TYPES = {
  '.html': 'text/html; charset=utf-8', '.js': 'application/javascript; charset=utf-8', '.css': 'text/css; charset=utf-8',
  '.json': 'application/json; charset=utf-8', '.svg': 'image/svg+xml', '.png': 'image/png', '.jpg': 'image/jpeg', '.ico': 'image/x-icon',
};

const server = http.createServer((req, res) => {
  let rel;
  try { rel = decodeURIComponent(req.url.split('?')[0]); } catch { rel = '/'; }
  if (rel === '/') rel = '/index.html';
  const filePath = path.join(__dirname, path.normalize(rel));
  if (!filePath.startsWith(__dirname + path.sep)) { res.writeHead(403); return res.end('403'); }   // nada fuera de la carpeta
  fs.readFile(filePath, (err, content) => {
    if (err) {
      res.writeHead(err.code === 'ENOENT' ? 404 : 500, { 'Content-Type': 'text/plain; charset=utf-8' });
      return res.end(err.code === 'ENOENT' ? '404: Archivo no encontrado' : '500: Error del servidor');
    }
    res.writeHead(200, { 'Content-Type': MIME_TYPES[path.extname(filePath).toLowerCase()] || 'application/octet-stream' });
    res.end(content);
  });
});

server.listen(PORT, '127.0.0.1', () => {
  const url = `http://localhost:${PORT}`;
  console.log(`\n===========================================`);
  console.log(`  Mapa 3D del Hospital iniciado con éxito  `);
  console.log(`  Abre en tu navegador: ${url}`);
  console.log(`===========================================\n`);
  const abrir = process.platform === 'win32' ? `start "" ${url}` : process.platform === 'darwin' ? `open ${url}` : `xdg-open ${url}`;
  exec(abrir, () => {});
});
