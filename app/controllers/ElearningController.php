<?php
require_once __DIR__ . '/../models/Course.php';
require_once __DIR__ . '/../models/CourseSection.php';
require_once __DIR__ . '/../models/CourseResource.php';
require_once __DIR__ . '/../models/Student.php';
require_once __DIR__ . '/../helpers/auth.php';

class ElearningController {
    public function createCourse(): void {
        require_login();
        require_role(['ADMIN']);
        $u = current_user();

        $nombre = $_POST['nombre'] ?? '';
        $descripcion = $_POST['descripcion'] ?? '';
        $grado = (int)($_POST['grado'] ?? 7);
        $seccion = $_POST['seccion'] ?? '';
        $docente_user_id = (int)($_POST['docente_user_id'] ?? 0);

        if (!$nombre) { http_response_code(400); echo json_encode(['status'=>'error','message'=>'Nombre es requerido']); return; }

        $model = new Course();
        $id = $model->create($nombre, $descripcion, $grado, $seccion, $docente_user_id);
        if ($id) echo json_encode(['status'=>'success','id'=>$id]);
        else { http_response_code(500); echo json_encode(['status'=>'error','message'=>'No se pudo crear el curso']); }
    }

    public function listCourses(): void {
        require_login();
        $u = current_user();
        $course = new Course();

        if (($u['rol'] ?? '') === 'DOCENTE') {
            $data = $course->list(null, (int)$u['id'], (int)($_GET['limit'] ?? 200));
            echo json_encode(['status'=>'success','data'=>$data]);
            return;
        }

        if (($u['rol'] ?? '') === 'ESTUDIANTE') {
            $student = new Student();
            $s = $student->getByUserId((int)$u['id']);

            if (!$s) {
                echo json_encode(['status'=>'success','data'=>[], 'message'=>'No hay ficha de estudiante asociada a este usuario']);
                return;
            }

            $seccion = $s['seccion'] ?? '';
            if (!$seccion) {
                echo json_encode(['status'=>'success','data'=>[], 'message'=>'El estudiante no tiene sección asignada']);
                return;
            }

            $data = $course->listBySeccion($seccion, (int)($_GET['limit'] ?? 200));
            echo json_encode(['status'=>'success','data'=>$data]);
            return;
        }

        // ADMIN: ve todos o puede filtrar por grado
        $grado = isset($_GET['grado']) ? (int)$_GET['grado'] : null;
        $data = $course->list($grado, null, (int)($_GET['limit'] ?? 200));
        echo json_encode(['status'=>'success','data'=>$data]);
    }

    public function createSection(): void {
        require_login();
        require_role(['ADMIN','DOCENTE']);
        $u = current_user();

        $course_id = (int)($_POST['course_id'] ?? 0);
        $titulo = $_POST['titulo'] ?? '';
        $descripcion = $_POST['descripcion'] ?? '';
        $semana = (int)($_POST['semana'] ?? 0);
        $orden = (int)($_POST['orden'] ?? 0);

        if (!$course_id || !$titulo) { http_response_code(400); echo json_encode(['status'=>'error','message'=>'Faltan datos']); return; }

        // Si docente, validar dueño del curso
        if (($u['rol'] ?? '') === 'DOCENTE') {
            $c = (new Course())->get($course_id);
            if (!$c || (int)$c['docente_user_id'] !== (int)$u['id']) {
                http_response_code(403);
                echo json_encode(['status'=>'error','message'=>'No puedes modificar cursos de otro docente']);
                return;
            }
        }

        $model = new CourseSection();
        $id = $model->create($course_id, $titulo, $descripcion, $semana, $orden);
        if ($id) echo json_encode(['status'=>'success','id'=>$id]);
        else { http_response_code(500); echo json_encode(['status'=>'error','message'=>'No se pudo crear la sección']); }
    }

    public function listSections(): void {
        require_login();
        $course_id = (int)($_GET['course_id'] ?? 0);
        if (!$course_id) { http_response_code(400); echo json_encode(['status'=>'error','message'=>'Falta course_id']); return; }
        $model = new CourseSection();
        echo json_encode(['status'=>'success','data'=>$model->list($course_id)]);
    }

    public function uploadResource(): void {
        require_login();
        require_role(['ADMIN','DOCENTE']);
        $u = current_user();

        $section_id = (int)($_POST['section_id'] ?? 0);
        $course_id = (int)($_POST['course_id'] ?? 0);
        if (!$section_id || !$course_id || !isset($_FILES['file'])) {
            http_response_code(400);
            echo json_encode(['status'=>'error','message'=>'Faltan datos (course_id, section_id, file)']);
            return;
        }

        if (($u['rol'] ?? '') === 'DOCENTE') {
            $c = (new Course())->get($course_id);
            if (!$c || (int)$c['docente_user_id'] !== (int)$u['id']) {
                http_response_code(403);
                echo json_encode(['status'=>'error','message'=>'No puedes subir a cursos de otro docente']);
                return;
            }
        }

        $file = $_FILES['file'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            http_response_code(400);
            echo json_encode(['status'=>'error','message'=>'Error de subida']);
            return;
        }

        $original = basename($file['name']);
        $mime = $file['type'] ?? 'application/octet-stream';
        $size = (int)$file['size'];

        $safeOriginal = preg_replace('/[^A-Za-z0-9._-]/', '_', $original);
        $stored = time() . '_' . bin2hex(random_bytes(6)) . '_' . $safeOriginal;

        $baseDir = realpath(__DIR__ . '/../../uploads');
        if ($baseDir === false) {
            http_response_code(500);
            echo json_encode(['status'=>'error','message'=>'No existe carpeta uploads']);
            return;
        }

        $targetDir = $baseDir . DIRECTORY_SEPARATOR . 'course_' . $course_id;
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0777, true);
        }

        $targetPath = $targetDir . DIRECTORY_SEPARATOR . $stored;
        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            http_response_code(500);
            echo json_encode(['status'=>'error','message'=>'No se pudo guardar el archivo']);
            return;
        }

        $model = new CourseResource();
        $id = $model->create($section_id, 'course_' . $course_id . '/' . $stored, $original, $mime, $size, (int)$u['id']);
        if ($id) echo json_encode(['status'=>'success','id'=>$id]);
        else { http_response_code(500); echo json_encode(['status'=>'error','message'=>'No se pudo registrar el archivo']); }
    }

    public function listResources(): void {
        require_login();
        $section_id = (int)($_GET['section_id'] ?? 0);
        if (!$section_id) { http_response_code(400); echo json_encode(['status'=>'error','message'=>'Falta section_id']); return; }
        $model = new CourseResource();
        echo json_encode(['status'=>'success','data'=>$model->listBySection($section_id)]);
    }

    public function deleteResource(): void {
        require_login();
        require_role(['ADMIN','DOCENTE']);
        $id = (int)($_POST['id'] ?? 0);
        if (!$id) { http_response_code(400); echo json_encode(['status'=>'error','message'=>'Falta id']); return; }
        $model = new CourseResource();
        $row = $model->delete($id);
        if (!$row) { http_response_code(404); echo json_encode(['status'=>'error','message'=>'No encontrado']); return; }

        // Borrar archivo físico (si existe)
        $path = __DIR__ . '/../../uploads/' . ($row['stored_name'] ?? '');
        if (is_file($path)) @unlink($path);
        echo json_encode(['status'=>'success']);
    }
}
