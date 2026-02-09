<?php
require_once __DIR__ . '/../config/db.php';

class User
{
    private mysqli $db;

    public function __construct()
    {
        $this->db = Database::connect();
    }

    public function usernameExists(string $username): bool
    {
        $stmt = $this->db->prepare('SELECT id FROM users WHERE username = ?');
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $res = $stmt->get_result();
        return $res && $res->num_rows > 0;
    }

    public function create(string $username, string $password, string $nombre, string $correo, ?string $telefono, string $rol = 'ESTUDIANTE'): bool
    {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $this->db->prepare('INSERT INTO users (username, password_hash, nombre, correo, telefono, rol) VALUES (?,?,?,?,?,?)');
        $stmt->bind_param('ssssss', $username, $hash, $nombre, $correo, $telefono, $rol);
        return (bool) $stmt->execute();
    }

    public function login(string $username, string $password): ?array
    {
        $stmt = $this->db->prepare('SELECT id, username, password_hash, nombre, correo, telefono, rol, estado FROM users WHERE username = ? LIMIT 1');
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();

        if (!$row)
            return null;
        if (($row['estado'] ?? 'ACTIVO') !== 'ACTIVO')
            return null;

        if (password_verify($password, $row['password_hash'])) {
            unset($row['password_hash']);
            return $row;
        }
        return null;
    }

    public function getById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT id, username, nombre, correo, telefono, rol, estado, created_at FROM users WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        return $row ?: null;
    }

    public function list(int $limit = 200): array
    {
        $limit = max(1, min(1000, $limit));
        $result = $this->db->query('SELECT id, username, nombre, correo, telefono, rol, estado, created_at FROM users ORDER BY id DESC LIMIT ' . $limit);
        return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    }

    public function update(int $id, string $nombre, string $correo, ?string $telefono): bool
    {
        $stmt = $this->db->prepare('UPDATE users SET nombre = ?, correo = ?, telefono = ? WHERE id = ?');
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

    public function listForStudents(int $limit = 500): array
    {
        $limit = max(1, min(2000, $limit));
        $sql = "SELECT id, username, nombre, rol, estado
                FROM users
                WHERE estado = 'ACTIVO'
                ORDER BY nombre ASC
                LIMIT " . (int) $limit;
        $res = $this->db->query($sql);
        return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    }
}
