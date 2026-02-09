<?php
require_once __DIR__ . '/../models/Grade.php';
require_once __DIR__ . '/../helpers/auth.php';

class GradesController {
    public function createGrade(): void {
        require_login();
        require_role(['ADMIN', 'DOCENTE']);
        
        $student_id = (int)($_POST['student_id'] ?? 0);
        $course_id = (int)($_POST['course_id'] ?? 0);
        $periodo = $_POST['periodo'] ?? '';
        $calificacion = (float)($_POST['calificacion'] ?? 0);
        $u = current_user();
        $docente_user_id = $u['id'];

        if (!$student_id || !$course_id || !$periodo) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Faltan datos requeridos']);
            return;
        }

        // Verificar que el docente tenga acceso al curso (solo si no es admin)
        if ($u['rol'] === 'DOCENTE') {
            $stmt = (Database::connect())->prepare('SELECT id FROM courses WHERE id = ? AND docente_user_id = ?');
            $stmt->bind_param('ii', $course_id, $docente_user_id);
            $stmt->execute();
            if (!$stmt->get_result()->fetch_assoc()) {
                http_response_code(403);
                echo json_encode(['status' => 'error', 'message' => 'No tiene permisos para este curso']);
                return;
            }
        }

        $model = new Grade();
        $id = $model->create([
            'student_id' => $student_id,
            'course_id' => $course_id,
            'periodo' => $periodo,
            'calificacion' => $calificacion,
            'docente_user_id' => $docente_user_id
        ]);

        if ($id) {
            echo json_encode(['status' => 'success', 'id' => $id]);
        } else {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'No se pudo crear la calificación']);
        }
    }

    public function listGrades(): void {
        require_login();
        require_role(['ADMIN', 'DOCENTE']);
        
        $model = new Grade();
        $limit = (int)($_GET['limit'] ?? 200);
        echo json_encode(['status' => 'success', 'data' => $model->list($limit)]);
    }

    public function updateGrade(): void {
        require_login();
        require_role(['ADMIN', 'DOCENTE']);
        
        $id = (int)($_POST['id'] ?? 0);
        $calificacion = (float)($_POST['calificacion'] ?? 0);
        $periodo = $_POST['periodo'] ?? '';

        if (!$id) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Falta id']);
            return;
        }

        $model = new Grade();
        if ($model->update($id, ['calificacion' => $calificacion, 'periodo' => $periodo])) {
            echo json_encode(['status' => 'success']);
        } else {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'No se pudo actualizar']);
        }
    }

    public function deleteGrade(): void {
        require_login();
        require_role(['ADMIN', 'DOCENTE']);
        
        $id = (int)($_POST['id'] ?? 0);
        if (!$id) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Falta id']);
            return;
        }

        $model = new Grade();
        if ($model->delete($id)) {
            echo json_encode(['status' => 'success']);
        } else {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'No se pudo eliminar']);
        }
    }

    public function getStudentGrades(): void {
        require_login();
        
        $student_id = (int)($_GET['student_id'] ?? 0);
        $periodo = $_GET['periodo'] ?? null;
        $u = current_user();

        if (!$student_id) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Falta student_id']);
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

        $model = new Grade();
        $grades = $model->getByStudent($student_id, $periodo);
        $average = $model->getAverageByStudent($student_id, $periodo);
        
        echo json_encode(['status' => 'success', 'data' => ['grades' => $grades, 'average' => $average]]);
    }
}
