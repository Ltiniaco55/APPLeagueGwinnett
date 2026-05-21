<?php

declare(strict_types=1);


require_once __DIR__ . '/../core/Autenticacion.php';
require_once __DIR__ . '/../model/equiposModel.php';
require_once __DIR__ . '/../model/usuariosModel.php';
require_once __DIR__ . '/../model/entrenadoresModel.php';
require_once __DIR__ . '/../model/entrenadorEquipoModel.php';
require_once __DIR__ . '/../model/equipoLigaModel.php';
require_once __DIR__ . '/../model/partidosModel.php';
require_once __DIR__ . '/../model/clasificacionesModel.php';

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

    private function regenerarClasificacion(int $idLiga): void
    {
        $clasificacionesModel = new ClasificacionesModel();
        $clasificacionesModel->regenerarLiga($idLiga);
    }

    private function asegurarClasificaciones(array $idsLigas): void
    {
        $clasificacionesModel = new ClasificacionesModel();

        foreach ($idsLigas as $idLiga) {
            $idLiga = (int)$idLiga;

            if ($idLiga > 0) {
                $clasificacionesModel->asegurarClasificacionLiga($idLiga);
            }
        }
    }

    private function existePorClubCategoria(EquiposModel $modelo, string $club, string $categoria, int $idIgnorar = 0): bool
    {

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

    public function seleccionarStaff(array $entrada = []): void
    {
        try {
            Autenticacion::requerirRol([
                Autenticacion::ROL_STAFF,
                Autenticacion::ROL_ADMIN
            ]);

            $usuarioActual = Autenticacion::usuario();

            require_once __DIR__ . '/../model/ligasModel.php';

            $entModel = new EntrenadoresModel();
            $entEqModel = new EntrenadorEquipoModel();
            $eqModel = new EquiposModel();

            $idUsuario = (int)($usuarioActual['id_usuario'] ?? $usuarioActual['id'] ?? 0);

            if ($idUsuario <= 0) {
                $this->responder(401, [
                    'success' => false,
                    'message' => 'Usuario no autenticado'
                ]);
            }

            $entrenador = $entModel->getByUserId($idUsuario);

            if (!$entrenador) {
                $this->responder(200, [
                    'success' => true,
                    'data' => [],
                    'ligas_staff' => []
                ]);
            }

            $misEquipos = $entEqModel->getByEntrenador((int)$entrenador['id_entrenador']);

            if (empty($misEquipos)) {
                $this->responder(200, [
                    'success' => true,
                    'data' => [],
                    'ligas_staff' => []
                ]);
            }

            $club = $this->limpiarTexto($entrada['club'] ?? '');
            $categoria = $this->limpiarTexto($entrada['categoria'] ?? ($entrada['categ'] ?? ''));

            $misIdsMap = [];
            $misLigasFlatIds = [];

            foreach ($misEquipos as $me) {
                $idEquipo = (int)($me['id_equipo'] ?? 0);
                $idLiga = (int)($me['id_liga'] ?? 0);

                if ($idEquipo > 0) {
                    $misIdsMap[$idEquipo] = true;
                }

                if ($idLiga > 0 && !in_array($idLiga, $misLigasFlatIds, true)) {
                    $misLigasFlatIds[] = $idLiga;
                }
            }

            if ($club !== '' || $categoria !== '') {
                $datos = $eqModel->search($club, $categoria);
            } else {
                $datos = $eqModel->getAll();
            }

            $datos = array_filter($datos, function ($eq) use ($misIdsMap) {
                return isset($misIdsMap[(int)($eq['id_equipo'] ?? 0)]);
            });

            $datos = array_values($datos);

            foreach ($datos as &$equipo) {
                $idEquipoActual = (int)($equipo['id_equipo'] ?? 0);
                $susLigas = [];

                foreach ($misEquipos as $me) {
                    if ((int)($me['id_equipo'] ?? 0) === $idEquipoActual) {
                        $susLigas[] = (int)($me['id_liga'] ?? 0);
                    }
                }

                $equipo['ligas_ids'] = array_values(array_filter(array_unique($susLigas)));
            }

            unset($equipo);

            $ligasModel = new LigasModel();
            $ligasAssoc = [];

            foreach ($misLigasFlatIds as $lid) {
                $ligaRaw = $ligasModel->getById((int)$lid);

                if ($ligaRaw) {
                    $ligasAssoc[] = $ligaRaw;
                }
            }

            $this->responder(200, [
                'success' => true,
                'data' => $datos,
                'ligas_staff' => $ligasAssoc
            ]);
        } catch (Throwable $e) {
            $this->responder(500, [
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }


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


            if ($this->existePorClubCategoria($modelo, $club, $categoria)) {
                $this->responder(409, [
                    'success' => false,
                    'message' => 'Ya existe un equipo con el mismo club y categoría'
                ]);
            }

            $idNuevo = $modelo->insert($club, $categoria, $descripcion);


            if (is_array($ligas)) {
                $modelo->syncLigas($idNuevo, $ligas);
            }

            if (is_array($ligas)) {
                $this->asegurarClasificaciones($ligas);
            }

            $equipo = $modelo->getById((int)$idNuevo);
            $equipo['ligas_ids'] = $modelo->getLigasByEquipo((int)$idNuevo);

            $this->responder(201, [
                'success' => true,
                'message' => 'Equipo creado exitosamente',
                'data' => $equipo
            ]);
        } catch (Throwable $e) {

            $this->responder(500, ['success' => false, 'message' => $e->getMessage()]);
        }
    }

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


            if ($this->existePorClubCategoria($modelo, $club, $categoria, $id)) {
                $this->responder(409, [
                    'success' => false,
                    'message' => 'No se puede actualizar: ya existe otro equipo con el mismo club y categoría'
                ]);
            }

            $modelo->update($id, $club, $categoria, $descripcion);

            $ligasAnteriores = $modelo->getLigasByEquipo($id);

            if (is_array($ligas)) {
                $modelo->syncLigas($id, $ligas);

                $ligasAfectadas = array_values(array_unique(array_merge(
                    array_map('intval', $ligasAnteriores),
                    array_map('intval', $ligas)
                )));

                $this->asegurarClasificaciones($ligasAfectadas);
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

    public function eliminar(int $id): void
    {
        try {
            Autenticacion::requerirRol([Autenticacion::ROL_ADMIN]);

            if ($id <= 0) {
                $this->responder(400, [
                    'success' => false,
                    'message' => 'ID inválido'
                ]);
            }

            $equiposModel = new EquiposModel();
            $usuariosModel = new UsuariosModel();
            $entrenadoresModel = new EntrenadoresModel();
            $entrenadorEquipoModel = new EntrenadorEquipoModel();

            $existente = $equiposModel->getById($id);

            if (!$existente) {
                $this->responder(404, [
                    'success' => false,
                    'message' => 'Equipo no encontrado'
                ]);
            }

            $ligasDelEquipo = $equiposModel->getLigasByEquipo($id);

            $relacionesEntrenadores = $entrenadorEquipoModel->getByEquipo($id);

            $idsEntrenadoresAfectados = [];

            foreach ($relacionesEntrenadores as $relacion) {
                if (isset($relacion['id_entrenador'])) {
                    $idsEntrenadoresAfectados[] = (int)$relacion['id_entrenador'];
                }
            }

            $idsEntrenadoresAfectados = array_values(array_unique($idsEntrenadoresAfectados));

            $filas = $equiposModel->delete($id);

            foreach ($ligasDelEquipo as $idLiga) {
                $this->regenerarClasificacion((int)$idLiga);
            }


            $usuariosConvertidos = [];
            $adminsSinEquipos = [];

            foreach ($idsEntrenadoresAfectados as $idEntrenador) {
                $relacionesRestantes = $entrenadorEquipoModel->getByEntrenador($idEntrenador);

                if (count($relacionesRestantes) > 0) {
                    continue;
                }

                $entrenador = $entrenadoresModel->getById($idEntrenador);

                if (!$entrenador || empty($entrenador['id_usuario'])) {
                    continue;
                }

                $idUsuario = (int)$entrenador['id_usuario'];
                $usuario = $usuariosModel->getById($idUsuario);

                if (!$usuario) {
                    $entrenadoresModel->delete($idEntrenador);
                    continue;
                }

                $rolUsuario = strtoupper((string)($usuario['rol'] ?? ''));

                $entrenadoresModel->delete($idEntrenador);
                $usuariosModel->updateEquipoStaff($idUsuario, null);

                if ($rolUsuario === Autenticacion::ROL_STAFF) {
                    $usuariosModel->updateRol($idUsuario, Autenticacion::ROL_USUARIO);

                    $usuariosConvertidos[] = [
                        'id_usuario' => $idUsuario,
                        'nombre' => trim(($usuario['nombre'] ?? '') . ' ' . ($usuario['apellido'] ?? ''))
                    ];
                }

                if ($rolUsuario === Autenticacion::ROL_ADMIN) {
                    $adminsSinEquipos[] = [
                        'id_usuario' => $idUsuario,
                        'nombre' => trim(($usuario['nombre'] ?? '') . ' ' . ($usuario['apellido'] ?? ''))
                    ];
                }
            }

            $this->responder(200, [
                'success' => true,
                'message' => 'Equipo eliminado correctamente',
                'filas_afectadas' => $filas,
                'staff_convertidos_a_usuario' => $usuariosConvertidos,
                'admins_sin_equipos_asociados' => $adminsSinEquipos
            ]);
        } catch (Throwable $e) {
            $this->responder(500, [
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    public function entrenadoresEquipo(int $id, array $entrada = []): void
    {
        try {
            Autenticacion::requerirRol([
                Autenticacion::ROL_ADMIN,
                Autenticacion::ROL_STAFF
            ]);

            if ($id <= 0) {
                $this->responder(400, [
                    'success' => false,
                    'message' => 'ID de equipo inválido'
                ]);
            }

            $idLiga = isset($entrada['id_liga']) ? (int)$entrada['id_liga'] : 0;

            if ($idLiga <= 0) {
                $this->responder(400, [
                    'success' => false,
                    'message' => 'ID de liga inválido'
                ]);
            }

            $modelo = new EntrenadorEquipoModel();

            $datos = $modelo->getEntrenadoresByEquipoLiga($id, $idLiga);

            $this->responder(200, [
                'success' => true,
                'data' => $datos
            ]);
        } catch (Throwable $e) {
            $this->responder(500, [
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

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
