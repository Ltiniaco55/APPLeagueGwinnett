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
require_once __DIR__ . '/../model/entrenadoresModel.php';
require_once __DIR__ . '/../model/entrenadorEquipoModel.php';

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
                $this->responder(400, [
                    'success' => false,
                    'message' => 'ID inválido'
                ]);
            }

            $rol = strtoupper($this->limpiarTexto($entrada['rol'] ?? ''));

            $rolesValidos = [
                Autenticacion::ROL_ADMIN,
                Autenticacion::ROL_ARBITRO,
                Autenticacion::ROL_STAFF,
                Autenticacion::ROL_USUARIO
            ];

            if ($rol === '' || !in_array($rol, $rolesValidos, true)) {
                $this->responder(400, [
                    'success' => false,
                    'message' => 'Rol inválido'
                ]);
            }

            $modelo = new UsuariosModel();

            $existente = $modelo->getById($id);

            if (!$existente) {
                $this->responder(404, [
                    'success' => false,
                    'message' => 'Usuario no encontrado'
                ]);
            }

            $rolActual = strtoupper((string)($existente['rol'] ?? Autenticacion::ROL_USUARIO));

            /*
         * ============================================================
         * PROTECCIÓN 1:
         * No permitir quitar el último ADMIN del sistema.
         * Porque dejar la app sin admin es una forma elegante de pegarse un tiro en el CRUD.
         * ============================================================
         */
            if ($rolActual === Autenticacion::ROL_ADMIN && $rol !== Autenticacion::ROL_ADMIN) {
                if ($modelo->countAdmins() <= 1) {
                    $this->responder(409, [
                        'success' => false,
                        'message' => 'No se puede quitar el último administrador del sistema'
                    ]);
                }
            }

            /*
         * ============================================================
         * PROTECCIÓN 2:
         * Si el cambio entra o sale de ADMIN, pedir contraseña del admin actual.
         * Casos:
         *  - USUARIO / STAFF / ARBITRO -> ADMIN
         *  - ADMIN -> USUARIO / STAFF / ARBITRO
         * ============================================================
         */
            $cambioCriticoAdmin =
                $rolActual === Autenticacion::ROL_ADMIN ||
                $rol === Autenticacion::ROL_ADMIN;

            if ($cambioCriticoAdmin) {
                $passwordConfirmacion = (string)($entrada['password_confirmacion'] ?? '');

                if ($passwordConfirmacion === '') {
                    $this->responder(400, [
                        'success' => false,
                        'message' => 'Debes confirmar tu contraseña para modificar permisos de administrador'
                    ]);
                }

                $usuarioSesion = Autenticacion::usuario();

                if (!$usuarioSesion || empty($usuarioSesion['email'])) {
                    $this->responder(401, [
                        'success' => false,
                        'message' => 'No se pudo validar la sesión del administrador'
                    ]);
                }

                $adminValido = $modelo->verifyCredentials(
                    (string)$usuarioSesion['email'],
                    $passwordConfirmacion
                );

                if (!$adminValido) {
                    $this->responder(403, [
                        'success' => false,
                        'message' => 'Contraseña de confirmación incorrecta'
                    ]);
                }
            }

            /*
         * ============================================================
         * Si el nuevo rol ya no puede actuar como entrenador/staff,
         * limpiamos relaciones con equipos.
         *
         * STAFF y ADMIN sí pueden tener equipos asociados.
         * USUARIO y ARBITRO no.
         * ============================================================
         */
            if (!in_array($rol, [Autenticacion::ROL_STAFF, Autenticacion::ROL_ADMIN], true)) {
                $entrenadoresModel = new EntrenadoresModel();
                $entrenadorEquipoModel = new EntrenadorEquipoModel();

                $entrenador = $entrenadoresModel->getByUserId($id);

                if ($entrenador) {
                    $entrenadorEquipoModel->deleteByEntrenador((int)$entrenador['id_entrenador']);
                    $entrenadoresModel->delete((int)$entrenador['id_entrenador']);
                }

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
            $this->responder(500, [
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    // =========================================================================
    // ACTUALIZAR EQUIPO DE STAFF (PATCH /api/usuarios/{id}/equipo-staff)
    // =========================================================================

    public function actualizarEquiposStaff(int $id, array $entrada): void
    {
        try {
            Autenticacion::requerirRol([Autenticacion::ROL_ADMIN]);

            if ($id <= 0) {
                $this->responder(400, [
                    'success' => false,
                    'message' => 'ID de usuario inválido'
                ]);
            }

            $relaciones = $entrada['relaciones'] ?? [];

            error_log('DEBUG actualizarEquiposStaff entrada: ' . json_encode($entrada));
            error_log('DEBUG relaciones recibidas: ' . json_encode($relaciones));

            if (!is_array($relaciones)) {
                $this->responder(400, [
                    'success' => false,
                    'message' => 'El campo relaciones debe ser un array'
                ]);
            }

            $usuariosModel = new UsuariosModel();
            $entrenadoresModel = new EntrenadoresModel();
            $entrenadorEquipoModel = new EntrenadorEquipoModel();

            $usuario = $usuariosModel->getById($id);

            if (!$usuario) {
                $this->responder(404, [
                    'success' => false,
                    'message' => 'Usuario no encontrado'
                ]);
            }

            $rolUsuario = strtoupper((string)$usuario['rol']);

            if (!in_array($rolUsuario, [Autenticacion::ROL_STAFF, Autenticacion::ROL_ADMIN], true)) {
                $this->responder(400, [
                    'success' => false,
                    'message' => 'Solo usuarios STAFF o ADMIN pueden tener equipos asignados como entrenadores'
                ]);
            }

            $entrenador = $entrenadoresModel->getByUserId($id);

            if (!$entrenador) {
                $idEntrenador = $entrenadoresModel->insert(
                    $id,
                    $usuario['nombre'],
                    $usuario['apellido'],
                    $usuario['fecha_nacimiento'] ?? null,
                    $usuario['telefono'] ?? null,
                    $usuario['email'] ?? null
                );
            } else {
                $idEntrenador = (int)$entrenador['id_entrenador'];
            }


            error_log('DEBUG idEntrenador: ' . $idEntrenador);
            error_log('DEBUG relaciones antes de sincronizar: ' . json_encode($relaciones));
            $entrenadorEquipoModel->sincronizarEquiposEntrenador($idEntrenador, $relaciones);

            if (count($relaciones) === 0) {
                $entrenadoresModel->delete($idEntrenador);
                $usuariosModel->updateEquipoStaff($id, null);

                if ($rolUsuario === Autenticacion::ROL_STAFF) {
                    $usuariosModel->updateRol($id, Autenticacion::ROL_USUARIO);

                    $this->responder(200, [
                        'success' => true,
                        'message' => 'El usuario quedó sin equipos, por lo tanto pasó automáticamente a USUARIO'
                    ]);
                }

                $this->responder(200, [
                    'success' => true,
                    'message' => 'El admin quedó sin equipos asociados como entrenador, pero mantiene su rol ADMIN'
                ]);
            }

            $usuarioActualizado = $usuariosModel->getById($id);
            $usuarioActualizado = $this->limpiarUsuario($usuarioActualizado);

            $this->responder(200, [
                'success' => true,
                'message' => 'Equipos del staff actualizados correctamente',
                'debug' => [
                    'id_usuario' => $id,
                    'rol_usuario' => $usuario['rol'],
                    'id_entrenador' => $idEntrenador,
                    'relaciones_recibidas' => $relaciones
                ],
                'data' => $usuarioActualizado
            ]);
        } catch (Throwable $e) {
            $this->responder(500, [
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    public function obtenerEquiposStaff(int $id): void
    {
        try {
            Autenticacion::requerirRol([Autenticacion::ROL_ADMIN]);

            if ($id <= 0) {
                $this->responder(400, [
                    'success' => false,
                    'message' => 'ID de usuario inválido'
                ]);
            }

            $entrenadoresModel = new EntrenadoresModel();
            $entrenadorEquipoModel = new EntrenadorEquipoModel();

            $entrenador = $entrenadoresModel->getByUserId($id);

            if (!$entrenador) {
                $this->responder(200, [
                    'success' => true,
                    'data' => []
                ]);
            }

            $relaciones = $entrenadorEquipoModel->getByEntrenador(
                (int)$entrenador['id_entrenador']
            );

            $this->responder(200, [
                'success' => true,
                'data' => $relaciones
            ]);
        } catch (Throwable $e) {
            $this->responder(500, [
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }
}
