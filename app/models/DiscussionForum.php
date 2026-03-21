<?php
require_once __DIR__ . '/../config/db.php';

class DiscussionForum {
    private mysqli $db;
    public function __construct() { $this->db = Database::connect(); }

    public function getBySection(int $section_id): ?array {
        $stmt = $this->db->prepare(
            'SELECT f.*, s.course_id, c.docente_user_id, c.nombre AS course_nombre, s.titulo AS section_titulo
             FROM discussion_forums f
             JOIN course_sections s ON s.id = f.section_id
             JOIN courses c ON c.id = s.course_id
             WHERE f.section_id = ? LIMIT 1'
        );
        $stmt->bind_param('i', $section_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        return $row ?: null;
    }

    public function get(int $id): ?array {
        $stmt = $this->db->prepare(
            'SELECT f.*, s.course_id, c.docente_user_id, c.nombre AS course_nombre, s.titulo AS section_titulo
             FROM discussion_forums f
             JOIN course_sections s ON s.id = f.section_id
             JOIN courses c ON c.id = s.course_id
             WHERE f.id = ? LIMIT 1'
        );
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        return $row ?: null;
    }

    public function upsertBySection(int $section_id, string $title, ?string $description, int $created_by): int|false {
        $existing = $this->getBySection($section_id);
        if ($existing) {
            $id = (int)$existing['id'];
            $stmt = $this->db->prepare('UPDATE discussion_forums SET title = ?, description = ? WHERE id = ?');
            $stmt->bind_param('ssi', $title, $description, $id);
            if (!$stmt->execute()) return false;
            return $id;
        }

        $stmt = $this->db->prepare(
            'INSERT INTO discussion_forums (section_id, title, description, created_by) VALUES (?,?,?,?)'
        );
        $stmt->bind_param('issi', $section_id, $title, $description, $created_by);
        if (!$stmt->execute()) return false;
        return (int)$this->db->insert_id;
    }

    public function delete(int $id): bool {
        $stmt = $this->db->prepare('DELETE FROM discussion_forums WHERE id = ?');
        $stmt->bind_param('i', $id);
        return (bool)$stmt->execute();
    }
}
