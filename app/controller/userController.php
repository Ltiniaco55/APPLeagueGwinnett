<?php

declare(strict_types=1);

require_once __DIR__ . '/../core/Autenticacion.php';
require_once __DIR__ . '/../model/usuariosModel.php';
require_once __DIR__ . '/../model/entrenadoresModel.php';
require_once __DIR__ . '/../model/entrenadorEquipoModel.php';

class UsuariosController
{
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

    public function modificar(int $id, array $entrada): void
    {
        try {
            Autenticacion::requerirAutenticacion();

            $usuarioSesion = Autenticacion::usuario();

            if (!$usuarioSesion) {
                $this->responder(401, [
                    'success' => false,
                    'message' => 'No autenticado'
                ]);
            }

            $idSesion = (int)($usuarioSesion['id_usuario'] ?? $usuarioSesion['id'] ?? 0);
            $rolSesion = strtoupper((string)($usuarioSesion['rol'] ?? ''));

            $esAdmin = $rolSesion === Autenticacion::ROL_ADMIN;
            $esPropietario = $idSesion === $id;

            if (!$esAdmin && !$esPropietario) {
                $this->responder(403, [
                    'success' => false,
                    'message' => 'No tienes permiso para modificar este usuario'
                ]);
            }

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

            if ($rolActual === Autenticacion::ROL_ADMIN && $rol !== Autenticacion::ROL_ADMIN) {
                if ($modelo->countAdmins() <= 1) {
                    $this->responder(409, [
                        'success' => false,
                        'message' => 'No se puede quitar el último administrador del sistema'
                    ]);
                }
            }

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

    public function subirFotoEntrenador(int $id): void
    {
        try {
            Autenticacion::requerirAutenticacion();

            $usuarioSesion = Autenticacion::usuario();

            if (!$usuarioSesion) {
                $this->responder(401, [
                    'success' => false,
                    'message' => 'No autenticado'
                ]);
            }

            $idSesion = (int)($usuarioSesion['id_usuario'] ?? $usuarioSesion['id'] ?? 0);
            $rolSesion = strtoupper((string)($usuarioSesion['rol'] ?? ''));

            $esAdmin = $rolSesion === Autenticacion::ROL_ADMIN;
            $esPropietario = $idSesion === $id;

            if (!$esAdmin && !$esPropietario) {
                $this->responder(403, [
                    'success' => false,
                    'message' => 'No autorizado para modificar esta foto'
                ]);
            }

            if (
                !isset($_FILES['foto']) ||
                $_FILES['foto']['error'] !== UPLOAD_ERR_OK
            ) {
                $this->responder(400, [
                    'success' => false,
                    'message' => 'No se recibió una imagen válida'
                ]);
            }

            $usuariosModel = new UsuariosModel();
            $entrenadoresModel = new EntrenadoresModel();

            $usuario = $usuariosModel->getById($id);

            if (!$usuario) {
                $this->responder(404, [
                    'success' => false,
                    'message' => 'Usuario no encontrado'
                ]);
            }

            $rolUsuario = strtoupper((string)($usuario['rol'] ?? ''));

            if (!in_array($rolUsuario, [Autenticacion::ROL_STAFF, Autenticacion::ROL_ADMIN], true)) {
                $this->responder(400, [
                    'success' => false,
                    'message' => 'Solo usuarios STAFF o ADMIN pueden tener foto de entrenador'
                ]);
            }

            $entrenador = $entrenadoresModel->getByUserId($id);

            if (!$entrenador) {
                $this->responder(400, [
                    'success' => false,
                    'message' => 'Este usuario todavía no existe como entrenador'
                ]);
            }

            $file = $_FILES['foto'];

            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $permitidas = ['jpg', 'jpeg', 'png', 'webp'];

            if (!in_array($ext, $permitidas, true)) {
                $this->responder(400, [
                    'success' => false,
                    'message' => 'Formato no permitido. Usa JPG, JPEG, PNG o WEBP'
                ]);
            }

            $mime = mime_content_type($file['tmp_name']);
            $mimesPermitidos = ['image/jpeg', 'image/png', 'image/webp'];

            if (!in_array($mime, $mimesPermitidos, true)) {
                $this->responder(400, [
                    'success' => false,
                    'message' => 'El archivo no es una imagen válida'
                ]);
            }

            $uploadDir = __DIR__ . '/../../public/uploads/entrenadores/' . $id;

            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $fotoAnterior = (string)($entrenador['foto'] ?? '');

            if ($fotoAnterior !== '') {
                $rutaAnteriorFisica = __DIR__ . '/../..' . $fotoAnterior;

                if (is_file($rutaAnteriorFisica)) {
                    @unlink($rutaAnteriorFisica);
                }
            }

            $filename = 'perfil_' . time() . '.' . $ext;
            $destPath = $uploadDir . '/' . $filename;
            $dbPath = '/public/uploads/entrenadores/' . $id . '/' . $filename;

            if (!move_uploaded_file($file['tmp_name'], $destPath)) {
                $this->responder(500, [
                    'success' => false,
                    'message' => 'Error al guardar la imagen'
                ]);
            }

            $entrenadoresModel->update((int)$entrenador['id_entrenador'], [
                'foto' => $dbPath
            ]);

            $this->responder(200, [
                'success' => true,
                'message' => 'Foto actualizada correctamente',
                'foto' => $dbPath
            ]);
        } catch (Throwable $e) {
            $this->responder(500, [
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }
}
