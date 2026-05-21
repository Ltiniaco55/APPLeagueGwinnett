<?php

declare(strict_types=1);

require_once __DIR__ . '/../core/Autenticacion.php';
require_once __DIR__ . '/../model/ligasModel.php';
require_once __DIR__ . '/../model/clasificacionesModel.php';

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

    private function asegurarClasificacionSiEnCurso(int $idLiga, string $estadoLiga): void
    {
        if ($estadoLiga !== 'EN_CURSO') {
            return;
        }

        $clasificacionesModel = new ClasificacionesModel();
        $clasificacionesModel->asegurarClasificacionLiga($idLiga);
    }

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

            $liga = $modelo->getByKey($nom, $temp, $categ);

            if (!$liga) {
                $this->responder(404, [
                    'success' => false,
                    'message' => 'No existe una liga con ese nom, temp y categ'
                ]);
            }

            $datos = $modelo->getAllFiltered($nom, $temp, $categ);

            $this->responder(200, [
                'success' => true,
                'data' => $datos
            ]);
        } catch (Throwable $e) {
            $this->responder(500, ['success' => false, 'message' => $e->getMessage()]);
        }
    }

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

            if ($modelo->getByKey($nom, $temp, $categ)) {
                $this->responder(409, [
                    'success' => false,
                    'message' => 'Ya existe una liga con el mismo nom, temp y categ'
                ]);
            }

            $idNuevo = $modelo->insert($nom, $temp, $categ, $descripcion, $estadoLiga, $formatoLiga);

            $this->asegurarClasificacionSiEnCurso($idNuevo, $estadoLiga);

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

            $escudoPath = $liga['escudo'] ?? null;

            $modelo->deleteClasificacionByLiga($idLiga);
            $modelo->deletePartidosByLiga($idLiga);
            $modelo->deleteEquiposLigaByLiga($idLiga);

            $filas = $modelo->deleteByKey($nom, $temp, $categ);

            if ($filas > 0 && $escudoPath) {
                $carpetaEscudo = __DIR__ . '/../../public/uploads/ligas/' . $idLiga;

                if (is_dir($carpetaEscudo)) {
                    $archivos = glob($carpetaEscudo . '/*');

                    foreach ($archivos as $archivo) {
                        if (is_file($archivo)) {
                            @unlink($archivo);
                        }
                    }

                    @rmdir($carpetaEscudo);
                }
            }

            $this->responder(200, [
                'success' => true,
                'message' => 'Liga eliminada correctamente',
                'filas_afectadas' => $filas
            ]);
        } catch (Throwable $e) {
            $this->responder(500, ['success' => false, 'message' => $e->getMessage()]);
        }
    }

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

            $ligaActual = $modelo->getByKey($nomActual, $tempActual, $categActual);

            if (!$ligaActual) {
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

            $this->asegurarClasificacionSiEnCurso($ligaActual['id_liga'], $estadoLiga);

            $this->responder(200, [
                'success' => true,
                'message' => 'Liga modificada correctamente',
                'filas_afectadas' => $filas
            ]);
        } catch (Throwable $e) {
            $this->responder(500, ['success' => false, 'message' => $e->getMessage()]);
        }
    }

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
