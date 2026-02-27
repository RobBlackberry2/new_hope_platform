<?php
require_once __DIR__ . '/../models/Course.php';
require_once __DIR__ . '/../models/CourseSection.php';
require_once __DIR__ . '/../models/Student.php';
require_once __DIR__ . '/../helpers/auth.php';
// Nota: recursos + tareas se movieron a AssignementResourseController.php
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
            if ((int) ($c['docente_user_id'] ?? 0) !== (int) ($u['id'] ?? 0)) {
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
            $c['nota_actual'] = 0.0;
            if (!empty($s['id'])) {
                $c['nota_actual'] = (new Course())->getStudentCurrentGrade((int) $c['id'], (int) $s['id']);
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
        $titulo = trim($_POST['titulo'] ?? '');
        $descripcion = trim($_POST['descripcion'] ?? '');
        $semana = (int) ($_POST['semana'] ?? 1);
        $orden = (int) ($_POST['orden'] ?? 0);
        $tipo = trim($_POST['tipo'] ?? 'RECURSOS');

        if (!$course_id || $titulo === '') {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Faltan datos (course_id, titulo)']);
            return;
        }

        // Validar dueño si DOCENTE
        if (($u['rol'] ?? '') === 'DOCENTE') {
            $c = (new Course())->get($course_id);
            if (!$c || (int) $c['docente_user_id'] !== (int) $u['id']) {
                http_response_code(403);
                echo json_encode(['status' => 'error', 'message' => 'No puedes modificar cursos de otro docente']);
                return;
            }
        }

        $sec = new CourseSection();
        $id = $sec->create($course_id, $titulo, $descripcion, $semana, $orden, $tipo);
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

        $sec = new CourseSection();
        $items = $sec->list($course_id);
        echo json_encode(['status' => 'success', 'data' => $items]);
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

        $secModel = new CourseSection();
        $sec = $secModel->get($id);
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
                echo json_encode(['status' => 'error', 'message' => 'No puedes eliminar secciones de otro docente']);
                return;
            }
        }

        $ok = $secModel->delete($id);
        echo json_encode(['status' => $ok ? 'success' : 'error']);
    }

    public function updateSectionTipo(): void
    {
        require_login();
        require_role(['ADMIN', 'DOCENTE']);
        $u = current_user();

        $id = (int) ($_POST['id'] ?? 0);
        $tipo = trim($_POST['tipo'] ?? '');
        if (!$id || $tipo === '') {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Faltan datos (id, tipo)']);
            return;
        }

        $secModel = new CourseSection();
        $sec = $secModel->get($id);
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
                echo json_encode(['status' => 'error', 'message' => 'No puedes modificar secciones de otro docente']);
                return;
            }
        }

        $ok = $secModel->updateTipo($id, $tipo);
        echo json_encode(['status' => $ok ? 'success' : 'error']);
    }
}