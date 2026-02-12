<?php

class ClasificacionesModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Obtener todas las clasificaciones
     */
    public function getAll(): array
    {
        $stmt = $this->db->prepare("SELECT * FROM clasificacion");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtener clasificación por ID
     */
    public function getById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM clasificacion WHERE id_clasificacion = ?"
        );
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Obtener clasificación de una liga (ordenada por PTS descendente)
     */
    public function getByLiga(int $idLiga): array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM clasificacion WHERE id_liga = ? ORDER BY PTS DESC, GF DESC"
        );
        $stmt->execute([$idLiga]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtener clasificaciones de un equipo
     */
    public function getByEquipo(int $idEquipo): array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM clasificacion WHERE id_equipo = ?"
        );
        $stmt->execute([$idEquipo]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtener clasificación de un equipo en una liga específica
     */
    public function getByEquipoAndLiga(int $idEquipo, int $idLiga): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM clasificacion WHERE id_equipo = ? AND id_liga = ?"
        );
        $stmt->execute([$idEquipo, $idLiga]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Verificar si la clasificación ya existe para un equipo en una liga
     */
    public function existsByKey(int $idEquipo, int $idLiga): bool
    {
        $stmt = $this->db->prepare(
            "SELECT 1 FROM clasificacion WHERE id_equipo = ? AND id_liga = ?"
        );
        $stmt->execute([$idEquipo, $idLiga]);
        return (bool) $stmt->fetchColumn();
    }

    /**
     * Insertar nueva clasificación
     */
    public function insert(
        int $idLiga,
        int $idEquipo,
        int $PJ = 0,
        int $PG = 0,
        int $PE = 0,
        int $PP = 0,
        int $GF = 0,
        int $GC = 0,
        int $PTS = 0
    ): int {
        $stmt = $this->db->prepare(
            "INSERT INTO clasificacion (id_liga, id_equipo, PJ, PG, PE, PP, GF, GC, PTS)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([$idLiga, $idEquipo, $PJ, $PG, $PE, $PP, $GF, $GC, $PTS]);
        return (int) $this->db->lastInsertId();
    }

    /**
     * Actualizar clasificación por ID
     */
    public function update(
        int $id,
        int $PJ,
        int $PG,
        int $PE,
        int $PP,
        int $GF,
        int $GC,
        int $PTS
    ): int {
        $stmt = $this->db->prepare(
            "UPDATE clasificacion SET PJ = ?, PG = ?, PE = ?, PP = ?, GF = ?, GC = ?, PTS = ?
             WHERE id_clasificacion = ?"
        );
        $stmt->execute([$PJ, $PG, $PE, $PP, $GF, $GC, $PTS, $id]);
        return $stmt->rowCount();
    }

    /**
     * Eliminar clasificación por ID
     */
    public function delete(int $id): int
    {
        $stmt = $this->db->prepare(
            "DELETE FROM clasificacion WHERE id_clasificacion = ?"
        );
        $stmt->execute([$id]);
        return $stmt->rowCount();
    }

    /**
     * Eliminar todas las clasificaciones de una liga
     */
    public function deleteByLiga(int $idLiga): int
    {
        $stmt = $this->db->prepare(
            "DELETE FROM clasificacion WHERE id_liga = ?"
        );
        $stmt->execute([$idLiga]);
        return $stmt->rowCount();
    }
}
