<?php
require_once __DIR__ . '/../config/db.php';

class Course {
    private mysqli $db;

    public function __construct() {
        $this->db = Database::connect();
    }

    public function create(string $nombre, string $descripcion, int $grado, string $seccion, int $docente_user_id): int|false {
        $stmt = $this->db->prepare('INSERT INTO courses (nombre, descripcion, grado, seccion, docente_user_id) VALUES (?,?,?,?,?)');
        $stmt->bind_param('ssisi', $nombre, $descripcion, $grado, $seccion, $docente_user_id);
        if (!$stmt->execute()) return false;
        return (int)$this->db->insert_id;
    }

    public function list(?int $grado = null, ?int $docente_user_id = null, int $limit = 200): array {
        $limit = max(1, min(1000, $limit));
        $where = [];
        $params = [];
        $types = '';

        if ($grado !== null) { $where[] = 'c.grado = ?'; $params[] = $grado; $types .= 'i'; }
        if ($docente_user_id !== null) { $where[] = 'c.docente_user_id = ?'; $params[] = $docente_user_id; $types .= 'i'; }

        $sql = 'SELECT c.*, u.nombre AS docente_nombre
                FROM courses c
                JOIN users u ON u.id = c.docente_user_id';
        if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
        $sql .= ' ORDER BY c.id DESC LIMIT ' . $limit;

        if ($params) {
            $stmt = $this->db->prepare($sql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $res = $stmt->get_result();
            return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        }

        $res = $this->db->query($sql);
        return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    }

    public function get(int $id): ?array {
        $stmt = $this->db->prepare('SELECT c.*, u.nombre AS docente_nombre FROM courses c JOIN users u ON u.id = c.docente_user_id WHERE c.id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        return $row ?: null;
    }

    public function delete(int $id): bool {
        $stmt = $this->db->prepare('DELETE FROM courses WHERE id = ?');
        $stmt->bind_param('i', $id);
        return (bool)$stmt->execute();
    }

    public function listBySeccion(string $seccion, int $limit = 200): array {
        $limit = max(1, min(1000, $limit));
        $sql = "SELECT c.*, u.nombre AS docente_nombre
                FROM courses c
                LEFT JOIN users u ON u.id = c.docente_user_id
                WHERE c.seccion = ?
                ORDER BY c.id DESC
                LIMIT " . (int)$limit;
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param('s', $seccion);
        $stmt->execute();
        $res = $stmt->get_result();
        return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    }
}
