<?php

declare(strict_types=1);

/**
 * ============================================================================
 *  UsuariosController (compatible con tu UsuariosModel)
 * ============================================================================
 *  IMPORTANTE:
 *   - Sin funciones de Auth (login/logout) -> van en AuthController
 *   - No devuelve nunca el campo 'pwd'.
 * ============================================================================
 */

require_once __DIR__ . '/../core/Autenticacion.php';
require_once __DIR__ . '/../model/usuariosModel.php';

class UsuariosController
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

    private function limpiarListaUsuarios(array $usuarios): array
    {
        foreach ($usuarios as &$u) {
            if (isset($u['pwd'])) unset($u['pwd']);
        }
        return $usuarios;
    }

    // =========================================================================
    // SELECCIONAR (GET /api/usuarios)
    // Soporta filtros opcionales:
    //  - ?q=texto
    //  - ?nombre=...&apellido=...&email=...
    //  
    // =========================================================================
    public function seleccionar(array $entrada = []): void
    {
        try {
            $modelo = new UsuariosModel();

            $q = $this->limpiarTexto($entrada['q'] ?? '');
            $nombre = $this->limpiarTexto($entrada['nombre'] ?? '');
            $apellido = $this->limpiarTexto($entrada['apellido'] ?? '');
            $email = $this->limpiarTexto($entrada['email'] ?? '');

            if ($nombre !== '' || $apellido !== '' || $email !== '') {
                $datos = $modelo->searchFields($nombre, $apellido, $email);
            } elseif ($q !== '') {
                $datos = $modelo->search($q);
            } else {
                $datos = $modelo->getAll();
            }

            $datos = $this->limpiarListaUsuarios($datos);

            $this->responder(200, ['success' => true, 'data' => $datos]);
        } catch (Throwable $e) {
            $this->responder(500, ['success' => false, 'message' => $e->getMessage()]);
        }
    }

    // =========================================================================
    // LOCALIZAR (GET /api/usuarios/{id})
    // =========================================================================
    public function localizar(int $id): void
    {
        try {
            if ($id <= 0) {
                $this->responder(400, ['success' => false, 'message' => 'ID inválido']);
            }

            $modelo = new UsuariosModel();
            $usuario = $modelo->getById($id);

            if (!$usuario) {
                $this->responder(404, ['success' => false, 'message' => 'Usuario no encontrado']);
            }

            $usuario = $this->limpiarUsuario($usuario);

            $this->responder(200, ['success' => true, 'data' => $usuario]);
        } catch (Throwable $e) {
            $this->responder(500, ['success' => false, 'message' => $e->getMessage()]);
        }
    }

    // =========================================================================
    // INSERTAR (POST /api/usuarios)
    // (Esto es “crear usuario” genérico. El registro público va en AuthController)
    // =========================================================================
    public function insertar(array $entrada): void
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

            $modelo = new UsuariosModel();

            if ($modelo->emailExists($email)) {
                $this->responder(409, ['success' => false, 'message' => 'Ya existe un usuario con ese email']);
            }

            $idNuevo = $modelo->insert($nombre, $apellido, $fecha_nacimiento, $email, $pwd, $telefono);

            $usuarioCreado = $modelo->getById((int)$idNuevo);
            $usuarioCreado = $this->limpiarUsuario($usuarioCreado);

            $this->responder(201, [
                'success' => true,
                'message' => 'Usuario creado exitosamente',
                'data' => $usuarioCreado
            ]);
        } catch (Throwable $e) {
            $this->responder(500, ['success' => false, 'message' => $e->getMessage()]);
        }
    }

    // =========================================================================
    // MODIFICAR (PUT /api/usuarios/{id})
    // - SOLO ADMIN
    // =========================================================================
    public function modificar(int $id, array $entrada): void
    {
        try {
            Autenticacion::requerirRol([Autenticacion::ROL_ADMIN]);

            if ($id <= 0) {
                $this->responder(400, ['success' => false, 'message' => 'Falta el campo obligatorio: id']);
            }

            $modelo = new UsuariosModel();
            $existente = $modelo->getById($id);

            if (!$existente) {
                $this->responder(404, ['success' => false, 'message' => 'No existe un usuario con ese ID']);
            }

            $nombre = $this->limpiarTexto($entrada['nombre'] ?? '');
            $apellido = $this->limpiarTexto($entrada['apellido'] ?? '');
            $fecha_nacimiento = $this->limpiarTexto($entrada['fecha_nacimiento'] ?? '');
            $email = $this->limpiarTexto($entrada['email'] ?? '');
            $telefono = array_key_exists('telefono', $entrada) ? $this->limpiarTexto($entrada['telefono']) : null;

            if ($nombre === '' || $apellido === '' || $fecha_nacimiento === '' || $email === '') {
                $this->responder(400, ['success' => false, 'message' => 'Faltan campos obligatorios para actualizar']);
            }

            $modelo->update($id, $nombre, $apellido, $fecha_nacimiento, $email, $telefono);

            if (array_key_exists('pwd', $entrada) && (string)$entrada['pwd'] !== '') {
                $modelo->updatePassword($id, (string)$entrada['pwd']);
            }

            $usuarioActualizado = $modelo->getById($id);
            $usuarioActualizado = $this->limpiarUsuario($usuarioActualizado);

            $this->responder(200, [
                'success' => true,
                'message' => 'Usuario actualizado exitosamente',
                'data' => $usuarioActualizado
            ]);
        } catch (Throwable $e) {
            $this->responder(500, ['success' => false, 'message' => $e->getMessage()]);
        }
    }

    // =========================================================================
    // ELIMINAR (DELETE /api/usuarios/{id})
    // =========================================================================
    public function eliminar(int $id): void
    {
        try {
            Autenticacion::requerirRol([Autenticacion::ROL_ADMIN]);

            if ($id <= 0) {
                $this->responder(400, ['success' => false, 'message' => 'ID inválido']);
            }

            $modelo = new UsuariosModel();
            $existente = $modelo->getById($id);

            if (!$existente) {
                $this->responder(404, ['success' => false, 'message' => 'No existe un usuario con ese ID']);
            }

            $modelo->delete($id);

            $this->responder(200, [
                'success' => true,
                'message' => 'Usuario eliminado correctamente'
            ]);
        } catch (Throwable $e) {
            $this->responder(500, ['success' => false, 'message' => $e->getMessage()]);
        }
    }

    // =========================================================================
    // ACTUALIZAR ROL (PATCH /api/usuarios/{id}/rol)
    // =========================================================================
    public function actualizarRol(int $id, array $entrada): void
    {
        try {
            Autenticacion::requerirRol([Autenticacion::ROL_ADMIN]);

            if ($id <= 0) {
                $this->responder(400, ['success' => false, 'message' => 'ID inválido']);
            }

            $rol = strtoupper($this->limpiarTexto($entrada['rol'] ?? ''));

            $rolesValidos = [
                Autenticacion::ROL_ADMIN,
                Autenticacion::ROL_ARBITRO,
                Autenticacion::ROL_STAFF,
                Autenticacion::ROL_USUARIO
            ];

            if ($rol === '' || !in_array($rol, $rolesValidos, true)) {
                $this->responder(400, ['success' => false, 'message' => 'Rol inválido']);
            }

            $modelo = new UsuariosModel();

            $existente = $modelo->getById($id);
            if (!$existente) {
                $this->responder(404, ['success' => false, 'message' => 'Usuario no encontrado']);
            }

            // Si el rol ya no es STAFF, limpiar id_equipo automático por seguridad
            if ($rol !== Autenticacion::ROL_STAFF) {
                $modelo->updateEquipoStaff($id, null);
            }

            $filas = $modelo->updateRol($id, $rol);

            $usuarioActualizado = $modelo->getById($id);
            $usuarioActualizado = $this->limpiarUsuario($usuarioActualizado);

            $this->responder(200, [
                'success' => true,
                'message' => 'Rol actualizado correctamente',
                'filas_afectadas' => $filas,
                'data' => $usuarioActualizado
            ]);
        } catch (Throwable $e) {
            $this->responder(500, ['success' => false, 'message' => $e->getMessage()]);
        }
    }

    // =========================================================================
    // ACTUALIZAR EQUIPO DE STAFF (PATCH /api/usuarios/{id}/equipo-staff)
    // =========================================================================
    public function actualizarEquipoStaff(int $id, array $entrada): void
    {
        try {
            Autenticacion::requerirRol([Autenticacion::ROL_ADMIN]);

            if ($id <= 0) {
                $this->responder(400, ['success' => false, 'message' => 'ID de usuario inválido']);
            }

            $idEquipo = isset($entrada['id_equipo']) ? (int)$entrada['id_equipo'] : null;

            if ($idEquipo !== null && $idEquipo <= 0) {
                $this->responder(400, ['success' => false, 'message' => 'ID de equipo inválido']);
            }

            $modelo = new UsuariosModel();

            $usuario = $modelo->getById($id);
            if (!$usuario) {
                $this->responder(404, ['success' => false, 'message' => 'Usuario no encontrado']);
            }

            if ($usuario['rol'] !== Autenticacion::ROL_STAFF && $idEquipo !== null) {
                $this->responder(400, ['success' => false, 'message' => 'Solo a usuarios con rol STAFF se les puede asignar un equipo']);
            }

            $filas = $modelo->updateEquipoStaff($id, $idEquipo);

            $usuarioActualizado = $modelo->getById($id);
            $usuarioActualizado = $this->limpiarUsuario($usuarioActualizado);

            $this->responder(200, [
                'success' => true,
                'message' => 'Equipo de staff actualizado correctamente',
                'filas_afectadas' => $filas,
                'data' => $usuarioActualizado
            ]);
        } catch (Throwable $e) {
            $this->responder(500, ['success' => false, 'message' => $e->getMessage()]);
        }
    }
}
