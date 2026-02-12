<?php

class EquipoJugadorModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Obtener todas las asignaciones equipo-jugador
     */
    public function getAll(): array
    {
        $stmt = $this->db->prepare("SELECT * FROM equipo_jugador");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtener asignación por claves (id_jugador, id_equipo, id_liga)
     */
    public function getById(int $idJugador, int $idEquipo, int $idLiga): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM equipo_jugador
             WHERE id_jugador = ? AND id_equipo = ? AND id_liga = ?"
        );
        $stmt->execute([$idJugador, $idEquipo, $idLiga]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Obtener asignaciones por jugador
     */
    public function getByJugador(int $idJugador): array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM equipo_jugador WHERE id_jugador = ?"
        );
        $stmt->execute([$idJugador]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtener jugadores de un equipo
     */
    public function getByEquipo(int $idEquipo): array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM equipo_jugador WHERE id_equipo = ?"
        );
        $stmt->execute([$idEquipo]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtener asignaciones por liga
     */
    public function getByLiga(int $idLiga): array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM equipo_jugador WHERE id_liga = ?"
        );
        $stmt->execute([$idLiga]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtener jugadores de un equipo en una liga específica
     */
    public function getByEquipoAndLiga(int $idEquipo, int $idLiga): array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM equipo_jugador WHERE id_equipo = ? AND id_liga = ?"
        );
        $stmt->execute([$idEquipo, $idLiga]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Verificar si la asignación ya existe
     */
    public function existsByKey(int $idJugador, int $idEquipo, int $idLiga): bool
    {
        $stmt = $this->db->prepare(
            "SELECT 1 FROM equipo_jugador
             WHERE id_jugador = ? AND id_equipo = ? AND id_liga = ?"
        );
        $stmt->execute([$idJugador, $idEquipo, $idLiga]);
        return (bool) $stmt->fetchColumn();
    }

    /**
     * Insertar nueva asignación equipo-jugador
     */
    public function insert(
        int $idJugador,
        int $idEquipo,
        int $idLiga,
        ?int $dorsal = null,
        string $estado = 'activo'
    ): int {
        $stmt = $this->db->prepare(
            "INSERT INTO equipo_jugador (id_jugador, id_equipo, id_liga, dorsal, estado)
             VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->execute([$idJugador, $idEquipo, $idLiga, $dorsal, $estado]);
        return $stmt->rowCount();
    }

    /**
     * Actualizar asignación equipo-jugador
     */
    public function update(
        int $idJugador,
        int $idEquipo,
        int $idLiga,
        ?int $dorsal = null,
        string $estado = 'activo'
    ): int {
        $stmt = $this->db->prepare(
            "UPDATE equipo_jugador SET dorsal = ?, estado = ?
             WHERE id_jugador = ? AND id_equipo = ? AND id_liga = ?"
        );
        $stmt->execute([$dorsal, $estado, $idJugador, $idEquipo, $idLiga]);
        return $stmt->rowCount();
    }

    /**
     * Eliminar asignación equipo-jugador
     */
    public function delete(int $idJugador, int $idEquipo, int $idLiga): int
    {
        $stmt = $this->db->prepare(
            "DELETE FROM equipo_jugador
             WHERE id_jugador = ? AND id_equipo = ? AND id_liga = ?"
        );
        $stmt->execute([$idJugador, $idEquipo, $idLiga]);
        return $stmt->rowCount();
    }
}
