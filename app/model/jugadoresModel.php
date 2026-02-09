<?php

class JugadoresModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Obtener todos los jugadores
     */
    public function getAll(): array
    {
        $stmt = $this->db->prepare("SELECT * FROM jugador");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtener jugador por ID
     */
    public function getById(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM jugador WHERE id_jugador = ?");
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Obtener jugadores por nombre
     */
    public function getByNombre(string $nombre): array
    {
        $stmt = $this->db->prepare("SELECT * FROM jugador WHERE nombre LIKE ?");
        $stmt->execute(["%$nombre%"]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtener jugadores por apellido
     */
    public function getByApellido(string $apellido): array
    {
        $stmt = $this->db->prepare("SELECT * FROM jugador WHERE apellido LIKE ?");
        $stmt->execute(["%$apellido%"]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtener jugadores de un usuario
     */
    public function getByUsuario(int $id_usuario): array
    {
        $stmt = $this->db->prepare("SELECT * FROM jugador WHERE id_usuario = ?");
        $stmt->execute([$id_usuario]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Verificar si el jugador ya existe (por nombre, apellido y fecha_nacimiento)
     */
    public function existsByKey(string $nombre, string $apellido, string $fecha_nacimiento): bool
    {
        $stmt = $this->db->prepare(
            "SELECT 1 FROM jugador WHERE nombre = ? AND apellido = ? AND fecha_nacimiento = ?"
        );
        $stmt->execute([$nombre, $apellido, $fecha_nacimiento]);
        return (bool) $stmt->fetchColumn();
    }

    /**
     * Crear nuevo jugador
     */
    public function insert(
        string $nombre,
        string $apellido,
        string $fecha_nacimiento,
        ?string $foto_path = null,
        ?int $id_usuario = null
    ): int {
        $stmt = $this->db->prepare(
            "INSERT INTO jugador (nombre, apellido, fecha_nacimiento, foto_path, id_usuario)
             VALUES (?, ?, ?, ?, ?)"
        );
        
        $stmt->execute([
            $nombre,
            $apellido,
            $fecha_nacimiento,
            $foto_path,
            $id_usuario
        ]);
        
        return (int) $this->db->lastInsertId();
    }

    /**
     * Actualizar jugador
     */
    public function update(
        int $id,
        string $nombre,
        string $apellido,
        string $fecha_nacimiento,
        ?string $foto_path = null,
        ?int $id_usuario = null
    ): int {
        $stmt = $this->db->prepare(
            "UPDATE jugador SET nombre = ?, apellido = ?, fecha_nacimiento = ?, foto_path = ?, id_usuario = ?
             WHERE id_jugador = ?"
        );
        
        $stmt->execute([
            $nombre,
            $apellido,
            $fecha_nacimiento,
            $foto_path,
            $id_usuario,
            $id
        ]);
        
        return $stmt->rowCount();
    }

    /**
     * Eliminar jugador
     */
    public function delete(int $id): int
    {
        $stmt = $this->db->prepare("DELETE FROM jugador WHERE id_jugador = ?");
        $stmt->execute([$id]);
        return $stmt->rowCount();
    }

    /**
     * Buscar jugadores por nombre, apellido o ambos
     */
    public function search(string $nombre = '', string $apellido = ''): array
    {
        $sql = "SELECT * FROM jugador WHERE 1=1";
        $params = [];

        if ($nombre !== '') {
            $sql .= " AND nombre LIKE ?";
            $params[] = "%$nombre%";
        }
        if ($apellido !== '') {
            $sql .= " AND apellido LIKE ?";
            $params[] = "%$apellido%";
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Contar jugadores
     */
    public function count(): int
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM jugador");
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }
}
