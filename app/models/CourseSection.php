<?php
require_once __DIR__ . '/../config/db.php';

class CourseSection {
    private mysqli $db;
    public function __construct() { $this->db = Database::connect(); }

    public function create(int $course_id, string $titulo, string $descripcion, int $semana, int $orden = 0): int|false {
        $stmt = $this->db->prepare('INSERT INTO course_sections (course_id, titulo, descripcion, semana, orden) VALUES (?,?,?,?,?)');
        $stmt->bind_param('issii', $course_id, $titulo, $descripcion, $semana, $orden);
        if (!$stmt->execute()) return false;
        return (int)$this->db->insert_id;
    }

    public function list(int $course_id): array {
        $stmt = $this->db->prepare('SELECT * FROM course_sections WHERE course_id = ? ORDER BY semana ASC, orden ASC, id ASC');
        $stmt->bind_param('i', $course_id);
        $stmt->execute();
        $res = $stmt->get_result();
        return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    }

    public function get(int $id): ?array {
        $stmt = $this->db->prepare('SELECT * FROM course_sections WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        return $row ?: null;
    }

    public function delete(int $id): bool {
        $stmt = $this->db->prepare('DELETE FROM course_sections WHERE id = ?');
        $stmt->bind_param('i', $id);
        return (bool)$stmt->execute();
    }
}
