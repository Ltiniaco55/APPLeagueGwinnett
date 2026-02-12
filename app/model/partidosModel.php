<?php

class PartidosModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Obtener todos los partidos
     */
    public function getAll(): array
    {
        $stmt = $this->db->prepare("SELECT * FROM partidos ORDER BY fecha DESC");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtener partido por ID
     */
    public function getById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM partidos WHERE id_partido = ?"
        );
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Obtener partidos de una liga
     */
    public function getByLiga(int $idLiga): array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM partidos WHERE id_liga = ? ORDER BY fecha DESC"
        );
        $stmt->execute([$idLiga]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtener partidos de un equipo (como local o visitante)
     */
    public function getByEquipo(int $idEquipo): array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM partidos WHERE id_equipo1 = ? OR id_equipo2 = ? ORDER BY fecha DESC"
        );
        $stmt->execute([$idEquipo, $idEquipo]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtener partidos por fecha
     */
    public function getByFecha(string $fecha): array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM partidos WHERE DATE(fecha) = ? ORDER BY fecha"
        );
        $stmt->execute([$fecha]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtener partidos por estado
     */
    public function getByEstado(string $estado): array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM partidos WHERE estado = ? ORDER BY fecha DESC"
        );
        $stmt->execute([$estado]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtener partidos de una liga por estado
     */
    public function getByLigaAndEstado(int $idLiga, string $estado): array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM partidos WHERE id_liga = ? AND estado = ? ORDER BY fecha DESC"
        );
        $stmt->execute([$idLiga, $estado]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Buscar partidos con filtros
     */
    public function search(
        int $idLiga = 0,
        int $idEquipo = 0,
        string $estado = '',
        string $fechaDesde = '',
        string $fechaHasta = ''
    ): array {
        $sql = "SELECT * FROM partidos WHERE 1=1";
        $params = [];

        if ($idLiga > 0) {
            $sql .= " AND id_liga = ?";
            $params[] = $idLiga;
        }
        if ($idEquipo > 0) {
            $sql .= " AND (id_equipo1 = ? OR id_equipo2 = ?)";
            $params[] = $idEquipo;
            $params[] = $idEquipo;
        }
        if ($estado !== '') {
            $sql .= " AND estado = ?";
            $params[] = $estado;
        }
        if ($fechaDesde !== '') {
            $sql .= " AND fecha >= ?";
            $params[] = $fechaDesde;
        }
        if ($fechaHasta !== '') {
            $sql .= " AND fecha <= ?";
            $params[] = $fechaHasta;
        }

        $sql .= " ORDER BY fecha DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Insertar nuevo partido
     */
    public function insert(
        int $idLiga,
        string $fecha,
        string $lugar,
        ?string $arbitro,
        int $idEquipo1,
        int $idEquipo2,
        ?int $golesEquipo1 = null,
        ?int $golesEquipo2 = null,
        string $estado = 'pendiente'
    ): int {
        $stmt = $this->db->prepare(
            "INSERT INTO partidos (id_liga, fecha, lugar, arbitro, id_equipo1, id_equipo2, goles_equipo1, goles_equipo2, estado)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $idLiga,
            $fecha,
            $lugar,
            $arbitro,
            $idEquipo1,
            $idEquipo2,
            $golesEquipo1,
            $golesEquipo2,
            $estado
        ]);
        return (int) $this->db->lastInsertId();
    }

    /**
     * Actualizar partido
     */
    public function update(
        int $id,
        int $idLiga,
        string $fecha,
        string $lugar,
        ?string $arbitro,
        int $idEquipo1,
        int $idEquipo2,
        ?int $golesEquipo1 = null,
        ?int $golesEquipo2 = null,
        string $estado = 'pendiente'
    ): int {
        $stmt = $this->db->prepare(
            "UPDATE partidos SET id_liga = ?, fecha = ?, lugar = ?, arbitro = ?,
             id_equipo1 = ?, id_equipo2 = ?, goles_equipo1 = ?, goles_equipo2 = ?, estado = ?
             WHERE id_partido = ?"
        );
        $stmt->execute([
            $idLiga,
            $fecha,
            $lugar,
            $arbitro,
            $idEquipo1,
            $idEquipo2,
            $golesEquipo1,
            $golesEquipo2,
            $estado,
            $id
        ]);
        return $stmt->rowCount();
    }

    /**
     * Actualizar resultado de un partido
     */
    public function updateResultado(
        int $id,
        int $golesEquipo1,
        int $golesEquipo2,
        string $estado = 'finalizado'
    ): int {
        $stmt = $this->db->prepare(
            "UPDATE partidos SET goles_equipo1 = ?, goles_equipo2 = ?, estado = ?
             WHERE id_partido = ?"
        );
        $stmt->execute([$golesEquipo1, $golesEquipo2, $estado, $id]);
        return $stmt->rowCount();
    }

    /**
     * Eliminar partido
     */
    public function delete(int $id): int
    {
        $stmt = $this->db->prepare(
            "DELETE FROM partidos WHERE id_partido = ?"
        );
        $stmt->execute([$id]);
        return $stmt->rowCount();
    }

    /**
     * Eliminar todos los partidos de una liga
     */
    public function deleteByLiga(int $idLiga): int
    {
        $stmt = $this->db->prepare(
            "DELETE FROM partidos WHERE id_liga = ?"
        );
        $stmt->execute([$idLiga]);
        return $stmt->rowCount();
    }
}
