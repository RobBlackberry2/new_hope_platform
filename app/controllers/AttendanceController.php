<?php
require_once __DIR__ . '/../models/Attendance.php';
require_once __DIR__ . '/../models/Student.php';
require_once __DIR__ . '/../helpers/auth.php';

class AttendanceController {
    public function createAttendance(): void {
        require_login();
        require_role(['ADMIN', 'DOCENTE']);
        
        $student_id = (int)($_POST['student_id'] ?? 0);
        $fecha = $_POST['fecha'] ?? '';
        $estado = $_POST['estado'] ?? 'PRESENTE';
        $course_id = isset($_POST['course_id']) ? (int)$_POST['course_id'] : null;

        if (!$student_id || !$fecha) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Faltan datos requeridos']);
            return;
        }

        $model = new Attendance();
        $id = $model->create([
            'student_id' => $student_id,
            'fecha' => $fecha,
            'estado' => $estado,
            'course_id' => $course_id
        ]);

        if ($id) {
            echo json_encode(['status' => 'success', 'id' => $id]);
        } else {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'No se pudo registrar la asistencia']);
        }
    }

    public function listAttendance(): void {
        require_login();
        require_role(['ADMIN', 'DOCENTE']);
        
        $model = new Attendance();
        $limit = (int)($_GET['limit'] ?? 200);
        echo json_encode(['status' => 'success', 'data' => $model->list($limit)]);
    }

    public function updateAttendance(): void {
        require_login();
        require_role(['ADMIN', 'DOCENTE']);
        
        $id = (int)($_POST['id'] ?? 0);
        $estado = $_POST['estado'] ?? 'PRESENTE';

        if (!$id) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Falta id']);
            return;
        }

        $model = new Attendance();
        if ($model->update($id, ['estado' => $estado])) {
            echo json_encode(['status' => 'success']);
        } else {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'No se pudo actualizar']);
        }
    }

    public function getStudentAttendance(): void {
        require_login();
        
        $student_id = (int)($_GET['student_id'] ?? 0);
        $fecha_inicio = $_GET['fecha_inicio'] ?? '';
        $fecha_fin = $_GET['fecha_fin'] ?? '';
        $u = current_user();

        if (!$student_id || !$fecha_inicio || !$fecha_fin) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Faltan parámetros requeridos']);
            return;
        }

        // Control de acceso
        if ($u['rol'] === 'ESTUDIANTE') {
            // Verificar que sea su propio perfil
            $studentModel = new Student();
            $student = $studentModel->getByUserId($u['id']);
            if (!$student || $student['id'] != $student_id) {
                http_response_code(403);
                echo json_encode(['status' => 'error', 'message' => 'No tiene permisos']);
                return;
            }
        } elseif ($u['rol'] === 'PADRE') {
            // Verificar relación padre-estudiante
            require_once __DIR__ . '/../models/ParentStudentRelation.php';
            $relationModel = new ParentStudentRelation();
            if (!$relationModel->hasAccess($u['id'], $student_id)) {
                http_response_code(403);
                echo json_encode(['status' => 'error', 'message' => 'No tiene permisos para ver este estudiante']);
                return;
            }
        }

        $model = new Attendance();
        $attendance = $model->getByStudent($student_id, $fecha_inicio, $fecha_fin);
        $summary = $model->getSummary($student_id, $fecha_inicio, $fecha_fin);
        
        echo json_encode(['status' => 'success', 'data' => ['attendance' => $attendance, 'summary' => $summary]]);
    }
}
