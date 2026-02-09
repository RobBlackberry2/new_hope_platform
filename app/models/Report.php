<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/Grade.php';
require_once __DIR__ . '/Attendance.php';

class Report {
    private mysqli $db;

    public function __construct() {
        $this->db = Database::connect();
    }

    public function create(array $data): int|false {
        $tipo = $data['tipo'] ?? '';
        $titulo = $data['titulo'] ?? '';
        $descripcion = $data['descripcion'] ?? null;
        $periodo_inicio = $data['periodo_inicio'] ?? null;
        $periodo_fin = $data['periodo_fin'] ?? null;
        $datos_json = isset($data['datos_json']) ? json_encode($data['datos_json']) : null;
        $created_by = (int)($data['created_by'] ?? 0);

        if (!$tipo || !$titulo || !$created_by) {
            return false;
        }

        $stmt = $this->db->prepare(
            'INSERT INTO reports (tipo, titulo, descripcion, periodo_inicio, periodo_fin, datos_json, created_by) 
             VALUES (?,?,?,?,?,?,?)'
        );
        $stmt->bind_param('ssssssi', $tipo, $titulo, $descripcion, $periodo_inicio, $periodo_fin, $datos_json, $created_by);
        if (!$stmt->execute()) return false;
        return (int)$this->db->insert_id;
    }

    public function get(int $id): ?array {
        $stmt = $this->db->prepare(
            'SELECT r.*, u.nombre as creator_name 
             FROM reports r 
             JOIN users u ON r.created_by = u.id 
             WHERE r.id = ?'
        );
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if ($row && $row['datos_json']) {
            $row['datos_json'] = json_decode($row['datos_json'], true);
        }
        return $row ?: null;
    }

    public function list(array $filters = [], int $limit = 200): array {
        $limit = max(1, min(1000, $limit));
        $where = ['1=1'];
        $params = [];
        $types = '';

        if (!empty($filters['tipo'])) {
            $where[] = 'r.tipo = ?';
            $params[] = $filters['tipo'];
            $types .= 's';
        }

        if (!empty($filters['estado'])) {
            $where[] = 'r.estado = ?';
            $params[] = $filters['estado'];
            $types .= 's';
        }

        if (!empty($filters['periodo_inicio'])) {
            $where[] = 'r.periodo_inicio >= ?';
            $params[] = $filters['periodo_inicio'];
            $types .= 's';
        }

        if (!empty($filters['periodo_fin'])) {
            $where[] = 'r.periodo_fin <= ?';
            $params[] = $filters['periodo_fin'];
            $types .= 's';
        }

        $sql = 'SELECT r.*, u.nombre as creator_name 
                FROM reports r 
                JOIN users u ON r.created_by = u.id 
                WHERE ' . implode(' AND ', $where) . ' 
                ORDER BY r.created_at DESC 
                LIMIT ' . $limit;

        if ($params) {
            $stmt = $this->db->prepare($sql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $res = $stmt->get_result();
        } else {
            $res = $this->db->query($sql);
        }

        $reports = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        foreach ($reports as &$report) {
            if ($report['datos_json']) {
                $report['datos_json'] = json_decode($report['datos_json'], true);
            }
        }
        return $reports;
    }

    public function update(int $id, array $data): bool {
        $titulo = $data['titulo'] ?? '';
        $descripcion = $data['descripcion'] ?? null;
        $periodo_inicio = $data['periodo_inicio'] ?? null;
        $periodo_fin = $data['periodo_fin'] ?? null;
        
        // Guardar versión anterior
        $old = $this->get($id);
        if ($old) {
            $versions = $old['datos_json']['versions'] ?? [];
            $versions[] = [
                'timestamp' => date('Y-m-d H:i:s'),
                'titulo' => $old['titulo'],
                'descripcion' => $old['descripcion'],
                'periodo_inicio' => $old['periodo_inicio'],
                'periodo_fin' => $old['periodo_fin']
            ];
            $datos_json = json_encode(['versions' => $versions]);
        } else {
            $datos_json = null;
        }

        if (!$titulo) return false;

        $stmt = $this->db->prepare(
            'UPDATE reports SET titulo = ?, descripcion = ?, periodo_inicio = ?, periodo_fin = ?, datos_json = ? WHERE id = ?'
        );
        $stmt->bind_param('sssssi', $titulo, $descripcion, $periodo_inicio, $periodo_fin, $datos_json, $id);
        return (bool)$stmt->execute();
    }

    public function archive(int $id): bool {
        $stmt = $this->db->prepare('UPDATE reports SET estado = "ARCHIVADO" WHERE id = ?');
        $stmt->bind_param('i', $id);
        return (bool)$stmt->execute();
    }

    public function restore(int $id): bool {
        $stmt = $this->db->prepare('UPDATE reports SET estado = "ACTIVO" WHERE id = ?');
        $stmt->bind_param('i', $id);
        return (bool)$stmt->execute();
    }

    public function delete(int $id): bool {
        $stmt = $this->db->prepare('DELETE FROM reports WHERE id = ?');
        $stmt->bind_param('i', $id);
        return (bool)$stmt->execute();
    }

    public function generateAcademicReport(int $student_id, string $periodo): array {
        $gradeModel = new Grade();
        $attendanceModel = new Attendance();

        $grades = $gradeModel->getByStudent($student_id, $periodo);
        $average = $gradeModel->getAverageByStudent($student_id, $periodo);
        
        // Calcular rango de fechas del periodo (ejemplo: I = enero-abril, II = mayo-agosto, III = septiembre-diciembre)
        $year = date('Y');
        $ranges = [
            'I' => ['inicio' => "$year-01-01", 'fin' => "$year-04-30"],
            'II' => ['inicio' => "$year-05-01", 'fin' => "$year-08-31"],
            'III' => ['inicio' => "$year-09-01", 'fin' => "$year-12-31"]
        ];
        $range = $ranges[$periodo] ?? ['inicio' => "$year-01-01", 'fin' => "$year-12-31"];
        
        $attendance = $attendanceModel->getSummary($student_id, $range['inicio'], $range['fin']);

        return [
            'student_id' => $student_id,
            'periodo' => $periodo,
            'grades' => $grades,
            'average' => $average,
            'attendance' => $attendance
        ];
    }

    public function generateInstitutionalReport(array $params): array {
        $tipo = $params['tipo'] ?? 'general'; // 'por_area', 'comparativo_anual', 'general'
        $periodo = $params['periodo'] ?? '';
        
        $gradeModel = new Grade();
        
        if ($tipo === 'comparativo_anual') {
            // Comparar promedios entre diferentes periodos
            $periodos = ['I', 'II', 'III'];
            $data = [];
            foreach ($periodos as $p) {
                $stmt = $this->db->prepare(
                    'SELECT AVG(calificacion) as promedio FROM grades WHERE periodo = ?'
                );
                $stmt->bind_param('s', $p);
                $stmt->execute();
                $row = $stmt->get_result()->fetch_assoc();
                $data[$p] = (float)($row['promedio'] ?? 0);
            }
            return ['tipo' => 'comparativo_anual', 'data' => $data];
        } else {
            // Reporte general
            $stmt = $this->db->query(
                'SELECT 
                    COUNT(DISTINCT s.id) as total_estudiantes,
                    COUNT(DISTINCT c.id) as total_cursos,
                    AVG(g.calificacion) as promedio_general
                 FROM students s
                 LEFT JOIN grades g ON s.id = g.student_id
                 LEFT JOIN courses c ON g.course_id = c.id'
            );
            $row = $stmt->fetch_assoc();
            return ['tipo' => 'general', 'data' => $row];
        }
    }

    public function generateGroupReport(int $course_id, string $periodo): array {
        $gradeModel = new Grade();
        $grades = $gradeModel->getByCourse($course_id, $periodo);
        
        $total = count($grades);
        $sum = array_sum(array_column($grades, 'calificacion'));
        $average = $total > 0 ? $sum / $total : 0;
        
        return [
            'course_id' => $course_id,
            'periodo' => $periodo,
            'grades' => $grades,
            'average' => $average,
            'total_students' => $total
        ];
    }

    public function generateComparativeReport(array $params): array {
        $tipo = $params['tipo'] ?? 'grupos'; // 'grupos' o 'estudiantes'
        $periodo = $params['periodo'] ?? '';
        
        if ($tipo === 'grupos') {
            $course_ids = $params['course_ids'] ?? [];
            $data = [];
            foreach ($course_ids as $course_id) {
                $report = $this->generateGroupReport($course_id, $periodo);
                $data[] = $report;
            }
            return ['tipo' => 'comparativo_grupos', 'data' => $data];
        } else {
            $student_ids = $params['student_ids'] ?? [];
            $gradeModel = new Grade();
            $data = [];
            foreach ($student_ids as $student_id) {
                $grades = $gradeModel->getByStudent($student_id, $periodo);
                $average = $gradeModel->getAverageByStudent($student_id, $periodo);
                $data[] = ['student_id' => $student_id, 'average' => $average, 'grades' => $grades];
            }
            return ['tipo' => 'comparativo_estudiantes', 'data' => $data];
        }
    }
}
