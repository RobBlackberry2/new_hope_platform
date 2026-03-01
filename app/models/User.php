<?php
require_once __DIR__ . '/../config/db.php';

class User
{
    private mysqli $db;

    public function __construct()
    {
        $this->db = Database::connect();
    }

    /* =========================
       BÁSICO
       ========================= */

    public function usernameExists(string $username): bool
    {
        $stmt = $this->db->prepare('SELECT id FROM users WHERE username = ?');
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $res = $stmt->get_result();
        return $res && $res->num_rows > 0;
    }

    public function create(
        string $username,
        string $password,
        string $nombre,
        string $correo,
        ?string $telefono,
        string $rol = 'ESTUDIANTE'
    ): bool {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $this->db->prepare(
            'INSERT INTO users (username, password_hash, nombre, correo, telefono, rol)
             VALUES (?,?,?,?,?,?)'
        );
        $stmt->bind_param('ssssss', $username, $hash, $nombre, $correo, $telefono, $rol);
        return (bool) $stmt->execute();
    }

    public function getByUsernameRaw(string $username): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, username, password_hash, nombre, correo, telefono, rol, estado,
                    reset_token, reset_expires_at, created_at
             FROM users
             WHERE username = ?
             LIMIT 1'
        );
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        return $row ?: null;
    }

    /* =========================
       LISTADOS
       ========================= */

    public function list(int $limit = 200): array
    {
        $limit = max(1, $limit);
        $stmt = $this->db->prepare(
            'SELECT id, username, nombre, correo, telefono, rol, estado, created_at
             FROM users
             ORDER BY id DESC
             LIMIT ?'
        );
        $stmt->bind_param('i', $limit);
        $stmt->execute();
        $res = $stmt->get_result();
        return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    }

    public function listActive(int $limit = 1000): array
    {
        $limit = max(1, $limit);
        $stmt = $this->db->prepare(
            "SELECT id, username, nombre, correo, telefono, rol, estado, created_at
             FROM users
             WHERE estado = 'ACTIVO'
             ORDER BY nombre ASC
             LIMIT ?"
        );
        $stmt->bind_param('i', $limit);
        $stmt->execute();
        $res = $stmt->get_result();
        return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    }

    public function listForStudents(int $limit = 500): array
    {
        // Para usar en matriculas: usuarios activos (cualquier rol) ordenados por nombre
        $limit = max(1, $limit);
        $stmt = $this->db->prepare(
            "SELECT id, username, nombre, correo, rol
             FROM users
             WHERE estado = 'ACTIVO'
             ORDER BY nombre ASC
             LIMIT ?"
        );
        $stmt->bind_param('i', $limit);
        $stmt->execute();
        $res = $stmt->get_result();
        return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    }

    public function listDocentes(int $limit = 500): array
    {
        $limit = max(1, $limit);
        $stmt = $this->db->prepare(
            "SELECT id, username, nombre, correo, rol, estado
             FROM users
             WHERE rol = 'DOCENTE'
             ORDER BY nombre ASC
             LIMIT ?"
        );
        $stmt->bind_param('i', $limit);
        $stmt->execute();
        $res = $stmt->get_result();
        return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    }

    /* =========================
       MÉTODOS DE EDICIÓN
       ========================= */

    public function update(
        int $id,
        string $nombre,
        string $correo,
        ?string $telefono
    ): bool {
        $stmt = $this->db->prepare(
            'UPDATE users
             SET nombre = ?, correo = ?, telefono = ?
             WHERE id = ?'
        );
        $stmt->bind_param('sssi', $nombre, $correo, $telefono, $id);
        return (bool) $stmt->execute();
    }

    public function setRole(int $id, string $rol): bool
    {
        $stmt = $this->db->prepare('UPDATE users SET rol = ? WHERE id = ?');
        $stmt->bind_param('si', $rol, $id);
        return (bool) $stmt->execute();
    }

    public function setEstado(int $id, string $estado): bool
    {
        $stmt = $this->db->prepare('UPDATE users SET estado = ? WHERE id = ?');
        $stmt->bind_param('si', $estado, $id);
        return (bool) $stmt->execute();
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM users WHERE id = ?');
        $stmt->bind_param('i', $id);
        return (bool) $stmt->execute();
    }

    /* =========================
       🔐 MÉTODOS PARA RECUPERAR CONTRASEÑA
       ========================= */

    public function getByCorreo(string $correo): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE correo = ? LIMIT 1');
        $stmt->bind_param('s', $correo);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        return $row ?: null;
    }

    public function saveResetToken(int $id, string $token, string $expires): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE users
             SET reset_token = ?, reset_expires_at = ?
             WHERE id = ?'
        );
        $stmt->bind_param('ssi', $token, $expires, $id);
        return (bool) $stmt->execute();
    }

    public function getByResetToken(string $token): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM users WHERE reset_token = ? LIMIT 1'
        );
        $stmt->bind_param('s', $token);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        return $row ?: null;
    }

    public function updatePasswordById(int $id, string $hash): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE users
             SET password_hash = ?
             WHERE id = ?'
        );
        $stmt->bind_param('si', $hash, $id);
        return (bool) $stmt->execute();
    }

    public function clearResetToken(int $id): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE users
             SET reset_token = NULL,
                 reset_expires_at = NULL
             WHERE id = ?'
        );
        $stmt->bind_param('i', $id);
        return (bool) $stmt->execute();
    }
}