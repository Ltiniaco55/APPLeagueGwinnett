<?php

declare(strict_types=1);

require_once __DIR__ . '/../model/equiposFavoritosModel.php';
require_once __DIR__ . '/../core/Autenticacion.php';

class EquiposFavoritosController
{
    private EquiposFavoritosModel $model;

    public function __construct()
    {
        $this->model = new EquiposFavoritosModel();
    }

    private function responder(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');

        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    private function obtenerUsuarioSesion()
    {
        Autenticacion::requerirAutenticacion();

        if (isset($_SESSION['usuario']['id_usuario'])) {
            return (int) $_SESSION['usuario']['id_usuario'];
        }

        if (isset($_SESSION['id_usuario'])) {
            return (int) $_SESSION['id_usuario'];
        }

        if (isset($_SESSION['usuario_id'])) {
            return (int) $_SESSION['usuario_id'];
        }

        if (isset($_SESSION['user']['id_usuario'])) {
            return (int) $_SESSION['user']['id_usuario'];
        }

        $this->responder([
            'success' => false,
            'message' => 'Usuario no autenticado',
            'debug_session_keys' => array_keys($_SESSION)
        ], 401);
    }

    public function seleccionar(): void
    {
        try {
            $idUsuario = $this->obtenerUsuarioSesion();

            $favoritos = $this->model->seleccionarFavoritosConLigas($idUsuario);

            $this->responder([
                'success' => true,
                'data' => $favoritos
            ]);
        } catch (Throwable $e) {
            $this->responder([
                'success' => false,
                'message' => 'Error al cargar equipos favoritos',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function insertar(): void
    {
        try {
            $idUsuario = $this->obtenerUsuarioSesion();

            $input = json_decode(file_get_contents('php://input'), true);

            $idEquipo = isset($input['id_equipo'])
                ? (int)$input['id_equipo']
                : 0;

            if ($idEquipo <= 0) {
                $this->responder([
                    'success' => false,
                    'message' => 'id_equipo es obligatorio'
                ], 400);
            }

            if ($this->model->existe($idUsuario, $idEquipo)) {
                $this->responder([
                    'success' => false,
                    'message' => 'Este equipo ya está en favoritos'
                ], 409);
            }

            $ok = $this->model->insertar($idUsuario, $idEquipo);

            $this->responder([
                'success' => $ok,
                'message' => $ok
                    ? 'Equipo añadido a favoritos'
                    : 'No se pudo añadir el equipo a favoritos'
            ], $ok ? 201 : 500);
        } catch (Throwable $e) {
            $this->responder([
                'success' => false,
                'message' => 'Error al añadir equipo favorito',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function eliminar(): void
    {
        try {
            $idUsuario = $this->obtenerUsuarioSesion();

            $input = json_decode(file_get_contents('php://input'), true);

            $idEquipo = isset($input['id_equipo'])
                ? (int)$input['id_equipo']
                : 0;

            if ($idEquipo <= 0) {
                $this->responder([
                    'success' => false,
                    'message' => 'id_equipo es obligatorio'
                ], 400);
            }

            $ok = $this->model->eliminar($idUsuario, $idEquipo);

            $this->responder([
                'success' => $ok,
                'message' => $ok
                    ? 'Equipo eliminado de favoritos'
                    : 'No se pudo eliminar el equipo de favoritos'
            ]);
        } catch (Throwable $e) {
            $this->responder([
                'success' => false,
                'message' => 'Error al eliminar equipo favorito',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function proximosPartidos(): void
    {
        try {
            $idUsuario = $this->obtenerUsuarioSesion();

            $limite = isset($_GET['limite'])
                ? (int)$_GET['limite']
                : 16;

            if ($limite <= 0 || $limite > 16) {
                $limite = 16;
            }

            $partidos = $this->model->seleccionarProximosPartidosFavoritos(
                $idUsuario,
                $limite
            );

            $this->responder([
                'success' => true,
                'data' => $partidos
            ]);
        } catch (Throwable $e) {
            $this->responder([
                'success' => false,
                'message' => 'Error al cargar próximos partidos favoritos',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
