# Mapa 3D del Hospital – versión 2

Mejoras sobre `../mapa-hospital/` (esa carpeta queda intacta).

## Cómo verlo
- **En su computador (Windows):** doble clic en `iniciar.bat` (necesita [Node.js](https://nodejs.org)). Abre http://localhost:3000.
- **En otro sistema:** `node servidor.js`.
- **Para compartirlo:** no hace falta servidor. Publique esta carpeta como sitio estático (GitHub Pages, Netlify, Cloudflare Pages). Ya no depende de internet para el 3D (Three.js está en `lib/`); solo la foto satelital y la tipografía se descargan.

Calidad: se elige sola según el equipo (celulares arrancan en calidad ligera). Se puede forzar con `?calidad=alta` o `?calidad=baja` al final de la dirección. El botón **HD** la cambia a mano.

## Cargar las salas reales (sin tocar código)
1. Llene `levantamiento-salas.csv` en Excel o Google Sheets (separador `;`, guardar como CSV UTF-8).
2. Doble clic en `actualizar.bat` (o `node convertir-csv.js`). Genera `salas.js` y le informa de errores y avisos.
3. Recargue el mapa.

Columnas: `edificio; piso; nombre_sala; que_se_hace_ahi; horario; como_llegar_referencia; foto; confirmado; acceso`
- **edificio:** `Lobby`, `Hospital Escuela` (= Bloque Médico Quirúrgico), `Materno Infantil`, `Patología`. *Si "Hospital Escuela" no es el BMQ, cámbielo en `ALIAS` dentro de `convertir-csv.js`.*
- **piso:** número (1 = planta baja). Debe existir en `datos.js` (BMQ 6 niveles, BMI 4, Lobby 1).
- **acceso** (opcional pero importante): `publico`, `visitas`, `estudiantes`, `personal`, `restringido`. Las de `personal` y `restringido` **no trazan ruta**: el mapa dice que pregunten en Información. Si la deja vacía, el script lo deduce por el nombre (quirófano, UCI, neonatal, archivo…) y se lo avisa; si no puede, la trata como pública.
- **confirmado:** `si` cuando la sala fue verificada en sitio.
- **como_llegar_referencia:** aparece en negrita como último paso (ej. "A la derecha al entrar, frente a las gradas").
- **foto:** no se usa; las fotos no se publican.
- Las filas vacías y las que empiezan con "Ejemplo" se ignoran.
- Las salas de un nivel **reemplazan las hipótesis** de ese mismo nivel en `datos.js`.

## Qué cambió respecto a la versión original
- **Fluidez:** solo se dibuja cuando algo cambia (antes dibujaba sin parar), las sombras se calculan una vez, el posprocesado se crea solo si hace falta, la foto satelital se sube a la GPU por lotes y, en equipos modestos, se piden menos teselas y menos resolución. Si un equipo va lento, baja la calidad solo y avisa.
- **Sin metros inventados:** las indicaciones ya no dan distancias ni rumbos. Si la posición dentro del nivel no está medida, el último tramo de la ruta se dibuja tenue y hay un aviso visible.
- **Áreas restringidas / solo personal:** sin ruta; muestran un aviso rojo.
- **Etiquetas:** estacionamientos y vecinos solo aparecen al acercarse; ya no se montan sobre la tarjeta.
- **Celular:** los pasos de "Cómo llegar" van plegados para no tapar el mapa.
- **Sin dependencia de CDN** para Three.js (`lib/three`, licencia MIT).
- **Servidor** local solo en 127.0.0.1 y compatible con Mac/Linux.

## Pendiente (requiere datos reales)
Posiciones medidas de cada sala (`pos: [x, z]` en `datos.js`), puertas reales de cada edificio, niveles del Materno Infantil, y verificación de todo lo marcado "Por confirmar".
