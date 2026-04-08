<?php

declare(strict_types=1);

/**
 * ============================================================================
 *  EquiposController
 * ============================================================================
 *  Enlazado a tu EquiposModel REAL:
 *   - getAll()
 *   - getById(int $id)
 *   - getByClub(string $club)
 *   - existsByKey(string $club, string $categoria, string $temporada)
 *   - insert(string $club, string $categoria, string $temporada, ?string $descripcion)
 *   - update(int $id, string $club, string $categoria, string $temporada, ?string $descripcion)
 *   - delete(int $id)
 *   - search(string $club='', string $categoria='', string $temporada='')
 *   - getByCategoria(string $categoria)
 *   - getByTemporada(string $temporada)
 *
 *  Nota importante:
 *   - Tu BD tiene UNIQUE(club, categoria). Por eso validamos duplicados por club+categoria.
 * ============================================================================
 */

require_once __DIR__ . '/../core/Autenticacion.php';
require_once __DIR__ . '/../model/equiposModel.php';

class EquiposController
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

    /**
     * Validar duplicado por UNIQUE (club, categoria).
     * Devuelve true si YA existe un equipo con ese club+categoria.
     * Si pasas $idIgnorar, ignora ese id (útil para updates).
     */
    private function existePorClubCategoria(EquiposModel $modelo, string $club, string $categoria, int $idIgnorar = 0): bool
    {
        // getByClub es exacto, perfecto para esto
        $equiposClub = $modelo->getByClub($club);

        foreach ($equiposClub as $e) {
            $idEquipo = (int)($e['id_equipo'] ?? 0);
            $catEquipo = (string)($e['categoria'] ?? '');

            if ($idIgnorar > 0 && $idEquipo === $idIgnorar) {
                continue;
            }

            if ($catEquipo === $categoria) {
                return true;
            }
        }

        return false;
    }

    // =========================================================================
    // SELECCIONAR (GET /api/equipos)
    // Filtros opcionales:
    //  - ?club=...
    //  - ?categoria=...  (o ?categ=...)
    //  - ?temporada=...  (o ?temp=...)
    // =========================================================================
    public function seleccionar(array $entrada = []): void
    {
        try {
            $club      = $this->limpiarTexto($entrada['club'] ?? '');
            $categoria = $this->limpiarTexto($entrada['categoria'] ?? ($entrada['categ'] ?? ''));

            $modelo = new EquiposModel();

            if ($club !== '' || $categoria !== '') {
                $datos = $modelo->search($club, $categoria);
            } else {
                $datos = $modelo->getAll();
            }

            // Adjuntar ligas_ids a cada equipo
            foreach ($datos as &$equipo) {
                if (isset($equipo['id_equipo'])) {
                    $equipo['ligas_ids'] = $modelo->getLigasByEquipo((int)$equipo['id_equipo']);
                }
            }
            unset($equipo);

            $this->responder(200, ['success' => true, 'data' => $datos]);
        } catch (Throwable $e) {
            $this->responder(500, ['success' => false, 'message' => $e->getMessage()]);
        }
    }

    // =========================================================================
    // STAFF (GET /api/staff/equipos)
    // =========================================================================
    public function seleccionarStaff(array $entrada = []): void
    {
        try {
            Autenticacion::requerirRol([Autenticacion::ROL_STAFF]);
            $usuarioActual = Autenticacion::usuario();
            
            require_once __DIR__ . '/../model/entrenadoresModel.php';
            require_once __DIR__ . '/../model/entrenadorEquipoModel.php';
            require_once __DIR__ . '/../model/ligasModel.php';
            
            $entModel = new EntrenadoresModel();
            $entEqModel = new EntrenadorEquipoModel();
            $eqModel = new EquiposModel();

            $entrenador = $entModel->getByUserId((int)$usuarioActual['id_usuario']);

            if (!$entrenador) {
                $this->responder(200, ['success' => true, 'data' => []]);
            }

            $misEquipos = $entEqModel->getByEntrenador((int)$entrenador['id_entrenador']);
            
            if (empty($misEquipos)) {
                $this->responder(200, ['success' => true, 'data' => []]);
            }

            $club      = $this->limpiarTexto($entrada['club'] ?? '');
            $categoria = $this->limpiarTexto($entrada['categoria'] ?? ($entrada['categ'] ?? ''));

            $misIdsMap = [];
            $misLigasFlatIds = [];
            foreach ($misEquipos as $me) {
                $misIdsMap[$me['id_equipo']] = true;
                if (!in_array($me['id_liga'], $misLigasFlatIds)) {
                    $misLigasFlatIds[] = $me['id_liga'];
                }
            }

            if ($club !== '' || $categoria !== '') {
                $datos = $eqModel->search($club, $categoria);
            } else {
                $datos = $eqModel->getAll();
            }

            $datos = array_filter($datos, fn($eq) => isset($misIdsMap[$eq['id_equipo']]));
            $datos = array_values($datos);

            foreach ($datos as &$equipo) {
                $susLigas = [];
                foreach ($misEquipos as $me) {
                    if ($me['id_equipo'] === $equipo['id_equipo']) {
                        $susLigas[] = $me['id_liga'];
                    }
                }
                $equipo['ligas_ids'] = $susLigas;
            }
            unset($equipo);
            
            $ligasModel = new LigasModel();
            $ligasAssoc = [];
            foreach ($misLigasFlatIds as $lid) {
                $ligaRaw = $ligasModel->getById($lid);
                if ($ligaRaw) {
                    $ligasAssoc[] = $ligaRaw;
                }
            }

            $this->responder(200, ['success' => true, 'data' => $datos, 'ligas_staff' => $ligasAssoc]);
        } catch (Throwable $e) {
            $this->responder(500, ['success' => false, 'message' => $e->getMessage()]);
        }
    }

    // =========================================================================
    // LOCALIZAR (GET /api/equipos/{id})
    // =========================================================================
    public function localizar(int $id): void
    {
        try {
            if ($id <= 0) {
                $this->responder(400, ['success' => false, 'message' => 'ID inválido']);
            }

            $modelo = new EquiposModel();
            $equipo = $modelo->getById($id);

            if (!$equipo) {
                $this->responder(404, ['success' => false, 'message' => 'Equipo no encontrado']);
            }

            $equipo['ligas_ids'] = $modelo->getLigasByEquipo((int)$id);

            $this->responder(200, ['success' => true, 'data' => $equipo]);
        } catch (Throwable $e) {
            $this->responder(500, ['success' => false, 'message' => $e->getMessage()]);
        }
    }

    // =========================================================================
    // INSERTAR (POST /api/equipos)
    // Solo ADMIN (por ahora)
    // Body:
    //  - club, categoria, temporada
    //  - descripcion (opcional)
    // =========================================================================
    public function insertar(array $entrada): void
    {
        try {
            Autenticacion::requerirRol([Autenticacion::ROL_ADMIN]);

            $club      = $this->limpiarTexto($entrada['club'] ?? '');
            $categoria = $this->limpiarTexto($entrada['categoria'] ?? ($entrada['categ'] ?? ''));
            $descripcion = array_key_exists('descripcion', $entrada) ? $this->limpiarTexto($entrada['descripcion']) : null;
            $ligas = $entrada['ligas'] ?? [];

            if ($club === '' || $categoria === '') {
                $this->responder(400, [
                    'success' => false,
                    'message' => 'Faltan campos obligatorios: club, categoria'
                ]);
            }

            $modelo = new EquiposModel();

            // Tu BD impide duplicados por (club,categoria)
            if ($this->existePorClubCategoria($modelo, $club, $categoria)) {
                $this->responder(409, [
                    'success' => false,
                    'message' => 'Ya existe un equipo con el mismo club y categoría'
                ]);
            }

            $idNuevo = $modelo->insert($club, $categoria, $descripcion);

            // Sincronizar ligas en equipo_liga
            if (is_array($ligas)) {
                $modelo->syncLigas($idNuevo, $ligas);
            }

            $equipo = $modelo->getById((int)$idNuevo);
            $equipo['ligas_ids'] = $modelo->getLigasByEquipo((int)$idNuevo);

            $this->responder(201, [
                'success' => true,
                'message' => 'Equipo creado exitosamente',
                'data' => $equipo
            ]);
        } catch (Throwable $e) {
            // Si por lo que sea se cuela duplicado, la BD puede lanzar excepción: lo devolvemos claro.
            $this->responder(500, ['success' => false, 'message' => $e->getMessage()]);
        }
    }

    // =========================================================================
    // MODIFICAR (PUT /api/equipos/{id})
    // Solo ADMIN (por ahora)
    // =========================================================================
    public function modificar(int $id, array $entrada): void
    {
        try {
            Autenticacion::requerirRol([Autenticacion::ROL_ADMIN]);

            if ($id <= 0) {
                $this->responder(400, ['success' => false, 'message' => 'ID inválido']);
            }

            $club      = $this->limpiarTexto($entrada['club'] ?? '');
            $categoria = $this->limpiarTexto($entrada['categoria'] ?? ($entrada['categ'] ?? ''));
            $descripcion = array_key_exists('descripcion', $entrada) ? $this->limpiarTexto($entrada['descripcion']) : null;
            $ligas = $entrada['ligas'] ?? [];

            if ($club === '' || $categoria === '') {
                $this->responder(400, [
                    'success' => false,
                    'message' => 'Faltan campos obligatorios: club, categoria'
                ]);
            }

            $modelo = new EquiposModel();
            $existente = $modelo->getById($id);

            if (!$existente) {
                $this->responder(404, ['success' => false, 'message' => 'Equipo no encontrado']);
            }

            // Si cambias club/categoria, respeta UNIQUE (club,categoria)
            // Ignoramos el propio id para no autocolisionar.
            if ($this->existePorClubCategoria($modelo, $club, $categoria, $id)) {
                $this->responder(409, [
                    'success' => false,
                    'message' => 'No se puede actualizar: ya existe otro equipo con el mismo club y categoría'
                ]);
            }

            $modelo->update($id, $club, $categoria, $descripcion);

            // Sincronizar ligas en equipo_liga
            if (is_array($ligas)) {
                $modelo->syncLigas($id, $ligas);
            }

            $actualizado = $modelo->getById($id);
            $actualizado['ligas_ids'] = $modelo->getLigasByEquipo($id);

            $this->responder(200, [
                'success' => true,
                'message' => 'Equipo actualizado correctamente',
                'data' => $actualizado
            ]);
        } catch (Throwable $e) {
            $this->responder(500, ['success' => false, 'message' => $e->getMessage()]);
        }
    }

    // =========================================================================
    // ELIMINAR (DELETE /api/equipos/{id})
    // Solo ADMIN (por ahora)
    // =========================================================================
    public function eliminar(int $id): void
    {
        try {
            Autenticacion::requerirRol([Autenticacion::ROL_ADMIN]);

            if ($id <= 0) {
                $this->responder(400, ['success' => false, 'message' => 'ID inválido']);
            }

            $modelo = new EquiposModel();
            $existente = $modelo->getById($id);

            if (!$existente) {
                $this->responder(404, ['success' => false, 'message' => 'Equipo no encontrado']);
            }

            $filas = $modelo->delete($id);

            $this->responder(200, [
                'success' => true,
                'message' => 'Equipo eliminado correctamente',
                'filas_afectadas' => $filas
            ]);
        } catch (Throwable $e) {
            $this->responder(500, ['success' => false, 'message' => $e->getMessage()]);
        }
    }

    // =========================================================================
    // SUBIR ESCUDO (POST /api/equipos/{id}/escudo)
    // =========================================================================
    public function subirEscudo(int $id): void
    {
        try {
            Autenticacion::requerirRol([Autenticacion::ROL_ADMIN, Autenticacion::ROL_STAFF]);

            $modelo = new EquiposModel();
            $equipo = $modelo->getById($id);

            if (!$equipo) {
                $this->responder(404, ['success' => false, 'message' => 'Equipo no encontrado']);
            }

            if ($modelo->tieneEscudo($id)) {
                $this->responder(409, [
                    'success' => false,
                    'message' => 'Este equipo ya tiene escudo asignado. No se puede modificar.'
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

            $uploadDir = __DIR__ . '/../../public/uploads/equipos/' . $id;
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $filename = 'escudo.' . $ext;
            $destPath = $uploadDir . '/' . $filename;
            $dbPath = '/public/uploads/equipos/' . $id . '/' . $filename;

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
