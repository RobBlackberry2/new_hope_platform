<?php
require_once __DIR__ . '/../config/db.php';

class AcademicManagement
{
    private mysqli $db;

    public const SUBJECTS = [
        'Artes Plásticas',
        'Biología',
        'Educación Física',
        'Educación Hogar',
        'Español',
        'Filosofía',
        'Física',
        'Francés',
        'Informática',
        'Inglés',
        'Matemáticas',
        'Música',
        'Psicología',
    ];

    public function __construct()
    {
        $this->db = Database::connect();
    }

    public function listSectionsForUser(array $user): array
    {
        $role = $user['rol'] ?? '';
        $userId = (int) ($user['id'] ?? 0);

        if ($role === 'ADMIN') {
            $sql = "SELECT s.*, u.nombre AS docente_nombre, COUNT(st.id) AS total_estudiantes
                    FROM sections s
                    LEFT JOIN users u ON u.id = s.docente_guia_user_id
                    LEFT JOIN students st ON st.seccion = s.codigo AND st.archived_at IS NULL
                    GROUP BY s.id
                    ORDER BY s.grado ASC, s.codigo ASC";
            $res = $this->db->query($sql);
            return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        }

        $stmt = $this->db->prepare(
            "SELECT s.*, u.nombre AS docente_nombre, COUNT(st.id) AS total_estudiantes
             FROM sections s
             LEFT JOIN users u ON u.id = s.docente_guia_user_id
             LEFT JOIN students st ON st.seccion = s.codigo AND st.archived_at IS NULL
             WHERE s.docente_guia_user_id = ?
             GROUP BY s.id
             ORDER BY s.grado ASC, s.codigo ASC"
        );
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $res = $stmt->get_result();
        return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    }

    public function getSectionById(int $sectionId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT s.*, u.nombre AS docente_nombre
             FROM sections s
             LEFT JOIN users u ON u.id = s.docente_guia_user_id
             WHERE s.id = ?
             LIMIT 1"
        );
        $stmt->bind_param('i', $sectionId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        return $row ?: null;
    }

    public function listStudentsBySection(int $sectionId): array
    {
        $stmt = $this->db->prepare(
            "SELECT DISTINCT st.id, st.user_id, st.nombre, st.apellidos, CONCAT(TRIM(COALESCE(st.nombre, '')), CASE WHEN COALESCE(st.apellidos, '') <> '' THEN ' ' ELSE '' END, TRIM(COALESCE(st.apellidos, ''))) AS nombre_completo, st.cedula, st.grado, st.seccion
             FROM students st
             JOIN sections sec ON sec.id = ? AND sec.codigo = st.seccion
             WHERE st.archived_at IS NULL
             ORDER BY COALESCE(NULLIF(TRIM(st.apellidos), ''), 'ZZZZZZ') ASC, st.nombre ASC, st.id ASC"
        );
        $stmt->bind_param('i', $sectionId);
        $stmt->execute();
        $res = $stmt->get_result();
        return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    }

    public function getSectionByCode(string $code): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM sections WHERE codigo = ? LIMIT 1");
        $stmt->bind_param('s', $code);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        return $row ?: null;
    }

    public function getStudentById(int $studentId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM students WHERE id = ? AND archived_at IS NULL LIMIT 1');
        $stmt->bind_param('i', $studentId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        return $row ?: null;
    }

    public function getStudentYearBounds(int $studentId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT MIN(year) AS first_year, MAX(year) AS last_year
             FROM enrollments
             WHERE student_id = ?
               AND archived_at IS NULL"
        );
        $stmt->bind_param('i', $studentId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if (!$row || empty($row['first_year'])) {
            return null;
        }
        return [
            'first_year' => (int) $row['first_year'],
            'last_year' => (int) $row['last_year'],
        ];
    }

    public function studentIsEnrolledInYear(int $studentId, int $year): bool
    {
        $stmt = $this->db->prepare(
            "SELECT id
             FROM enrollments
             WHERE student_id = ?
               AND year = ?
               AND archived_at IS NULL
               AND estado <> 'BLOQUEADO'
             LIMIT 1"
        );
        $stmt->bind_param('ii', $studentId, $year);
        $stmt->execute();
        return (bool) $stmt->get_result()->fetch_assoc();
    }

    public function listRubrics(string $subject, int $trimester): array
    {
        $stmt = $this->db->prepare(
            "SELECT *
             FROM academic_rubrics
             WHERE subject_name = ? AND trimester = ?
             ORDER BY id ASC"
        );
        $stmt->bind_param('si', $subject, $trimester);
        $stmt->execute();
        $res = $stmt->get_result();
        return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    }

    public function sumRubricPercentages(string $subject, int $trimester, ?int $excludeId = null): float
    {
        if ($excludeId) {
            $stmt = $this->db->prepare(
                "SELECT COALESCE(SUM(percentage_value), 0) AS total
                 FROM academic_rubrics
                 WHERE subject_name = ? AND trimester = ? AND id <> ?"
            );
            $stmt->bind_param('sii', $subject, $trimester, $excludeId);
        } else {
            $stmt = $this->db->prepare(
                "SELECT COALESCE(SUM(percentage_value), 0) AS total
                 FROM academic_rubrics
                 WHERE subject_name = ? AND trimester = ?"
            );
            $stmt->bind_param('si', $subject, $trimester);
        }
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        return (float) ($row['total'] ?? 0);
    }

    public function createRubric(string $subject, int $trimester, string $name, float $percentage): int|false
    {
        $stmt = $this->db->prepare(
            'INSERT INTO academic_rubrics (subject_name, trimester, rubric_name, percentage_value) VALUES (?,?,?,?)'
        );
        $stmt->bind_param('sisd', $subject, $trimester, $name, $percentage);
        if (!$stmt->execute()) {
            return false;
        }
        return (int) $this->db->insert_id;
    }

    public function updateRubric(int $rubricId, string $name, float $percentage): bool
    {
        $stmt = $this->db->prepare('UPDATE academic_rubrics SET rubric_name = ?, percentage_value = ? WHERE id = ?');
        $stmt->bind_param('sdi', $name, $percentage, $rubricId);
        return (bool) $stmt->execute();
    }

    public function deleteRubric(int $rubricId): bool
    {
        $stmt = $this->db->prepare('DELETE FROM academic_rubrics WHERE id = ?');
        $stmt->bind_param('i', $rubricId);
        return (bool) $stmt->execute();
    }

    public function upsertGrade(int $studentId, string $subject, int $year, int $trimester, int $rubricId, float $score, int $gradedBy): bool
    {
        $stmt = $this->db->prepare(
            "INSERT INTO academic_grades (student_id, subject_name, academic_year, trimester, rubric_id, score, graded_by_user_id)
             VALUES (?,?,?,?,?,?,?)
             ON DUPLICATE KEY UPDATE score = VALUES(score), graded_by_user_id = VALUES(graded_by_user_id), updated_at = CURRENT_TIMESTAMP"
        );
        $stmt->bind_param('isiiidi', $studentId, $subject, $year, $trimester, $rubricId, $score, $gradedBy);
        return (bool) $stmt->execute();
    }

    public function getSubjectRecovery(int $studentId, string $subject, int $year): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT convocatoria_1, convocatoria_2
             FROM academic_subject_recoveries
             WHERE student_id = ? AND subject_name = ? AND academic_year = ?
             LIMIT 1"
        );
        $stmt->bind_param('isi', $studentId, $subject, $year);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        return $row ?: null;
    }

    public function upsertSubjectRecovery(int $studentId, string $subject, int $year, ?float $conv1, ?float $conv2): bool
    {
        $stmt = $this->db->prepare(
            "INSERT INTO academic_subject_recoveries (student_id, subject_name, academic_year, convocatoria_1, convocatoria_2)
             VALUES (?,?,?,?,?)
             ON DUPLICATE KEY UPDATE convocatoria_1 = VALUES(convocatoria_1), convocatoria_2 = VALUES(convocatoria_2), updated_at = CURRENT_TIMESTAMP"
        );
        $stmt->bind_param('isidd', $studentId, $subject, $year, $conv1, $conv2);
        return (bool) $stmt->execute();
    }

    public function listSubjectGradebook(string $subject, int $year, string $sectionCode, ?int $studentId = null): array
    {
        $sql = "SELECT st.id AS student_id, CONCAT(TRIM(COALESCE(st.nombre, '')), CASE WHEN COALESCE(st.apellidos, '') <> '' THEN ' ' ELSE '' END, TRIM(COALESCE(st.apellidos, ''))) AS student_nombre, st.cedula, st.seccion,
                       r.id AS rubric_id, r.trimester, r.rubric_name, r.percentage_value,
                       g.score
                FROM students st
                JOIN enrollments e
                  ON e.student_id = st.id
                 AND e.year = ?
                 AND e.archived_at IS NULL
                 AND e.estado <> 'BLOQUEADO'
                LEFT JOIN academic_rubrics r
                  ON r.subject_name = ?
                LEFT JOIN academic_grades g
                  ON g.student_id = st.id
                 AND g.subject_name = ?
                 AND g.academic_year = ?
                 AND g.trimester = r.trimester
                 AND g.rubric_id = r.id
                WHERE st.seccion = ?
                  AND st.archived_at IS NULL";

        if ($studentId !== null) {
            $sql .= " AND st.id = ?";
        }

        $sql .= " ORDER BY COALESCE(NULLIF(TRIM(st.apellidos), ''), 'ZZZZZZ') ASC, st.nombre ASC, r.trimester ASC, r.id ASC";

        $stmt = $this->db->prepare($sql);
        if ($studentId !== null) {
            $stmt->bind_param('issisi', $year, $subject, $subject, $year, $sectionCode, $studentId);
        } else {
            $stmt->bind_param('issis', $year, $subject, $subject, $year, $sectionCode);
        }
        $stmt->execute();
        $res = $stmt->get_result();
        return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    }

    private function calculateStatus(float $finalAverage, ?float $conv1, ?float $conv2): array
    {
        $status = 'Aprobado';
        if ($finalAverage >= 70) {
            $status = 'Aprobado';
        } elseif ($finalAverage >= 65) {
            $status = 'Aplazado';
            if ($conv1 !== null && $conv1 >= 70) {
                $status = 'Aprobado';
            } elseif ($conv2 !== null) {
                $status = $conv2 >= 70 ? 'Aprobado' : 'Reprobado';
            }
        } else {
            $status = 'Reprobado';
        }

        return [
            'status_label' => $status,
            'status_key' => strtolower($status),
        ];
    }

    public function buildStudentSubjectSummary(int $studentId, int $year): array
    {
        $rubrics = [];
        $res = $this->db->query('SELECT * FROM academic_rubrics ORDER BY subject_name ASC, trimester ASC, id ASC');
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $rubrics[$row['subject_name']][(int) $row['trimester']][] = $row;
            }
        }

        $grades = [];
        $stmt = $this->db->prepare(
            "SELECT rubric_id, subject_name, trimester, score
             FROM academic_grades
             WHERE student_id = ? AND academic_year = ?"
        );
        $stmt->bind_param('ii', $studentId, $year);
        $stmt->execute();
        $gres = $stmt->get_result();
        while ($row = $gres->fetch_assoc()) {
            $grades[$row['subject_name']][(int) $row['trimester']][(int) $row['rubric_id']] = (float) $row['score'];
        }

        $recoveries = [];
        $rStmt = $this->db->prepare(
            "SELECT subject_name, convocatoria_1, convocatoria_2
             FROM academic_subject_recoveries
             WHERE student_id = ? AND academic_year = ?"
        );
        $rStmt->bind_param('ii', $studentId, $year);
        $rStmt->execute();
        $rRes = $rStmt->get_result();
        while ($row = $rRes->fetch_assoc()) {
            $recoveries[$row['subject_name']] = [
                'convocatoria_1' => $row['convocatoria_1'] !== null ? (float) $row['convocatoria_1'] : null,
                'convocatoria_2' => $row['convocatoria_2'] !== null ? (float) $row['convocatoria_2'] : null,
            ];
        }

        $weights = [1 => 0.30, 2 => 0.35, 3 => 0.35];
        $out = [];
        foreach (self::SUBJECTS as $subject) {
            $trimesterScores = [];
            for ($t = 1; $t <= 3; $t++) {
                $items = $rubrics[$subject][$t] ?? [];
                $sum = 0.0;
                foreach ($items as $rubric) {
                    $score = $grades[$subject][$t][(int) $rubric['id']] ?? null;
                    if ($score !== null) {
                        $sum += ((float) $score) * (((float) $rubric['percentage_value']) / 100);
                    }
                }
                $trimesterScores[$t] = round($sum, 2);
            }

            $final = round(
                ($trimesterScores[1] * $weights[1]) +
                ($trimesterScores[2] * $weights[2]) +
                ($trimesterScores[3] * $weights[3]),
                2
            );

            $conv1 = $recoveries[$subject]['convocatoria_1'] ?? null;
            $conv2 = $recoveries[$subject]['convocatoria_2'] ?? null;
            $status = $this->calculateStatus($final, $conv1, $conv2);

            $out[] = [
                'subject_name' => $subject,
                'trimester_1' => $trimesterScores[1],
                'trimester_2' => $trimesterScores[2],
                'trimester_3' => $trimesterScores[3],
                'final_average' => $final,
                'convocatoria_1' => $conv1,
                'convocatoria_2' => $conv2,
                'status_label' => $status['status_label'],
                'status_key' => $status['status_key'],
            ];
        }

        return $out;
    }
}
