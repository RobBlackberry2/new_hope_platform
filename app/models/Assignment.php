<?php
require_once __DIR__ . '/../config/db.php';

class Assignment {
  private mysqli $db;
  public function __construct(){ $this->db = Database::connect(); }

  public function getBySection(int $section_id): ?array {
    $stmt = $this->db->prepare('SELECT * FROM assignments WHERE section_id = ? LIMIT 1');
    $stmt->bind_param('i', $section_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return $row ?: null;
  }

  public function getWithCourse(int $assignment_id): ?array {
    $stmt = $this->db->prepare(
      'SELECT a.*, s.course_id
       FROM assignments a
       JOIN course_sections s ON s.id = a.section_id
       WHERE a.id = ? LIMIT 1'
    );
    $stmt->bind_param('i', $assignment_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return $row ?: null;
  }

  public function upsertBySection(
    int $section_id,
    string $title,
    ?string $instructions,
    ?string $due_at,
    int $is_group,
    ?int $weight_percent,
    int $max_score,
    int $passing_score
  ): int|false {
    $existing = $this->getBySection($section_id);

    if ($existing) {
      $id = (int)$existing['id'];
      $stmt = $this->db->prepare(
        'UPDATE assignments
         SET title = ?, instructions = ?, due_at = ?, is_group = ?, weight_percent = ?, max_score = ?, passing_score = ?
         WHERE id = ?'
      );
      // weight_percent puede ser NULL
      $stmt->bind_param(
        'sssiiiii',
        $title,
        $instructions,
        $due_at,
        $is_group,
        $weight_percent,
        $max_score,
        $passing_score,
        $id
      );
      if (!$stmt->execute()) return false;
      return $id;
    }

    $stmt = $this->db->prepare(
      'INSERT INTO assignments (section_id, title, instructions, due_at, is_group, weight_percent, max_score, passing_score)
       VALUES (?,?,?,?,?,?,?,?)'
    );
    $stmt->bind_param(
      'isssiiii',
      $section_id,
      $title,
      $instructions,
      $due_at,
      $is_group,
      $weight_percent,
      $max_score,
      $passing_score
    );
    if (!$stmt->execute()) return false;
    return (int)$this->db->insert_id;
  }
}
