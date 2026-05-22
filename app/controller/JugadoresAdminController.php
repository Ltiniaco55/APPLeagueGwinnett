<?php

declare(strict_types=1);

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

    private function subirArchivo(string $clave, int $id_jugador): ?string
    {
        if (!isset($_FILES[$clave]) || $_FILES[$clave]['error'] !== UPLOAD_ERR_OK) {
            return null;
        }

        $file    = $_FILES[$clave];
        $ext     = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];

        if (!in_array($ext, $allowed)) {
            throw new \InvalidArgumentException(
                "Formato no permitido para «{$clave}». Usar: jpg, jpeg, png, webp."
            );
        }

        $uploadDir = __DIR__ . '/../../public/uploads/jugadores/' . $id_jugador;
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        foreach (glob($uploadDir . '/' . $clave . '.*') ?: [] as $old) {
            @unlink($old);
        }

        $filename = $clave . '.' . $ext;
        $destPath = $uploadDir . '/' . $filename;
        $dbPath   = '/public/uploads/jugadores/' . $id_jugador . '/' . $filename;

        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            throw new \RuntimeException("Error al guardar el archivo «{$clave}».");
        }

        return $dbPath;
    }

    public function pendientes(array $entrada = []): void
    {
        try {
            Autenticacion::requerirRol([Autenticacion::ROL_ADMIN]);

            $accion    = $this->limpiar($entrada['accion']    ?? '');
            $id_equipo = (int)($entrada['id_equipo']          ?? 0);
            $categoria = $this->limpiar($entrada['categoria'] ?? '');

            $modelo = new EquipoJugadorModel();
            $datos  = $modelo->getPendientesAdmin($accion, $id_equipo, $categoria);

            $this->responder(200, ['success' => true, 'data' => $datos]);
        } catch (Throwable $e) {
            $this->responder(500, ['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function altaDirecta(array $entrada): void
    {
        try {
            Autenticacion::requerirRol([Autenticacion::ROL_ADMIN]);

            $nombre    = $this->limpiar($entrada['nombre']           ?? '');
            $apellido  = $this->limpiar($entrada['apellido']         ?? '');
            $fecha     = $this->limpiar($entrada['fecha_nacimiento'] ?? '');
            $id_equipo = (int)($entrada['id_equipo']                 ?? 0);
            $id_liga   = (int)($entrada['id_liga']                   ?? 0);

            if (!$nombre || !$apellido || !$fecha || !$id_equipo || !$id_liga) {
                $this->responder(400, [
                    'success' => false,
                    'message' => 'Faltan campos obligatorios: nombre, apellido, fecha_nacimiento, id_equipo, id_liga'
                ]);
            }

            if (!isset($_FILES['documento_identidad'])
                || $_FILES['documento_identidad']['error'] !== UPLOAD_ERR_OK) {
                $this->responder(400, [
                    'success' => false,
                    'message' => 'El documento de identidad es obligatorio (jpg, jpeg, png, webp)'
                ]);
            }

            $hoy        = new \DateTime();
            $nacimiento = new \DateTime($fecha);
            $edad       = (int)$hoy->diff($nacimiento)->y;
            $esmenor    = ($edad < 18);

            $nombres_padres  = null;
            $email_padres    = null;
            $telefono_padres = null;

            if ($esmenor) {
                $nombres_padres  = $this->limpiar($entrada['nombres_padres']  ?? '');
                $email_padres    = $this->limpiar($entrada['email_padres']    ?? '');
                $telefono_padres = $this->limpiar($entrada['telefono_padres'] ?? '');

                if (!$nombres_padres || !$email_padres || !$telefono_padres) {
                    $this->responder(400, [
                        'success' => false,
                        'message' => 'Jugador menor de 18: nombres_padres, email_padres y telefono_padres son obligatorios'
                    ]);
                }
            }

            $jugModel = new JugadoresModel();
            $ejModel  = new EquipoJugadorModel();

            $jugadorExistente = $jugModel->getByKey($nombre, $apellido, $fecha);

            if ($jugadorExistente) {
                $id_jugador = (int)$jugadorExistente['id_jugador'];
            } else {
                $id_jugador = $jugModel->insert($nombre, $apellido, $fecha);
            }

            if ($ejModel->existeRelacion($id_jugador, $id_equipo, $id_liga)) {
                $this->responder(409, [
                    'success' => false,
                    'message' => 'Este jugador ya está registrado en este equipo y liga'
                ]);
            }

            $foto_path = $this->subirArchivo('foto',                $id_jugador);
            $doc_path  = $this->subirArchivo('documento_identidad', $id_jugador);

            $jugModel->actualizarDocumentos(
                $id_jugador,
                $foto_path,
                $doc_path,
                $esmenor ? $nombres_padres  : null,
                $esmenor ? $email_padres    : null,
                $esmenor ? $telefono_padres : null
            );

            $ejModel->insertarRelacion($id_jugador, $id_equipo, $id_liga, null);

            $avisos = $jugModel->buscarCoincidenciasEnOtrosEquipos(
                $nombre, $apellido, $fecha, $id_equipo, $id_liga
            );

            $respuesta = [
                'success' => true,
                'message' => $jugadorExistente
                    ? 'Jugador existente asignado al equipo correctamente'
                    : 'Jugador dado de alta y asignado al equipo correctamente',
                'data'    => $jugModel->getById($id_jugador),
            ];

            if (!empty($avisos)) {
                $respuesta['avisos']    = $avisos;
                $respuesta['aviso_msg'] = 'Este jugador ya estaba registrado en otros equipos';
            }

            $this->responder(201, $respuesta);
        } catch (\InvalidArgumentException $e) {
            $this->responder(400, ['success' => false, 'message' => $e->getMessage()]);
        } catch (Throwable $e) {
            $this->responder(500, ['success' => false, 'message' => $e->getMessage()]);
        }
    }

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
                $filas = $ejModel->rechazarAlta($id_relacion);
                if (!$filas) {
                    $this->responder(409, ['success' => false, 'message' => 'No se pudo rechazar (ya resuelta)']);
                }
                $this->responder(200, ['success' => true, 'message' => 'Alta rechazada. Solicitud eliminada.']);
            } elseif ($accion === 'BAJA') {
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

    public function editarJugador(int $id_jugador, array $entrada): void
    {
        try {
            Autenticacion::requerirRol([Autenticacion::ROL_ADMIN]);

            $nombre   = $this->limpiar($entrada['nombre']           ?? '');
            $apellido = $this->limpiar($entrada['apellido']         ?? '');
            $fecha    = $this->limpiar($entrada['fecha_nacimiento'] ?? '');

            if (!$nombre || !$apellido || !$fecha) {
                $this->responder(400, ['success' => false, 'message' => 'Faltan campos: nombre, apellido, fecha_nacimiento']);
            }

            $jugModel = new JugadoresModel();
            $jugador  = $jugModel->getById($id_jugador);

            if (!$jugador) {
                $this->responder(404, ['success' => false, 'message' => 'Jugador no encontrado']);
            }

            $jugModel->update($id_jugador, $nombre, $apellido, $fecha,
                $jugador['foto_path'], $jugador['id_usuario'] ?? null);

            $this->responder(200, ['success' => true, 'message' => 'Jugador actualizado correctamente']);
        } catch (Throwable $e) {
            $this->responder(500, ['success' => false, 'message' => $e->getMessage()]);
        }
    }

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
                $id_relacion, $dorsal,
                (int)$relacion['id_equipo'],
                (int)$relacion['id_liga'],
                (int)$relacion['id_jugador']
            );

            $this->responder(200, ['success' => true, 'message' => 'Dorsal corregido correctamente']);
        } catch (Throwable $e) {
            $this->responder(500, ['success' => false, 'message' => $e->getMessage()]);
        }
    }

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

            foreach (glob($uploadDir . '/foto.*') ?: [] as $old) {
                @unlink($old);
            }

            $filename = 'foto.' . $ext;
            $destPath = $uploadDir . '/' . $filename;
            $dbPath   = '/public/uploads/jugadores/' . $id_jugador . '/' . $filename;

            if (!move_uploaded_file($file['tmp_name'], $destPath)) {
                $this->responder(500, ['success' => false, 'message' => 'Error al guardar el archivo']);
            }

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
