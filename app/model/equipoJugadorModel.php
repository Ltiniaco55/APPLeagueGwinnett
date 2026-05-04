<?php

class EquipoJugadorModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function getDb(): PDO
    {
        return $this->db;
    }

    // =====================================================
    // INSERTAR RELACIÓN pendiente (STAFF → ALTA solicitada)
    // =====================================================
    public function insertarPendiente(
        int $id_jugador,
        int $id_equipo,
        int $id_liga,
        int $id_usuario_solicitante
    ): int {
        $stmt = $this->db->prepare(
            "INSERT INTO equipo_jugador
                (id_jugador, id_equipo, id_liga, dorsal, estado, accion_solicitada, id_usuario_solicitante)
             VALUES (?, ?, ?, NULL, 'PENDIENTE', 'ALTA', ?)"
        );
        $stmt->execute([$id_jugador, $id_equipo, $id_liga, $id_usuario_solicitante]);
        return (int) $this->db->lastInsertId();
    }

    // =====================================================
    // INSERTAR RELACIÓN directa (ADMIN → ALTA directa)
    // =====================================================
    public function insertarRelacion(
        int $id_jugador,
        int $id_equipo,
        int $id_liga,
        ?int $dorsal = null
    ): int {
        $stmt = $this->db->prepare(
            "INSERT INTO equipo_jugador (id_jugador, id_equipo, id_liga, dorsal, estado, accion_solicitada)
             VALUES (?, ?, ?, ?, 'ALTA', NULL)"
        );
        $stmt->execute([$id_jugador, $id_equipo, $id_liga, $dorsal]);
        return $stmt->rowCount();
    }

    // =====================================================
    // APROBAR ALTA (cambia PENDIENTE/ALTA → ALTA)
    // =====================================================
    public function aprobarAlta(int $id_relacion, int $id_admin): int
    {
        $stmt = $this->db->prepare(
            "UPDATE equipo_jugador
             SET estado = 'ALTA',
                 accion_solicitada = NULL,
                 id_admin_resolutor = ?,
                 fecha_resolucion = NOW()
             WHERE id = ?
               AND estado = 'PENDIENTE'
               AND accion_solicitada = 'ALTA'"
        );
        $stmt->execute([$id_admin, $id_relacion]);
        return $stmt->rowCount();
    }

    // =====================================================
    // RECHAZAR ALTA (elimina el pendiente de alta)
    // =====================================================
    public function rechazarAlta(int $id_relacion): int
    {
        $stmt = $this->db->prepare(
            "DELETE FROM equipo_jugador
             WHERE id = ?
               AND estado = 'PENDIENTE'
               AND accion_solicitada = 'ALTA'"
        );
        $stmt->execute([$id_relacion]);
        return $stmt->rowCount();
    }

    // =====================================================
    // SOLICITAR BAJA (equipo_jugador pasa a PENDIENTE/BAJA)
    // =====================================================
    public function solicitarBaja(int $id_relacion, int $id_usuario_solicitante): int
    {
        $stmt = $this->db->prepare(
            "UPDATE equipo_jugador
             SET estado = 'PENDIENTE',
                 accion_solicitada = 'BAJA',
                 id_usuario_solicitante = ?,
                 fecha_solicitud = NOW()
             WHERE id = ?
               AND estado = 'ALTA'
               AND accion_solicitada IS NULL"
        );
        $stmt->execute([$id_usuario_solicitante, $id_relacion]);
        return $stmt->rowCount();
    }

    // =====================================================
    // APROBAR BAJA (eliminar la relación)
    // =====================================================
    public function aprobarBaja(int $id_relacion): int
    {
        $stmt = $this->db->prepare(
            "DELETE FROM equipo_jugador
             WHERE id = ?
               AND estado = 'PENDIENTE'
               AND accion_solicitada = 'BAJA'"
        );
        $stmt->execute([$id_relacion]);
        return $stmt->rowCount();
    }

    // =====================================================
    // RECHAZAR BAJA (restaurar ALTA)
    // =====================================================
    public function rechazarBaja(int $id_relacion, int $id_admin): int
    {
        $stmt = $this->db->prepare(
            "UPDATE equipo_jugador
             SET estado = 'ALTA',
                 accion_solicitada = NULL,
                 id_admin_resolutor = ?,
                 fecha_resolucion = NOW()
             WHERE id = ?
               AND estado = 'PENDIENTE'
               AND accion_solicitada = 'BAJA'"
        );
        $stmt->execute([$id_admin, $id_relacion]);
        return $stmt->rowCount();
    }

    // =====================================================
    // OBTENER RELACIÓN POR ID (primary key)
    // =====================================================
    public function getById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT ej.*,
                    j.nombre, j.apellido, j.fecha_nacimiento, j.foto_path,
                    e.club AS nombre_equipo, e.categoria,
                    l.nombre_liga
             FROM equipo_jugador ej
             INNER JOIN jugador j ON ej.id_jugador = j.id_jugador
             INNER JOIN equipos e ON ej.id_equipo = e.id_equipo
             INNER JOIN ligas l ON ej.id_liga = l.id_liga
             WHERE ej.id = ?"
        );
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    // =====================================================
    // OBTENER RELACIÓN POR jugador+equipo+liga
    // =====================================================
    public function getRelacion(int $id_jugador, int $id_equipo, int $id_liga): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM equipo_jugador
             WHERE id_jugador = ? AND id_equipo = ? AND id_liga = ?"
        );
        $stmt->execute([$id_jugador, $id_equipo, $id_liga]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    // =====================================================
    // VERIFICAR SI EXISTE RELACIÓN (cualquier estado)
    // =====================================================
    public function existeRelacion(int $id_jugador, int $id_equipo, int $id_liga): bool
    {
        $stmt = $this->db->prepare(
            "SELECT 1 FROM equipo_jugador
             WHERE id_jugador = ? AND id_equipo = ? AND id_liga = ?"
        );
        $stmt->execute([$id_jugador, $id_equipo, $id_liga]);
        return (bool) $stmt->fetchColumn();
    }

    // =====================================================
    // VERIFICAR SI YA ESTÁ PENDIENTE (cualquier acción)
    // =====================================================
    public function estaPendiente(int $id_jugador, int $id_equipo, int $id_liga): bool
    {
        $stmt = $this->db->prepare(
            "SELECT 1 FROM equipo_jugador
             WHERE id_jugador = ? AND id_equipo = ? AND id_liga = ?
               AND estado = 'PENDIENTE'"
        );
        $stmt->execute([$id_jugador, $id_equipo, $id_liga]);
        return (bool) $stmt->fetchColumn();
    }

    // =====================================================
    // PLANTILLA COMPLETA CON JOIN (filtrada por staff o todos)
    // =====================================================
    public function getPlantillaConJugadores(
        int $id_equipo,
        int $id_liga,
        string $nombreFiltro = ''
    ): array {
        $sql = "SELECT ej.id, ej.id_jugador, ej.id_equipo, ej.id_liga, ej.dorsal,
                       ej.estado, ej.accion_solicitada, ej.id_usuario_solicitante,
                       ej.fecha_solicitud,
                       j.nombre, j.apellido, j.fecha_nacimiento, j.foto_path,
                       e.club AS nombre_equipo, e.categoria,
                       l.nombre_liga
                FROM equipo_jugador ej
                INNER JOIN jugador j ON ej.id_jugador = j.id_jugador
                INNER JOIN equipos e ON ej.id_equipo = e.id_equipo
                INNER JOIN ligas l ON ej.id_liga = l.id_liga
                WHERE ej.id_equipo = ? AND ej.id_liga = ?";
        $params = [$id_equipo, $id_liga];

        if ($nombreFiltro !== '') {
            $sql .= " AND (j.nombre LIKE ? OR j.apellido LIKE ?)";
            $params[] = "%$nombreFiltro%";
            $params[] = "%$nombreFiltro%";
        }

        $sql .= " ORDER BY ej.dorsal ASC, j.apellido ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // =====================================================
    // PLANTILLA STAFF: todos los equipos asignados al staff
    // =====================================================
    public function getPlantillaStaff(array $equipoIds, string $nombreFiltro = '', string $categoriaFiltro = ''): array
    {
        if (empty($equipoIds)) return [];

        $placeholders = implode(',', array_fill(0, count($equipoIds), '?'));
        $sql = "SELECT ej.id, ej.id_jugador, ej.id_equipo, ej.id_liga, ej.dorsal,
                       ej.estado, ej.accion_solicitada, ej.id_usuario_solicitante,
                       ej.fecha_solicitud,
                       j.nombre, j.apellido, j.fecha_nacimiento, j.foto_path,
                       e.club AS nombre_equipo, e.categoria,
                       l.nombre_liga
                FROM equipo_jugador ej
                INNER JOIN jugador j ON ej.id_jugador = j.id_jugador
                INNER JOIN equipos e ON ej.id_equipo = e.id_equipo
                INNER JOIN ligas l ON ej.id_liga = l.id_liga
                WHERE ej.id_equipo IN ($placeholders)";
        $params = $equipoIds;

        if ($nombreFiltro !== '') {
            $sql .= " AND (j.nombre LIKE ? OR j.apellido LIKE ?)";
            $params[] = "%$nombreFiltro%";
            $params[] = "%$nombreFiltro%";
        }

        if ($categoriaFiltro !== '') {
            $sql .= " AND e.categoria = ?";
            $params[] = $categoriaFiltro;
        }

        $sql .= " ORDER BY e.club ASC, ej.dorsal ASC, j.apellido ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // =====================================================
    // PENDIENTES STAFF: solo de sus equipos
    // =====================================================
    public function getPendientesStaff(array $equipoIds): array
    {
        if (empty($equipoIds)) return [];

        $placeholders = implode(',', array_fill(0, count($equipoIds), '?'));
        $stmt = $this->db->prepare(
            "SELECT ej.id, ej.id_jugador, ej.id_equipo, ej.id_liga, ej.dorsal,
                    ej.estado, ej.accion_solicitada, ej.id_usuario_solicitante,
                    ej.fecha_solicitud,
                    j.nombre, j.apellido, j.fecha_nacimiento, j.foto_path,
                    e.club AS nombre_equipo, e.categoria,
                    l.nombre_liga
             FROM equipo_jugador ej
             INNER JOIN jugador j ON ej.id_jugador = j.id_jugador
             INNER JOIN equipos e ON ej.id_equipo = e.id_equipo
             INNER JOIN ligas l ON ej.id_liga = l.id_liga
             WHERE ej.id_equipo IN ($placeholders)
               AND ej.estado = 'PENDIENTE'
             ORDER BY ej.fecha_solicitud DESC"
        );
        $stmt->execute($equipoIds);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // =====================================================
    // PENDIENTES ADMIN: todos del sistema
    // =====================================================
    public function getPendientesAdmin(
        string $accionFiltro = '',
        int $equipoFiltro = 0,
        string $categoriaFiltro = ''
    ): array {
        $sql = "SELECT ej.id, ej.id_jugador, ej.id_equipo, ej.id_liga, ej.dorsal,
                       ej.estado, ej.accion_solicitada, ej.id_usuario_solicitante,
                       ej.fecha_solicitud,
                       j.nombre, j.apellido, j.fecha_nacimiento, j.foto_path,
                       j.documento_identidad_path,
                       j.nombres_padres, j.email_padres, j.telefono_padres,
                       e.club AS nombre_equipo, e.categoria,
                       l.nombre_liga,
                       u.nombre  AS solicitante_nombre,
                       u.apellido AS solicitante_apellido
                FROM equipo_jugador ej
                INNER JOIN jugador j  ON ej.id_jugador = j.id_jugador
                INNER JOIN equipos e  ON ej.id_equipo  = e.id_equipo
                INNER JOIN ligas l    ON ej.id_liga    = l.id_liga
                LEFT  JOIN usuario u ON ej.id_usuario_solicitante = u.id_usuario
                WHERE ej.estado = 'PENDIENTE'";
        $params = [];

        if ($accionFiltro !== '') {
            $sql .= " AND ej.accion_solicitada = ?";
            $params[] = $accionFiltro;
        }

        if ($equipoFiltro > 0) {
            $sql .= " AND ej.id_equipo = ?";
            $params[] = $equipoFiltro;
        }

        if ($categoriaFiltro !== '') {
            $sql .= " AND e.categoria = ?";
            $params[] = $categoriaFiltro;
        }

        $sql .= " ORDER BY ej.fecha_solicitud DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // =====================================================
    // ACTUALIZAR DORSAL — SOLO SI ESTÁ VACÍO / NULL
    // =====================================================
    public function actualizarDorsal(int $id_jugador, int $id_equipo, int $id_liga, int $dorsal): int
    {
        // Validate dorsal uniqueness in same equipo+liga
        $chk = $this->db->prepare(
            "SELECT 1 FROM equipo_jugador
             WHERE id_equipo = ? AND id_liga = ? AND dorsal = ? AND id_jugador != ?"
        );
        $chk->execute([$id_equipo, $id_liga, $dorsal, $id_jugador]);
        if ($chk->fetchColumn()) {
            throw new \RuntimeException("El dorsal $dorsal ya está asignado en este equipo/liga");
        }

        $stmt = $this->db->prepare(
            "UPDATE equipo_jugador
             SET dorsal = ?
             WHERE id_jugador = ? AND id_equipo = ? AND id_liga = ?
               AND (dorsal IS NULL)"
        );
        $stmt->execute([$dorsal, $id_jugador, $id_equipo, $id_liga]);
        return $stmt->rowCount();
    }

    // =====================================================
    // ADMIN CORRIGE DORSAL (sin restricción de NULL)
    // =====================================================
    public function corregirDorsalAdmin(int $id_relacion, int $dorsal, int $id_equipo, int $id_liga, int $id_jugador): int
    {
        // Validate uniqueness
        $chk = $this->db->prepare(
            "SELECT 1 FROM equipo_jugador
             WHERE id_equipo = ? AND id_liga = ? AND dorsal = ? AND id != ?"
        );
        $chk->execute([$id_equipo, $id_liga, $dorsal, $id_relacion]);
        if ($chk->fetchColumn()) {
            throw new \RuntimeException("El dorsal $dorsal ya está asignado en este equipo/liga");
        }

        $stmt = $this->db->prepare(
            "UPDATE equipo_jugador SET dorsal = ? WHERE id = ?"
        );
        $stmt->execute([$dorsal, $id_relacion]);
        return $stmt->rowCount();
    }

    /**
     * ¿Ya tiene dorsal este jugador en este equipo/liga?
     */
    public function tieneDorsal(int $id_jugador, int $id_equipo, int $id_liga): bool
    {
        $stmt = $this->db->prepare(
            "SELECT dorsal FROM equipo_jugador
             WHERE id_jugador = ? AND id_equipo = ? AND id_liga = ?"
        );
        $stmt->execute([$id_jugador, $id_equipo, $id_liga]);
        $dorsal = $stmt->fetchColumn();
        return ($dorsal !== false && $dorsal !== null);
    }

    // =====================================================
    // ELIMINAR RELACIÓN (admin: baja directa)
    // =====================================================
    public function eliminarRelacion(int $id_jugador, int $id_equipo, int $id_liga): int
    {
        $stmt = $this->db->prepare(
            "DELETE FROM equipo_jugador
             WHERE id_jugador = ? AND id_equipo = ? AND id_liga = ?"
        );
        $stmt->execute([$id_jugador, $id_equipo, $id_liga]);
        return $stmt->rowCount();
    }

    // =====================================================
    // DETALLE ENRIQUECIDO DE JUGADOR EN PLANTILLA
    // =====================================================
    public function getDetalleJugadorPlantilla(int $id_jugador, int $id_equipo, int $id_liga): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT ej.id, ej.id_jugador, ej.id_equipo, ej.id_liga, ej.dorsal,
                    ej.estado, ej.accion_solicitada, ej.id_usuario_solicitante,
                    ej.fecha_solicitud,
                    j.nombre, j.apellido, j.fecha_nacimiento, j.foto_path,
                    e.club AS nombre_equipo, e.categoria,
                    l.nombre_liga
             FROM equipo_jugador ej
             INNER JOIN jugador j ON ej.id_jugador = j.id_jugador
             INNER JOIN equipos e ON ej.id_equipo = e.id_equipo
             INNER JOIN ligas l ON ej.id_liga = l.id_liga
             WHERE ej.id_jugador = ? AND ej.id_equipo = ? AND ej.id_liga = ?"
        );
        $stmt->execute([$id_jugador, $id_equipo, $id_liga]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    // =====================================================
    // APROBAR EN LOTE — todo o nada
    // =====================================================
    public function aprobarLote(array $ids, int $id_admin): int
    {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $params = array_merge([$id_admin], $ids);
        $stmt = $this->db->prepare(
            "UPDATE equipo_jugador
             SET estado = 'ALTA',
                 accion_solicitada = NULL,
                 id_admin_resolutor = ?,
                 fecha_resolucion = NOW()
             WHERE id IN ($placeholders)
               AND estado = 'PENDIENTE'
               AND accion_solicitada = 'ALTA'"
        );
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    // Legacy compat — still used for admin direct inserts
    public function getByEquipoConJugadores(int $id_equipo, int $id_liga): array
    {
        return $this->getPlantillaConJugadores($id_equipo, $id_liga);
    }
}
