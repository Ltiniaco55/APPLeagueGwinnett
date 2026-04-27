<?php

declare(strict_types=1);

/**
 * ============================================================================
 *  JugadoresAdminController
 * ============================================================================
 *  Rutas ADMIN para gestión de jugadores.
 *  Opera sobre equipo_jugador (tabla de relaciones con estado).
 *
 *  GET  /admin/jugadores/pendientes
 *  POST /admin/jugadores/{id_relacion}/aprobar
 *  POST /admin/jugadores/{id_relacion}/rechazar
 *  POST /admin/jugadores/aprobar-lote
 *  PATCH /admin/jugadores/{id_jugador}/editar
 *  PATCH /admin/jugadores/{id_relacion}/dorsal
 *  POST  /admin/jugadores/{id_jugador}/foto
 * ============================================================================
 */

require_once __DIR__ . '/../core/Autenticacion.php';
require_once __DIR__ . '/../model/jugadoresModel.php';
require_once __DIR__ . '/../model/equipoJugadorModel.php';

class JugadoresAdminController
{
    private function responder(int $code, array $body): void
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($body, JSON_UNESCAPED_UNICODE);
        exit;
    }

    private function limpiar($val): string
    {
        return trim((string)($val ?? ''));
    }

    // =====================================================
    // GET /admin/jugadores/pendientes
    //   ?accion=ALTA|BAJA&id_equipo=X&categoria=Y
    // =====================================================
    public function pendientes(array $entrada = []): void
    {
        try {
            Autenticacion::requerirRol([Autenticacion::ROL_ADMIN]);

            $accion    = $this->limpiar($entrada['accion'] ?? '');
            $id_equipo = (int)($entrada['id_equipo'] ?? 0);
            $categoria = $this->limpiar($entrada['categoria'] ?? '');

            $modelo = new EquipoJugadorModel();
            $datos  = $modelo->getPendientesAdmin($accion, $id_equipo, $categoria);

            $this->responder(200, ['success' => true, 'data' => $datos]);
        } catch (Throwable $e) {
            $this->responder(500, ['success' => false, 'message' => $e->getMessage()]);
        }
    }

    // =====================================================
    // POST /admin/jugadores/{id_relacion}/aprobar
    // =====================================================
    public function aprobar(int $id_relacion): void
    {
        try {
            Autenticacion::requerirRol([Autenticacion::ROL_ADMIN]);

            $usuario  = Autenticacion::usuario();
            $id_admin = (int)$usuario['id_usuario'];

            $ejModel  = new EquipoJugadorModel();
            $relacion = $ejModel->getById($id_relacion);

            if (!$relacion) {
                $this->responder(404, ['success' => false, 'message' => 'Solicitud no encontrada']);
            }

            if ($relacion['estado'] !== 'PENDIENTE') {
                $this->responder(400, ['success' => false, 'message' => 'Esta solicitud ya fue resuelta']);
            }

            $accion = $relacion['accion_solicitada'];

            if ($accion === 'ALTA') {
                $filas = $ejModel->aprobarAlta($id_relacion, $id_admin);
                if (!$filas) {
                    $this->responder(409, ['success' => false, 'message' => 'No se pudo aprobar (ya resuelta por otro admin)']);
                }
                $this->responder(200, ['success' => true, 'message' => 'Alta aprobada correctamente']);
            } elseif ($accion === 'BAJA') {
                $filas = $ejModel->aprobarBaja($id_relacion);
                if (!$filas) {
                    $this->responder(409, ['success' => false, 'message' => 'No se pudo aprobar la baja (ya resuelta por otro admin)']);
                }
                $this->responder(200, ['success' => true, 'message' => 'Baja aprobada. Jugador eliminado del equipo.']);
            } else {
                $this->responder(400, ['success' => false, 'message' => 'Acción desconocida: ' . $accion]);
            }
        } catch (Throwable $e) {
            $this->responder(500, ['success' => false, 'message' => $e->getMessage()]);
        }
    }

    // =====================================================
    // POST /admin/jugadores/{id_relacion}/rechazar
    // =====================================================
    public function rechazar(int $id_relacion): void
    {
        try {
            Autenticacion::requerirRol([Autenticacion::ROL_ADMIN]);

            $usuario  = Autenticacion::usuario();
            $id_admin = (int)$usuario['id_usuario'];

            $ejModel  = new EquipoJugadorModel();
            $relacion = $ejModel->getById($id_relacion);

            if (!$relacion) {
                $this->responder(404, ['success' => false, 'message' => 'Solicitud no encontrada']);
            }

            if ($relacion['estado'] !== 'PENDIENTE') {
                $this->responder(400, ['success' => false, 'message' => 'Esta solicitud ya fue resuelta']);
            }

            $accion = $relacion['accion_solicitada'];

            if ($accion === 'ALTA') {
                // Rechazar alta → eliminar la relación pendiente
                $filas = $ejModel->rechazarAlta($id_relacion);
                if (!$filas) {
                    $this->responder(409, ['success' => false, 'message' => 'No se pudo rechazar (ya resuelta)']);
                }
                $this->responder(200, ['success' => true, 'message' => 'Alta rechazada. Solicitud eliminada.']);
            } elseif ($accion === 'BAJA') {
                // Rechazar baja → restaurar a ALTA
                $filas = $ejModel->rechazarBaja($id_relacion, $id_admin);
                if (!$filas) {
                    $this->responder(409, ['success' => false, 'message' => 'No se pudo rechazar la baja (ya resuelta)']);
                }
                $this->responder(200, ['success' => true, 'message' => 'Baja rechazada. Jugador restaurado a ALTA.']);
            } else {
                $this->responder(400, ['success' => false, 'message' => 'Acción desconocida: ' . $accion]);
            }
        } catch (Throwable $e) {
            $this->responder(500, ['success' => false, 'message' => $e->getMessage()]);
        }
    }

    // =====================================================
    // POST /admin/jugadores/aprobar-lote
    // Body: { ids: [1,2,3] }
    // =====================================================
    public function aprobarLote(array $entrada): void
    {
        try {
            Autenticacion::requerirRol([Autenticacion::ROL_ADMIN]);

            $usuario  = Autenticacion::usuario();
            $id_admin = (int)$usuario['id_usuario'];

            $ids = $entrada['ids'] ?? [];
            if (!is_array($ids) || empty($ids)) {
                $this->responder(400, ['success' => false, 'message' => 'No se recibieron IDs para aprobar en lote']);
            }

            // Solo IDs enteros positivos
            $ids = array_filter(array_map('intval', $ids), fn($id) => $id > 0);
            if (empty($ids)) {
                $this->responder(400, ['success' => false, 'message' => 'IDs inválidos']);
            }

            $ejModel = new EquipoJugadorModel();
            $filas   = $ejModel->aprobarLote(array_values($ids), $id_admin);

            $this->responder(200, [
                'success' => true,
                'message' => "$filas solicitud(es) aprobada(s) correctamente",
                'data'    => ['aprobadas' => $filas]
            ]);
        } catch (Throwable $e) {
            $this->responder(500, ['success' => false, 'message' => $e->getMessage()]);
        }
    }

    // =====================================================
    // PATCH /admin/jugadores/{id_jugador}/editar
    // Edita nombre, apellido, fecha_nacimiento del jugador global
    // =====================================================
    public function editarJugador(int $id_jugador, array $entrada): void
    {
        try {
            Autenticacion::requerirRol([Autenticacion::ROL_ADMIN]);

            $nombre   = $this->limpiar($entrada['nombre'] ?? '');
            $apellido = $this->limpiar($entrada['apellido'] ?? '');
            $fecha    = $this->limpiar($entrada['fecha_nacimiento'] ?? '');

            if (!$nombre || !$apellido || !$fecha) {
                $this->responder(400, ['success' => false, 'message' => 'Faltan campos: nombre, apellido, fecha_nacimiento']);
            }

            $jugModel = new JugadoresModel();
            $jugador  = $jugModel->getById($id_jugador);

            if (!$jugador) {
                $this->responder(404, ['success' => false, 'message' => 'Jugador no encontrado']);
            }

            $jugModel->update(
                $id_jugador,
                $nombre,
                $apellido,
                $fecha,
                $jugador['foto_path'],
                $jugador['id_usuario'] ?? null
            );

            $this->responder(200, ['success' => true, 'message' => 'Jugador actualizado correctamente']);
        } catch (Throwable $e) {
            $this->responder(500, ['success' => false, 'message' => $e->getMessage()]);
        }
    }

    // =====================================================
    // PATCH /admin/jugadores/{id_relacion}/dorsal
    // Body: { dorsal: 7 }
    // =====================================================
    public function corregirDorsal(int $id_relacion, array $entrada): void
    {
        try {
            Autenticacion::requerirRol([Autenticacion::ROL_ADMIN]);

            $dorsal = (int)($entrada['dorsal'] ?? 0);
            if ($dorsal < 1) {
                $this->responder(400, ['success' => false, 'message' => 'Dorsal inválido']);
            }

            $ejModel  = new EquipoJugadorModel();
            $relacion = $ejModel->getById($id_relacion);

            if (!$relacion) {
                $this->responder(404, ['success' => false, 'message' => 'Relación no encontrada']);
            }

            $ejModel->corregirDorsalAdmin(
                $id_relacion,
                $dorsal,
                (int)$relacion['id_equipo'],
                (int)$relacion['id_liga'],
                (int)$relacion['id_jugador']
            );

            $this->responder(200, ['success' => true, 'message' => 'Dorsal corregido correctamente']);
        } catch (Throwable $e) {
            $this->responder(500, ['success' => false, 'message' => $e->getMessage()]);
        }
    }

    // =====================================================
    // POST /admin/jugadores/{id_jugador}/foto
    // Admin puede reemplazar foto aunque ya exista
    // =====================================================
    public function subirFoto(int $id_jugador): void
    {
        try {
            Autenticacion::requerirRol([Autenticacion::ROL_ADMIN]);

            $jugModel = new JugadoresModel();
            $jugador  = $jugModel->getById($id_jugador);

            if (!$jugador) {
                $this->responder(404, ['success' => false, 'message' => 'Jugador no encontrado']);
            }

            if (!isset($_FILES['foto']) || $_FILES['foto']['error'] !== UPLOAD_ERR_OK) {
                $this->responder(400, ['success' => false, 'message' => 'No se recibió ninguna foto válida']);
            }

            $file    = $_FILES['foto'];
            $ext     = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'webp'];

            if (!in_array($ext, $allowed)) {
                $this->responder(400, ['success' => false, 'message' => 'Formato no permitido. Usa: jpg, png, webp']);
            }

            $uploadDir = __DIR__ . '/../../public/uploads/jugadores/' . $id_jugador;
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            // Eliminar foto anterior si existe
            foreach (glob($uploadDir . '/foto.*') as $old) {
                @unlink($old);
            }

            $filename = 'foto.' . $ext;
            $destPath = $uploadDir . '/' . $filename;
            $dbPath   = '/public/uploads/jugadores/' . $id_jugador . '/' . $filename;

            if (!move_uploaded_file($file['tmp_name'], $destPath)) {
                $this->responder(500, ['success' => false, 'message' => 'Error al guardar el archivo']);
            }

            // Admin puede forzar actualización de foto
            $db   = Database::getInstance();
            $stmt = $db->prepare("UPDATE jugador SET foto_path = ? WHERE id_jugador = ?");
            $stmt->execute([$dbPath, $id_jugador]);

            $this->responder(200, [
                'success'   => true,
                'message'   => 'Foto actualizada correctamente',
                'foto_path' => $dbPath
            ]);
        } catch (Throwable $e) {
            $this->responder(500, ['success' => false, 'message' => $e->getMessage()]);
        }
    }
}
