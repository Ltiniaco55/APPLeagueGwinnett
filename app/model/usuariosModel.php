<?php

declare(strict_types=1);

require_once __DIR__ . '/../core/database.php';

class UsuariosModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    private function limpiarUsuario(?array $usuario): ?array
    {
        if (!$usuario) return null;
        if (isset($usuario['pwd'])) unset($usuario['pwd']);
        return $usuario;
    }

    private function limpiarLista(array $usuarios): array
    {
        foreach ($usuarios as &$u) {
            if (isset($u['pwd'])) unset($u['pwd']);
        }
        return $usuarios;
    }

    public function getAll(): array
    {
        $stmt = $this->db->prepare("SELECT * FROM usuario");
        $stmt->execute();
        return $this->limpiarLista($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function getById(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM usuario WHERE id_usuario = ? LIMIT 1");
        $stmt->execute([$id]);
        $u = $stmt->fetch(PDO::FETCH_ASSOC);
        return $this->limpiarUsuario($u ?: null);
    }

    public function getByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM usuario WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $u = $stmt->fetch(PDO::FETCH_ASSOC);
        return $this->limpiarUsuario($u ?: null);
    }

    private function getByEmailConPwd(string $email): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM usuario WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function emailExists(string $email): bool
    {
        $stmt = $this->db->prepare("SELECT 1 FROM usuario WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        return (bool)$stmt->fetchColumn();
    }

    public function insert(
        string $nombre,
        string $apellido,
        string $fecha_nacimiento,
        string $email,
        string $pwd,
        ?string $telefono = null
    ): int {
        $hashedPwd = password_hash($pwd, PASSWORD_DEFAULT);

        $stmt = $this->db->prepare(
            "INSERT INTO usuario (nombre, apellido, fecha_nacimiento, email, pwd, telefono)
             VALUES (?, ?, ?, ?, ?, ?)"
        );

        $stmt->execute([
            $nombre,
            $apellido,
            $fecha_nacimiento,
            $email,
            $hashedPwd,
            $telefono
        ]);

        return (int)$this->db->lastInsertId();
    }

    public function update(
        int $id,
        string $nombre,
        string $apellido,
        string $fecha_nacimiento,
        string $email,
        ?string $telefono = null
    ): int {
        $stmt = $this->db->prepare(
            "UPDATE usuario
             SET nombre = ?, apellido = ?, fecha_nacimiento = ?, email = ?, telefono = ?
             WHERE id_usuario = ?"
        );

        $stmt->execute([
            $nombre,
            $apellido,
            $fecha_nacimiento,
            $email,
            $telefono,
            $id
        ]);

        return $stmt->rowCount();
    }

    public function updatePassword(int $id, string $newPwd): int
    {
        $hashedPwd = password_hash($newPwd, PASSWORD_DEFAULT);
        $stmt = $this->db->prepare("UPDATE usuario SET pwd = ? WHERE id_usuario = ?");
        $stmt->execute([$hashedPwd, $id]);
        return $stmt->rowCount();
    }

    public function updatePasswordByEmail(string $email, string $newPwd): int
    {
        $hashedPwd = password_hash($newPwd, PASSWORD_DEFAULT);
        $stmt = $this->db->prepare("UPDATE usuario SET pwd = ? WHERE email = ?");
        $stmt->execute([$hashedPwd, $email]);
        return $stmt->rowCount();
    }

    public function countAdmins(): int
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM usuario WHERE rol = 'ADMIN'");
        $stmt->execute();

        return (int)$stmt->fetchColumn();
    }

    public function updateRol(int $id, string $rol): int
    {
        $stmt = $this->db->prepare("UPDATE usuario SET rol = ? WHERE id_usuario = ?");
        $stmt->execute([$rol, $id]);
        return $stmt->rowCount();
    }

    public function updateEquipoStaff(int $id, ?int $id_equipo): int
    {
        $stmt = $this->db->prepare("UPDATE usuario SET id_equipo = ? WHERE id_usuario = ?");
        $stmt->execute([$id_equipo, $id]);
        return $stmt->rowCount();
    }

    public function delete(int $id): int
    {
        $stmt = $this->db->prepare("DELETE FROM usuario WHERE id_usuario = ?");
        $stmt->execute([$id]);
        return $stmt->rowCount();
    }

    public function verifyCredentials(string $email, string $pwd): ?array
    {
        $usuario = $this->getByEmailConPwd($email);

        if (!$usuario || empty($usuario['pwd'])) {
            return null;
        }

        if (password_verify($pwd, (string)$usuario['pwd'])) {
            unset($usuario['pwd']);
            return $usuario;
        }

        return null;
    }

    public function search(string $query): array
    {
        $searchTerm = "%$query%";
        $stmt = $this->db->prepare(
            "SELECT * FROM usuario
             WHERE nombre LIKE ? OR apellido LIKE ? OR email LIKE ?"
        );
        $stmt->execute([$searchTerm, $searchTerm, $searchTerm]);
        return $this->limpiarLista($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

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

        return $this->limpiarLista($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function getByEmailAuth(string $email): ?array
    {
        return $this->getByEmail($email);
    }

    public function guardarCodigoVerificacionEmail(int $idUsuario, string $codeHash, string $expireAt): int
    {
        $stmt = $this->db->prepare(
            "UPDATE usuario
             SET email_verification_code_hash = ?,
                 email_verification_expire = ?,
                 email_verificado = 0
             WHERE id_usuario = ?"
        );
        $stmt->execute([$codeHash, $expireAt, $idUsuario]);
        return $stmt->rowCount();
    }

    public function marcarEmailVerificado(int $idUsuario): int
    {
        $stmt = $this->db->prepare(
            "UPDATE usuario
             SET email_verificado = 1,
                 email_verification_code_hash = NULL,
                 email_verification_expire = NULL
             WHERE id_usuario = ?"
        );
        $stmt->execute([$idUsuario]);
        return $stmt->rowCount();
    }

    public function getCodigoVerificacion(int $idUsuario): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT email_verification_code_hash, email_verification_expire
             FROM usuario
             WHERE id_usuario = ?
             LIMIT 1"
        );
        $stmt->execute([$idUsuario]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function eliminarNoVerificado(string $email): int
    {
        $stmt = $this->db->prepare(
            "DELETE FROM usuario
             WHERE email = ?
               AND (email_verificado = 0 OR email_verificado IS NULL)
             LIMIT 1"
        );
        $stmt->execute([$email]);
        return $stmt->rowCount();
    }

    public function guardarResetPasswordToken(int $idUsuario, string $tokenHash, string $expireAt): int
    {
        $stmt = $this->db->prepare(
            "UPDATE usuario
             SET reset_password_token = ?,
                 reset_password_expire = ?
             WHERE id_usuario = ?"
        );
        $stmt->execute([$tokenHash, $expireAt, $idUsuario]);
        return $stmt->rowCount();
    }

    public function limpiarResetPasswordToken(int $idUsuario): int
    {
        $stmt = $this->db->prepare(
            "UPDATE usuario
             SET reset_password_token = NULL,
                 reset_password_expire = NULL
             WHERE id_usuario = ?"
        );
        $stmt->execute([$idUsuario]);
        return $stmt->rowCount();
    }

    public function getResetPasswordData(int $idUsuario): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT reset_password_token, reset_password_expire
         FROM usuario
         WHERE id_usuario = ?
         LIMIT 1"
        );

        $stmt->execute([$idUsuario]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function guardarCodigoResetPassword(int $idUsuario, string $codigoHash, string $expireAt): int
    {
        return $this->guardarResetPasswordToken($idUsuario, $codigoHash, $expireAt);
    }

    public function limpiarCodigoResetPassword(int $idUsuario): int
    {
        return $this->limpiarResetPasswordToken($idUsuario);
    }

    public function getByOAuth(string $provider, string $oauthId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM usuario WHERE oauth_provider = ? AND oauth_id = ? LIMIT 1"
        );
        $stmt->execute([$provider, $oauthId]);
        $u = $stmt->fetch(PDO::FETCH_ASSOC);
        return $this->limpiarUsuario($u ?: null);
    }

    public function createOAuthUser(
        string $provider,
        string $oauthId,
        string $email,
        string $nombre = '',
        string $apellido = '',
        int $emailVerificado = 1
    ): int {
        $stmt = $this->db->prepare(
            "INSERT INTO usuario
                (nombre, apellido, fecha_nacimiento, email, pwd, telefono, rol, email_verificado, oauth_provider, oauth_id)
             VALUES
                (?, ?, NULL, ?, NULL, NULL, 'USUARIO', ?, ?, ?)"
        );

        $stmt->execute([
            $nombre,
            $apellido,
            $email,
            $emailVerificado,
            $provider,
            $oauthId
        ]);

        return (int)$this->db->lastInsertId();
    }

    public function linkOAuthToExistingUser(int $idUsuario, string $provider, string $oauthId, int $emailVerificado = 1): int
    {
        $stmt = $this->db->prepare(
            "UPDATE usuario
             SET oauth_provider = ?, oauth_id = ?, email_verificado = ?
             WHERE id_usuario = ?"
        );
        $stmt->execute([$provider, $oauthId, $emailVerificado, $idUsuario]);
        return $stmt->rowCount();
    }
}
