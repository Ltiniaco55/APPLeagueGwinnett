<?php

class EntrenadorEquipoModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Obtener todas las asignaciones entrenador-equipo
     */
    public function getAll(): array
    {
        $stmt = $this->db->prepare("SELECT * FROM entrenador_equipo");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtener asignación por claves (id_entrenador, id_equipo, id_liga)
     */
    public function getById(int $idEntrenador, int $idEquipo, int $idLiga): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM entrenador_equipo
             WHERE id_entrenador = ? AND id_equipo = ? AND id_liga = ?"
        );
        $stmt->execute([$idEntrenador, $idEquipo, $idLiga]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Obtener asignaciones por entrenador
     */
    public function getByEntrenador(int $idEntrenador): array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM entrenador_equipo WHERE id_entrenador = ?"
        );
        $stmt->execute([$idEntrenador]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function contarEquiposPorEntrenador(int $idEntrenador): int
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*)
             FROM entrenador_equipo
             WHERE id_entrenador = ?"
        );

        $stmt->execute([$idEntrenador]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Obtener asignaciones por equipo
     */
    public function getByEquipo(int $idEquipo): array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM entrenador_equipo WHERE id_equipo = ?"
        );
        $stmt->execute([$idEquipo]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getEntrenadoresByEquipo(int $idEquipo, ?int $idLiga = null): array
    {
        $sql = "
            SELECT 
                ee.id_entrenador,
                ee.id_equipo,
                ee.id_liga,
                ee.estado,
                e.nombre,
                e.apellido,
                e.telefono,
                e.email,
                e.id_usuario
            FROM entrenador_equipo ee
            INNER JOIN entrenadores e 
                ON e.id_entrenador = ee.id_entrenador
            WHERE ee.id_equipo = ?
        ";

        $params = [$idEquipo];

        if ($idLiga !== null) {
            $sql .= " AND ee.id_liga = ?";
            $params[] = $idLiga;
        }

        $sql .= " ORDER BY e.apellido ASC, e.nombre ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtener asignaciones por liga
     */
    public function getByLiga(int $idLiga): array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM entrenador_equipo WHERE id_liga = ?"
        );
        $stmt->execute([$idLiga]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Verificar si la asignación ya existe
     */
    public function existsByKey(int $idEntrenador, int $idEquipo, int $idLiga): bool
    {
        $stmt = $this->db->prepare(
            "SELECT 1 FROM entrenador_equipo
             WHERE id_entrenador = ? AND id_equipo = ? AND id_liga = ?"
        );
        $stmt->execute([$idEntrenador, $idEquipo, $idLiga]);
        return (bool) $stmt->fetchColumn();
    }

    /**
     * Insertar nueva asignación entrenador-equipo
     */
    public function insert(int $idEntrenador, int $idEquipo, int $idLiga, string $estado): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO entrenador_equipo (id_entrenador, id_equipo, id_liga, estado)
             VALUES (?, ?, ?, ?)"
        );
        $stmt->execute([$idEntrenador, $idEquipo, $idLiga, $estado]);
        return $stmt->rowCount();
    }

    /**
     * Actualizar estado de la asignación
     */
    public function update(int $idEntrenador, int $idEquipo, int $idLiga, string $estado): int
    {
        $stmt = $this->db->prepare(
            "UPDATE entrenador_equipo SET estado = ?
             WHERE id_entrenador = ? AND id_equipo = ? AND id_liga = ?"
        );
        $stmt->execute([$estado, $idEntrenador, $idEquipo, $idLiga]);
        return $stmt->rowCount();
    }

    /**
     * Eliminar asignación entrenador-equipo
     */
    public function delete(int $idEntrenador, int $idEquipo, int $idLiga): int
    {
        $stmt = $this->db->prepare(
            "DELETE FROM entrenador_equipo
             WHERE id_entrenador = ? AND id_equipo = ? AND id_liga = ?"
        );
        $stmt->execute([$idEntrenador, $idEquipo, $idLiga]);
        return $stmt->rowCount();
    }


    public function deleteByEntrenador(int $idEntrenador): int
    {
        $stmt = $this->db->prepare(
            "DELETE FROM entrenador_equipo
            WHERE id_entrenador = ?"
        );

        $stmt->execute([$idEntrenador]);

        return $stmt->rowCount();
    }

    public function deleteByEquipo(int $idEquipo): int
    {
        $stmt = $this->db->prepare(
            "DELETE FROM entrenador_equipo
            WHERE id_equipo = ?"
        );

        $stmt->execute([$idEquipo]);

        return $stmt->rowCount();
    }

    public function sincronizarEquiposEntrenador(
        int $idEntrenador,
        array $relaciones
    ): void {
        $this->db->beginTransaction();

        try {
            $stmtDelete = $this->db->prepare(
                "DELETE FROM entrenador_equipo
                WHERE id_entrenador = ?"
            );
            $stmtDelete->execute([$idEntrenador]);

            $stmtInsert = $this->db->prepare(
                "INSERT INTO entrenador_equipo 
                    (id_entrenador, id_equipo, id_liga, estado)
                VALUES (?, ?, ?, ?)"
            );

            foreach ($relaciones as $relacion) {
                $stmtInsert->execute([
                    $idEntrenador,
                    (int) $relacion['id_equipo'],
                    (int) $relacion['id_liga'],
                    $relacion['estado'] ?? 'ACTIVO'
                ]);
            }

            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }


    public function getEntrenadoresByEquipoLiga(int $idEquipo, int $idLiga): array
    {
        $stmt = $this->db->prepare(
            "SELECT 
            e.id_entrenador,
            e.id_usuario,
            e.nombre,
            e.apellido,
            e.telefono,
            e.email,
            ee.id_equipo,
            ee.id_liga,
            ee.estado
         FROM entrenador_equipo ee
         INNER JOIN entrenadores e 
            ON e.id_entrenador = ee.id_entrenador
         WHERE ee.id_equipo = ?
           AND ee.id_liga = ?
         ORDER BY e.nombre ASC, e.apellido ASC"
        );

        $stmt->execute([$idEquipo, $idLiga]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
