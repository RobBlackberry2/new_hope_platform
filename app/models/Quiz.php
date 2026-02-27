<?php
require_once __DIR__ . '/../config/db.php';

class Quiz
{
  private mysqli $db;
  public function __construct()
  {
    $this->db = Database::connect();
  }

  public function getBySection(int $section_id): ?array
  {
    $stmt = $this->db->prepare('SELECT * FROM quizzes WHERE section_id = ? LIMIT 1');
    $stmt->bind_param('i', $section_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return $row ?: null;
  }

  public function get(int $id): ?array
  {
    $stmt = $this->db->prepare('SELECT * FROM quizzes WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return $row ?: null;
  }

  public function getWithCourse(int $quiz_id): ?array
  {
    $stmt = $this->db->prepare(
      'SELECT q.*, s.course_id
       FROM quizzes q
       JOIN course_sections s ON s.id = q.section_id
       WHERE q.id = ? LIMIT 1'
    );
    $stmt->bind_param('i', $quiz_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return $row ?: null;
  }

  public function upsertBySection(
    int $section_id,
    string $title,
    ?string $instructions,
    ?int $time_limit_minutes,
    ?string $available_from,
    ?string $due_at,
    ?int $weight_percent,
    int $passing_score,
    string $show_results,
    int $is_exam
  ): int|false {
    $existing = $this->getBySection($section_id);

    if ($existing) {
      $id = (int) $existing['id'];
      $stmt = $this->db->prepare(
        'UPDATE quizzes
   SET title=?, instructions=?, time_limit_minutes=?, available_from=?, due_at=?,
       weight_percent=?, passing_score=?, show_results=?, is_exam=?
   WHERE id=?'
      );
      $stmt->bind_param(
        'ssissiissi',
        $title,
        $instructions,
        $time_limit_minutes,
        $available_from,
        $due_at,
        $weight_percent,
        $passing_score,
        $show_results,
        $is_exam,
        $id
      );
      if (!$stmt->execute())
        return false;
      return $id;
    }

    $stmt = $this->db->prepare(
      'INSERT INTO quizzes (section_id, title, instructions, time_limit_minutes, available_from, due_at, weight_percent, passing_score, show_results, is_exam)
   VALUES (?,?,?,?,?,?,?,?,?,?)'
    );
    $stmt->bind_param(
      'ississiisi',
      $section_id,
      $title,
      $instructions,
      $time_limit_minutes,
      $available_from,
      $due_at,
      $weight_percent,
      $passing_score,
      $show_results,
      $is_exam
    );
    if (!$stmt->execute())
      return false;
    return (int) $this->db->insert_id;
  }
}
