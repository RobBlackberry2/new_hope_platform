<?php
require_once __DIR__ . '/../config/db.php';

class Grade {
  private mysqli $db;
  public function __construct(){ $this->db = Database::connect(); }

  public function getBySubmission(int $submission_id): ?array {
    $stmt = $this->db->prepare('SELECT * FROM grades WHERE submission_id = ? LIMIT 1');
    $stmt->bind_param('i', $submission_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return $row ?: null;
  }

  public function upsert(int $submission_id, int $score, ?string $feedback, int $graded_by): bool {
    $existing = $this->getBySubmission($submission_id);
    if ($existing) {
      $stmt = $this->db->prepare(
        'UPDATE grades SET score = ?, feedback = ?, graded_by = ?, graded_at = NOW()
         WHERE submission_id = ?'
      );
      $stmt->bind_param('isii', $score, $feedback, $graded_by, $submission_id);
      return (bool)$stmt->execute();
    }

    $stmt = $this->db->prepare(
      'INSERT INTO grades (submission_id, score, feedback, graded_by)
       VALUES (?,?,?,?)'
    );
    $stmt->bind_param('iisi', $submission_id, $score, $feedback, $graded_by);
    return (bool)$stmt->execute();
  }
}
