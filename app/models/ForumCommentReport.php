<?php
require_once __DIR__ . '/../config/db.php';

class ForumCommentReport {
    private mysqli $db;
    public function __construct() { $this->db = Database::connect(); }

    public function create(int $comment_id, int $reported_by_user_id, ?string $reason): int|false {
        $stmt = $this->db->prepare(
            'INSERT INTO forum_comment_reports (comment_id, reported_by_user_id, reason) VALUES (?,?,?)'
        );
        $stmt->bind_param('iis', $comment_id, $reported_by_user_id, $reason);
        if (!$stmt->execute()) return false;
        return (int)$this->db->insert_id;
    }

    public function alreadyReportedByUser(int $comment_id, int $reported_by_user_id): bool {
        $stmt = $this->db->prepare(
            'SELECT id FROM forum_comment_reports WHERE comment_id = ? AND reported_by_user_id = ? LIMIT 1'
        );
        $stmt->bind_param('ii', $comment_id, $reported_by_user_id);
        $stmt->execute();
        return (bool)$stmt->get_result()->fetch_assoc();
    }
}
