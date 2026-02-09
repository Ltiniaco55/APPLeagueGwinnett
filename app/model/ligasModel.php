<?php

class LigasModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance(); // tu conexión PDO
    }

    public function getAllFiltered(string $nom, string $temp, string $categ): array
    {
        $sql = "SELECT * FROM ligas WHERE 1=1";
        $params = [];

        if ($nom !== '') {
            $sql .= " AND nombre_liga LIKE ?";
            $params[] = "%$nom%";
        }
        if ($temp !== '') {
            $sql .= " AND temporada LIKE ?";
            $params[] = "%$temp%";
        }
        if ($categ !== '') {
            $sql .= " AND categoria LIKE ?";
            $params[] = "%$categ%";
        }

        $stmt = $this->db->prepare($sql); //stmt = statement prepara la query cambiando la "?" por un valor 
        $stmt->execute($params); // ejecuta la query sustituyendola por el valor 
        return $stmt->fetchAll(PDO::FETCH_ASSOC); // lo envia a controller como un json 
    }

    public function existsByKey(string $nom, string $temp, string $categ): bool
    {
        $stmt = $this->db->prepare(
            "SELECT 1 FROM ligas WHERE nombre_liga = ? AND temporada = ? AND categoria = ?"
        );
        $stmt->execute([$nom, $temp, $categ]);
        return (bool) $stmt->fetchColumn();
    }  

    public function insert(string $nom, string $temp, string $categ): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO ligas (nombre_liga, temporada, categoria, descripcion) VALUES (?, ?, ?, ?)"
        );
        $descripcion = '';
        // If caller provided a fourth argument (descripcion), use it
        $args = func_get_args();
        if (isset($args[3])) {
            $descripcion = $args[3];
        }
        $stmt->execute([$nom, $temp, $categ, $descripcion]);
        return (int) $this->db->lastInsertId();
    }

    public function deleteByKey(string $nom, string $temp, string $categ): int
    {
        $stmt = $this->db->prepare(
            "DELETE FROM ligas WHERE nombre_liga = ? AND temporada = ? AND categoria = ?"
        );
        $stmt->execute([$nom, $temp, $categ]);
        return $stmt->rowCount();
    }
}
