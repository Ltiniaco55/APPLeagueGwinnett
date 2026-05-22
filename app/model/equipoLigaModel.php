<?php

class EquipoLigaModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function getAll(): array
    {
        $stmt = $this->db->prepare("SELECT * FROM equipo_liga");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getById(int $idEquipo, int $idLiga): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM equipo_liga WHERE id_equipo = ? AND id_liga = ?"
        );
        $stmt->execute([$idEquipo, $idLiga]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public function getByEquipo(int $idEquipo): array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM equipo_liga WHERE id_equipo = ?"
        );
        $stmt->execute([$idEquipo]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getByLiga(int $idLiga): array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM equipo_liga WHERE id_liga = ?"
        );
        $stmt->execute([$idLiga]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function existsByKey(int $idEquipo, int $idLiga): bool
    {
        $stmt = $this->db->prepare(
            "SELECT 1 FROM equipo_liga WHERE id_equipo = ? AND id_liga = ?"
        );
        $stmt->execute([$idEquipo, $idLiga]);
        return (bool) $stmt->fetchColumn();
    }

    public function insert(int $idEquipo, int $idLiga): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO equipo_liga (id_equipo, id_liga) VALUES (?, ?)"
        );
        $stmt->execute([$idEquipo, $idLiga]);
        return $stmt->rowCount();
    }

    public function delete(int $idEquipo, int $idLiga): int
    {
        $stmt = $this->db->prepare(
            "DELETE FROM equipo_liga WHERE id_equipo = ? AND id_liga = ?"
        );
        $stmt->execute([$idEquipo, $idLiga]);
        return $stmt->rowCount();
    }
}
