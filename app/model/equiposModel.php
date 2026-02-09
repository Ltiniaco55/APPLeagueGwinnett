<?php

class EquiposModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Obtener todos los equipos
     */
    public function getAll(): array
    {
        $stmt = $this->db->prepare("SELECT * FROM equipos");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtener equipo por ID
     */
    public function getById(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM equipos WHERE id_equipo = ?");
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Obtener equipos por club
     */
    public function getByClub(string $club): array
    {
        $stmt = $this->db->prepare("SELECT * FROM equipos WHERE club = ?");
        $stmt->execute([$club]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Verificar si el equipo ya existe (por club, categoria y temporada)
     */
    public function existsByKey(string $club, string $categoria, string $temporada): bool
    {
        $stmt = $this->db->prepare(
            "SELECT 1 FROM equipos WHERE club = ? AND categoria = ? AND temporada = ?"
        );
        $stmt->execute([$club, $categoria, $temporada]);
        return (bool) $stmt->fetchColumn();
    }

    /**
     * Crear nuevo equipo
     */
    public function insert(
        string $club,
        string $categoria,
        string $temporada,
        ?string $descripcion = null
    ): int {
        $stmt = $this->db->prepare(
            "INSERT INTO equipos (club, categoria, temporada, descripcion)
             VALUES (?, ?, ?, ?)"
        );
        
        $stmt->execute([
            $club,
            $categoria,
            $temporada,
            $descripcion
        ]);
        
        return (int) $this->db->lastInsertId();
    }

    /**
     * Actualizar equipo
     */
    public function update(
        int $id,
        string $club,
        string $categoria,
        string $temporada,
        ?string $descripcion = null
    ): int {
        $stmt = $this->db->prepare(
            "UPDATE equipos SET club = ?, categoria = ?, temporada = ?, descripcion = ?
             WHERE id_equipo = ?"
        );
        
        $stmt->execute([
            $club,
            $categoria,
            $temporada,
            $descripcion,
            $id
        ]);
        
        return $stmt->rowCount();
    }

    /**
     * Eliminar equipo
     */
    public function delete(int $id): int
    {
        $stmt = $this->db->prepare("DELETE FROM equipos WHERE id_equipo = ?");
        $stmt->execute([$id]);
        return $stmt->rowCount();
    }

    /**
     * Buscar equipos por club, categoria o temporada
     */
    public function search(string $club = '', string $categoria = '', string $temporada = ''): array
    {
        $sql = "SELECT * FROM equipos WHERE 1=1";
        $params = [];

        if ($club !== '') {
            $sql .= " AND club LIKE ?";
            $params[] = "%$club%";
        }
        if ($categoria !== '') {
            $sql .= " AND categoria LIKE ?";
            $params[] = "%$categoria%";
        }
        if ($temporada !== '') {
            $sql .= " AND temporada LIKE ?";
            $params[] = "%$temporada%";
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtener equipos por categoria
     */
    public function getByCategoria(string $categoria): array
    {
        $stmt = $this->db->prepare("SELECT * FROM equipos WHERE categoria = ?");
        $stmt->execute([$categoria]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtener equipos por temporada
     */
    public function getByTemporada(string $temporada): array
    {
        $stmt = $this->db->prepare("SELECT * FROM equipos WHERE temporada = ?");
        $stmt->execute([$temporada]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
