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

    // ── Plantilla (equipo_jugador) ──
    if ($entidad === 'plantilla') {
        $id_equipo = isset($_GET['id_equipo']) && $_GET['id_equipo'] !== '' ? (int)$_GET['id_equipo'] : 0;
        $id_liga   = isset($_GET['id_liga']) && $_GET['id_liga'] !== '' ? (int)$_GET['id_liga'] : 0;

        $model = new EquipoJugadorModel();

        if ($id_equipo > 0 && $id_liga > 0) {
            $data = $model->getByEquipoAndLiga($id_equipo, $id_liga);
        } elseif ($id_equipo > 0) {
            $data = $model->getByEquipo($id_equipo);
        } elseif ($id_liga > 0) {
            $data = $model->getByLiga($id_liga);
        } else {
            $data = $model->getAll();
        }

        echo json_encode($data, JSON_PRETTY_PRINT);
        exit;
    }

    // ── Staff (entrenador_equipo) ──
    if ($entidad === 'staff') {
        $id_entrenador = isset($_GET['id_entrenador']) && $_GET['id_entrenador'] !== '' ? (int)$_GET['id_entrenador'] : 0;
        $id_equipo     = isset($_GET['id_equipo']) && $_GET['id_equipo'] !== '' ? (int)$_GET['id_equipo'] : 0;
        $id_liga       = isset($_GET['id_liga']) && $_GET['id_liga'] !== '' ? (int)$_GET['id_liga'] : 0;

        $model = new EntrenadorEquipoModel();

        if ($id_entrenador > 0) {
            $data = $model->getByEntrenador($id_entrenador);
        } elseif ($id_equipo > 0) {
            $data = $model->getByEquipo($id_equipo);
        } elseif ($id_liga > 0) {
            $data = $model->getByLiga($id_liga);
        } else {
            $data = $model->getAll();
        }

        echo json_encode($data, JSON_PRETTY_PRINT);
        exit;
    }

    // ── Equipo-Liga ──
    if ($entidad === 'equipo_liga') {
        $id_equipo = isset($_GET['id_equipo']) && $_GET['id_equipo'] !== '' ? (int)$_GET['id_equipo'] : 0;
        $id_liga   = isset($_GET['id_liga']) && $_GET['id_liga'] !== '' ? (int)$_GET['id_liga'] : 0;

        $model = new EquipoLigaModel();

        if ($id_equipo > 0) {
            $data = $model->getByEquipo($id_equipo);
        } elseif ($id_liga > 0) {
            $data = $model->getByLiga($id_liga);
        } else {
            $data = $model->getAll();
        }

        echo json_encode($data, JSON_PRETTY_PRINT);
        exit;
    }

    // ── Clasificaciones ──
    if ($entidad === 'clasificaciones') {
        $id_liga   = isset($_GET['id_liga']) && $_GET['id_liga'] !== '' ? (int)$_GET['id_liga'] : 0;
        $id_equipo = isset($_GET['id_equipo']) && $_GET['id_equipo'] !== '' ? (int)$_GET['id_equipo'] : 0;

        $model = new ClasificacionesModel();

        if ($id_liga > 0 && $id_equipo > 0) {
            $row = $model->getByEquipoAndLiga($id_equipo, $id_liga);
            $data = $row ? [$row] : [];
        } elseif ($id_liga > 0) {
            $data = $model->getByLiga($id_liga);
        } elseif ($id_equipo > 0) {
            $data = $model->getByEquipo($id_equipo);
        } else {
            $data = $model->getAll();
        }

        echo json_encode($data, JSON_PRETTY_PRINT);
        exit;
    }

    // ── Partidos ──
    if ($entidad === 'partidos') {
        $id_liga   = isset($_GET['id_liga']) && $_GET['id_liga'] !== '' ? (int)$_GET['id_liga'] : 0;
        $id_equipo = isset($_GET['id_equipo']) && $_GET['id_equipo'] !== '' ? (int)$_GET['id_equipo'] : 0;
        $estado    = $_GET['estado'] ?? '';

        $model = new PartidosModel();
        $data = $model->search($id_liga, $id_equipo, $estado);

        echo json_encode($data, JSON_PRETTY_PRINT);
        exit;
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Entidad no válida. Usa: ligas | equipos | jugadores | entrenadores | usuario | plantilla | staff | equipo_liga | clasificaciones | partidos']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
