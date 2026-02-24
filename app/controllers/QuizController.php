<?php
// app/controllers/QuizController.php

require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../models/Course.php';
require_once __DIR__ . '/../models/CourseSection.php';
require_once __DIR__ . '/../models/Student.php';

require_once __DIR__ . '/../models/Quiz.php';
require_once __DIR__ . '/../models/QuizQuestion.php';
require_once __DIR__ . '/../models/QuizOption.php';
require_once __DIR__ . '/../models/QuizAttempt.php';

/**
 * Maneja toda la lógica de Quices/Exámenes: configuración, preguntas/opciones e intentos.
 */
class QuizController
{
    /**
     * Lee payload tanto de JSON como de form-data.
     */
    private function payload(): array
    {
        $ct = $_SERVER['CONTENT_TYPE'] ?? '';
        if (stripos($ct, 'application/json') !== false) {
            $raw = file_get_contents('php://input');
            $j = json_decode($raw ?: '[]', true);
            return is_array($j) ? $j : [];
        }
        // Para multipart/form-data o application/x-www-form-urlencoded
        return $_POST ?? [];
    }

    /**
     * Si el usuario es DOCENTE, valida que sea dueño del curso.
     */
    private function assertDocenteOwnsCourse(int $course_id): void
    {
        $u = current_user();
        $rol = $u['rol'] ?? '';
        if ($rol !== 'DOCENTE') {
            return; // ADMIN u otros roles controlados antes
        }

        $c = (new Course())->get($course_id);
        if (!$c || (int) ($c['docente_user_id'] ?? 0) !== (int) ($u['id'] ?? 0)) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'No puedes modificar quices de otro docente']);
            exit;
        }
    }

    /**
     * Crea o actualiza la configuración de un Quiz/Examen.
     */
    public function upsertQuiz(): void
    {
        require_login();
        require_role(['ADMIN', 'DOCENTE']);

        $p = $this->payload();

        $section_id = (int) ($p['section_id'] ?? 0);
        $title = trim($p['title'] ?? '');
        $instructions = $p['instructions'] ?? null;
        $time_limit_minutes_raw = trim((string) ($p['time_limit_minutes'] ?? ''));
        $time_limit_minutes = (int) $time_limit_minutes_raw;

        if ($time_limit_minutes_raw === '' || $time_limit_minutes <= 0) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'El tiempo (min) es obligatorio']);
            return;
        }

        $available_from = ($p['available_from'] ?? '') ?: null;
        $due_at = ($p['due_at'] ?? '') ?: null;
        $passing_score = (int) ($p['passing_score'] ?? 70);
        $show_results = $p['show_results'] ?? 'AFTER_SUBMIT';
        $is_exam = 0;

        if (!$section_id || $title === '') {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Faltan datos (section_id, title)']);
            return;
        }

        $allowedShow = ['NO', 'AFTER_SUBMIT', 'AFTER_DUE'];
        if (!in_array($show_results, $allowedShow, true)) {
            $show_results = 'AFTER_SUBMIT';
        }

        $sec = (new CourseSection())->get($section_id);
        if (!$sec) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Sección no encontrada']);
            return;
        }

        $this->assertDocenteOwnsCourse((int) $sec['course_id']);

        $id = (new Quiz())->upsertBySection(
            $section_id,
            $title,
            $instructions,
            $time_limit_minutes,
            $available_from,
            $due_at,
            $passing_score,
            $show_results,
            $is_exam
        );

        if ($id) {
            echo json_encode(['status' => 'success', 'id' => $id]);
        } else {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'No se pudo guardar el quiz']);
        }
    }

    public function getQuizBySection(): void
    {
        require_login();
        $section_id = (int) ($_GET['section_id'] ?? 0);
        if (!$section_id) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Falta section_id']);
            return;
        }
        $q = (new Quiz())->getBySection($section_id);
        echo json_encode(['status' => 'success', 'data' => $q]);
    }

    public function listQuizQuestions(): void
    {
        require_login();
        $quiz_id = (int) ($_GET['quiz_id'] ?? 0);
        if (!$quiz_id) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Falta quiz_id']);
            return;
        }

        $qq = new QuizQuestion();
        $qo = new QuizOption();
        $questions = $qq->listByQuiz($quiz_id);
        foreach ($questions as &$q) {
            $q['options'] = $qo->listByQuestion((int) $q['id']);
        }
        echo json_encode(['status' => 'success', 'data' => $questions]);
    }

    public function upsertQuizQuestion(): void
    {
        require_login();
        require_role(['ADMIN', 'DOCENTE']);
        $p = $this->payload();

        $quiz_id = (int) ($p['quiz_id'] ?? 0);
        $id = (int) ($p['id'] ?? 0);
        $type = $p['type'] ?? 'MCQ';
        $text = trim($p['question_text'] ?? '');
        $points = (int) ($p['points'] ?? 10);
        $orden = (int) ($p['orden'] ?? 0);

        if (!$quiz_id || $text === '') {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Faltan datos (quiz_id, question_text)']);
            return;
        }
        $allowed = ['MCQ', 'TF', 'SHORT'];
        if (!in_array($type, $allowed, true)) {
            $type = 'MCQ';
        }

        $quiz = (new Quiz())->getWithCourse($quiz_id);
        if (!$quiz) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Quiz no encontrado']);
            return;
        }
        $this->assertDocenteOwnsCourse((int) $quiz['course_id']);
        $this->assertQuizQuestionsEditable($quiz_id);

        $qid = (new QuizQuestion())->upsert($id, $quiz_id, $type, $text, $points, $orden);
        if ($qid) {
            echo json_encode(['status' => 'success', 'id' => $qid]);
        } else {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'No se pudo guardar pregunta']);
        }
    }

    public function deleteQuizQuestion(): void
    {
        require_login();
        require_role(['ADMIN', 'DOCENTE']);
        $p = $this->payload();

        $quiz_id = (int) ($p['quiz_id'] ?? 0);
        $id = (int) ($p['id'] ?? 0);
        if (!$quiz_id || !$id) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Faltan datos']);
            return;
        }

        $quiz = (new Quiz())->getWithCourse($quiz_id);
        if (!$quiz) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Quiz no encontrado']);
            return;
        }
        $this->assertDocenteOwnsCourse((int) $quiz['course_id']);
        $this->assertQuizQuestionsEditable($quiz_id);

        if ((new QuizQuestion())->delete($id, $quiz_id)) {
            echo json_encode(['status' => 'success']);
        } else {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'No se pudo eliminar']);
        }
    }

    public function upsertQuizOption(): void
    {
        require_login();
        require_role(['ADMIN', 'DOCENTE']);
        $p = $this->payload();

        $question_id = (int) ($p['question_id'] ?? 0);
        $id = (int) ($p['id'] ?? 0);
        $text = trim($p['option_text'] ?? '');
        $is_correct = (int) ($p['is_correct'] ?? 0);
        $orden = (int) ($p['orden'] ?? 0);

        if (!$question_id || $text === '') {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Faltan datos (question_id, option_text)']);
            return;
        }

        $qq = new QuizQuestion();
        $qrow = $qq->get($question_id);
        if (!$qrow) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Pregunta no encontrada']);
            return;
        }

        $quiz = (new Quiz())->getWithCourse((int) $qrow['quiz_id']);
        if (!$quiz) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Quiz no encontrado']);
            return;
        }
        $this->assertDocenteOwnsCourse((int) $quiz['course_id']);

        $qo = new QuizOption();
        $oid = $qo->upsert($id, $question_id, $text, $is_correct, $orden);
        if (!$oid) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'No se pudo guardar opción']);
            return;
        }

        if ($is_correct === 1) {
            $qo->clearCorrectExcept($question_id, $oid);
        }

        echo json_encode(['status' => 'success', 'id' => $oid]);
    }

    public function deleteQuizOption(): void
    {
        require_login();
        require_role(['ADMIN', 'DOCENTE']);
        $p = $this->payload();

        $id = (int) ($p['id'] ?? 0);
        if (!$id) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Falta id']);
            return;
        }

        require_once __DIR__ . '/../config/db.php';
        $db = Database::connect();

        // Buscar quiz_id y course_id para validar dueño + bloqueo
        $stmt = $db->prepare(
            "SELECT qq.quiz_id, c.id AS course_id
         FROM quiz_options qo
         JOIN quiz_questions qq ON qq.id = qo.question_id
         JOIN quizzes q ON q.id = qq.quiz_id
         JOIN course_sections cs ON cs.id = q.section_id
         JOIN courses c ON c.id = cs.course_id
         WHERE qo.id = ?
         LIMIT 1"
        );
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Opción no encontrada']);
            return;
        }

        $quiz_id = (int) $row['quiz_id'];
        $course_id = (int) $row['course_id'];

        $this->assertDocenteOwnsCourse($course_id);
        $this->assertQuizQuestionsEditable($quiz_id);

        if ((new QuizOption())->delete($id)) {
            echo json_encode(['status' => 'success']);
        } else {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'No se pudo eliminar']);
        }
    }

    public function startQuizAttempt(): void
    {
        require_login();
        require_role(['ESTUDIANTE']);
        $u = current_user();
        $p = $this->payload();

        $quiz_id = (int) ($p['quiz_id'] ?? 0);
        if (!$quiz_id) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Falta quiz_id']);
            return;
        }

        $quiz = (new Quiz())->get($quiz_id);
        if (!$quiz) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Quiz no encontrado']);
            return;
        }

        $student = (new Student())->getByUserId((int) $u['id']);
        if (!$student) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'No hay ficha de estudiante']);
            return;
        }

        $tz = new DateTimeZone('America/Costa_Rica');
        $now = new DateTime('now', $tz);

        if (!empty($quiz['available_from'])) {
            $af = new DateTime($quiz['available_from'], $tz);
            if ($now < $af) {
                http_response_code(409);
                echo json_encode(['status' => 'error', 'message' => 'Aún no disponible']);
                return;
            }
        }
        if (!empty($quiz['due_at'])) {
            $due = new DateTime($quiz['due_at'], $tz);
            if ($now > $due) {
                http_response_code(409);
                echo json_encode(['status' => 'error', 'message' => 'Fecha límite vencida']);
                return;
            }
        }

        $max = (new QuizQuestion())->sumPoints($quiz_id);
        $attemptModel = new QuizAttempt();

        $existing = $attemptModel->getMine($quiz_id, (int) $student['id']);

        if ($existing) {
            $attemptId = (int) $existing['id'];

            if (($existing['status'] ?? '') !== 'IN_PROGRESS') {
                echo json_encode(['status' => 'success', 'attempt_id' => $attemptId]);
                return;
            }

            $ansCount = $attemptModel->countAnswers($attemptId);

            $limit = $quiz['time_limit_minutes'];
            $expired = false;
            if ($limit !== null && $limit !== '' && (int) $limit > 0) {
                $st = new DateTime($existing['started_at'], $tz);
                $deadline = (clone $st)->modify('+' . (int) $limit . ' minutes');
                if ($now > $deadline) {
                    $expired = true;
                }
            }

            if ($ansCount === 0 || $expired) {
                $attemptModel->clearAnswers($attemptId);
                $attemptModel->restart($attemptId, $max);
            }

            echo json_encode(['status' => 'success', 'attempt_id' => $attemptId]);
            return;
        }

        $attemptId = $attemptModel->start($quiz_id, (int) $student['id'], $max);
        if ($attemptId) {
            echo json_encode(['status' => 'success', 'attempt_id' => $attemptId]);
        } else {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'No se pudo iniciar intento']);
        }
    }

    public function getMyQuizAttempt(): void
    {
        require_login();
        require_role(['ESTUDIANTE']);
        $u = current_user();

        $quiz_id = (int) ($_GET['quiz_id'] ?? 0);
        if (!$quiz_id) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Falta quiz_id']);
            return;
        }

        $quiz = (new Quiz())->get($quiz_id);
        if (!$quiz) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Quiz no encontrado']);
            return;
        }

        $student = (new Student())->getByUserId((int) $u['id']);
        if (!$student) {
            echo json_encode(['status' => 'success', 'data' => null]);
            return;
        }

        $att = (new QuizAttempt())->getMine($quiz_id, (int) $student['id']);
        if (!$att) {
            echo json_encode(['status' => 'success', 'data' => null]);
            return;
        }

        $answers = (new QuizAttempt())->listAnswers((int) $att['id']);
        echo json_encode(['status' => 'success', 'data' => ['attempt' => $att, 'answers' => $answers]]);
    }

    public function submitQuizAttempt(): void
    {
        require_login();
        require_role(['ESTUDIANTE']);
        $u = current_user();
        $p = $this->payload();

        $quiz_id = (int) ($p['quiz_id'] ?? 0);
        $answersJson = $p['answers'] ?? '[]';

        $tz = new DateTimeZone('America/Costa_Rica');
        $now = new DateTime('now', $tz);

        if (!$quiz_id) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Falta quiz_id']);
            return;
        }

        $quiz = (new Quiz())->get($quiz_id);
        if (!$quiz) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Quiz no encontrado']);
            return;
        }

        if (!empty($quiz['available_from'])) {
            $af = new DateTime($quiz['available_from'], $tz);
            if ($now < $af) {
                http_response_code(409);
                echo json_encode(['status' => 'error', 'message' => 'Aún no disponible']);
                return;
            }
        }

        if (!empty($quiz['due_at'])) {
            $due = new DateTime($quiz['due_at'], $tz);
            if ($now > $due) {
                http_response_code(409);
                echo json_encode(['status' => 'error', 'message' => 'Fecha límite vencida']);
                return;
            }
        }

        $student = (new Student())->getByUserId((int) $u['id']);
        if (!$student) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'No hay ficha de estudiante']);
            return;
        }

        $attemptModel = new QuizAttempt();
        $att = $attemptModel->getMine($quiz_id, (int) $student['id']);
        if (!$att) {
            http_response_code(409);
            echo json_encode(['status' => 'error', 'message' => 'Debes iniciar el intento primero']);
            return;
        }
        if (($att['status'] ?? '') !== 'IN_PROGRESS') {
            http_response_code(409);
            echo json_encode(['status' => 'error', 'message' => 'Este intento ya fue enviado']);
            return;
        }

        $limit = $quiz['time_limit_minutes'];
        if ($limit !== null && $limit !== '' && (int) $limit > 0) {
            $st = new DateTime($att['started_at'], $tz);
            $st->modify('+' . (int) $limit . ' minutes');
            if ($now > $st) {
                http_response_code(409);
                echo json_encode(['status' => 'error', 'message' => 'Tiempo vencido']);
                return;
            }
        }

        $answersArr = json_decode($answersJson, true);
        if (!is_array($answersArr)) {
            $answersArr = [];
        }

        $qq = new QuizQuestion();
        $qo = new QuizOption();
        $questions = $qq->listByQuiz($quiz_id);
        $qById = [];
        foreach ($questions as $q) {
            $qById[(int) $q['id']] = $q;
        }

        foreach ($answersArr as $a) {
            $qid = (int) ($a['question_id'] ?? 0);
            if (!$qid || !isset($qById[$qid])) {
                continue;
            }
            $selected = isset($a['selected_option_id']) ? (int) $a['selected_option_id'] : null;
            $text = isset($a['answer_text']) ? (string) $a['answer_text'] : null;
            $attemptModel->upsertAnswer((int) $att['id'], $qid, $selected, $text);
        }

        // auto-calificar MCQ/TF
        $raw = 0;
        $max = (int) ($att['max_points'] ?? $qq->sumPoints($quiz_id));
        $hasShort = false;

        $saved = $attemptModel->listAnswers((int) $att['id']);
        $ansMap = [];
        foreach ($saved as $sa) {
            $ansMap[(int) $sa['question_id']] = $sa;
        }

        foreach ($questions as $q) {
            $qid = (int) $q['id'];
            $type = $q['type'];
            $points = (int) $q['points'];

            if ($type === 'SHORT') {
                $hasShort = true;
                continue;
            }

            $correctId = $qo->getCorrectOptionId($qid);
            $chosen = isset($ansMap[$qid]) ? (int) ($ansMap[$qid]['selected_option_id'] ?? 0) : 0;

            $isCorrect = ($correctId && $chosen && $chosen === $correctId) ? 1 : 0;
            $awarded = $isCorrect ? $points : 0;
            $attemptModel->setAnswerAutoGrade((int) $att['id'], $qid, $isCorrect, $awarded);
            $raw += $awarded;
        }

        $score = ($max > 0) ? (int) round(($raw / $max) * 100) : 0;
        $status = $hasShort ? 'SUBMITTED' : 'GRADED';

        $ok = $attemptModel->finish((int) $att['id'], $status, $raw, $max, $score);
        if ($ok) {
            echo json_encode(['status' => 'success', 'data' => ['status' => $status, 'score' => $score]]);
        } else {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'No se pudo enviar']);
        }
    }

    public function listQuizAttempts(): void
    {
        require_login();
        require_role(['ADMIN', 'DOCENTE']);

        $quiz_id = (int) ($_GET['quiz_id'] ?? 0);
        if (!$quiz_id) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Falta quiz_id']);
            return;
        }

        $quiz = (new Quiz())->getWithCourse($quiz_id);
        if (!$quiz) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Quiz no encontrado']);
            return;
        }
        $this->assertDocenteOwnsCourse((int) $quiz['course_id']);

        $attempt_id = (int) ($_GET['attempt_id'] ?? 0);
        if ($attempt_id > 0) {
            require_once __DIR__ . '/../config/db.php';
            $db = Database::connect();

            $stmt = $db->prepare(
                'SELECT a.*, s.nombre AS student_nombre
             FROM quiz_attempts a
             JOIN students s ON s.id = a.student_id
             WHERE a.id=? AND a.quiz_id=?
             LIMIT 1'
            );
            $stmt->bind_param('ii', $attempt_id, $quiz_id);
            $stmt->execute();
            $attempt = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$attempt) {
                http_response_code(404);
                echo json_encode(['status' => 'error', 'message' => 'Intento no encontrado']);
                return;
            }

            $qq = new QuizQuestion();
            $qo = new QuizOption();
            $questions = $qq->listByQuiz($quiz_id);
            foreach ($questions as &$q) {
                $qid = (int) $q['id'];
                $q['options'] = $qo->listByQuestion($qid);
                $q['correct_option_id'] = $qo->getCorrectOptionId($qid);
            }

            $answers = (new QuizAttempt())->listAnswers($attempt_id);

            echo json_encode([
                'status' => 'success',
                'data' => [
                    'attempt' => $attempt,
                    'questions' => $questions,
                    'answers' => $answers
                ]
            ]);
            return;
        }

        $rows = (new QuizAttempt())->listByQuiz($quiz_id);
        echo json_encode(['status' => 'success', 'data' => $rows]);
    }

    public function gradeShort(): void
    {
        require_login();
        require_role(['ADMIN', 'DOCENTE']);

        $p = $this->payload();

        $quiz_id = (int) ($p['quiz_id'] ?? 0);
        $attempt_id = (int) ($p['attempt_id'] ?? 0);
        $gradesJson = (string) ($p['grades'] ?? '[]');

        if (!$quiz_id || !$attempt_id) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Faltan datos (quiz_id, attempt_id)']);
            return;
        }

        $quiz = (new Quiz())->getWithCourse($quiz_id);
        if (!$quiz) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Quiz no encontrado']);
            return;
        }
        $this->assertDocenteOwnsCourse((int) $quiz['course_id']);

        require_once __DIR__ . '/../config/db.php';
        $db = Database::connect();

        // Traer intento
        $stmt = $db->prepare('SELECT * FROM quiz_attempts WHERE id=? AND quiz_id=? LIMIT 1');
        $stmt->bind_param('ii', $attempt_id, $quiz_id);
        $stmt->execute();
        $attempt = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$attempt) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Intento no encontrado']);
            return;
        }

        $grades = json_decode($gradesJson, true);
        if (!is_array($grades))
            $grades = [];

        // Mapa de puntos máximos por pregunta SHORT
        $qq = new QuizQuestion();
        $questions = $qq->listByQuiz($quiz_id);
        $shortMax = [];
        foreach ($questions as $q) {
            if (($q['type'] ?? '') === 'SHORT') {
                $shortMax[(int) $q['id']] = (int) ($q['points'] ?? 0);
            }
        }

        // Guardar calificación de cada SHORT
        foreach ($grades as $g) {
            $qid = (int) ($g['question_id'] ?? 0);
            if (!$qid || !isset($shortMax[$qid]))
                continue;

            $maxPts = $shortMax[$qid];
            $pts = (int) ($g['points_awarded'] ?? 0);
            if ($pts < 0)
                $pts = 0;
            if ($pts > $maxPts)
                $pts = $maxPts;

            // Asegurar fila en quiz_answers
            $stmt = $db->prepare('SELECT id FROM quiz_answers WHERE attempt_id=? AND question_id=? LIMIT 1');
            $stmt->bind_param('ii', $attempt_id, $qid);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$row) {
                $stmt = $db->prepare('INSERT INTO quiz_answers (attempt_id, question_id, points_awarded) VALUES (?,?,?)');
                $stmt->bind_param('iii', $attempt_id, $qid, $pts);
                $stmt->execute();
                $stmt->close();
            } else {
                $stmt = $db->prepare('UPDATE quiz_answers SET points_awarded=? WHERE attempt_id=? AND question_id=?');
                $stmt->bind_param('iii', $pts, $attempt_id, $qid);
                $stmt->execute();
                $stmt->close();
            }
        }

        // Recalcular total
        $stmt = $db->prepare('SELECT COALESCE(SUM(COALESCE(points_awarded,0)),0) AS total FROM quiz_answers WHERE attempt_id=?');
        $stmt->bind_param('i', $attempt_id);
        $stmt->execute();
        $totalRow = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $raw_points = (int) ($totalRow['total'] ?? 0);
        $max_points = (int) ($attempt['max_points'] ?? $qq->sumPoints($quiz_id));
        $score = ($max_points > 0) ? (int) round(($raw_points / $max_points) * 100) : 0;

        $stmt = $db->prepare(
            "UPDATE quiz_attempts
         SET status='GRADED',
             raw_points=?,
             score=?,
             finished_at=COALESCE(finished_at, NOW())
         WHERE id=? AND quiz_id=?"
        );
        $stmt->bind_param('iiii', $raw_points, $score, $attempt_id, $quiz_id);
        $ok = (bool) $stmt->execute();
        $stmt->close();

        if (!$ok) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'No se pudo guardar la calificación']);
            return;
        }

        echo json_encode(['status' => 'success', 'data' => ['raw_points' => $raw_points, 'max_points' => $max_points, 'score' => $score]]);
    }

    private function assertQuizQuestionsEditable(int $quiz_id): void
    {
        $u = current_user();
        $rol = $u['rol'] ?? '';

        // ✅ aplica a DOCENTE (y si querés también a ADMIN, quita esta condición)
        if ($rol !== 'DOCENTE')
            return;

        require_once __DIR__ . '/../config/db.php';
        $db = Database::connect();

        $stmt = $db->prepare(
            "SELECT COUNT(*) AS c
         FROM quiz_attempts
         WHERE quiz_id=?
           AND status <> 'IN_PROGRESS'"
        );
        $stmt->bind_param('i', $quiz_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $count = (int) ($row['c'] ?? 0);
        if ($count > 0) {
            http_response_code(403);
            echo json_encode([
                'status' => 'error',
                'message' => 'No se pueden modificar preguntas/opciones: ya existen entregas de estudiantes.'
            ]);
            exit;
        }
    }

    public function studentAttemptReview(): void
    {
        require_login();
        require_role(['ESTUDIANTE']);

        $u = current_user();
        $quiz_id = (int) ($_GET['quiz_id'] ?? 0);
        if (!$quiz_id) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Falta quiz_id']);
            return;
        }

        $quiz = (new Quiz())->getWithCourse($quiz_id);
        if (!$quiz) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Quiz no encontrado']);
            return;
        }

        // ✅ el student_id real viene de la tabla students (no del user_id)
        $student = (new Student())->getByUserId((int) ($u['id'] ?? 0));
        if (!$student) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'No hay ficha de estudiante']);
            return;
        }

        $attemptModel = new QuizAttempt();
        $attempt = $attemptModel->getMine($quiz_id, (int) $student['id']); // ✅ correcto
        if (!$attempt) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'No hay intentos para este quiz']);
            return;
        }

        if (($attempt['status'] ?? '') === 'IN_PROGRESS') {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'El intento aún está en progreso']);
            return;
        }

        // Respeta show_results (NO / AFTER_SUBMIT / AFTER_DUE)
        $mode = $quiz['show_results'] ?? 'AFTER_SUBMIT';
        $allow = false;

        if ($mode === 'AFTER_SUBMIT')
            $allow = true;
        if ($mode === 'NO')
            $allow = false;

        if ($mode === 'AFTER_DUE') {
            $due = $quiz['due_at'] ?? null;
            if ($due) {
                $dueTs = strtotime($due); // "YYYY-MM-DD HH:mm:ss" OK
                $allow = ($dueTs !== false) ? (time() >= $dueTs) : false;
            } else {
                $allow = false;
            }
        }

        if (!$allow) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'Los resultados no están disponibles según la configuración del quiz']);
            return;
        }

        // Traer preguntas + opciones + correcta + respuestas
        $qq = new QuizQuestion();
        $qo = new QuizOption();
        $questions = $qq->listByQuiz($quiz_id);

        foreach ($questions as &$q) {
            $qid = (int) $q['id'];
            $q['options'] = $qo->listByQuestion($qid);
            $q['correct_option_id'] = $qo->getCorrectOptionId($qid); // ✅ necesario para correctas/incorrectas
        }

        $answers = $attemptModel->listAnswers((int) $attempt['id']);

        echo json_encode([
            'status' => 'success',
            'data' => [
                'attempt' => $attempt,
                'questions' => $questions,
                'answers' => $answers
            ]
        ]);
    }

}
