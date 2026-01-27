<?php
require_once __DIR__ . '/../config/db.php';

class Enrollment {
    private mysqli $db;

    public function __construct() {
        $this->db = Database::connect();
    }

    public function create(int $student_id, int $year, string $estado = 'ACTIVA'): int|false {
        $stmt = $this->db->prepare('INSERT INTO enrollments (student_id, year, estado) VALUES (?,?,?)');
        $stmt->bind_param('iis', $student_id, $year, $estado);
        if (!$stmt->execute()) return false;
        return (int)$this->db->insert_id;
    }

    public function list(int $limit = 200): array {
        $limit = max(1, min(1000, $limit));
        $sql = 'SELECT e.*, s.nombre AS student_nombre, s.grado, s.seccion
                FROM enrollments e
                JOIN students s ON s.id = e.student_id
                ORDER BY e.id DESC LIMIT ' . $limit;
        $res = $this->db->query($sql);
        return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    }

    public function updateEstado(int $id, string $estado): bool {
        $stmt = $this->db->prepare('UPDATE enrollments SET estado = ? WHERE id = ?');
        $stmt->bind_param('si', $estado, $id);
        return (bool)$stmt->execute();
    }

    public function delete(int $id): bool {
        $stmt = $this->db->prepare('DELETE FROM enrollments WHERE id = ?');
        $stmt->bind_param('i', $id);
        return (bool)$stmt->execute();
    }
}
