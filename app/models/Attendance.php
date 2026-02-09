<?php
require_once __DIR__ . '/../config/db.php';

class Attendance {
    private mysqli $db;

    public function __construct() {
        $this->db = Database::connect();
    }

    public function create(array $data): int|false {
        $student_id = (int)($data['student_id'] ?? 0);
        $fecha = $data['fecha'] ?? ''; // YYYY-MM-DD
        $estado = $data['estado'] ?? 'PRESENTE';
        $course_id = isset($data['course_id']) ? (int)$data['course_id'] : null;

        if (!$student_id || !$fecha) {
            return false;
        }

        $stmt = $this->db->prepare(
            'INSERT INTO attendance (student_id, fecha, estado, course_id) 
             VALUES (?,?,?,?) 
             ON DUPLICATE KEY UPDATE estado = VALUES(estado)'
        );
        $stmt->bind_param('issi', $student_id, $fecha, $estado, $course_id);
        if (!$stmt->execute()) return false;
        return (int)$this->db->insert_id;
    }

    public function get(int $id): ?array {
        $stmt = $this->db->prepare('SELECT * FROM attendance WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        return $row ?: null;
    }

    public function getByStudent(int $student_id, string $fecha_inicio, string $fecha_fin): array {
        $stmt = $this->db->prepare(
            'SELECT a.*, c.nombre as course_name 
             FROM attendance a 
             LEFT JOIN courses c ON a.course_id = c.id 
             WHERE a.student_id = ? AND a.fecha BETWEEN ? AND ? 
             ORDER BY a.fecha DESC'
        );
        $stmt->bind_param('iss', $student_id, $fecha_inicio, $fecha_fin);
        $stmt->execute();
        $res = $stmt->get_result();
        return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    }

    public function getByDate(string $fecha, ?int $course_id = null): array {
        if ($course_id) {
            $stmt = $this->db->prepare(
                'SELECT a.*, s.nombre as student_name 
                 FROM attendance a 
                 JOIN students s ON a.student_id = s.id 
                 WHERE a.fecha = ? AND a.course_id = ? 
                 ORDER BY s.nombre'
            );
            $stmt->bind_param('si', $fecha, $course_id);
        } else {
            $stmt = $this->db->prepare(
                'SELECT a.*, s.nombre as student_name 
                 FROM attendance a 
                 JOIN students s ON a.student_id = s.id 
                 WHERE a.fecha = ? 
                 ORDER BY s.nombre'
            );
            $stmt->bind_param('s', $fecha);
        }
        $stmt->execute();
        $res = $stmt->get_result();
        return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    }

    public function update(int $id, array $data): bool {
        $estado = $data['estado'] ?? 'PRESENTE';
        
        $stmt = $this->db->prepare('UPDATE attendance SET estado = ? WHERE id = ?');
        $stmt->bind_param('si', $estado, $id);
        return (bool)$stmt->execute();
    }

    public function delete(int $id): bool {
        $stmt = $this->db->prepare('DELETE FROM attendance WHERE id = ?');
        $stmt->bind_param('i', $id);
        return (bool)$stmt->execute();
    }

    public function getSummary(int $student_id, string $periodo_inicio, string $periodo_fin): array {
        $stmt = $this->db->prepare(
            'SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN estado = "PRESENTE" THEN 1 ELSE 0 END) as presentes,
                SUM(CASE WHEN estado = "AUSENTE" THEN 1 ELSE 0 END) as ausentes,
                SUM(CASE WHEN estado = "TARDANZA" THEN 1 ELSE 0 END) as tardanzas
             FROM attendance 
             WHERE student_id = ? AND fecha BETWEEN ? AND ?'
        );
        $stmt->bind_param('iss', $student_id, $periodo_inicio, $periodo_fin);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        return $row ?: ['total' => 0, 'presentes' => 0, 'ausentes' => 0, 'tardanzas' => 0];
    }

    public function list(int $limit = 200): array {
        $limit = max(1, min(1000, $limit));
        $res = $this->db->query(
            'SELECT a.*, s.nombre as student_name, c.nombre as course_name 
             FROM attendance a 
             JOIN students s ON a.student_id = s.id 
             LEFT JOIN courses c ON a.course_id = c.id 
             ORDER BY a.fecha DESC, s.nombre 
             LIMIT ' . $limit
        );
        return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    }
}
