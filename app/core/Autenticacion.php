<?php

require_once __DIR__ . '/database.php';

class Autenticacion
{
    const ROL_ADMIN   = 'ADMIN';
    const ROL_ARBITRO = 'ARBITRO';
    const ROL_STAFF   = 'STAFF';
    const ROL_USUARIO = 'USUARIO';

    const PERM_GESTIONAR_TODO         = 'GESTIONAR_TODO';
    const PERM_MODIFICAR_RESULTADO    = 'MODIFICAR_RESULTADO';
    const PERM_GESTIONAR_PLANTILLA    = 'GESTIONAR_PLANTILLA';
    const PERM_GESTIONAR_EQUIPO_CLUB  = 'GESTIONAR_EQUIPO_CLUB';
    const PERM_GESTIONAR_CLASIFICACION = 'GESTIONAR_CLASIFICACION';
    const PERM_VER_DATOS              = 'VER_DATOS';

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

    public static function usuario(): ?array
    {
        self::iniciarSesion();

        if (!isset($_SESSION['id_usuario'])) {
            return null;
        }

        unset($_SESSION['_usuario_cache']);

        try {
            $db = Database::getInstance();
            $stmt = $db->prepare("SELECT * FROM usuario WHERE id_usuario = ? LIMIT 1");
            $stmt->execute([$_SESSION['id_usuario']]);
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$usuario) {
                self::cerrarSesion();
                return null;
            }

            unset($usuario['pwd']);

            $stmtFoto = $db->prepare(
                "SELECT foto 
                    FROM entrenadores 
                    WHERE id_usuario = ? 
                    LIMIT 1"
            );
            $stmtFoto->execute([(int)$usuario['id_usuario']]);

            $fotoEntrenador = $stmtFoto->fetchColumn();

            $usuario['foto_entrenador'] = $fotoEntrenador ?: null;

            return $usuario;
        } catch (Exception $e) {
            return null;
        }
    }

    public static function estaAutenticado(): bool
    {
        return self::usuario() !== null;
    }

    public static function requerirAutenticacion(): void
    {
        if (!self::estaAutenticado()) {
            self::responderError(401, 'No autenticado');
        }
    }

    public static function requerirRol(array $rolesPermitidos): void
    {
        self::requerirAutenticacion();

        $usuario = self::usuario();
        $rolUsuario = $usuario['rol'] ?? '';

        if (!in_array($rolUsuario, $rolesPermitidos, true)) {
            self::responderError(403, 'No autorizado. Rol requerido: ' . implode(' o ', $rolesPermitidos));
        }
    }

    public static function requerirPermiso(string $permiso): void
    {
        self::requerirAutenticacion();

        $usuario = self::usuario();
        $rolUsuario = $usuario['rol'] ?? '';

        $permisos = self::$permisosRol[$rolUsuario] ?? [];

        if (in_array(self::PERM_GESTIONAR_TODO, $permisos, true)) {
            return;
        }

        if (!in_array($permiso, $permisos, true)) {
            self::responderError(403, 'No autorizado. Permiso requerido: ' . $permiso);
        }
    }

    public static function requerirStaffDeEquipo(int $id_equipo): void
    {
        self::requerirAutenticacion();

        $usuario = self::usuario();
        $rolUsuario = $usuario['rol'] ?? '';

        if ($rolUsuario === self::ROL_ADMIN) {
            return;
        }

        $id_usuario = $usuario['id_usuario'];

        try {
            $db = Database::getInstance();

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

    public static function login(int $id_usuario, array $datosUsuario = []): void
    {
        self::iniciarSesion();

        session_regenerate_id(true);

        $_SESSION['id_usuario'] = $id_usuario;

        unset($_SESSION['_usuario_cache']);
    }

    public static function cerrarSesion(): void
    {
        self::iniciarSesion();

        $_SESSION = [];

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

        session_destroy();
    }

    private static function iniciarSesion(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

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

    public static function obtenerPermisos(): array
    {
        $usuario = self::usuario();
        if (!$usuario) {
            return [];
        }

        $rolUsuario = $usuario['rol'] ?? '';
        return self::$permisosRol[$rolUsuario] ?? [];
    }

    public static function tienePermiso(string $permiso): bool
    {
        $permisos = self::obtenerPermisos();

        if (in_array(self::PERM_GESTIONAR_TODO, $permisos, true)) {
            return true;
        }

        return in_array($permiso, $permisos, true);
    }

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
