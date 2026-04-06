<?php

declare(strict_types=1);

/**
 * ============================================================================
 *  JugadoresController
 * ============================================================================
 *  Flujos soportados:
 *   - Listado / búsqueda
 *   - Alta individual (ADMIN directo / STAFF pendiente)
 *   - Alta masiva (lote, todo-o-nada, max 25)
 *   - Aprobación admin (PENDIENTE → ALTA + equipo_jugador)
 *   - Rechazo admin (elimina pendiente)
 *   - Subida de foto individual (una sola vez, inmutable)
 *   - Detalle individual
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
            $esStaff = $usuarioActual && $usuarioActual['rol'] === Autenticacion::ROL_STAFF;

            if ($esStaff) {
                require_once __DIR__ . '/../model/entrenadoresModel.php';
                require_once __DIR__ . '/../model/entrenadorEquipoModel.php';
                $entModel = new EntrenadoresModel();
                $entEqModel = new EntrenadorEquipoModel();
                
                $entrenador = $entModel->getByUserId((int)$usuarioActual['id_usuario']);
                $misIds = [];
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

            $modelo = new JugadoresModel();
            $jugador = $modelo->getById($id);

            if (!$jugador) $this->responder(404, ['success' => false, 'message' => 'Jugador no encontrado']);

            $this->responder(200, ['success' => true, 'data' => $jugador]);
        } catch (Throwable $e) {
            $this->responder(500, ['success' => false, 'message' => $e->getMessage()]);
        }
    }

    // =====================================================
    // STAFF CREA → PENDIENTE
    // =====================================================
    public function insertarStaff(array $entrada): void
    {
        try {
            Autenticacion::requerirRol([Autenticacion::ROL_STAFF, Autenticacion::ROL_ADMIN]);

            $usuario = Autenticacion::usuario();
            $id_usuario_solicitante = (int)$usuario['id_usuario'];

            $nombre   = $this->limpiarTexto($entrada['nombre'] ?? '');
            $apellido = $this->limpiarTexto($entrada['apellido'] ?? '');
            $fecha    = $this->limpiarTexto($entrada['fecha_nacimiento'] ?? '');
            $id_equipo = (int)($entrada['id_equipo'] ?? 0);
            $id_liga   = (int)($entrada['id_liga'] ?? 0);

            if (!$nombre || !$apellido || !$fecha || !$id_equipo || !$id_liga) {
                $this->responder(400, ['success' => false, 'message' => 'Faltan campos obligatorios']);
            }

            Autenticacion::requerirStaffDeEquipo($id_equipo);

            $modelo = new JugadoresModel();

            // Comprobar duplicado en mismo equipo
            if ($modelo->existeEnMismoEquipo($nombre, $apellido, $fecha, $id_equipo, $id_liga)) {
                $this->responder(409, ['success' => false, 'message' => 'Jugador ya existe en este equipo']);
            }

            $idNuevo = $modelo->insertPendienteStaff(
                $nombre,
                $apellido,
                $fecha,
                null, // foto_path = NULL en alta
                null,
                $id_equipo,
                $id_usuario_solicitante,
                $id_liga
            );

            // Avisos de coincidencia en otros equipos
            $avisos = $modelo->buscarCoincidenciasEnOtrosEquipos($nombre, $apellido, $fecha, $id_equipo, $id_liga);

            $response = [
                'success' => true,
                'message' => 'Jugador enviado a aprobación',
                'data' => $modelo->getById($idNuevo)
            ];

            if (!empty($avisos)) {
                $response['avisos'] = $avisos;
                $response['aviso_msg'] = 'Este jugador coincide con registros en otros equipos';
            }

            $this->responder(201, $response);
        } catch (Throwable $e) {
            $this->responder(500, ['success' => false, 'message' => $e->getMessage()]);
        }
    }

    // =====================================================
    // ADMIN CREA DIRECTO → ALTA + asigna equipo_jugador
    // =====================================================
    public function insertarAdmin(array $entrada): void
    {
        try {
            Autenticacion::requerirRol([Autenticacion::ROL_ADMIN]);

            $usuario = Autenticacion::usuario();
            $id_admin = (int)$usuario['id_usuario'];

            $nombre   = $this->limpiarTexto($entrada['nombre'] ?? '');
            $apellido = $this->limpiarTexto($entrada['apellido'] ?? '');
            $fecha    = $this->limpiarTexto($entrada['fecha_nacimiento'] ?? '');
            $id_equipo = (int)($entrada['id_equipo'] ?? 0);
            $id_liga   = (int)($entrada['id_liga'] ?? 0);

            if (!$nombre || !$apellido || !$fecha || !$id_equipo || !$id_liga) {
                $this->responder(400, ['success' => false, 'message' => 'Faltan campos obligatorios']);
            }

            $modelo = new JugadoresModel();

            // Comprobar duplicado en mismo equipo
            if ($modelo->existeEnMismoEquipo($nombre, $apellido, $fecha, $id_equipo, $id_liga)) {
                $this->responder(409, ['success' => false, 'message' => 'Jugador ya existe en este equipo']);
            }

            $idJugador = $modelo->insertAltaAdmin(
                $nombre,
                $apellido,
                $fecha,
                null, // foto_path = NULL
                null,
                $id_equipo,
                $id_admin,
                $id_liga
            );

            // Insertar en plantilla
            $plantilla = new EquipoJugadorModel();
            $plantilla->insertarRelacion($idJugador, $id_equipo, $id_liga, null);

            // Avisos de coincidencia
            $avisos = $modelo->buscarCoincidenciasEnOtrosEquipos($nombre, $apellido, $fecha, $id_equipo, $id_liga);

            $response = [
                'success' => true,
                'message' => 'Jugador dado de alta y asignado al equipo',
                'data' => $modelo->getById($idJugador)
            ];

            if (!empty($avisos)) {
                $response['avisos'] = $avisos;
                $response['aviso_msg'] = 'Este jugador coincide con registros en otros equipos';
            }

            $this->responder(201, $response);
        } catch (Throwable $e) {
            $this->responder(500, ['success' => false, 'message' => $e->getMessage()]);
        }
    }

    // =====================================================
    // ALTA MASIVA (POST /jugadores/lote)
    // Todo o nada — max 25 jugadores
    // =====================================================
    public function insertarLote(array $entrada): void
    {
        try {
            Autenticacion::requerirRol([Autenticacion::ROL_STAFF, Autenticacion::ROL_ADMIN]);

            $usuario = Autenticacion::usuario();
            $id_usuario_auth = (int)$usuario['id_usuario'];
            $esAdmin = Autenticacion::tieneRol([Autenticacion::ROL_ADMIN]);

            $id_equipo = (int)($entrada['id_equipo'] ?? 0);
            $id_liga   = (int)($entrada['id_liga'] ?? 0);
            $jugadores = $entrada['jugadores'] ?? [];

            if (!$id_equipo || !$id_liga) {
                $this->responder(400, ['success' => false, 'message' => 'Faltan id_equipo e id_liga globales']);
            }

            if (!is_array($jugadores) || empty($jugadores)) {
                $this->responder(400, ['success' => false, 'message' => 'No hay jugadores para insertar']);
            }

            if (count($jugadores) > 25) {
                $this->responder(400, ['success' => false, 'message' => 'Máximo 25 jugadores por lote']);
            }

            // Si es STAFF, verificar pertenencia al equipo
            if (!$esAdmin) {
                Autenticacion::requerirStaffDeEquipo($id_equipo);
            }

            $modelo = new JugadoresModel();
            $plantilla = new EquipoJugadorModel();
            $db = $modelo->getDb();

            // Validación previa de TODOS los jugadores antes de insertar
            $avisosTotales = [];
            foreach ($jugadores as $i => $jug) {
                $nombre   = $this->limpiarTexto($jug['nombre'] ?? '');
                $apellido = $this->limpiarTexto($jug['apellido'] ?? '');
                $fecha    = $this->limpiarTexto($jug['fecha_nacimiento'] ?? '');

                if (!$nombre || !$apellido || !$fecha) {
                    $this->responder(400, [
                        'success' => false,
                        'message' => "Fila " . ($i + 1) . ": Te faltan campos por rellenar"
                    ]);
                }

                // Validar formato fecha
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
                    $this->responder(400, [
                        'success' => false,
                        'message' => "Fila " . ($i + 1) . ": Formato de fecha inválido (usar YYYY-MM-DD)"
                    ]);
                }

                // Duplicado en mismo equipo = bloqueo
                if ($modelo->existeEnMismoEquipo($nombre, $apellido, $fecha, $id_equipo, $id_liga)) {
                    $this->responder(409, [
                        'success' => false,
                        'message' => "Fila " . ($i + 1) . ": $nombre $apellido ya existe en este equipo"
                    ]);
                }

                // Coincidencias en otros = aviso
                $coincidencias = $modelo->buscarCoincidenciasEnOtrosEquipos($nombre, $apellido, $fecha, $id_equipo, $id_liga);
                if (!empty($coincidencias)) {
                    $avisosTotales[] = [
                        'fila' => $i + 1,
                        'jugador' => "$nombre $apellido",
                        'coincidencias' => $coincidencias
                    ];
                }
            }

            // ─── Todo o nada: transacción ───
            $db->beginTransaction();
            try {
                $insertados = [];

                foreach ($jugadores as $jug) {
                    $nombre   = $this->limpiarTexto($jug['nombre']);
                    $apellido = $this->limpiarTexto($jug['apellido']);
                    $fecha    = $this->limpiarTexto($jug['fecha_nacimiento']);

                    if ($esAdmin) {
                        // ADMIN → ALTA directa
                        $idNuevo = $modelo->insertAltaAdmin(
                            $nombre, $apellido, $fecha,
                            null, null, $id_equipo, $id_usuario_auth, $id_liga
                        );
                        // Insertar en equipo_jugador
                        $plantilla->insertarRelacion($idNuevo, $id_equipo, $id_liga, null);
                    } else {
                        // STAFF → PENDIENTE
                        $idNuevo = $modelo->insertPendienteStaff(
                            $nombre, $apellido, $fecha,
                            null, null, $id_equipo, $id_usuario_auth, $id_liga
                        );
                        // No insertar en equipo_jugador hasta que admin apruebe
                    }

                    $insertados[] = $modelo->getById($idNuevo);
                }

                $db->commit();

                $response = [
                    'success' => true,
                    'message' => $esAdmin
                        ? count($insertados) . ' jugador(es) dados de alta correctamente'
                        : count($insertados) . ' jugador(es) enviados a aprobación',
                    'data' => $insertados
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
    // ADMIN APRUEBA → ALTA + inserta en plantilla
    // Usa id_liga_solicitante del jugador pendiente
    // =====================================================
    public function adminAprueba(int $id): void
    {
        try {
            Autenticacion::requerirRol([Autenticacion::ROL_ADMIN]);

            $modelo = new JugadoresModel();
            $jugador = $modelo->getById($id);

            if (!$jugador) $this->responder(404, ['success' => false, 'message' => 'Jugador no encontrado']);

            $estado = $jugador['estado'] ?? '';

            if ($estado === 'PENDIENTE') {
                if (!$jugador['id_equipo_solicitante']) {
                    $this->responder(400, ['success' => false, 'message' => 'Jugador sin equipo solicitante']);
                }

                $id_liga = (int)($jugador['id_liga_solicitante'] ?? 0);
                if (!$id_liga) {
                    $this->responder(400, ['success' => false, 'message' => 'Jugador sin liga solicitante']);
                }

                $modelo->marcarAlta($id);

                $plantilla = new EquipoJugadorModel();
                // Ojo: Verificar si no existe ya para evitar errores
                if (!$plantilla->existeRelacion($id, (int)$jugador['id_equipo_solicitante'], $id_liga)) {
                    $plantilla->insertarRelacion($id, (int)$jugador['id_equipo_solicitante'], $id_liga, null);
                }

                $this->responder(200, ['success' => true, 'message' => 'Alta aprobada y jugador añadido a plantilla']);
                
            } elseif ($estado === 'PENDIENTE_BAJA') {
                // Admin aprueba la baja: se elimina la relación de la plantilla y el jugador
                // Dependiendo del modelo, a veces se borra de la DB por completo si no está en múltiples equipos
                $plantilla = new EquipoJugadorModel();
                $id_equipo_sol = (int)($jugador['id_equipo_solicitante'] ?? 0);
                $id_liga_sol = (int)($jugador['id_liga_solicitante'] ?? 0);
                if ($id_equipo_sol && $id_liga_sol) {
                    $plantilla->eliminarRelacion($id, $id_equipo_sol, $id_liga_sol);
                }
                
                // Si el jugador ya no tiene equipos, se elimina de la base de datos central?
                // Según modelo general, el borrar lo quita
                $modelo->delete($id);

                $this->responder(200, ['success' => true, 'message' => 'Baja aprobada, el jugador ha sido removido del equipo/sistema.']);
            } else {
                $this->responder(400, ['success' => false, 'message' => 'El jugador no está en un estado PENDIENTE válido']);
            }

        } catch (Throwable $e) {
            $this->responder(500, ['success' => false, 'message' => $e->getMessage()]);
        }
    }

    // =====================================================
    // ADMIN RECHAZA → elimina pendiente o anula solicitud baja
    // =====================================================
    public function adminRechaza(int $id): void
    {
        try {
            Autenticacion::requerirRol([Autenticacion::ROL_ADMIN]);

            $modelo = new JugadoresModel();
            $jugador = $modelo->getById($id);

            if (!$jugador) {
                $this->responder(404, ['success' => false, 'message' => 'Jugador no encontrado']);
            }

            $estado = $jugador['estado'] ?? '';

            if ($estado === 'PENDIENTE') {
                // Se rechaza su alta: borrar
                $modelo->deletePendiente($id);
                $this->responder(200, ['success' => true, 'message' => 'Alta rechazada y registro eliminado']);
            } elseif ($estado === 'PENDIENTE_BAJA') {
                // Se rechaza su baja: restaurar a ALTA
                $modelo->marcarAlta($id);
                $this->responder(200, ['success' => true, 'message' => 'Baja rechazada, el jugador vuelve a estado ALTA']);
            } else {
                $this->responder(400, ['success' => false, 'message' => 'El jugador no está en un estado PENDIENTE válido']);
            }

        } catch (Throwable $e) {
            $this->responder(500, ['success' => false, 'message' => $e->getMessage()]);
        }
    }

    // =====================================================
    // SUBIR FOTO INDIVIDUAL (POST /jugadores/{id}/foto)
    // =====================================================
    public function subirFoto(int $id): void
    {
        try {
            Autenticacion::requerirRol([Autenticacion::ROL_ADMIN, Autenticacion::ROL_STAFF]);

            $modelo = new JugadoresModel();
            $jugador = $modelo->getById($id);

            if (!$jugador) {
                $this->responder(404, ['success' => false, 'message' => 'Jugador no encontrado']);
            }

            // ¿Ya tiene foto?
            if ($modelo->tieneFoto($id)) {
                $this->responder(409, [
                    'success' => false,
                    'message' => 'Este jugador ya tiene foto asignada. No se puede modificar.'
                ]);
            }

            // Comprobar que viene archivo
            if (!isset($_FILES['foto']) || $_FILES['foto']['error'] !== UPLOAD_ERR_OK) {
                $this->responder(400, ['success' => false, 'message' => 'No se recibió ninguna foto válida']);
            }

            $file = $_FILES['foto'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'webp'];

            if (!in_array($ext, $allowed)) {
                $this->responder(400, ['success' => false, 'message' => 'Formato no permitido. Usar: jpg, png, webp']);
            }

            // Crear directorio de destino
            $uploadDir = __DIR__ . '/../../public/uploads/jugadores/' . $id;
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $filename = 'foto.' . $ext;
            $destPath = $uploadDir . '/' . $filename;
            $dbPath = '/public/uploads/jugadores/' . $id . '/' . $filename;

            if (!move_uploaded_file($file['tmp_name'], $destPath)) {
                $this->responder(500, ['success' => false, 'message' => 'Error al guardar el archivo']);
            }

            // Guardar en DB (protegido: solo si no existe)
            $filas = $modelo->guardarFotoSiNoExiste($id, $dbPath);

            if (!$filas) {
                // Race condition: alguien la subió justo antes
                @unlink($destPath);
                $this->responder(409, [
                    'success' => false,
                    'message' => 'La foto fue asignada por otro proceso. No se puede modificar.'
                ]);
            }

            $this->responder(200, [
                'success' => true,
                'message' => 'Foto subida correctamente',
                'foto_path' => $dbPath
            ]);
        } catch (Throwable $e) {
            $this->responder(500, ['success' => false, 'message' => $e->getMessage()]);
        }
    }

    // =====================================================
    // ELIMINAR (DELETE /jugadores/{id})
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
