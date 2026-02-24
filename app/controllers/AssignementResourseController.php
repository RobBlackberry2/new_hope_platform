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
        if (!$c)
            return false;

        $s = (new Student())->getByUserId($user_id);
        if (!$s)
            return false;

        return (($s['seccion'] ?? '') !== '' && ($s['seccion'] ?? '') === ($c['seccion'] ?? ''));
    }

    private function canManageCourseAsTeacher(int $course_id, array $user): bool
    {
        if (($user['rol'] ?? '') === 'ADMIN')
            return true;
        if (($user['rol'] ?? '') !== 'DOCENTE')
            return false;

        $c = (new Course())->get($course_id);
        return $c && (int) $c['docente_user_id'] === (int) $user['id'];
    }

    private function normalizeUploads(array $files, string $fieldName): array
    {
        if (!isset($files[$fieldName]))
            return [];
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
                    'size' => (int) ($f['size'][$i] ?? 0),
                ];
            }
            return $out;
        }

        return [
            [
                'name' => $f['name'],
                'type' => $f['type'] ?? 'application/octet-stream',
                'tmp_name' => $f['tmp_name'],
                'error' => $f['error'],
                'size' => (int) ($f['size'] ?? 0),
            ]
        ];
    }

    // =========================
    // RECURSOS + INSTRUCCIONES (TAREA)
    // =========================

    // Docente/Admin: sube 1 o varios archivos a una sección RECURSOS o TAREA (instrucciones)
    public function uploadResource(): void
    {
        require_login();
        require_role(['ADMIN', 'DOCENTE']);
        $u = current_user();

        $section_id = (int) ($_POST['section_id'] ?? 0);
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

        $course_id = (int) $sec['course_id'];
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
        if (!$uploads)
            $uploads = $this->normalizeUploads($_FILES, 'file');

        if (!$uploads) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'No se recibieron archivos']);
            return;
        }

        $svc = new OneDriveService();
        $model = new CourseResource();

        // carpeta por tipo
        $subfolder = ($tipo === 'TAREA') ? 'instructions' : 'resources';
        $folder = "course_{$course_id}/section_{$section_id}/{$subfolder}";

        $createdIds = [];
        foreach ($uploads as $file) {
            if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK)
                continue;

            $original = basename($file['name']);
            $mime = $file['type'] ?? 'application/octet-stream';
            $size = (int) $file['size'];

            // Subir a OneDrive desde tmp_name (NO mover a local)
            $item = $svc->uploadLocalFileToPath($folder, $file['tmp_name'], $original);
            $itemId = $item['id'] ?? null;

            if (!$itemId)
                continue;

            // Guardar en BD (course_resources)
            // IMPORTANTE: tu CourseResource::create debe aceptar provider/itemId/public_url según lo que definimos.
            $id = $model->create(
                $section_id,
                $itemId,        // stored_name (para compatibilidad)
                $original,
                $mime,
                $size,
                (int) $u['id'],
                'onedrive',
                $itemId,
                null
            );

            if ($id)
                $createdIds[] = $id;
        }

        echo json_encode(['status' => 'success', 'ids' => $createdIds]);
    }

    // Listar recursos (docente/admin/estudiante con permisos)
    public function listResources(): void
    {
        require_login();
        $u = current_user();

        $section_id = (int) ($_GET['section_id'] ?? 0);
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

        $course_id = (int) $sec['course_id'];

        $rol = $u['rol'] ?? '';
        $ok = false;
        if ($rol === 'ADMIN')
            $ok = true;
        elseif ($rol === 'DOCENTE')
            $ok = $this->canManageCourseAsTeacher($course_id, $u);
        elseif ($rol === 'ESTUDIANTE')
            $ok = $this->canAccessCourseAsStudent($course_id, (int) $u['id']);

        if (!$ok) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
            return;
        }

        $items = (new CourseResource())->listBySection($section_id);
        echo json_encode(['status' => 'success', 'data' => $items]);
    }

    // Descargar recurso/instrucción desde OneDrive
    public function downloadResource(): void
    {
        require_login();
        $u = current_user();

        $id = (int) ($_GET['id'] ?? 0);
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

        $course_id = (int) $info['course_id'];
        $rol = $u['rol'] ?? '';

        $ok = false;
        if ($rol === 'ADMIN')
            $ok = true;
        elseif ($rol === 'DOCENTE')
            $ok = ((int) $info['docente_user_id'] === (int) $u['id']);
        elseif ($rol === 'ESTUDIANTE')
            $ok = $this->canAccessCourseAsStudent($course_id, (int) $u['id']);

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

        // Redirigir al link temporal (más simple que stream)
        header_remove('Content-Type');
        header('Location: ' . $dl);
        exit;
    }

    // Eliminar recurso/instrucción: borra OneDrive + BD
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

        $m = new CourseResource();
        $info = $m->getWithCourse($id);
        if (!$info) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'No encontrado']);
            return;
        }

        if (!$this->canManageCourseAsTeacher((int) $info['course_id'], $u)) {
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

        // borrar en OneDrive (si tiene itemId)
        if ($itemId) {
            try {
                (new OneDriveService())->deleteItem($itemId);
            } catch (Throwable $e) {
                // Si falla borrar en OneDrive, igual ya borraste BD; puedes loguearlo si quieres.
            }
        }

        echo json_encode(['status' => 'success']);
    }

    // =========================
    // TAREA (due_at, estudiante entrega, docente ve/descarga)
    // =========================

    public function upsertAssignment(): void
    {
        require_login();
        require_role(['ADMIN', 'DOCENTE']);
        $u = current_user();

        $section_id = (int) ($_POST['section_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $instructions = $_POST['instructions'] ?? null;
        $due_at = $_POST['due_at'] ?? null;

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
        if (!$sec || (($sec['tipo'] ?? '') !== 'TAREA')) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'La sección no es de tipo TAREA']);
            return;
        }

        $course_id = (int) $sec['course_id'];
        if (!$this->canManageCourseAsTeacher($course_id, $u)) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
            return;
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
        $u = current_user();

        $section_id = (int) ($_GET['section_id'] ?? 0);
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

        $course_id = (int) $sec['course_id'];
        $rol = $u['rol'] ?? '';
        $ok = false;

        if ($rol === 'ADMIN')
            $ok = true;
        elseif ($rol === 'DOCENTE')
            $ok = $this->canManageCourseAsTeacher($course_id, $u);
        elseif ($rol === 'ESTUDIANTE')
            $ok = $this->canAccessCourseAsStudent($course_id, (int) $u['id']);

        if (!$ok) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
            return;
        }

        $row = (new Assignment())->getBySection($section_id);
        echo json_encode(['status' => 'success', 'data' => $row]);
    }

    // Estudiante: sube 1+ archivos a su entrega (OneDrive) hasta due_at
    public function uploadSubmission(): void
    {
        require_login();
        require_role(['ESTUDIANTE']);
        $u = current_user();

        // 1) Normalizar archivos recibidos
        $uploads = $this->normalizeUploads($_FILES, 'files');
        if (!$uploads)
            $uploads = $this->normalizeUploads($_FILES, 'file');

        if (!$uploads) {
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => 'No se recibió archivo.',
                'detail' => '$_FILES llegó vacío. Verificá que el input tenga name="files[]" (o name="file") y que el submit use FormData.'
            ]);
            return;
        }

        // 2) Validar assignment_id
        $assignment_id = (int) ($_POST['assignment_id'] ?? 0);
        if (!$assignment_id) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Falta assignment_id']);
            return;
        }

        // 3) Cargar tarea + curso
        $a = (new Assignment())->getWithCourse($assignment_id);
        if (!$a) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Tarea no encontrada']);
            return;
        }

        $course_id = (int) $a['course_id'];

        // 4) Permiso estudiante en el curso
        if (!$this->canAccessCourseAsStudent($course_id, (int) $u['id'])) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
            return;
        }

        // 5) Validar fecha límite (timezone CR)
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

        // 6) Obtener estudiante_id
        $student = (new Student())->getByUserId((int) $u['id']);
        if (!$student) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'No hay ficha de estudiante asociada']);
            return;
        }
        $student_id = (int) $student['id'];

        // 7) Si es grupal, resolver group_id
        $is_group = (int) ($a['is_group'] ?? 0);
        $group_id = null;

        if ($is_group === 1) {
            $gModel = new SubmissionGroup();
            $gid = $gModel->getGroupForStudent($course_id, $student_id);
            if (!$gid) {
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => 'Tarea grupal: no tienes grupo asignado']);
                return;
            }
            $group_id = (int) $gid;
        }

        // 8) Crear o reutilizar la entrega
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

        // 9) Subir a OneDrive + guardar referencias en BD
        $svc = new OneDriveService();
        $folder = "course_{$course_id}/assignment_{$assignment_id}/submissions/student_{$student_id}";

        $fileIds = [];
        $errors = [];

        foreach ($uploads as $file) {
            $err = $file['error'] ?? UPLOAD_ERR_NO_FILE;

            if ($err !== UPLOAD_ERR_OK) {
                $errors[] = $this->uploadErrorMessage((int) $err);
                continue;
            }

            $original = basename($file['name'] ?? 'archivo');
            $mime = $file['type'] ?? 'application/octet-stream';
            $size = (int) ($file['size'] ?? 0);

            try {
                $item = $svc->uploadLocalFileToPath($folder, $file['tmp_name'], $original);
                $itemId = $item['id'] ?? null;

                if (!$itemId) {
                    $errors[] = "No se obtuvo ID de OneDrive para: {$original}";
                    continue;
                }

                $fid = $subModel->addFile(
                    (int) $submission_id,
                    $original,
                    $mime,
                    $size,
                    'onedrive',
                    $itemId,
                    null
                );

                if ($fid) {
                    $fileIds[] = $fid;
                } else {
                    $errors[] = "No se pudo guardar en BD el archivo: {$original}";
                }

            } catch (Throwable $ex) {
                $errors[] = $ex->getMessage();
            }
        }

        // 10) Si no se guardó ninguno, devolver 400 con detalle
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
            'submission_id' => (int) $submission_id,
            'file_ids' => $fileIds
        ]);
    }

    private function uploadErrorMessage(int $code): string
    {
        switch ($code) {
            case UPLOAD_ERR_INI_SIZE:
                return 'El archivo excede upload_max_filesize (php.ini).';
            case UPLOAD_ERR_FORM_SIZE:
                return 'El archivo excede MAX_FILE_SIZE del formulario.';
            case UPLOAD_ERR_PARTIAL:
                return 'El archivo se subió parcialmente.';
            case UPLOAD_ERR_NO_FILE:
                return 'No se recibió archivo.';
            case UPLOAD_ERR_NO_TMP_DIR:
                return 'Falta carpeta temporal del servidor.';
            case UPLOAD_ERR_CANT_WRITE:
                return 'No se pudo escribir el archivo en disco.';
            case UPLOAD_ERR_EXTENSION:
                return 'Una extensión de PHP detuvo la subida.';
            default:
                return 'Error desconocido al subir archivo.';
        }
    }

    // Estudiante: ver su entrega + archivos
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

        $course_id = (int) $a['course_id'];
        if (!$this->canAccessCourseAsStudent($course_id, (int) $u['id'])) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
            return;
        }

        $student = (new Student())->getByUserId((int) $u['id']);
        if (!$student) {
            echo json_encode(['status' => 'success', 'data' => null, 'message' => 'No hay ficha de estudiante']);
            return;
        }
        $student_id = (int) $student['id'];

        $is_group = (int) ($a['is_group'] ?? 0);
        $group_id = null;
        if ($is_group === 1)
            $group_id = (new SubmissionGroup())->getGroupForStudent($course_id, $student_id);

        $subModel = new Submission();
        $sub = $subModel->getMine($assignment_id, $is_group ? null : $student_id, $is_group ? $group_id : null);
        if (!$sub) {
            echo json_encode(['status' => 'success', 'data' => null]);
            return;
        }

        $files = $subModel->listFiles((int) $sub['id']);
        $grade = (new Grade())->getBySubmission((int) $sub['id']);

        echo json_encode([
            'status' => 'success',
            'data' => [
                'submission' => $sub,
                'files' => $files,
                'grade' => $grade
            ]
        ]);
    }

    // Estudiante: eliminar archivo de entrega antes de due_at (borra OneDrive + BD)
    public function deleteSubmissionFile(): void
    {
        require_login();
        require_role(['ESTUDIANTE']);
        $u = current_user();

        $file_id = (int) ($_POST['file_id'] ?? 0);
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

        $course_id = (int) $info['course_id'];
        if (!$this->canAccessCourseAsStudent($course_id, (int) $u['id'])) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
            return;
        }

        $student = (new Student())->getByUserId((int) $u['id']);
        $student_id = (int) ($student['id'] ?? 0);
        if (!$student_id) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'No hay ficha de estudiante']);
            return;
        }

        if ((int) ($info['student_id'] ?? 0) !== $student_id) {
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

        // Borra en BD primero (retorna fila para saber dónde está)
        $row = $subModel->deleteFile($file_id);
        if (!$row) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'No se pudo eliminar']);
            return;
        }

        // Borra en OneDrive
        if (($row['storage_provider'] ?? '') === 'onedrive' && !empty($row['storage_item_id'])) {
            try {
                (new OneDriveService())->deleteItem($row['storage_item_id']);
            } catch (Throwable $e) {
                // si falla, opcional log
            }
        }

        echo json_encode(['status' => 'success']);
    }

    // Docente/Admin: ver entregas + archivos
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

        if (!$this->canManageCourseAsTeacher((int) $a['course_id'], $u)) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
            return;
        }

        $subModel = new Submission();
        $rows = $subModel->listByAssignment($assignment_id);

        $gradeModel = new Grade();
        foreach ($rows as &$r) {
            $sid = (int) $r['id'];
            $r['files'] = $subModel->listFiles($sid);
            $r['grade'] = $gradeModel->getBySubmission($sid);
        }

        echo json_encode(['status' => 'success', 'data' => $rows]);
    }

    // Descargar archivo de entrega (docente/admin o estudiante con acceso al curso)
    public function downloadSubmissionFile(): void
    {
        require_login();
        $u = current_user();

        $file_id = (int) ($_GET['file_id'] ?? 0);
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
        $course_id = (int) $info['course_id'];

        $ok = false;
        if ($rol === 'ADMIN')
            $ok = true;
        elseif ($rol === 'DOCENTE')
            $ok = ((int) $info['docente_user_id'] === (int) $u['id']);
        elseif ($rol === 'ESTUDIANTE')
            $ok = $this->canAccessCourseAsStudent($course_id, (int) $u['id']);

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
}