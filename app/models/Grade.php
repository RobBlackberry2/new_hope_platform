<?php
require_once __DIR__ . '/../config/db.php';

class Grade {
    private mysqli $db;

    public function __construct() {
        $this->db = Database::connect();
    }

    public function create(array $data): int|false {
        $student_id = (int)($data['student_id'] ?? 0);
        $course_id = (int)($data['course_id'] ?? 0);
        $periodo = $data['periodo'] ?? '';
        $calificacion = (float)($data['calificacion'] ?? 0);
        $docente_user_id = (int)($data['docente_user_id'] ?? 0);

        if (!$student_id || !$course_id || !$periodo || !$docente_user_id) {
            return false;
        }

        $stmt = $this->db->prepare(
            'INSERT INTO grades (student_id, course_id, periodo, calificacion, docente_user_id) 
             VALUES (?,?,?,?,?)'
        );
        $stmt->bind_param('iisdi', $student_id, $course_id, $periodo, $calificacion, $docente_user_id);
        if (!$stmt->execute()) return false;
        return (int)$this->db->insert_id;
    }

    public function get(int $id): ?array {
        $stmt = $this->db->prepare('SELECT * FROM grades WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        return $row ?: null;
    }

    public function getByStudent(int $student_id, ?string $periodo = null): array {
        if ($periodo) {
            $stmt = $this->db->prepare(
                'SELECT g.*, c.nombre as course_name, u.nombre as docente_name 
                 FROM grades g 
                 JOIN courses c ON g.course_id = c.id 
                 JOIN users u ON g.docente_user_id = u.id 
                 WHERE g.student_id = ? AND g.periodo = ? 
                 ORDER BY c.nombre'
            );
            $stmt->bind_param('is', $student_id, $periodo);
        } else {
            $stmt = $this->db->prepare(
                'SELECT g.*, c.nombre as course_name, u.nombre as docente_name 
                 FROM grades g 
                 JOIN courses c ON g.course_id = c.id 
                 JOIN users u ON g.docente_user_id = u.id 
                 WHERE g.student_id = ? 
                 ORDER BY g.periodo DESC, c.nombre'
            );
            $stmt->bind_param('i', $student_id);
        }
        $stmt->execute();
        $res = $stmt->get_result();
        return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    }

    public function getByCourse(int $course_id, ?string $periodo = null): array {
        if ($periodo) {
            $stmt = $this->db->prepare(
                'SELECT g.*, s.nombre as student_name 
                 FROM grades g 
                 JOIN students s ON g.student_id = s.id 
                 WHERE g.course_id = ? AND g.periodo = ? 
                 ORDER BY s.nombre'
            );
            $stmt->bind_param('is', $course_id, $periodo);
        } else {
            $stmt = $this->db->prepare(
                'SELECT g.*, s.nombre as student_name 
                 FROM grades g 
                 JOIN students s ON g.student_id = s.id 
                 WHERE g.course_id = ? 
                 ORDER BY g.periodo DESC, s.nombre'
            );
            $stmt->bind_param('i', $course_id);
        }
        $stmt->execute();
        $res = $stmt->get_result();
        return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    }

    public function update(int $id, array $data): bool {
        $calificacion = (float)($data['calificacion'] ?? 0);
        $periodo = $data['periodo'] ?? '';
        
        if (!$periodo) return false;

        $stmt = $this->db->prepare(
            'UPDATE grades SET calificacion = ?, periodo = ? WHERE id = ?'
        );
        $stmt->bind_param('dsi', $calificacion, $periodo, $id);
        return (bool)$stmt->execute();
    }

    public function delete(int $id): bool {
        $stmt = $this->db->prepare('DELETE FROM grades WHERE id = ?');
        $stmt->bind_param('i', $id);
        return (bool)$stmt->execute();
    }

    public function getAverageByStudent(int $student_id, ?string $periodo = null): float {
        if ($periodo) {
            $stmt = $this->db->prepare(
                'SELECT AVG(calificacion) as promedio FROM grades WHERE student_id = ? AND periodo = ?'
            );
            $stmt->bind_param('is', $student_id, $periodo);
        } else {
            $stmt = $this->db->prepare(
                'SELECT AVG(calificacion) as promedio FROM grades WHERE student_id = ?'
            );
            $stmt->bind_param('i', $student_id);
        }
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        return (float)($row['promedio'] ?? 0);
    }

    public function list(int $limit = 200): array {
        $limit = max(1, min(1000, $limit));
        $res = $this->db->query(
            'SELECT g.*, s.nombre as student_name, c.nombre as course_name 
             FROM grades g 
             JOIN students s ON g.student_id = s.id 
             JOIN courses c ON g.course_id = c.id 
             ORDER BY g.created_at DESC 
             LIMIT ' . $limit
        );
        return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    }
}
