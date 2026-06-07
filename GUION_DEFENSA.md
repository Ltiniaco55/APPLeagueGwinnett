# Guión de Defensa — Gwinnett Soccer League Hub
**Luciano Tiniaco · 2º DAW ESIC · 2026**

> **Timing total: 20 min → 10 min presentación + 5 min demo + 5 min preguntas**  
> Cada slide tiene un tiempo objetivo. Respeta el ritmo: lo que no dices en la slide lo defiendes en preguntas.  
> Navega con las flechas del teclado. Los enlaces de demo abren la web directamente desde la presentación.

---

## SLIDE 1 — Portada `~30 seg`

"Buenos días. El proyecto se llama **Gwinnett Soccer League Hub**: una plataforma web para gestionar ligas de fútbol amateur de principio a fin. Cubro desde la inscripción de un jugador hasta la clasificación automática, con sistema de roles, workflows de aprobación y portal público. Todo construido desde cero — PHP, MySQL y JavaScript puro, sin frameworks."

---

## SLIDE 2 — Problema y Solución `~45 seg`

"El problema: una liga amateur gestiona jugadores, partidos y equipos con Excel o WhatsApp. Sin trazabilidad, sin control de acceso, con datos dispersos.

La solución son tres perfiles de usuario con responsabilidades claras: el **ADMIN** tiene control total de la competición, el **STAFF** gestiona su equipo con restricciones explícitas, y el **USUARIO** tiene una experiencia personalizada con favoritos y calendario. El hilo técnico que une todo eso es el sistema de roles y el workflow de validación de jugadores."

---

## SLIDE 3 — Arquitectura `~1 min`

"La arquitectura es MVC implementada a mano. Toda petición entra por `index.php`, el único punto de entrada del backend. Ahí se cargan modelos y controladores, se registran las 40+ rutas REST y se ejecuta el dispatch del Router.

El Router lee la URL, identifica qué controlador tiene que llamar, y mira cómo está definido ese método para pasarle los datos correctos. Si el método espera un número, le pasa el ID de la URL. Si espera un array, le pasa el contenido del body. Todo sin framework, el código está ahí y se puede leer directamente."

*(Señalar diagrama: Frontend → index.php → Router → Controller → Model → Database → JSON → render)*

---

## SLIDE 4 — Base de datos `~1 min`

"El modelo relacional se articula en cuatro entidades core — `usuario`, `equipos`, `ligas`, `jugador` — y tres tablas puente clave.

`equipo_liga` permite que un equipo compita en varias competiciones. `equipo_jugador` es la tabla de plantillas: tiene un campo `estado` con valores `PENDIENTE`, `ALTA` o `BAJA`, que es el que habilita todo el workflow de aprobación. `entrenador_equipo` vincula usuarios STAFF a sus equipos con estado 'activo'.

La tabla `clasificacion` no es estática: se regenera en transacción cada vez que un resultado cambia. `utf8mb4` con `InnoDB` para integridad referencial completa."

---

## SLIDE 5 — Rutas `~30 seg`

"El `index.php` hace cuatro cosas: declara qué orígenes pueden hablar con el backend — en este caso solo localhost, para que el navegador no bloquee las peticiones del frontend —, carga los archivos necesarios, registra las rutas y ejecuta el dispatch. Si la URI es raíz, redirige al `home.html`.

Los verbos cubiertos son GET, POST, PUT, PATCH y DELETE. Las rutas siguen convenciones REST: `/admin/partidos` para operaciones protegidas, `/staff/jugadores` para las del STAFF, sin prefijo para las públicas."

---

## SLIDE 6 — Roles `~1 min`

"La autenticación se gestiona por sesión PHP. En pantalla tenéis el flujo: el usuario manda sus credenciales, el backend verifica la contraseña, regenera la sesión para evitar ataques, guarda el ID del usuario y devuelve su rol con los permisos.

Cada rol tiene permisos concretos. El ADMIN puede hacer cualquier cosa. El STAFF solo puede operar sobre su propio equipo — el sistema lo verifica en cada petición cruzando la base de datos. Cualquier intento fuera de sus permisos devuelve un 403 y corta la ejecución."

---

## SLIDE 7 — Workflow Admin `~45 seg`

"El admin construye la competición en secuencia: crea la liga con su formato, asigna equipos, crea partidos.

Al crear un partido se validan cinco condiciones antes de persistir: liga existe, equipos existen, ambos pertenecen a esa liga, no hay duplicado en esa ronda, y ninguno tiene conflicto de horario. Si el partido se marca como 'jugado', se dispara automáticamente `regenerarClasificacion()`."

---

## SLIDE 8 — Workflow Jugadores `~1 min`

"El STAFF no puede insertar un jugador directo en plantilla. Solicita un alta que queda en estado `PENDIENTE`. El admin aprueba o rechaza.

Al solicitar el alta el sistema hace detección de duplicados por nombre + apellido + fecha de nacimiento: si el jugador ya existe, reutiliza su registro. Si está en otros equipos, devuelve un campo `avisos` en el JSON — no bloquea, pero señaliza para revisión del admin.

Si el jugador es menor de 18, los campos del tutor legal son obligatorios. La edad se calcula con `DateTime::diff()` en tiempo de ejecución.

El ADMIN también puede hacer altas directas para no depender del STAFF en gestión interna."

---

## SLIDE 9 — Formatos de liga y Clasificación `~1 min`

"Cada liga tiene `formato_liga`: `JORNADAS`, `ELIMINATORIA` o `AMISTOSO`. Este valor condiciona los tipos de ronda permitidos al crear partidos y si hay o no clasificación.

En JORNADAS, la clasificación se mantiene activa con PJ, PG, PE, PP, GF, GC, DG y PTS. La lógica de regeneración es una transacción PDO: DELETE de todas las filas de la liga, lectura de partidos con `estado = 'jugado'`, cálculo en PHP de los acumulados y re-INSERT. Si algo falla hay `rollBack()`. El orden es PTS → DG → GF → PG → nombre de club."

---

## SLIDE 10 — Frontend y JS `~45 seg`

"Frontend en HTML/CSS/JS puro. El `layout.js` hace fetch de `header.html` y `footer.html` con `cache: 'no-store'` y los parsea con `<template>` antes de inyectarlos — así el HTML nunca toca el DOM sin sanitizar. Comprueba si ya existen antes de inyectar para ser idempotente.

`sesionactiva.js` va más allá de consultar sesión: llama también a `GET /usuarios/{id}/equipos-staff` para saber si el usuario tiene equipos asignados, y con eso construye el menú de navegación dinámicamente según el rol. Usa un `waitForElement()` con polling de 50ms para esperar a que el DOM esté listo antes de inyectar el mini-menu. Expone `window.gyslLogout()` globalmente para el botón de logout del HTML.

`adminNav.js` expone `window.checkAdminNav()` globalmente — `sesionactiva.js` lo llama tras el logout para eliminar la barra de admin del DOM sin recargar página. `proximoPartidos.js` hace `Promise.all()` para cargar ligas y partidos en paralelo, filtra solo los futuros y no cancelados, y renderiza un carrusel paginado de 4 en 4."

---

## SLIDES 11–12 — Screenshots + Demo `~30 seg`

"Aquí veis las pantallas principales en funcionamiento. Pasamos directamente a la demo."

---

## DEMO EN VIVO `5 minutos — recorrido exacto`

> Abre los enlaces desde la slide de Demo. Sigue este orden sin improvisar.

| Tiempo | Acción | Qué destacas |
|--------|--------|--------------|
| 0:00 | Abrir `home.html` | Próximos partidos, filtros por liga, botón favoritos |
| 0:45 | Login como ADMIN | La navegación cambia según el rol |
| 1:30 | `admin-dashboard.html` | CRUD de ligas y equipos, formatos disponibles |
| 2:15 | `admin-partidos.html` | Crear partido: mostrar las validaciones (conflicto horario) |
| 3:00 | `staff-jugadores.html` | Solicitudes pendientes de alta |
| 3:45 | `equiposFavoritos.html` | Favoritos del usuario y próximos partidos |
| 4:30 | `calendario.html` | Vista mensual, filtro por liga |
| 5:00 | Volver a presentación → Slide Conclusiones | |

---

## SLIDE 13 — Conclusiones `~30 seg`

"Para cerrar: una plataforma completa con arquitectura MVC propia, control de acceso por roles, workflows validados y clasificación automática — sin un solo framework externo. El sistema es modular: añadir un nuevo rol, un endpoint o un formato de competición no toca lo existente, solo lo extiende. Gracias."

---

---

# BLOQUE DE PREGUNTAS ANTICIPADAS

> Preparate estas respuestas. Responde siempre con código concreto o nombres de archivo.

---

## ARQUITECTURA

**¿Por qué no usaste Laravel?**
"Decisión deliberada: el proyecto final de DAW debe demostrar que entiendo qué hay debajo, no que sé usar un framework. Implementar el router, la auth y el acceso a datos desde cero obliga a entender cada capa. Con Laravel habría menos código pero menos evidencia de comprensión."

**¿Cómo funciona el Router exactamente?**
"`dispatch()` obtiene método HTTP y URI. Convierte el patrón — `/usuarios/{id}` — a regex: `/^\/usuarios\/([^\/]+)$/`. Si hay match, extrae los parámetros. Usa `ReflectionMethod` para ver el tipo del primer parámetro del controlador: si es `int` pasa el ID del path, si es `array` pasa el body completo. Si no hay match, devuelve 404 JSON."

**¿Qué hace el .htaccess?**
"Redirige todo lo que no sea un archivo o directorio físico al `index.php`. Es el patrón front controller estándar: cualquier ruta llega al punto de entrada único."

---

## SEGURIDAD

**¿Cómo proteges las contraseñas?**
"`password_hash($pwd, PASSWORD_DEFAULT)` al insertar — bcrypt actualmente. Verificación con `password_verify()`. La columna `pwd` se elimina de todos los arrays antes de cualquier respuesta JSON. La regex de validación exige mínimo 9 caracteres, mayúscula, minúscula, número y especial."

**¿Qué es `session_regenerate_id(true)`?**
"Previene session fixation: si alguien consigue el session ID antes del login, después del login ese ID ya no vale porque se regenera. El `true` elimina la sesión anterior."

**¿Cómo funciona la verificación de email?**
"Código de 6 dígitos generado con `random_int()`, se hashea con `password_hash()` y se guarda en BD con expiración. El código en claro solo viaja al email. Al verificar, `password_verify()` contra el hash y `strtotime()` para comprobar expiración. Nunca se guarda el código en claro."

**¿Proteges contra SQL injection?**
"Sí. PDO con prepared statements en todos los accesos. `ATTR_EMULATE_PREPARES => false` para que sean prepared statements reales del motor, no emulados por PHP."

---

## BASE DE DATOS

**¿Por qué utf8mb4?**
"El `utf8` de MySQL es solo 3 bytes por carácter — excluye emojis y varios caracteres internacionales. `utf8mb4` es el UTF-8 real de 4 bytes."

**¿Por qué la clasificación se regenera completa en vez de actualizarse?**
"Garantía de consistencia. Una actualización incremental puede dejar inconsistencias si se modifica o cancela un partido anterior. La regeneración es atómica — transacción PDO — y correcta siempre. Para 30 equipos y 300 partidos por temporada, el coste es insignificante."

**¿Qué unique constraints tienes?**
"`ligas`: `(nombre_liga, temporada, categoria)`. `equipos`: `(club, categoria)`. `usuario`: `email` y `(oauth_provider, oauth_id)`. Los duplicados en `equipo_jugador` se controlan por lógica de aplicación antes de insertar."

---

## ROLES Y PERMISOS

**¿Cómo impides que STAFF acceda a datos de otro equipo?**
"`requerirStaffDeEquipo($id_equipo)` hace un JOIN entre `entrenador_equipo` y `entrenadores` filtrando por el `id_usuario` de sesión y el `id_equipo` recibido. Solo pasa si el estado de la relación es 'activo'. Si el usuario es ADMIN, cortocircuita directamente sin hacer la query."

**¿Por qué el STAFF no puede aprobar sus propios jugadores?**
"Separación de responsabilidades y prevención de fraude deportivo. El STAFF que inscribe no puede validar lo que inscribe — el ADMIN actúa como árbitro neutral del proceso."

---

## FRONTEND

**¿Cómo protege el frontend las páginas de admin?**
"Las páginas HTML son estáticas, accesibles directamente. Pero toda acción que muta datos llama a un endpoint que verifica sesión y rol. Sin sesión, la API devuelve 401 y la página se queda vacía. La protección real es el backend — el HTML es solo UI."

**¿Por qué Vanilla JS y no React?**
"El mismo argumento que para PHP: demostrar comprensión del stack sin toolchain de compilación. Además facilita la instalación en XAMPP sin npm ni webpack."

**¿Por qué usas `<template>` para inyectar los layouts en vez de `innerHTML`?**
"`innerHTML` directamente en el body puede provocar problemas si el HTML no está bien formado o tiene scripts. El elemento `<template>` parsea el HTML en un fragmento de documento inerte, sin ejecutar scripts ni renderizar nada, y luego se insertan los nodos resultantes. Es la forma correcta de inyectar HTML externo de forma segura."

**¿Cómo funcionan los toasts y las confirmaciones del panel admin?**
"`toast.js` expone dos funciones globales. `showToast()` crea un elemento div, lo añade a un contenedor fijo en el body, añade la clase `is-visible` en el siguiente frame con `requestAnimationFrame` para que la transición CSS se dispare, y lo elimina del DOM después de 3,5 segundos. Se pueden apilar varios a la vez.

`showConfirm()` devuelve una `Promise` que resuelve `true` o `false`. Crea un overlay modal con dos botones — Confirmar y Cancelar — y registra los listeners. Cuando el usuario pulsa cualquiera de los dos, limpia los listeners y llama a `resolve(true/false)`. Por eso todos los DELETE del panel admin usan `const confirmado = await showConfirm(...)` — si el usuario cancela, la Promise devuelve `false` y el código simplemente hace `return` sin ejecutar nada."

**¿Por qué `seleccionarProximosPartidosFavoritos()` usa DISTINCT?**
"Porque si un usuario tiene dos equipos favoritos que juegan entre sí, el partido aparecería dos veces en el resultado — una por cada equipo favorito. El DISTINCT elimina ese duplicado. También usa `bindValue` con `PDO::PARAM_INT` para el LIMIT porque MySQL no acepta un valor de tipo string en el LIMIT de un prepared statement."

**¿Por qué los favoritos del carrusel del home usan localStorage?**
"El carrusel del home necesita saber los favoritos del usuario para filtrar partidos — pero ese filtro es puramente visual y local. El localStorage evita una llamada extra al API en cada carga del home. Los favoritos reales (persistidos por usuario) se guardan en la base de datos vía el endpoint `/favoritos/equipos` cuando el usuario los añade o elimina desde `equiposFavoritos.html`. El localStorage actúa como caché local de esa lista."

**¿Qué hace `waitForElement()` en sesionactiva.js?**
"Es un helper que sondea el DOM cada 50ms hasta encontrar un selector o hasta que pasa el timeout (3 segundos). Es necesario porque `sesionactiva.js` carga antes de que `layout.js` haya terminado de inyectar el mini-menu en el DOM. En vez de asumir un orden de carga, el script espera activamente a que el elemento host aparezca."

**¿Por qué `window.checkAdminNav()` es global?**
"Porque necesita ser invocado desde fuera del módulo. Cuando el usuario hace logout, `sesionactiva.js` llama a `window.checkAdminNav()` para que el adminNav compruebe de nuevo el rol — como ya no hay sesión, devuelve null y el nav se elimina del DOM. Si fuera privado dentro del IIFE no sería accesible."

**¿Por qué `clasificacion.html` redirige al cargarse?**
"Porque la clasificación solo tiene sentido para ligas con formato `JORNADAS`. Si alguien llega a `clasificacion.html?liga=X&formato=ELIMINATORIA`, al arrancar el script lee el parámetro y hace `window.location.replace()` a `resultados.html` con los mismos params. Así nunca se ve una clasificación vacía — simplemente aterrizas en la vista correcta según el formato."

**¿Cómo sabe `resultados.html` qué interfaz mostrar?**
"Lee el parámetro `formato` de la URL y despacha a tres funciones distintas: `initJornadas()`, `initEliminatoria()` o `initAmistoso()`. Cada una construye sus propios filtros en el DOM y registra sus propios event listeners. El array `ORDEN_RONDAS` en el frontend espeja exactamente los valores permitidos en el backend, así el orden siempre es coherente: Fase de grupos → Octavos → Cuartos → Semifinal → Final."

**¿Cómo funciona el calendario?**
"La cuadrícula es siempre de 42 celdas — 6 semanas × 7 días. Para alinear al lunes calculo el offset del primer día: `(firstDay.getDay() + 6) % 7`. Eso convierte el domingo (0 en JS) en 6 y el lunes (1) en 0. Los partidos se agrupan por clave de fecha `YYYY-MM-DD`, se muestran máximo 3 por celda con un contador `+N partido(s)` si hay más. Los clicks en los eventos usan event delegation sobre el grid — un solo listener en vez de uno por card."

**¿Por qué los favoritos en `equiposFavoritos.html` no usan `<select>` nativo?**
"Para controlar el comportamiento exactamente: deshabilitar visualmente opciones ya añadidas mostrando '· ya añadido', aplicar estilos propios del sistema de diseño, y cerrar los dropdowns cuando el usuario hace clic fuera. El `<select>` nativo no permite ese nivel de personalización de forma consistente entre navegadores."

**¿Por qué las páginas admin no usan `layout.js`?**
"Porque tienen una experiencia completamente diferente al portal público. Las páginas admin cargan `global.css`, `admin.css` y `admin-nav.css` — su propio sistema visual. Solo comparten `adminNav.js` y `toast.js`. No tienen header de navegación pública ni footer: la interfaz admin es deliberadamente distinta para que el contexto quede claro."

**¿Cómo protege `staff-jugadores.html` el acceso sin login?**
"`checkAccess()` llama a `GET /auth/me` al arrancar. Si no hay sesión o el rol no es STAFF ni ADMIN, reemplaza todo el `document.body` con una pantalla 404 personalizada. Es una protección client-side — la real sigue en el backend con `requerirRol()`, pero evita que el usuario vea la UI sin permisos."

**La misma página sirve para STAFF y ADMIN, ¿cómo?**
"`aplicarModoPorRol()` adapta los textos del formulario según el rol. Si eres ADMIN, el botón dice 'Dar de Alta' y el fetch va a `/admin/jugadores/alta-directa`; si eres STAFF dice 'Solicitar Alta' y va a `/staff/jugadores/alta`. Un formulario, dos comportamientos, cero páginas duplicadas."

**¿Por qué se calcula la edad tanto en frontend como en backend?**
"El frontend calcula la edad en `onAltaFechaChange()` para mostrar u ocultar el bloque de datos del tutor en tiempo real — mejora de UX. El backend lo recalcula igualmente en el controlador porque nunca confía en el cliente. Son dos capas independientes: una para la experiencia, otra para la seguridad."

**¿Cómo funciona la subida de foto sin input visible?**
"`abrirSubirFoto()` crea un `<input type='file'>` dinámicamente en memoria, llama a `.click()` para abrir el selector de archivo y cuando el usuario elige uno, hace el fetch con `FormData`. El input nunca se inserta en el DOM — es un truco para activar el selector nativo desde cualquier botón."

**¿Por qué el modal de login no se carga al arrancar la página?**
"Lazy loading deliberado. El HTML del modal se inyecta en el body solo la primera vez que el usuario hace clic en el botón de cuenta. Ahorra una petición en cada carga de página para todos los usuarios que ya tienen sesión activa o que nunca van a hacer login. `register.js` y `forgotPassword.js` también se cargan dinámicamente en ese momento con un timestamp de cache-busting."

**¿Por qué usas `dataset.wired` en los event listeners del login?**
"Para evitar registrar el mismo listener dos veces. `wireEvents()` podría llamarse más de una vez si el modal se manipula en múltiples ciclos. El flag `dataset.wired = 'true'` garantiza que cada listener se registra exactamente una vez."

**¿Qué pasa si el usuario empieza a registrarse y cierra el modal?**
"Al cerrar, `closeModal()` llama a `window.cancelarRegistroPendiente()` si existe — definida en `register.js`. Esa función llama a `POST /auth/email/eliminar-no-verificado` para borrar el usuario sin verificar de la base de datos. Sin esto, la tabla `usuario` acumularía registros huérfanos con `email_verificado = 0`."

**¿Por qué el login usa `FormData` y no JSON?**
"Se envía como `FormData` por consistencia con el resto de formularios que tienen ficheros adjuntos. El backend lo recibe vía `$_POST`, que PHP parsea automáticamente para multipart. El `parseBody()` del Router primero intenta leer JSON de `php://input`; si falla, cae en `$_POST`."

---

## WORKFLOW DE JUGADORES — MODEL

**¿Qué pasa en la base de datos cuando se rechaza un alta?**
"Se hace un DELETE directo de la fila en `equipo_jugador`. Un alta rechazada no deja rastro en la tabla — no existe estado 'RECHAZADO'. Si el STAFF quiere volver a intentarlo, envía una nueva solicitud desde cero."

**¿Y cuando se aprueba una baja?**
"También DELETE. La baja aprobada elimina la relación del jugador con ese equipo y liga. El jugador sigue existiendo en la tabla `jugador`, pero ya no tiene vínculo activo con ese equipo."

**¿Cómo impides que el STAFF solicite una baja si ya hay una acción pendiente?**
"`solicitarBaja()` tiene en su WHERE `AND estado = 'ALTA' AND accion_solicitada IS NULL`. Si ya hay un PENDIENTE de cualquier tipo, el UPDATE no afecta ninguna fila y devuelve `rowCount() = 0`, que el controlador interpreta como 409."

**¿Por qué el `IN` dinámico de `getPlantillaStaff()` es seguro contra SQL injection?**
"Porque los valores no se concatenan al SQL — se generan placeholders `?` con `array_fill(0, count($ids), '?')` y los valores reales se pasan como parámetros del prepared statement. MySQL nunca los interpreta como SQL."

**¿Cuál es la diferencia entre `insertarPendiente()` e `insertarRelacion()`?**
"`insertarPendiente()` es para el STAFF: inserta con `estado = PENDIENTE` y `accion_solicitada = ALTA`, queda pendiente de aprobación. `insertarRelacion()` es para el ADMIN en alta directa: inserta con `estado = ALTA` directamente, sin pasar por ningún flujo de aprobación."

---

## JUGADORES

**¿Qué pasa si el mismo jugador está en dos equipos?**
"`buscarCoincidenciasEnOtrosEquipos()` busca por nombre + apellido + fecha de nacimiento en otros equipos. Si hay coincidencia, la solicitud pasa pero la respuesta incluye `avisos` con los datos. El admin decide — hay casos legítimos como un jugador en dos categorías distintas."

**¿Hay race condition en la asignación de dorsal?**
"El modelo usa un UPDATE condicionado: `WHERE dorsal IS NULL`. Es una operación atómica en InnoDB. El segundo request recibe `rowCount() = 0` y devuelve 409. Sin LOCK explícito porque la atomicidad del UPDATE es suficiente."

---

## PARTIDOS — MODEL

**¿Cómo funciona exactamente `existeDuplicado()`?**
"Comprueba `(local=A AND visitante=B) OR (local=B AND visitante=A)` — bidireccional. Impide que existan A vs B y B vs A en la misma ronda. El parámetro `$excluirId` evita que al editar un partido se autobloquee comparándose consigo mismo."

**¿Y `existeConflictoHorario()`?**
"Busca si cualquiera de los dos equipos — ya sea como local o visitante — ya tiene un partido a esa fecha exacta en esa liga. También tiene `$excluirId` para el caso de edición."

**¿Cómo compruebas que ambos equipos pertenecen a la liga con una sola query?**
"`SELECT COUNT(*) FROM equipo_liga WHERE id_liga = ? AND id_equipo IN (?, ?)` y verifico que el resultado sea exactamente 2. Si uno no pertenece, COUNT = 1 ≠ 2, devuelve false."

**¿Por qué `getAll()` usa `WHERE 1=1`?**
"Patrón clásico para SQL dinámico. Permite añadir `AND condicion = ?` sin tener que controlar si es el primer WHERE o no. Limpio y sin condicionales extra en el código PHP."

**¿Cómo obtienes el nombre del equipo local y visitante en una sola query?**
"JOIN doble a la misma tabla `equipos` con alias distintos: `INNER JOIN equipos local ON local.id_equipo = p.id_equipo_local` y `INNER JOIN equipos visitante ON visitante.id_equipo = p.id_equipo_visitante`. Obtengo `club_local` y `club_visitante` en una única consulta."

---

## PARTIDOS Y CLASIFICACIÓN

**¿Qué pasa si cancelas un partido jugado?**
"La acción `cancelar` cambia el estado a 'cancelado' y llama a `regenerarClasificacion()`. Como la regeneración solo lee partidos con `estado = 'jugado'`, el partido cancelado deja de contar automáticamente."

**¿Por qué `modificar` llama a `regenerarClasificacion` dos veces?**
"Si el admin mueve un partido de una liga a otra, hay que regenerar la liga de origen y la de destino. El código compara `$idLigaAnterior !== $idLiga` y solo ejecuta la segunda regeneración si son distintas."

---

## USUARIOS — CONTROLLER

**¿Cómo proteges el cambio de rol ADMIN?**
"Doble protección. Primero comprueba que no sea el último admin del sistema con `countAdmins() <= 1` — no puedes quedarte sin administradores. Segundo, si el cambio involucra dar o quitar rol ADMIN, exige `password_confirmacion` del admin que ejecuta la acción y la verifica con `verifyCredentials()`. Si la contraseña es incorrecta, 403."

**¿Qué pasa cuando degradas un usuario de STAFF a USUARIO?**
"`actualizarRol()` detecta que el nuevo rol no es STAFF ni ADMIN y ejecuta limpieza automática: borra el registro de `entrenadores` vinculado y todas sus relaciones de `entrenador_equipo`. El usuario queda completamente desvinculado de cualquier equipo."

**¿Cómo validas que una imagen subida es realmente una imagen?**
"En `subirFotoEntrenador()` se hace doble validación: primero la extensión del nombre del archivo, luego el MIME type real con `mime_content_type()` sobre el fichero temporal. Si alguien renombra un ejecutable a `.jpg`, la extensión pasa pero el MIME type lo detecta."

---

## EQUIPOS — CONTROLLER

**¿Qué pasa cuando se elimina un equipo?**
"El DELETE de un equipo desencadena lógica de negocio en cascada. Primero regenera la clasificación de todas las ligas en las que participaba. Luego recorre todos los entrenadores vinculados a ese equipo: si alguno ya no tiene otros equipos asignados, se elimina su registro de `entrenadores` y su usuario asociado se degrada automáticamente de STAFF a USUARIO. Si era ADMIN no se degrada — la respuesta incluye `admins_sin_equipos_asociados` para que el frontend pueda avisarlo."

---

## PREGUNTAS DIFÍCILES

**¿Por qué `Autenticacion::usuario()` consulta la DB en cada llamada?**
"Consistencia sobre rendimiento: si cacheo el usuario en sesión y alguien cambia su rol en DB, la sesión seguiría mostrando el rol antiguo. Consultando siempre la DB, los cambios son inmediatos. Para este volumen el overhead es despreciable."

**¿Por qué `require_once` y no autoloader PSR-4?**
"Para que el proyecto sea instalable en XAMPP sin Composer. Clonar, crear la DB, iniciar XAMPP: listo. Un autoloader PSR-4 requeriría Composer y convenciones de namespacing estrictas. Es una decisión de pragmatismo de despliegue."

**¿Qué harías diferente si lo rehacieras?**
"PHPUnit para cubrir la lógica de clasificación con tests automatizados, Composer con autoloader desde el principio para facilitar añadir librerías, y en el frontend `<script type='module'>` con importaciones nativas para gestionar dependencias explícitas."

---

*Fin del guión.*

---

---

# BLOQUE DE PREGUNTAS ANTICIPADAS

> Esta sección es para que te prepares las respuestas. No la leas en la presentación.

---

## ARQUITECTURA Y DISEÑO

**P: ¿Por qué no usaste un framework como Laravel o Symfony?**
R: "La decisión fue deliberada. El objetivo del proyecto final de DAW es demostrar que entiendo cómo funciona el stack, no que sé usar un framework. Implementar el router, la autenticación y el acceso a datos desde cero me obliga a entender cada capa. Con Laravel haría el mismo resultado en menos tiempo pero sin demostrar que entiendo qué hay debajo. Además, para el scope de este proyecto, añadir un framework sería sobreingeniería."

**P: ¿Qué es el patrón Singleton que usas en Database?**
R: "El Singleton garantiza que solo existe una instancia de PDO en toda la ejecución. Esto es importante porque abrir una conexión a MySQL tiene coste. Con `private static ?PDO $instance = null` y el constructor privado, si ya hay instancia creada, devuelvo la misma. Si no, la creo. Todos los modelos llaman a `Database::getInstance()` y todos comparten la misma conexión sin saberlo."

**P: ¿Cómo funciona el Router exactamente?**
R: "Al llamar a `dispatch()`, el Router obtiene el método HTTP y la URI. Para cada ruta registrada, convierte el patrón — por ejemplo `/usuarios/{id}` — a una regex: `/^\/usuarios\/([^\/]+)$/`. Si hay match, extrae los parámetros del path. Luego usa `ReflectionMethod` para ver cómo está tipada la firma del controlador: si el primer parámetro es `int`, le pasa el ID numérico; si es `array`, le pasa el body del request. Esto elimina la necesidad de un objeto Request genérico en la mayoría de los métodos."

**P: ¿Qué hace `parse_url` en el index?**
R: "Limpia la URI de query strings antes de hacer el routing. Si alguien llama a `/equipos?categoria=infantil`, el Router necesita ver solo `/equipos` para hacer el match, no la query string. `parse_url` con `PHP_URL_PATH` extrae solo el path. Los query params se leen por separado desde `$_GET`."

---

## SEGURIDAD

**P: ¿Cómo proteges las contraseñas?**
R: "Con `password_hash($pwd, PASSWORD_DEFAULT)` en el insert, que actualmente usa bcrypt. La verificación es con `password_verify()`. Nunca guardo ni transmito la contraseña en claro. Además, antes de hashear, valido que la contraseña tenga mínimo 9 caracteres, mayúscula, minúscula, número y carácter especial con una regex. La columna `pwd` se elimina de todos los arrays antes de enviar cualquier respuesta JSON."

**P: ¿Qué es `session_regenerate_id(true)` y por qué lo usas?**
R: "Previene el ataque de session fixation. Si alguien consigue el session ID antes del login — por ejemplo con XSS o sniffing — después del login ese ID ya no vale porque se regenera. El parámetro `true` elimina la sesión antigua."

**P: ¿Cómo funciona la verificación de email?**
R: "Al registrarse, el usuario empieza con `email_verificado = 0`. El sistema genera un código de 6 dígitos con `random_int(0, 999999)`, lo hashea con `password_hash()` y guarda el hash en la base de datos con una fecha de expiración de 2 minutos. Envía el código en claro al email. Cuando el usuario lo introduce, verificamos con `password_verify()` contra el hash y comprobamos que no ha expirado con `strtotime()`. Si pasa, marcamos `email_verificado = 1` y limpiamos el hash. El código en sí nunca se guarda en claro en la base de datos."

**P: ¿Cómo funciona el reset de contraseña?**
R: "Exactamente igual que la verificación de email: código 6 dígitos, hash en DB, expiración de 10 minutos. Primero el usuario solicita el código, lo verifica en un paso separado, y solo si ese paso fue correcto puede enviar la nueva contraseña. Así el frontend puede implementar un flujo de tres pasos sin que el backend deje estado intermedio sin verificar. Al finalizar, limpiamos el token."

**P: ¿Qué pasa si el servidor de email no funciona?**
R: "En entorno local, `mail()` suele fallar porque no hay un MTA configurado. Lo gestiono con el fallback de `email_dev.log`: si `mail()` devuelve `false`, escribo en un archivo de log la línea con email, código y fecha de expiración. Así en desarrollo siempre puedo ver el código sin necesitar SMTP real. En producción se configuraría un servidor de correo real o un servicio como SendGrid."

**P: ¿Proteges contra SQL injection?**
R: "Sí, con PDO y prepared statements en todos los accesos a base de datos. Ninguna consulta concatena strings del usuario directamente. El PDO con `ATTR_EMULATE_PREPARES => false` usa prepared statements reales del motor MySQL, no emulados por PHP."

**P: ¿Qué es el CORS que configuras en el index?**
R: "Cross-Origin Resource Sharing. Como el frontend es HTML estático servido desde el mismo origen, técnicamente no hay CORS cross-origin. Pero lo configuro explícitamente con una whitelist de orígenes permitidos (`localhost`, `127.0.0.1`) para que si se sirve desde cualquier puerto o subdominio local siga funcionando, y para estar preparado si en el futuro el frontend se separa del backend. El preflight OPTIONS devuelve 204 y sale."

---

## BASE DE DATOS

**P: ¿Por qué `utf8mb4` y no `utf8`?**
R: "El `utf8` de MySQL en realidad solo soporta hasta 3 bytes por carácter, lo que excluye emojis y algunos caracteres CJK. `utf8mb4` es el UTF-8 real de 4 bytes. Como la plataforma gestiona nombres de personas y clubes de cualquier origen, usamos `utf8mb4` para evitar errores de truncamiento."

**P: ¿Por qué la clasificación se regenera completa en lugar de actualizarla incrementalmente?**
R: "Simplicidad y corrección garantizada. Una actualización incremental podría generar inconsistencias si se modifica o cancela un partido anterior. La regeneración completa es más costosa en términos de queries, pero para el volumen de datos de una liga amateur — máximo 30 equipos, 200-300 partidos por temporada — es perfectamente viable. La operación usa una transacción para ser atómica: si falla a mitad, hace rollback y la clasificación anterior se mantiene."

**P: ¿Qué unique constraints tienes y por qué?**
R: "En `ligas`: unique en `(nombre_liga, temporada, categoria)` para evitar duplicar la misma competición. En `equipos`: unique en `(club, categoria)` porque un club puede tener equipo infantil y cadete simultáneamente. En `usuario`: unique en `email` y en `(oauth_provider, oauth_id)` para el flujo OAuth que está preparado pero no activo. En `equipo_jugador` hay control por lógica de aplicación antes de insertar."

**P: ¿Qué son los campos `escudo_bloqueado`?**
R: "Una vez que un escudo es aprobado o se considera definitivo, se marca como bloqueado para evitar que se sobreescriba accidentalmente. La lógica en el controlador comprueba ese flag antes de procesar una subida de imagen."

---

## ROLES Y PERMISOS

**P: ¿Cómo impides que un STAFF acceda a datos de otro equipo?**
R: "En `Autenticacion::requerirStaffDeEquipo($id_equipo)`, hago un JOIN entre `entrenador_equipo` y `entrenadores` filtrando por el `id_usuario` de sesión y el `id_equipo` que recibo como parámetro. Solo permite continuar si la relación existe y su estado es 'activo'. Esto se llama en todos los endpoints donde el STAFF opera sobre un equipo concreto. Si el admin llama al mismo endpoint, cortocircuita la comprobación y pasa directamente."

**P: ¿Por qué el STAFF no puede aprobar sus propios jugadores?**
R: "Por separación de responsabilidades y prevención de fraude deportivo. En una liga real, que el mismo club que inscribe al jugador también lo apruebe sería un conflicto de intereses. El ADMIN actúa como árbitro neutral del proceso."

**P: ¿Qué puede hacer el rol ARBITRO que no aparece en las slides?**
R: "Está definido en el sistema de permisos con `MODIFICAR_RESULTADO` y `VER_DATOS`. Está preparado para una futura funcionalidad donde el árbitro puede registrar el resultado de un partido desde el campo. En la presentación lo mencioné brevemente porque el frontend de árbitro no está implementado en esta versión."

---

## FRONTEND

**P: ¿Por qué no usaste React o Vue?**
R: "Por el mismo motivo que no usé Laravel: el objetivo era demostrar dominio del stack completo. Vanilla JS con módulos bien organizados es perfectamente capaz para este scope. Además, evita la dependencia de un toolchain de compilación (npm, webpack) que complicaría la instalación en XAMPP."

**P: ¿Cómo funciona la inyección de layouts?**
R: "El `layout.js` hace `fetch()` a los archivos HTML de los layouts, espera la respuesta con `await`, obtiene el texto HTML y lo inserta en el DOM con `insertAdjacentHTML`. Después inicializa el burger menu, comprueba la sesión activa y marca el enlace activo en la navegación. Todo esto ocurre en el evento `DOMContentLoaded`."

**P: ¿Cómo sabes en el frontend qué rol tiene el usuario?**
R: "`sesionactiva.js` llama a `GET /auth/me` que devuelve el objeto usuario con su campo `rol` y el array `permisos`. Con eso el frontend decide qué elementos del menú mostrar y qué acciones habilitar. Si la sesión expira o no existe, la respuesta es 401 y el frontend redirige al login o deshabilita las opciones."

**P: ¿El frontend está protegido contra acceso directo a páginas de admin?**
R: "Las páginas HTML son estáticas y accesibles directamente, sí. Pero cada acción que mutan datos — crear, editar, borrar — llama a un endpoint del backend que verifica la sesión y el rol. Si alguien accede a `admin-dashboard.html` sin sesión, las llamadas a la API devuelven 401 y la página se queda vacía. La protección real está en el backend. Para el scope de este proyecto eso es correcto; en producción se añadiría una capa de autenticación en el servidor Apache también."

---

## JUGADORES Y WORKFLOW

**P: ¿Qué pasa si el mismo jugador está en dos equipos?**
R: "El sistema lo detecta. Al solicitar un alta, `buscarCoincidenciasEnOtrosEquipos()` busca por nombre + apellido + fecha de nacimiento en otros equipos y ligas. Si hay coincidencia, la solicitud sigue procesándose pero la respuesta incluye un campo `avisos` con la información de los otros equipos. Es información para el admin, no un bloqueo, porque hay casos legítimos — por ejemplo un jugador de categoría benjamín que también juega en categoría alevín."

**P: ¿Por qué el documento de identidad es obligatorio?**
R: "Para cumplir con los requisitos reales de las federaciones deportivas: toda inscripción de jugador debe estar respaldada por documentación. Además previene duplicados fraudulentos. La ruta del documento se guarda en BD pero el archivo en sí se almacena en el servidor en `/public/uploads/jugadores/{id}/`."

**P: ¿Qué pasa con la foto del jugador si el STAFF intenta subirla cuando ya existe?**
R: "El endpoint de staff usa `guardarFotoSiNoExiste()` que hace un UPDATE condicionado a `foto_path IS NULL`. Si ya hay foto, devuelve 409. Solo el ADMIN puede sobrescribir una foto existente, usando el endpoint `/admin/jugadores/{id}/foto` que sí borra el archivo anterior."

---

## PARTIDOS Y CLASIFICACIÓN

**P: ¿Cómo evitas que se cree un partido con el mismo equipo como local y visitante?**
R: "Hay una validación explícita en el controlador: `if ($idLocal === $idVisitante) → 400`. Simple pero necesario."

**P: ¿Cómo funciona la detección de conflicto de horario?**
R: "El modelo consulta si alguno de los dos equipos ya tiene un partido en esa misma fecha exacta. Si el partido que estás modificando ya existía, excluyes su propio ID del check para que no se autobloquee."

**P: ¿Qué pasa con la clasificación si cancelas un partido que ya estaba jugado?**
R: "La acción `cancelar` cambia el estado del partido a 'cancelado' y luego llama a `regenerarClasificacion()`. Como la regeneración solo lee partidos con `estado = 'jugado'`, el partido cancelado deja de contar automáticamente. Los puntos se recalculan desde cero."

**P: ¿Por qué el método `modificar` de partidos llama a `regenerarClasificacion` dos veces?**
R: "Porque si el admin mueve un partido de una liga a otra — caso raro pero posible — hay que regenerar tanto la liga de origen como la de destino. El código compara `$idLigaAnterior !== $idLiga` y solo ejecuta la segunda regeneración si son diferentes."

---

## DESPLIEGUE Y ESCALABILIDAD

**P: ¿Cómo está configurado el .htaccess?**
R: "El `.htaccess` redirige todas las peticiones que no corresponden a un archivo o directorio físico al `index.php`, que es el patrón estándar de front controller. Así el router puede manejar rutas limpias sin que Apache devuelva 404."

**P: ¿Cómo escalaría esto a múltiples temporadas?**
R: "El campo `temporada` en `ligas` es un varchar que puede ser '2024-2025', '2025-2026', etc. Los equipos y jugadores son entidades persistentes que se reutilizan entre temporadas mediante nuevas relaciones en `equipo_liga` y `equipo_jugador`. La clasificación por temporada está aislada en cada liga. Para añadir una nueva temporada bastaría con crear nuevas ligas y reasignar equipos."

**P: ¿Qué habría que hacer para llevarlo a producción?**
R: "Cuatro cosas principalmente: configurar un servidor de correo real en vez del fallback de logs, mover las credenciales de la base de datos a variables de entorno o un archivo de configuración excluido del repositorio, configurar HTTPS y ajustar las cookies de sesión para que sean `secure` e `httponly`, y revisar los permisos de los directorios de uploads. El código de negocio no necesitaría cambios."

---

## PREGUNTAS TRAMPA / DIFÍCILES

**P: ¿Por qué el `Autenticacion::usuario()` hace una query a la base de datos en cada llamada?**
R: "Es una decisión de consistencia sobre rendimiento. Si cacheara el usuario en sesión y alguien modificara su rol en la base de datos, la sesión seguiría mostrando el rol antiguo. Al consultar siempre la DB, los cambios de rol son inmediatos. Para el volumen de esta aplicación el overhead es insignificante. En un sistema de mayor escala usaría una caché con TTL corto, tipo Redis."

**P: ¿Hay alguna race condition posible en la asignación de dorsal?**
R: "En teoría sí: dos requests simultáneos podrían leer que el dorsal está libre y ambos intentar asignarlo. El modelo usa un UPDATE condicionado — `WHERE dorsal IS NULL` — que actúa como operación atómica a nivel de MySQL. El que llega segundo recibe `rowCount() = 0` y el controlador devuelve 409. No usamos LOCK explícito porque la atomicidad del UPDATE en InnoDB es suficiente para este caso."

**P: ¿Por qué `require_once` y no un autoloader PSR-4?**
R: "Para mantener el proyecto instalable en XAMPP sin Composer. Un autoloader PSR-4 requeriría Composer y seguir la convención de namespaces estricta. Con `require_once` explícito en el `index.php`, cualquiera puede clonar el repositorio, crear la base de datos e iniciar XAMPP sin instalar nada adicional. Es una decisión de pragmatismo de despliegue para el contexto académico."

**P: ¿La clase `Autenticacion` tiene estado compartido entre requests?**
R: "No, PHP no comparte estado entre requests. La clase `Autenticacion` es estática pero sus datos provienen de `$_SESSION` que es por usuario, y de queries a la DB. Cada request inicia desde cero."

**P: ¿Qué harías diferente si lo rehacieras?**
R: "Añadiría un autoloader y Composer desde el principio, aunque fuera solo para el propio proyecto — facilita añadir librerías de testing. Implementaría PHPUnit para cubrir la lógica de clasificación con tests automatizados. Y en el frontend, organizaría los módulos JS en un sistema de importaciones nativas con `<script type='module'>` para tener tree-shaking y dependencias explícitas."

---

*Fin del guión.*
