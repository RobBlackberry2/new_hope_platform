<?php
require_once __DIR__ . '/../config/db.php';

class CourseResource
{
    private mysqli $db;
    public function __construct()
    {
        $this->db = Database::connect();
    }

    public function create(
        int $section_id,
        string $stored_name,
        string $original_name,
        string $mime,
        int $size,
        int $uploaded_by,
        string $storage_provider,
        ?string $storage_item_id,
        ?string $public_url
    ): int|false {
        $stmt = $this->db->prepare(
            'INSERT INTO course_resources
     (section_id, stored_name, original_name, mime, size, uploaded_by, storage_provider, storage_item_id, public_url)
     VALUES (?,?,?,?,?,?,?,?,?)'
        );
        $stmt->bind_param('isssiiiss', $section_id, $stored_name, $original_name, $mime, $size, $uploaded_by, $storage_provider, $storage_item_id, $public_url);
        if (!$stmt->execute())
            return false;
        return (int) $this->db->insert_id;
    }

    public function listBySection(int $section_id): array
    {
        $stmt = $this->db->prepare(
            'SELECT r.*, u.nombre AS uploaded_by_nombre
             FROM course_resources r
             JOIN users u ON u.id = r.uploaded_by
             WHERE r.section_id = ?
             ORDER BY r.id DESC'
        );
        $stmt->bind_param('i', $section_id);
        $stmt->execute();
        $res = $stmt->get_result();
        return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    }

    // NUEVO: para permisos/descarga
    public function getWithCourse(int $resource_id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT r.*, s.course_id, c.docente_user_id
             FROM course_resources r
             JOIN course_sections s ON s.id = r.section_id
             JOIN courses c ON c.id = s.course_id
             WHERE r.id = ?
             LIMIT 1'
        );
        $stmt->bind_param('i', $resource_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        return $row ?: null;
    }

    public function delete(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM course_resources WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if (!$row)
            return null;

        $stmt2 = $this->db->prepare('DELETE FROM course_resources WHERE id = ?');
        $stmt2->bind_param('i', $id);
        $stmt2->execute();
        return $row;
    }
}