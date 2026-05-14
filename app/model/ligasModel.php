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

        $sql .= " ORDER BY 
        FIELD(estado_liga, 'EN_CURSO', 'PROXIMAMENTE'),
        temporada DESC,
        nombre_liga ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function insert(
        string $nom,
        string $temp,
        string $categ,
        string $descripcion = '',
        string $estadoLiga = 'PROXIMAMENTE',
        string $formatoLiga = 'JORNADAS'
    ): int {
        $stmt = $this->db->prepare(
            "INSERT INTO ligas 
        (nombre_liga, temporada, categoria, descripcion, estado_liga, formato_liga) 
        VALUES (?, ?, ?, ?, ?, ?)"
        );

        $stmt->execute([
            $nom,
            $temp,
            $categ,
            $descripcion,
            $estadoLiga,
            $formatoLiga
        ]);

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



    /**
     * Actualizar una liga usando su clave natural (nom, temp, categ)
     * Devuelve el número de filas afectadas.
     */
    public function updateByKey(
        string $nomActual,
        string $tempActual,
        string $categActual,
        string $nomNuevo,
        string $tempNuevo,
        string $categNuevo,
        ?string $descripcion = null,
        string $estadoLiga = 'PROXIMAMENTE',
        string $formatoLiga = 'JORNADAS'
    ): int {
        $stmt = $this->db->prepare(
            "UPDATE ligas
         SET nombre_liga = ?, 
             temporada = ?, 
             categoria = ?, 
             descripcion = ?,
             estado_liga = ?,
             formato_liga = ?
         WHERE nombre_liga = ? 
           AND temporada = ? 
           AND categoria = ?"
        );

        $stmt->execute([
            $nomNuevo,
            $tempNuevo,
            $categNuevo,
            $descripcion,
            $estadoLiga,
            $formatoLiga,
            $nomActual,
            $tempActual,
            $categActual
        ]);

        return $stmt->rowCount();
    }


    public function getByKey(string $nom, string $temp, string $categ): ?array
    {
        $stmt = $this->db->prepare("
        SELECT *
        FROM ligas
        WHERE nombre_liga = ?
          AND temporada = ?
          AND categoria = ?
        LIMIT 1
    ");

        $stmt->execute([$nom, $temp, $categ]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ?: null;
    }

    public function deleteClasificacionByLiga(int $idLiga): int
    {
        $stmt = $this->db->prepare("
        DELETE FROM clasificacion
        WHERE id_liga = ?
    ");

        $stmt->execute([$idLiga]);

        return $stmt->rowCount();
    }

    public function deletePartidosByLiga(int $idLiga): int
    {
        $stmt = $this->db->prepare("
        DELETE FROM partidos
        WHERE id_liga = ?
    ");

        $stmt->execute([$idLiga]);

        return $stmt->rowCount();
    }

    public function deleteEquiposLigaByLiga(int $idLiga): int
    {
        $stmt = $this->db->prepare("
        DELETE FROM equipo_liga
        WHERE id_liga = ?
    ");

        $stmt->execute([$idLiga]);

        return $stmt->rowCount();
    }

    public function getById(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM ligas WHERE id_liga = ?");
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public function guardarEscudoSiNoExiste(int $id, string $ruta): int
    {
        $stmt = $this->db->prepare(
            "UPDATE ligas 
             SET escudo = ?, escudo_bloqueado = 1 
             WHERE id_liga = ? AND (escudo_bloqueado = 0 OR escudo_bloqueado IS NULL)"
        );
        $stmt->execute([$ruta, $id]);
        return $stmt->rowCount();
    }

    public function tieneEscudo(int $id): bool
    {
        $stmt = $this->db->prepare("SELECT escudo_bloqueado FROM ligas WHERE id_liga = ?");
        $stmt->execute([$id]);
        return (bool) $stmt->fetchColumn();
    }
}
