// =====================================================================
//  DATOS DEL MAPA — edita este archivo, no hace falta tocar index.html
// =====================================================================
//  Coordenadas en METROS. Origen (0,0) = centro del terreno del hospital.
//  x = hacia el este, z = hacia el sur (negativo = norte).
//  Formas de terreno y edificios: © colaboradores de OpenStreetMap (ODbL).
//
//  acceso: 'publico' | 'visitas' | 'estudiantes' | 'personal' | 'restringido'.
//          'personal' y 'restringido' NO trazan ruta (el mapa dice que pregunten en Información).
//  confirmado: false  -> se muestra con la etiqueta "Por confirmar".
//  TODO lo marcado así es HIPÓTESIS DE TRABAJO y se verificará con
//  documentación y personal del hospital antes de darlo por definitivo.
// =====================================================================

window.DATOS = {

  // Punto (0,0) del mapa en coordenadas GPS (centro del terreno). Se usa para
  // colocar la foto satelital exactamente debajo del modelo.
  origen: { lat: 14.0891537, lon: -87.1972406 },

  // Contorno del terreno (OSM way 961803937)
  terreno: [[-131,-42],[-82,-63],[-45,-79],[-19,-91],[-1,-99],[17,-107],[28,-91],[31,-86],[34,-81],[58,-45],[71,-27],[77,-19],[129,48],[131,55],[128,62],[116,71],[106,77],[67,96],[49,107],[7,45],[0,40],[-7,38],[-21,43],[-83,68],[-131,-42]],

  // Entradas y salidas. tipo: 'entrada' | 'salida' | 'entrada y salida'
  entradas: [
    { id: 'principal', nombre: 'Entrada principal', tipo: 'entrada y salida', x: -3, z: 38, confirmado: false },
    { id: 'vehicular', nombre: 'Acceso vehicular', tipo: 'entrada y salida', x: -55, z: 57, confirmado: false },
  ],

  // Estacionamientos (ubicación aproximada, por confirmar)
  estacionamientos: [
    { nombre: 'Estacionamiento 1', forma: [[-70,30],[-35,20],[-30,38],[-62,50]], confirmado: false },
    { nombre: 'Estacionamiento 2', forma: [[-118,-30],[-96,-30],[-96,10],[-108,10]], confirmado: false },
  ],

  // Edificios. "niveles" va de abajo hacia arriba (el primero es planta baja).
  edificios: [
    {
      id: 'BMQ', nombre: 'Bloque Médico Quirúrgico', corto: 'BMQ', color: '#2f8fd8', confirmado: false,
      niveles: ['Planta baja', 'Segundo piso', 'Tercer piso', 'Cuarto piso', 'Quinto piso', 'Sexto piso'],
      forma: [[0,29],[16,-8],[24,-28],[27,-33],[-44,-63],[-51,-45],[-56,-35],[-64,-38],[-67,-32],[-76,-36],[-71,-48],[-84,-54],[-92,-37],[-88,-27],[-75,-21],[-72,-28],[-49,-18],[-39,-43],[-19,-35],[-39,13],[0,29]],
    },
    {
      id: 'LOB', nombre: 'Lobby', corto: 'Lobby', color: '#f2a03d', confirmado: false,
      niveles: ['Planta baja'],
      forma: [[7,26],[39,63],[77,31],[67,18],[79,8],[60,-17],[59,-20],[55,-20],[54,-19],[29,-32],[24,-28],[16,-8],[19,-2],[7,26]],
    },
    {
      id: 'BMI', nombre: 'Bloque Materno Infantil', corto: 'BMI', color: '#d95fa6', confirmado: false,
      // Número de pisos sin confirmar: NO asumimos que sea igual al BMQ
      niveles: ['Planta baja', 'Segundo piso', 'Tercer piso', 'Cuarto piso'],
      forma: [[53,88],[111,40],[93,18],[77,31],[39,63],[35,66],[53,88]],
    },
    {
      id: 'PAT', nombre: 'Patología', corto: 'Patología', color: '#7a5fd9', confirmado: false,
      niveles: ['Planta baja'],
      forma: [[0,-88],[22,-88],[22,-66],[0,-66],[0,-88]],   // forma y ubicación provisionales
    },
  ],

  // Edificios vecinos (solo referencia, en gris)
  vecinos: [
    { nombre: '', forma: [[104,-52],[115,-32],[132,-41],[122,-61],[104,-52]] },
    { nombre: '', forma: [[-10,-123],[-2,-104],[8,-109],[0,-128],[-10,-123]] },
    { nombre: '', forma: [[106,-11],[102,-8],[129,30],[134,27],[106,-11]] },
    { nombre: '', pisos: 3, forma: [[-76,69],[-58,60],[-41,96],[-27,90],[-23,97],[-9,91],[-24,60],[-6,51],[17,100],[-48,130],[-76,69]] },
    { nombre: 'Medicina Legal', forma: [[-141,-73],[-88,-96],[-84,-87],[-81,-88],[-76,-76],[-78,-75],[-76,-70],[-129,-47],[-141,-73]] },
    { nombre: '', forma: [[-60,-117],[-52,-117],[-51,-125],[-43,-125],[-42,-109],[-51,-109],[-51,-102],[-60,-101],[-60,-117]] },
    { nombre: '', forma: [[-49,-102],[-49,-107],[-33,-109],[-31,-96],[-23,-97],[-23,-94],[-24,-90],[-38,-85],[-41,-89],[-44,-89],[-47,-94],[-54,-93],[-56,-99],[-49,-102]] },
    { nombre: '', forma: [[-78,-100],[-74,-106],[-63,-98],[-67,-93],[-78,-100]] },
    { nombre: '', forma: [[-149,23],[-117,10],[-95,62],[-127,75],[-149,23]] },
  ],

  // Áreas por nivel (matriz inicial de modelado — hipótesis de trabajo).
  //   nivel: 1 = planta baja, 2 = segundo piso, ...
  //   pos: [x, z] opcional. Si no se pone, el área se acomoda automáticamente
  //        dentro del piso (distribución ilustrativa, NO es el plano real).
  areas: [
    // BMQ — Planta baja
    { nombre: 'Emergencias', acceso: 'publico', edificio: 'BMQ', nivel: 1, etiquetas: 'urgencias', confirmado: false },
    { nombre: 'Triaje', acceso: 'publico', edificio: 'BMQ', nivel: 1, etiquetas: 'triage clasificacion', confirmado: false },
    { nombre: 'Consulta externa', acceso: 'publico', edificio: 'BMQ', nivel: 1, etiquetas: 'consultas citas', confirmado: false },
    { nombre: 'Diagnóstico (laboratorio e imágenes)', acceso: 'publico', edificio: 'BMQ', nivel: 1, etiquetas: 'laboratorio rayos x imagenes examenes', confirmado: false },
    // BMQ — Segundo piso
    { nombre: 'Quirófanos', acceso: 'restringido', edificio: 'BMQ', nivel: 2, etiquetas: 'cirugia sala de operaciones', confirmado: false },
    { nombre: 'Central de equipos', acceso: 'personal', edificio: 'BMQ', nivel: 2, etiquetas: 'esterilizacion ceye', confirmado: false },
    { nombre: 'Servicios especializados', acceso: 'publico', edificio: 'BMQ', nivel: 2, etiquetas: '', confirmado: false },
    // BMQ — Tercer piso
    { nombre: 'Pediatría', acceso: 'visitas', edificio: 'BMQ', nivel: 3, etiquetas: 'niños', confirmado: false },
    { nombre: 'Hospitalización infantil', acceso: 'visitas', edificio: 'BMQ', nivel: 3, etiquetas: 'niños salas', confirmado: false },
    // BMQ — Cuarto piso
    { nombre: 'Hospitalización de adultos', acceso: 'visitas', edificio: 'BMQ', nivel: 4, etiquetas: 'salas encamados', confirmado: false },
    { nombre: 'Cirugía', acceso: 'visitas', edificio: 'BMQ', nivel: 4, etiquetas: 'hospitalizacion cirugia', confirmado: false },
    { nombre: 'Medicina Interna', acceso: 'visitas', edificio: 'BMQ', nivel: 4, etiquetas: 'medicina', descripcion: 'Ubicación supuesta: no estaba en la matriz inicial.', confirmado: false },
    // BMQ — Quinto piso
    { nombre: 'Neonatología', acceso: 'restringido', edificio: 'BMQ', nivel: 5, etiquetas: 'recien nacidos bebes', confirmado: false },
    { nombre: 'Farmacia de dosis unitaria', acceso: 'personal', edificio: 'BMQ', nivel: 5, etiquetas: 'medicamentos', confirmado: false },
    // BMQ — Sexto piso
    { nombre: 'UCIN', acceso: 'restringido', edificio: 'BMQ', nivel: 6, etiquetas: 'cuidados intensivos neonatales', confirmado: false },
    { nombre: 'Cuidados neonatales', acceso: 'restringido', edificio: 'BMQ', nivel: 6, etiquetas: 'recien nacidos', confirmado: false },
    { nombre: 'Áreas académicas', acceso: 'estudiantes', edificio: 'BMQ', nivel: 6, etiquetas: 'aulas docencia estudiantes', confirmado: false },
    // Patología
    { nombre: 'Patología', acceso: 'restringido', edificio: 'PAT', nivel: 1, etiquetas: 'anatomia patologica biopsias', confirmado: false },
  ],
};
