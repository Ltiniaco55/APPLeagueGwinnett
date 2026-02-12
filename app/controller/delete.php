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

$entidad = $_GET['entidad'] ?? '';

// ── Entidades con clave compuesta (sin ID único) ──

try {
    $db = Database::getInstance();

    // ── Plantilla (equipo_jugador): clave compuesta (id_jugador, id_equipo, id_liga) ──
    if ($entidad === 'plantilla') {
        $id_jugador = isset($_GET['id_jugador']) && $_GET['id_jugador'] !== '' ? (int)$_GET['id_jugador'] : 0;
        $id_equipo  = isset($_GET['id_equipo']) && $_GET['id_equipo'] !== '' ? (int)$_GET['id_equipo'] : 0;
        $id_liga    = isset($_GET['id_liga']) && $_GET['id_liga'] !== '' ? (int)$_GET['id_liga'] : 0;

        if (!$id_jugador || !$id_equipo || !$id_liga) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Faltan parámetros: id_jugador, id_equipo, id_liga']);
            exit;
        }

        $model = new EquipoJugadorModel();
        $row = $model->getById($id_jugador, $id_equipo, $id_liga);
        if (!$row) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'No existe esa asignación de jugador']);
            exit;
        }

        $model->delete($id_jugador, $id_equipo, $id_liga);
        echo json_encode(['success' => true, 'entidad' => $entidad, 'registro_borrado' => $row], JSON_PRETTY_PRINT);
        exit;
    }

    // ── Staff (entrenador_equipo): clave compuesta (id_entrenador, id_equipo, id_liga) ──
    if ($entidad === 'staff') {
        $id_entrenador = isset($_GET['id_entrenador']) && $_GET['id_entrenador'] !== '' ? (int)$_GET['id_entrenador'] : 0;
        $id_equipo     = isset($_GET['id_equipo']) && $_GET['id_equipo'] !== '' ? (int)$_GET['id_equipo'] : 0;
        $id_liga       = isset($_GET['id_liga']) && $_GET['id_liga'] !== '' ? (int)$_GET['id_liga'] : 0;

        if (!$id_entrenador || !$id_equipo || !$id_liga) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Faltan parámetros: id_entrenador, id_equipo, id_liga']);
            exit;
        }

        $model = new EntrenadorEquipoModel();
        $row = $model->getById($id_entrenador, $id_equipo, $id_liga);
        if (!$row) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'No existe esa asignación de entrenador']);
            exit;
        }

        $model->delete($id_entrenador, $id_equipo, $id_liga);
        echo json_encode(['success' => true, 'entidad' => $entidad, 'registro_borrado' => $row], JSON_PRETTY_PRINT);
        exit;
    }

    // ── Equipo-Liga: clave compuesta (id_equipo, id_liga) ──
    if ($entidad === 'equipo_liga') {
        $id_equipo = isset($_GET['id_equipo']) && $_GET['id_equipo'] !== '' ? (int)$_GET['id_equipo'] : 0;
        $id_liga   = isset($_GET['id_liga']) && $_GET['id_liga'] !== '' ? (int)$_GET['id_liga'] : 0;

        if (!$id_equipo || !$id_liga) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Faltan parámetros: id_equipo, id_liga']);
            exit;
        }

        $model = new EquipoLigaModel();
        $row = $model->getById($id_equipo, $id_liga);
        if (!$row) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'No existe esa relación equipo-liga']);
            exit;
        }

        $model->delete($id_equipo, $id_liga);
        echo json_encode(['success' => true, 'entidad' => $entidad, 'registro_borrado' => $row], JSON_PRETTY_PRINT);
        exit;
    }

    // ── Entidades con ID único ──
    $id = $_GET['id'] ?? '';

    if (!is_numeric($id) || intval($id) <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID inválido']);
        exit;
    }

    $id = (int)$id;

    $mapping = [
        'equipos'         => ['table' => 'equipos', 'id_col' => 'id_equipo'],
        'ligas'           => ['table' => 'ligas', 'id_col' => 'id_liga'],
        'jugador'         => ['table' => 'jugador', 'id_col' => 'id_jugador'],
        'jugadores'       => ['table' => 'jugador', 'id_col' => 'id_jugador'],
        'entrenadores'    => ['table' => 'entrenadores', 'id_col' => 'id_entrenador'],
        'usuario'         => ['table' => 'usuario', 'id_col' => 'id_usuario'],
        'clasificaciones' => ['table' => 'clasificacion', 'id_col' => 'id_clasificacion'],
        'partidos'        => ['table' => 'partidos', 'id_col' => 'id_partido'],
    ];

    // Mapeo de entidades a clases de modelos disponibles
    $modelMapping = [
        'ligas'           => 'LigasModel',
        'usuario'         => 'UsuariosModel',
        'equipos'         => 'EquiposModel',
        'jugadores'       => 'JugadoresModel',
        'jugador'         => 'JugadoresModel',
        'entrenadores'    => 'EntrenadoresModel',
        'clasificaciones' => 'ClasificacionesModel',
        'partidos'        => 'PartidosModel',
    ];

    if (!isset($mapping[$entidad])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Entidad no válida. Usa: equipos | ligas | jugador | entrenadores | usuario | plantilla | staff | equipo_liga | clasificaciones | partidos']);
        exit;
    }

    $table = $mapping[$entidad]['table'];
    $id_col = $mapping[$entidad]['id_col'];

    // Usar el modelo si está disponible; fallback a PDO genérico
    if (isset($modelMapping[$entidad])) {
        $modelClass = $modelMapping[$entidad];
        $model = new $modelClass();

        $row = $model->getById($id);
        if (!$row) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'No existe un registro con ese ID']);
            exit;
        }

        $ok = $model->delete($id);
        if ($ok) {
            echo json_encode([
                'success' => true,
                'entidad' => $entidad,
                'registro_borrado' => $row
            ], JSON_PRETTY_PRINT);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Error al borrar el registro']);
        }

        exit;
    }

    // Fallback: PDO genérico para entidades sin modelo
    $stmt = $db->prepare("SELECT * FROM `$table` WHERE `$id_col` = :id LIMIT 1");
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();

    if (!$row) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'No existe un registro con ese ID']);
        exit;
    }

    $del = $db->prepare("DELETE FROM `$table` WHERE `$id_col` = :id");
    $del->execute([':id' => $id]);

    echo json_encode([
        'success' => true,
        'entidad' => $entidad,
        'registro_borrado' => $row
    ], JSON_PRETTY_PRINT);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
