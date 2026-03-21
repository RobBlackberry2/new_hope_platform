<?php
require_once __DIR__ . '/../models/Course.php';
require_once __DIR__ . '/../models/CourseSection.php';
require_once __DIR__ . '/../models/Student.php';
require_once __DIR__ . '/../models/DiscussionForum.php';
require_once __DIR__ . '/../models/ForumComment.php';
require_once __DIR__ . '/../models/ForumCommentReport.php';
require_once __DIR__ . '/../models/Message.php';
require_once __DIR__ . '/../helpers/auth.php';

class ForumController {
    private function canAccessCourseAsStudent(int $course_id, int $user_id): bool {
        $c = (new Course())->get($course_id);
        if (!$c) return false;
        $s = (new Student())->getByUserId($user_id);
        if (!$s) return false;
        return (($s['seccion'] ?? '') !== '' && ($s['seccion'] ?? '') === ($c['seccion'] ?? ''));
    }

    private function canManageCourseAsTeacher(int $course_id, array $user): bool {
        if (($user['rol'] ?? '') === 'ADMIN') return true;
        if (($user['rol'] ?? '') !== 'DOCENTE') return false;
        $c = (new Course())->get($course_id);
        return $c && (int)$c['docente_user_id'] === (int)$user['id'];
    }

    private function canViewCourse(int $course_id, array $u): bool {
        $rol = $u['rol'] ?? '';
        if ($rol === 'ADMIN') return true;
        if ($rol === 'DOCENTE') return $this->canManageCourseAsTeacher($course_id, $u);
        if ($rol === 'ESTUDIANTE') return $this->canAccessCourseAsStudent($course_id, (int)$u['id']);
        return false;
    }

    public function upsertForum(): void {
        require_login();
        require_role(['ADMIN', 'DOCENTE']);
        $u = current_user();

        $section_id = (int)($_POST['section_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if (!$section_id || $title === '') {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Faltan datos (section_id, title)']);
            return;
        }

        $section = (new CourseSection())->get($section_id);
        if (!$section) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Sección no encontrada']);
            return;
        }

        if (($section['tipo'] ?? '') !== 'FORO') {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'La sección indicada no es de tipo FORO']);
            return;
        }

        $course_id = (int)$section['course_id'];
        if (!$this->canManageCourseAsTeacher($course_id, $u)) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
            return;
        }

        $id = (new DiscussionForum())->upsertBySection($section_id, $title, $description ?: null, (int)$u['id']);
        if (!$id) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'No se pudo guardar el foro']);
            return;
        }

        echo json_encode(['status' => 'success', 'id' => $id]);
    }

    public function getForumBySection(): void {
        require_login();
        $u = current_user();
        $section_id = (int)($_GET['section_id'] ?? 0);

        if (!$section_id) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Falta section_id']);
            return;
        }

        $section = (new CourseSection())->get($section_id);
        if (!$section) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Sección no encontrada']);
            return;
        }

        $course_id = (int)$section['course_id'];
        if (!$this->canViewCourse($course_id, $u)) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
            return;
        }

        $forum = (new DiscussionForum())->getBySection($section_id);
        echo json_encode(['status' => 'success', 'data' => $forum]);
    }

    public function deleteForum(): void {
        require_login();
        require_role(['ADMIN', 'DOCENTE']);
        $u = current_user();
        $id = (int)($_POST['id'] ?? 0);

        if (!$id) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Falta id']);
            return;
        }

        $forum = (new DiscussionForum())->get($id);
        if (!$forum) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Foro no encontrado']);
            return;
        }

        if (!$this->canManageCourseAsTeacher((int)$forum['course_id'], $u)) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
            return;
        }

        $ok = (new DiscussionForum())->delete($id);
        echo json_encode(['status' => $ok ? 'success' : 'error']);
    }

    public function listComments(): void {
        require_login();
        $u = current_user();
        $forum_id = (int)($_GET['forum_id'] ?? 0);

        if (!$forum_id) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Falta forum_id']);
            return;
        }

        $forum = (new DiscussionForum())->get($forum_id);
        if (!$forum) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Foro no encontrado']);
            return;
        }

        if (!$this->canViewCourse((int)$forum['course_id'], $u)) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
            return;
        }

        $items = (new ForumComment())->listByForum($forum_id);
        echo json_encode(['status' => 'success', 'data' => $items]);
    }

    public function createComment(): void {
        require_login();
        require_role(['ESTUDIANTE']);
        $u = current_user();
        $forum_id = (int)($_POST['forum_id'] ?? 0);
        $comment_body = trim($_POST['comment_body'] ?? '');

        if (!$forum_id || $comment_body === '') {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Faltan datos (forum_id, comment_body)']);
            return;
        }

        $forum = (new DiscussionForum())->get($forum_id);
        if (!$forum) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Foro no encontrado']);
            return;
        }

        if (!$this->canAccessCourseAsStudent((int)$forum['course_id'], (int)$u['id'])) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
            return;
        }

        $id = (new ForumComment())->create($forum_id, (int)$u['id'], $comment_body);
        if (!$id) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'No se pudo crear el comentario']);
            return;
        }

        echo json_encode(['status' => 'success', 'id' => $id]);
    }

    public function deleteComment(): void {
        require_login();
        require_role(['ADMIN', 'DOCENTE']);
        $u = current_user();
        $id = (int)($_POST['id'] ?? 0);

        if (!$id) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Falta id']);
            return;
        }

        $comment = (new ForumComment())->get($id);
        if (!$comment) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Comentario no encontrado']);
            return;
        }

        if (!$this->canManageCourseAsTeacher((int)$comment['course_id'], $u)) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
            return;
        }

        $ok = (new ForumComment())->delete($id);
        echo json_encode(['status' => $ok ? 'success' : 'error']);
    }

    public function reportComment(): void {
        require_login();
        require_role(['ESTUDIANTE']);
        $u = current_user();
        $comment_id = (int)($_POST['comment_id'] ?? 0);
        $reason = trim($_POST['reason'] ?? '');

        if (!$comment_id) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Falta comment_id']);
            return;
        }

        $comment = (new ForumComment())->get($comment_id);
        if (!$comment) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Comentario no encontrado']);
            return;
        }

        if (!$this->canAccessCourseAsStudent((int)$comment['course_id'], (int)$u['id'])) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
            return;
        }

        $reportModel = new ForumCommentReport();
        if ($reportModel->alreadyReportedByUser($comment_id, (int)$u['id'])) {
            http_response_code(409);
            echo json_encode(['status' => 'error', 'message' => 'Ya reportaste este comentario']);
            return;
        }

        $reportId = $reportModel->create($comment_id, (int)$u['id'], $reason ?: null);
        if (!$reportId) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'No se pudo reportar el comentario']);
            return;
        }

        $studentName = (string)($u['nombre'] ?? ('Usuario #' . $u['id']));
        $asunto = 'Reporte de comentario en foro de discusión';
        $cuerpo = "El estudiante {$studentName} reportó un comentario en el foro \"{$comment['forum_title']}\" del curso \"{$comment['course_nombre']}\".\n\n" .
                  "Comentario reportado:\n{$comment['comment_body']}\n\n" .
                  'Motivo: ' . ($reason !== '' ? $reason : 'Sin motivo especificado.');

        (new Message())->send((int)$u['id'], (int)$comment['docente_user_id'], null, $asunto, $cuerpo);

        echo json_encode(['status' => 'success', 'id' => $reportId]);
    }
}
