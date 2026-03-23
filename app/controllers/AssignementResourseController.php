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

require_once __DIR__ . '/../services/OneDriveService.php';

class AssignementResourseController
{
    // =========================
    // Helpers
    // =========================

    private function canAccessCourseAsStudent(int $course_id, int $user_id): bool
    {
        $c = (new Course())->get($course_id);
        if (!$c) return false;

        $s = (new Student())->getByUserId($user_id);
        if (!$s) return false;

        return (($s['seccion'] ?? '') !== '' && ($s['seccion'] ?? '') === ($c['seccion'] ?? ''));
    }

    private function canManageCourseAsTeacher(int $course_id, array $user): bool
    {
        if (($user['rol'] ?? '') === 'ADMIN') return true;
        if (($user['rol'] ?? '') !== 'DOCENTE') return false;

        $c = (new Course())->get($course_id);
        return $c && (int)$c['docente_user_id'] === (int)$user['id'];
    }

    private function normalizeUploads(array $files, string $fieldName): array
    {
        if (!isset($files[$fieldName])) return [];
        $f = $files[$fieldName];

        if (is_array($f['name'])) {
            $out = [];
            $n = count($f['name']);
            for ($i = 0; $i < $n; $i++) {
                $out[] = [
                    'name' => $f['name'][$i],
                    'type' => $f['type'][$i] ?? 'application/octet-stream',
                    'tmp_name' => $f['tmp_name'][$i],
                    'error' => $f['error'][$i],
                    'size' => (int)($f['size'][$i] ?? 0),
                ];
            }
            return $out;
        }

        return [[
            'name' => $f['name'],
            'type' => $f['type'] ?? 'application/octet-stream',
            'tmp_name' => $f['tmp_name'],
            'error' => $f['error'],
            'size' => (int)($f['size'] ?? 0),
        ]];
    }

    private function uploadErrorMessage(int $code): string
    {
        switch ($code) {
            case UPLOAD_ERR_INI_SIZE: return 'El archivo excede upload_max_filesize (php.ini).';
            case UPLOAD_ERR_FORM_SIZE: return 'El archivo excede MAX_FILE_SIZE del formulario.';
            case UPLOAD_ERR_PARTIAL: return 'El archivo se subió parcialmente.';
            case UPLOAD_ERR_NO_FILE: return 'No se recibió archivo.';
            case UPLOAD_ERR_NO_TMP_DIR: return 'Falta carpeta temporal del servidor.';
            case UPLOAD_ERR_CANT_WRITE: return 'No se pudo escribir el archivo en disco.';
            case UPLOAD_ERR_EXTENSION: return 'Una extensión de PHP detuvo la subida.';
            default: return 'Error desconocido al subir archivo.';
        }
    }


    private function validateUploadFile(array $file): ?string
    {
        $err = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($err !== UPLOAD_ERR_OK) {
            return $this->uploadErrorMessage($err);
        }

        $size = (int)($file['size'] ?? 0);
        $maxSize = 500 * 1024;
        if ($size <= 0) return 'Archivo inválido o vacío.';
        if ($size > $maxSize) return 'El archivo excede el tamaño máximo permitido de 500 KB.';

        $original = (string)($file['name'] ?? '');
        $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        $allowedExt = ['pdf', 'zip', 'jpg', 'jpeg'];
        if (!in_array($ext, $allowedExt, true)) {
            return 'Formato no permitido. Solo se aceptan PDF, ZIP o JPG.';
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $detectedMime = $finfo ? (string)finfo_file($finfo, (string)$file['tmp_name']) : '';
        if ($finfo) finfo_close($finfo);

        $allowedByExt = [
            'pdf' => ['application/pdf'],
            'zip' => ['application/zip', 'application/x-zip-compressed', 'multipart/x-zip'],
            'jpg' => ['image/jpeg'],
            'jpeg' => ['image/jpeg'],
        ];

        if ($detectedMime !== '' && !in_array($detectedMime, $allowedByExt[$ext] ?? [], true)) {
            return 'El archivo no coincide con un tipo permitido (PDF, ZIP o JPG).';
        }

        return null;
    }

    private function listStudentsForCourse(int $course_id): array
    {
        $c = (new Course())->get($course_id);
        if (!$c) return [];

        require_once __DIR__ . '/../config/db.php';
        $db = Database::connect();

        $stmt = $db->prepare(
            'SELECT s.id, TRIM(CONCAT(s.nombre, " ", COALESCE(s.apellidos, ""))) AS nombre, s.grado, s.seccion, s.user_id
             FROM students s
             WHERE s.seccion = ?
             ORDER BY s.nombre ASC, s.apellidos ASC'
        );
        $seccion = (string)($c['seccion'] ?? '');
        $stmt->bind_param('s', $seccion);
        $stmt->execute();
        $res = $stmt->get_result();
        return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    }

    // =========================
    // RECURSOS + INSTRUCCIONES (TAREA)
    // =========================

    public function uploadResource(): void
    {
        require_login();
        require_role(['ADMIN', 'DOCENTE']);
        $u = current_user();

        $section_id = (int)($_POST['section_id'] ?? 0);
        if (!$section_id) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Falta section_id']);
            return;
        }

        $sec = (new CourseSection())->get($section_id);
        if (!$sec) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Sección no encontrada']);
            return;
        }

        $course_id = (int)$sec['course_id'];
        if (!$this->canManageCourseAsTeacher($course_id, $u)) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
            return;
        }

        $tipo = $sec['tipo'] ?? '';
        if (!in_array($tipo, ['RECURSOS', 'TAREA'], true)) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Esta sección no acepta archivos']);
            return;
        }

        $uploads = $this->normalizeUploads($_FILES, 'files');
        if (!$uploads) $uploads = $this->normalizeUploads($_FILES, 'file');

        if (!$uploads) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'No se recibieron archivos']);
            return;
        }

        $svc = new OneDriveService();
        $model = new CourseResource();

        $subfolder = ($tipo === 'TAREA') ? 'instructions' : 'resources';
        $folder = "course_{$course_id}/section_{$section_id}/{$subfolder}";

        $createdIds = [];
        $errors = [];
        foreach ($uploads as $file) {
            $validationError = $this->validateUploadFile($file);
            if ($validationError !== null) {
                $errors[] = $validationError;
                continue;
            }

            $original = basename($file['name']);
            $mime = $file['type'] ?? 'application/octet-stream';
            $size = (int)$file['size'];

            $item = $svc->uploadLocalFileToPath($folder, $file['tmp_name'], $original);
            $itemId = $item['id'] ?? null;
            if (!$itemId) continue;

            $id = $model->create(
                $section_id,
                $itemId,
                $original,
                $mime,
                $size,
                (int)$u['id'],
                'onedrive',
                $itemId,
                null
            );

            if ($id) $createdIds[] = $id;
        }

        if (count($createdIds) === 0) {
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => 'No se pudo subir ningún archivo.',
                'detail' => implode(' | ', array_unique($errors))
            ]);
            return;
        }

        echo json_encode(['status' => 'success', 'ids' => $createdIds, 'errors' => array_values(array_unique($errors))]);
    }

    public function listResources(): void
    {
        require_login();
        $u = current_user();

        $section_id = (int)($_GET['section_id'] ?? 0);
        if (!$section_id) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Falta section_id']);
            return;
        }

        $sec = (new CourseSection())->get($section_id);
        if (!$sec) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Sección no encontrada']);
            return;
        }

        $course_id = (int)$sec['course_id'];
        $rol = $u['rol'] ?? '';
        $ok = false;

        if ($rol === 'ADMIN') $ok = true;
        elseif ($rol === 'DOCENTE') $ok = $this->canManageCourseAsTeacher($course_id, $u);
        elseif ($rol === 'ESTUDIANTE') $ok = $this->canAccessCourseAsStudent($course_id, (int)$u['id']);

        if (!$ok) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
            return;
        }

        $items = (new CourseResource())->listBySection($section_id);
        echo json_encode(['status' => 'success', 'data' => $items]);
    }

    public function downloadResource(): void
    {
        require_login();
        $u = current_user();

        $id = (int)($_GET['id'] ?? 0);
        if (!$id) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Falta id']);
            return;
        }

        $m = new CourseResource();
        $info = $m->getWithCourse($id);
        if (!$info) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'No encontrado']);
            return;
        }

        $course_id = (int)$info['course_id'];
        $rol = $u['rol'] ?? '';

        $ok = false;
        if ($rol === 'ADMIN') $ok = true;
        elseif ($rol === 'DOCENTE') $ok = ((int)$info['docente_user_id'] === (int)$u['id']);
        elseif ($rol === 'ESTUDIANTE') $ok = $this->canAccessCourseAsStudent($course_id, (int)$u['id']);

        if (!$ok) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
            return;
        }

        $itemId = $info['storage_item_id'] ?? $info['stored_name'] ?? null;
        if (!$itemId) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'No hay itemId asociado al archivo']);
            return;
        }

        $svc = new OneDriveService();
        $dl = $svc->getDownloadUrl($itemId);

        header_remove('Content-Type');
        header('Location: ' . $dl);
        exit;
    }

    public function deleteResource(): void
    {
        require_login();
        require_role(['ADMIN', 'DOCENTE']);
        $u = current_user();

        $id = (int)($_POST['id'] ?? 0);
        if (!$id) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Falta id']);
            return;
        }

        $m = new CourseResource();
        $info = $m->getWithCourse($id);
        if (!$info) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'No encontrado']);
            return;
        }

        if (!$this->canManageCourseAsTeacher((int)$info['course_id'], $u)) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
            return;
        }

        $itemId = $info['storage_item_id'] ?? $info['stored_name'] ?? null;

        $row = $m->delete($id);
        if (!$row) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'No se pudo eliminar en BD']);
            return;
        }

        if ($itemId) {
            try {
                (new OneDriveService())->deleteItem($itemId);
            } catch (Throwable $e) { /* opcional log */ }
        }

        echo json_encode(['status' => 'success']);
    }

    // =========================
    // TAREA (configuración)
    // =========================

    public function upsertAssignment(): void
    {
        require_login();
        require_role(['ADMIN', 'DOCENTE']);
        $u = current_user();

        $section_id = (int)($_POST['section_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $instructions = $_POST['instructions'] ?? null;
        $due_at = $_POST['due_at'] ?? null;

        $is_group = (int)($_POST['is_group'] ?? 0);
        $weight_percent_raw = $_POST['weight_percent'] ?? '';
        $weight_percent = ($weight_percent_raw === '' ? null : (int)$weight_percent_raw);
        $max_score = (int)($_POST['max_score'] ?? 100);
        $passing_score = (int)($_POST['passing_score'] ?? 70);

        if (!$section_id || $title === '') {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Faltan datos (section_id, title)']);
            return;
        }

        $sec = (new CourseSection())->get($section_id);
        if (!$sec || (($sec['tipo'] ?? '') !== 'TAREA')) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'La sección no es de tipo TAREA']);
            return;
        }

        $course_id = (int)$sec['course_id'];
        if (!$this->canManageCourseAsTeacher($course_id, $u)) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
            return;
        }

        // (si ya implementaste validación global de pesos en Course.php, aquí va)
        // puedes mantener tu validación existente y este controller seguirá funcionando

        $a = new Assignment();
        $id = $a->upsertBySection($section_id, $title, $instructions, $due_at, $is_group, $weight_percent, $max_score, $passing_score);

        if ($id) {
            echo json_encode(['status' => 'success', 'id' => $id]);
        } else {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'No se pudo guardar la tarea']);
        }
    }

    public function getAssignmentBySection(): void
    {
        require_login();
        $u = current_user();

        $section_id = (int)($_GET['section_id'] ?? 0);
        if (!$section_id) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Falta section_id']);
            return;
        }

        $sec = (new CourseSection())->get($section_id);
        if (!$sec) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Sección no encontrada']);
            return;
        }

        $course_id = (int)$sec['course_id'];
        $rol = $u['rol'] ?? '';
        $ok = false;

        if ($rol === 'ADMIN') $ok = true;
        elseif ($rol === 'DOCENTE') $ok = $this->canManageCourseAsTeacher($course_id, $u);
        elseif ($rol === 'ESTUDIANTE') $ok = $this->canAccessCourseAsStudent($course_id, (int)$u['id']);

        if (!$ok) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
            return;
        }

        $row = (new Assignment())->getBySection($section_id);
        echo json_encode(['status' => 'success', 'data' => $row]);
    }

    // =========================
    // GRUPOS DE TRABAJO (tareas grupales)
    // =========================

    public function createGroup(): void
    {
        require_login();
        require_role(['ADMIN', 'DOCENTE']);
        $u = current_user();

        $course_id = (int)($_POST['course_id'] ?? 0);
        $name = trim((string)($_POST['name'] ?? ''));

        if (!$course_id || $name === '') {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Faltan datos (course_id, name)']);
            return;
        }

        if (!$this->canManageCourseAsTeacher($course_id, $u)) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
            return;
        }

        $id = (new SubmissionGroup())->create($course_id, $name);
        if (!$id) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'No se pudo crear el grupo']);
            return;
        }

        echo json_encode(['status' => 'success', 'id' => $id]);
    }

    public function listGroups(): void
    {
        require_login();
        $u = current_user();
        $course_id = (int)($_GET['course_id'] ?? 0);

        if (!$course_id) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Falta course_id']);
            return;
        }

        $rol = $u['rol'] ?? '';
        $gModel = new SubmissionGroup();

        if ($rol === 'ADMIN' || $rol === 'DOCENTE') {
            if (!$this->canManageCourseAsTeacher($course_id, $u)) {
                http_response_code(403);
                echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
                return;
            }

            $groups = $gModel->list($course_id);
            foreach ($groups as &$g) {
                $g['members'] = $gModel->listMembers((int)$g['id']);
            }

            $students = $this->listStudentsForCourse($course_id);

            echo json_encode([
                'status' => 'success',
                'data' => [
                    'groups' => $groups,
                    'students' => $students
                ]
            ]);
            return;
        }

        if ($rol === 'ESTUDIANTE') {
            if (!$this->canAccessCourseAsStudent($course_id, (int)$u['id'])) {
                http_response_code(403);
                echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
                return;
            }

            $student = (new Student())->getByUserId((int)$u['id']);
            if (!$student) {
                echo json_encode(['status' => 'success', 'data' => ['my_group' => null]]);
                return;
            }

            // si existe getGroupForStudentDetailed en modelo, úsalo. Si no, armamos detalle manual:
            $myGroup = null;
            if (method_exists($gModel, 'getGroupForStudentDetailed')) {
                $myGroup = $gModel->getGroupForStudentDetailed($course_id, (int)$student['id']);
            } else {
                $gid = $gModel->getGroupForStudent($course_id, (int)$student['id']);
                if ($gid) {
                    $myGroup = $gModel->get((int)$gid);
                    if ($myGroup) $myGroup['members'] = $gModel->listMembers((int)$gid);
                }
            }

            echo json_encode(['status' => 'success', 'data' => ['my_group' => $myGroup]]);
            return;
        }

        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
    }

    public function setGroupMembers(): void
    {
        require_login();
        require_role(['ADMIN', 'DOCENTE']);
        $u = current_user();

        $group_id = (int)($_POST['group_id'] ?? 0);
        $student_ids_raw = $_POST['student_ids'] ?? [];

        if (!$group_id) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Falta group_id']);
            return;
        }

        $gModel = new SubmissionGroup();
        $group = $gModel->get($group_id);
        if (!$group) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Grupo no encontrado']);
            return;
        }

        $course_id = (int)$group['course_id'];
        if (!$this->canManageCourseAsTeacher($course_id, $u)) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
            return;
        }

        // Normalizar student_ids (array o JSON)
        $student_ids = [];
        if (is_string($student_ids_raw)) {
            $decoded = json_decode($student_ids_raw, true);
            if (is_array($decoded)) $student_ids_raw = $decoded;
        }
        if (is_array($student_ids_raw)) {
            foreach ($student_ids_raw as $sid) {
                $sid = (int)$sid;
                if ($sid > 0) $student_ids[] = $sid;
            }
        }
        $student_ids = array_values(array_unique($student_ids));

        // Validar que pertenezcan al curso
        $allowedStudents = $this->listStudentsForCourse($course_id);
        $allowedSet = [];
        foreach ($allowedStudents as $s) $allowedSet[(int)$s['id']] = true;

        foreach ($student_ids as $sid) {
            if (!isset($allowedSet[$sid])) {
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => "Estudiante inválido para este curso (ID {$sid})"]);
                return;
            }
        }

        // Regla recomendada: sacar a esos estudiantes de otros grupos del mismo curso (evita duplicidad)
        // (si tu modelo no tiene helper, lo manejamos directo con SQL)
        require_once __DIR__ . '/../config/db.php';
        $db = Database::connect();
        if (count($student_ids) > 0) {
            $placeholders = implode(',', array_fill(0, count($student_ids), '?'));
            $types = 'i' . str_repeat('i', count($student_ids)) . 'i';
            $sql = "DELETE gm FROM submission_group_members gm
                    JOIN submission_groups g ON g.id = gm.group_id
                    WHERE g.course_id = ? AND gm.student_id IN ($placeholders) AND gm.group_id <> ?";
            $stmt = $db->prepare($sql);
            $params = [$course_id, ...$student_ids, $group_id];
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
        }

        if (!$gModel->setMembers($group_id, $student_ids)) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'No se pudo guardar miembros']);
            return;
        }

        echo json_encode(['status' => 'success']);
    }

    // =========================
    // ENTREGAS DE TAREA
    // =========================

    public function uploadSubmission(): void
    {
        require_login();
        require_role(['ESTUDIANTE']);
        $u = current_user();

        $uploads = $this->normalizeUploads($_FILES, 'files');
        if (!$uploads) $uploads = $this->normalizeUploads($_FILES, 'file');

        if (!$uploads) {
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => 'No se recibió archivo.',
                'detail' => '$_FILES llegó vacío. Verificá que el input tenga name="files[]" (o name="file") y que el submit use FormData.'
            ]);
            return;
        }

        $assignment_id = (int)($_POST['assignment_id'] ?? 0);
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

        $course_id = (int)$a['course_id'];
        if (!$this->canAccessCourseAsStudent($course_id, (int)$u['id'])) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
            return;
        }

        $due_at = $a['due_at'] ?? null;
        if ($due_at) {
            $tz = new DateTimeZone('America/Costa_Rica');
            $now = new DateTime('now', $tz);
            $due = new DateTime($due_at, $tz);
            if ($now > $due) {
                http_response_code(409);
                echo json_encode(['status' => 'error', 'message' => 'Fecha límite vencida']);
                return;
            }
        }

        $student = (new Student())->getByUserId((int)$u['id']);
        if (!$student) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'No hay ficha de estudiante asociada']);
            return;
        }
        $student_id = (int)$student['id'];

        $is_group = (int)($a['is_group'] ?? 0);
        $group_id = null;

        if ($is_group === 1) {
            $gModel = new SubmissionGroup();
            $gid = $gModel->getGroupForStudent($course_id, $student_id);
            if (!$gid) {
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => 'Tarea grupal: no tienes grupo asignado']);
                return;
            }
            $group_id = (int)$gid;
        }

        $subModel = new Submission();
        $submission_id = $subModel->getOrCreate(
            $assignment_id,
            $is_group ? null : $student_id,
            $is_group ? $group_id : null
        );

        if (!$submission_id) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'No se pudo crear la entrega']);
            return;
        }

        $svc = new OneDriveService();

        if ($is_group === 1 && $group_id) {
            $folder = "course_{$course_id}/assignment_{$assignment_id}/submissions/group_{$group_id}";
        } else {
            $folder = "course_{$course_id}/assignment_{$assignment_id}/submissions/student_{$student_id}";
        }

        $fileIds = [];
        $errors = [];

        foreach ($uploads as $file) {
            $validationError = $this->validateUploadFile($file);
            if ($validationError !== null) {
                $errors[] = $validationError;
                continue;
            }

            $original = basename($file['name'] ?? 'archivo');
            $mime = $file['type'] ?? 'application/octet-stream';
            $size = (int)($file['size'] ?? 0);

            try {
                $item = $svc->uploadLocalFileToPath($folder, $file['tmp_name'], $original);
                $itemId = $item['id'] ?? null;

                if (!$itemId) {
                    $errors[] = "No se obtuvo ID de OneDrive para: {$original}";
                    continue;
                }

                $fid = $subModel->addFile(
                    (int)$submission_id,
                    $original,
                    $mime,
                    $size,
                    'onedrive',
                    $itemId,
                    null
                );

                if ($fid) $fileIds[] = $fid;
                else $errors[] = "No se pudo guardar en BD el archivo: {$original}";
            } catch (Throwable $ex) {
                $errors[] = $ex->getMessage();
            }
        }

        if (count($fileIds) === 0) {
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => 'No se pudo subir el archivo.',
                'detail' => implode(' | ', array_unique($errors))
            ]);
            return;
        }

        echo json_encode([
            'status' => 'success',
            'submission_id' => (int)$submission_id,
            'file_ids' => $fileIds
        ]);
    }

    public function getMySubmission(): void
    {
        require_login();
        require_role(['ESTUDIANTE']);
        $u = current_user();

        $assignment_id = (int)($_GET['assignment_id'] ?? 0);
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

        $course_id = (int)$a['course_id'];
        if (!$this->canAccessCourseAsStudent($course_id, (int)$u['id'])) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
            return;
        }

        $student = (new Student())->getByUserId((int)$u['id']);
        if (!$student) {
            echo json_encode(['status' => 'success', 'data' => null, 'message' => 'No hay ficha de estudiante']);
            return;
        }
        $student_id = (int)$student['id'];

        $is_group = (int)($a['is_group'] ?? 0);
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

        $files = $subModel->listFiles((int)$sub['id']);
        $grade = (new Grade())->getBySubmission((int)$sub['id']);

        echo json_encode([
            'status' => 'success',
            'data' => [
                'submission' => $sub,
                'files' => $files,
                'grade' => $grade
            ]
        ]);
    }

    public function deleteSubmissionFile(): void
    {
        require_login();
        require_role(['ESTUDIANTE']);
        $u = current_user();

        $file_id = (int)($_POST['file_id'] ?? 0);
        if (!$file_id) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Falta file_id']);
            return;
        }

        $subModel = new Submission();
        $info = $subModel->getFileWithAssignmentAndCourse($file_id);
        if (!$info) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Archivo no encontrado']);
            return;
        }

        $course_id = (int)$info['course_id'];
        if (!$this->canAccessCourseAsStudent($course_id, (int)$u['id'])) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
            return;
        }

        $student = (new Student())->getByUserId((int)$u['id']);
        $student_id = (int)($student['id'] ?? 0);
        if (!$student_id) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'No hay ficha de estudiante']);
            return;
        }

        $ownerStudentId = (int)($info['student_id'] ?? 0);
        $groupId = (int)($info['group_id'] ?? 0);

        $ownsFile = false;
        if ($ownerStudentId > 0 && $ownerStudentId === $student_id) {
            $ownsFile = true;
        }

        if (!$ownsFile && $groupId > 0) {
            if (method_exists($subModel, 'isStudentMemberOfSubmissionGroup')) {
                $ownsFile = $subModel->isStudentMemberOfSubmissionGroup($groupId, $student_id);
            } else {
                // fallback SQL directo si aún no agregaste el método al modelo
                require_once __DIR__ . '/../config/db.php';
                $db = Database::connect();
                $st = $db->prepare('SELECT 1 FROM submission_group_members WHERE group_id=? AND student_id=? LIMIT 1');
                $st->bind_param('ii', $groupId, $student_id);
                $st->execute();
                $ownsFile = (bool)$st->get_result()->fetch_assoc();
            }
        }

        if (!$ownsFile) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'No puedes borrar archivos de otra entrega']);
            return;
        }

        $due_at = $info['due_at'] ?? null;
        if ($due_at) {
            $tz = new DateTimeZone('America/Costa_Rica');
            $now = new DateTime('now', $tz);
            $due = new DateTime($due_at, $tz);
            if ($now > $due) {
                http_response_code(409);
                echo json_encode(['status' => 'error', 'message' => 'Fecha límite vencida: ya no puedes borrar/cambiar archivos']);
                return;
            }
        }

        $row = $subModel->deleteFile($file_id);
        if (!$row) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'No se pudo eliminar']);
            return;
        }

        if (($row['storage_provider'] ?? '') === 'onedrive' && !empty($row['storage_item_id'])) {
            try {
                (new OneDriveService())->deleteItem($row['storage_item_id']);
            } catch (Throwable $e) { /* opcional log */ }
        }

        echo json_encode(['status' => 'success']);
    }

    public function listSubmissionsByAssignment(): void
    {
        require_login();
        require_role(['ADMIN', 'DOCENTE']);
        $u = current_user();

        $assignment_id = (int)($_GET['assignment_id'] ?? 0);
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

        if (!$this->canManageCourseAsTeacher((int)$a['course_id'], $u)) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
            return;
        }

        $subModel = new Submission();
        $rows = $subModel->listByAssignment($assignment_id);

        $gradeModel = new Grade();
        $gModel = new SubmissionGroup();

        foreach ($rows as &$r) {
            $sid = (int)$r['id'];
            $r['files'] = $subModel->listFiles($sid);
            $r['grade'] = $gradeModel->getBySubmission($sid);

            $gid = (int)($r['group_id'] ?? 0);
            $r['group_members'] = $gid > 0 ? $gModel->listMembers($gid) : [];
        }

        echo json_encode(['status' => 'success', 'data' => $rows]);
    }

    public function downloadSubmissionFile(): void
    {
        require_login();
        $u = current_user();

        $file_id = (int)($_GET['file_id'] ?? 0);
        if (!$file_id) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Falta file_id']);
            return;
        }

        $subModel = new Submission();
        $info = $subModel->getFileWithCourseTeacher($file_id);
        if (!$info) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Archivo no encontrado']);
            return;
        }

        $rol = $u['rol'] ?? '';
        $course_id = (int)$info['course_id'];

        $ok = false;
        if ($rol === 'ADMIN') $ok = true;
        elseif ($rol === 'DOCENTE') $ok = ((int)$info['docente_user_id'] === (int)$u['id']);
        elseif ($rol === 'ESTUDIANTE') $ok = $this->canAccessCourseAsStudent($course_id, (int)$u['id']);

        if (!$ok) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
            return;
        }

        if (($info['storage_provider'] ?? '') !== 'onedrive' || empty($info['storage_item_id'])) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Archivo no está en OneDrive']);
            return;
        }

        $dl = (new OneDriveService())->getDownloadUrl($info['storage_item_id']);
        header_remove('Content-Type');
        header('Location: ' . $dl);
        exit;
    }

    // =========================
    // CALIFICACIÓN DE TAREA (individual o grupal)
    // =========================

    public function setGrade(): void
    {
        require_login();
        require_role(['ADMIN', 'DOCENTE']);
        $u = current_user();

        $submission_id = (int)($_POST['submission_id'] ?? 0);
        $score100 = (float)($_POST['score'] ?? 0); // UI envía 0..100
        $feedback = isset($_POST['feedback']) ? trim((string)$_POST['feedback']) : null;

        if (!$submission_id) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Falta submission_id']);
            return;
        }

        if ($score100 < 0 || $score100 > 100) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'La nota debe estar entre 0 y 100']);
            return;
        }

        $subModel = new Submission();

        // Si existe método en modelo úsalo; si no, fallback SQL
        $meta = null;
        if (method_exists($subModel, 'getCourseInfoFromSubmission')) {
            $meta = $subModel->getCourseInfoFromSubmission($submission_id);
        } else {
            require_once __DIR__ . '/../config/db.php';
            $db = Database::connect();
            $stmt = $db->prepare(
                'SELECT sub.assignment_id, cs.course_id
                 FROM submissions sub
                 JOIN assignments a ON a.id = sub.assignment_id
                 JOIN course_sections cs ON cs.id = a.section_id
                 WHERE sub.id = ?
                 LIMIT 1'
            );
            $stmt->bind_param('i', $submission_id);
            $stmt->execute();
            $meta = $stmt->get_result()->fetch_assoc();
        }

        if (!$meta) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Entrega no encontrada']);
            return;
        }

        if (!$this->canManageCourseAsTeacher((int)$meta['course_id'], $u)) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
            return;
        }

        $assignment = (new Assignment())->getWithCourse((int)$meta['assignment_id']);
        if (!$assignment) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Tarea no encontrada']);
            return;
        }

        $maxScore = max(1, (int)($assignment['max_score'] ?? 100));
        $storedScore = (int)round(($score100 / 100) * $maxScore);

        $ok = (new Grade())->upsert($submission_id, $storedScore, $feedback, (int)$u['id']);
        if (!$ok) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'No se pudo guardar la nota']);
            return;
        }

        echo json_encode(['status' => 'success']);
    }
}