<?php

declare(strict_types=1);

/**
 * ============================================================================
 *  LigasController
 * ============================================================================
 *  Compatible con tu LigasModel actual:
 *   - getAllFiltered($nom, $temp, $categ)   (usa LIKE)
 *   - existsByKey($nom, $temp, $categ)      (exacto)
 *   - insert($nom, $temp, $categ [, $descripcion])
 *   - deleteByKey($nom, $temp, $categ)
 *
 *  NOTA:
 *   - No hay update en el Model, por eso modificar() queda "pendiente".
 * ============================================================================
 */

require_once __DIR__ . '/../core/Autenticacion.php';
require_once __DIR__ . '/../model/ligasModel.php';

class LigasController
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

    // =========================================================================
    // SELECCIONAR (GET /api/ligas?nom=&temp=&categ=)
    // =========================================================================
    public function seleccionar(array $entrada = []): void
    {
        try {
            $nom   = $this->limpiarTexto($entrada['nom'] ?? '');
            $temp  = $this->limpiarTexto($entrada['temp'] ?? '');
            $categ = $this->limpiarTexto($entrada['categ'] ?? '');

            $modelo = new LigasModel();
            $datos = $modelo->getAllFiltered($nom, $temp, $categ);

            $this->responder(200, ['success' => true, 'data' => $datos]);
        } catch (Throwable $e) {
            $this->responder(500, ['success' => false, 'message' => $e->getMessage()]);
        }
    }

    // =========================================================================
    // LOCALIZAR (GET /api/ligas/localizar?nom=...&temp=...&categ=...)
    //
    // Como tu Model NO tiene getByKey exacto, hacemos:
    //  - existsByKey() para confirmar existencia exacta
    //  - luego usamos getAllFiltered() con valores exactos (PERO OJO: usa LIKE)
    //    En la práctica debería devolver 1 si no hay datos raros.
    // =========================================================================
    public function localizar(array $entrada): void
    {
        try {
            $nom   = $this->limpiarTexto($entrada['nom'] ?? '');
            $temp  = $this->limpiarTexto($entrada['temp'] ?? '');
            $categ = $this->limpiarTexto($entrada['categ'] ?? '');

            if ($nom === '' || $temp === '' || $categ === '') {
                $this->responder(400, [
                    'success' => false,
                    'message' => 'Faltan campos obligatorios: nom, temp, categ'
                ]);
            }

            $modelo = new LigasModel();

            // Existencia exacta
            $liga = $modelo->getByKey($nom, $temp, $categ);

            if (!$liga) {
                $this->responder(404, [
                    'success' => false,
                    'message' => 'No existe una liga con ese nom, temp y categ'
                ]);
            }

            // Traer datos (probablemente 1 registro)
            $datos = $modelo->getAllFiltered($nom, $temp, $categ);

            $this->responder(200, [
                'success' => true,
                'data' => $datos
            ]);
        } catch (Throwable $e) {
            $this->responder(500, ['success' => false, 'message' => $e->getMessage()]);
        }
    }

    // =========================================================================
    // INSERTAR (POST /api/ligas)
    // Body esperado:
    //  - nom, temp, categ
    //  - descripcion (opcional)
    // Solo ADMIN
    // =========================================================================
    public function insertar(array $entrada): void
    {
        try {
            Autenticacion::requerirRol([Autenticacion::ROL_ADMIN]);

            $nom   = $this->limpiarTexto($entrada['nom'] ?? '');
            $temp  = $this->limpiarTexto($entrada['temp'] ?? '');
            $categ = $this->limpiarTexto($entrada['categ'] ?? '');
            $descripcion = $this->limpiarTexto($entrada['descripcion'] ?? '');

            $estadoLiga = strtoupper($this->limpiarTexto($entrada['estado_liga'] ?? 'PROXIMAMENTE'));

            if (!in_array($estadoLiga, ['EN_CURSO', 'PROXIMAMENTE'], true)) {
                $estadoLiga = 'PROXIMAMENTE';
            }

            $formatoLiga = strtoupper($this->limpiarTexto($entrada['formato_liga'] ?? 'JORNADAS'));

            if (!in_array($formatoLiga, ['JORNADAS', 'ELIMINATORIA', 'AMISTOSO'], true)) {
                $formatoLiga = 'JORNADAS';
            }

            if ($nom === '' || $temp === '' || $categ === '') {
                $this->responder(400, [
                    'success' => false,
                    'message' => 'Faltan campos obligatorios: nom, temp, categ'
                ]);
            }

            $modelo = new LigasModel();

            // Regla: no duplicados por (nom,temp,categ)
            if ($modelo->getByKey($nom, $temp, $categ)) {
                $this->responder(409, [
                    'success' => false,
                    'message' => 'Ya existe una liga con el mismo nom, temp y categ'
                ]);
            }

            // Tu insert acepta descripcion como 4º argumento opcional
            $idNuevo = $modelo->insert($nom, $temp, $categ, $descripcion, $estadoLiga, $formatoLiga);

            $this->responder(201, [
                'success' => true,
                'message' => 'Liga creada exitosamente',
                'data' => [
                    'id_liga' => $idNuevo,
                    'nom' => $nom,
                    'temp' => $temp,
                    'categ' => $categ,
                    'descripcion' => $descripcion,
                    'estado_liga' => $estadoLiga,
                    'formato_liga' => $formatoLiga
                ]
            ]);
        } catch (Throwable $e) {
            $this->responder(500, ['success' => false, 'message' => $e->getMessage()]);
        }
    }

    // =========================================================================
    // ELIMINAR (DELETE /api/ligas)
    // Body o query esperado:
    //  - nom, temp, categ
    // Solo ADMIN
    // =========================================================================
    public function eliminar(array $entrada): void
    {
        try {
            Autenticacion::requerirRol([Autenticacion::ROL_ADMIN]);

            $nom   = $this->limpiarTexto($entrada['nom'] ?? '');
            $temp  = $this->limpiarTexto($entrada['temp'] ?? '');
            $categ = $this->limpiarTexto($entrada['categ'] ?? '');

            if ($nom === '' || $temp === '' || $categ === '') {
                $this->responder(400, [
                    'success' => false,
                    'message' => 'Faltan campos obligatorios: nom, temp, categ'
                ]);
            }

            $modelo = new LigasModel();

            $liga = $modelo->getByKey($nom, $temp, $categ);

            if (!$liga) {
                $this->responder(404, [
                    'success' => false,
                    'message' => 'No existe una liga con ese nom, temp y categ'
                ]);
            }

            $idLiga = (int)$liga['id_liga'];

            $modelo->deleteClasificacionByLiga($idLiga);
            $modelo->deletePartidosByLiga($idLiga);
            $modelo->deleteEquiposLigaByLiga($idLiga);

            $filas = $modelo->deleteByKey($nom, $temp, $categ);

            $this->responder(200, [
                'success' => true,
                'message' => 'Liga eliminada correctamente',
                'filas_afectadas' => $filas
            ]);
        } catch (Throwable $e) {
            $this->responder(500, ['success' => false, 'message' => $e->getMessage()]);
        }
    }

    // =========================================================================
    // MODIFICAR (PUT /api/ligas)
    // AÚN NO SE PUEDE, porque tu LigasModel no tiene update.
    // Aquí mantenemos tu estilo: devolvemos error claro.
    // =========================================================================
    public function modificar(array $entrada): void
    {
        try {
            Autenticacion::requerirRol([Autenticacion::ROL_ADMIN]);

            $nomActual   = $this->limpiarTexto($entrada['nom_actual'] ?? '');
            $tempActual  = $this->limpiarTexto($entrada['temp_actual'] ?? '');
            $categActual = $this->limpiarTexto($entrada['categ_actual'] ?? '');

            $nomNuevo   = $this->limpiarTexto($entrada['nom'] ?? '');
            $tempNuevo  = $this->limpiarTexto($entrada['temp'] ?? '');
            $categNuevo = $this->limpiarTexto($entrada['categ'] ?? '');
            $descripcion = $this->limpiarTexto($entrada['descripcion'] ?? '');

            $estadoLiga = strtoupper($this->limpiarTexto($entrada['estado_liga'] ?? 'PROXIMAMENTE'));

            if (!in_array($estadoLiga, ['EN_CURSO', 'PROXIMAMENTE'], true)) {
                $estadoLiga = 'PROXIMAMENTE';
            }

            $formatoLiga = strtoupper($this->limpiarTexto($entrada['formato_liga'] ?? 'JORNADAS'));

            if (!in_array($formatoLiga, ['JORNADAS', 'ELIMINATORIA', 'AMISTOSO'], true)) {
                $formatoLiga = 'JORNADAS';
            }

            if (
                $nomActual === '' || $tempActual === '' || $categActual === '' ||
                $nomNuevo === '' || $tempNuevo === '' || $categNuevo === ''
            ) {
                $this->responder(400, [
                    'success' => false,
                    'message' => 'Faltan campos obligatorios para modificar la liga'
                ]);
            }

            $modelo = new LigasModel();

            if (!$modelo->getByKey($nomActual, $tempActual, $categActual)) {
                $this->responder(404, [
                    'success' => false,
                    'message' => 'La liga original no existe'
                ]);
            }

            $filas = $modelo->updateByKey(
                $nomActual,
                $tempActual,
                $categActual,
                $nomNuevo,
                $tempNuevo,
                $categNuevo,
                $descripcion,
                $estadoLiga,
                $formatoLiga
            );

            $this->responder(200, [
                'success' => true,
                'message' => 'Liga modificada correctamente',
                'filas_afectadas' => $filas
            ]);
        } catch (Throwable $e) {
            $this->responder(500, ['success' => false, 'message' => $e->getMessage()]);
        }
    }

    // =========================================================================
    // SUBIR ESCUDO (POST /api/ligas/{id}/escudo)
    // =========================================================================
    public function subirEscudo(int $id): void
    {
        try {
            Autenticacion::requerirRol([Autenticacion::ROL_ADMIN, Autenticacion::ROL_STAFF]);

            $modelo = new LigasModel();
            $liga = $modelo->getById($id);

            if (!$liga) {
                $this->responder(404, ['success' => false, 'message' => 'Liga no encontrada']);
            }

            if ($modelo->tieneEscudo($id)) {
                $this->responder(409, [
                    'success' => false,
                    'message' => 'Esta liga ya tiene escudo asignado. No se puede modificar.'
                ]);
            }

            if (!isset($_FILES['escudo']) || $_FILES['escudo']['error'] !== UPLOAD_ERR_OK) {
                $this->responder(400, ['success' => false, 'message' => 'No se recibió ningún escudo válido']);
            }

            $file = $_FILES['escudo'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'webp'];

            if (!in_array($ext, $allowed)) {
                $this->responder(400, ['success' => false, 'message' => 'Formato no permitido. Usar: jpg, png, webp']);
            }

            $uploadDir = __DIR__ . '/../../public/uploads/ligas/' . $id;
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $filename = 'escudo.' . $ext;
            $destPath = $uploadDir . '/' . $filename;
            $dbPath = '/public/uploads/ligas/' . $id . '/' . $filename;

            if (!move_uploaded_file($file['tmp_name'], $destPath)) {
                $this->responder(500, ['success' => false, 'message' => 'Error al guardar el archivo']);
            }

            $filas = $modelo->guardarEscudoSiNoExiste($id, $dbPath);

            if (!$filas) {
                @unlink($destPath);
                $this->responder(409, [
                    'success' => false,
                    'message' => 'El escudo fue asignado por otro proceso. No se puede modificar.'
                ]);
            }

            $this->responder(200, [
                'success' => true,
                'message' => 'Escudo subido correctamente',
                'escudo_path' => $dbPath
            ]);
        } catch (Throwable $e) {
            $this->responder(500, ['success' => false, 'message' => $e->getMessage()]);
        }
    }
}
