<?php
require_once __DIR__ . '/../config/db.php';

class ForumComment {
    private mysqli $db;
    public function __construct() { $this->db = Database::connect(); }

    public function listByForum(int $forum_id): array {
        $stmt = $this->db->prepare(
            'SELECT fc.*, u.nombre AS author_nombre, u.username AS author_username, u.rol AS author_rol
             FROM forum_comments fc
             JOIN users u ON u.id = fc.user_id
             WHERE fc.forum_id = ?
             ORDER BY fc.id ASC'
        );
        $stmt->bind_param('i', $forum_id);
        $stmt->execute();
        $res = $stmt->get_result();
        return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    }

    public function get(int $id): ?array {
        $stmt = $this->db->prepare(
            'SELECT fc.*, f.section_id, s.course_id, c.docente_user_id, c.nombre AS course_nombre, f.title AS forum_title
             FROM forum_comments fc
             JOIN discussion_forums f ON f.id = fc.forum_id
             JOIN course_sections s ON s.id = f.section_id
             JOIN courses c ON c.id = s.course_id
             WHERE fc.id = ? LIMIT 1'
        );
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        return $row ?: null;
    }

    public function create(int $forum_id, int $user_id, string $comment_body): int|false {
        $stmt = $this->db->prepare('INSERT INTO forum_comments (forum_id, user_id, comment_body) VALUES (?,?,?)');
        $stmt->bind_param('iis', $forum_id, $user_id, $comment_body);
        if (!$stmt->execute()) return false;
        return (int)$this->db->insert_id;
    }

    public function delete(int $id): bool {
        $stmt = $this->db->prepare('DELETE FROM forum_comments WHERE id = ?');
        $stmt->bind_param('i', $id);
        return (bool)$stmt->execute();
    }
}
