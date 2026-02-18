<?php
require_once __DIR__ . '/../config/db.php';

class QuizAttempt {
  private mysqli $db;
  public function __construct(){ $this->db = Database::connect(); }

  public function getMine(int $quiz_id, int $student_id): ?array {
    $stmt = $this->db->prepare('SELECT * FROM quiz_attempts WHERE quiz_id=? AND student_id=? LIMIT 1');
    $stmt->bind_param('ii', $quiz_id, $student_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return $row ?: null;
  }

  public function start(int $quiz_id, int $student_id, int $max_points): int|false {
    $existing = $this->getMine($quiz_id, $student_id);
    if ($existing) return (int)$existing['id'];

    $stmt = $this->db->prepare(
      'INSERT INTO quiz_attempts (quiz_id, student_id, max_points) VALUES (?,?,?)'
    );
    $stmt->bind_param('iii', $quiz_id, $student_id, $max_points);
    if (!$stmt->execute()) return false;
    return (int)$this->db->insert_id;
  }

  public function upsertAnswer(int $attempt_id, int $question_id, ?int $selected_option_id, ?string $answer_text): bool {
    $stmt = $this->db->prepare('SELECT id FROM quiz_answers WHERE attempt_id=? AND question_id=? LIMIT 1');
    $stmt->bind_param('ii', $attempt_id, $question_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    if ($row) {
      $id = (int)$row['id'];
      $stmt2 = $this->db->prepare(
        'UPDATE quiz_answers SET selected_option_id=?, answer_text=? WHERE id=?'
      );
      $stmt2->bind_param('isi', $selected_option_id, $answer_text, $id);
      return (bool)$stmt2->execute();
    }

    $stmt3 = $this->db->prepare(
      'INSERT INTO quiz_answers (attempt_id, question_id, selected_option_id, answer_text) VALUES (?,?,?,?)'
    );
    $stmt3->bind_param('iiis', $attempt_id, $question_id, $selected_option_id, $answer_text);
    return (bool)$stmt3->execute();
  }

  public function listAnswers(int $attempt_id): array {
    $stmt = $this->db->prepare('SELECT * FROM quiz_answers WHERE attempt_id=?');
    $stmt->bind_param('i', $attempt_id);
    $stmt->execute();
    $res = $stmt->get_result();
    return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
  }

  public function finish(int $attempt_id, string $status, int $raw_points, int $max_points, int $score): bool {
    $stmt = $this->db->prepare(
      'UPDATE quiz_attempts
       SET finished_at=NOW(), status=?, raw_points=?, max_points=?, score=?
       WHERE id=?'
    );
    $stmt->bind_param('siiii', $status, $raw_points, $max_points, $score, $attempt_id);
    return (bool)$stmt->execute();
  }

  public function listByQuiz(int $quiz_id): array {
    $stmt = $this->db->prepare(
      'SELECT a.*, s.nombre AS student_nombre
       FROM quiz_attempts a
       JOIN students s ON s.id = a.student_id
       WHERE a.quiz_id=?
       ORDER BY a.started_at DESC'
    );
    $stmt->bind_param('i', $quiz_id);
    $stmt->execute();
    $res = $stmt->get_result();
    return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
  }

  public function countAnswers(int $attempt_id): int {
  $stmt = $this->db->prepare('SELECT COUNT(*) c FROM quiz_answers WHERE attempt_id=?');
  $stmt->bind_param('i', $attempt_id);
  $stmt->execute();
  $row = $stmt->get_result()->fetch_assoc();
  return (int)($row['c'] ?? 0);
}

public function clearAnswers(int $attempt_id): bool {
  $stmt = $this->db->prepare('DELETE FROM quiz_answers WHERE attempt_id=?');
  $stmt->bind_param('i', $attempt_id);
  return (bool)$stmt->execute();
}

public function restart(int $attempt_id, int $max_points): bool {
  $stmt = $this->db->prepare(
    "UPDATE quiz_attempts
     SET started_at=NOW(), finished_at=NULL, status='IN_PROGRESS',
         score=0, raw_points=0, max_points=?
     WHERE id=?"
  );
  $stmt->bind_param('ii', $max_points, $attempt_id);
  return (bool)$stmt->execute();
}

public function setAnswerAutoGrade(int $attempt_id, int $question_id, int $is_correct, int $points_awarded): void
{
    $stmt = $this->db->prepare(
        'UPDATE quiz_answers SET is_correct=?, points_awarded=? WHERE attempt_id=? AND question_id=?'
    );
    if (!$stmt) {
        throw new Exception('Prepare failed (quiz_answers auto-grade): ' . ($this->db->error ?? 'unknown'));
    }

    $stmt->bind_param('iiii', $is_correct, $points_awarded, $attempt_id, $question_id);
    $stmt->execute();
    $stmt->close();
}



}
