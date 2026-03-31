<?php
require_once __DIR__ . '/../config/db.php';

class Report
{
    private mysqli $db;

    public const TYPES = [
        'ACADEMIC_NOTES',
        'ACADEMIC_ATTENDANCE',
        'ADMIN_PAYMENTS',
        'ADMIN_ENROLLMENTS_LEVEL',
        'ADMIN_ENROLLMENTS_SECTION',
    ];

    public function __construct()
    {
        $this->db = Database::connect();
    }

    public function listForUser(array $user): array
    {
        $role = $user['rol'] ?? '';
        $userId = (int) ($user['id'] ?? 0);
        if ($role === 'ADMIN') {
            $sql = "SELECT r.*, u.nombre AS created_by_name, uu.nombre AS updated_by_name
                    FROM reports r
                    LEFT JOIN users u ON u.id = r.created_by_user_id
                    LEFT JOIN users uu ON uu.id = r.updated_by_user_id
                    ORDER BY r.updated_at DESC, r.id DESC";
            $res = $this->db->query($sql);
            return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        }

        $stmt = $this->db->prepare(
            "SELECT r.*, u.nombre AS created_by_name, uu.nombre AS updated_by_name
             FROM reports r
             LEFT JOIN users u ON u.id = r.created_by_user_id
             LEFT JOIN users uu ON uu.id = r.updated_by_user_id
             WHERE r.report_type IN ('ACADEMIC_NOTES','ACADEMIC_ATTENDANCE') AND (r.scope_role = 'DOCENTE' OR r.created_by_user_id = ?)
             ORDER BY r.updated_at DESC, r.id DESC"
        );
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $res = $stmt->get_result();
        return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    }

    public function getById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM reports WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        return $row ?: null;
    }

    public function create(string $title, string $type, string $scopeRole, array $filters, int $createdBy): int|false
    {
        $filtersJson = json_encode($filters, JSON_UNESCAPED_UNICODE);
        $stmt = $this->db->prepare('INSERT INTO reports (title, report_type, scope_role, filters_json, created_by_user_id, updated_by_user_id) VALUES (?,?,?,?,?,?)');
        $stmt->bind_param('ssssii', $title, $type, $scopeRole, $filtersJson, $createdBy, $createdBy);
        if (!$stmt->execute()) {
            return false;
        }
        return (int) $this->db->insert_id;
    }

    public function update(int $id, string $title, array $filters, int $updatedBy): bool
    {
        $filtersJson = json_encode($filters, JSON_UNESCAPED_UNICODE);
        $stmt = $this->db->prepare('UPDATE reports SET title = ?, filters_json = ?, updated_by_user_id = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
        $stmt->bind_param('ssii', $title, $filtersJson, $updatedBy, $id);
        return (bool) $stmt->execute();
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM reports WHERE id = ?');
        $stmt->bind_param('i', $id);
        return (bool) $stmt->execute();
    }

    public function getSectionById(int $sectionId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM sections WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $sectionId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        return $row ?: null;
    }

    public function listSectionsForUser(array $user): array
    {
        $role = $user['rol'] ?? '';
        $userId = (int) ($user['id'] ?? 0);
        if ($role === 'ADMIN') {
            $res = $this->db->query("SELECT s.*, u.nombre AS docente_nombre FROM sections s LEFT JOIN users u ON u.id = s.docente_guia_user_id ORDER BY s.grado, s.codigo");
            return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        }
        $stmt = $this->db->prepare("SELECT s.*, u.nombre AS docente_nombre FROM sections s LEFT JOIN users u ON u.id = s.docente_guia_user_id WHERE s.docente_guia_user_id = ? ORDER BY s.grado, s.codigo");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $res = $stmt->get_result();
        return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    }

    public function sectionBelongsToTeacher(int $sectionId, int $teacherUserId): bool
    {
        $stmt = $this->db->prepare('SELECT id FROM sections WHERE id = ? AND docente_guia_user_id = ? LIMIT 1');
        $stmt->bind_param('ii', $sectionId, $teacherUserId);
        $stmt->execute();
        return (bool) $stmt->get_result()->fetch_assoc();
    }

    public function buildDataset(string $type, array $filters, array $user): array
    {
        return match ($type) {
            'ACADEMIC_NOTES' => $this->datasetAcademicNotes($filters, $user),
            'ACADEMIC_ATTENDANCE' => $this->datasetAcademicAttendance($filters, $user),
            'ADMIN_PAYMENTS' => $this->datasetAdminPayments($filters),
            'ADMIN_ENROLLMENTS_LEVEL' => $this->datasetAdminEnrollmentsLevel($filters),
            'ADMIN_ENROLLMENTS_SECTION' => $this->datasetAdminEnrollmentsSection($filters),
            default => ['headers' => [], 'rows' => [], 'meta' => []],
        };
    }

    private function datasetAcademicNotes(array $filters, array $user): array
    {
        $year = (int) ($filters['year'] ?? date('Y'));
        $sectionId = (int) ($filters['section_id'] ?? 0);
        $subject = trim((string) ($filters['subject_name'] ?? ''));
        $teacherId = (int) ($user['id'] ?? 0);
        if (($user['rol'] ?? '') === 'DOCENTE' && $sectionId > 0 && !$this->sectionBelongsToTeacher($sectionId, $teacherId)) {
            return ['headers' => [], 'rows' => [], 'meta' => ['error' => 'Sin acceso a la sección']];
        }

        $sql = "SELECT st.id AS student_id,
                       CONCAT(TRIM(COALESCE(st.nombre,'')), CASE WHEN COALESCE(st.apellidos,'') <> '' THEN ' ' ELSE '' END, TRIM(COALESCE(st.apellidos,''))) AS estudiante,
                       st.seccion,
                       ag.subject_name,
                       ROUND(AVG(CASE WHEN ag.trimester = 1 THEN ag.score END), 2) AS trim1,
                       ROUND(AVG(CASE WHEN ag.trimester = 2 THEN ag.score END), 2) AS trim2,
                       ROUND(AVG(CASE WHEN ag.trimester = 3 THEN ag.score END), 2) AS trim3,
                       ROUND(AVG(ag.score), 2) AS promedio_final
                FROM academic_grades ag
                JOIN students st ON st.id = ag.student_id
                JOIN enrollments e ON e.student_id = st.id AND e.year = ag.academic_year AND e.archived_at IS NULL
                WHERE ag.academic_year = ? AND st.archived_at IS NULL";
        $types = 'i';
        $params = [$year];

        if ($sectionId > 0) {
            $section = $this->getSectionById($sectionId);
            $sectionCode = (string) ($section['codigo'] ?? '');
            $sql .= ' AND st.seccion = ?';
            $types .= 's';
            $params[] = $sectionCode;
        } elseif (($user['rol'] ?? '') === 'DOCENTE') {
            $sql .= ' AND st.seccion IN (SELECT codigo FROM sections WHERE docente_guia_user_id = ?)';
            $types .= 'i';
            $params[] = $teacherId;
        }

        if ($subject !== '') {
            $sql .= ' AND ag.subject_name = ?';
            $types .= 's';
            $params[] = $subject;
        }

        $sql .= " GROUP BY st.id, estudiante, st.seccion, ag.subject_name
                  ORDER BY st.seccion ASC, estudiante ASC, ag.subject_name ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = [];
        while ($r = $res->fetch_assoc()) {
            $rows[] = [
                $r['estudiante'],
                $r['seccion'],
                $r['subject_name'],
                $r['trim1'] ?? '-',
                $r['trim2'] ?? '-',
                $r['trim3'] ?? '-',
                $r['promedio_final'] ?? '-',
            ];
        }
        return [
            'headers' => ['Estudiante', 'Sección', 'Materia', 'I Trim.', 'II Trim.', 'III Trim.', 'Promedio'],
            'rows' => $rows,
            'meta' => ['year' => $year, 'section_id' => $sectionId, 'subject_name' => $subject],
        ];
    }

    private function datasetAcademicAttendance(array $filters, array $user): array
    {
        $sectionId = (int) ($filters['section_id'] ?? 0);
        $from = trim((string) ($filters['date_from'] ?? ''));
        $to = trim((string) ($filters['date_to'] ?? ''));
        $teacherId = (int) ($user['id'] ?? 0);
        if ($sectionId <= 0) {
            return ['headers' => [], 'rows' => [], 'meta' => ['error' => 'Sección requerida']];
        }
        if (($user['rol'] ?? '') === 'DOCENTE' && !$this->sectionBelongsToTeacher($sectionId, $teacherId)) {
            return ['headers' => [], 'rows' => [], 'meta' => ['error' => 'Sin acceso a la sección']];
        }
        $section = $this->getSectionById($sectionId);
        $sql = "SELECT CONCAT(TRIM(COALESCE(st.nombre,'')), CASE WHEN COALESCE(st.apellidos,'') <> '' THEN ' ' ELSE '' END, TRIM(COALESCE(st.apellidos,''))) AS estudiante,
                       st.seccion,
                       COUNT(CASE WHEN ar.status = 'AUSENTE' THEN 1 END) AS ausencias,
                       COUNT(CASE WHEN ar.status = 'TARDIA' THEN 1 END) AS tardias,
                       COUNT(CASE WHEN ar.is_justified = 1 THEN 1 END) AS justificadas
                FROM students st
                LEFT JOIN attendance_records ar ON ar.student_id = st.id AND ar.section_id = ?";
        $types = 'i';
        $params = [$sectionId];
        if ($from !== '') {
            $sql .= ' AND ar.attendance_date >= ?';
            $types .= 's';
            $params[] = $from;
        }
        if ($to !== '') {
            $sql .= ' AND ar.attendance_date <= ?';
            $types .= 's';
            $params[] = $to;
        }
        $sql .= " WHERE st.archived_at IS NULL AND st.seccion = ? GROUP BY st.id, estudiante, st.seccion ORDER BY estudiante ASC";
        $types .= 's';
        $params[] = (string) ($section['codigo'] ?? '');
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = [];
        while ($r = $res->fetch_assoc()) {
            $rows[] = [$r['estudiante'], $r['seccion'], (string)$r['ausencias'], (string)$r['tardias'], (string)$r['justificadas']];
        }
        return [
            'headers' => ['Estudiante', 'Sección', 'Ausencias', 'Tardías', 'Justificadas'],
            'rows' => $rows,
            'meta' => ['section' => $section['codigo'] ?? '', 'date_from' => $from, 'date_to' => $to],
        ];
    }

    private function datasetAdminPayments(array $filters): array
    {
        $year = (int) ($filters['year'] ?? date('Y'));
        $month = trim((string) ($filters['month_key'] ?? ''));
        $isPaid = $filters['is_paid'] ?? '';
        $sql = "SELECT CONCAT(TRIM(COALESCE(st.nombre,'')), CASE WHEN COALESCE(st.apellidos,'') <> '' THEN ' ' ELSE '' END, TRIM(COALESCE(st.apellidos,''))) AS estudiante,
                       st.grado, st.seccion, ep.payment_year, ep.month_key, ep.invoice_number,
                       CASE WHEN ep.is_paid = 1 THEN 'Pagado' ELSE 'Pendiente' END AS estado_pago
                FROM enrollment_payments ep
                JOIN enrollments e ON e.id = ep.enrollment_id AND e.archived_at IS NULL
                JOIN students st ON st.id = e.student_id AND st.archived_at IS NULL
                WHERE ep.payment_year = ?";
        $types = 'i';
        $params = [$year];
        if ($month !== '') {
            $sql .= ' AND ep.month_key = ?';
            $types .= 's';
            $params[] = $month;
        }
        if ($isPaid !== '' && ($isPaid === '0' || $isPaid === '1')) {
            $paid = (int) $isPaid;
            $sql .= ' AND ep.is_paid = ?';
            $types .= 'i';
            $params[] = $paid;
        }
        $sql .= ' ORDER BY st.grado ASC, st.seccion ASC, estudiante ASC, ep.month_key ASC';
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = [];
        while ($r = $res->fetch_assoc()) {
            $rows[] = [$r['estudiante'], (string)$r['grado'], $r['seccion'], (string)$r['payment_year'], $r['month_key'], $r['estado_pago'], (string)($r['invoice_number'] ?? '-')];
        }
        return [
            'headers' => ['Estudiante', 'Nivel', 'Sección', 'Año', 'Cuota', 'Estado', 'Factura'],
            'rows' => $rows,
            'meta' => ['year' => $year, 'month_key' => $month, 'is_paid' => $isPaid],
        ];
    }

    private function datasetAdminEnrollmentsLevel(array $filters): array
    {
        $year = (int) ($filters['year'] ?? date('Y'));
        $sql = "SELECT st.grado,
                       COUNT(*) AS total,
                       SUM(CASE WHEN e.estado = 'ACTIVA' THEN 1 ELSE 0 END) AS activas,
                       SUM(CASE WHEN e.estado = 'PENDIENTE' THEN 1 ELSE 0 END) AS pendientes,
                       SUM(CASE WHEN e.estado = 'BLOQUEADO' THEN 1 ELSE 0 END) AS bloqueados
                FROM enrollments e
                JOIN students st ON st.id = e.student_id AND st.archived_at IS NULL
                WHERE e.archived_at IS NULL AND e.year = ?
                GROUP BY st.grado
                ORDER BY st.grado ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param('i', $year);
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = [];
        while ($r = $res->fetch_assoc()) {
            $rows[] = [(string)$r['grado'], (string)$r['total'], (string)$r['activas'], (string)$r['pendientes'], (string)$r['bloqueados']];
        }
        return [
            'headers' => ['Nivel', 'Total', 'Activas', 'Pendientes', 'Bloqueadas'],
            'rows' => $rows,
            'meta' => ['year' => $year],
        ];
    }

    private function datasetAdminEnrollmentsSection(array $filters): array
    {
        $year = (int) ($filters['year'] ?? date('Y'));
        $sql = "SELECT st.grado, st.seccion,
                       COUNT(*) AS total,
                       SUM(CASE WHEN e.estado = 'ACTIVA' THEN 1 ELSE 0 END) AS activas,
                       SUM(CASE WHEN e.estado = 'PENDIENTE' THEN 1 ELSE 0 END) AS pendientes,
                       SUM(CASE WHEN e.estado = 'BLOQUEADO' THEN 1 ELSE 0 END) AS bloqueados
                FROM enrollments e
                JOIN students st ON st.id = e.student_id AND st.archived_at IS NULL
                WHERE e.archived_at IS NULL AND e.year = ?
                GROUP BY st.grado, st.seccion
                ORDER BY st.grado ASC, st.seccion ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param('i', $year);
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = [];
        while ($r = $res->fetch_assoc()) {
            $rows[] = [(string)$r['grado'], $r['seccion'], (string)$r['total'], (string)$r['activas'], (string)$r['pendientes'], (string)$r['bloqueados']];
        }
        return [
            'headers' => ['Nivel', 'Sección', 'Total', 'Activas', 'Pendientes', 'Bloqueadas'],
            'rows' => $rows,
            'meta' => ['year' => $year],
        ];
    }
}
