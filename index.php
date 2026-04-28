<?php

// ====================== CORS (compatible con sesiones/cookies) ======================
// Si tu front y back están en el MISMO origen (mismo host/puerto), podrías incluso quitar CORS.
// Pero como estás probando con fetch + credentials, NO se puede usar "*".

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowedOrigins = [
    'http://localhost',
    'http://127.0.0.1',
    // Si usas puerto distinto, añade:
    // 'http://localhost:8080',
    // 'http://127.0.0.1:8080',
];

if ($origin !== '' && in_array($origin, $allowedOrigins, true)) {
    header("Access-Control-Allow-Origin: $origin");
} else {
    // Fallback razonable para entorno local
    header("Access-Control-Allow-Origin: http://localhost");
}

header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Si es preflight CORS, respondemos y salimos
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ====================== Redirección a Home ======================
$requestUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$scriptName = $_SERVER['SCRIPT_NAME'];
$basePath = dirname($scriptName);

if ($basePath !== '/' && $basePath !== '\\') {
    $relativeUri = substr($requestUri, strlen($basePath)) ?: '/';
} else {
    $relativeUri = $requestUri;
}

if ($relativeUri === '' || $relativeUri === '/' || $relativeUri === '/index.php') {
    $redirectUrl = rtrim($basePath, '/\\') . '/public/home.html';
    header("Location: $redirectUrl");
    exit;
}

header('Content-Type: application/json; charset=utf-8');

// ====================== Carga estricta de dependencias ======================
$requiredFiles = [
    // Core
    __DIR__ . '/app/core/database.php',
    __DIR__ . '/app/core/Autenticacion.php',
    // Router
    __DIR__ . '/app/libs/Router.php',
    // Models
    __DIR__ . '/app/model/usuariosModel.php',
    __DIR__ . '/app/model/ligasModel.php',
    __DIR__ . '/app/model/equiposModel.php',
    __DIR__ . '/app/model/jugadoresModel.php',
    __DIR__ . '/app/model/equipoJugadorModel.php',
    // Models
    __DIR__ . '/app/model/partidosModel.php',
    // Controllers (nombres reales de los archivos)
    __DIR__ . '/app/controller/AuthController.php',
    __DIR__ . '/app/controller/userController.php',
    __DIR__ . '/app/controller/ligasController.php',
    __DIR__ . '/app/controller/equiposController.php',
    __DIR__ . '/app/controller/jugadoresController.php',
    __DIR__ . '/app/controller/equipojugador.php',
    __DIR__ . '/app/controller/incidenciasController.php',
    __DIR__ . '/app/controller/JugadoresStaffController.php',
    __DIR__ . '/app/controller/JugadoresAdminController.php',
    __DIR__ . '/app/controller/PartidosController.php',
];

foreach ($requiredFiles as $file) {
    if (!file_exists($file)) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Falta un archivo requerido: ' . basename($file),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    require_once $file;
}

// ====================== Enrutamiento ======================
$router = new \Librerias\Router();

// ====================== USUARIOS ======================
$router->add('GET',    '/usuarios',              'UsuariosController', 'seleccionar');
$router->add('GET',    '/usuarios/{id}',         'UsuariosController', 'localizar');
$router->add('POST',   '/usuarios',              'UsuariosController', 'insertar');
$router->add('PUT',    '/usuarios/{id}',         'UsuariosController', 'modificar');
$router->add('DELETE', '/usuarios/{id}',         'UsuariosController', 'eliminar');
$router->add('PATCH',  '/usuarios/{id}/rol',     'UsuariosController', 'actualizarRol');
$router->add('PATCH',  '/usuarios/{id}/equipo-staff', 'UsuariosController', 'actualizarEquipoStaff');

// ====================== AUTH ======================
$router->add('POST',   '/auth/register',                 'AuthController', 'register');
$router->add('POST',   '/auth/login',                    'AuthController', 'login');
$router->add('POST',   '/auth/logout',                   'AuthController', 'logout');
$router->add('GET',    '/auth/me',                       'AuthController', 'me');
$router->add('POST',   '/auth/email/solicitar-codigo',        'AuthController', 'solicitarCodigoEmail');
$router->add('POST',   '/auth/email/verificar-codigo',        'AuthController', 'verificarCodigoEmail');
$router->add('POST',   '/auth/email/eliminar-no-verificado',  'AuthController', 'eliminarNoVerificado');
$router->add('POST', '/auth/password/solicitar-codigo', 'AuthController', 'solicitarCodigoResetPassword');
$router->add('POST', '/auth/password/verificar-codigo', 'AuthController', 'verificarCodigoResetPassword');
$router->add('POST', '/auth/password/reset', 'AuthController', 'resetPassword');

// ====================== LIGAS ======================
$router->add('GET',    '/ligas',                 'LigasController', 'seleccionar');
$router->add('POST',   '/ligas',                 'LigasController', 'insertar');
$router->add('DELETE', '/ligas',                 'LigasController', 'eliminar');
$router->add('PUT',    '/ligas',                 'LigasController', 'modificar');
$router->add('POST',   '/ligas/{id}/escudo',     'LigasController', 'subirEscudo');

// ====================== EQUIPOS ======================
$router->add('GET',    '/equipos',               'EquiposController', 'seleccionar');
$router->add('GET',    '/equipos/{id}',          'EquiposController', 'localizar');
$router->add('POST',   '/equipos',               'EquiposController', 'insertar');
$router->add('PUT',    '/equipos/{id}',          'EquiposController', 'modificar');
$router->add('DELETE', '/equipos/{id}',          'EquiposController', 'eliminar');
$router->add('POST',   '/equipos/{id}/escudo',   'EquiposController', 'subirEscudo');

// ====================== STAFF ======================
$router->add('GET',    '/staff/equipos',                              'EquiposController',        'seleccionarStaff');

// ─── STAFF: Gestión de jugadores ─────────────────────────────────────────
$router->add('GET',    '/staff/jugadores/pendientes',                 'JugadoresStaffController', 'pendientes');
$router->add('GET',    '/staff/jugadores/plantilla',                  'JugadoresStaffController', 'plantilla');
$router->add('POST',   '/staff/jugadores/alta',                       'JugadoresStaffController', 'alta');
$router->add('PATCH',  '/staff/jugadores/{id}/solicitar-baja',        'JugadoresStaffController', 'solicitarBaja');
$router->add('POST',   '/staff/jugadores/{id}/foto',                  'JugadoresStaffController', 'subirFoto');
$router->add('PATCH',  '/staff/jugadores/dorsal',                     'JugadoresStaffController', 'asignarDorsal');

// ─── ADMIN: Gestión de jugadores ─────────────────────────────────────────
$router->add('GET',    '/admin/jugadores/pendientes',                 'JugadoresAdminController', 'pendientes');
$router->add('POST',   '/admin/jugadores/alta-directa',               'JugadoresAdminController', 'altaDirecta');
$router->add('POST',   '/admin/jugadores/aprobar-lote',               'JugadoresAdminController', 'aprobarLote');
$router->add('POST',   '/admin/jugadores/{id}/aprobar',               'JugadoresAdminController', 'aprobar');
$router->add('POST',   '/admin/jugadores/{id}/rechazar',              'JugadoresAdminController', 'rechazar');
$router->add('PATCH',  '/admin/jugadores/{id}/editar',                'JugadoresAdminController', 'editarJugador');
$router->add('PATCH',  '/admin/jugadores/{id}/dorsal',                'JugadoresAdminController', 'corregirDorsal');
$router->add('POST',   '/admin/jugadores/{id}/foto',                  'JugadoresAdminController', 'subirFoto');

// ====================== JUGADORES (genérico) ======================
$router->add('GET',    '/jugadores',                  'JugadoresController', 'seleccionar');
$router->add('POST',   '/jugadores/{id}/foto',        'JugadoresController', 'subirFoto');
$router->add('GET',    '/jugadores/{id}',             'JugadoresController', 'localizar');
$router->add('DELETE', '/jugadores/{id}',             'JugadoresController', 'eliminar');

// ====================== PLANTILLA (equipo_jugador) ======================
$router->add('GET',    '/plantillas',                 'EquipoJugadorController', 'seleccionar');
$router->add('GET',    '/plantillas/detalle',         'EquipoJugadorController', 'detalle');
$router->add('POST',   '/plantillas',                 'EquipoJugadorController', 'insertar');
$router->add('PATCH',  '/plantillas/dorsal',          'EquipoJugadorController', 'actualizarDorsal');
$router->add('DELETE', '/plantillas',                 'EquipoJugadorController', 'eliminar');

// ====================== INCIDENCIAS ======================
$router->add('POST',   '/incidencias',                'IncidenciasController', 'abrirIncidencia');

// ====================== ADMIN: PARTIDOS ======================
// (rutas específicas ANTES que las dinámicas para evitar colisiones)
$router->add('POST',   '/admin/partidos',              'PartidosController', 'insertar');
$router->add('PUT',    '/admin/partidos/{id}',         'PartidosController', 'modificar');
$router->add('PATCH',  '/admin/partidos/{id}/cancelar','PartidosController', 'cancelar');
$router->add('DELETE', '/admin/partidos/{id}',         'PartidosController', 'eliminar');

// ====================== PARTIDOS ======================
$router->add('GET',    '/partidos',                    'PartidosController', 'seleccionar');
$router->add('GET',    '/partidos/{id}',               'PartidosController', 'localizar');

// ====================== Dispatch ======================
$router->dispatch();
