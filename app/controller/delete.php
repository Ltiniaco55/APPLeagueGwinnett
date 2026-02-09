<?php

require_once __DIR__ . '/../core/database.php';
require_once __DIR__ . '/../model/ligasModel.php';
require_once __DIR__ . '/../model/usuariosModel.php';
require_once __DIR__ . '/../model/equiposModel.php';
require_once __DIR__ . '/../model/jugadoresModel.php';
require_once __DIR__ . '/../model/entrenadoresModel.php';

header('Content-Type: application/json; charset=utf-8');

$entidad = $_GET['entidad'] ?? '';
$id      = $_GET['id'] ?? '';

if (!is_numeric($id) || intval($id) <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID inválido']);
    exit;
}

$id = (int)$id;

$mapping = [
    'equipos'      => ['table' => 'equipos', 'id_col' => 'id_equipo'],
    'ligas'        => ['table' => 'ligas', 'id_col' => 'id_liga'],
    'jugador'      => ['table' => 'jugador', 'id_col' => 'id_jugador'],
    'jugadores'    => ['table' => 'jugador', 'id_col' => 'id_jugador'],
    'entrenadores' => ['table' => 'entrenadores', 'id_col' => 'id_entrenador'],
    'usuario'      => ['table' => 'usuario', 'id_col' => 'id_usuario'],
];

// Mapeo de entidades a clases de modelos disponibles
$modelMapping = [
    'ligas'        => 'LigasModel',
    'usuario'      => 'UsuariosModel',
    'equipos'      => 'EquiposModel',
    'jugadores'    => 'JugadoresModel',
    'jugador'      => 'JugadoresModel',
    'entrenadores' => 'EntrenadoresModel',
];

if (!isset($mapping[$entidad])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Entidad no válida. Usa: equipos | ligas | jugador | entrenadores | usuario']);
    exit;
}

$table = $mapping[$entidad]['table'];
$id_col = $mapping[$entidad]['id_col'];

try {
    $db = Database::getInstance();

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
