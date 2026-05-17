<?php

declare(strict_types=1);

class PartidosModel
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

    public function getAll(array $filtros = []): array
    {
        $sql = "
            SELECT
                p.id_partido,
                p.id_liga,
                p.tipo_ronda,
                p.fecha,
                p.lugar,
                p.arbitro,
                p.id_equipo_local,
                p.id_equipo_visitante,
                p.goles_local,
                p.goles_visitante,
                p.estado,
                l.nombre_liga,
                l.temporada   AS temporada_liga,
                l.categoria   AS categoria_liga,
                local.club    AS club_local,
                local.categoria AS categoria_local,
                visitante.club  AS club_visitante,
                visitante.categoria AS categoria_visitante
            FROM partidos p
            INNER JOIN ligas    l         ON l.id_liga       = p.id_liga
            INNER JOIN equipos  local     ON local.id_equipo = p.id_equipo_local
            INNER JOIN equipos  visitante ON visitante.id_equipo = p.id_equipo_visitante
            WHERE 1=1
        ";

        $params = [];

        if (!empty($filtros['id_liga'])) {
            $sql    .= " AND p.id_liga = ?";
            $params[] = (int) $filtros['id_liga'];
        }

        if (!empty($filtros['fecha'])) {
            $sql    .= " AND DATE(p.fecha) = ?";
            $params[] = $filtros['fecha'];
        }

        if (!empty($filtros['id_equipo'])) {
            $sql    .= " AND (p.id_equipo_local = ? OR p.id_equipo_visitante = ?)";
            $params[] = (int) $filtros['id_equipo'];
            $params[] = (int) $filtros['id_equipo'];
        }

        if (isset($filtros['tipo_ronda']) && $filtros['tipo_ronda'] !== '') {
            $sql    .= " AND p.tipo_ronda = ?";
            $params[] = $filtros['tipo_ronda'];
        }

        if (isset($filtros['estado']) && $filtros['estado'] !== '') {
            $sql    .= " AND p.estado = ?";
            $params[] = $filtros['estado'];
        }

        if (isset($filtros['lugar']) && $filtros['lugar'] !== '') {
            $sql    .= " AND p.lugar LIKE ?";
            $params[] = '%' . $filtros['lugar'] . '%';
        }

        $sql .= " ORDER BY p.fecha DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // =========================================================================
    //  SELECT: getById con JOINs enriquecidos
    // =========================================================================

    public function getById(int $id_partido): ?array
    {
        $sql = "
            SELECT
                p.id_partido,
                p.id_liga,
                p.tipo_ronda,
                p.fecha,
                p.lugar,
                p.arbitro,
                p.id_equipo_local,
                p.id_equipo_visitante,
                p.goles_local,
                p.goles_visitante,
                p.estado,
                l.nombre_liga,
                l.temporada   AS temporada_liga,
                l.categoria   AS categoria_liga,
                local.club    AS club_local,
                local.categoria AS categoria_local,
                visitante.club  AS club_visitante,
                visitante.categoria AS categoria_visitante
            FROM partidos p
            INNER JOIN ligas    l         ON l.id_liga       = p.id_liga
            INNER JOIN equipos  local     ON local.id_equipo = p.id_equipo_local
            INNER JOIN equipos  visitante ON visitante.id_equipo = p.id_equipo_visitante
            WHERE p.id_partido = ?
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id_partido]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    // =========================================================================
    //  INSERT
    // =========================================================================

    /**
     * Inserta un nuevo partido.
     * Devuelve el id_partido generado.
     */
    public function insertar(array $datos): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO partidos
                (id_liga, tipo_ronda, fecha, lugar, arbitro,
                 id_equipo_local, id_equipo_visitante,
                 goles_local, goles_visitante, estado)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $datos['id_liga'],
            $datos['tipo_ronda'],
            $datos['fecha'],
            $datos['lugar'],
            $datos['arbitro']           ?? null,
            $datos['id_equipo_local'],
            $datos['id_equipo_visitante'],
            $datos['goles_local']       ?? null,
            $datos['goles_visitante']   ?? null,
            $datos['estado']            ?? 'programado',
        ]);

        return (int) $this->db->lastInsertId();
    }

    // =========================================================================
    //  UPDATE
    // =========================================================================

    /**
     * Actualiza un partido existente.
     * Devuelve el número de filas afectadas.
     */
    public function modificar(int $id_partido, array $datos): int
    {
        $stmt = $this->db->prepare("
            UPDATE partidos SET
                id_liga              = ?,
                tipo_ronda           = ?,
                fecha                = ?,
                lugar                = ?,
                arbitro              = ?,
                id_equipo_local      = ?,
                id_equipo_visitante  = ?,
                goles_local          = ?,
                goles_visitante      = ?,
                estado               = ?
            WHERE id_partido = ?
        ");

        $stmt->execute([
            $datos['id_liga'],
            $datos['tipo_ronda'],
            $datos['fecha'],
            $datos['lugar'],
            $datos['arbitro']           ?? null,
            $datos['id_equipo_local'],
            $datos['id_equipo_visitante'],
            $datos['goles_local']       ?? null,
            $datos['goles_visitante']   ?? null,
            $datos['estado'],
            $id_partido,
        ]);

        return $stmt->rowCount();
    }

    // =========================================================================
    //  CANCELAR (solo cambia estado)
    // =========================================================================

    public function cancelar(int $id_partido): int
    {
        $stmt = $this->db->prepare(
            "UPDATE partidos SET estado = 'cancelado' WHERE id_partido = ?"
        );
        $stmt->execute([$id_partido]);
        return $stmt->rowCount();
    }

    // =========================================================================
    //  DELETE (físico)
    // =========================================================================

    public function delete(int $id_partido): int
    {
        $stmt = $this->db->prepare(
            "DELETE FROM partidos WHERE id_partido = ?"
        );
        $stmt->execute([$id_partido]);
        return $stmt->rowCount();
    }

    public function deleteByEquipoLiga(int $idLiga, int $idEquipo): int
    {
        $stmt = $this->db->prepare("
        DELETE FROM partidos
        WHERE id_liga = ?
          AND (
                id_equipo_local = ?
             OR id_equipo_visitante = ?
          )
    ");

        $stmt->execute([
            $idLiga,
            $idEquipo,
            $idEquipo
        ]);

        return $stmt->rowCount();
    }

    public function deleteByLiga(int $idLiga): int
    {
        $stmt = $this->db->prepare("
        DELETE FROM partidos
        WHERE id_liga = ?
    ");

        $stmt->execute([$idLiga]);

        return $stmt->rowCount();
    }

    public function existeDuplicado(
        int     $idLiga,
        string  $tipo_ronda,
        int     $idLocal,
        int     $idVisitante,
        ?int    $excluirId = null
    ): bool {
        $sql = "
            SELECT 1 FROM partidos
            WHERE id_liga  = ?
              AND tipo_ronda  = ?
              AND (
                    (id_equipo_local = ? AND id_equipo_visitante = ?)
                 OR (id_equipo_local = ? AND id_equipo_visitante = ?)
              )
        ";
        $params = [
            $idLiga,
            $tipo_ronda,
            $idLocal,
            $idVisitante,
            $idVisitante,
            $idLocal,
        ];

        if ($excluirId !== null) {
            $sql    .= " AND id_partido != ?";
            $params[] = $excluirId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (bool) $stmt->fetchColumn();
    }

    /**
     * Comprueba que ninguno de los dos equipos tenga ya un partido en la misma
     * fecha/hora exacta dentro de la misma liga.
     */
    public function existeConflictoHorario(
        int     $idLiga,
        string  $fecha,
        int     $idLocal,
        int     $idVisitante,
        ?int    $excluirId = null
    ): bool {
        $sql = "
            SELECT 1 FROM partidos
            WHERE id_liga = ?
              AND fecha   = ?
              AND (
                    id_equipo_local      = ? OR id_equipo_visitante = ?
                 OR id_equipo_local      = ? OR id_equipo_visitante = ?
              )
        ";
        $params = [
            $idLiga,
            $fecha,
            $idLocal,
            $idLocal,
            $idVisitante,
            $idVisitante,
        ];

        if ($excluirId !== null) {
            $sql    .= " AND id_partido != ?";
            $params[] = $excluirId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (bool) $stmt->fetchColumn();
    }

    /**
     * Verifica que ambos equipos pertenezcan a la liga mediante equipo_liga.
     */
    public function equiposPertenecenALiga(
        int $idLiga,
        int $idLocal,
        int $idVisitante
    ): bool {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM equipo_liga
            WHERE id_liga  = ?
              AND id_equipo IN (?, ?)
        ");
        $stmt->execute([$idLiga, $idLocal, $idVisitante]);
        return ((int) $stmt->fetchColumn()) === 2;
    }

    /**
     * Verifica que la liga exista.
     */
    public function existeLiga(int $idLiga): bool
    {
        $stmt = $this->db->prepare(
            "SELECT 1 FROM ligas WHERE id_liga = ?"
        );
        $stmt->execute([$idLiga]);
        return (bool) $stmt->fetchColumn();
    }

    /**
     * Verifica que el equipo exista.
     */
    public function existeEquipo(int $idEquipo): bool
    {
        $stmt = $this->db->prepare(
            "SELECT 1 FROM equipos WHERE id_equipo = ?"
        );
        $stmt->execute([$idEquipo]);
        return (bool) $stmt->fetchColumn();
    }


    public function getFormatoLiga(int $idLiga): ?string
    {
        $stmt = $this->db->prepare("
        SELECT formato_liga
        FROM ligas
        WHERE id_liga = ?
        LIMIT 1
    ");

        $stmt->execute([$idLiga]);

        $formato = $stmt->fetchColumn();

        return $formato ? strtoupper((string)$formato) : null;
    }
}
