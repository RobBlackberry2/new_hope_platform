<?php
require_once __DIR__ . '/../models/Course.php';
require_once __DIR__ . '/../models/CourseSection.php';
require_once __DIR__ . '/../models/CourseResource.php';
require_once __DIR__ . '/../models/Student.php';
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../models/Assignment.php';
require_once __DIR__ . '/../models/Submission.php';
require_once __DIR__ . '/../models/SubmissionGroup.php';
require_once __DIR__ . '/../models/Grade.php';

class AssignementResourseController
{
    // =========================
    // RECURSOS (Secciones tipo RECURSOS)
    // =========================

    public function uploadResource(): void
    {
        require_login();
        require_role(['ADMIN', 'DOCENTE']);
        $u = current_user();

        $section_id = (int) ($_POST['section_id'] ?? 0);
        $course_id  = (int) ($_POST['course_id'] ?? 0);

        if (!$section_id || !$course_id || !isset($_FILES['file'])) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Faltan datos (course_id, section_id, file)']);
            return;
        }

        // Validar sección y que sea del curso + tipo RECURSOS
        $sec = (new CourseSection())->get($section_id);
        if (!$sec || (int)$sec['course_id'] !== $course_id) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Sección no encontrada o no pertenece al curso']);
            return;
        }
        if (($sec['tipo'] ?? '') !== 'RECURSOS') {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'La sección no es de tipo RECURSOS']);
            return;
        }

        // Validar dueño si DOCENTE
        if (($u['rol'] ?? '') === 'DOCENTE') {
            $c = (new Course())->get($course_id);
            if (!$c || (int) $c['docente_user_id'] !== (int) $u['id']) {
                http_response_code(403);
                echo json_encode(['status' => 'error', 'message' => 'No puedes subir a cursos de otro docente']);
                return;
            }
        }

        $file = $_FILES['file'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Error de subida']);
            return;
        }

        $original = basename($file['name']);
        $mime = $file['type'] ?? 'application/octet-stream';
        $size = (int) $file['size'];

        $safeOriginal = preg_replace('/[^A-Za-z0-9._-]/', '_', $original);
        $stored = time() . '_' . bin2hex(random_bytes(6)) . '_' . $safeOriginal;

        $baseDir = realpath(__DIR__ . '/../../uploads');
        if ($baseDir === false) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'No existe carpeta uploads']);
            return;
        }

        $targetDir = $baseDir . DIRECTORY_SEPARATOR . 'course_' . $course_id;
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0777, true);
        }

        $targetPath = $targetDir . DIRECTORY_SEPARATOR . $stored;
        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'No se pudo guardar el archivo']);
            return;
        }

        $model = new CourseResource();
        $id = $model->create(
            $section_id,
            'course_' . $course_id . '/' . $stored,
            $original,
            $mime,
            $size,
            (int) $u['id']
        );

        if ($id) echo json_encode(['status' => 'success', 'id' => $id]);
        else {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'No se pudo registrar el archivo']);
        }
    }

    public function listResources(): void
    {
        require_login();
        $section_id = (int) ($_GET['section_id'] ?? 0);
        if (!$section_id) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Falta section_id']);
            return;
        }

        $model = new CourseResource();
        $items = $model->listBySection($section_id);
        echo json_encode(['status' => 'success', 'data' => $items]);
    }

    public function deleteResource(): void
    {
        require_login();
        require_role(['ADMIN', 'DOCENTE']);
        $u = current_user();

        $id = (int) ($_POST['id'] ?? 0);
        if (!$id) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Falta id']);
            return;
        }

        $model = new CourseResource();
        $info = $model->getWithCourse($id);
        if (!$info) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'No encontrado']);
            return;
        }

        // Validar dueño si DOCENTE
        if (($u['rol'] ?? '') === 'DOCENTE' && (int)($info['docente_user_id'] ?? 0) !== (int)$u['id']) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'No puedes eliminar recursos de otro docente']);
            return;
        }

        $row = $model->delete($id);
        if (!$row) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'No se pudo eliminar']);
            return;
        }

        // Borrar archivo físico (si existe)
        $path = __DIR__ . '/../../uploads/' . ($row['stored_name'] ?? '');
        if (is_file($path)) @unlink($path);

        echo json_encode(['status' => 'success']);
    }

    // =========================
    // TAREA (Secciones tipo TAREA)
    // =========================

    public function upsertAssignment(): void
    {
        require_login();
        require_role(['ADMIN', 'DOCENTE']);
        $u = current_user();

        $section_id = (int) ($_POST['section_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $instructions = $_POST['instructions'] ?? null;
        $due_at = $_POST['due_at'] ?? null; // 'YYYY-MM-DD HH:MM:SS' o null
        $is_group = (int) ($_POST['is_group'] ?? 0);

        $weight_percent_raw = $_POST['weight_percent'] ?? '';
        $weight_percent = ($weight_percent_raw === '' ? null : (int) $weight_percent_raw);

        $max_score = (int) ($_POST['max_score'] ?? 100);
        $passing_score = (int) ($_POST['passing_score'] ?? 70);

        if (!$section_id || $title === '') {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Faltan datos (section_id, title)']);
            return;
        }

        $sec = (new CourseSection())->get($section_id);
        if (!$sec) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Sección no encontrada']);
            return;
        }

        if (($sec['tipo'] ?? '') !== 'TAREA') {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'La sección no es de tipo TAREA']);
            return;
        }

        // Validar dueño si DOCENTE
        if (($u['rol'] ?? '') === 'DOCENTE') {
            $c = (new Course())->get((int) $sec['course_id']);
            if (!$c || (int) $c['docente_user_id'] !== (int) $u['id']) {
                http_response_code(403);
                echo json_encode(['status' => 'error', 'message' => 'No puedes modificar cursos de otro docente']);
                return;
            }
        }

        $a = new Assignment();
        $id = $a->upsertBySection($section_id, $title, $instructions, $due_at, $is_group, $weight_percent, $max_score, $passing_score);

        if ($id) echo json_encode(['status' => 'success', 'id' => $id]);
        else {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'No se pudo guardar la tarea']);
        }
    }

    public function getAssignmentBySection(): void
    {
        require_login();
        $section_id = (int) ($_GET['section_id'] ?? 0);
        if (!$section_id) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Falta section_id']);
            return;
        }

        $a = new Assignment();
        $row = $a->getBySection($section_id);
        echo json_encode(['status' => 'success', 'data' => $row]);
    }

    // =========================
    // ENTREGAS / GRUPOS / NOTAS (TAREA)
    // =========================

    public function uploadSubmission(): void
    {
        require_login();
        require_role(['ESTUDIANTE']);
        $u = current_user();

        $assignment_id = (int) ($_POST['assignment_id'] ?? 0);
        if (!$assignment_id || !isset($_FILES['file'])) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Faltan datos (assignment_id, file)']);
            return;
        }

        $assignModel = new Assignment();
        $a = $assignModel->getWithCourse($assignment_id);
        if (!$a) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Tarea no encontrada']);
            return;
        }

        // Fecha límite si existe
        $due = $a['due_at'] ?? null;
        if ($due) {
            try {
                $dueDt = new DateTime($due);
                $now = new DateTime('now');
                if ($now > $dueDt) {
                    http_response_code(400);
                    echo json_encode(['status' => 'error', 'message' => 'La fecha límite ya pasó']);
                    return;
                }
            } catch (Exception $e) {
                // si hay formato inválido, no bloqueamos
            }
        }

        // Resolver student_id
        $student = new Student();
        $s = $student->getByUserId((int) $u['id']);
        $student_id = (int)($s['id'] ?? 0);
        if (!$student_id) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'No se encontró el estudiante']);
            return;
        }

        // si es grupo, validar/crear grupo y usar group_id
        $group_id = null;
        if ((int)($a['is_group'] ?? 0) === 1) {
            $g = new SubmissionGroup();
            $group = $g->getOrCreateGroupForStudent((int)$assignment_id, $student_id);
            $group_id = (int)($group['id'] ?? 0);
            if (!$group_id) {
                http_response_code(500);
                echo json_encode(['status' => 'error', 'message' => 'No se pudo resolver el grupo']);
                return;
            }
        }

        $file = $_FILES['file'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Error de subida']);
            return;
        }

        $original = basename($file['name']);
        $mime = $file['type'] ?? 'application/octet-stream';
        $size = (int) $file['size'];

        $safeOriginal = preg_replace('/[^A-Za-z0-9._-]/', '_', $original);
        $stored = time() . '_' . bin2hex(random_bytes(6)) . '_' . $safeOriginal;

        $baseDir = realpath(__DIR__ . '/../../uploads');
        if ($baseDir === false) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'No existe carpeta uploads']);
            return;
        }

        $course_id = (int)($a['course_id'] ?? 0);
        $targetDir = $baseDir . DIRECTORY_SEPARATOR . 'course_' . $course_id . DIRECTORY_SEPARATOR . 'submissions';
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0777, true);
        }

        $targetPath = $targetDir . DIRECTORY_SEPARATOR . $stored;
        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'No se pudo guardar el archivo']);
            return;
        }

        $sub = new Submission();
        $id = $sub->upsertSubmission(
            (int)$assignment_id,
            $student_id,
            $group_id,
            'course_' . $course_id . '/submissions/' . $stored,
            $original,
            $mime,
            $size
        );

        if ($id) echo json_encode(['status' => 'success', 'id' => $id]);
        else {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'No se pudo registrar la entrega']);
        }
    }

    public function getMySubmission(): void
    {
        require_login();
        require_role(['ESTUDIANTE']);
        $u = current_user();

        $assignment_id = (int) ($_GET['assignment_id'] ?? 0);
        if (!$assignment_id) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Falta assignment_id']);
            return;
        }

        $student = new Student();
        $s = $student->getByUserId((int) $u['id']);
        $student_id = (int)($s['id'] ?? 0);
        if (!$student_id) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'No se encontró el estudiante']);
            return;
        }

        $assignModel = new Assignment();
        $a = $assignModel->getWithCourse($assignment_id);
        if (!$a) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Tarea no encontrada']);
            return;
        }

        $sub = new Submission();
        if ((int)($a['is_group'] ?? 0) === 1) {
            $g = new SubmissionGroup();
            $group = $g->getGroupForStudent((int)$assignment_id, $student_id);
            $group_id = (int)($group['id'] ?? 0);
            $row = $group_id ? $sub->getByAssignmentAndGroup((int)$assignment_id, $group_id) : null;
        } else {
            $row = $sub->getByAssignmentAndStudent((int)$assignment_id, $student_id);
        }

        // Adjuntar calificación si existe
        $grade = null;
        if ($row) {
            $gradeModel = new Grade();
            $grade = $gradeModel->getBySubmission((int)$row['id']);
        }

        echo json_encode(['status' => 'success', 'data' => ['submission' => $row, 'grade' => $grade]]);
    }

    public function listSubmissionsByAssignment(): void
    {
        require_login();
        require_role(['ADMIN', 'DOCENTE']);
        $u = current_user();

        $assignment_id = (int) ($_GET['assignment_id'] ?? 0);
        if (!$assignment_id) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Falta assignment_id']);
            return;
        }

        $assignModel = new Assignment();
        $a = $assignModel->getWithCourse($assignment_id);
        if (!$a) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Tarea no encontrada']);
            return;
        }

        // Validar dueño si DOCENTE
        if (($u['rol'] ?? '') === 'DOCENTE') {
            $c = (new Course())->get((int)($a['course_id'] ?? 0));
            if (!$c || (int)$c['docente_user_id'] !== (int)$u['id']) {
                http_response_code(403);
                echo json_encode(['status' => 'error', 'message' => 'No puedes ver entregas de otro docente']);
                return;
            }
        }

        $sub = new Submission();
        $items = $sub->listByAssignment((int)$assignment_id);

        echo json_encode(['status' => 'success', 'data' => $items]);
    }

    public function setGrade(): void
    {
        require_login();
        require_role(['ADMIN', 'DOCENTE']);
        $u = current_user();

        $submission_id = (int) ($_POST['submission_id'] ?? 0);
        $score = (int) ($_POST['score'] ?? -1);
        $feedback = $_POST['feedback'] ?? null;

        if (!$submission_id || $score < 0) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Datos inválidos (submission_id, score)']);
            return;
        }

        $subModel = new Submission();
        $info = $subModel->getCourseInfoFromSubmission($submission_id);
        if (!$info) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Entrega no encontrada']);
            return;
        }

        // si DOCENTE, validar dueño
        if (($u['rol'] ?? '') === 'DOCENTE' && (int) $info['docente_user_id'] !== (int) $u['id']) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'No puedes calificar entregas de otro docente']);
            return;
        }

        $grade = new Grade();
        $ok = $grade->upsert((int)$submission_id, (int)$score, $feedback, (int)$u['id']);

        if ($ok) echo json_encode(['status' => 'success']);
        else {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'No se pudo guardar la nota']);
        }
    }

    public function createGroup(): void
    {
        require_login();
        require_role(['ADMIN', 'DOCENTE']);

        $assignment_id = (int) ($_POST['assignment_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        if (!$assignment_id || $name === '') {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Faltan datos (assignment_id, name)']);
            return;
        }

        $g = new SubmissionGroup();
        $id = $g->create((int)$assignment_id, $name);

        if ($id) echo json_encode(['status' => 'success', 'id' => $id]);
        else {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'No se pudo crear el grupo']);
        }
    }

    public function listGroups(): void
    {
        require_login();
        require_role(['ADMIN', 'DOCENTE']);

        $assignment_id = (int) ($_GET['assignment_id'] ?? 0);
        if (!$assignment_id) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Falta assignment_id']);
            return;
        }

        $g = new SubmissionGroup();
        $items = $g->listByAssignment((int)$assignment_id);

        echo json_encode(['status' => 'success', 'data' => $items]);
    }

    public function setGroupMembers(): void
    {
        require_login();
        require_role(['ADMIN', 'DOCENTE']);

        $group_id = (int) ($_POST['group_id'] ?? 0);
        $student_ids = $_POST['student_ids'] ?? null; // array esperado

        if (!$group_id || !is_array($student_ids)) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Faltan datos (group_id, student_ids[])']);
            return;
        }

        $g = new SubmissionGroup();
        $ok = $g->setMembers($group_id, array_map('intval', $student_ids));

        if ($ok) echo json_encode(['status' => 'success']);
        else {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'No se pudieron guardar los miembros']);
        }
    }
}