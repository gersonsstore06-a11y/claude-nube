# Prototipo – Mapa del Lobby

Demostración del mapa interactivo en 2D. **La distribución, los servicios y los horarios son inventados.** Hay que reemplazarlos con los datos reales después de medir el lobby.

## Qué hace
- Buscador de servicios que entiende palabras sin tilde y sinónimos (ej.: "medicina" encuentra Farmacia).
- Mapa del lobby: al tocar un área se marca la ruta y aparecen las indicaciones paso a paso.
- Códigos QR: cada QR abre la página con `?aqui=<punto>` y muestra "Usted está aquí".
  Puntos de ejemplo: `entrada`, `ascensores`, `pasillo-mi`, `pasillo-he`.
- Funciona en celular, se puede instalar y sirve sin internet después de la primera visita.
- Modo oscuro automático.

## Cómo verlo
Debe abrirse desde un servidor, no con doble clic, para que funcione sin internet:

```bash
cd "modelo 3d hospital escuela/prototipo-lobby"
python3 -m http.server 8000
```

Luego abra http://localhost:8000 en el navegador.

Para publicarlo gratis: GitHub Pages, Netlify o Cloudflare Pages (no necesita servidor propio).

## Editar los textos desde Google Sheets
1. Importe `datos-ejemplo.csv` en una hoja de Google Sheets.
2. Archivo → Compartir → Publicar en la web → formato **CSV**.
3. Pegue el enlace en `SHEET_CSV_URL`, dentro de `index.html`.

En la hoja, los pasos de "indicaciones" se separan con `|`. Las filas se combinan por `id`; un `id` nuevo necesita además su forma en el mapa (`GEOMETRIA` en `index.html`).

## Limitaciones conocidas
- Las indicaciones escritas parten siempre de la entrada; la línea del mapa sí parte del QR escaneado.
- Las rutas están dibujadas a mano: sirven para un piso, no son un cálculo automático.
