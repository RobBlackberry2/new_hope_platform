<?php
require_once __DIR__ . '/../config/db.php';

class ParentStudentRelation {
    private mysqli $db;

    public function __construct() {
        $this->db = Database::connect();
    }

    public function create(int $parent_user_id, int $student_id): int|false {
        $stmt = $this->db->prepare(
            'INSERT INTO parent_student_relations (parent_user_id, student_id) VALUES (?,?)'
        );
        $stmt->bind_param('ii', $parent_user_id, $student_id);
        if (!$stmt->execute()) return false;
        return (int)$this->db->insert_id;
    }

    public function getStudentsByParent(int $parent_user_id): array {
        $stmt = $this->db->prepare(
            'SELECT s.*, psr.id as relation_id 
             FROM parent_student_relations psr 
             JOIN students s ON psr.student_id = s.id 
             WHERE psr.parent_user_id = ? 
             ORDER BY s.nombre'
        );
        $stmt->bind_param('i', $parent_user_id);
        $stmt->execute();
        $res = $stmt->get_result();
        return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    }

    public function getParentsByStudent(int $student_id): array {
        $stmt = $this->db->prepare(
            'SELECT u.*, psr.id as relation_id 
             FROM parent_student_relations psr 
             JOIN users u ON psr.parent_user_id = u.id 
             WHERE psr.student_id = ? 
             ORDER BY u.nombre'
        );
        $stmt->bind_param('i', $student_id);
        $stmt->execute();
        $res = $stmt->get_result();
        return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    }

    public function hasAccess(int $parent_user_id, int $student_id): bool {
        $stmt = $this->db->prepare(
            'SELECT id FROM parent_student_relations WHERE parent_user_id = ? AND student_id = ? LIMIT 1'
        );
        $stmt->bind_param('ii', $parent_user_id, $student_id);
        $stmt->execute();
        $res = $stmt->get_result();
        return $res && $res->num_rows > 0;
    }

    public function delete(int $id): bool {
        $stmt = $this->db->prepare('DELETE FROM parent_student_relations WHERE id = ?');
        $stmt->bind_param('i', $id);
        return (bool)$stmt->execute();
    }

    public function list(int $limit = 200): array {
        $limit = max(1, min(1000, $limit));
        $res = $this->db->query(
            'SELECT psr.*, u.nombre as parent_name, s.nombre as student_name 
             FROM parent_student_relations psr 
             JOIN users u ON psr.parent_user_id = u.id 
             JOIN students s ON psr.student_id = s.id 
             ORDER BY psr.id DESC 
             LIMIT ' . $limit
        );
        return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    }
}
