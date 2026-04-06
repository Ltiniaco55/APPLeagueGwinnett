<?php

declare(strict_types=1);

/**
 * ============================================================================
 *  AuthController
 * ============================================================================
 *  - Registro público (con validación de contraseña fuerte)
 *  - Login / Logout (sesión/cookies vía Autenticacion)
 *  - /me (usuario actual)
 *  - Verificación de email con código (6 dígitos, hash + expiración en BD)
 *
 *  IMPORTANTE:
 *   - NO se modifica el Model (por orden tuya).
 *   - La verificación del código se hace con query directa aquí porque tu Model
 *     NO trae método para validar código.
 *
 *  Depende de:
 *   - Autenticacion.php
 *   - usuariosModel.php
 *   - database.php (para validar código sin tocar el model)
 * ============================================================================
 */

require_once __DIR__ . '/../core/Autenticacion.php';
require_once __DIR__ . '/../core/database.php';
require_once __DIR__ . '/../model/usuariosModel.php';

class AuthController
{
    // ---------- Respuesta JSON estándar ----------
    private function responder(int $codigoHttp, array $contenido): void
    {
        http_response_code($codigoHttp);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($contenido, JSON_UNESCAPED_UNICODE);
        exit;
    }

    private function limpiarTexto($valor): string
    {
        return trim((string)($valor ?? ''));
    }

    private function limpiarUsuario(?array $usuario): ?array
    {
        if (!$usuario) return null;
        if (isset($usuario['pwd'])) unset($usuario['pwd']);
        return $usuario;
    }

    private function passwordFuerte(string $pwd): bool
    {
        // min 9, mayúscula, minúscula, número, especial
        return (bool)preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z\d]).{9,}$/', $pwd);
    }

    // =========================================================================
    //  EMAIL CODE HELPERS
    // =========================================================================

    private function generarCodigo6(): string
    {
        // 6 dígitos con ceros a la izquierda si hiciera falta
        return str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    private function expireAt(int $minutos = 10): string
    {
        return date('Y-m-d H:i:s', time() + ($minutos * 60));
    }

    private function enviarCodigoEmail(string $email, string $codigo): bool
    {
        // Producción: PHPMailer / SMTP real.
        // Aquí: mail() básico (en local muchas veces no envía, pero es el “mínimo viable”).
        $subject = 'GYSL - Código de verificación';
        $message = "Tu código de verificación es: {$codigo}\n\nCaduca en 10 minutos.";
        $headers = "From: no-reply@gysl.local\r\n" .
            "Reply-To: no-reply@gysl.local\r\n" .
            "Content-Type: text/plain; charset=UTF-8\r\n";

        // Si mail() no está configurado, devolverá false.
        return @mail($email, $subject, $message, $headers);
    }

    /**
     * Genera, guarda (hash+expire) y envía código de verificación para un usuario.
     * En entorno local (mail() no configurado), guarda el código en un log de desarrollo
     * y continúa el flujo con éxito en lugar de bloquearlo.
     */
    private function generarGuardarYEnviarCodigo(UsuariosModel $modelo, int $idUsuario, string $email): void
    {
        $codigo = $this->generarCodigo6();
        $hash = password_hash($codigo, PASSWORD_DEFAULT);
        $expireAt = $this->expireAt(2);

        $modelo->guardarCodigoVerificacionEmail($idUsuario, $hash, $expireAt);

        $ok = $this->enviarCodigoEmail($email, $codigo);

        // Si mail() no está configurado (entorno local/dev), guardamos el código
        // en un archivo de log para poder usarlo manualmente. El flujo NO se bloquea.
        if (!$ok) {
            $logDir  = __DIR__ . '/../../logs';
            $logFile = $logDir . '/email_dev.log';

            if (!is_dir($logDir)) {
                @mkdir($logDir, 0755, true);
            }

            $linea = '[' . date('Y-m-d H:i:s') . '] '
                . "EMAIL: {$email} | CÓDIGO: {$codigo} | EXPIRA: {$expireAt}" . PHP_EOL;

            @file_put_contents($logFile, $linea, FILE_APPEND | LOCK_EX);
        }
        // Si $ok === true, el email se envió correctamente (producción). No hacemos nada más.
    }

    // =========================================================================
    // REGISTER (POST /api/auth/register)
    // - Crea usuario
    // - Genera + guarda + envía código automáticamente
    // =========================================================================
    public function register(array $entrada): void
    {
        try {
            $nombre = $this->limpiarTexto($entrada['nombre'] ?? '');
            $apellido = $this->limpiarTexto($entrada['apellido'] ?? '');
            $fecha_nacimiento = $this->limpiarTexto($entrada['fecha_nacimiento'] ?? '');
            $email = $this->limpiarTexto($entrada['email'] ?? '');
            $pwd = (string)($entrada['pwd'] ?? '');
            $telefono = array_key_exists('telefono', $entrada) ? $this->limpiarTexto($entrada['telefono']) : null;

            if ($nombre === '' || $apellido === '' || $fecha_nacimiento === '' || $email === '' || $pwd === '') {
                $this->responder(400, ['success' => false, 'message' => 'Faltan campos obligatorios']);
            }

            if (!$this->passwordFuerte($pwd)) {
                $this->responder(400, [
                    'success' => false,
                    'message' => 'La contraseña no cumple requisitos: mínimo 9, mayúscula, minúscula, número y carácter especial.'
                ]);
            }

            $modelo = new UsuariosModel();

            if ($modelo->emailExists($email)) {
                $this->responder(409, ['success' => false, 'message' => 'Ya existe un usuario con ese email']);
            }

            $idNuevo = $modelo->insert($nombre, $apellido, $fecha_nacimiento, $email, $pwd, $telefono);

            // Genera + guarda + envía código
            $this->generarGuardarYEnviarCodigo($modelo, (int)$idNuevo, $email);

            $usuarioCreado = $modelo->getById((int)$idNuevo);
            $usuarioCreado = $this->limpiarUsuario($usuarioCreado);

            $this->responder(201, [
                'success' => true,
                'message' => 'Registro creado. Se envió un código de verificación al email.',
                'data' => $usuarioCreado
            ]);
        } catch (Throwable $e) {
            $this->responder(500, ['success' => false, 'message' => $e->getMessage()]);
        }
    }

    // =========================================================================
    // LOGIN (POST /api/auth/login)
    // - verifyCredentials() del model
    // - Si email_verificado existe y no está verificado => manda código y 403
    // =========================================================================
    public function login(array $entrada): void
    {
        try {
            $email = strtolower($this->limpiarTexto($entrada['email'] ?? ''));
            $pwd = (string)($entrada['pwd'] ?? '');

            if ($email === '' || $pwd === '') {
                $this->responder(400, ['success' => false, 'message' => 'Faltan campos obligatorios: email, pwd']);
            }

            $modelo = new UsuariosModel();
            $usuario = $modelo->verifyCredentials($email, $pwd);

            if (!$usuario) {
                $this->responder(401, ['success' => false, 'message' => 'Credenciales inválidas']);
            }

            // Bloqueo por email no verificado (si el campo existe)
            if (array_key_exists('email_verificado', $usuario) && (int)$usuario['email_verificado'] !== 1) {

                // Genera + guarda + envía código y bloquea login
                $idUsuario = (int)($usuario['id_usuario'] ?? 0);
                if ($idUsuario > 0) {
                    $this->generarGuardarYEnviarCodigo($modelo, $idUsuario, $email);
                }

                $this->responder(403, [
                    'success' => false,
                    'message' => 'Email no verificado. Te enviamos un código para verificar.',
                ]);
            }

            $usuario = $this->limpiarUsuario($usuario);

            Autenticacion::login((int)$usuario['id_usuario'], $usuario);

            $this->responder(200, [
                'success' => true,
                'message' => 'Inicio de sesión exitoso',
                'data' => $usuario,
                'permisos' => Autenticacion::obtenerPermisos()
            ]);
        } catch (Throwable $e) {
            $this->responder(500, ['success' => false, 'message' => $e->getMessage()]);
        }
    }

    // =========================================================================
    // LOGOUT (POST /api/auth/logout)
    // =========================================================================
    public function logout(): void
    {
        try {
            Autenticacion::requerirAutenticacion();
            Autenticacion::cerrarSesion();

            $this->responder(200, [
                'success' => true,
                'message' => 'Cierre de sesión exitoso'
            ]);
        } catch (Throwable $e) {
            $this->responder(500, ['success' => false, 'message' => $e->getMessage()]);
        }
    }

    // =========================================================================
    // ME (GET /api/auth/me)
    // =========================================================================
    public function me(): void
    {
        try {
            Autenticacion::requerirAutenticacion();

            $usuarioSesion = Autenticacion::usuario(); // aquí está la verdad
            if (!$usuarioSesion || !isset($usuarioSesion['id_usuario'])) {
                $this->responder(401, ['success' => false, 'message' => 'No autenticado']);
            }

            $this->responder(200, [
                'success' => true,
                'data' => $usuarioSesion,
                'permisos' => Autenticacion::obtenerPermisos()
            ]);
        } catch (Throwable $e) {
            $this->responder(500, ['success' => false, 'message' => $e->getMessage()]);
        }
    }

    // =========================================================================
    // POST /api/auth/email/solicitar-codigo
    // =========================================================================
    public function solicitarCodigoEmail(array $entrada): void

    {
        try {
            $email = $this->limpiarTexto($entrada['email'] ?? '');
            if ($email === '') {
                $this->responder(400, ['success' => false, 'message' => 'Falta el campo: email']);
            }

            $modelo = new UsuariosModel();
            $u = $modelo->getByEmailAuth($email);

            if (!$u || !isset($u['id_usuario'])) {
                $this->responder(404, ['success' => false, 'message' => 'No existe usuario con ese email']);
            }

            $this->generarGuardarYEnviarCodigo($modelo, (int)$u['id_usuario'], $email);

            $this->responder(200, [
                'success' => true,
                'message' => 'Código enviado al email.'
            ]);
        } catch (Throwable $e) {
            $this->responder(500, ['success' => false, 'message' => $e->getMessage()]);
        }
    }

    // =========================================================================
    // POST /api/auth/email/verificar-codigo
    // - Valida contra hash + expiración EN BD (sin tocar el model)
    // =========================================================================
    public function verificarCodigoEmail(array $entrada): void
    {
        try {
            $email = $this->limpiarTexto($entrada['email'] ?? '');
            $codigo = $this->limpiarTexto($entrada['codigo'] ?? '');

            if ($email === '' || $codigo === '') {
                $this->responder(400, ['success' => false, 'message' => 'Faltan campos obligatorios: email, codigo']);
            }

            $modelo = new UsuariosModel();
            $u = $modelo->getByEmailAuth($email);

            if (!$u || !isset($u['id_usuario'])) {
                $this->responder(404, ['success' => false, 'message' => 'Usuario no encontrado']);
            }

            $idUsuario = (int)$u['id_usuario'];

            // Query directa: sacar hash + expire (porque no tocamos el model)
            $db = Database::getInstance();
            $stmt = $db->prepare(
                "SELECT email_verification_code_hash, email_verification_expire
                 FROM usuario
                 WHERE id_usuario = ?
                 LIMIT 1"
            );
            $stmt->execute([$idUsuario]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                $this->responder(400, ['success' => false, 'message' => 'Código no disponible']);
            }

            $hash = (string)($row['email_verification_code_hash'] ?? '');
            $expire = (string)($row['email_verification_expire'] ?? '');

            if ($hash === '' || $expire === '') {
                $this->responder(400, ['success' => false, 'message' => 'No hay código activo. Solicita uno nuevo.']);
            }

            if (strtotime($expire) < time()) {
                $this->responder(400, ['success' => false, 'message' => 'Código expirado. Solicita uno nuevo.']);
            }

            if (!password_verify($codigo, $hash)) {
                $this->responder(400, ['success' => false, 'message' => 'Código inválido.']);
            }

            // Marcar verificado y limpiar code/hash/expire usando el método del model
            $modelo->marcarEmailVerificado($idUsuario);

            $this->responder(200, [
                'success' => true,
                'message' => 'Email verificado correctamente.'
            ]);
        } catch (Throwable $e) {
            $this->responder(500, ['success' => false, 'message' => $e->getMessage()]);
        }
    }

    // =========================================================================
    // POST /api/auth/email/eliminar-no-verificado
    // - Borra el usuario si lleva 10 min sin verificar el email.
    // - Solo elimina si email_verificado = 0, como medida de seguridad.
    // =========================================================================
    public function eliminarNoVerificado(array $entrada): void
    {
        try {
            $email = $this->limpiarTexto($entrada['email'] ?? '');
            if ($email === '') {
                $this->responder(400, ['success' => false, 'message' => 'Falta el campo: email']);
            }

            $db = Database::getInstance();

            // Solo borramos si el usuario existe Y su email NO está verificado
            $stmt = $db->prepare(
                "DELETE FROM usuario
                 WHERE email = ?
                   AND (email_verificado = 0 OR email_verificado IS NULL)
                 LIMIT 1"
            );
            $stmt->execute([$email]);
            $borrados = $stmt->rowCount();

            if ($borrados > 0) {
                $this->responder(200, [
                    'success' => true,
                    'message' => 'Usuario no verificado eliminado correctamente.'
                ]);
            } else {
                // O ya estaba verificado, o no existía: no hacemos nada (caso seguro)
                $this->responder(200, [
                    'success' => true,
                    'message' => 'No se requirió acción (usuario ya verificado o no encontrado).'
                ]);
            }
        } catch (Throwable $e) {
            $this->responder(500, ['success' => false, 'message' => $e->getMessage()]);
        }
    }
    // =========================================================================
    // POST /auth/password/solicitar-codigo
    // Solicita un código de RESET para el email indicado (forgot password)
    // Reutiliza el mismo campo email_verification_code_hash + expire del usuario
    // =========================================================================
    public function solicitarCodigoResetPassword(array $entrada): void
    {
        try {
            $email = strtolower($this->limpiarTexto($entrada['email'] ?? ''));
            if ($email === '') {
                $this->responder(400, ['success' => false, 'message' => 'Falta el campo: email']);
            }

            $modelo = new UsuariosModel();
            $u = $modelo->getByEmailAuth($email);

            if (!$u || !isset($u['id_usuario'])) {
                // Por seguridad devolvemos 200 igualmente (no revelamos si existe o no)
                $this->responder(200, [
                    'success' => true,
                    'message' => 'Si el email existe, recibirás un código.'
                ]);
            }

            $this->generarGuardarYEnviarCodigo($modelo, (int)$u['id_usuario'], $email);

            $this->responder(200, [
                'success' => true,
                'message' => 'Código enviado. Revisa tu email (o el log en entorno local).'
            ]);
        } catch (Throwable $e) {
            $this->responder(500, ['success' => false, 'message' => $e->getMessage()]);
        }
    }

    // =========================================================================
    // POST /auth/password/verificar-codigo
    // Verifica el código de reset (NO marca email_verificado, solo valida)
    // =========================================================================
    public function verificarCodigoResetPassword(array $entrada): void
    {
        try {
            $email  = strtolower($this->limpiarTexto($entrada['email']  ?? ''));
            $codigo = $this->limpiarTexto($entrada['codigo'] ?? '');

            if ($email === '' || $codigo === '') {
                $this->responder(400, ['success' => false, 'message' => 'Faltan campos: email, codigo']);
            }

            $modelo = new UsuariosModel();
            $u = $modelo->getByEmailAuth($email);

            if (!$u || !isset($u['id_usuario'])) {
                $this->responder(404, ['success' => false, 'message' => 'Usuario no encontrado']);
            }

            $idUsuario = (int)$u['id_usuario'];

            // Verificamos el hash + expiración directamente en BD
            $db   = Database::getInstance();
            $stmt = $db->prepare(
                "SELECT email_verification_code_hash, email_verification_expire
                 FROM usuario
                 WHERE id_usuario = ?
                 LIMIT 1"
            );
            $stmt->execute([$idUsuario]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                $this->responder(400, ['success' => false, 'message' => 'Código no disponible']);
            }

            $hash   = (string)($row['email_verification_code_hash'] ?? '');
            $expire = (string)($row['email_verification_expire']    ?? '');

            if ($hash === '' || $expire === '') {
                $this->responder(400, ['success' => false, 'message' => 'No hay código activo. Solicita uno nuevo.']);
            }

            if (strtotime($expire) < time()) {
                $this->responder(400, ['success' => false, 'message' => 'Código expirado. Solicita uno nuevo.']);
            }

            if (!password_verify($codigo, $hash)) {
                $this->responder(400, ['success' => false, 'message' => 'Código incorrecto.']);
            }

            // ✅ Código válido — NO limpiamos aún (lo limpiará resetPassword)
            $this->responder(200, [
                'success' => true,
                'message' => 'Código verificado correctamente.'
            ]);
        } catch (Throwable $e) {
            $this->responder(500, ['success' => false, 'message' => $e->getMessage()]);
        }
    }

    // =========================================================================
    // POST /auth/password/reset
    // Actualiza la contraseña del usuario (tras haber verificado el código)
    // =========================================================================
    public function resetPassword(array $entrada): void
    {
        try {
            $email    = strtolower($this->limpiarTexto($entrada['email']     ?? ''));
            $nuevaPwd = (string)($entrada['nueva_pwd'] ?? '');

            if ($email === '' || $nuevaPwd === '') {
                $this->responder(400, ['success' => false, 'message' => 'Faltan campos: email, nueva_pwd']);
            }

            if (!$this->passwordFuerte($nuevaPwd)) {
                $this->responder(400, [
                    'success' => false,
                    'message' => 'La contraseña debe tener mín. 9 caracteres, mayúscula, minúscula, número y carácter especial.'
                ]);
            }

            $modelo = new UsuariosModel();
            $u = $modelo->getByEmailAuth($email);

            if (!$u || !isset($u['id_usuario'])) {
                $this->responder(404, ['success' => false, 'message' => 'Usuario no encontrado']);
            }

            // Actualizar la contraseña usando el nuevo método del model
            $filas = $modelo->updatePasswordByEmail($email, $nuevaPwd);

            // Limpiar el token/código de reset para que no pueda reutilizarse
            $modelo->limpiarResetPasswordToken((int)$u['id_usuario']);
            // También borramos el código de verificación de email usado
            $modelo->marcarEmailVerificado((int)$u['id_usuario']);

            $this->responder(200, [
                'success' => true,
                'message' => 'Contraseña actualizada correctamente.',
                'filas'   => $filas
            ]);
        } catch (Throwable $e) {
            $this->responder(500, ['success' => false, 'message' => $e->getMessage()]);
        }
    }
}
