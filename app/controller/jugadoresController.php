<?php

declare(strict_types=1);

/**
 * ============================================================================
 *  JugadoresController
 * ============================================================================
 *  Operaciones genéricas sobre la entidad jugador global.
 *
 *  GET    /jugadores          → seleccionar()   Listado / búsqueda
 *  GET    /jugadores/{id}     → localizar()     Detalle individual
 *  POST   /jugadores/{id}/foto → subirFoto()    Subida de foto (solo una vez)
 *  DELETE /jugadores/{id}     → eliminar()      Solo ADMIN
 *
 *  NOTA: Toda la lógica de alta, baja y aprobación/rechazo vive en:
 *   - JugadoresAdminController  (flujo ADMIN)
 *   - JugadoresStaffController  (flujo STAFF)
 * ============================================================================
 */

require_once __DIR__ . '/../core/Autenticacion.php';
require_once __DIR__ . '/../model/jugadoresModel.php';
require_once __DIR__ . '/../model/equipoJugadorModel.php';

class JugadoresController
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

    // =====================================================
    // SELECCIONAR (GET /jugadores)
    // =====================================================
    public function seleccionar(array $entrada = []): void
    {
        try {
            $nombre   = $this->limpiarTexto($entrada['nombre'] ?? '');
            $apellido = $this->limpiarTexto($entrada['apellido'] ?? '');

            $modelo = new JugadoresModel();

            $usuarioActual = Autenticacion::usuario();
            $esStaff       = $usuarioActual && $usuarioActual['rol'] === Autenticacion::ROL_STAFF;

            if ($esStaff) {
                require_once __DIR__ . '/../model/entrenadoresModel.php';
                require_once __DIR__ . '/../model/entrenadorEquipoModel.php';
                $entModel   = new EntrenadoresModel();
                $entEqModel = new EntrenadorEquipoModel();

                $entrenador = $entModel->getByUserId((int)$usuarioActual['id_usuario']);
                $misIds     = [];
                if ($entrenador) {
                    $misEquipos = $entEqModel->getByEntrenador((int)$entrenador['id_entrenador']);
                    foreach ($misEquipos as $me) {
                        $misIds[] = (int)$me['id_equipo'];
                    }
                }

                if (empty($misIds)) {
                    $datos = [];
                } else {
                    $datos = ($nombre || $apellido)
                        ? $modelo->searchStaff($nombre, $apellido, $misIds)
                        : $modelo->getAllStaff($misIds);
                }
            } else {
                $datos = ($nombre || $apellido)
                    ? $modelo->search($nombre, $apellido)
                    : $modelo->getAll();
            }

            $this->responder(200, ['success' => true, 'data' => $datos]);
        } catch (Throwable $e) {
            $this->responder(500, ['success' => false, 'message' => $e->getMessage()]);
        }
    }

    // =====================================================
    // LOCALIZAR (GET /jugadores/{id})
    // =====================================================
    public function localizar(int $id): void
    {
        try {
            if ($id <= 0) $this->responder(400, ['success' => false, 'message' => 'ID inválido']);

            $modelo  = new JugadoresModel();
            $jugador = $modelo->getById($id);

            if (!$jugador) $this->responder(404, ['success' => false, 'message' => 'Jugador no encontrado']);

            $this->responder(200, ['success' => true, 'data' => $jugador]);
        } catch (Throwable $e) {
            $this->responder(500, ['success' => false, 'message' => $e->getMessage()]);
        }
    }

    // =====================================================
    // SUBIR FOTO INDIVIDUAL (POST /jugadores/{id}/foto)
    // Solo si no tiene foto aún (operación inmutable para STAFF)
    // =====================================================
    public function subirFoto(int $id): void
    {
        try {
            Autenticacion::requerirRol([Autenticacion::ROL_ADMIN, Autenticacion::ROL_STAFF]);

            $modelo  = new JugadoresModel();
            $jugador = $modelo->getById($id);

            if (!$jugador) {
                $this->responder(404, ['success' => false, 'message' => 'Jugador no encontrado']);
            }

            // STAFF: foto inmutable
            if (!Autenticacion::tieneRol([Autenticacion::ROL_ADMIN]) && $modelo->tieneFoto($id)) {
                $this->responder(409, [
                    'success' => false,
                    'message' => 'Este jugador ya tiene foto asignada. No se puede modificar.'
                ]);
            }

            if (!isset($_FILES['foto']) || $_FILES['foto']['error'] !== UPLOAD_ERR_OK) {
                $this->responder(400, ['success' => false, 'message' => 'No se recibió ninguna foto válida']);
            }

            $file    = $_FILES['foto'];
            $ext     = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'webp'];

            if (!in_array($ext, $allowed)) {
                $this->responder(400, ['success' => false, 'message' => 'Formato no permitido. Usar: jpg, png, webp']);
            }

            $uploadDir = __DIR__ . '/../../public/uploads/jugadores/' . $id;
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $filename = 'foto.' . $ext;
            $destPath = $uploadDir . '/' . $filename;
            $dbPath   = '/public/uploads/jugadores/' . $id . '/' . $filename;

            if (!move_uploaded_file($file['tmp_name'], $destPath)) {
                $this->responder(500, ['success' => false, 'message' => 'Error al guardar el archivo']);
            }

            $filas = $modelo->guardarFotoSiNoExiste($id, $dbPath);

            if (!$filas) {
                @unlink($destPath);
                $this->responder(409, [
                    'success' => false,
                    'message' => 'La foto fue asignada por otro proceso. No se puede modificar.'
                ]);
            }

            $this->responder(200, [
                'success'   => true,
                'message'   => 'Foto subida correctamente',
                'foto_path' => $dbPath
            ]);
        } catch (Throwable $e) {
            $this->responder(500, ['success' => false, 'message' => $e->getMessage()]);
        }
    }

    // =====================================================
    // ELIMINAR (DELETE /jugadores/{id})  — Solo ADMIN
    // =====================================================
    public function eliminar(int $id): void
    {
        try {
            Autenticacion::requerirRol([Autenticacion::ROL_ADMIN]);

            $modelo = new JugadoresModel();

            if (!$modelo->getById($id)) {
                $this->responder(404, ['success' => false, 'message' => 'Jugador no encontrado']);
            }

            $modelo->delete($id);

            $this->responder(200, ['success' => true, 'message' => 'Jugador eliminado']);
        } catch (Throwable $e) {
            $this->responder(500, ['success' => false, 'message' => $e->getMessage()]);
        }
    }
}
