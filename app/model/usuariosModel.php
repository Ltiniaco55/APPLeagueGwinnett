<?php

class UsuariosModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Obtener todos los usuarios
     */
    public function getAll(): array
    {
        $stmt = $this->db->prepare("SELECT * FROM usuario");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtener usuario por ID
     */
    public function getById(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM usuario WHERE id_usuario = ?");
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Obtener usuario por email
     */
    public function getByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM usuario WHERE email = ?");
        $stmt->execute([$email]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Verificar si el email ya existe
     */
    public function emailExists(string $email): bool
    {
        $stmt = $this->db->prepare("SELECT 1 FROM usuario WHERE email = ?");
        $stmt->execute([$email]);
        return (bool) $stmt->fetchColumn();
    }

    /**
     * Crear nuevo usuario
     */
    public function insert(
        string $nombre,
        string $apellido,
        string $fecha_nacimiento,
        string $sexo,
        string $email,
        string $pwd,
        ?string $telefono = null
    ): int {
        $hashedPwd = password_hash($pwd, PASSWORD_DEFAULT);
        
        $stmt = $this->db->prepare(
            "INSERT INTO usuario (nombre, apellido, fecha_nacimiento, sexo, email, pwd, telefono)
            VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        
        $stmt->execute([
            $nombre,
            $apellido,
            $fecha_nacimiento,
            $sexo,
            $email,
            $hashedPwd,
            $telefono
        ]);
        
        return (int) $this->db->lastInsertId();
    }

    /**
     * Actualizar usuario
     */
    public function update(
        int $id,
        string $nombre,
        string $apellido,
        string $fecha_nacimiento,
        string $sexo,
        string $email,
        ?string $telefono = null
    ): int {
        $stmt = $this->db->prepare(
            "UPDATE usuario SET nombre = ?, apellido = ?, fecha_nacimiento = ?, sexo = ?, email = ?, telefono = ?
            WHERE id_usuario = ?"
        );
        
        $stmt->execute([
            $nombre,
            $apellido,
            $fecha_nacimiento,
            $sexo,
            $email,
            $telefono,
            $id
        ]);
        
        return $stmt->rowCount();
    }

    /**
     * Actualizar contraseña
     */
    public function updatePassword(int $id, string $newPwd): int
    {
        $hashedPwd = password_hash($newPwd, PASSWORD_DEFAULT);
        
        $stmt = $this->db->prepare(
            "UPDATE usuario SET pwd = ? WHERE id_usuario = ?"
        );
        
        $stmt->execute([$hashedPwd, $id]);
        return $stmt->rowCount();
    }

    /**
     * Verificar credenciales (login)
     */
    public function verifyCredentials(string $email, string $pwd): ?array
    {
        $usuario = $this->getByEmail($email);
        
        if ($usuario && password_verify($pwd, $usuario['pwd'])) {
            unset($usuario['pwd']); // No devolver la contraseña
            return $usuario;
        }
        
        return null;
    }

    /**
     * Eliminar usuario
     */
    public function delete(int $id): int
    {
        $stmt = $this->db->prepare("DELETE FROM usuario WHERE id_usuario = ?");
        $stmt->execute([$id]);
        return $stmt->rowCount();
    }

    /**
     * Buscar usuarios por nombre o apellido
     */
    public function search(string $query): array
    {
        $searchTerm = "%$query%";
        $stmt = $this->db->prepare(
            "SELECT * FROM usuario WHERE nombre LIKE ? OR apellido LIKE ? OR email LIKE ?"
        );
        $stmt->execute([$searchTerm, $searchTerm, $searchTerm]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtener usuarios por sexo
     */
    public function getBySexo(string $sexo): array
    {
        $stmt = $this->db->prepare("SELECT * FROM usuario WHERE sexo = ?");
        $stmt->execute([$sexo]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Buscar usuarios por nombre, apellido y/o email usando campos separados
     */
    public function searchFields(string $nombre = '', string $apellido = '', string $email = ''): array
    {
        $sql = "SELECT * FROM usuario WHERE 1=1";
        $params = [];

        if ($nombre !== '') {
            $sql .= " AND nombre LIKE ?";
            $params[] = "%$nombre%";
        }
        if ($apellido !== '') {
            $sql .= " AND apellido LIKE ?";
            $params[] = "%$apellido%";
        }
        if ($email !== '') {
            $sql .= " AND email LIKE ?";
            $params[] = "%$email%";
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
