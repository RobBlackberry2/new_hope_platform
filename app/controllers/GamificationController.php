<?php
require_once __DIR__ . '/../models/Gamification.php';
require_once __DIR__ . '/../models/Student.php';
require_once __DIR__ . '/../helpers/auth.php';

class GamificationController
{
    private function normalizeNullableDate(?string $value): ?string
    {
        $value = trim((string) $value);
        return $value !== '' ? $value : null;
    }

    private function validateOwnerOrAdmin(array $challenge, array $u): bool
    {
        return ($u['rol'] ?? '') === 'ADMIN' || (int) ($challenge['created_by_user_id'] ?? 0) === (int) ($u['id'] ?? 0);
    }

    public function dashboard(): void
    {
        require_login();
        $u = current_user();
        $rol = $u['rol'] ?? '';
        $gm = new Gamification();

        if ($rol === 'ESTUDIANTE') {
            $student = (new Student())->getByUserId((int) $u['id']);
            if (!$student) {
                echo json_encode(['status' => 'success', 'data' => ['rewards' => [], 'challenges' => ['items' => [], 'pagination' => ['page' => 1, 'per_page' => 5, 'total' => 0, 'total_pages' => 0]]]]);
                return;
            }

            $page = (int) ($_GET['page'] ?? 1);
            $perPage = (int) ($_GET['per_page'] ?? 5);
            $rewards = $gm->listStudentRewards((int) $student['id']);
            $challenges = $gm->listPublishedChallengesForStudent((int) $student['id'], $page, $perPage);

            echo json_encode([
                'status' => 'success',
                'data' => [
                    'role' => $rol,
                    'student' => $student,
                    'rewards' => $rewards,
                    'challenges' => $challenges,
                ],
            ]);
            return;
        }

        $page = (int) ($_GET['page'] ?? 1);
        $perPage = (int) ($_GET['per_page'] ?? 5);
        $challenges = $rol === 'ADMIN'
            ? $gm->listChallengesAdmin($page, $perPage)
            : $gm->listChallengesByCreator((int) $u['id'], $page, $perPage);

        echo json_encode([
            'status' => 'success',
            'data' => [
                'role' => $rol,
                'challenges' => $challenges,
            ],
        ]);
    }

    public function createChallenge(): void
    {
        require_login();
        require_role(['ADMIN', 'DOCENTE']);
        $u = current_user();

        $titulo = trim($_POST['titulo'] ?? '');
        $tipo = strtoupper(trim($_POST['tipo'] ?? 'RETO'));
        $instrucciones = trim($_POST['instrucciones'] ?? '');
        $recompensaTipo = strtoupper(trim($_POST['recompensa_tipo'] ?? 'MEDALLA'));
        $recompensaNombre = trim($_POST['recompensa_nombre'] ?? '');
        $fechaInicio = $this->normalizeNullableDate($_POST['fecha_inicio'] ?? null);
        $fechaFin = $this->normalizeNullableDate($_POST['fecha_fin'] ?? null);

        if ($titulo === '' || $recompensaNombre === '') {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Título y nombre de recompensa son obligatorios']);
            return;
        }

        if (!in_array($tipo, ['RETO', 'MISION'], true)) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Tipo inválido']);
            return;
        }

        if (!in_array($recompensaTipo, ['MEDALLA', 'TROFEO'], true)) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Tipo de recompensa inválido']);
            return;
        }

        $gm = new Gamification();
        $id = $gm->createChallenge([
            'created_by_user_id' => (int) $u['id'],
            'tipo' => $tipo,
            'titulo' => $titulo,
            'instrucciones' => $instrucciones,
            'recompensa_tipo' => $recompensaTipo,
            'recompensa_nombre' => $recompensaNombre,
            'fecha_inicio' => $fechaInicio,
            'fecha_fin' => $fechaFin,
        ]);

        if (!$id) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'No se pudo crear el reto o misión']);
            return;
        }

        echo json_encode(['status' => 'success', 'id' => $id]);
    }

    public function updateChallenge(): void
    {
        require_login();
        require_role(['ADMIN', 'DOCENTE']);
        $u = current_user();
        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Falta id']);
            return;
        }

        $gm = new Gamification();
        $challenge = $gm->getChallenge($id);
        if (!$challenge) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Reto o misión no encontrado']);
            return;
        }

        if (!$this->validateOwnerOrAdmin($challenge, $u)) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
            return;
        }

        $titulo = trim($_POST['titulo'] ?? '');
        $tipo = strtoupper(trim($_POST['tipo'] ?? 'RETO'));
        $instrucciones = trim($_POST['instrucciones'] ?? '');
        $recompensaTipo = strtoupper(trim($_POST['recompensa_tipo'] ?? 'MEDALLA'));
        $recompensaNombre = trim($_POST['recompensa_nombre'] ?? '');
        $fechaInicio = $this->normalizeNullableDate($_POST['fecha_inicio'] ?? null);
        $fechaFin = $this->normalizeNullableDate($_POST['fecha_fin'] ?? null);

        if ($titulo === '' || $recompensaNombre === '') {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Título y nombre de recompensa son obligatorios']);
            return;
        }

        $ok = $gm->updateChallenge($id, [
            'tipo' => $tipo,
            'titulo' => $titulo,
            'instrucciones' => $instrucciones,
            'recompensa_tipo' => $recompensaTipo,
            'recompensa_nombre' => $recompensaNombre,
            'fecha_inicio' => $fechaInicio,
            'fecha_fin' => $fechaFin,
        ]);

        if (!$ok) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'No se pudo actualizar']);
            return;
        }

        echo json_encode(['status' => 'success']);
    }


    public function deleteChallenge(): void
    {
        require_login();
        require_role(['ADMIN', 'DOCENTE']);
        $u = current_user();
        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Falta id']);
            return;
        }

        $gm = new Gamification();
        $challenge = $gm->getChallenge($id);
        if (!$challenge) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Reto o misión no encontrado']);
            return;
        }

        if (!$this->validateOwnerOrAdmin($challenge, $u)) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
            return;
        }

        if (!$gm->deleteChallenge($id)) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'No se pudo eliminar']);
            return;
        }

        echo json_encode(['status' => 'success']);
    }

    public function getChallenge(): void
    {
        require_login();
        $id = (int) ($_GET['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Falta id']);
            return;
        }

        $gm = new Gamification();
        $challenge = $gm->getChallenge($id);
        if (!$challenge) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Reto o misión no encontrado']);
            return;
        }

        echo json_encode(['status' => 'success', 'data' => $challenge]);
    }

    public function enroll(): void
    {
        require_login();
        require_role(['ESTUDIANTE']);
        $u = current_user();
        $challengeId = (int) ($_POST['challenge_id'] ?? 0);
        if ($challengeId <= 0) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Falta challenge_id']);
            return;
        }

        $student = (new Student())->getByUserId((int) $u['id']);
        if (!$student) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Tu usuario no está asociado a un estudiante']);
            return;
        }

        $gm = new Gamification();
        $challenge = $gm->getChallenge($challengeId);
        if (!$challenge || ($challenge['estado'] ?? '') !== 'PUBLICADO') {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Reto o misión no disponible']);
            return;
        }

        if (!$gm->enroll($challengeId, (int) $student['id'])) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'No se pudo realizar la inscripción']);
            return;
        }

        echo json_encode(['status' => 'success']);
    }

    public function unenroll(): void
    {
        require_login();
        require_role(['ESTUDIANTE']);
        $u = current_user();
        $challengeId = (int) ($_POST['challenge_id'] ?? 0);
        if ($challengeId <= 0) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Falta challenge_id']);
            return;
        }

        $student = (new Student())->getByUserId((int) $u['id']);
        if (!$student) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Tu usuario no está asociado a un estudiante']);
            return;
        }

        $gm = new Gamification();
        if (!$gm->unenroll($challengeId, (int) $student['id'])) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'No se pudo realizar la desinscripción']);
            return;
        }

        echo json_encode(['status' => 'success']);
    }

    public function participants(): void
    {
        require_login();
        require_role(['ADMIN', 'DOCENTE']);
        $u = current_user();
        $challengeId = (int) ($_GET['challenge_id'] ?? 0);
        if ($challengeId <= 0) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Falta challenge_id']);
            return;
        }

        $gm = new Gamification();
        $challenge = $gm->getChallenge($challengeId);
        if (!$challenge) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Reto o misión no encontrado']);
            return;
        }

        if (!$this->validateOwnerOrAdmin($challenge, $u)) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
            return;
        }

        $page = (int) ($_GET['page'] ?? 1);
        $perPage = (int) ($_GET['per_page'] ?? 5);

        echo json_encode([
            'status' => 'success',
            'data' => [
                'challenge' => $challenge,
                'participants' => $gm->listParticipants($challengeId, $page, $perPage),
            ],
        ]);
    }

    public function assignReward(): void
    {
        require_login();
        require_role(['ADMIN', 'DOCENTE']);
        $u = current_user();

        $challengeId = (int) ($_POST['challenge_id'] ?? 0);
        $studentId = (int) ($_POST['student_id'] ?? 0);
        $feedback = trim($_POST['feedback'] ?? '');

        if ($challengeId <= 0 || $studentId <= 0) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Faltan datos para asignar la recompensa']);
            return;
        }

        $gm = new Gamification();
        $challenge = $gm->getChallenge($challengeId);
        if (!$challenge) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Reto o misión no encontrado']);
            return;
        }

        if (!$this->validateOwnerOrAdmin($challenge, $u)) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
            return;
        }

        $enrollment = $gm->getEnrollment($challengeId, $studentId);
        if (!$enrollment) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'El estudiante no está asociado a este reto o misión']);
            return;
        }

        if ($gm->hasRewardForChallengeAndStudent($challengeId, $studentId)) {
            http_response_code(409);
            echo json_encode(['status' => 'error', 'message' => 'Esta recompensa ya fue asignada a este estudiante']);
            return;
        }

        $id = $gm->assignReward([
            'challenge_id' => $challengeId,
            'student_id' => $studentId,
            'assigned_by_user_id' => (int) $u['id'],
            'reward_type' => (string) ($challenge['recompensa_tipo'] ?? 'MEDALLA'),
            'reward_name' => (string) ($challenge['recompensa_nombre'] ?? ''),
            'feedback' => $feedback,
        ]);

        if (!$id) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'No se pudo asignar la recompensa']);
            return;
        }

        echo json_encode(['status' => 'success', 'id' => $id]);
    }
}
