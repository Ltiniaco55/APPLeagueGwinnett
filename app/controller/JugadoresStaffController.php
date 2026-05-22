<?php

declare(strict_types=1);

require_once __DIR__ . '/../core/Autenticacion.php';
require_once __DIR__ . '/../model/jugadoresModel.php';
require_once __DIR__ . '/../model/equipoJugadorModel.php';
require_once __DIR__ . '/../model/entrenadoresModel.php';
require_once __DIR__ . '/../model/entrenadorEquipoModel.php';

class JugadoresStaffController
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

    private function getMisEquipoIds(): array
    {
        $usuario = Autenticacion::usuario();
        if (!$usuario) return [];

        $entModel   = new EntrenadoresModel();
        $entEqModel = new EntrenadorEquipoModel();

        $entrenador = $entModel->getByUserId((int)$usuario['id_usuario']);
        if (!$entrenador) return [];

        $misEquipos = $entEqModel->getByEntrenador((int)$entrenador['id_entrenador']);
        return array_map(fn($me) => (int)$me['id_equipo'], $misEquipos);
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

        $filename = $clave . '.' . $ext;
        $destPath = $uploadDir . '/' . $filename;
        $dbPath   = '/public/uploads/jugadores/' . $id_jugador . '/' . $filename;

        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            throw new \RuntimeException("Error al guardar el archivo «{$clave}».");
        }

        return $dbPath;
    }

    public function pendientes(): void
    {
        try {
            Autenticacion::requerirRol([Autenticacion::ROL_STAFF, Autenticacion::ROL_ADMIN]);

            $misIds = $this->getMisEquipoIds();
            $modelo = new EquipoJugadorModel();
            $datos  = $modelo->getPendientesStaff($misIds);

            $this->responder(200, ['success' => true, 'data' => $datos]);
        } catch (Throwable $e) {
            $this->responder(500, ['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function plantilla(array $entrada = []): void
    {
        try {
            Autenticacion::requerirRol([Autenticacion::ROL_STAFF, Autenticacion::ROL_ADMIN]);

            $misIds       = $this->getMisEquipoIds();
            $nombreFiltro = $this->limpiar($entrada['nombre']    ?? '');
            $catFiltro    = $this->limpiar($entrada['categoria'] ?? '');
            $id_equipo    = (int)($entrada['id_equipo']          ?? 0);
            $id_liga      = (int)($entrada['id_liga']            ?? 0);

            $modelo = new EquipoJugadorModel();

            if ($id_equipo && $id_liga) {
                if (!in_array($id_equipo, $misIds, true)) {
                    $this->responder(403, ['success' => false, 'message' => 'No autorizado para este equipo']);
                }
                $datos = $modelo->getPlantillaConJugadores($id_equipo, $id_liga, $nombreFiltro);
            } else {
                $datos = $modelo->getPlantillaStaff($misIds, $nombreFiltro, $catFiltro);
            }

            $this->responder(200, ['success' => true, 'data' => $datos]);
        } catch (Throwable $e) {
            $this->responder(500, ['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function alta(array $entrada): void
    {
        try {
            Autenticacion::requerirRol([Autenticacion::ROL_STAFF, Autenticacion::ROL_ADMIN]);

            $usuario    = Autenticacion::usuario();
            $id_usuario = (int)$usuario['id_usuario'];

            $nombre    = $this->limpiar($entrada['nombre']           ?? '');
            $apellido  = $this->limpiar($entrada['apellido']         ?? '');
            $fecha     = $this->limpiar($entrada['fecha_nacimiento'] ?? '');
            $id_equipo = (int)($entrada['id_equipo']                 ?? 0);
            $id_liga   = (int)($entrada['id_liga']                   ?? 0);

            if (!$nombre || !$apellido || !$fecha || !$id_equipo || !$id_liga) {
                $this->responder(400, ['success' => false, 'message' => 'Faltan campos obligatorios']);
            }

            if (!isset($_FILES['documento_identidad'])
                || $_FILES['documento_identidad']['error'] !== UPLOAD_ERR_OK) {
                $this->responder(400, [
                    'success' => false,
                    'message' => 'El documento de identidad es obligatorio (jpg, jpeg, png, webp)'
                ]);
            }

            Autenticacion::requerirStaffDeEquipo($id_equipo);

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

                if ($ejModel->existeRelacion($id_jugador, $id_equipo, $id_liga)) {
                    $this->responder(409, [
                        'success' => false,
                        'message' => 'Este jugador ya tiene una solicitud activa o está dado de alta en este equipo/liga'
                    ]);
                }
            } else {
                $id_jugador = $jugModel->insert($nombre, $apellido, $fecha);
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

            $ejModel->insertarPendiente($id_jugador, $id_equipo, $id_liga, $id_usuario);

            $avisos = $jugModel->buscarCoincidenciasEnOtrosEquipos(
                $nombre, $apellido, $fecha, $id_equipo, $id_liga
            );

            $respuesta = [
                'success' => true,
                'message' => 'Solicitud de alta enviada correctamente',
                'data'    => ['id_jugador' => $id_jugador],
            ];

            if (!empty($avisos)) {
                $respuesta['avisos']    = $avisos;
                $respuesta['aviso_msg'] = 'Este jugador ya existe en otros equipos. La solicitud fue enviada a revisión.';
            }

            $this->responder(201, $respuesta);
        } catch (\InvalidArgumentException $e) {
            $this->responder(400, ['success' => false, 'message' => $e->getMessage()]);
        } catch (Throwable $e) {
            $this->responder(500, ['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function solicitarBaja(int $id_relacion): void
    {
        try {
            Autenticacion::requerirRol([Autenticacion::ROL_STAFF, Autenticacion::ROL_ADMIN]);

            $usuario    = Autenticacion::usuario();
            $id_usuario = (int)$usuario['id_usuario'];

            $ejModel  = new EquipoJugadorModel();
            $relacion = $ejModel->getById($id_relacion);

            if (!$relacion) {
                $this->responder(404, ['success' => false, 'message' => 'Relación no encontrada']);
            }

            Autenticacion::requerirStaffDeEquipo((int)$relacion['id_equipo']);

            if ($relacion['estado'] !== 'ALTA' || $relacion['accion_solicitada'] !== null) {
                $this->responder(409, [
                    'success' => false,
                    'message' => 'No se puede solicitar baja: la relación ya tiene una acción pendiente o no está activa'
                ]);
            }

            $filas = $ejModel->solicitarBaja($id_relacion, $id_usuario);

            if (!$filas) {
                $this->responder(409, ['success' => false, 'message' => 'No se pudo procesar la solicitud de baja']);
            }

            $this->responder(200, [
                'success' => true,
                'message' => 'Baja solicitada. Pendiente de aprobación por el administrador.'
            ]);
        } catch (Throwable $e) {
            $this->responder(500, ['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function subirFoto(int $id_jugador): void
    {
        try {
            Autenticacion::requerirRol([Autenticacion::ROL_STAFF, Autenticacion::ROL_ADMIN]);

            $jugModel = new JugadoresModel();
            $jugador  = $jugModel->getById($id_jugador);

            if (!$jugador) {
                $this->responder(404, ['success' => false, 'message' => 'Jugador no encontrado']);
            }

            if ($jugModel->tieneFoto($id_jugador)) {
                $this->responder(409, [
                    'success' => false,
                    'message' => 'Este jugador ya tiene foto. Solo ADMIN puede corregirla.'
                ]);
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

            $filename = 'foto.' . $ext;
            $destPath = $uploadDir . '/' . $filename;
            $dbPath   = '/public/uploads/jugadores/' . $id_jugador . '/' . $filename;

            if (!move_uploaded_file($file['tmp_name'], $destPath)) {
                $this->responder(500, ['success' => false, 'message' => 'Error al guardar el archivo']);
            }

            $filas = $jugModel->guardarFotoSiNoExiste($id_jugador, $dbPath);

            if (!$filas) {
                @unlink($destPath);
                $this->responder(409, [
                    'success' => false,
                    'message' => 'La foto ya fue asignada por otro proceso.'
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

    public function asignarDorsal(array $entrada): void
    {
        try {
            Autenticacion::requerirRol([Autenticacion::ROL_STAFF, Autenticacion::ROL_ADMIN]);

            $id_jugador = (int)($entrada['id_jugador'] ?? 0);
            $id_equipo  = (int)($entrada['id_equipo']  ?? 0);
            $id_liga    = (int)($entrada['id_liga']    ?? 0);
            $dorsal     = (int)($entrada['dorsal']     ?? 0);

            if (!$id_jugador || !$id_equipo || !$id_liga || $dorsal < 1) {
                $this->responder(400, ['success' => false, 'message' => 'Faltan campos obligatorios o dorsal inválido']);
            }

            Autenticacion::requerirStaffDeEquipo($id_equipo);

            $ejModel = new EquipoJugadorModel();

            if ($ejModel->tieneDorsal($id_jugador, $id_equipo, $id_liga)) {
                $this->responder(409, [
                    'success' => false,
                    'message' => 'El dorsal ya fue asignado. Solo ADMIN puede corregirlo.'
                ]);
            }

            $filas = $ejModel->actualizarDorsal($id_jugador, $id_equipo, $id_liga, $dorsal);

            if (!$filas) {
                $this->responder(404, ['success' => false, 'message' => 'Relación no encontrada o dorsal ya asignado']);
            }

            $this->responder(200, ['success' => true, 'message' => 'Dorsal asignado correctamente']);
        } catch (Throwable $e) {
            $this->responder(500, ['success' => false, 'message' => $e->getMessage()]);
        }
    }
}
