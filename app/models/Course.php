<?php
require_once __DIR__ . '/../config/db.php';

class Course
{
    private mysqli $db;

    public function __construct()
    {
        $this->db = Database::connect();
    }

    public function create(string $nombre, string $descripcion, int $grado, string $seccion, int $docente_user_id): int|false
    {
        $stmt = $this->db->prepare('INSERT INTO courses (nombre, descripcion, grado, seccion, docente_user_id) VALUES (?,?,?,?,?)');
        $stmt->bind_param('ssisi', $nombre, $descripcion, $grado, $seccion, $docente_user_id);
        if (!$stmt->execute())
            return false;
        return (int) $this->db->insert_id;
    }

    public function list(?int $grado = null, ?int $docente_user_id = null, int $limit = 200): array
    {
        $limit = max(1, min(1000, $limit));
        $where = [];
        $params = [];
        $types = '';

        if ($grado !== null) {
            $where[] = 'c.grado = ?';
            $params[] = $grado;
            $types .= 'i';
        }
        if ($docente_user_id !== null) {
            $where[] = 'c.docente_user_id = ?';
            $params[] = $docente_user_id;
            $types .= 'i';
        }

        $sql = 'SELECT c.*, u.nombre AS docente_nombre
                FROM courses c
                JOIN users u ON u.id = c.docente_user_id';
        if ($where)
            $sql .= ' WHERE ' . implode(' AND ', $where);
        $sql .= ' ORDER BY c.id DESC LIMIT ' . $limit;

        if ($params) {
            $stmt = $this->db->prepare($sql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $res = $stmt->get_result();
            return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        }

        $res = $this->db->query($sql);
        return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    }

    public function get(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT c.*, u.nombre AS docente_nombre FROM courses c JOIN users u ON u.id = c.docente_user_id WHERE c.id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        return $row ?: null;
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM courses WHERE id = ?');
        $stmt->bind_param('i', $id);
        return (bool) $stmt->execute();
    }

    public function listBySeccion(string $seccion, int $limit = 200): array
    {
        $limit = max(1, min(1000, $limit));
        $sql = "SELECT c.*, u.nombre AS docente_nombre
                FROM courses c
                LEFT JOIN users u ON u.id = c.docente_user_id
                WHERE c.seccion = ?
                ORDER BY c.id DESC
                LIMIT " . (int) $limit;
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param('s', $seccion);
        $stmt->execute();
        $res = $stmt->get_result();
        return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    }

    public function sumConfiguredWeights(int $course_id, ?int $exclude_assignment_id = null, ?int $exclude_quiz_id = null): int
    {
        $total = 0;

        // TAREAS
        $sqlA = "SELECT COALESCE(SUM(COALESCE(a.weight_percent,0)),0) AS t
             FROM assignments a
             JOIN course_sections s ON s.id = a.section_id
             WHERE s.course_id = ?";
        $typesA = 'i';
        $paramsA = [$course_id];

        if ($exclude_assignment_id !== null && $exclude_assignment_id > 0) {
            $sqlA .= " AND a.id <> ?";
            $typesA .= 'i';
            $paramsA[] = $exclude_assignment_id;
        }

        $stmtA = $this->db->prepare($sqlA);
        $stmtA->bind_param($typesA, ...$paramsA);
        $stmtA->execute();
        $rowA = $stmtA->get_result()->fetch_assoc();
        $total += (int) ($rowA['t'] ?? 0);

        // QUIZ/EXÁMENES (misma tabla quizzes)
        $sqlQ = "SELECT COALESCE(SUM(COALESCE(q.weight_percent,0)),0) AS t
             FROM quizzes q
             JOIN course_sections s ON s.id = q.section_id
             WHERE s.course_id = ?";
        $typesQ = 'i';
        $paramsQ = [$course_id];

        if ($exclude_quiz_id !== null && $exclude_quiz_id > 0) {
            $sqlQ .= " AND q.id <> ?";
            $typesQ .= 'i';
            $paramsQ[] = $exclude_quiz_id;
        }

        $stmtQ = $this->db->prepare($sqlQ);
        $stmtQ->bind_param($typesQ, ...$paramsQ);
        $stmtQ->execute();
        $rowQ = $stmtQ->get_result()->fetch_assoc();
        $total += (int) ($rowQ['t'] ?? 0);

        return $total;
    }

    public function getStudentCurrentGrade(int $course_id, int $student_id): float
    {
        // Aporte de TAREAS: (grade.score / assignment.max_score) * assignment.weight_percent
        $stmtA = $this->db->prepare(
            "SELECT COALESCE(SUM(
            (CAST(g.score AS DECIMAL(10,4)) / NULLIF(a.max_score,0)) * COALESCE(a.weight_percent,0)
        ),0) AS total_tasks
         FROM assignments a
         JOIN course_sections s ON s.id = a.section_id
         JOIN submissions sub ON sub.assignment_id = a.id
         JOIN grades g ON g.submission_id = sub.id
         WHERE s.course_id = ?
           AND sub.student_id = ?
           AND a.weight_percent IS NOT NULL"
        );
        $stmtA->bind_param('ii', $course_id, $student_id);
        $stmtA->execute();
        $rowA = $stmtA->get_result()->fetch_assoc();
        $tasks = (float) ($rowA['total_tasks'] ?? 0);

        // Aporte de QUIZ/EXAMEN: (attempt.score / 100) * quiz.weight_percent
        $stmtQ = $this->db->prepare(
            "SELECT COALESCE(SUM(
            (CAST(qa.score AS DECIMAL(10,4)) / 100) * COALESCE(q.weight_percent,0)
        ),0) AS total_quiz
         FROM quizzes q
         JOIN course_sections s ON s.id = q.section_id
         JOIN quiz_attempts qa ON qa.quiz_id = q.id
         WHERE s.course_id = ?
           AND qa.student_id = ?
           AND q.weight_percent IS NOT NULL
           AND qa.status IN ('SUBMITTED','GRADED')"
        );
        $stmtQ->bind_param('ii', $course_id, $student_id);
        $stmtQ->execute();
        $rowQ = $stmtQ->get_result()->fetch_assoc();
        $quiz = (float) ($rowQ['total_quiz'] ?? 0);

        $total = $tasks + $quiz;
        if ($total < 0)
            $total = 0;
        if ($total > 100)
            $total = 100;

        return round($total, 2);
    }
}
