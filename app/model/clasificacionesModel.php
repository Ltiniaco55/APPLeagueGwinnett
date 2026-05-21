<?php

declare(strict_types=1);

class ClasificacionesModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

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

            FROM clasificacion c

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

    public function asegurarClasificacionLiga(int $idLiga): void
    {
        $this->regenerarLiga($idLiga);
    }

    public function regenerarLiga(int $idLiga): void
    {
        $this->db->beginTransaction();

        try {
            $stmtDelete = $this->db->prepare("
                DELETE FROM clasificacion
                WHERE id_liga = ?
            ");

            $stmtDelete->execute([$idLiga]);

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

            foreach ($partidos as $p) {
                $local      = (int)$p['id_equipo_local'];
                $visitante  = (int)$p['id_equipo_visitante'];

                if (!isset($tabla[$local], $tabla[$visitante])) {
                    continue;
                }

                $golesLocal     = (int)$p['goles_local'];
                $golesVisitante = (int)$p['goles_visitante'];

                $tabla[$local]['PJ']++;
                $tabla[$visitante]['PJ']++;

                $tabla[$local]['GF'] += $golesLocal;
                $tabla[$local]['GC'] += $golesVisitante;

                $tabla[$visitante]['GF'] += $golesVisitante;
                $tabla[$visitante]['GC'] += $golesLocal;

                if ($golesLocal > $golesVisitante) {
                    $tabla[$local]['PG']++;
                    $tabla[$local]['PTS'] += 3;

                    $tabla[$visitante]['PP']++;
                } elseif ($golesLocal < $golesVisitante) {
                    $tabla[$visitante]['PG']++;
                    $tabla[$visitante]['PTS'] += 3;

                    $tabla[$local]['PP']++;
                } else {
                    $tabla[$local]['PE']++;
                    $tabla[$visitante]['PE']++;

                    $tabla[$local]['PTS']++;
                    $tabla[$visitante]['PTS']++;
                }
            }

            foreach ($tabla as $idEquipo => &$stats) {
                $stats['DG'] = $stats['GF'] - $stats['GC'];
            }

            unset($stats);

            $stmtInsert = $this->db->prepare("
                INSERT INTO clasificacion (
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

    public function deleteByLiga(int $idLiga): int
    {
        $stmt = $this->db->prepare("
            DELETE FROM clasificacion
            WHERE id_liga = ?
        ");

        $stmt->execute([$idLiga]);

        return $stmt->rowCount();
    }
}
