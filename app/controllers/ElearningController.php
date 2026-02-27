<?php
require_once __DIR__ . '/../models/Course.php';
require_once __DIR__ . '/../models/CourseSection.php';
require_once __DIR__ . '/../models/CourseResource.php';
require_once __DIR__ . '/../models/Student.php';
require_once __DIR__ . '/../helpers/auth.php';

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

            $student_id = (int) $s['id'];
            foreach ($data as &$c) {
                $cid = (int) ($c['id'] ?? 0);
                $c['nota_actual'] = $cid > 0 ? $course->getStudentCurrentGrade($cid, $student_id) : 0;
            }
            unset($c);

            echo json_encode(['status' => 'success', 'data' => $data]);
            return;
        }

        $grado = isset($_GET['grado']) ? (int) $_GET['grado'] : null;
        $data = $course->list($grado, null, (int) ($_GET['limit'] ?? 200));
        echo json_encode(['status' => 'success', 'data' => $data]);
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
}
