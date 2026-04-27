<?php

declare(strict_types=1);

/**
 * ============================================================================
 *  PartidosModel
 * ============================================================================
 *  Gestión completa de partidos en la liga.
 *
 *  NOTA: Este modelo asume que la BD ya tiene la migración 06 aplicada:
 *    - id_equipo_local  (antes id_equipo1)
 *    - id_equipo_visitante (antes id_equipo2)
 *    - goles_local      (antes goles_equipo1)
 *    - goles_visitante  (antes goles_equipo2)
 *    - jornada          (campo nuevo VARCHAR(50))
 *  Ver: migrations/06_partidos_v2.sql
 * ============================================================================
 */

class PartidosModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    // =========================================================================
    //  HELPER
    // =========================================================================

    public function getDb(): PDO
    {
        return $this->db;
    }

    // =========================================================================
    //  SELECT: getAll con JOINs enriquecidos y filtros opcionales
    // =========================================================================

    /**
     * Devuelve todos los partidos con datos de liga y equipos.
     *
     * Filtros soportados (claves del array $filtros):
     *   - id_liga   (int)    → coincidencia exacta
     *   - fecha     (string) → coincide con DATE(p.fecha) = ?
     *   - id_equipo (int)    → local O visitante
     *   - jornada   (string) → coincidencia exacta
     *   - estado    (string) → coincidencia exacta
     *   - lugar     (string) → LIKE %...%
     */
    public function getAll(array $filtros = []): array
    {
        $sql = "
            SELECT
                p.id_partido,
                p.id_liga,
                p.jornada,
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

        if (isset($filtros['jornada']) && $filtros['jornada'] !== '') {
            $sql    .= " AND p.jornada = ?";
            $params[] = $filtros['jornada'];
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
                p.jornada,
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
                (id_liga, jornada, fecha, lugar, arbitro,
                 id_equipo_local, id_equipo_visitante,
                 goles_local, goles_visitante, estado)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $datos['id_liga'],
            $datos['jornada'],
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
                jornada              = ?,
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
            $datos['jornada'],
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

    // =========================================================================
    //  VALIDACIONES DE NEGOCIO
    // =========================================================================

    /**
     * Comprueba si ya existe un partido con los mismos equipos (en cualquier orden)
     * dentro de la misma liga y jornada.
     */
    public function existeDuplicado(
        int     $idLiga,
        string  $jornada,
        int     $idLocal,
        int     $idVisitante,
        ?int    $excluirId = null
    ): bool {
        $sql = "
            SELECT 1 FROM partidos
            WHERE id_liga  = ?
              AND jornada  = ?
              AND (
                    (id_equipo_local = ? AND id_equipo_visitante = ?)
                 OR (id_equipo_local = ? AND id_equipo_visitante = ?)
              )
        ";
        $params = [
            $idLiga, $jornada,
            $idLocal, $idVisitante,
            $idVisitante, $idLocal,
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
            $idLiga, $fecha,
            $idLocal,    $idLocal,
            $idVisitante, $idVisitante,
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
}
