<?php

declare(strict_types=1);

/**
 * ============================================================================
 *  PartidosController
 * ============================================================================
 *  Gestiona los endpoints REST de partidos.
 *
 *  Rutas públicas (sin autenticación):
 *    GET  /partidos
 *    GET  /partidos/{id}
 *
 *  Rutas protegidas (solo ADMIN):
 *    POST   /admin/partidos
 *    PUT    /admin/partidos/{id}
 *    PATCH  /admin/partidos/{id}/cancelar
 *    DELETE /admin/partidos/{id}
 * ============================================================================
 */

require_once __DIR__ . '/../core/Autenticacion.php';
require_once __DIR__ . '/../model/partidosModel.php';

class PartidosController
{
    // =========================================================================
    //  HELPERS PRIVADOS
    // =========================================================================

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

    /**
     * Comprueba si el estado está dentro de los valores permitidos.
     */
    private function validarEstado(string $estado): bool
    {
        return in_array($estado, ['pendiente', 'programado', 'jugado', 'cancelado'], true);
    }

    /**
     * Construye un datetime SQL válido a partir de la entrada.
     *
     * Acepta:
     *   a) Campo "fecha" con datetime completo  →  se usa directamente
     *   b) "fecha_dia" + "hora"                 →  se unen como "Y-m-d H:i:s"
     *
     * Lanza RuntimeException si no se puede determinar la fecha.
     */
    private function prepararFecha(array $entrada): string
    {
        // Caso a: fecha completa
        if (!empty($entrada['fecha'])) {
            return $this->limpiarTexto($entrada['fecha']);
        }

        // Caso b: dia + hora separados
        $dia  = $this->limpiarTexto($entrada['fecha_dia'] ?? '');
        $hora = $this->limpiarTexto($entrada['hora']      ?? '');

        if ($dia === '' || $hora === '') {
            throw new \RuntimeException('Debe indicar "fecha" o bien "fecha_dia" y "hora"');
        }

        return $dia . ' ' . $hora;
    }

    // =========================================================================
    //  SELECCIONAR  –  GET /partidos
    // =========================================================================

    public function seleccionar(array $entrada = []): void
    {
        try {
            $filtros = [];

            if (!empty($entrada['id_liga'])) {
                $filtros['id_liga'] = (int) $entrada['id_liga'];
            }
            if (!empty($entrada['fecha'])) {
                $filtros['fecha'] = $this->limpiarTexto($entrada['fecha']);
            }
            if (!empty($entrada['id_equipo'])) {
                $filtros['id_equipo'] = (int) $entrada['id_equipo'];
            }
            if (isset($entrada['jornada']) && $entrada['jornada'] !== '') {
                $filtros['jornada'] = $this->limpiarTexto($entrada['jornada']);
            }
            if (isset($entrada['estado']) && $entrada['estado'] !== '') {
                $filtros['estado'] = $this->limpiarTexto($entrada['estado']);
            }
            if (isset($entrada['lugar']) && $entrada['lugar'] !== '') {
                $filtros['lugar'] = $this->limpiarTexto($entrada['lugar']);
            }

            $modelo = new PartidosModel();
            $datos  = $modelo->getAll($filtros);

            $this->responder(200, [
                'success' => true,
                'data'    => $datos,
            ]);
        } catch (\Throwable $e) {
            $this->responder(500, [
                'success' => false,
                'message' => 'Error interno: ' . $e->getMessage(),
            ]);
        }
    }

    // =========================================================================
    //  LOCALIZAR  –  GET /partidos/{id}
    // =========================================================================

    public function localizar(int $id): void
    {
        try {
            if ($id <= 0) {
                $this->responder(400, [
                    'success' => false,
                    'message' => 'ID de partido no válido',
                ]);
            }

            $modelo  = new PartidosModel();
            $partido = $modelo->getById($id);

            if ($partido === null) {
                $this->responder(404, [
                    'success' => false,
                    'message' => 'Partido no encontrado',
                ]);
            }

            $this->responder(200, [
                'success' => true,
                'data'    => $partido,
            ]);
        } catch (\Throwable $e) {
            $this->responder(500, [
                'success' => false,
                'message' => 'Error interno: ' . $e->getMessage(),
            ]);
        }
    }

    // =========================================================================
    //  INSERTAR  –  POST /admin/partidos
    // =========================================================================

    public function insertar(array $entrada): void
    {
        try {
            Autenticacion::requerirRol([Autenticacion::ROL_ADMIN]);

            // ── Preparar fecha ───────────────────────────────────────────────
            try {
                $fecha = $this->prepararFecha($entrada);
            } catch (\RuntimeException $ex) {
                $this->responder(400, ['success' => false, 'message' => $ex->getMessage()]);
            }

            // ── Campos base ──────────────────────────────────────────────────
            $idLiga       = isset($entrada['id_liga'])             ? (int) $entrada['id_liga']             : 0;
            $jornada      = $this->limpiarTexto($entrada['jornada']      ?? '');
            $lugar        = $this->limpiarTexto($entrada['lugar']        ?? '');
            $arbitro      = $this->limpiarTexto($entrada['arbitro']      ?? '');
            $idLocal      = isset($entrada['id_equipo_local'])      ? (int) $entrada['id_equipo_local']      : 0;
            $idVisitante  = isset($entrada['id_equipo_visitante'])  ? (int) $entrada['id_equipo_visitante']  : 0;
            $golesLocal   = isset($entrada['goles_local'])      && $entrada['goles_local']     !== ''
                            ? (int) $entrada['goles_local']     : null;
            $golesVisitante = isset($entrada['goles_visitante']) && $entrada['goles_visitante'] !== ''
                            ? (int) $entrada['goles_visitante'] : null;
            $estado       = $this->limpiarTexto($entrada['estado'] ?? 'programado');

            // ── Validar obligatorios ─────────────────────────────────────────
            if ($idLiga === 0 || $jornada === '' || $fecha === '' || $lugar === '' || $idLocal === 0 || $idVisitante === 0) {
                $this->responder(400, [
                    'success' => false,
                    'message' => 'Faltan campos obligatorios: id_liga, jornada, fecha, lugar, id_equipo_local, id_equipo_visitante',
                ]);
            }

            // ── Validar estado ───────────────────────────────────────────────
            if (!$this->validarEstado($estado)) {
                $this->responder(400, [
                    'success' => false,
                    'message' => 'Estado de partido no permitido. Valores válidos: pendiente, programado, jugado, cancelado',
                ]);
            }

            // ── Equipo local != visitante ────────────────────────────────────
            if ($idLocal === $idVisitante) {
                $this->responder(400, [
                    'success' => false,
                    'message' => 'Equipo local y visitante no pueden ser el mismo',
                ]);
            }

            $modelo = new PartidosModel();

            // ── Liga existe ──────────────────────────────────────────────────
            if (!$modelo->existeLiga($idLiga)) {
                $this->responder(404, [
                    'success' => false,
                    'message' => 'Liga no encontrada',
                ]);
            }

            // ── Equipos existen ──────────────────────────────────────────────
            if (!$modelo->existeEquipo($idLocal)) {
                $this->responder(404, [
                    'success' => false,
                    'message' => 'Equipo local no encontrado',
                ]);
            }
            if (!$modelo->existeEquipo($idVisitante)) {
                $this->responder(404, [
                    'success' => false,
                    'message' => 'Equipo visitante no encontrado',
                ]);
            }

            // ── Equipos pertenecen a la liga ─────────────────────────────────
            if (!$modelo->equiposPertenecenALiga($idLiga, $idLocal, $idVisitante)) {
                $this->responder(409, [
                    'success' => false,
                    'message' => 'Uno o ambos equipos no pertenecen a la liga seleccionada',
                ]);
            }

            // ── Sin duplicado de jornada ─────────────────────────────────────
            if ($modelo->existeDuplicado($idLiga, $jornada, $idLocal, $idVisitante)) {
                $this->responder(409, [
                    'success' => false,
                    'message' => 'Ya existe un partido con esos equipos en esta jornada',
                ]);
            }

            // ── Sin conflicto horario ────────────────────────────────────────
            if ($modelo->existeConflictoHorario($idLiga, $fecha, $idLocal, $idVisitante)) {
                $this->responder(409, [
                    'success' => false,
                    'message' => 'Uno de los equipos ya tiene un partido en esa fecha y hora',
                ]);
            }

            // ── Insertar ─────────────────────────────────────────────────────
            $idNuevo = $modelo->insertar([
                'id_liga'             => $idLiga,
                'jornada'             => $jornada,
                'fecha'               => $fecha,
                'lugar'               => $lugar,
                'arbitro'             => $arbitro !== '' ? $arbitro : null,
                'id_equipo_local'     => $idLocal,
                'id_equipo_visitante' => $idVisitante,
                'goles_local'         => $golesLocal,
                'goles_visitante'     => $golesVisitante,
                'estado'              => $estado,
            ]);

            $this->responder(201, [
                'success'    => true,
                'message'    => 'Partido creado correctamente',
                'data'       => ['id_partido' => $idNuevo],
            ]);
        } catch (\Throwable $e) {
            $this->responder(500, [
                'success' => false,
                'message' => 'Error interno: ' . $e->getMessage(),
            ]);
        }
    }

    // =========================================================================
    //  MODIFICAR  –  PUT /admin/partidos/{id}
    // =========================================================================

    public function modificar(int $id, array $entrada): void
    {
        try {
            Autenticacion::requerirRol([Autenticacion::ROL_ADMIN]);

            if ($id <= 0) {
                $this->responder(400, [
                    'success' => false,
                    'message' => 'ID de partido no válido',
                ]);
            }

            $modelo  = new PartidosModel();
            $partido = $modelo->getById($id);

            if ($partido === null) {
                $this->responder(404, [
                    'success' => false,
                    'message' => 'Partido no encontrado',
                ]);
            }

            // ── Preparar fecha ───────────────────────────────────────────────
            try {
                $fecha = $this->prepararFecha($entrada);
            } catch (\RuntimeException $ex) {
                $this->responder(400, ['success' => false, 'message' => $ex->getMessage()]);
            }

            // ── Campos base ──────────────────────────────────────────────────
            $idLiga       = isset($entrada['id_liga'])             ? (int) $entrada['id_liga']             : 0;
            $jornada      = $this->limpiarTexto($entrada['jornada']      ?? '');
            $lugar        = $this->limpiarTexto($entrada['lugar']        ?? '');
            $arbitro      = $this->limpiarTexto($entrada['arbitro']      ?? '');
            $idLocal      = isset($entrada['id_equipo_local'])      ? (int) $entrada['id_equipo_local']      : 0;
            $idVisitante  = isset($entrada['id_equipo_visitante'])  ? (int) $entrada['id_equipo_visitante']  : 0;
            $golesLocal   = isset($entrada['goles_local'])      && $entrada['goles_local']     !== ''
                            ? (int) $entrada['goles_local']     : null;
            $golesVisitante = isset($entrada['goles_visitante']) && $entrada['goles_visitante'] !== ''
                            ? (int) $entrada['goles_visitante'] : null;
            $estado       = $this->limpiarTexto($entrada['estado'] ?? '');

            // ── Validar obligatorios ─────────────────────────────────────────
            if ($idLiga === 0 || $jornada === '' || $fecha === '' || $lugar === '' || $idLocal === 0 || $idVisitante === 0) {
                $this->responder(400, [
                    'success' => false,
                    'message' => 'Faltan campos obligatorios: id_liga, jornada, fecha, lugar, id_equipo_local, id_equipo_visitante',
                ]);
            }

            // ── Validar estado ───────────────────────────────────────────────
            if ($estado === '' || !$this->validarEstado($estado)) {
                $this->responder(400, [
                    'success' => false,
                    'message' => 'Estado de partido no permitido. Valores válidos: pendiente, programado, jugado, cancelado',
                ]);
            }

            // ── Equipo local != visitante ────────────────────────────────────
            if ($idLocal === $idVisitante) {
                $this->responder(400, [
                    'success' => false,
                    'message' => 'Equipo local y visitante no pueden ser el mismo',
                ]);
            }

            // ── Liga existe ──────────────────────────────────────────────────
            if (!$modelo->existeLiga($idLiga)) {
                $this->responder(404, [
                    'success' => false,
                    'message' => 'Liga no encontrada',
                ]);
            }

            // ── Equipos existen ──────────────────────────────────────────────
            if (!$modelo->existeEquipo($idLocal)) {
                $this->responder(404, [
                    'success' => false,
                    'message' => 'Equipo local no encontrado',
                ]);
            }
            if (!$modelo->existeEquipo($idVisitante)) {
                $this->responder(404, [
                    'success' => false,
                    'message' => 'Equipo visitante no encontrado',
                ]);
            }

            // ── Equipos pertenecen a la liga ─────────────────────────────────
            if (!$modelo->equiposPertenecenALiga($idLiga, $idLocal, $idVisitante)) {
                $this->responder(409, [
                    'success' => false,
                    'message' => 'Uno o ambos equipos no pertenecen a la liga seleccionada',
                ]);
            }

            // ── Sin duplicado (excluye el propio partido) ────────────────────
            if ($modelo->existeDuplicado($idLiga, $jornada, $idLocal, $idVisitante, $id)) {
                $this->responder(409, [
                    'success' => false,
                    'message' => 'Ya existe un partido con esos equipos en esta jornada',
                ]);
            }

            // ── Sin conflicto horario (excluye el propio partido) ────────────
            if ($modelo->existeConflictoHorario($idLiga, $fecha, $idLocal, $idVisitante, $id)) {
                $this->responder(409, [
                    'success' => false,
                    'message' => 'Uno de los equipos ya tiene un partido en esa fecha y hora',
                ]);
            }

            // ── Actualizar ───────────────────────────────────────────────────
            $modelo->modificar($id, [
                'id_liga'             => $idLiga,
                'jornada'             => $jornada,
                'fecha'               => $fecha,
                'lugar'               => $lugar,
                'arbitro'             => $arbitro !== '' ? $arbitro : null,
                'id_equipo_local'     => $idLocal,
                'id_equipo_visitante' => $idVisitante,
                'goles_local'         => $golesLocal,
                'goles_visitante'     => $golesVisitante,
                'estado'              => $estado,
            ]);

            $this->responder(200, [
                'success' => true,
                'message' => 'Partido actualizado correctamente',
            ]);
        } catch (\Throwable $e) {
            $this->responder(500, [
                'success' => false,
                'message' => 'Error interno: ' . $e->getMessage(),
            ]);
        }
    }

    // =========================================================================
    //  CANCELAR  –  PATCH /admin/partidos/{id}/cancelar
    // =========================================================================

    public function cancelar(int $id): void
    {
        try {
            Autenticacion::requerirRol([Autenticacion::ROL_ADMIN]);

            if ($id <= 0) {
                $this->responder(400, [
                    'success' => false,
                    'message' => 'ID de partido no válido',
                ]);
            }

            $modelo  = new PartidosModel();
            $partido = $modelo->getById($id);

            if ($partido === null) {
                $this->responder(404, [
                    'success' => false,
                    'message' => 'Partido no encontrado',
                ]);
            }

            $modelo->cancelar($id);

            $this->responder(200, [
                'success' => true,
                'message' => 'Partido cancelado correctamente',
            ]);
        } catch (\Throwable $e) {
            $this->responder(500, [
                'success' => false,
                'message' => 'Error interno: ' . $e->getMessage(),
            ]);
        }
    }

    // =========================================================================
    //  ELIMINAR  –  DELETE /admin/partidos/{id}
    // =========================================================================

    public function eliminar(int $id): void
    {
        try {
            Autenticacion::requerirRol([Autenticacion::ROL_ADMIN]);

            if ($id <= 0) {
                $this->responder(400, [
                    'success' => false,
                    'message' => 'ID de partido no válido',
                ]);
            }

            $modelo  = new PartidosModel();
            $partido = $modelo->getById($id);

            if ($partido === null) {
                $this->responder(404, [
                    'success' => false,
                    'message' => 'Partido no encontrado',
                ]);
            }

            $modelo->delete($id);

            $this->responder(200, [
                'success' => true,
                'message' => 'Partido eliminado correctamente',
            ]);
        } catch (\Throwable $e) {
            $this->responder(500, [
                'success' => false,
                'message' => 'Error interno: ' . $e->getMessage(),
            ]);
        }
    }
}
