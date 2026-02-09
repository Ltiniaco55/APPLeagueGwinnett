<?php

require_once __DIR__ . '/../core/database.php';
require_once __DIR__ . '/../model/ligasModel.php';
require_once __DIR__ . '/../model/usuariosModel.php';
require_once __DIR__ . '/../model/equiposModel.php';
require_once __DIR__ . '/../model/jugadoresModel.php';
require_once __DIR__ . '/../model/entrenadoresModel.php';

header('Content-Type: application/json; charset=utf-8');

$entidad = $_GET['entidad'] ?? '';

try {
    $db = Database::getInstance();

    if ($entidad === 'ligas') {
        $nombre_liga = $_GET['nombre_liga'] ?? '';
        $temporada   = $_GET['temporada'] ?? '';
        $categoria   = $_GET['categoria'] ?? '';

        $model = new LigasModel();
        $data = $model->getAllFiltered($nombre_liga, $temporada, $categoria);

        echo json_encode($data, JSON_PRETTY_PRINT);
        exit;
    }

    if ($entidad === 'equipos') {
        $club      = $_GET['club'] ?? '';
        $categoria = $_GET['categoria'] ?? '';
        $temporada = $_GET['temporada'] ?? '';

        $model = new EquiposModel();
        $data = $model->search($club, $categoria, $temporada);

        echo json_encode($data, JSON_PRETTY_PRINT);
        exit;
    }

    if ($entidad === 'entrenadores') {
        $nombre = $_GET['nombre'] ?? '';
        $apellido = $_GET['apellido'] ?? '';

        $model = new EntrenadoresModel();
        $data = $model->search($nombre, $apellido);

        echo json_encode($data, JSON_PRETTY_PRINT);
        exit;
    }

    if ($entidad === 'usuario') {
        $nombre   = $_GET['nombre'] ?? '';
        $apellido = $_GET['apellido'] ?? '';
        $email    = $_GET['email'] ?? '';

        $model = new UsuariosModel();
        $data = $model->searchFields($nombre, $apellido, $email);

        echo json_encode($data, JSON_PRETTY_PRINT);
        exit;
    }

    if ($entidad === 'jugadores') {
        $nombre   = $_GET['nombre'] ?? '';
        $apellido = $_GET['apellido'] ?? '';

        $model = new JugadoresModel();
        $data = $model->search($nombre, $apellido);

        echo json_encode($data, JSON_PRETTY_PRINT);
        exit;
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Entidad no válida. Usa: ligas | equipos | jugadores | entrenadores | usuario']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
