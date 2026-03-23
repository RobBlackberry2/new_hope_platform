<?php
require_once __DIR__ . '/../models/AcademicManagement.php';
require_once __DIR__ . '/../models/Section.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../helpers/auth.php';

class AcademicManagementController
{
    private AcademicManagement $model;

    public function __construct()
    {
        $this->model = new AcademicManagement();
    }

    private function requireAcademicRole(): array
    {
        require_login();
        require_role(['ADMIN', 'DOCENTE']);
        return current_user();
    }

    private function assertSectionAccess(array $user, int $sectionId): ?array
    {
        $section = $this->model->getSectionById($sectionId);
        if (!$section) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Sección no encontrada']);
            return null;
        }

        if (($user['rol'] ?? '') === 'DOCENTE' && (int) ($section['docente_guia_user_id'] ?? 0) !== (int) ($user['id'] ?? 0)) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'Solo puedes ver tus grupos como profesor guía']);
            return null;
        }

        return $section;
    }

    private function assertStudentAccess(array $user, int $studentId): ?array
    {
        $student = $this->model->getStudentById($studentId);
        if (!$student) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Estudiante no encontrado']);
            return null;
        }

        if (($user['rol'] ?? '') === 'DOCENTE') {
            $section = $this->model->getSectionByCode($student['seccion'] ?? '');
            if (!$section || (int) ($section['docente_guia_user_id'] ?? 0) !== (int) ($user['id'] ?? 0)) {
                http_response_code(403);
                echo json_encode(['status' => 'error', 'message' => 'Sin acceso al estudiante']);
                return null;
            }
        }

        return $student;
    }

    public function listSections(): void
    {
        $user = $this->requireAcademicRole();
        echo json_encode(['status' => 'success', 'data' => $this->model->listSectionsForUser($user)]);
    }

    public function studentYears(): void
    {
        $user = $this->requireAcademicRole();
        $studentId = (int) ($_GET['student_id'] ?? 0);
        if ($studentId <= 0) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Falta el estudiante']);
            return;
        }

        $student = $this->assertStudentAccess($user, $studentId);
        if (!$student) {
            return;
        }

        $bounds = $this->model->getStudentYearBounds($studentId);
        if (!$bounds) {
            echo json_encode(['status' => 'success', 'data' => []]);
            return;
        }

        $currentYear = (int) date('Y');
        $years = [];
        for ($y = (int) $bounds['first_year']; $y <= $currentYear; $y++) {
            $years[] = $y;
        }

        echo json_encode(['status' => 'success', 'data' => $years, 'current_year' => $currentYear]);
    }

    public function listSectionStudents(): void
    {
        $user = $this->requireAcademicRole();
        $sectionId = (int) ($_GET['section_id'] ?? 0);

        $section = $this->assertSectionAccess($user, $sectionId);
        if (!$section) {
            return;
        }

        $rows = $this->model->listStudentsBySection($sectionId);
        echo json_encode(['status' => 'success', 'section' => $section, 'data' => $rows, 'subjects' => AcademicManagement::SUBJECTS]);
    }

    public function studentSubjects(): void
    {
        $user = $this->requireAcademicRole();
        $studentId = (int) ($_GET['student_id'] ?? 0);
        $year = (int) ($_GET['year'] ?? date('Y'));

        if ($studentId <= 0) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Falta el estudiante']);
            return;
        }

        $student = $this->assertStudentAccess($user, $studentId);
        if (!$student) {
            return;
        }

        if (!$this->model->studentIsEnrolledInYear($studentId, $year)) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'El estudiante no tiene matrícula en ese año lectivo']);
            return;
        }

        $summary = $this->model->buildStudentSubjectSummary($studentId, $year);
        echo json_encode(['status' => 'success', 'student' => $student, 'data' => $summary]);
    }

    public function subjectDetail(): void
    {
        $user = $this->requireAcademicRole();
        $sectionId = (int) ($_GET['section_id'] ?? 0);
        $year = (int) ($_GET['year'] ?? date('Y'));
        $subject = trim((string) ($_GET['subject_name'] ?? ''));
        $studentId = isset($_GET['student_id']) ? (int) $_GET['student_id'] : 0;

        if (!in_array($subject, AcademicManagement::SUBJECTS, true)) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Materia inválida']);
            return;
        }

        $section = $this->assertSectionAccess($user, $sectionId);
        if (!$section) {
            return;
        }

        $student = null;
        if ($studentId > 0) {
            $student = $this->assertStudentAccess($user, $studentId);
            if (!$student) {
                return;
            }
            if (!$this->model->studentIsEnrolledInYear($studentId, $year)) {
                http_response_code(404);
                echo json_encode(['status' => 'error', 'message' => 'El estudiante no tiene matrícula en ese año lectivo']);
                return;
            }
        }

        $rubrics = [];
        for ($t = 1; $t <= 3; $t++) {
            $rubrics[$t] = $this->model->listRubrics($subject, $t);
        }

        $gradebook = [];
        $summaryItem = null;
        $recovery = null;
        if ($studentId > 0) {
            $gradebook = $this->model->listSubjectGradebook($subject, $year, (string) $section['codigo'], $studentId);
            $summary = $this->model->buildStudentSubjectSummary($studentId, $year);
            foreach ($summary as $row) {
                if (($row['subject_name'] ?? '') === $subject) {
                    $summaryItem = $row;
                    break;
                }
            }
            $recovery = $this->model->getSubjectRecovery($studentId, $subject, $year);
        }

        echo json_encode([
            'status' => 'success',
            'section' => $section,
            'student' => $student,
            'subject_name' => $subject,
            'year' => $year,
            'rubrics' => $rubrics,
            'data' => $gradebook,
            'summary' => $summaryItem,
            'recovery' => $recovery,
        ]);
    }

    public function rubricSave(): void
    {
        $user = $this->requireAcademicRole();
        require_role(['ADMIN', 'DOCENTE']);
        $subject = trim((string) ($_POST['subject_name'] ?? ''));
        $trimester = (int) ($_POST['trimester'] ?? 0);
        $rubricId = (int) ($_POST['rubric_id'] ?? 0);
        $name = trim((string) ($_POST['rubric_name'] ?? ''));
        $percentage = (float) ($_POST['percentage_value'] ?? 0);

        if (!in_array($subject, AcademicManagement::SUBJECTS, true)) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Materia inválida']);
            return;
        }
        if ($trimester < 1 || $trimester > 3 || $name === '' || $percentage <= 0) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Datos inválidos del rubro']);
            return;
        }

        $currentTotal = $this->model->sumRubricPercentages($subject, $trimester, $rubricId ?: null);
        if (($currentTotal + $percentage) > 100.0001) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'La suma de porcentajes no puede superar 100%']);
            return;
        }

        $ok = $rubricId > 0
            ? $this->model->updateRubric($rubricId, $name, $percentage)
            : $this->model->createRubric($subject, $trimester, $name, $percentage);

        if (!$ok) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'No se pudo guardar el rubro']);
            return;
        }

        echo json_encode([
            'status' => 'success',
            'message' => ($currentTotal + $percentage) == 100.0 ? 'Rubro guardado. Trimestre completo al 100%.' : 'Rubro guardado.',
            'total_percentage' => $this->model->sumRubricPercentages($subject, $trimester),
            'user_id' => (int) ($user['id'] ?? 0),
        ]);
    }

    public function rubricDelete(): void
    {
        $this->requireAcademicRole();
        $rubricId = (int) ($_POST['rubric_id'] ?? 0);
        if ($rubricId <= 0) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Falta el rubro']);
            return;
        }

        if (!$this->model->deleteRubric($rubricId)) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'No se pudo eliminar el rubro']);
            return;
        }
        echo json_encode(['status' => 'success']);
    }

    public function gradesSave(): void
    {
        $user = $this->requireAcademicRole();
        $subject = trim((string) ($_POST['subject_name'] ?? ''));
        $year = (int) ($_POST['year'] ?? date('Y'));
        $studentId = (int) ($_POST['student_id'] ?? 0);
        $items = json_decode((string) ($_POST['items'] ?? '[]'), true);
        $conv1Raw = trim((string) ($_POST['convocatoria_1'] ?? ''));
        $conv2Raw = trim((string) ($_POST['convocatoria_2'] ?? ''));
        $conv1 = $conv1Raw === '' ? null : (float) $conv1Raw;
        $conv2 = $conv2Raw === '' ? null : (float) $conv2Raw;

        if (!in_array($subject, AcademicManagement::SUBJECTS, true) || !is_array($items) || $studentId <= 0) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Datos inválidos']);
            return;
        }

        $student = $this->assertStudentAccess($user, $studentId);
        if (!$student) {
            return;
        }

        if (!$this->model->studentIsEnrolledInYear($studentId, $year)) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'El estudiante no tiene matrícula en ese año lectivo']);
            return;
        }

        if (($conv1 !== null && ($conv1 < 0 || $conv1 > 100)) || ($conv2 !== null && ($conv2 < 0 || $conv2 > 100))) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Las convocatorias deben estar entre 0 y 100']);
            return;
        }

        for ($t = 1; $t <= 3; $t++) {
            $total = $this->model->sumRubricPercentages($subject, $t);
            if ($total > 0 && abs($total - 100.0) > 0.0001) {
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => "El trimestre {$t} debe sumar exactamente 100% antes de guardar notas"]);
                return;
            }
        }

        foreach ($items as $item) {
            $itemStudentId = (int) ($item['student_id'] ?? 0);
            $trimester = (int) ($item['trimester'] ?? 0);
            $rubricId = (int) ($item['rubric_id'] ?? 0);
            $score = (float) ($item['score'] ?? 0);
            if ($itemStudentId !== $studentId || $trimester < 1 || $trimester > 3 || $rubricId <= 0 || $score < 0 || $score > 100) {
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => 'Hay notas inválidas. Deben estar entre 0 y 100']);
                return;
            }
            if (!$this->model->upsertGrade($studentId, $subject, $year, $trimester, $rubricId, $score, (int) $user['id'])) {
                http_response_code(500);
                echo json_encode(['status' => 'error', 'message' => 'No se pudieron guardar las notas']);
                return;
            }
        }

        if (!$this->model->upsertSubjectRecovery($studentId, $subject, $year, $conv1, $conv2)) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'No se pudieron guardar las convocatorias']);
            return;
        }

        echo json_encode(['status' => 'success']);
    }
}
