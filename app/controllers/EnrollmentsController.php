<?php
require_once __DIR__ . '/../models/Student.php';
require_once __DIR__ . '/../models/Enrollment.php';
require_once __DIR__ . '/../helpers/auth.php';

class EnrollmentsController
{
    public function createStudent(): void
    {
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
        if ($id)
            echo json_encode(['status' => 'success', 'id' => $id]);
        else {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'No se pudo crear']);
        }
    }

    public function listStudents(): void
    {
        require_login();
        require_role(['ADMIN']);
        $model = new Student();
        echo json_encode(['status' => 'success', 'data' => $model->list((int) ($_GET['limit'] ?? 200))]);
    }

    public function updateStudent(): void
    {
        require_login();
        require_role(['ADMIN']);

        $id = (int) ($_POST['id'] ?? 0);
        if (!$id) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Falta id']);
            return;
        }

        $model = new Student();
        $current = $model->get($id);
        if (!$current) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Estudiante no existe']);
            return;
        }

        // NO editables: se preservan
        $data = [
            'cedula' => $current['cedula'],
            'nombre' => $current['nombre'],
            'fecha_nacimiento' => $current['fecha_nacimiento'],

            // Editables: se toman del POST
            'grado' => $_POST['grado'] ?? $current['grado'],
            'seccion' => $_POST['seccion'] ?? $current['seccion'],
            'encargado' => $_POST['encargado'] ?? $current['encargado'],
            'telefono_encargado' => $_POST['telefono_encargado'] ?? $current['telefono_encargado'],
        ];

        $ok = $model->update($id, $data);
        if ($ok)
            echo json_encode(['status' => 'success']);
        else {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'No se pudo actualizar']);
        }
    }


    public function deleteStudent(): void
    {
        require_login();
        require_role(['ADMIN']);
        $id = (int) ($_POST['id'] ?? 0);
        if (!$id) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Falta id']);
            return;
        }
        $model = new Student();
        if ($model->delete($id))
            echo json_encode(['status' => 'success']);
        else {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'No se pudo eliminar']);
        }
    }

    public function createEnrollment(): void
    {
        require_login();
        require_role(['ADMIN']);

        $student_id = (int) ($_POST['student_id'] ?? 0);
        $year = (int) ($_POST['year'] ?? (int) date('Y'));
        if (!$student_id) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Falta student_id']);
            return;
        }

        $studentModel = new Student();
        $student = $studentModel->get($student_id);
        if (!$student) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Estudiante no existe']);
            return;
        }

        $grado = (int) ($student['grado'] ?? 0);
        if ($grado < 7 || $grado > 11) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Grado inválido (7-11)']);
            return;
        }

        $LIMIT = 90;
        $enrModel = new Enrollment();
        $used = $enrModel->countUsedForGrade($grado, $year);

        if ($used >= $LIMIT) {
            http_response_code(409);
            echo json_encode([
                'status' => 'error',
                'message' => "No hay cupos disponibles para grado {$grado} en {$year} (límite {$LIMIT})."
            ]);
            return;
        }

        // Intentar crear matrícula
        $id = $enrModel->create($student_id, $year, 'ACTIVA');
        if (!$id) {
            // Manejar duplicado uq_student_year (errno 1062)
            // OJO: aquí no tenemos acceso directo al errno del stmt en tu model, así que dejamos mensaje genérico.
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'No se pudo matricular (posible duplicado o error)']);
            return;
        }

        // Si el estudiante tiene user ligado, asegurar ACTIVO al estar ACTIVA
        if (!empty($student['user_id'])) {
            require_once __DIR__ . '/../models/User.php';
            $userModel = new User();
            $userModel->setEstado((int) $student['user_id'], 'ACTIVO');
        }

        echo json_encode(['status' => 'success', 'id' => $id]);
    }


    public function listEnrollments(): void
    {
        require_login();
        require_role(['ADMIN']);
        $model = new Enrollment();
        echo json_encode(['status' => 'success', 'data' => $model->list((int) ($_GET['limit'] ?? 200))]);
    }

    public function updateEnrollmentEstado(): void
    {
        require_login();
        require_role(['ADMIN']);

        $id = (int) ($_POST['id'] ?? 0);
        $estado = $_POST['estado'] ?? '';
        if (!$id || !$estado) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Faltan datos']);
            return;
        }

        $allowed = ['ACTIVA', 'PENDIENTE', 'BLOQUEADO'];
        if (!in_array($estado, $allowed, true)) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Estado inválido']);
            return;
        }

        $enrModel = new Enrollment();
        $enr = $enrModel->get($id);
        if (!$enr) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Matrícula no existe']);
            return;
        }

        if (!$enrModel->updateEstado($id, $estado)) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'No se pudo actualizar']);
            return;
        }

        // Regla: si PENDIENTE o BLOQUEADO => user INACTIVO
        $user_id = (int) ($enr['user_id'] ?? 0);
        if ($user_id > 0) {
            require_once __DIR__ . '/../models/User.php';
            $userModel = new User();
            $newEstadoUser = ($estado === 'ACTIVA') ? 'ACTIVO' : 'INACTIVO';
            $userModel->setEstado($user_id, $newEstadoUser);
        }

        echo json_encode(['status' => 'success']);
    }


    public function deleteEnrollment(): void
    {
        require_login();
        require_role(['ADMIN']);
        $id = (int) ($_POST['id'] ?? 0);
        if (!$id) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Falta id']);
            return;
        }
        $model = new Enrollment();
        if ($model->delete($id))
            echo json_encode(['status' => 'success']);
        else {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'No se pudo eliminar']);
        }
    }

    public function updateStudentUserId(): void
    {
        require_login();
        require_role(['ADMIN']);
        $id = (int) ($_POST['id'] ?? 0);
        $user_id_raw = $_POST['user_id'] ?? '';
        if (!$id) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Falta id']);
            return;
        }
        $user_id = null;
        if ($user_id_raw !== '') {
            $user_id = (int) $user_id_raw;
            if ($user_id <= 0)
                $user_id = null;
        }
        $model = new Student();
        $ok = $model->updateUserId($id, $user_id);
        if ($ok)
            echo json_encode(['status' => 'success']);
        else {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'No se pudo actualizar user_id']);
        }
    }

    public function capacity(): void
    {
        require_login();
        require_role(['ADMIN']);

        $year = (int) ($_GET['year'] ?? (int) date('Y'));
        $LIMIT = 90;

        $enrModel = new Enrollment();
        $usedByGrade = $enrModel->countUsedByGrade($year);

        $out = [];
        for ($g = 7; $g <= 11; $g++) {
            $used = (int) ($usedByGrade[$g] ?? 0);
            $available = max(0, $LIMIT - $used);
            $out[] = [
                'grado' => $g,
                'limit' => $LIMIT,
                'used' => $used,
                'available' => $available,
            ];
        }

        echo json_encode(['status' => 'success', 'year' => $year, 'data' => $out]);
    }

    public function getStudent(): void
    {
        require_login();
        require_role(['ADMIN']);

        $id = (int) ($_GET['id'] ?? 0);
        if (!$id) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Falta id']);
            return;
        }

        $model = new Student();
        $s = $model->get($id);
        if (!$s) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'No existe']);
            return;
        }

        echo json_encode(['status' => 'success', 'data' => $s]);
    }



}
