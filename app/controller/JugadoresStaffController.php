<?php

declare(strict_types=1);

/**
 * ============================================================================
 *  JugadoresStaffController
 * ============================================================================
 *  Rutas STAFF para gestión de jugadores.
 *  La lógica de estado vive en equipo_jugador (NO en jugador).
 *
 *  GET  /staff/jugadores/pendientes
 *  GET  /staff/jugadores/plantilla
 *  POST /staff/jugadores/alta
 *  POST /staff/jugadores/lote
 *  PATCH /staff/jugadores/{id_relacion}/solicitar-baja
 *  POST  /staff/jugadores/{id_jugador}/foto
 *  PATCH /staff/jugadores/dorsal
 * ============================================================================
 */

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

    /**
     * Obtiene los IDs de equipo asignados al staff actual.
     * Devuelve array vacío si no es staff o no tiene equipos.
     */
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

    // =====================================================
    // GET /staff/jugadores/pendientes
    // =====================================================
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

    // =====================================================
    // GET /staff/jugadores/plantilla
    //   ?id_equipo=X&id_liga=Y&nombre=Z&categoria=C
    // =====================================================
    public function plantilla(array $entrada = []): void
    {
        try {
            Autenticacion::requerirRol([Autenticacion::ROL_STAFF, Autenticacion::ROL_ADMIN]);

            $misIds       = $this->getMisEquipoIds();
            $nombreFiltro = $this->limpiar($entrada['nombre'] ?? '');
            $catFiltro    = $this->limpiar($entrada['categoria'] ?? '');

            // Si viene id_equipo concreto, verificar que pertenece al staff
            $id_equipo = (int)($entrada['id_equipo'] ?? 0);
            $id_liga   = (int)($entrada['id_liga'] ?? 0);

            $modelo = new EquipoJugadorModel();

            if ($id_equipo && $id_liga) {
                // Verificar que el equipo es del staff
                if (!in_array($id_equipo, $misIds, true)) {
                    $this->responder(403, ['success' => false, 'message' => 'No autorizado para este equipo']);
                }
                $datos = $modelo->getPlantillaConJugadores($id_equipo, $id_liga, $nombreFiltro);
            } else {
                // Devolver plantilla de todos sus equipos
                $datos = $modelo->getPlantillaStaff($misIds, $nombreFiltro, $catFiltro);
            }

            $this->responder(200, ['success' => true, 'data' => $datos]);
        } catch (Throwable $e) {
            $this->responder(500, ['success' => false, 'message' => $e->getMessage()]);
        }
    }

    // =====================================================
    // POST /staff/jugadores/alta  (alta individual)
    // =====================================================
    public function alta(array $entrada): void
    {
        try {
            Autenticacion::requerirRol([Autenticacion::ROL_STAFF, Autenticacion::ROL_ADMIN]);

            $usuario = Autenticacion::usuario();
            $id_usuario = (int)$usuario['id_usuario'];

            $nombre    = $this->limpiar($entrada['nombre'] ?? '');
            $apellido  = $this->limpiar($entrada['apellido'] ?? '');
            $fecha     = $this->limpiar($entrada['fecha_nacimiento'] ?? '');
            $id_equipo = (int)($entrada['id_equipo'] ?? 0);
            $id_liga   = (int)($entrada['id_liga'] ?? 0);

            if (!$nombre || !$apellido || !$fecha || !$id_equipo || !$id_liga) {
                $this->responder(400, ['success' => false, 'message' => 'Faltan campos obligatorios']);
            }

            // Verificar pertenencia al equipo
            Autenticacion::requerirStaffDeEquipo($id_equipo);

            $jugModel = new JugadoresModel();
            $ejModel  = new EquipoJugadorModel();

            // Buscar si ya existe como persona global
            $jugadorExistente = $jugModel->getByKey($nombre, $apellido, $fecha);

            if ($jugadorExistente) {
                $id_jugador = (int)$jugadorExistente['id_jugador'];

                // Verificar que no existe ya la relación (activa o pendiente)
                if ($ejModel->existeRelacion($id_jugador, $id_equipo, $id_liga)) {
                    $this->responder(409, [
                        'success' => false,
                        'message' => 'Este jugador ya tiene una solicitud activa o está dado de alta en este equipo/liga'
                    ]);
                }
            } else {
                // Crear persona global
                $id_jugador = $jugModel->insert($nombre, $apellido, $fecha, null, null);
            }

            // Crear relación pendiente en equipo_jugador
            $ejModel->insertarPendiente($id_jugador, $id_equipo, $id_liga, $id_usuario);

            // Avisos coincidencias en otros equipos
            $avisos = $jugModel->buscarCoincidenciasEnOtrosEquipos($nombre, $apellido, $fecha, $id_equipo, $id_liga);

            $response = [
                'success' => true,
                'message' => 'Solicitud de alta enviada correctamente',
                'data'    => ['id_jugador' => $id_jugador]
            ];

            if (!empty($avisos)) {
                $response['avisos']    = $avisos;
                $response['aviso_msg'] = 'Este jugador ya existe en otros equipos. La solicitud igualmente fue enviada a revisión.';
            }

            $this->responder(201, $response);
        } catch (Throwable $e) {
            $this->responder(500, ['success' => false, 'message' => $e->getMessage()]);
        }
    }

    // =====================================================
    // POST /staff/jugadores/lote  (alta masiva, todo-o-nada)
    // =====================================================
    public function lote(array $entrada): void
    {
        try {
            Autenticacion::requerirRol([Autenticacion::ROL_STAFF, Autenticacion::ROL_ADMIN]);

            $usuario    = Autenticacion::usuario();
            $id_usuario = (int)$usuario['id_usuario'];

            $id_equipo = (int)($entrada['id_equipo'] ?? 0);
            $id_liga   = (int)($entrada['id_liga'] ?? 0);
            $jugadores = $entrada['jugadores'] ?? [];

            if (!$id_equipo || !$id_liga) {
                $this->responder(400, ['success' => false, 'message' => 'Faltan id_equipo e id_liga']);
            }
            if (!is_array($jugadores) || empty($jugadores)) {
                $this->responder(400, ['success' => false, 'message' => 'No hay jugadores para insertar']);
            }
            if (count($jugadores) > 25) {
                $this->responder(400, ['success' => false, 'message' => 'Máximo 25 jugadores por lote']);
            }

            Autenticacion::requerirStaffDeEquipo($id_equipo);

            $jugModel = new JugadoresModel();
            $ejModel  = new EquipoJugadorModel();
            $db       = $jugModel->getDb();

            // Validación previa — todo o nada
            $avisosTotales = [];
            foreach ($jugadores as $i => $jug) {
                $nombre   = $this->limpiar($jug['nombre'] ?? '');
                $apellido = $this->limpiar($jug['apellido'] ?? '');
                $fecha    = $this->limpiar($jug['fecha_nacimiento'] ?? '');

                if (!$nombre || !$apellido || !$fecha) {
                    $this->responder(400, [
                        'success' => false,
                        'message' => "Fila " . ($i + 1) . ": faltan campos obligatorios"
                    ]);
                }

                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
                    $this->responder(400, [
                        'success' => false,
                        'message' => "Fila " . ($i + 1) . ": formato de fecha inválido (usar YYYY-MM-DD)"
                    ]);
                }

                // Verificar si el jugador ya existe como persona global
                $jugExistente = $jugModel->getByKey($nombre, $apellido, $fecha);
                if ($jugExistente) {
                    $id_j = (int)$jugExistente['id_jugador'];
                    if ($ejModel->existeRelacion($id_j, $id_equipo, $id_liga)) {
                        $this->responder(409, [
                            'success' => false,
                            'message' => "Fila " . ($i + 1) . ": $nombre $apellido ya tiene solicitud activa en este equipo/liga"
                        ]);
                    }
                }

                // Avisos de coincidencias en otros equipos (no bloquea)
                $coinc = $jugModel->buscarCoincidenciasEnOtrosEquipos($nombre, $apellido, $fecha, $id_equipo, $id_liga);
                if (!empty($coinc)) {
                    $avisosTotales[] = ['fila' => $i + 1, 'jugador' => "$nombre $apellido", 'coincidencias' => $coinc];
                }
            }

            // Transacción todo-o-nada
            $db->beginTransaction();
            try {
                $insertados = [];
                foreach ($jugadores as $jug) {
                    $nombre   = $this->limpiar($jug['nombre']);
                    $apellido = $this->limpiar($jug['apellido']);
                    $fecha    = $this->limpiar($jug['fecha_nacimiento']);

                    $jugExistente = $jugModel->getByKey($nombre, $apellido, $fecha);
                    if ($jugExistente) {
                        $id_jugador = (int)$jugExistente['id_jugador'];
                    } else {
                        $id_jugador = $jugModel->insert($nombre, $apellido, $fecha, null, null);
                    }

                    $ejModel->insertarPendiente($id_jugador, $id_equipo, $id_liga, $id_usuario);
                    $insertados[] = ['id_jugador' => $id_jugador, 'nombre' => $nombre, 'apellido' => $apellido];
                }

                $db->commit();

                $response = [
                    'success' => true,
                    'message' => count($insertados) . ' jugador(es) enviados a aprobación',
                    'data'    => $insertados
                ];
                if (!empty($avisosTotales)) {
                    $response['avisos'] = $avisosTotales;
                }

                $this->responder(201, $response);
            } catch (Throwable $txErr) {
                $db->rollBack();
                throw $txErr;
            }
        } catch (Throwable $e) {
            $this->responder(500, ['success' => false, 'message' => $e->getMessage()]);
        }
    }

    // =====================================================
    // PATCH /staff/jugadores/{id_relacion}/solicitar-baja
    // =====================================================
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

            // Verificar que el staff puede gestionar este equipo
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

            $this->responder(200, ['success' => true, 'message' => 'Baja solicitada. Pendiente de aprobación por el administrador.']);
        } catch (Throwable $e) {
            $this->responder(500, ['success' => false, 'message' => $e->getMessage()]);
        }
    }

    // =====================================================
    // POST /staff/jugadores/{id_jugador}/foto
    // Solo si no existe foto
    // =====================================================
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

    // =====================================================
    // PATCH /staff/jugadores/dorsal
    // Body: {id_jugador, id_equipo, id_liga, dorsal}
    // Solo si no existe dorsal
    // =====================================================
    public function asignarDorsal(array $entrada): void
    {
        try {
            Autenticacion::requerirRol([Autenticacion::ROL_STAFF, Autenticacion::ROL_ADMIN]);

            $id_jugador = (int)($entrada['id_jugador'] ?? 0);
            $id_equipo  = (int)($entrada['id_equipo'] ?? 0);
            $id_liga    = (int)($entrada['id_liga'] ?? 0);
            $dorsal     = (int)($entrada['dorsal'] ?? 0);

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
