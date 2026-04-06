<?php

/**
 * ============================================================================
 *  CLASE DE AUTENTICACIÓN Y AUTORIZACIÓN CENTRALIZADA
 * ============================================================================
 *
 *  Proporciona un sistema completo de control de acceso para la API REST.
 *  Diseñada para ser usada desde cualquier controller con llamadas estáticas:
 *
 *      Autenticacion::requerirAutenticacion();
 *      Autenticacion::requerirRol(['ADMIN']);
 *      Autenticacion::requerirPermiso('MODIFICAR_RESULTADO');
 *      Autenticacion::requerirStaffDeEquipo($id_equipo);
 *
 *  Utiliza sesiones PHP para mantener al usuario autenticado.
 *
 *  REQUISITO: la tabla `usuario` debe tener una columna `rol` VARCHAR(20)
 *  con uno de estos valores: 'ADMIN', 'ARBITRO', 'STAFF', 'USUARIO'
 * ============================================================================
 */

require_once __DIR__ . '/database.php';

class Autenticacion
{
    // ─── ROLES DISPONIBLES ───────────────────────────────────────────────
    const ROL_ADMIN   = 'ADMIN';
    const ROL_ARBITRO = 'ARBITRO';
    const ROL_STAFF   = 'STAFF';
    const ROL_USUARIO = 'USUARIO';

    // ─── PERMISOS DISPONIBLES ────────────────────────────────────────────
    const PERM_GESTIONAR_TODO         = 'GESTIONAR_TODO';
    const PERM_MODIFICAR_RESULTADO    = 'MODIFICAR_RESULTADO';
    const PERM_GESTIONAR_PLANTILLA    = 'GESTIONAR_PLANTILLA';
    const PERM_GESTIONAR_EQUIPO_CLUB  = 'GESTIONAR_EQUIPO_CLUB';
    const PERM_GESTIONAR_CLASIFICACION = 'GESTIONAR_CLASIFICACION';
    const PERM_VER_DATOS              = 'VER_DATOS';

    /**
     * Mapa de permisos por rol.
     * Cada rol tiene una lista de acciones que puede realizar.
     * ADMIN tiene GESTIONAR_TODO que actúa como comodín (acceso total).
     */
    private static array $permisosRol = [
        self::ROL_ADMIN => [
            self::PERM_GESTIONAR_TODO,
        ],
        self::ROL_ARBITRO => [
            self::PERM_MODIFICAR_RESULTADO,
            self::PERM_VER_DATOS,
        ],
        self::ROL_STAFF => [
            self::PERM_GESTIONAR_PLANTILLA,
            self::PERM_GESTIONAR_EQUIPO_CLUB,
            self::PERM_VER_DATOS,
        ],
        self::ROL_USUARIO => [
            self::PERM_VER_DATOS,
        ],
    ];

    // =====================================================================
    //  MÉTODOS PRINCIPALES
    // =====================================================================

    /**
     * Obtener los datos del usuario autenticado.
     *
     * Busca en la sesión PHP activa. Si la sesión contiene un 'id_usuario',
     * consulta la base de datos para devolver los datos completos.
     *
     * @return array|null  Array asociativo con los datos del usuario, o null si no está autenticado.
     */
    public static function usuario(): ?array
    {
        self::iniciarSesion();

        // No hay usuario en sesión
        if (!isset($_SESSION['id_usuario'])) {
            return null;
        }

        // Devolver los datos cacheados si ya se consultaron en esta petición
        if (isset($_SESSION['_usuario_cache'])) {
            return $_SESSION['_usuario_cache'];
        }

        // Consultar datos actualizados del usuario en la base de datos
        try {
            $db = Database::getInstance();
            $stmt = $db->prepare("SELECT * FROM usuario WHERE id_usuario = ? LIMIT 1");
            $stmt->execute([$_SESSION['id_usuario']]);
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$usuario) {
                // El usuario fue eliminado de la BD: limpiar sesión
                self::cerrarSesion();
                return null;
            }

            // No exponer la contraseña
            unset($usuario['pwd']);

            // Cachear para no repetir consultas en la misma petición
            $_SESSION['_usuario_cache'] = $usuario;

            return $usuario;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Comprobar si hay un usuario autenticado.
     *
     * @return bool  true si hay sesión activa con usuario válido.
     */
    public static function estaAutenticado(): bool
    {
        return self::usuario() !== null;
    }

    /**
     * Exigir que el usuario esté autenticado.
     * Si no lo está, devuelve HTTP 401 y finaliza la ejecución.
     *
     * Uso en controllers:
     *     Autenticacion::requerirAutenticacion();
     *
     * @return void
     */
    public static function requerirAutenticacion(): void
    {
        if (!self::estaAutenticado()) {
            self::responderError(401, 'No autenticado');
        }
    }

    /**
     * Exigir que el usuario tenga uno de los roles indicados.
     * Primero verifica la autenticación y luego el rol.
     *
     * Uso en controllers:
     *     Autenticacion::requerirRol(['ADMIN', 'ARBITRO']);
     *
     * @param array $rolesPermitidos  Lista de roles válidos (ej: ['ADMIN', 'STAFF'])
     * @return void
     */
    public static function requerirRol(array $rolesPermitidos): void
    {
        self::requerirAutenticacion();

        $usuario = self::usuario();
        $rolUsuario = $usuario['rol'] ?? '';

        if (!in_array($rolUsuario, $rolesPermitidos, true)) {
            self::responderError(403, 'No autorizado. Rol requerido: ' . implode(' o ', $rolesPermitidos));
        }
    }

    /**
     * Exigir que el usuario tenga un permiso específico.
     * Los permisos se resuelven internamente según el rol del usuario.
     * ADMIN tiene GESTIONAR_TODO, que actúa como comodín.
     *
     * Uso en controllers:
     *     Autenticacion::requerirPermiso('MODIFICAR_RESULTADO');
     *
     * @param string $permiso  Nombre del permiso requerido.
     * @return void
     */
    public static function requerirPermiso(string $permiso): void
    {
        self::requerirAutenticacion();

        $usuario = self::usuario();
        $rolUsuario = $usuario['rol'] ?? '';

        // Obtener los permisos asignados al rol
        $permisos = self::$permisosRol[$rolUsuario] ?? [];

        // GESTIONAR_TODO es un comodín: concede cualquier permiso
        if (in_array(self::PERM_GESTIONAR_TODO, $permisos, true)) {
            return; // ADMIN → Acceso total
        }

        // Verificar si el permiso solicitado está en la lista del rol
        if (!in_array($permiso, $permisos, true)) {
            self::responderError(403, 'No autorizado. Permiso requerido: ' . $permiso);
        }
    }

    /**
     * Exigir que el usuario pertenezca como STAFF activo al equipo indicado.
     * Consulta la tabla `entrenador_equipo` buscando una relación activa
     * entre el entrenador vinculado al usuario y el equipo.
     *
     * ADMIN tiene acceso directo sin verificar pertenencia.
     *
     * Uso en controllers:
     *     Autenticacion::requerirStaffDeEquipo($id_equipo);
     *
     * @param int $id_equipo  ID del equipo al que se requiere acceso.
     * @return void
     */
    public static function requerirStaffDeEquipo(int $id_equipo): void
    {
        self::requerirAutenticacion();

        $usuario = self::usuario();
        $rolUsuario = $usuario['rol'] ?? '';

        // ADMIN tiene acceso universal
        if ($rolUsuario === self::ROL_ADMIN) {
            return;
        }

        $id_usuario = $usuario['id_usuario'];

        try {
            $db = Database::getInstance();

            // Buscar si el usuario está vinculado como entrenador activo del equipo
            // Se une la tabla entrenadores (que tiene id_usuario) con entrenador_equipo
            $stmt = $db->prepare(
                "SELECT 1 FROM entrenador_equipo ee
                 INNER JOIN entrenadores e ON ee.id_entrenador = e.id_entrenador
                 WHERE e.id_usuario = ?
                   AND ee.id_equipo = ?
                   AND ee.estado = 'activo'
                 LIMIT 1"
            );
            $stmt->execute([$id_usuario, $id_equipo]);
            $pertenece = (bool) $stmt->fetchColumn();

            if (!$pertenece) {
                self::responderError(403, 'No autorizado para este equipo');
            }
        } catch (Exception $e) {
            self::responderError(500, 'Error al verificar pertenencia al equipo');
        }
    }

    // =====================================================================
    //  MÉTODOS DE GESTIÓN DE SESIÓN
    // =====================================================================

    /**
     * Iniciar sesión para un usuario (login).
     * Guarda el id_usuario en $_SESSION para futuras peticiones.
     *
     * @param int   $id_usuario  ID del usuario autenticado.
     * @param array $datosUsuario  Datos del usuario para cachear (opcional).
     * @return void
     */
    public static function login(int $id_usuario, array $datosUsuario = []): void
    {
        self::iniciarSesion();

        // Regenerar ID de sesión para prevenir session fixation
        session_regenerate_id(true);

        $_SESSION['id_usuario'] = $id_usuario;

        // Cachear datos si se proporcionan
        if (!empty($datosUsuario)) {
            unset($datosUsuario['pwd']); // nunca guardar contraseña en sesión
            $_SESSION['_usuario_cache'] = $datosUsuario;
        }
    }

    /**
     * Cerrar sesión del usuario (logout).
     * Destruye toda la información de sesión.
     *
     * @return void
     */
    public static function cerrarSesion(): void
    {
        self::iniciarSesion();

        // Limpiar todas las variables de sesión
        $_SESSION = [];

        // Eliminar la cookie de sesión
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        // Destruir la sesión
        session_destroy();
    }

    // =====================================================================
    //  HELPERS INTERNOS
    // =====================================================================

    /**
     * Iniciar la sesión PHP si no está activa.
     * Método seguro que evita warnings por doble inicio.
     *
     * @return void
     */
    private static function iniciarSesion(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * Responder con un error JSON y finalizar la ejecución.
     *
     * @param int    $httpCode  Código HTTP (ej: 401, 403, 500).
     * @param string $mensaje   Mensaje descriptivo del error.
     * @return never            Finaliza la ejecución del script.
     */
    private static function responderError(int $httpCode, string $mensaje): void
    {
        http_response_code($httpCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'message' => $mensaje
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Obtener los permisos del usuario autenticado.
     * Útil para que el frontend conozca las acciones disponibles.
     *
     * @return array  Lista de permisos del usuario actual (vacía si no está autenticado).
     */
    public static function obtenerPermisos(): array
    {
        $usuario = self::usuario();
        if (!$usuario) {
            return [];
        }

        $rolUsuario = $usuario['rol'] ?? '';
        return self::$permisosRol[$rolUsuario] ?? [];
    }

    /**
     * Verificar si el usuario tiene un permiso sin cortar la ejecución.
     * Útil para lógica condicional en controllers.
     *
     * @param string $permiso  Permiso a verificar.
     * @return bool            true si el usuario tiene el permiso.
     */
    public static function tienePermiso(string $permiso): bool
    {
        $permisos = self::obtenerPermisos();

        // GESTIONAR_TODO = comodín
        if (in_array(self::PERM_GESTIONAR_TODO, $permisos, true)) {
            return true;
        }

        return in_array($permiso, $permisos, true);
    }

    /**
     * Verificar si el usuario tiene uno de los roles indicados sin cortar ejecución.
     *
     * @param array $roles  Roles a verificar.
     * @return bool         true si el usuario tiene alguno de los roles.
     */
    public static function tieneRol(array $roles): bool
    {
        $usuario = self::usuario();
        if (!$usuario) {
            return false;
        }

        $rolUsuario = $usuario['rol'] ?? '';
        return in_array($rolUsuario, $roles, true);
    }
}
