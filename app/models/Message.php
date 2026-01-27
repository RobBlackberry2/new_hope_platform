<?php
require_once __DIR__ . '/../config/db.php';

class Message {
    private mysqli $db;

    public function __construct() {
        $this->db = Database::connect();
    }

    public function send(int $from_user_id, ?int $to_user_id, ?string $to_role, string $asunto, string $cuerpo): int|false {
        $stmt = $this->db->prepare('INSERT INTO messages (from_user_id, to_user_id, to_role, asunto, cuerpo) VALUES (?,?,?,?,?)');
        $stmt->bind_param('iisss', $from_user_id, $to_user_id, $to_role, $asunto, $cuerpo);
        if (!$stmt->execute()) return false;
        return (int)$this->db->insert_id;
    }

    public function inbox(int $user_id, string $rol, int $limit = 200): array {
        $limit = max(1, min(1000, $limit));
        $sql = 'SELECT m.*, u.username AS from_username, u.nombre AS from_nombre
                FROM messages m
                JOIN users u ON u.id = m.from_user_id
                WHERE (m.to_user_id = ? OR (m.to_user_id IS NULL AND m.to_role = ?))
                ORDER BY m.id DESC LIMIT ' . $limit;
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param('is', $user_id, $rol);
        $stmt->execute();
        $res = $stmt->get_result();
        return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    }

    public function sent(int $user_id, int $limit = 200): array {
        $limit = max(1, min(1000, $limit));
        $sql = 'SELECT m.*, u.username AS to_username, u.nombre AS to_nombre
                FROM messages m
                LEFT JOIN users u ON u.id = m.to_user_id
                WHERE m.from_user_id = ?
                ORDER BY m.id DESC LIMIT ' . $limit;
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $res = $stmt->get_result();
        return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    }

    public function markRead(int $id, int $user_id): bool {
        $stmt = $this->db->prepare('UPDATE messages SET read_at = NOW() WHERE id = ? AND to_user_id = ?');
        $stmt->bind_param('ii', $id, $user_id);
        return (bool)$stmt->execute();
    }
}
