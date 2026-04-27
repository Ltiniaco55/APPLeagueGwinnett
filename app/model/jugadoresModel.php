<?php

class JugadoresModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    // =====================================================
    //  ACCESO A LA CONEXIÓN (para transacciones externas)
    // =====================================================
    public function getDb(): PDO
    {
        return $this->db;
    }

    // =====================================================
    //  CONSULTAS BÁSICAS
    // =====================================================

    public function getAll(): array
    {
        $stmt = $this->db->prepare("SELECT * FROM jugador");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getById(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM jugador WHERE id_jugador = ?");
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public function getByNombre(string $nombre): array
    {
        $stmt = $this->db->prepare("SELECT * FROM jugador WHERE nombre LIKE ?");
        $stmt->execute(["%$nombre%"]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getByApellido(string $apellido): array
    {
        $stmt = $this->db->prepare("SELECT * FROM jugador WHERE apellido LIKE ?");
        $stmt->execute(["%$apellido%"]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getByUsuario(int $id_usuario): array
    {
        $stmt = $this->db->prepare("SELECT * FROM jugador WHERE id_usuario = ?");
        $stmt->execute([$id_usuario]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }


    public function search(string $nombre = '', string $apellido = ''): array
    {
        $sql = "SELECT * FROM jugador WHERE 1=1";
        $params = [];

        if ($nombre !== '') {
            $sql .= " AND nombre LIKE ?";
            $params[] = "%$nombre%";
        }

        if ($apellido !== '') {
            $sql .= " AND apellido LIKE ?";
            $params[] = "%$apellido%";
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function count(): int
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM jugador");
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    // =====================================================
    //  VERIFICACIÓN GLOBAL (nombre + apellido + fecha)
    // =====================================================

    public function existsByKey(string $nombre, string $apellido, string $fecha_nacimiento): bool
    {
        $stmt = $this->db->prepare(
            "SELECT 1 FROM jugador WHERE nombre = ? AND apellido = ? AND fecha_nacimiento = ?"
        );
        $stmt->execute([$nombre, $apellido, $fecha_nacimiento]);
        return (bool) $stmt->fetchColumn();
    }

    /**
     * Obtener un jugador por su clave natural (nombre+apellido+fecha).
     * Útil para reutilizar una persona global existente.
     */
    public function getByKey(string $nombre, string $apellido, string $fecha_nacimiento): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM jugador WHERE nombre = ? AND apellido = ? AND fecha_nacimiento = ? LIMIT 1"
        );
        $stmt->execute([$nombre, $apellido, $fecha_nacimiento]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    // — Staff-filtered search (backward compat) —
    public function getAllStaff(array $equipoIds): array
    {
        if (empty($equipoIds)) return [];
        $placeholders = implode(',', array_fill(0, count($equipoIds), '?'));
        $stmt = $this->db->prepare(
            "SELECT DISTINCT j.* FROM jugador j
             INNER JOIN equipo_jugador ej ON j.id_jugador = ej.id_jugador
             WHERE ej.id_equipo IN ($placeholders)"
        );
        $stmt->execute($equipoIds);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function searchStaff(string $nombre, string $apellido, array $equipoIds): array
    {
        if (empty($equipoIds)) return [];
        $placeholders = implode(',', array_fill(0, count($equipoIds), '?'));
        $params = $equipoIds;
        $sql = "SELECT DISTINCT j.* FROM jugador j
                INNER JOIN equipo_jugador ej ON j.id_jugador = ej.id_jugador
                WHERE ej.id_equipo IN ($placeholders)";
        if ($nombre !== '') { $sql .= " AND j.nombre LIKE ?"; $params[] = "%$nombre%"; }
        if ($apellido !== '') { $sql .= " AND j.apellido LIKE ?"; $params[] = "%$apellido%"; }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // =====================================================
    //  DUPLICADOS POR EQUIPO (N:M via equipo_jugador)
    // =====================================================

    /**
     * ¿Existe un jugador con ese nombre+apellido+fecha en el MISMO equipo+liga?
     * Hace JOIN jugador ↔ equipo_jugador.
     */
    public function existeEnMismoEquipo(
        string $nombre,
        string $apellido,
        string $fecha_nacimiento,
        int $id_equipo,
        int $id_liga
    ): bool {
        $stmt = $this->db->prepare(
            "SELECT 1
             FROM jugador j
             INNER JOIN equipo_jugador ej ON j.id_jugador = ej.id_jugador
             WHERE j.nombre = ?
               AND j.apellido = ?
               AND j.fecha_nacimiento = ?
               AND ej.id_equipo = ?
               AND ej.id_liga = ?
             LIMIT 1"
        );
        $stmt->execute([$nombre, $apellido, $fecha_nacimiento, $id_equipo, $id_liga]);
        return (bool) $stmt->fetchColumn();
    }

    /**
     * Buscar coincidencias en OTROS equipos (aviso, no bloqueo).
     * Devuelve array con datos del jugador y equipo donde ya está.
     */
    public function buscarCoincidenciasEnOtrosEquipos(
        string $nombre,
        string $apellido,
        string $fecha_nacimiento,
        int $id_equipo,
        int $id_liga
    ): array {
        $stmt = $this->db->prepare(
            "SELECT j.id_jugador, j.nombre, j.apellido, j.fecha_nacimiento,
                    ej.id_equipo, ej.id_liga, ej.dorsal
             FROM jugador j
             INNER JOIN equipo_jugador ej ON j.id_jugador = ej.id_jugador
             WHERE j.nombre = ?
               AND j.apellido = ?
               AND j.fecha_nacimiento = ?
               AND NOT (ej.id_equipo = ? AND ej.id_liga = ?)"
        );
        $stmt->execute([$nombre, $apellido, $fecha_nacimiento, $id_equipo, $id_liga]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // =====================================================
    //  INSERCIONES
    // =====================================================

    /**
     * Crear jugador NORMAL (sin estado especial)
     */
    public function insert(
        string $nombre,
        string $apellido,
        string $fecha_nacimiento,
        ?string $foto_path = null,
        ?int $id_usuario = null
    ): int {
        $stmt = $this->db->prepare(
            "INSERT INTO jugador (nombre, apellido, fecha_nacimiento, foto_path, id_usuario)
             VALUES (?, ?, ?, ?, ?)"
        );

        $stmt->execute([
            $nombre,
            $apellido,
            $fecha_nacimiento,
            $foto_path,
            $id_usuario
        ]);

        return (int) $this->db->lastInsertId();
    }

    // =====================================================
    //  ACTUALIZACIÓN Y BORRADO
    // =====================================================

    public function update(
        int $id,
        string $nombre,
        string $apellido,
        string $fecha_nacimiento,
        ?string $foto_path = null,
        ?int $id_usuario = null
    ): int {
        $stmt = $this->db->prepare(
            "UPDATE jugador
             SET nombre = ?, apellido = ?, fecha_nacimiento = ?, foto_path = ?, id_usuario = ?
             WHERE id_jugador = ?"
        );

        $stmt->execute([
            $nombre,
            $apellido,
            $fecha_nacimiento,
            $foto_path,
            $id_usuario,
            $id
        ]);

        return $stmt->rowCount();
    }

    public function delete(int $id): int
    {
        $stmt = $this->db->prepare("DELETE FROM jugador WHERE id_jugador = ?");
        $stmt->execute([$id]);
        return $stmt->rowCount();
    }

    // =====================================================
    //  GESTIÓN DE FOTO (una sola vez, inmutable)
    // =====================================================

    /**
     * Guardar foto_path SOLO si actualmente está NULL.
     * Devuelve número de filas afectadas (0 = ya tenía foto).
     */
    public function guardarFotoSiNoExiste(int $id_jugador, string $fotoPath): int
    {
        $stmt = $this->db->prepare(
            "UPDATE jugador SET foto_path = ?
             WHERE id_jugador = ? AND (foto_path IS NULL OR foto_path = '')"
        );
        $stmt->execute([$fotoPath, $id_jugador]);
        return $stmt->rowCount();
    }

    /**
     * ¿Ya tiene foto este jugador?
     */
    public function tieneFoto(int $id_jugador): bool
    {
        $stmt = $this->db->prepare(
            "SELECT foto_path FROM jugador WHERE id_jugador = ?"
        );
        $stmt->execute([$id_jugador]);
        $fp = $stmt->fetchColumn();
        return ($fp !== false && $fp !== null && $fp !== '');
    }
}
