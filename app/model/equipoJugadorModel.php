<?php

class EquipoJugadorModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    // =====================================================
    // INSERTAR RELACIÓN jugador → equipo (plantilla)
    // =====================================================
    public function insertarRelacion(
        int $id_jugador,
        int $id_equipo,
        int $id_liga,
        ?int $dorsal = null
    ): int {
        $stmt = $this->db->prepare(
            "INSERT INTO equipo_jugador (id_jugador, id_equipo, id_liga, dorsal)
             VALUES (?, ?, ?, ?)"
        );

        $stmt->execute([$id_jugador, $id_equipo, $id_liga, $dorsal]);
        return $stmt->rowCount();
    }

    // =====================================================
    // ACTUALIZAR DORSAL — SOLO SI ESTÁ VACÍO / NULL
    // =====================================================
    public function actualizarDorsal(
        int $id_jugador,
        int $id_equipo,
        int $id_liga,
        int $dorsal
    ): int {
        $stmt = $this->db->prepare(
            "UPDATE equipo_jugador
             SET dorsal = ?
             WHERE id_jugador = ?
             AND id_equipo = ?
             AND id_liga = ?
             AND (dorsal IS NULL)"
        );

        $stmt->execute([$dorsal, $id_jugador, $id_equipo, $id_liga]);
        return $stmt->rowCount();
    }

    /**
     * Comprobar si el dorsal ya está asignado (no NULL y no 0).
     */
    public function tieneDorsal(int $id_jugador, int $id_equipo, int $id_liga): bool
    {
        $stmt = $this->db->prepare(
            "SELECT dorsal FROM equipo_jugador
             WHERE id_jugador = ? AND id_equipo = ? AND id_liga = ?"
        );
        $stmt->execute([$id_jugador, $id_equipo, $id_liga]);
        $dorsal = $stmt->fetchColumn();
        return ($dorsal !== false && $dorsal !== null && (int)$dorsal >= 0);
    }

    // =====================================================
    // ELIMINAR JUGADOR DE PLANTILLA
    // =====================================================
    public function eliminarRelacion(
        int $id_jugador,
        int $id_equipo,
        int $id_liga
    ): int {
        $stmt = $this->db->prepare(
            "DELETE FROM equipo_jugador
             WHERE id_jugador = ?
             AND id_equipo = ?
             AND id_liga = ?"
        );

        $stmt->execute([$id_jugador, $id_equipo, $id_liga]);
        return $stmt->rowCount();
    }

    // =====================================================
    // OBTENER PLANTILLA BÁSICA (solo equipo_jugador)
    // =====================================================
    public function getByEquipo(int $id_equipo, int $id_liga): array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM equipo_jugador
             WHERE id_equipo = ? AND id_liga = ?"
        );

        $stmt->execute([$id_equipo, $id_liga]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // =====================================================
    // OBTENER PLANTILLA CON DATOS DEL JUGADOR (JOIN)
    // =====================================================
    public function getByEquipoConJugadores(int $id_equipo, int $id_liga): array
    {
        $stmt = $this->db->prepare(
            "SELECT ej.id_jugador, ej.id_equipo, ej.id_liga, ej.dorsal,
                    j.nombre, j.apellido, j.fecha_nacimiento, j.foto_path, j.estado
             FROM equipo_jugador ej
             INNER JOIN jugador j ON ej.id_jugador = j.id_jugador
             WHERE ej.id_equipo = ? AND ej.id_liga = ?
             ORDER BY ej.dorsal ASC, j.apellido ASC"
        );

        $stmt->execute([$id_equipo, $id_liga]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // =====================================================
    // OBTENER UNA RELACIÓN CONCRETA
    // =====================================================
    public function getRelacion(int $id_jugador, int $id_equipo, int $id_liga): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM equipo_jugador
             WHERE id_jugador = ? AND id_equipo = ? AND id_liga = ?"
        );
        $stmt->execute([$id_jugador, $id_equipo, $id_liga]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    // =====================================================
    // DETALLE ENRIQUECIDO DE JUGADOR EN PLANTILLA
    // =====================================================
    public function getDetalleJugadorPlantilla(int $id_jugador, int $id_equipo, int $id_liga): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT ej.id_jugador, ej.id_equipo, ej.id_liga, ej.dorsal,
                    j.nombre, j.apellido, j.fecha_nacimiento, j.foto_path, j.estado,
                    j.id_usuario, j.id_equipo_solicitante, j.id_usuario_solicitante
             FROM equipo_jugador ej
             INNER JOIN jugador j ON ej.id_jugador = j.id_jugador
             WHERE ej.id_jugador = ? AND ej.id_equipo = ? AND ej.id_liga = ?"
        );
        $stmt->execute([$id_jugador, $id_equipo, $id_liga]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    // =====================================================
    // VERIFICAR SI EXISTE RELACIÓN
    // =====================================================
    public function existeRelacion(
        int $id_jugador,
        int $id_equipo,
        int $id_liga
    ): bool {
        $stmt = $this->db->prepare(
            "SELECT 1 FROM equipo_jugador
             WHERE id_jugador = ? AND id_equipo = ? AND id_liga = ?"
        );

        $stmt->execute([$id_jugador, $id_equipo, $id_liga]);
        return (bool)$stmt->fetchColumn();
    }
}
