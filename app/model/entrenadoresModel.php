<?php

require_once __DIR__ . '/../core/database.php';

class EntrenadoresModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function getAll(): array
    {
        $stmt = $this->db->query('SELECT * FROM entrenadores');
        return $stmt->fetchAll();
    }

    public function getById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM entrenadores WHERE id_entrenador = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function getByUserId(int $id_usuario): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM entrenadores WHERE id_usuario = :id LIMIT 1');
        $stmt->execute([':id' => $id_usuario]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function insert(?int $id_usuario, string $nombre, string $apellido, ?string $fecha_nacimiento = null, ?string $telefono = null, ?string $email = null): int
    {
        $sql = 'INSERT INTO entrenadores (id_usuario, nombre, apellido, fecha_nacimiento, telefono, email)
                VALUES (:id_usuario, :nombre, :apellido, :fecha_nacimiento, :telefono, :email)';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':id_usuario' => $id_usuario,
            ':nombre' => $nombre,
            ':apellido' => $apellido,
            ':fecha_nacimiento' => $fecha_nacimiento,
            ':telefono' => $telefono,
            ':email' => $email,
        ]);

        return (int)$this->db->lastInsertId();
    }

    public function crearDesdeUsuario(array $usuario): int
    {
        $existente = $this->getByUserId((int)$usuario['id_usuario']);

        if ($existente) {
            return (int)$existente['id_entrenador'];
        }

        return $this->insert(
            (int)$usuario['id_usuario'],
            $usuario['nombre'] ?? '',
            $usuario['apellido'] ?? '',
            $usuario['fecha_nacimiento'] ?? null,
            $usuario['telefono'] ?? null,
            $usuario['email'] ?? null
        );
    }

    public function update(int $id, array $data): bool
    {
        $allowed = [
            'id_usuario',
            'nombre',
            'apellido',
            'fecha_nacimiento',
            'telefono',
            'email',
            'foto'
        ];
        $sets = [];
        $params = [':id' => $id];

        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $sets[] = "`$field` = :$field";
                $params[":$field"] = $data[$field];
            }
        }

        if (empty($sets)) {
            return false;
        }

        $sql = 'UPDATE entrenadores SET ' . implode(', ', $sets) . ' WHERE id_entrenador = :id';
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM entrenadores WHERE id_entrenador = :id');
        return $stmt->execute([':id' => $id]);
    }

    public function deleteByUsuario(int $id_usuario): int
    {
        $stmt = $this->db->prepare(
            "DELETE FROM entrenadores WHERE id_usuario = ?"
        );

        $stmt->execute([$id_usuario]);

        return $stmt->rowCount();
    }

    public function existsByEmail(string $email): bool
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM entrenadores WHERE email = :email');
        $stmt->execute([':email' => $email]);
        return (int)$stmt->fetchColumn() > 0;
    }

    public function search(string $nombre = '', string $apellido = ''): array
    {
        $nombre = strtoupper($nombre);
        $apellido = strtoupper($apellido);

        $sql = "SELECT * FROM entrenadores
                WHERE UCASE(nombre) LIKE :nombre
                AND UCASE(apellido) LIKE :apellido";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':nombre' => "%$nombre%",
            ':apellido' => "%$apellido%",
        ]);

        return $stmt->fetchAll();
    }

    public function getConEquipos(int $id_entrenador): ?array
    {
        $entrenador = $this->getById($id_entrenador);

        if (!$entrenador) {
            return null;
        }

        $stmt = $this->db->prepare(
            "SELECT ee.*, e.club, e.categoria, l.nombre_liga
         FROM entrenador_equipo ee
         INNER JOIN equipos e ON ee.id_equipo = e.id_equipo
         INNER JOIN ligas l ON ee.id_liga = l.id_liga
         WHERE ee.id_entrenador = ?"
        );

        $stmt->execute([$id_entrenador]);

        $entrenador['equipos'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $entrenador;
    }

    public function getByEquipo(int $id_equipo, ?int $id_liga = null): array
    {
        $sql = "
            SELECT 
                en.id_entrenador,
                en.id_usuario,
                en.nombre,
                en.apellido,
                en.fecha_nacimiento,
                en.telefono,
                en.email,
                ee.id_equipo,
                ee.id_liga,
                ee.estado
            FROM entrenadores en
            INNER JOIN entrenador_equipo ee ON en.id_entrenador = ee.id_entrenador
            WHERE ee.id_equipo = ?
        ";

        $params = [$id_equipo];

        if ($id_liga !== null) {
            $sql .= " AND ee.id_liga = ?";
            $params[] = $id_liga;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
