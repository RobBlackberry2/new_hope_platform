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
require_once __DIR__ . '/../models/Quiz.php';
require_once __DIR__ . '/../models/QuizQuestion.php';
require_once __DIR__ . '/../models/QuizOption.php';
require_once __DIR__ . '/../models/QuizAttempt.php';


class ElearningController
{
    public function createCourse(): void
    {
        require_login();
        require_role(['ADMIN']);
        $u = current_user();

        $nombre = $_POST['nombre'] ?? '';
        $descripcion = $_POST['descripcion'] ?? '';
        $grado = (int) ($_POST['grado'] ?? 7);
        $seccion = $_POST['seccion'] ?? '';
        $docente_user_id = (int) ($_POST['docente_user_id'] ?? 0);
        if ($docente_user_id <= 0) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Debe seleccionar un docente.']);
            return;
        }

        if (!$nombre) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Nombre es requerido']);
            return;
        }

        $model = new Course();
        $id = $model->create($nombre, $descripcion, $grado, $seccion, $docente_user_id);
        if ($id)
            echo json_encode(['status' => 'success', 'id' => $id]);
        else {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'No se pudo crear el curso']);
        }
    }

    public function listCourses(): void
    {
        require_login();
        $u = current_user();
        $course = new Course();

        if (($u['rol'] ?? '') === 'DOCENTE') {
            $data = $course->list(null, (int) $u['id'], (int) ($_GET['limit'] ?? 200));
            echo json_encode(['status' => 'success', 'data' => $data]);
            return;
        }

        if (($u['rol'] ?? '') === 'ESTUDIANTE') {
            $student = new Student();
            $s = $student->getByUserId((int) $u['id']);

            if (!$s) {
                echo json_encode(['status' => 'success', 'data' => [], 'message' => 'No hay ficha de estudiante asociada a este usuario']);
                return;
            }

            $seccion = $s['seccion'] ?? '';
            if (!$seccion) {
                echo json_encode(['status' => 'success', 'data' => [], 'message' => 'El estudiante no tiene sección asignada']);
                return;
            }

            $data = $course->listBySeccion($seccion, (int) ($_GET['limit'] ?? 200));
            echo json_encode(['status' => 'success', 'data' => $data]);
            return;
        }

        // ADMIN: ve todos o puede filtrar por grado
        $grado = isset($_GET['grado']) ? (int) $_GET['grado'] : null;
        $data = $course->list($grado, null, (int) ($_GET['limit'] ?? 200));
        echo json_encode(['status' => 'success', 'data' => $data]);
    }

    public function createSection(): void
    {
        require_login();
        require_role(['ADMIN', 'DOCENTE']);
        $u = current_user();

        $course_id = (int) ($_POST['course_id'] ?? 0);
        $titulo = $_POST['titulo'] ?? '';
        $descripcion = $_POST['descripcion'] ?? '';
        $semana = (int) ($_POST['semana'] ?? 0);
        $orden = (int) ($_POST['orden'] ?? 0);
        $tipo = $_POST['tipo'] ?? 'RECURSOS';

        if (!$course_id || !$titulo) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Faltan datos']);
            return;
        }

        // Si docente, validar dueño del curso
        if (($u['rol'] ?? '') === 'DOCENTE') {
            $c = (new Course())->get($course_id);
            if (!$c || (int) $c['docente_user_id'] !== (int) $u['id']) {
                http_response_code(403);
                echo json_encode(['status' => 'error', 'message' => 'No puedes modificar cursos de otro docente']);
                return;
            }
        }

        $model = new CourseSection();
        $id = $model->create($course_id, $titulo, $descripcion, $semana, $orden, $tipo);
        if ($id)
            echo json_encode(['status' => 'success', 'id' => $id]);
        else {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'No se pudo crear la sección']);
        }
    }

    public function listSections(): void
    {
        require_login();
        $course_id = (int) ($_GET['course_id'] ?? 0);
        if (!$course_id) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Falta course_id']);
            return;
        }
        $model = new CourseSection();
        echo json_encode(['status' => 'success', 'data' => $model->list($course_id)]);
    }

    public function uploadResource(): void
    {
        require_login();
        require_role(['ADMIN', 'DOCENTE']);
        $u = current_user();

        $section_id = (int) ($_POST['section_id'] ?? 0);
        $course_id = (int) ($_POST['course_id'] ?? 0);
        if (!$section_id || !$course_id || !isset($_FILES['file'])) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Faltan datos (course_id, section_id, file)']);
            return;
        }

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
        $id = $model->create($section_id, 'course_' . $course_id . '/' . $stored, $original, $mime, $size, (int) $u['id']);
        if ($id)
            echo json_encode(['status' => 'success', 'id' => $id]);
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
        echo json_encode(['status' => 'success', 'data' => $model->listBySection($section_id)]);
    }

    public function deleteResource(): void
    {
        require_login();
        require_role(['ADMIN', 'DOCENTE']);
        $id = (int) ($_POST['id'] ?? 0);
        if (!$id) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Falta id']);
            return;
        }
        $model = new CourseResource();
        $row = $model->delete($id);
        if (!$row) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'No encontrado']);
            return;
        }

        // Borrar archivo físico (si existe)
        $path = __DIR__ . '/../../uploads/' . ($row['stored_name'] ?? '');
        if (is_file($path))
            @unlink($path);
        echo json_encode(['status' => 'success']);
    }

    public function deleteSection(): void
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

        $sectionModel = new CourseSection();
        $section = $sectionModel->get($id);
        if (!$section) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Sección no encontrada']);
            return;
        }

        $course_id = (int) ($section['course_id'] ?? 0);

        if (($u['rol'] ?? '') === 'DOCENTE') {
            $c = (new Course())->get($course_id);
            if (!$c || (int) $c['docente_user_id'] !== (int) $u['id']) {
                http_response_code(403);
                echo json_encode(['status' => 'error', 'message' => 'No puedes eliminar secciones de otro docente']);
                return;
            }
        }

        $resModel = new CourseResource();
        $resources = $resModel->listBySection($id);
        foreach ($resources as $r) {
            $row = $resModel->delete((int) $r['id']);
            if ($row) {
                $path = __DIR__ . '/../../uploads/' . ($row['stored_name'] ?? '');
                if (is_file($path))
                    @unlink($path);
            }
        }

        if (!$sectionModel->delete($id)) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'No se pudo eliminar la sección']);
            return;
        }

        echo json_encode(['status' => 'success']);
    }

    public function deleteCourse(): void
    {
        require_login();
        require_role(['ADMIN']);

        $id = (int) ($_POST['id'] ?? 0);
        if (!$id) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Falta id']);
            return;
        }

        $courseModel = new Course();
        $c = $courseModel->get($id);
        if (!$c) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Curso no encontrado']);
            return;
        }

        $secModel = new CourseSection();
        $resModel = new CourseResource();

        $sections = $secModel->list($id);
        foreach ($sections as $s) {
            $sid = (int) $s['id'];
            $resources = $resModel->listBySection($sid);
            foreach ($resources as $r) {
                $row = $resModel->delete((int) $r['id']);
                if ($row) {
                    $path = __DIR__ . '/../../uploads/' . ($row['stored_name'] ?? '');
                    if (is_file($path))
                        @unlink($path);
                }
            }
            $secModel->delete($sid);
        }

        if (!$courseModel->delete($id)) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'No se pudo eliminar el curso']);
            return;
        }

        echo json_encode(['status' => 'success']);
    }

    public function updateSectionTipo(): void
    {
        require_login();
        require_role(['ADMIN', 'DOCENTE']);
        $u = current_user();

        $id = (int) ($_POST['id'] ?? 0);
        $tipo = $_POST['tipo'] ?? '';

        $allowed = ['RECURSOS', 'TAREA', 'QUIZ', 'EXAMEN', 'AVISO'];
        if (!$id || !in_array($tipo, $allowed, true)) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Datos inválidos']);
            return;
        }

        $secModel = new CourseSection();
        $sec = $secModel->get($id);
        if (!$sec) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Sección no encontrada']);
            return;
        }

        // Si docente, validar dueño del curso
        if (($u['rol'] ?? '') === 'DOCENTE') {
            $c = (new Course())->get((int) $sec['course_id']);
            if (!$c || (int) $c['docente_user_id'] !== (int) $u['id']) {
                http_response_code(403);
                echo json_encode(['status' => 'error', 'message' => 'No puedes modificar cursos de otro docente']);
                return;
            }
        }

        if ($secModel->updateTipo($id, $tipo)) {
            echo json_encode(['status' => 'success']);
        } else {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'No se pudo actualizar tipo']);
        }
    }

    public function upsertAssignment(): void
    {
        require_login();
        require_role(['ADMIN', 'DOCENTE']);
        $u = current_user();

        $section_id = (int) ($_POST['section_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $instructions = $_POST['instructions'] ?? null;
        $due_at = $_POST['due_at'] ?? null;            // 'YYYY-MM-DD HH:MM:SS' o null
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
        if ($id)
            echo json_encode(['status' => 'success', 'id' => $id]);
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
        echo json_encode(['status' => 'success', 'data' => $row]); // puede ser null si no existe
    }

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

        // Fecha límite (si existe)
        $due_at = $a['due_at'] ?? null;
        if ($due_at) {
            $now = new DateTime('now');
            $due = new DateTime($due_at);
            if ($now > $due) {
                http_response_code(409);
                echo json_encode(['status' => 'error', 'message' => 'Fecha límite vencida']);
                return;
            }
        }

        $student = new Student();
        $s = $student->getByUserId((int) $u['id']);
        if (!$s) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'No hay ficha de estudiante asociada a este usuario']);
            return;
        }
        $student_id = (int) $s['id'];

        $course_id = (int) $a['course_id'];
        $is_group = (int) ($a['is_group'] ?? 0);

        $group_id = null;
        if ($is_group === 1) {
            $gModel = new SubmissionGroup();
            $gid = $gModel->getGroupForStudent($course_id, $student_id);
            if (!$gid) {
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => 'Esta tarea es grupal y no tienes grupo asignado']);
                return;
            }
            $group_id = (int) $gid;
        }

        // Crear o obtener submission
        $subModel = new Submission();
        $submission_id = $subModel->getOrCreate($assignment_id, $is_group ? null : $student_id, $is_group ? $group_id : null);
        if (!$submission_id) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'No se pudo crear la entrega']);
            return;
        }

        // Guardar archivo (local)
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

        $targetDir = $baseDir . DIRECTORY_SEPARATOR . 'course_' . $course_id
            . DIRECTORY_SEPARATOR . 'assignment_' . $assignment_id
            . DIRECTORY_SEPARATOR . 'submissions';

        if (!is_dir($targetDir))
            mkdir($targetDir, 0777, true);

        $targetPath = $targetDir . DIRECTORY_SEPARATOR . $stored;
        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'No se pudo guardar el archivo']);
            return;
        }

        // URL pública local (relativa)
        $publicUrl = 'uploads/course_' . $course_id . '/assignment_' . $assignment_id . '/submissions/' . $stored;

        $fid = $subModel->addFile($submission_id, $original, $mime, $size, 'local', null, $publicUrl);
        if (!$fid) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'No se pudo registrar archivo']);
            return;
        }

        echo json_encode(['status' => 'success', 'submission_id' => $submission_id, 'file_id' => $fid]);
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

        $a = (new Assignment())->getWithCourse($assignment_id);
        if (!$a) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Tarea no encontrada']);
            return;
        }

        $student = (new Student())->getByUserId((int) $u['id']);
        if (!$student) {
            echo json_encode(['status' => 'success', 'data' => null, 'message' => 'No hay ficha de estudiante']);
            return;
        }
        $student_id = (int) $student['id'];

        $course_id = (int) $a['course_id'];
        $is_group = (int) ($a['is_group'] ?? 0);
        $group_id = null;

        if ($is_group === 1) {
            $group_id = (new SubmissionGroup())->getGroupForStudent($course_id, $student_id);
        }

        $subModel = new Submission();
        $sub = $subModel->getMine($assignment_id, $is_group ? null : $student_id, $is_group ? $group_id : null);
        if (!$sub) {
            echo json_encode(['status' => 'success', 'data' => null]);
            return;
        }

        $file = $subModel->getLatestFile((int) $sub['id']);
        $grade = (new Grade())->getBySubmission((int) $sub['id']);

        echo json_encode([
            'status' => 'success',
            'data' => [
                'submission' => $sub,
                'file' => $file,
                'grade' => $grade
            ]
        ]);
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

        $a = (new Assignment())->getWithCourse($assignment_id);
        if (!$a) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Tarea no encontrada']);
            return;
        }

        // si DOCENTE, validar dueño
        if (($u['rol'] ?? '') === 'DOCENTE') {
            $c = (new Course())->get((int) $a['course_id']);
            if (!$c || (int) $c['docente_user_id'] !== (int) $u['id']) {
                http_response_code(403);
                echo json_encode(['status' => 'error', 'message' => 'No puedes ver entregas de otro docente']);
                return;
            }
        }

        $subModel = new Submission();
        $rows = $subModel->listByAssignment($assignment_id);

        // Adjuntar latest file + grade (simple, por ahora 1 query extra por submission)
        $gradeModel = new Grade();
        foreach ($rows as &$r) {
            $sid = (int) $r['id'];
            $r['latest_file'] = $subModel->getLatestFile($sid);
            $r['grade'] = $gradeModel->getBySubmission($sid);
        }

        echo json_encode(['status' => 'success', 'data' => $rows]);
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

        $ok = (new Grade())->upsert($submission_id, $score, $feedback, (int) $u['id']);
        if ($ok)
            echo json_encode(['status' => 'success']);
        else {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'No se pudo guardar la calificación']);
        }
    }

    public function createGroup(): void
    {
        require_login();
        require_role(['ADMIN', 'DOCENTE']);
        $u = current_user();

        $course_id = (int) ($_POST['course_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        if (!$course_id || $name === '') {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Faltan datos (course_id, name)']);
            return;
        }

        if (($u['rol'] ?? '') === 'DOCENTE') {
            $c = (new Course())->get($course_id);
            if (!$c || (int) $c['docente_user_id'] !== (int) $u['id']) {
                http_response_code(403);
                echo json_encode(['status' => 'error', 'message' => 'No puedes crear grupos en cursos de otro docente']);
                return;
            }
        }

        $id = (new SubmissionGroup())->create($course_id, $name);
        if ($id)
            echo json_encode(['status' => 'success', 'id' => $id]);
        else {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'No se pudo crear el grupo']);
        }
    }

    public function listGroups(): void
    {
        require_login();
        $u = current_user();

        $course_id = (int) ($_GET['course_id'] ?? 0);
        if (!$course_id) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Falta course_id']);
            return;
        }

        $gModel = new SubmissionGroup();
        $groups = $gModel->list($course_id);

        if (($u['rol'] ?? '') === 'ESTUDIANTE') {
            $s = (new Student())->getByUserId((int) $u['id']);
            if (!$s) {
                echo json_encode(['status' => 'success', 'data' => []]);
                return;
            }

            $gid = $gModel->getGroupForStudent($course_id, (int) $s['id']);
            if (!$gid) {
                echo json_encode(['status' => 'success', 'data' => []]);
                return;
            }

            // devolver solo su grupo
            $out = [];
            foreach ($groups as $g) {
                if ((int) $g['id'] === (int) $gid) {
                    $g['members'] = $gModel->listMembers((int) $g['id']);
                    $out[] = $g;
                    break;
                }
            }
            echo json_encode(['status' => 'success', 'data' => $out]);
            return;
        }

        // ADMIN/DOCENTE: incluir miembros de todos
        foreach ($groups as &$g) {
            $g['members'] = $gModel->listMembers((int) $g['id']);
        }

        echo json_encode(['status' => 'success', 'data' => $groups]);
    }

    public function setGroupMembers(): void
    {
        require_login();
        require_role(['ADMIN', 'DOCENTE']);
        $u = current_user();

        $group_id = (int) ($_POST['group_id'] ?? 0);
        $student_ids_raw = trim($_POST['student_ids'] ?? '');

        if (!$group_id) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Falta group_id']);
            return;
        }

        $gModel = new SubmissionGroup();
        $g = $gModel->get($group_id);
        if (!$g) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Grupo no encontrado']);
            return;
        }

        // si DOCENTE, validar dueño del curso
        if (($u['rol'] ?? '') === 'DOCENTE') {
            $c = (new Course())->get((int) $g['course_id']);
            if (!$c || (int) $c['docente_user_id'] !== (int) $u['id']) {
                http_response_code(403);
                echo json_encode(['status' => 'error', 'message' => 'No puedes modificar grupos de otro docente']);
                return;
            }
        }

        $ids = [];
        if ($student_ids_raw !== '') {
            foreach (explode(',', $student_ids_raw) as $p) {
                $v = (int) trim($p);
                if ($v > 0)
                    $ids[] = $v;
            }
        }

        $ok = $gModel->setMembers($group_id, $ids);
        if ($ok)
            echo json_encode(['status' => 'success']);
        else {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'No se pudo actualizar miembros']);
        }
    }

    private function payload(): array
    {
        $data = $_POST;
        if (!empty($data))
            return $data;
        $raw = file_get_contents('php://input');
        $j = json_decode($raw, true);
        return is_array($j) ? $j : [];
    }

    private function assertDocenteOwnsCourse(int $course_id): void
    {
        $u = current_user();
        if (($u['rol'] ?? '') !== 'DOCENTE')
            return;
        $c = (new Course())->get($course_id);
        if (!$c || (int) $c['docente_user_id'] !== (int) $u['id']) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'No puedes modificar cursos de otro docente']);
            exit;
        }
    }

    public function upsertQuiz(): void
    {
        require_login();
        require_role(['ADMIN', 'DOCENTE']);
        $u = current_user();
        $p = $this->payload();

        $section_id = (int) ($p['section_id'] ?? 0);
        $title = trim($p['title'] ?? '');
        $instructions = $p['instructions'] ?? null;
        $time_limit_minutes_raw = trim((string) ($p['time_limit_minutes'] ?? ''));
        $time_limit_minutes = (int) $time_limit_minutes_raw;

        if ($time_limit_minutes_raw === '' || $time_limit_minutes <= 0) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'El tiempo (min) es obligatorio']);
            return;
        }
        $available_from = ($p['available_from'] ?? '') ?: null;
        $due_at = ($p['due_at'] ?? '') ?: null;
        $passing_score = (int) ($p['passing_score'] ?? 70);
        $show_results = $p['show_results'] ?? 'AFTER_SUBMIT';
        $is_exam = (int) ($p['is_exam'] ?? 0);

        if (!$section_id || $title === '') {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Faltan datos (section_id, title)']);
            return;
        }
        $allowedShow = ['NO', 'AFTER_SUBMIT', 'AFTER_DUE'];
        if (!in_array($show_results, $allowedShow, true))
            $show_results = 'AFTER_SUBMIT';

        $sec = (new CourseSection())->get($section_id);
        if (!$sec) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Sección no encontrada']);
            return;
        }

        $this->assertDocenteOwnsCourse((int) $sec['course_id']);

        $id = (new Quiz())->upsertBySection(
            $section_id,
            $title,
            $instructions,
            $time_limit_minutes,
            $available_from,
            $due_at,
            $passing_score,
            $show_results,
            $is_exam
        );

        if ($id)
            echo json_encode(['status' => 'success', 'id' => $id]);
        else {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'No se pudo guardar el quiz']);
        }
    }

    public function getQuizBySection(): void
    {
        require_login();
        $section_id = (int) ($_GET['section_id'] ?? 0);
        if (!$section_id) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Falta section_id']);
            return;
        }
        $q = (new Quiz())->getBySection($section_id);
        echo json_encode(['status' => 'success', 'data' => $q]);
    }

    public function listQuizQuestions(): void
    {
        require_login();
        $quiz_id = (int) ($_GET['quiz_id'] ?? 0);
        if (!$quiz_id) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Falta quiz_id']);
            return;
        }

        $qq = new QuizQuestion();
        $qo = new QuizOption();
        $questions = $qq->listByQuiz($quiz_id);
        foreach ($questions as &$q) {
            $q['options'] = $qo->listByQuestion((int) $q['id']);
        }
        echo json_encode(['status' => 'success', 'data' => $questions]);
    }

    public function upsertQuizQuestion(): void
    {
        require_login();
        require_role(['ADMIN', 'DOCENTE']);
        $p = $this->payload();

        $quiz_id = (int) ($p['quiz_id'] ?? 0);
        $id = (int) ($p['id'] ?? 0);
        $type = $p['type'] ?? 'MCQ';
        $text = trim($p['question_text'] ?? '');
        $points = (int) ($p['points'] ?? 10);
        $orden = (int) ($p['orden'] ?? 0);

        if (!$quiz_id || $text === '') {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Faltan datos (quiz_id, question_text)']);
            return;
        }
        $allowed = ['MCQ', 'TF', 'SHORT'];
        if (!in_array($type, $allowed, true))
            $type = 'MCQ';

        $quiz = (new Quiz())->getWithCourse($quiz_id);
        if (!$quiz) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Quiz no encontrado']);
            return;
        }
        $this->assertDocenteOwnsCourse((int) $quiz['course_id']);

        $qid = (new QuizQuestion())->upsert($id, $quiz_id, $type, $text, $points, $orden);
        if ($qid)
            echo json_encode(['status' => 'success', 'id' => $qid]);
        else {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'No se pudo guardar pregunta']);
        }
    }

    public function deleteQuizQuestion(): void
    {
        require_login();
        require_role(['ADMIN', 'DOCENTE']);
        $p = $this->payload();

        $quiz_id = (int) ($p['quiz_id'] ?? 0);
        $id = (int) ($p['id'] ?? 0);
        if (!$quiz_id || !$id) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Faltan datos']);
            return;
        }

        $quiz = (new Quiz())->getWithCourse($quiz_id);
        if (!$quiz) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Quiz no encontrado']);
            return;
        }
        $this->assertDocenteOwnsCourse((int) $quiz['course_id']);

        if ((new QuizQuestion())->delete($id, $quiz_id))
            echo json_encode(['status' => 'success']);
        else {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'No se pudo eliminar']);
        }
    }

    public function upsertQuizOption(): void
    {
        require_login();
        require_role(['ADMIN', 'DOCENTE']);
        $p = $this->payload();

        $question_id = (int) ($p['question_id'] ?? 0);
        $id = (int) ($p['id'] ?? 0);
        $text = trim($p['option_text'] ?? '');
        $is_correct = (int) ($p['is_correct'] ?? 0);
        $orden = (int) ($p['orden'] ?? 0);

        if (!$question_id || $text === '') {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Faltan datos (question_id, option_text)']);
            return;
        }

        $qq = new QuizQuestion();
        $qrow = $qq->get($question_id);
        if (!$qrow) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Pregunta no encontrada']);
            return;
        }

        $quiz = (new Quiz())->getWithCourse((int) $qrow['quiz_id']);
        if (!$quiz) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Quiz no encontrado']);
            return;
        }
        $this->assertDocenteOwnsCourse((int) $quiz['course_id']);

        $qo = new QuizOption();
        $oid = $qo->upsert($id, $question_id, $text, $is_correct, $orden);
        if (!$oid) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'No se pudo guardar opción']);
            return;
        }

        if ($is_correct === 1)
            $qo->clearCorrectExcept($question_id, $oid);

        echo json_encode(['status' => 'success', 'id' => $oid]);
    }

    public function deleteQuizOption(): void
    {
        require_login();
        require_role(['ADMIN', 'DOCENTE']);
        $p = $this->payload();

        $id = (int) ($p['id'] ?? 0);
        if (!$id) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Falta id']);
            return;
        }

        // Para simplificar MVP, no validamos dueño aquí por join extra (opcional).
        // Si querés 100% seguro: query por option->question->quiz->course.
        if ((new QuizOption())->delete($id))
            echo json_encode(['status' => 'success']);
        else {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'No se pudo eliminar']);
        }
    }

    public function startQuizAttempt(): void
    {
        require_login();
        require_role(['ESTUDIANTE']);
        $u = current_user();
        $p = $this->payload();

        $quiz_id = (int) ($p['quiz_id'] ?? 0);
        if (!$quiz_id) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Falta quiz_id']);
            return;
        }

        $quiz = (new Quiz())->get($quiz_id);
        if (!$quiz) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Quiz no encontrado']);
            return;
        }

        // ventana disponible
        $tz = new DateTimeZone('America/Costa_Rica');
        $now = new DateTime('now', $tz);

        if (!empty($quiz['available_from'])) {
            $af = new DateTime($quiz['available_from'], $tz);
            if ($now < $af) {
                http_response_code(409);
                echo json_encode(['status' => 'error', 'message' => 'Aún no disponible']);
                return;
            }
        }
        if (!empty($quiz['due_at'])) {
            $due = new DateTime($quiz['due_at'], $tz);
            if ($now > $due) {
                http_response_code(409);
                echo json_encode(['status' => 'error', 'message' => 'Fecha límite vencida']);
                return;
            }
        }

        $student = (new Student())->getByUserId((int) $u['id']);
        if (!$student) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'No hay ficha de estudiante']);
            return;
        }

        $tz = new DateTimeZone('America/Costa_Rica');
        $now = new DateTime('now', $tz);

        $max = (new QuizQuestion())->sumPoints($quiz_id);
        $attemptModel = new QuizAttempt();

        $existing = $attemptModel->getMine($quiz_id, (int) $student['id']);

        if ($existing) {
            $attemptId = (int) $existing['id'];

            // Si ya fue enviado/calificado, mantenemos 1 intento (MVP)
            if (($existing['status'] ?? '') !== 'IN_PROGRESS') {
                echo json_encode(['status' => 'success', 'attempt_id' => $attemptId]);
                return;
            }

            // Si no tiene respuestas o ya venció, reiniciamos el intento
            $ansCount = $attemptModel->countAnswers($attemptId);

            $limit = $quiz['time_limit_minutes'];
            $expired = false;

            if ($limit !== null && $limit !== '' && (int) $limit > 0) {
                $st = new DateTime($existing['started_at'], $tz);
                $deadline = (clone $st)->modify('+' . (int) $limit . ' minutes');
                if ($now > $deadline)
                    $expired = true;
            }

            if ($ansCount === 0 || $expired) {
                $attemptModel->clearAnswers($attemptId);
                $attemptModel->restart($attemptId, $max);
            }

            echo json_encode(['status' => 'success', 'attempt_id' => $attemptId]);
            return;
        }

        // si no existe, crearlo
        $attemptId = $attemptModel->start($quiz_id, (int) $student['id'], $max);

        if ($attemptId)
            echo json_encode(['status' => 'success', 'attempt_id' => $attemptId]);
        else {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'No se pudo iniciar intento']);
        }

    }

    public function getMyQuizAttempt(): void
    {
        require_login();
        require_role(['ESTUDIANTE']);
        $u = current_user();

        $quiz_id = (int) ($_GET['quiz_id'] ?? 0);
        if (!$quiz_id) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Falta quiz_id']);
            return;
        }

        $quiz = (new Quiz())->get($quiz_id);
        if (!$quiz) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Quiz no encontrado']);
            return;
        }

        $student = (new Student())->getByUserId((int) $u['id']);
        if (!$student) {
            echo json_encode(['status' => 'success', 'data' => null]);
            return;
        }

        $att = (new QuizAttempt())->getMine($quiz_id, (int) $student['id']);
        if (!$att) {
            echo json_encode(['status' => 'success', 'data' => null]);
            return;
        }

        $answers = (new QuizAttempt())->listAnswers((int) $att['id']);
        echo json_encode(['status' => 'success', 'data' => ['attempt' => $att, 'answers' => $answers]]);
    }

    public function submitQuizAttempt(): void
    {
        require_login();
        require_role(['ESTUDIANTE']);
        $u = current_user();
        $p = $this->payload();

        $quiz_id = (int) ($p['quiz_id'] ?? 0);
        $answersJson = $p['answers'] ?? '[]';

        $tz = new DateTimeZone('America/Costa_Rica');
        $now = new DateTime('now', $tz);



        if (!$quiz_id) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Falta quiz_id']);
            return;
        }

        $quiz = (new Quiz())->get($quiz_id);
        if (!$quiz) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Quiz no encontrado']);
            return;
        }

        if (!empty($quiz['available_from'])) {
            $af = new DateTime($quiz['available_from'], $tz);
            if ($now < $af) {
                http_response_code(409);
                echo json_encode(['status' => 'error', 'message' => 'Aún no disponible']);
                return;
            }
        }

        // ventana
        // ventana (con TZ consistente)
        if (!empty($quiz['due_at'])) {
            $due = new DateTime($quiz['due_at'], $tz);
            if ($now > $due) {
                http_response_code(409);
                echo json_encode(['status' => 'error', 'message' => 'Fecha límite vencida']);
                return;
            }
        }


        $student = (new Student())->getByUserId((int) $u['id']);
        if (!$student) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'No hay ficha de estudiante']);
            return;
        }

        $attemptModel = new QuizAttempt();
        $att = $attemptModel->getMine($quiz_id, (int) $student['id']);
        if (!$att) {
            http_response_code(409);
            echo json_encode(['status' => 'error', 'message' => 'Debes iniciar el intento primero']);
            return;
        }
        if (($att['status'] ?? '') !== 'IN_PROGRESS') {
            http_response_code(409);
            echo json_encode(['status' => 'error', 'message' => 'Este intento ya fue enviado']);
            return;
        }

        // time limit (hard)
        $limit = $quiz['time_limit_minutes'];
        if ($limit !== null && $limit !== '' && (int) $limit > 0) {
            $st = new DateTime($att['started_at'], $tz);
            $st->modify('+' . (int) $limit . ' minutes');
            if ($now > $st) {
                http_response_code(409);
                echo json_encode(['status' => 'error', 'message' => 'Tiempo vencido']);
                return;
            }
        }

        $answersArr = json_decode($answersJson, true);
        if (!is_array($answersArr))
            $answersArr = [];

        $qq = new QuizQuestion();
        $qo = new QuizOption();

        $questions = $qq->listByQuiz($quiz_id);
        $qById = [];
        foreach ($questions as $q)
            $qById[(int) $q['id']] = $q;

        // guardar respuestas
        foreach ($answersArr as $a) {
            $qid = (int) ($a['question_id'] ?? 0);
            if (!$qid || !isset($qById[$qid]))
                continue;

            $selected = isset($a['selected_option_id']) ? (int) $a['selected_option_id'] : null;
            $text = isset($a['answer_text']) ? (string) $a['answer_text'] : null;

            $attemptModel->upsertAnswer((int) $att['id'], $qid, $selected, $text);
        }

        // auto-calificar MCQ/TF
        $raw = 0;
        $max = (int) ($att['max_points'] ?? $qq->sumPoints($quiz_id));
        $hasShort = false;

        $saved = $attemptModel->listAnswers((int) $att['id']);
        $ansMap = [];
        foreach ($saved as $sa)
            $ansMap[(int) $sa['question_id']] = $sa;

        foreach ($questions as $q) {
            $qid = (int) $q['id'];
            $type = $q['type'];
            $points = (int) $q['points'];

            if ($type === 'SHORT') {
                $hasShort = true;
                continue;
            }

            $correctId = $qo->getCorrectOptionId($qid);
            $chosen = isset($ansMap[$qid]) ? (int) ($ansMap[$qid]['selected_option_id'] ?? 0) : 0;

            $isCorrect = ($correctId && $chosen && $chosen === $correctId) ? 1 : 0;
            $awarded = $isCorrect ? $points : 0;

            // actualizar respuesta con is_correct y points_awarded
            $attemptModel->setAnswerAutoGrade((int) $att['id'], $qid, $isCorrect, $awarded);

            $raw += $awarded;
        }

        $score = ($max > 0) ? (int) round(($raw / $max) * 100) : 0;
        $status = $hasShort ? 'SUBMITTED' : 'GRADED';

        $ok = $attemptModel->finish((int) $att['id'], $status, $raw, $max, $score);
        if ($ok)
            echo json_encode(['status' => 'success', 'data' => ['status' => $status, 'score' => $score]]);
        else {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'No se pudo enviar']);
        }
    }

    public function listQuizAttempts(): void
    {
        require_login();
        require_role(['ADMIN', 'DOCENTE']);
        $quiz_id = (int) ($_GET['quiz_id'] ?? 0);
        if (!$quiz_id) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Falta quiz_id']);
            return;
        }

        $quiz = (new Quiz())->getWithCourse($quiz_id);
        if (!$quiz) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Quiz no encontrado']);
            return;
        }
        $this->assertDocenteOwnsCourse((int) $quiz['course_id']);

        $rows = (new QuizAttempt())->listByQuiz($quiz_id);
        echo json_encode(['status' => 'success', 'data' => $rows]);
    }

    public function gradeShort(): void
    {
        require_login();
        require_role(['ADMIN', 'DOCENTE']);
        http_response_code(501);
        echo json_encode(['status' => 'error', 'message' => 'gradeShort aún no implementado']);
    }

}
