<?php

declare(strict_types=1);

require_once __DIR__ . '/../core/Autenticacion.php';
require_once __DIR__ . '/../model/partidosModel.php';
require_once __DIR__ . '/../model/clasificacionesModel.php';

class PartidosController
{
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

    private function validarEstado(string $estado): bool
    {
        return in_array($estado, ['pendiente', 'programado', 'jugado', 'cancelado'], true);
    }

    private function obtenerTiposRondaPermitidos(string $formatoLiga): array
    {
        if ($formatoLiga === 'JORNADAS') {
            $jornadas = [];

            for ($i = 1; $i <= 32; $i++) {
                $jornadas[] = 'Jornada ' . $i;
            }

            return $jornadas;
        }

        if ($formatoLiga === 'ELIMINATORIA') {
            return [
                'Fase de grupos',
                'Octavos de final',
                'Cuartos de final',
                'Semifinal',
                'Final'
            ];
        }

        if ($formatoLiga === 'AMISTOSO') {
            return ['Amistoso'];
        }

        return [];
    }

    private function prepararTipoRonda(PartidosModel $modelo, int $idLiga, string $tipoRondaEntrada): string
    {
        $formatoLiga = $modelo->getFormatoLiga($idLiga);

        if (!$formatoLiga) {
            throw new RuntimeException('No se pudo obtener el formato de la liga');
        }

        if ($formatoLiga === 'AMISTOSO') {
            return 'Amistoso';
        }

        $tipoRonda = $this->limpiarTexto($tipoRondaEntrada);

        if ($tipoRonda === '') {
            throw new RuntimeException('Debe indicar el tipo de ronda');
        }

        $permitidos = $this->obtenerTiposRondaPermitidos($formatoLiga);

        if (!in_array($tipoRonda, $permitidos, true)) {
            throw new RuntimeException('Tipo de ronda no permitido para esta liga');
        }

        return $tipoRonda;
    }

    private function regenerarClasificacion(int $idLiga): void
    {
        $clasificacionesModel = new ClasificacionesModel();
        $clasificacionesModel->regenerarLiga($idLiga);
    }

    private function prepararFecha(array $entrada): string
    {
        if (!empty($entrada['fecha'])) {
            return $this->limpiarTexto($entrada['fecha']);
        }

        $dia  = $this->limpiarTexto($entrada['fecha_dia'] ?? '');
        $hora = $this->limpiarTexto($entrada['hora']      ?? '');

        if ($dia === '' || $hora === '') {
            throw new \RuntimeException('Debe indicar "fecha" o bien "fecha_dia" y "hora"');
        }

        return $dia . ' ' . $hora;
    }

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
            if (isset($entrada['tipo_ronda']) && $entrada['tipo_ronda'] !== '') {
                $filtros['tipo_ronda'] = $this->limpiarTexto($entrada['tipo_ronda']);
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

    public function insertar(array $entrada): void
    {
        try {
            Autenticacion::requerirRol([Autenticacion::ROL_ADMIN]);

            try {
                $fecha = $this->prepararFecha($entrada);
            } catch (\RuntimeException $ex) {
                $this->responder(400, ['success' => false, 'message' => $ex->getMessage()]);
            }

            $idLiga       = isset($entrada['id_liga'])             ? (int) $entrada['id_liga']             : 0;
            $tipo_ronda      = $this->limpiarTexto($entrada['tipo_ronda']      ?? '');
            $lugar        = $this->limpiarTexto($entrada['lugar']        ?? '');
            $arbitro      = $this->limpiarTexto($entrada['arbitro']      ?? '');
            $idLocal      = isset($entrada['id_equipo_local'])      ? (int) $entrada['id_equipo_local']      : 0;
            $idVisitante  = isset($entrada['id_equipo_visitante'])  ? (int) $entrada['id_equipo_visitante']  : 0;
            $golesLocal   = isset($entrada['goles_local'])      && $entrada['goles_local']     !== ''
                ? (int) $entrada['goles_local']     : null;
            $golesVisitante = isset($entrada['goles_visitante']) && $entrada['goles_visitante'] !== ''
                ? (int) $entrada['goles_visitante'] : null;
            $estado       = $this->limpiarTexto($entrada['estado'] ?? 'programado');

            if (!$this->validarEstado($estado)) {
                $this->responder(400, [
                    'success' => false,
                    'message' => 'Estado de partido no permitido. Valores válidos: pendiente, programado, jugado, cancelado',
                ]);
            }

            if (
                ($golesLocal !== null && $golesLocal < 0) ||
                ($golesVisitante !== null && $golesVisitante < 0)
            ) {
                $this->responder(400, [
                    'success' => false,
                    'message' => 'Los goles no pueden ser negativos',
                ]);
            }

            if ($estado === 'jugado' && ($golesLocal === null || $golesVisitante === null)) {
                $this->responder(400, [
                    'success' => false,
                    'message' => 'Un partido jugado debe tener goles local y visitante',
                ]);
            }

            if ($estado !== 'jugado') {
                $golesLocal = null;
                $golesVisitante = null;
            }

            if ($idLiga === 0 || $fecha === '' || $lugar === '' || $idLocal === 0 || $idVisitante === 0) {
                $this->responder(400, [
                    'success' => false,
                    'message' => 'Faltan campos obligatorios: id_liga, fecha, lugar, id_equipo_local, id_equipo_visitante',
                ]);
            }

            if ($idLocal === $idVisitante) {
                $this->responder(400, [
                    'success' => false,
                    'message' => 'Equipo local y visitante no pueden ser el mismo',
                ]);
            }

            $modelo = new PartidosModel();

            try {
                $tipo_ronda = $this->prepararTipoRonda($modelo, $idLiga, $tipo_ronda);
            } catch (RuntimeException $ex) {
                $this->responder(400, [
                    'success' => false,
                    'message' => $ex->getMessage()
                ]);
            }

            if (!$modelo->existeLiga($idLiga)) {
                $this->responder(404, [
                    'success' => false,
                    'message' => 'Liga no encontrada',
                ]);
            }

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

            if (!$modelo->equiposPertenecenALiga($idLiga, $idLocal, $idVisitante)) {
                $this->responder(409, [
                    'success' => false,
                    'message' => 'Uno o ambos equipos no pertenecen a la liga seleccionada',
                ]);
            }

            if ($modelo->existeDuplicado($idLiga, $tipo_ronda, $idLocal, $idVisitante)) {
                $this->responder(409, [
                    'success' => false,
                    'message' => 'Ya existe un partido con esos equipos en esta ronda',
                ]);
            }

            if ($modelo->existeConflictoHorario($idLiga, $fecha, $idLocal, $idVisitante)) {
                $this->responder(409, [
                    'success' => false,
                    'message' => 'Uno de los equipos ya tiene un partido en esa fecha y hora',
                ]);
            }

            $idNuevo = $modelo->insertar([
                'id_liga'             => $idLiga,
                'tipo_ronda'          => $tipo_ronda,
                'fecha'               => $fecha,
                'lugar'               => $lugar,
                'arbitro'             => $arbitro !== '' ? $arbitro : null,
                'id_equipo_local'     => $idLocal,
                'id_equipo_visitante' => $idVisitante,
                'goles_local'         => $golesLocal,
                'goles_visitante'     => $golesVisitante,
                'estado'              => $estado,
            ]);

            if ($estado === 'jugado') {
                $this->regenerarClasificacion($idLiga);
            }

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

            try {
                $fecha = $this->prepararFecha($entrada);
            } catch (\RuntimeException $ex) {
                $this->responder(400, ['success' => false, 'message' => $ex->getMessage()]);
            }

            $idLiga       = isset($entrada['id_liga'])             ? (int) $entrada['id_liga']             : 0;
            $tipo_ronda      = $this->limpiarTexto($entrada['tipo_ronda']      ?? '');
            $lugar        = $this->limpiarTexto($entrada['lugar']        ?? '');
            $arbitro      = $this->limpiarTexto($entrada['arbitro']      ?? '');
            $idLocal      = isset($entrada['id_equipo_local'])      ? (int) $entrada['id_equipo_local']      : 0;
            $idVisitante  = isset($entrada['id_equipo_visitante'])  ? (int) $entrada['id_equipo_visitante']  : 0;
            $golesLocal   = isset($entrada['goles_local'])      && $entrada['goles_local']     !== ''
                ? (int) $entrada['goles_local']     : null;
            $golesVisitante = isset($entrada['goles_visitante']) && $entrada['goles_visitante'] !== ''
                ? (int) $entrada['goles_visitante'] : null;
            $estado       = $this->limpiarTexto($entrada['estado'] ?? '');

            if (
                ($golesLocal !== null && $golesLocal < 0) ||
                ($golesVisitante !== null && $golesVisitante < 0)
            ) {
                $this->responder(400, [
                    'success' => false,
                    'message' => 'Los goles no pueden ser negativos',
                ]);
            }

            if ($estado === 'jugado' && ($golesLocal === null || $golesVisitante === null)) {
                $this->responder(400, [
                    'success' => false,
                    'message' => 'Un partido jugado debe tener goles local y visitante',
                ]);
            }

            if ($estado !== 'jugado') {
                $golesLocal = null;
                $golesVisitante = null;
            }

            if ($idLiga === 0 || $fecha === '' || $lugar === '' || $idLocal === 0 || $idVisitante === 0) {
                $this->responder(400, [
                    'success' => false,
                    'message' => 'Faltan campos obligatorios: id_liga, fecha, lugar, id_equipo_local, id_equipo_visitante',
                ]);
            }

            if ($estado === '' || !$this->validarEstado($estado)) {
                $this->responder(400, [
                    'success' => false,
                    'message' => 'Estado de partido no permitido. Valores válidos: pendiente, programado, jugado, cancelado',
                ]);
            }

            if ($idLocal === $idVisitante) {
                $this->responder(400, [
                    'success' => false,
                    'message' => 'Equipo local y visitante no pueden ser el mismo',
                ]);
            }

            if (!$modelo->existeLiga($idLiga)) {
                $this->responder(404, [
                    'success' => false,
                    'message' => 'Liga no encontrada',
                ]);
            }

            try {
                $tipo_ronda = $this->prepararTipoRonda($modelo, $idLiga, $tipo_ronda);
            } catch (RuntimeException $ex) {
                $this->responder(400, [
                    'success' => false,
                    'message' => $ex->getMessage()
                ]);
            }

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

            if (!$modelo->equiposPertenecenALiga($idLiga, $idLocal, $idVisitante)) {
                $this->responder(409, [
                    'success' => false,
                    'message' => 'Uno o ambos equipos no pertenecen a la liga seleccionada',
                ]);
            }

            if ($modelo->existeDuplicado($idLiga, $tipo_ronda, $idLocal, $idVisitante, $id)) {
                $this->responder(409, [
                    'success' => false,
                    'message' => 'Ya existe un partido con esos equipos en esta tipo_ronda',
                ]);
            }

            if ($modelo->existeConflictoHorario($idLiga, $fecha, $idLocal, $idVisitante, $id)) {
                $this->responder(409, [
                    'success' => false,
                    'message' => 'Uno de los equipos ya tiene un partido en esa fecha y hora',
                ]);
            }

            $modelo->modificar($id, [
                'id_liga'             => $idLiga,
                'tipo_ronda'          => $tipo_ronda,
                'fecha'               => $fecha,
                'lugar'               => $lugar,
                'arbitro'             => $arbitro !== '' ? $arbitro : null,
                'id_equipo_local'     => $idLocal,
                'id_equipo_visitante' => $idVisitante,
                'goles_local'         => $golesLocal,
                'goles_visitante'     => $golesVisitante,
                'estado'              => $estado,
            ]);

            $idLigaAnterior = (int)$partido['id_liga'];

            $this->regenerarClasificacion($idLigaAnterior);

            if ($idLigaAnterior !== $idLiga) {
                $this->regenerarClasificacion($idLiga);
            }

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

            $this->regenerarClasificacion((int)$partido['id_liga']);

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

            $this->regenerarClasificacion((int)$partido['id_liga']);

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
