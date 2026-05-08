<?php

declare(strict_types=1);

/**
 * ============================================================================
 *  ClasificacionesModel
 * ============================================================================
 *  Gestión de clasificaciones dinámicas por liga.
 *
 *  Reglas:
 *   - Victoria = 3 pts
 *   - Empate   = 1 pt
 *   - Derrota  = 0 pts
 *
 *  Orden:
 *   1. PTS DESC
 *   2. DG DESC
 *   3. GF DESC
 *   4. PG DESC
 * ============================================================================
 */

class ClasificacionesModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    // =========================================================================
    // GET CLASIFICACION POR LIGA
    // =========================================================================

    public function getByLiga(int $idLiga): array
    {
        $stmt = $this->db->prepare("
            SELECT
                c.id_clasificacion,
                c.id_liga,
                c.id_equipo,

                e.club,
                e.categoria,
                e.escudo,

                c.PJ,
                c.PG,
                c.PE,
                c.PP,
                c.GF,
                c.GC,
                c.DG,
                c.PTS

            FROM clasificaciones c

            INNER JOIN equipos e
                ON e.id_equipo = c.id_equipo

            WHERE c.id_liga = ?

            ORDER BY
                c.PTS DESC,
                c.DG DESC,
                c.GF DESC,
                c.PG DESC,
                e.club ASC
        ");

        $stmt->execute([$idLiga]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // =========================================================================
    // REGENERAR CLASIFICACION COMPLETA DE UNA LIGA
    // =========================================================================

    /**
     * Recalcula TODA la clasificación a partir de los partidos jugados.
     *
     * Ideal para ejecutar:
     *  - cuando un partido pasa a "jugado"
     *  - cuando se modifica un resultado
     *  - cuando se elimina un partido jugado
     */
    public function regenerarLiga(int $idLiga): void
    {
        $this->db->beginTransaction();

        try {

            // ─────────────────────────────────────────────────────────────
            // Limpiar clasificación anterior
            // ─────────────────────────────────────────────────────────────

            $stmtDelete = $this->db->prepare("
                DELETE FROM clasificaciones
                WHERE id_liga = ?
            ");

            $stmtDelete->execute([$idLiga]);

            // ─────────────────────────────────────────────────────────────
            // Obtener equipos de la liga
            // ─────────────────────────────────────────────────────────────

            $stmtEquipos = $this->db->prepare("
                SELECT
                    id_equipo
                FROM equipo_liga
                WHERE id_liga = ?
            ");

            $stmtEquipos->execute([$idLiga]);

            $equipos = $stmtEquipos->fetchAll(PDO::FETCH_COLUMN);

            if (empty($equipos)) {
                $this->db->commit();
                return;
            }

            // ─────────────────────────────────────────────────────────────
            // Inicializar tabla temporal PHP
            // ─────────────────────────────────────────────────────────────

            $tabla = [];

            foreach ($equipos as $idEquipo) {

                $tabla[(int)$idEquipo] = [
                    'PJ'  => 0,
                    'PG'  => 0,
                    'PE'  => 0,
                    'PP'  => 0,
                    'GF'  => 0,
                    'GC'  => 0,
                    'DG'  => 0,
                    'PTS' => 0,
                ];
            }

            // ─────────────────────────────────────────────────────────────
            // Obtener partidos jugados
            // ─────────────────────────────────────────────────────────────

            $stmtPartidos = $this->db->prepare("
                SELECT
                    id_equipo_local,
                    id_equipo_visitante,
                    goles_local,
                    goles_visitante
                FROM partidos
                WHERE id_liga = ?
                  AND estado = 'jugado'
            ");

            $stmtPartidos->execute([$idLiga]);

            $partidos = $stmtPartidos->fetchAll(PDO::FETCH_ASSOC);

            // ─────────────────────────────────────────────────────────────
            // Procesar partidos
            // ─────────────────────────────────────────────────────────────

            foreach ($partidos as $p) {

                $local      = (int)$p['id_equipo_local'];
                $visitante  = (int)$p['id_equipo_visitante'];

                if (!isset($tabla[$local], $tabla[$visitante])) {
                    continue;
                }

                $golesLocal     = (int)$p['goles_local'];
                $golesVisitante = (int)$p['goles_visitante'];

                // ───── PJ ─────

                $tabla[$local]['PJ']++;
                $tabla[$visitante]['PJ']++;

                // ───── GF / GC ─────

                $tabla[$local]['GF'] += $golesLocal;
                $tabla[$local]['GC'] += $golesVisitante;

                $tabla[$visitante]['GF'] += $golesVisitante;
                $tabla[$visitante]['GC'] += $golesLocal;

                // ───── RESULTADO ─────

                if ($golesLocal > $golesVisitante) {

                    // Local gana

                    $tabla[$local]['PG']++;
                    $tabla[$local]['PTS'] += 3;

                    $tabla[$visitante]['PP']++;
                } elseif ($golesLocal < $golesVisitante) {

                    // Visitante gana

                    $tabla[$visitante]['PG']++;
                    $tabla[$visitante]['PTS'] += 3;

                    $tabla[$local]['PP']++;
                } else {

                    // Empate

                    $tabla[$local]['PE']++;
                    $tabla[$visitante]['PE']++;

                    $tabla[$local]['PTS']++;
                    $tabla[$visitante]['PTS']++;
                }
            }

            // ─────────────────────────────────────────────────────────────
            // Calcular DG
            // ─────────────────────────────────────────────────────────────

            foreach ($tabla as $idEquipo => &$stats) {

                $stats['DG'] = $stats['GF'] - $stats['GC'];
            }

            unset($stats);

            // ─────────────────────────────────────────────────────────────
            // Insertar clasificación final
            // ─────────────────────────────────────────────────────────────

            $stmtInsert = $this->db->prepare("
                INSERT INTO clasificaciones (
                    id_liga,
                    id_equipo,
                    PJ,
                    PG,
                    PE,
                    PP,
                    GF,
                    GC,
                    DG,
                    PTS
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            foreach ($tabla as $idEquipo => $stats) {

                $stmtInsert->execute([
                    $idLiga,
                    $idEquipo,

                    $stats['PJ'],
                    $stats['PG'],
                    $stats['PE'],
                    $stats['PP'],

                    $stats['GF'],
                    $stats['GC'],
                    $stats['DG'],
                    $stats['PTS'],
                ]);
            }

            $this->db->commit();
        } catch (Throwable $e) {

            $this->db->rollBack();

            throw $e;
        }
    }

    // =========================================================================
    // EXISTE CLASIFICACION DE LIGA
    // =========================================================================

    public function existeLiga(int $idLiga): bool
    {
        $stmt = $this->db->prepare("
            SELECT 1
            FROM ligas
            WHERE id_liga = ?
            LIMIT 1
        ");

        $stmt->execute([$idLiga]);

        return (bool)$stmt->fetchColumn();
    }

    // =========================================================================
    // LIMPIAR CLASIFICACION
    // =========================================================================

    public function deleteByLiga(int $idLiga): int
    {
        $stmt = $this->db->prepare("
            DELETE FROM clasificaciones
            WHERE id_liga = ?
        ");

        $stmt->execute([$idLiga]);

        return $stmt->rowCount();
    }
}
