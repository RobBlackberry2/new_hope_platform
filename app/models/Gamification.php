<?php
require_once __DIR__ . '/../config/db.php';

class Gamification
{
    private mysqli $db;

    public function __construct()
    {
        $this->db = Database::connect();
    }

    public function createChallenge(array $data): int|false
    {
        $stmt = $this->db->prepare(
            "INSERT INTO gamification_challenges (
                created_by_user_id, tipo, titulo, instrucciones,
                recompensa_tipo, recompensa_nombre, fecha_inicio, fecha_fin, estado
            ) VALUES (?,?,?,?,?,?,?,?,'PUBLICADO')"
        );
        $stmt->bind_param(
            'isssssss',
            $data['created_by_user_id'],
            $data['tipo'],
            $data['titulo'],
            $data['instrucciones'],
            $data['recompensa_tipo'],
            $data['recompensa_nombre'],
            $data['fecha_inicio'],
            $data['fecha_fin']
        );
        if (!$stmt->execute()) {
            return false;
        }
        return (int) $this->db->insert_id;
    }

    public function updateChallenge(int $id, array $data): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE gamification_challenges
             SET tipo = ?, titulo = ?, instrucciones = ?,
                 recompensa_tipo = ?, recompensa_nombre = ?, fecha_inicio = ?, fecha_fin = ?,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = ?'
        );
        $stmt->bind_param(
            'sssssssi',
            $data['tipo'],
            $data['titulo'],
            $data['instrucciones'],
            $data['recompensa_tipo'],
            $data['recompensa_nombre'],
            $data['fecha_inicio'],
            $data['fecha_fin'],
            $id
        );
        return (bool) $stmt->execute();
    }

    public function deleteChallenge(int $id): bool
    {
        $this->db->begin_transaction();
        try {
            $stmtRewards = $this->db->prepare('UPDATE gamification_rewards SET challenge_id = NULL WHERE challenge_id = ?');
            $stmtRewards->bind_param('i', $id);
            if (!$stmtRewards->execute()) {
                throw new RuntimeException('No se pudieron preservar las recompensas');
            }

            $stmtEnrollments = $this->db->prepare('DELETE FROM gamification_enrollments WHERE challenge_id = ?');
            $stmtEnrollments->bind_param('i', $id);
            if (!$stmtEnrollments->execute()) {
                throw new RuntimeException('No se pudieron eliminar las inscripciones');
            }

            $stmtChallenge = $this->db->prepare('DELETE FROM gamification_challenges WHERE id = ?');
            $stmtChallenge->bind_param('i', $id);
            if (!$stmtChallenge->execute()) {
                throw new RuntimeException('No se pudo eliminar el reto o misión');
            }

            $this->db->commit();
            return true;
        } catch (Throwable $e) {
            $this->db->rollback();
            return false;
        }
    }

    public function getChallenge(int $id): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT gc.*, u.nombre AS creador_nombre
             FROM gamification_challenges gc
             JOIN users u ON u.id = gc.created_by_user_id
             WHERE gc.id = ?
             LIMIT 1"
        );
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        return $row ?: null;
    }

    public function listPublishedChallengesForStudent(int $student_id, int $page = 1, int $perPage = 5): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(50, $perPage));
        $offset = ($page - 1) * $perPage;

        $stmtCount = $this->db->prepare(
            "SELECT COUNT(*) AS total
             FROM gamification_challenges
             WHERE estado = 'PUBLICADO'"
        );
        $stmtCount->execute();
        $total = (int) (($stmtCount->get_result()->fetch_assoc()['total'] ?? 0));

        $sql = "SELECT gc.*, u.nombre AS creador_nombre,
                       ge.status AS mi_estado_inscripcion,
                       ge.enrolled_at AS mi_fecha_inscripcion,
                       EXISTS(
                         SELECT 1 FROM gamification_rewards gr
                         WHERE gr.challenge_id = gc.id AND gr.student_id = ?
                       ) AS ya_recompensado
                FROM gamification_challenges gc
                JOIN users u ON u.id = gc.created_by_user_id
                LEFT JOIN gamification_enrollments ge
                  ON ge.challenge_id = gc.id AND ge.student_id = ?
                WHERE gc.estado = 'PUBLICADO'
                ORDER BY gc.created_at DESC
                LIMIT ? OFFSET ?";

        $stmt = $this->db->prepare($sql);
        $stmt->bind_param('iiii', $student_id, $student_id, $perPage, $offset);
        $stmt->execute();
        $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC) ?: [];

        return [
            'items' => $items,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => (int) ceil($total / max(1, $perPage)),
            ],
        ];
    }

    public function listChallengesByCreator(int $creator_user_id, int $page = 1, int $perPage = 5): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(50, $perPage));
        $offset = ($page - 1) * $perPage;

        $stmtCount = $this->db->prepare(
            'SELECT COUNT(*) AS total FROM gamification_challenges WHERE created_by_user_id = ?'
        );
        $stmtCount->bind_param('i', $creator_user_id);
        $stmtCount->execute();
        $total = (int) (($stmtCount->get_result()->fetch_assoc()['total'] ?? 0));

        $stmt = $this->db->prepare(
            "SELECT gc.*,
                    (SELECT COUNT(*) FROM gamification_enrollments ge WHERE ge.challenge_id = gc.id AND ge.status = 'INSCRITO') AS inscritos,
                    (SELECT COUNT(*) FROM gamification_rewards gr WHERE gr.challenge_id = gc.id) AS recompensas_asignadas
             FROM gamification_challenges gc
             WHERE gc.created_by_user_id = ?
             ORDER BY gc.created_at DESC
             LIMIT ? OFFSET ?"
        );
        $stmt->bind_param('iii', $creator_user_id, $perPage, $offset);
        $stmt->execute();
        $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC) ?: [];

        return [
            'items' => $items,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => (int) ceil($total / max(1, $perPage)),
            ],
        ];
    }

    public function listChallengesAdmin(int $page = 1, int $perPage = 5): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(50, $perPage));
        $offset = ($page - 1) * $perPage;

        $totalRes = $this->db->query('SELECT COUNT(*) AS total FROM gamification_challenges');
        $total = (int) (($totalRes?->fetch_assoc()['total'] ?? 0));

        $stmt = $this->db->prepare(
            "SELECT gc.*, u.nombre AS creador_nombre,
                       (SELECT COUNT(*) FROM gamification_enrollments ge WHERE ge.challenge_id = gc.id AND ge.status = 'INSCRITO') AS inscritos,
                       (SELECT COUNT(*) FROM gamification_rewards gr WHERE gr.challenge_id = gc.id) AS recompensas_asignadas
                FROM gamification_challenges gc
                JOIN users u ON u.id = gc.created_by_user_id
                ORDER BY gc.created_at DESC
                LIMIT ? OFFSET ?"
        );
        $stmt->bind_param('ii', $perPage, $offset);
        $stmt->execute();
        $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC) ?: [];

        return [
            'items' => $items,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => (int) ceil($total / max(1, $perPage)),
            ],
        ];
    }

    public function listStudentRewards(int $student_id): array
    {
        $stmt = $this->db->prepare(
            "SELECT gr.*, gc.titulo AS reto_titulo, u.nombre AS asignado_por_nombre
             FROM gamification_rewards gr
             LEFT JOIN gamification_challenges gc ON gc.id = gr.challenge_id
             JOIN users u ON u.id = gr.assigned_by_user_id
             WHERE gr.student_id = ?
             ORDER BY gr.assigned_at DESC"
        );
        $stmt->bind_param('i', $student_id);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC) ?: [];
    }

    public function listParticipants(int $challenge_id, int $page = 1, int $perPage = 5): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(50, $perPage));
        $offset = ($page - 1) * $perPage;

        $stmtCount = $this->db->prepare(
            "SELECT COUNT(*) AS total
             FROM gamification_enrollments ge
             WHERE ge.challenge_id = ?
               AND ge.status = 'INSCRITO'
               AND NOT EXISTS (
                 SELECT 1 FROM gamification_rewards gr
                 WHERE gr.challenge_id = ge.challenge_id AND gr.student_id = ge.student_id
               )"
        );
        $stmtCount->bind_param('i', $challenge_id);
        $stmtCount->execute();
        $total = (int) (($stmtCount->get_result()->fetch_assoc()['total'] ?? 0));

        $stmt = $this->db->prepare(
            "SELECT ge.id, ge.challenge_id, ge.student_id, ge.enrolled_at,
                    TRIM(CONCAT(s.nombre, ' ', COALESCE(s.apellidos, ''))) AS estudiante_nombre, s.seccion, s.grado, u.correo
             FROM gamification_enrollments ge
             JOIN students s ON s.id = ge.student_id
             LEFT JOIN users u ON u.id = s.user_id
             WHERE ge.challenge_id = ?
               AND ge.status = 'INSCRITO'
               AND NOT EXISTS (
                 SELECT 1 FROM gamification_rewards gr
                 WHERE gr.challenge_id = ge.challenge_id AND gr.student_id = ge.student_id
               )
             ORDER BY ge.enrolled_at DESC
             LIMIT ? OFFSET ?"
        );
        $stmt->bind_param('iii', $challenge_id, $perPage, $offset);
        $stmt->execute();
        $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC) ?: [];

        return [
            'items' => $items,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => (int) ceil($total / max(1, $perPage)),
            ],
        ];
    }

    public function enroll(int $challenge_id, int $student_id): bool
    {
        $existing = $this->getEnrollment($challenge_id, $student_id);
        if ($existing) {
            $stmt = $this->db->prepare(
                "UPDATE gamification_enrollments
                 SET status = 'INSCRITO', enrolled_at = CURRENT_TIMESTAMP, unenrolled_at = NULL
                 WHERE challenge_id = ? AND student_id = ?"
            );
            $stmt->bind_param('ii', $challenge_id, $student_id);
            return (bool) $stmt->execute();
        }

        $stmt = $this->db->prepare(
            "INSERT INTO gamification_enrollments (challenge_id, student_id, status)
             VALUES (?, ?, 'INSCRITO')"
        );
        $stmt->bind_param('ii', $challenge_id, $student_id);
        return (bool) $stmt->execute();
    }

    public function unenroll(int $challenge_id, int $student_id): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE gamification_enrollments
             SET status = 'DESINSCRITO', unenrolled_at = CURRENT_TIMESTAMP
             WHERE challenge_id = ? AND student_id = ? AND status = 'INSCRITO'"
        );
        $stmt->bind_param('ii', $challenge_id, $student_id);
        return (bool) $stmt->execute();
    }

    public function getEnrollment(int $challenge_id, int $student_id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM gamification_enrollments WHERE challenge_id = ? AND student_id = ? LIMIT 1'
        );
        $stmt->bind_param('ii', $challenge_id, $student_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        return $row ?: null;
    }

    public function assignReward(array $data): int|false
    {
        $stmt = $this->db->prepare(
            'INSERT INTO gamification_rewards (
                challenge_id, student_id, assigned_by_user_id, reward_type, reward_name, feedback
             ) VALUES (?,?,?,?,?,?)'
        );
        $stmt->bind_param(
            'iiisss',
            $data['challenge_id'],
            $data['student_id'],
            $data['assigned_by_user_id'],
            $data['reward_type'],
            $data['reward_name'],
            $data['feedback']
        );
        if (!$stmt->execute()) {
            return false;
        }
        return (int) $this->db->insert_id;
    }

    public function hasRewardForChallengeAndStudent(int $challenge_id, int $student_id): bool
    {
        $stmt = $this->db->prepare(
            'SELECT id FROM gamification_rewards WHERE challenge_id = ? AND student_id = ? LIMIT 1'
        );
        $stmt->bind_param('ii', $challenge_id, $student_id);
        $stmt->execute();
        return (bool) $stmt->get_result()->fetch_assoc();
    }
}
