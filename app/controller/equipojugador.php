<?php

declare(strict_types=1);

/**
 * ============================================================================
 *  EquipoJugadorController
 * ============================================================================
 *  Gestión de la relación equipo ↔ jugador (plantilla).
 *
 *  GET    /plantillas               → seleccionar()      Ver plantilla de un equipo
 *  GET    /plantillas/detalle       → detalle()          Detalle individual
 *  POST   /plantillas               → insertar()         Añadir jugador EXISTENTE a plantilla
 *  PATCH  /plantillas/dorsal        → actualizarDorsal() Asignar dorsal (una sola vez)
 *  DELETE /plantillas               → eliminar()         Baja directa (admin) / solicitar baja (staff)
 *
 *  NOTA IMPORTANTE:
 *  insertar() NO crea jugadores. Solo vincula un jugador global ya existente
 *  a un equipo/liga. Para crear jugadores nuevos usar:
 *    - POST /admin/jugadores/alta-directa  (ADMIN, alta directa con documentación)
 *    - POST /staff/jugadores/alta          (STAFF, genera solicitud pendiente)
 * ============================================================================
 */

require_once __DIR__ . '/../core/Autenticacion.php';
require_once __DIR__ . '/../model/equipoJugadorModel.php';

class EquipoJugadorController
{
    private function responder(int $codigoHttp, array $contenido): void
    {
        http_response_code($codigoHttp);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($contenido, JSON_UNESCAPED_UNICODE);
        exit;
    }

    // =====================================================
    // VER PLANTILLA DE EQUIPO (GET /plantillas?id_equipo=X&id_liga=Y)
    // Refactorizado: lee de query/body, usa JOIN con jugador
    // =====================================================
    public function seleccionar(array $entrada = []): void
    {
        try {
            $id_equipo = (int)($entrada['id_equipo'] ?? 0);
            $id_liga   = (int)($entrada['id_liga'] ?? 0);

            if (!$id_equipo || !$id_liga) {
                $this->responder(400, ['success' => false, 'message' => 'Faltan id_equipo e id_liga']);
            }

            $modelo = new EquipoJugadorModel();
            $datos = $modelo->getByEquipoConJugadores($id_equipo, $id_liga);

            $this->responder(200, [
                'success' => true,
                'data' => $datos
            ]);
        } catch (Throwable $e) {
            $this->responder(500, ['success' => false, 'message' => $e->getMessage()]);
        }
    }

    // =====================================================
    // DETALLE INDIVIDUAL (GET /plantillas/detalle?id_jugador=X&id_equipo=Y&id_liga=Z)
    // =====================================================
    public function detalle(array $entrada): void
    {
        try {
            $id_jugador = (int)($entrada['id_jugador'] ?? 0);
            $id_equipo  = (int)($entrada['id_equipo'] ?? 0);
            $id_liga    = (int)($entrada['id_liga'] ?? 0);

            if (!$id_jugador || !$id_equipo || !$id_liga) {
                $this->responder(400, ['success' => false, 'message' => 'Faltan campos obligatorios']);
            }

            $modelo = new EquipoJugadorModel();
            $detalle = $modelo->getDetalleJugadorPlantilla($id_jugador, $id_equipo, $id_liga);

            if (!$detalle) {
                $this->responder(404, ['success' => false, 'message' => 'Jugador no encontrado en esta plantilla']);
            }

            $this->responder(200, [
                'success' => true,
                'data' => $detalle
            ]);
        } catch (Throwable $e) {
            $this->responder(500, ['success' => false, 'message' => $e->getMessage()]);
        }
    }

    // =====================================================
    // INSERTAR JUGADOR A EQUIPO (ADMIN)
    // =====================================================
    public function insertar(array $entrada): void
    {
        try {
            Autenticacion::requerirRol([Autenticacion::ROL_ADMIN]);

            $id_jugador = (int)($entrada['id_jugador'] ?? 0);
            $id_equipo  = (int)($entrada['id_equipo'] ?? 0);
            $id_liga    = (int)($entrada['id_liga'] ?? 0);
            $dorsal     = isset($entrada['dorsal']) ? (int)$entrada['dorsal'] : null;

            if (!$id_jugador || !$id_equipo || !$id_liga) {
                $this->responder(400, ['success' => false, 'message' => 'Faltan campos obligatorios']);
            }

            $modelo = new EquipoJugadorModel();

            if ($modelo->existeRelacion($id_jugador, $id_equipo, $id_liga)) {
                $this->responder(409, ['success' => false, 'message' => 'El jugador ya pertenece a ese equipo']);
            }

            $modelo->insertarRelacion($id_jugador, $id_equipo, $id_liga, $dorsal);

            $this->responder(201, [
                'success' => true,
                'message' => 'Jugador añadido a plantilla'
            ]);
        } catch (Throwable $e) {
            $this->responder(500, ['success' => false, 'message' => $e->getMessage()]);
        }
    }

    // =====================================================
    // ACTUALIZAR DORSAL — SOLO UNA VEZ
    // =====================================================
    public function actualizarDorsal(array $entrada): void
    {
        try {
            $id_jugador = (int)($entrada['id_jugador'] ?? 0);
            $id_equipo  = (int)($entrada['id_equipo'] ?? 0);
            $id_liga    = (int)($entrada['id_liga'] ?? 0);
            $dorsal     = (int)($entrada['dorsal'] ?? 0);

            if (!$id_jugador || !$id_equipo || !$id_liga || !$dorsal) {
                $this->responder(400, ['success' => false, 'message' => 'Faltan campos obligatorios']);
            }

            // Staff solo su equipo o admin
            if (!Autenticacion::tieneRol([Autenticacion::ROL_ADMIN])) {
                Autenticacion::requerirStaffDeEquipo($id_equipo);
            }

            $modelo = new EquipoJugadorModel();

            // Comprobar si ya tiene dorsal
            if ($modelo->tieneDorsal($id_jugador, $id_equipo, $id_liga)) {
                $this->responder(409, [
                    'success' => false,
                    'message' => 'El dorsal ya fue asignado anteriormente. No se puede modificar.'
                ]);
            }

            $filas = $modelo->actualizarDorsal(
                $id_jugador,
                $id_equipo,
                $id_liga,
                $dorsal
            );

            if (!$filas) {
                $this->responder(404, ['success' => false, 'message' => 'Relación no encontrada']);
            }

            $this->responder(200, [
                'success' => true,
                'message' => 'Dorsal asignado correctamente'
            ]);
        } catch (Throwable $e) {
            $this->responder(500, ['success' => false, 'message' => $e->getMessage()]);
        }
    }

    // =====================================================
    // ELIMINAR JUGADOR DE PLANTILLA
    // =====================================================
    public function eliminar(array $entrada): void
    {
        try {
            $id_jugador = (int)($entrada['id_jugador'] ?? 0);
            $id_equipo  = (int)($entrada['id_equipo'] ?? 0);
            $id_liga    = (int)($entrada['id_liga'] ?? 0);

            if (!$id_jugador || !$id_equipo || !$id_liga) {
                $this->responder(400, ['success' => false, 'message' => 'Faltan campos obligatorios']);
            }

            $esAdmin = Autenticacion::tieneRol([Autenticacion::ROL_ADMIN]);

            if (!$esAdmin) {
                Autenticacion::requerirStaffDeEquipo($id_equipo);
            }

            $modelo = new EquipoJugadorModel();

            // Verificar que la relación existe
            if (!$modelo->existeRelacion($id_jugador, $id_equipo, $id_liga)) {
                $this->responder(404, ['success' => false, 'message' => 'Relación no encontrada']);
            }

            if (!$esAdmin) {
                require_once __DIR__ . '/../model/jugadoresModel.php';
                $jugModel = new JugadoresModel();
                $jugModel->solicitarBaja($id_jugador);

                $this->responder(200, [
                    'success' => true,
                    'message' => 'Baja solicitada correctamente. Pendiente de aprobación.'
                ]);
            } else {
                $modelo->eliminarRelacion($id_jugador, $id_equipo, $id_liga);

                $this->responder(200, [
                    'success' => true,
                    'message' => 'Jugador eliminado de plantilla'
                ]);
            }
        } catch (Throwable $e) {
            $this->responder(500, ['success' => false, 'message' => $e->getMessage()]);
        }
    }
}
