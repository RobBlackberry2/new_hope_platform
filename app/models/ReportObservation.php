<?php
require_once __DIR__ . '/../config/db.php';

class ReportObservation {
    private mysqli $db;

    public function __construct() {
        $this->db = Database::connect();
    }

    public function create(array $data): int|false {
        $report_id = isset($data['report_id']) ? (int)$data['report_id'] : null;
        $student_id = (int)($data['student_id'] ?? 0);
        $docente_user_id = (int)($data['docente_user_id'] ?? 0);
        $observacion = $data['observacion'] ?? '';

        if (!$student_id || !$docente_user_id || !$observacion) {
            return false;
        }

        $stmt = $this->db->prepare(
            'INSERT INTO report_observations (report_id, student_id, docente_user_id, observacion) 
             VALUES (?,?,?,?)'
        );
        $stmt->bind_param('iiis', $report_id, $student_id, $docente_user_id, $observacion);
        if (!$stmt->execute()) return false;
        return (int)$this->db->insert_id;
    }

    public function get(int $id): ?array {
        $stmt = $this->db->prepare(
            'SELECT ro.*, s.nombre as student_name, u.nombre as docente_name 
             FROM report_observations ro 
             JOIN students s ON ro.student_id = s.id 
             JOIN users u ON ro.docente_user_id = u.id 
             WHERE ro.id = ?'
        );
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        return $row ?: null;
    }

    public function getByReport(int $report_id): array {
        $stmt = $this->db->prepare(
            'SELECT ro.*, s.nombre as student_name, u.nombre as docente_name 
             FROM report_observations ro 
             JOIN students s ON ro.student_id = s.id 
             JOIN users u ON ro.docente_user_id = u.id 
             WHERE ro.report_id = ? 
             ORDER BY ro.created_at DESC'
        );
        $stmt->bind_param('i', $report_id);
        $stmt->execute();
        $res = $stmt->get_result();
        return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    }

    public function getByStudent(int $student_id, ?int $docente_user_id = null): array {
        if ($docente_user_id) {
            $stmt = $this->db->prepare(
                'SELECT ro.*, u.nombre as docente_name 
                 FROM report_observations ro 
                 JOIN users u ON ro.docente_user_id = u.id 
                 WHERE ro.student_id = ? AND ro.docente_user_id = ? 
                 ORDER BY ro.created_at DESC'
            );
            $stmt->bind_param('ii', $student_id, $docente_user_id);
        } else {
            $stmt = $this->db->prepare(
                'SELECT ro.*, u.nombre as docente_name 
                 FROM report_observations ro 
                 JOIN users u ON ro.docente_user_id = u.id 
                 WHERE ro.student_id = ? 
                 ORDER BY ro.created_at DESC'
            );
            $stmt->bind_param('i', $student_id);
        }
        $stmt->execute();
        $res = $stmt->get_result();
        return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    }

    public function update(int $id, string $observacion): bool {
        if (!$observacion) return false;

        $stmt = $this->db->prepare('UPDATE report_observations SET observacion = ? WHERE id = ?');
        $stmt->bind_param('si', $observacion, $id);
        return (bool)$stmt->execute();
    }

    public function delete(int $id): bool {
        $stmt = $this->db->prepare('DELETE FROM report_observations WHERE id = ?');
        $stmt->bind_param('i', $id);
        return (bool)$stmt->execute();
    }

    public function list(int $limit = 200): array {
        $limit = max(1, min(1000, $limit));
        $res = $this->db->query(
            'SELECT ro.*, s.nombre as student_name, u.nombre as docente_name 
             FROM report_observations ro 
             JOIN students s ON ro.student_id = s.id 
             JOIN users u ON ro.docente_user_id = u.id 
             ORDER BY ro.created_at DESC 
             LIMIT ' . $limit
        );
        return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    }
}
