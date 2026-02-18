<?php
require_once __DIR__ . '/../config/db.php';

class QuizOption {
  private mysqli $db;
  public function __construct(){ $this->db = Database::connect(); }

  public function listByQuestion(int $question_id): array {
    $stmt = $this->db->prepare('SELECT * FROM quiz_options WHERE question_id=? ORDER BY orden ASC, id ASC');
    $stmt->bind_param('i', $question_id);
    $stmt->execute();
    $res = $stmt->get_result();
    return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
  }

  public function upsert(int $id, int $question_id, string $option_text, int $is_correct, int $orden): int|false {
    if ($id > 0) {
      $stmt = $this->db->prepare(
        'UPDATE quiz_options SET option_text=?, is_correct=?, orden=? WHERE id=? AND question_id=?'
      );
      $stmt->bind_param('siiii', $option_text, $is_correct, $orden, $id, $question_id);
      if (!$stmt->execute()) return false;
      return $id;
    }

    $stmt = $this->db->prepare(
      'INSERT INTO quiz_options (question_id, option_text, is_correct, orden) VALUES (?,?,?,?)'
    );
    $stmt->bind_param('isii', $question_id, $option_text, $is_correct, $orden);
    if (!$stmt->execute()) return false;
    return (int)$this->db->insert_id;
  }

  public function delete(int $id): bool {
    $stmt = $this->db->prepare('DELETE FROM quiz_options WHERE id=?');
    $stmt->bind_param('i', $id);
    return (bool)$stmt->execute();
  }

  public function getCorrectOptionId(int $question_id): ?int {
    $stmt = $this->db->prepare('SELECT id FROM quiz_options WHERE question_id=? AND is_correct=1 LIMIT 1');
    $stmt->bind_param('i', $question_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return $row ? (int)$row['id'] : null;
  }

  public function clearCorrectExcept(int $question_id, int $keep_id): void {
    $stmt = $this->db->prepare('UPDATE quiz_options SET is_correct=0 WHERE question_id=? AND id<>?');
    $stmt->bind_param('ii', $question_id, $keep_id);
    $stmt->execute();
  }
}
