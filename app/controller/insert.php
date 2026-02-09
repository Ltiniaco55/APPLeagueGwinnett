<?php

require_once __DIR__ . '/../core/database.php';
require_once __DIR__ . '/../model/ligasModel.php';
require_once __DIR__ . '/../model/usuariosModel.php';
require_once __DIR__ . '/../model/equiposModel.php';
require_once __DIR__ . '/../model/jugadoresModel.php';
require_once __DIR__ . '/../model/entrenadoresModel.php';

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
}
elseif ($entidad === 'usuario') {
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
}
elseif ($entidad === 'equipos') {
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
}

elseif ($entidad === 'entrenadores') {
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

echo json_encode(['success' => false, 'message' => 'Entidad no soportada']);
