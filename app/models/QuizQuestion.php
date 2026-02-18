<?php
require_once __DIR__ . '/../config/db.php';

class QuizQuestion {
  private mysqli $db;
  public function __construct(){ $this->db = Database::connect(); }

  public function get(int $id): ?array {
    $stmt = $this->db->prepare('SELECT * FROM quiz_questions WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return $row ?: null;
  }

  public function listByQuiz(int $quiz_id): array {
    $stmt = $this->db->prepare('SELECT * FROM quiz_questions WHERE quiz_id=? ORDER BY orden ASC, id ASC');
    $stmt->bind_param('i', $quiz_id);
    $stmt->execute();
    $res = $stmt->get_result();
    return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
  }

  public function upsert(int $id, int $quiz_id, string $type, string $question_text, int $points, int $orden): int|false {
    if ($id > 0) {
      $stmt = $this->db->prepare(
        'UPDATE quiz_questions SET type=?, question_text=?, points=?, orden=? WHERE id=? AND quiz_id=?'
      );
      $stmt->bind_param('ssiiii', $type, $question_text, $points, $orden, $id, $quiz_id);
      if (!$stmt->execute()) return false;
      return $id;
    }

    $stmt = $this->db->prepare(
      'INSERT INTO quiz_questions (quiz_id, type, question_text, points, orden) VALUES (?,?,?,?,?)'
    );
    $stmt->bind_param('issii', $quiz_id, $type, $question_text, $points, $orden);
    if (!$stmt->execute()) return false;
    return (int)$this->db->insert_id;
  }

  public function delete(int $id, int $quiz_id): bool {
    $stmt = $this->db->prepare('DELETE FROM quiz_questions WHERE id=? AND quiz_id=?');
    $stmt->bind_param('ii', $id, $quiz_id);
    return (bool)$stmt->execute();
  }

  public function sumPoints(int $quiz_id): int {
    $stmt = $this->db->prepare('SELECT COALESCE(SUM(points),0) AS total FROM quiz_questions WHERE quiz_id=?');
    $stmt->bind_param('i', $quiz_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return (int)($row['total'] ?? 0);
  }
}
