<?php

require_once __DIR__ . '/../core/database.php';
require_once __DIR__ . '/../model/ligasModel.php';
require_once __DIR__ . '/../model/usuariosModel.php';
require_once __DIR__ . '/../model/equiposModel.php';
require_once __DIR__ . '/../model/jugadoresModel.php';
require_once __DIR__ . '/../model/entrenadoresModel.php';
require_once __DIR__ . '/../model/entrenadorEquipoModel.php';
require_once __DIR__ . '/../model/equipoLigaModel.php';
require_once __DIR__ . '/../model/equipoJugadorModel.php';
require_once __DIR__ . '/../model/clasificacionesModel.php';
require_once __DIR__ . '/../model/partidosModel.php';

header('Content-Type: application/json; charset=utf-8');

// Soportar POST JSON, form-data y GET (legacy)
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$input = $_GET;
if ($method === 'POST' || $method === 'PUT' || $method === 'PATCH') {
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    $raw = file_get_contents('php://input');
    if (stripos($contentType, 'application/json') !== false) {
        $json = json_decode($raw, true);
        if (is_array($json)) {
            $input = array_merge($input, $json);
        }
    } else {
        $input = array_merge($input, $_POST);
    }
}

$entidad = $input['entidad'] ?? '';

// ── Usuario ──
if ($entidad === 'usuario') {
    $id              = isset($input['id']) && $input['id'] !== '' ? (int)$input['id'] : 0;
    $nombre          = trim($input['nombre'] ?? '');
    $apellido        = trim($input['apellido'] ?? '');
    $fecha_nacimiento = trim($input['fecha_nacimiento'] ?? '');
    $sexo            = trim($input['sexo'] ?? '');
    $email           = trim($input['email'] ?? '');
    $telefono        = isset($input['telefono']) ? trim($input['telefono']) : null;
    $pwd             = $input['pwd'] ?? '';

    try {
        if (!$id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Falta el campo obligatorio: id']);
            exit;
        }

        $model = new UsuariosModel();
        $existing = $model->getById($id);
        if (!$existing) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'No existe un usuario con ese ID']);
            exit;
        }

        // Actualizar datos generales
        if ($nombre && $apellido && $fecha_nacimiento && $sexo && $email) {
            $model->update($id, $nombre, $apellido, $fecha_nacimiento, $sexo, $email, $telefono);
        }

        // Actualizar contraseña si se proporciona
        if ($pwd !== '') {
            $model->updatePassword($id, $pwd);
        }

        $updated = $model->getById($id);
        if ($updated && isset($updated['pwd'])) {
            unset($updated['pwd']);
        }
        echo json_encode(['success' => true, 'message' => 'Usuario actualizado exitosamente', 'data' => $updated]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }

    exit;
} elseif ($entidad === 'equipos') {
    // ── Equipos ──
    $id          = isset($input['id']) && $input['id'] !== '' ? (int)$input['id'] : 0;
    $club        = trim($input['club'] ?? '');
    $categoria   = trim($input['categoria'] ?? '');
    $temporada   = trim($input['temporada'] ?? '');
    $descripcion = isset($input['descripcion']) ? trim($input['descripcion']) : null;

    try {
        if (!$id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Falta el campo obligatorio: id']);
            exit;
        }

        $model = new EquiposModel();
        $existing = $model->getById($id);
        if (!$existing) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'No existe un equipo con ese ID']);
            exit;
        }

        if (!$club || !$categoria || !$temporada) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Faltan campos obligatorios: club, categoria, temporada']);
            exit;
        }

        $model->update($id, $club, $categoria, $temporada, $descripcion);
        $updated = $model->getById($id);
        echo json_encode(['success' => true, 'message' => 'Equipo actualizado exitosamente', 'data' => $updated]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }

    exit;
} elseif ($entidad === 'jugadores') {
    // ── Jugadores ──
    $id               = isset($input['id']) && $input['id'] !== '' ? (int)$input['id'] : 0;
    $nombre           = trim($input['nombre'] ?? '');
    $apellido         = trim($input['apellido'] ?? '');
    $fecha_nacimiento = trim($input['fecha_nacimiento'] ?? '');
    $foto_path        = isset($input['foto_path']) ? trim($input['foto_path']) : null;
    $id_usuario       = isset($input['id_usuario']) && $input['id_usuario'] !== '' ? (int)$input['id_usuario'] : null;

    try {
        if (!$id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Falta el campo obligatorio: id']);
            exit;
        }

        $model = new JugadoresModel();
        $existing = $model->getById($id);
        if (!$existing) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'No existe un jugador con ese ID']);
            exit;
        }

        if (!$nombre || !$apellido || !$fecha_nacimiento) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Faltan campos obligatorios: nombre, apellido, fecha_nacimiento']);
            exit;
        }

        $model->update($id, $nombre, $apellido, $fecha_nacimiento, $foto_path, $id_usuario);
        $updated = $model->getById($id);
        echo json_encode(['success' => true, 'message' => 'Jugador actualizado exitosamente', 'data' => $updated]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }

    exit;
} elseif ($entidad === 'entrenadores') {
    // ── Entrenadores ──
    $id = isset($input['id']) && $input['id'] !== '' ? (int)$input['id'] : 0;

    try {
        if (!$id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Falta el campo obligatorio: id']);
            exit;
        }

        $model = new EntrenadoresModel();
        $existing = $model->getById($id);
        if (!$existing) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'No existe un entrenador con ese ID']);
            exit;
        }

        // Construir array de datos a actualizar (solo campos proporcionados)
        $data = [];
        $campos = ['id_usuario', 'nombre', 'apellido', 'fecha_nacimiento', 'telefono', 'email'];
        foreach ($campos as $campo) {
            if (array_key_exists($campo, $input)) {
                $data[$campo] = $input[$campo] !== '' ? trim($input[$campo]) : null;
            }
        }

        if (empty($data)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'No se proporcionaron campos para actualizar']);
            exit;
        }

        $model->update($id, $data);
        $updated = $model->getById($id);
        echo json_encode(['success' => true, 'message' => 'Entrenador actualizado exitosamente', 'data' => $updated]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }

    exit;
} elseif ($entidad === 'plantilla') {
    // ── Plantilla (equipo_jugador): actualizar dorsal y estado ──
    $id_jugador = isset($input['id_jugador']) && $input['id_jugador'] !== '' ? (int)$input['id_jugador'] : 0;
    $id_equipo  = isset($input['id_equipo']) && $input['id_equipo'] !== '' ? (int)$input['id_equipo'] : 0;
    $id_liga    = isset($input['id_liga']) && $input['id_liga'] !== '' ? (int)$input['id_liga'] : 0;
    $dorsal     = isset($input['dorsal']) && $input['dorsal'] !== '' ? (int)$input['dorsal'] : null;
    $estado     = trim($input['estado'] ?? 'activo');

    try {
        if (!$id_jugador || !$id_equipo || !$id_liga) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Faltan campos obligatorios: id_jugador, id_equipo, id_liga']);
            exit;
        }

        $model = new EquipoJugadorModel();
        $existing = $model->getById($id_jugador, $id_equipo, $id_liga);
        if (!$existing) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'No existe esa asignación de jugador']);
            exit;
        }

        $model->update($id_jugador, $id_equipo, $id_liga, $dorsal, $estado);
        $updated = $model->getById($id_jugador, $id_equipo, $id_liga);
        echo json_encode(['success' => true, 'message' => 'Plantilla actualizada exitosamente', 'data' => $updated]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }

    exit;
} elseif ($entidad === 'staff') {
    // ── Staff (entrenador_equipo): actualizar estado ──
    $id_entrenador = isset($input['id_entrenador']) && $input['id_entrenador'] !== '' ? (int)$input['id_entrenador'] : 0;
    $id_equipo     = isset($input['id_equipo']) && $input['id_equipo'] !== '' ? (int)$input['id_equipo'] : 0;
    $id_liga       = isset($input['id_liga']) && $input['id_liga'] !== '' ? (int)$input['id_liga'] : 0;
    $estado        = trim($input['estado'] ?? '');

    try {
        if (!$id_entrenador || !$id_equipo || !$id_liga) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Faltan campos obligatorios: id_entrenador, id_equipo, id_liga']);
            exit;
        }

        if ($estado === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Falta el campo obligatorio: estado']);
            exit;
        }

        $model = new EntrenadorEquipoModel();
        $existing = $model->getById($id_entrenador, $id_equipo, $id_liga);
        if (!$existing) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'No existe esa asignación de entrenador']);
            exit;
        }

        $model->update($id_entrenador, $id_equipo, $id_liga, $estado);
        $updated = $model->getById($id_entrenador, $id_equipo, $id_liga);
        echo json_encode(['success' => true, 'message' => 'Staff actualizado exitosamente', 'data' => $updated]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }

    exit;
} elseif ($entidad === 'clasificaciones') {
    // ── Clasificaciones: actualizar estadísticas ──
    $id  = isset($input['id']) && $input['id'] !== '' ? (int)$input['id'] : 0;
    $PJ  = isset($input['PJ']) ? (int)$input['PJ'] : 0;
    $PG  = isset($input['PG']) ? (int)$input['PG'] : 0;
    $PE  = isset($input['PE']) ? (int)$input['PE'] : 0;
    $PP  = isset($input['PP']) ? (int)$input['PP'] : 0;
    $GF  = isset($input['GF']) ? (int)$input['GF'] : 0;
    $GC  = isset($input['GC']) ? (int)$input['GC'] : 0;
    $PTS = isset($input['PTS']) ? (int)$input['PTS'] : 0;

    try {
        if (!$id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Falta el campo obligatorio: id (id_clasificacion)']);
            exit;
        }

        $model = new ClasificacionesModel();
        $existing = $model->getById($id);
        if (!$existing) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'No existe esa clasificación']);
            exit;
        }

        $model->update($id, $PJ, $PG, $PE, $PP, $GF, $GC, $PTS);
        $updated = $model->getById($id);
        echo json_encode(['success' => true, 'message' => 'Clasificación actualizada exitosamente', 'data' => $updated]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }

    exit;
} elseif ($entidad === 'partidos') {
    // ── Partidos: actualizar datos completos o solo resultado ──
    $id = isset($input['id']) && $input['id'] !== '' ? (int)$input['id'] : 0;

    try {
        if (!$id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Falta el campo obligatorio: id (id_partido)']);
            exit;
        }

        $model = new PartidosModel();
        $existing = $model->getById($id);
        if (!$existing) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'No existe un partido con ese ID']);
            exit;
        }

        // Si solo se envían goles y estado → actualizar resultado rápido
        $soloResultado = isset($input['solo_resultado']) && $input['solo_resultado'];

        if ($soloResultado) {
            $goles_equipo1 = isset($input['goles_equipo1']) ? (int)$input['goles_equipo1'] : 0;
            $goles_equipo2 = isset($input['goles_equipo2']) ? (int)$input['goles_equipo2'] : 0;
            $estado        = trim($input['estado'] ?? 'finalizado');

            $model->updateResultado($id, $goles_equipo1, $goles_equipo2, $estado);
        } else {
            // Actualización completa del partido
            $id_liga        = isset($input['id_liga']) && $input['id_liga'] !== '' ? (int)$input['id_liga'] : 0;
            $fecha          = trim($input['fecha'] ?? '');
            $lugar          = trim($input['lugar'] ?? '');
            $arbitro        = isset($input['arbitro']) ? trim($input['arbitro']) : null;
            $id_equipo1     = isset($input['id_equipo1']) && $input['id_equipo1'] !== '' ? (int)$input['id_equipo1'] : 0;
            $id_equipo2     = isset($input['id_equipo2']) && $input['id_equipo2'] !== '' ? (int)$input['id_equipo2'] : 0;
            $goles_equipo1  = isset($input['goles_equipo1']) && $input['goles_equipo1'] !== '' ? (int)$input['goles_equipo1'] : null;
            $goles_equipo2  = isset($input['goles_equipo2']) && $input['goles_equipo2'] !== '' ? (int)$input['goles_equipo2'] : null;
            $estado         = trim($input['estado'] ?? 'pendiente');

            if (!$id_liga || !$fecha || !$lugar || !$id_equipo1 || !$id_equipo2) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Faltan campos obligatorios: id_liga, fecha, lugar, id_equipo1, id_equipo2']);
                exit;
            }

            if ($id_equipo1 === $id_equipo2) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Un equipo no puede jugar contra sí mismo']);
                exit;
            }

            $model->update($id, $id_liga, $fecha, $lugar, $arbitro, $id_equipo1, $id_equipo2, $goles_equipo1, $goles_equipo2, $estado);
        }

        $updated = $model->getById($id);
        echo json_encode(['success' => true, 'message' => 'Partido actualizado exitosamente', 'data' => $updated]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }

    exit;
}

echo json_encode(['success' => false, 'message' => 'Entidad no soportada. Usa: usuario | equipos | jugadores | entrenadores | plantilla | staff | clasificaciones | partidos']);
