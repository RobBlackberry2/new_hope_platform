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
// Nota: lógica de quices/exámenes se movió a QuizController.php

class VirtualCampusController
{
    public function getCourse(): void
    {
        require_login();
        $u = current_user();
        $id = (int) ($_GET['id'] ?? 0);
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

        $rol = $u['rol'] ?? '';
        if ($rol === 'ADMIN') {
            echo json_encode(['status' => 'success', 'data' => $c]);
            return;
        }

        if ($rol === 'DOCENTE') {
            if ((int)($c['docente_user_id'] ?? 0) !== (int)($u['id'] ?? 0)) {
                http_response_code(403);
                echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
                return;
            }
            echo json_encode(['status' => 'success', 'data' => $c]);
            return;
        }

        if ($rol === 'ESTUDIANTE') {
            $student = new Student();
            $s = $student->getByUserId((int) $u['id']);
            $seccion = $s['seccion'] ?? '';
            if (!$seccion || $seccion !== ($c['seccion'] ?? '')) {
                http_response_code(403);
                echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
                return;
            }
            echo json_encode(['status' => 'success', 'data' => $c]);
            return;
        }

        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
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

    // Nota: quices/exámenes movidos a QuizController
}
