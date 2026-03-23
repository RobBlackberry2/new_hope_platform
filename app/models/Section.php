<?php
require_once __DIR__ . '/../config/db.php';

class Section
{
    private mysqli $db;

    public function __construct()
    {
        $this->db = Database::connect();
    }

    public function listAll(): array
    {
        $sql = "SELECT s.*, u.nombre AS docente_nombre, u.username AS docente_username, COUNT(st.id) AS total_estudiantes
                FROM sections s
                LEFT JOIN users u ON u.id = s.docente_guia_user_id
                LEFT JOIN students st ON st.seccion = s.codigo AND st.archived_at IS NULL
                GROUP BY s.id
                ORDER BY s.grado ASC, s.codigo ASC";
        $res = $this->db->query($sql);
        return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    }

    public function listByGrade(int $grado): array
    {
        $stmt = $this->db->prepare(
            "SELECT s.*, u.nombre AS docente_nombre, u.username AS docente_username
             FROM sections s
             LEFT JOIN users u ON u.id = s.docente_guia_user_id
             WHERE s.grado = ?
             ORDER BY s.codigo ASC"
        );
        $stmt->bind_param('i', $grado);
        $stmt->execute();
        $res = $stmt->get_result();
        return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    }

    public function getById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT s.*, u.nombre AS docente_nombre, u.username AS docente_username
             FROM sections s
             LEFT JOIN users u ON u.id = s.docente_guia_user_id
             WHERE s.id = ?
             LIMIT 1"
        );
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        return $row ?: null;
    }

    public function getByCode(string $codigo): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT s.*, u.nombre AS docente_nombre, u.username AS docente_username
             FROM sections s
             LEFT JOIN users u ON u.id = s.docente_guia_user_id
             WHERE s.codigo = ?
             LIMIT 1"
        );
        $stmt->bind_param('s', $codigo);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        return $row ?: null;
    }

    public function existsForGrade(string $codigo, int $grado): bool
    {
        $stmt = $this->db->prepare('SELECT id FROM sections WHERE codigo = ? AND grado = ? LIMIT 1');
        $stmt->bind_param('si', $codigo, $grado);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        return (bool) $row;
    }

    public function assignTeacher(int $sectionId, ?int $teacherUserId): bool
    {
        if ($teacherUserId === null || $teacherUserId <= 0) {
            $stmt = $this->db->prepare('UPDATE sections SET docente_guia_user_id = NULL, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
            $stmt->bind_param('i', $sectionId);
            return (bool) $stmt->execute();
        }

        $stmt = $this->db->prepare('UPDATE sections SET docente_guia_user_id = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
        $stmt->bind_param('ii', $teacherUserId, $sectionId);
        return (bool) $stmt->execute();
    }

    public function listTeacherSections(int $teacherUserId): array
    {
        return $this->listAll();
    }

    public function listStudentsBySectionCode(string $codigo): array
    {
        $stmt = $this->db->prepare(
            "SELECT st.*, CONCAT(TRIM(COALESCE(st.nombre, '')), CASE WHEN COALESCE(st.apellidos, '') <> '' THEN ' ' ELSE '' END, TRIM(COALESCE(st.apellidos, ''))) AS nombre_completo
             FROM students st
             WHERE st.seccion = ?
               AND st.archived_at IS NULL
             ORDER BY COALESCE(NULLIF(TRIM(st.apellidos), ''), 'ZZZZZZ') ASC, st.nombre ASC, st.id ASC"
        );
        $stmt->bind_param('s', $codigo);
        $stmt->execute();
        $res = $stmt->get_result();
        return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    }
}
