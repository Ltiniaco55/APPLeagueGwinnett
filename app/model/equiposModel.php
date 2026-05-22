<?php

class EquiposModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function getAll(): array
    {
        $stmt = $this->db->prepare("SELECT * FROM equipos");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getById(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM equipos WHERE id_equipo = ?");
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public function getByClub(string $club): array
    {
        $stmt = $this->db->prepare("SELECT * FROM equipos WHERE club = ?");
        $stmt->execute([$club]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function existsByKey(string $club, string $categoria): bool
    {
        $stmt = $this->db->prepare(
            "SELECT 1 FROM equipos WHERE club = ? AND categoria = ?"
        );
        $stmt->execute([$club, $categoria]);
        return (bool) $stmt->fetchColumn();
    }

    public function insert(
        string $club,
        string $categoria,
        ?string $descripcion = null
    ): int {
        $stmt = $this->db->prepare(
            "INSERT INTO equipos (club, categoria, descripcion)
             VALUES (?, ?, ?)"
        );
        
        $stmt->execute([
            $club,
            $categoria,
            $descripcion
        ]);
        
        return (int) $this->db->lastInsertId();
    }

    public function update(
        int $id,
        string $club,
        string $categoria,
        ?string $descripcion = null
    ): int {
        $stmt = $this->db->prepare(
            "UPDATE equipos SET club = ?, categoria = ?, descripcion = ?
             WHERE id_equipo = ?"
        );
        
        $stmt->execute([
            $club,
            $categoria,
            $descripcion,
            $id
        ]);
        
        return $stmt->rowCount();
    }

    public function delete(int $id): int
    {
        $stmt = $this->db->prepare("DELETE FROM equipos WHERE id_equipo = ?");
        $stmt->execute([$id]);
        return $stmt->rowCount();
    }

    public function search(string $club = '', string $categoria = ''): array
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

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getByCategoria(string $categoria): array
    {
        $stmt = $this->db->prepare("SELECT * FROM equipos WHERE categoria = ?");
        $stmt->execute([$categoria]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getLigasByEquipo(int $id_equipo): array
    {
        $stmt = $this->db->prepare("SELECT id_liga FROM equipo_liga WHERE id_equipo = ?");
        $stmt->execute([$id_equipo]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function syncLigas(int $id_equipo, array $ligas_ids): void
    {
        $this->db->beginTransaction();
        try {
            $stmtDel = $this->db->prepare("DELETE FROM equipo_liga WHERE id_equipo = ?");
            $stmtDel->execute([$id_equipo]);
            
            if (!empty($ligas_ids)) {
                $stmtIns = $this->db->prepare("INSERT INTO equipo_liga (id_equipo, id_liga) VALUES (?, ?)");
                foreach ($ligas_ids as $id_liga) {
                    $stmtIns->execute([$id_equipo, (int)$id_liga]);
                }
            }
            $this->db->commit();
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function guardarEscudoSiNoExiste(int $id, string $ruta): int
    {
        $stmt = $this->db->prepare(
            "UPDATE equipos 
             SET escudo = ?, escudo_bloqueado = 1 
             WHERE id_equipo = ? AND (escudo_bloqueado = 0 OR escudo_bloqueado IS NULL)"
        );
        $stmt->execute([$ruta, $id]);
        return $stmt->rowCount();
    }

    public function tieneEscudo(int $id): bool
    {
        $stmt = $this->db->prepare("SELECT escudo_bloqueado FROM equipos WHERE id_equipo = ?");
        $stmt->execute([$id]);
        return (bool) $stmt->fetchColumn();
    }
}
