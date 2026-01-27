<?php
require_once __DIR__ . '/../models/Student.php';
require_once __DIR__ . '/../models/Enrollment.php';
require_once __DIR__ . '/../helpers/auth.php';

class EnrollmentsController {
    public function createStudent(): void {
        require_login();
        require_role(['ADMIN']);
        $model = new Student();
        $data = [
            'user_id' => $_POST['user_id'] ?? null,
            'cedula' => $_POST['cedula'] ?? null,
            'nombre' => $_POST['nombre'] ?? '',
            'fecha_nacimiento' => $_POST['fecha_nacimiento'] ?? null,
            'grado' => $_POST['grado'] ?? 7,
            'seccion' => $_POST['seccion'] ?? null,
            'encargado' => $_POST['encargado'] ?? null,
            'telefono_encargado' => $_POST['telefono_encargado'] ?? null,
        ];
        if (!$data['nombre']) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Nombre es requerido']);
            return;
        }
        $id = $model->create($data);
        if ($id) echo json_encode(['status' => 'success', 'id' => $id]);
        else { http_response_code(500); echo json_encode(['status' => 'error', 'message' => 'No se pudo crear']); }
    }

    public function listStudents(): void {
        require_login();
        require_role(['ADMIN']);
        $model = new Student();
        echo json_encode(['status' => 'success', 'data' => $model->list((int)($_GET['limit'] ?? 200))]);
    }

    public function updateStudent(): void {
        require_login();
        require_role(['ADMIN']);
        $id = (int)($_POST['id'] ?? 0);
        if (!$id) { http_response_code(400); echo json_encode(['status'=>'error','message'=>'Falta id']); return; }
        $model = new Student();
        $ok = $model->update($id, $_POST);
        if ($ok) echo json_encode(['status' => 'success']);
        else { http_response_code(500); echo json_encode(['status'=>'error','message'=>'No se pudo actualizar']); }
    }

    public function deleteStudent(): void {
        require_login();
        require_role(['ADMIN']);
        $id = (int)($_POST['id'] ?? 0);
        if (!$id) { http_response_code(400); echo json_encode(['status'=>'error','message'=>'Falta id']); return; }
        $model = new Student();
        if ($model->delete($id)) echo json_encode(['status' => 'success']);
        else { http_response_code(500); echo json_encode(['status'=>'error','message'=>'No se pudo eliminar']); }
    }

    public function createEnrollment(): void {
        require_login();
        require_role(['ADMIN']);
        $student_id = (int)($_POST['student_id'] ?? 0);
        $year = (int)($_POST['year'] ?? (int)date('Y'));
        if (!$student_id) { http_response_code(400); echo json_encode(['status'=>'error','message'=>'Falta student_id']); return; }
        $model = new Enrollment();
        $id = $model->create($student_id, $year, 'ACTIVA');
        if ($id) echo json_encode(['status' => 'success', 'id' => $id]);
        else { http_response_code(500); echo json_encode(['status'=>'error','message'=>'No se pudo matricular']); }
    }

    public function listEnrollments(): void {
        require_login();
        require_role(['ADMIN']);
        $model = new Enrollment();
        echo json_encode(['status' => 'success', 'data' => $model->list((int)($_GET['limit'] ?? 200))]);
    }

    public function updateEnrollmentEstado(): void {
        require_login();
        require_role(['ADMIN']);
        $id = (int)($_POST['id'] ?? 0);
        $estado = $_POST['estado'] ?? '';
        if (!$id || !$estado) { http_response_code(400); echo json_encode(['status'=>'error','message'=>'Faltan datos']); return; }
        $model = new Enrollment();
        if ($model->updateEstado($id, $estado)) echo json_encode(['status' => 'success']);
        else { http_response_code(500); echo json_encode(['status'=>'error','message'=>'No se pudo actualizar']); }
    }

    public function deleteEnrollment(): void {
        require_login();
        require_role(['ADMIN']);
        $id = (int)($_POST['id'] ?? 0);
        if (!$id) { http_response_code(400); echo json_encode(['status'=>'error','message'=>'Falta id']); return; }
        $model = new Enrollment();
        if ($model->delete($id)) echo json_encode(['status' => 'success']);
        else { http_response_code(500); echo json_encode(['status'=>'error','message'=>'No se pudo eliminar']); }
    }
}
