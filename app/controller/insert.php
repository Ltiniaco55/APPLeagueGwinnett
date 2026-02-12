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

// Support GET (legacy) and POST JSON or form data. Merge into $input array.
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$input = $_GET;
if ($method === 'POST') {
    // parse JSON body when sent
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    $raw = file_get_contents('php://input');
    if (stripos($contentType, 'application/json') !== false) {
        $json = json_decode($raw, true);
        if (is_array($json)) {
            $input = array_merge($input, $json);
        }
    } else {
        // fallback to regular POST form-encoded
        $input = array_merge($input, $_POST);
    }
}

$entidad = $input['entidad'] ?? '';

if ($entidad === 'ligas') {
    $nombre_liga = trim($input['nombre_liga'] ?? '');
    $temporada   = trim($input['temporada'] ?? '');
    $categoria   = trim($input['categoria'] ?? '');
    $descripcion = trim($input['descripcion'] ?? '');

    try {
        $model = new LigasModel();
        if ($model->existsByKey($nombre_liga, $temporada, $categoria)) {
            echo json_encode(['success' => false, 'message' => 'La liga ya existe']);
            exit;
        }
        $id = $model->insert($nombre_liga, $temporada, $categoria, $descripcion);
        echo json_encode(['success' => true, 'id' => $id]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }

    exit;
} elseif ($entidad === 'usuario') {
    $nombre = trim($input['nombre'] ?? '');
    $apellido = trim($input['apellido'] ?? '');
    $fecha_nacimiento = trim($input['fecha_nacimiento'] ?? '');
    $sexo = trim($input['sexo'] ?? '');
    $email = trim($input['email'] ?? '');
    $pwd = $input['pwd'] ?? '';
    $telefono = isset($input['telefono']) ? trim($input['telefono']) : null;

    try {
        // Validar campos obligatorios
        if (!$nombre || !$apellido || !$fecha_nacimiento || !$sexo || !$email || !$pwd) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Faltan campos obligatorios']);
            exit;
        }

        // Validar formato email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Email inválido']);
            exit;
        }

        $model = new UsuariosModel();

        // Verificar si el email ya existe
        if ($model->emailExists($email)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'El email ya está registrado']);
            exit;
        }

        $id = $model->insert($nombre, $apellido, $fecha_nacimiento, $sexo, $email, $pwd, $telefono);
        // Obtener el registro insertado y no devolver la contraseña
        $inserted = $model->getById($id);
        if ($inserted && isset($inserted['pwd'])) {
            unset($inserted['pwd']);
        }
        echo json_encode(['success' => true, 'id' => $id, 'message' => 'Usuario creado exitosamente', 'data' => $inserted]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }

    exit;
} elseif ($entidad === 'equipos') {
    $club = trim($input['club'] ?? '');
    $categoria = trim($input['categoria'] ?? '');
    $temporada = trim($input['temporada'] ?? '');
    $descripcion = isset($input['descripcion']) ? trim($input['descripcion']) : null;

    try {
        // Validar campos obligatorios
        if (!$club || !$categoria || !$temporada) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Faltan campos obligatorios: club, categoria, temporada']);
            exit;
        }

        $model = new EquiposModel();

        // Verificar si el equipo ya existe
        if ($model->existsByKey($club, $categoria, $temporada)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'El equipo ya existe con esa combinación de club, categoria y temporada']);
            exit;
        }

        $id = $model->insert($club, $categoria, $temporada, $descripcion);
        // Obtener el registro insertado
        $inserted = $model->getById($id);
        echo json_encode(['success' => true, 'id' => $id, 'message' => 'Equipo creado exitosamente', 'data' => $inserted]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }

    exit;
} elseif ($entidad === 'entrenadores') {
    $id_usuario = isset($input['id_usuario']) && $input['id_usuario'] !== '' ? (int)$input['id_usuario'] : null;
    $nombre = trim($input['nombre'] ?? '');
    $apellido = trim($input['apellido'] ?? '');
    $fecha_nacimiento = trim($input['fecha_nacimiento'] ?? null);
    $telefono = isset($input['telefono']) ? trim($input['telefono']) : null;
    $email = isset($input['email']) ? trim($input['email']) : null;

    try {
        if (!$nombre || !$apellido) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Faltan campos obligatorios: nombre o apellido']);
            exit;
        }

        $model = new EntrenadoresModel();

        if ($email && $model->existsByEmail($email)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'El email del entrenador ya está registrado']);
            exit;
        }

        $id = $model->insert($id_usuario, $nombre, $apellido, $fecha_nacimiento, $telefono, $email);
        $inserted = $model->getById($id);

        echo json_encode(['success' => true, 'id' => $id, 'message' => 'Entrenador creado exitosamente', 'data' => $inserted]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }

    exit;
}

// ── Plantilla (equipo_jugador) ──
elseif ($entidad === 'plantilla') {
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

        if ($model->existsByKey($id_jugador, $id_equipo, $id_liga)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'El jugador ya está asignado a ese equipo en esa liga']);
            exit;
        }

        $model->insert($id_jugador, $id_equipo, $id_liga, $dorsal, $estado);
        $inserted = $model->getById($id_jugador, $id_equipo, $id_liga);
        echo json_encode(['success' => true, 'message' => 'Jugador asignado al equipo exitosamente', 'data' => $inserted]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }

    exit;
}

// ── Staff (entrenador_equipo) ──
elseif ($entidad === 'staff') {
    $id_entrenador = isset($input['id_entrenador']) && $input['id_entrenador'] !== '' ? (int)$input['id_entrenador'] : 0;
    $id_equipo     = isset($input['id_equipo']) && $input['id_equipo'] !== '' ? (int)$input['id_equipo'] : 0;
    $id_liga       = isset($input['id_liga']) && $input['id_liga'] !== '' ? (int)$input['id_liga'] : 0;
    $estado        = trim($input['estado'] ?? 'activo');

    try {
        if (!$id_entrenador || !$id_equipo || !$id_liga) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Faltan campos obligatorios: id_entrenador, id_equipo, id_liga']);
            exit;
        }

        $model = new EntrenadorEquipoModel();

        if ($model->existsByKey($id_entrenador, $id_equipo, $id_liga)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'El entrenador ya está asignado a ese equipo en esa liga']);
            exit;
        }

        $model->insert($id_entrenador, $id_equipo, $id_liga, $estado);
        $inserted = $model->getById($id_entrenador, $id_equipo, $id_liga);
        echo json_encode(['success' => true, 'message' => 'Entrenador asignado al equipo exitosamente', 'data' => $inserted]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }

    exit;
}

// ── Equipo-Liga ──
elseif ($entidad === 'equipo_liga') {
    $id_equipo = isset($input['id_equipo']) && $input['id_equipo'] !== '' ? (int)$input['id_equipo'] : 0;
    $id_liga   = isset($input['id_liga']) && $input['id_liga'] !== '' ? (int)$input['id_liga'] : 0;

    try {
        if (!$id_equipo || !$id_liga) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Faltan campos obligatorios: id_equipo, id_liga']);
            exit;
        }

        $model = new EquipoLigaModel();

        if ($model->existsByKey($id_equipo, $id_liga)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'El equipo ya está asignado a esa liga']);
            exit;
        }

        $model->insert($id_equipo, $id_liga);
        $inserted = $model->getById($id_equipo, $id_liga);
        echo json_encode(['success' => true, 'message' => 'Equipo asignado a la liga exitosamente', 'data' => $inserted]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }

    exit;
}

// ── Clasificaciones ──
elseif ($entidad === 'clasificaciones') {
    $id_liga   = isset($input['id_liga']) && $input['id_liga'] !== '' ? (int)$input['id_liga'] : 0;
    $id_equipo = isset($input['id_equipo']) && $input['id_equipo'] !== '' ? (int)$input['id_equipo'] : 0;
    $PJ  = isset($input['PJ']) ? (int)$input['PJ'] : 0;
    $PG  = isset($input['PG']) ? (int)$input['PG'] : 0;
    $PE  = isset($input['PE']) ? (int)$input['PE'] : 0;
    $PP  = isset($input['PP']) ? (int)$input['PP'] : 0;
    $GF  = isset($input['GF']) ? (int)$input['GF'] : 0;
    $GC  = isset($input['GC']) ? (int)$input['GC'] : 0;
    $PTS = isset($input['PTS']) ? (int)$input['PTS'] : 0;

    try {
        if (!$id_liga || !$id_equipo) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Faltan campos obligatorios: id_liga, id_equipo']);
            exit;
        }

        $model = new ClasificacionesModel();

        if ($model->existsByKey($id_equipo, $id_liga)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Ya existe una clasificación para ese equipo en esa liga']);
            exit;
        }

        $id = $model->insert($id_liga, $id_equipo, $PJ, $PG, $PE, $PP, $GF, $GC, $PTS);
        $inserted = $model->getById($id);
        echo json_encode(['success' => true, 'id' => $id, 'message' => 'Clasificación creada exitosamente', 'data' => $inserted]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }

    exit;
}

// ── Partidos ──
elseif ($entidad === 'partidos') {
    $id_liga        = isset($input['id_liga']) && $input['id_liga'] !== '' ? (int)$input['id_liga'] : 0;
    $fecha          = trim($input['fecha'] ?? '');
    $lugar          = trim($input['lugar'] ?? '');
    $arbitro        = isset($input['arbitro']) ? trim($input['arbitro']) : null;
    $id_equipo1     = isset($input['id_equipo1']) && $input['id_equipo1'] !== '' ? (int)$input['id_equipo1'] : 0;
    $id_equipo2     = isset($input['id_equipo2']) && $input['id_equipo2'] !== '' ? (int)$input['id_equipo2'] : 0;
    $goles_equipo1  = isset($input['goles_equipo1']) && $input['goles_equipo1'] !== '' ? (int)$input['goles_equipo1'] : null;
    $goles_equipo2  = isset($input['goles_equipo2']) && $input['goles_equipo2'] !== '' ? (int)$input['goles_equipo2'] : null;
    $estado         = trim($input['estado'] ?? 'pendiente');

    try {
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

        $model = new PartidosModel();
        $id = $model->insert($id_liga, $fecha, $lugar, $arbitro, $id_equipo1, $id_equipo2, $goles_equipo1, $goles_equipo2, $estado);
        $inserted = $model->getById($id);
        echo json_encode(['success' => true, 'id' => $id, 'message' => 'Partido creado exitosamente', 'data' => $inserted]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }

    exit;
}

echo json_encode(['success' => false, 'message' => 'Entidad no soportada']);
