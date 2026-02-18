<?php
require_once __DIR__ . '/../config/db.php';

class Submission {
  private mysqli $db;
  public function __construct(){ $this->db = Database::connect(); }

  public function getMine(int $assignment_id, ?int $student_id, ?int $group_id): ?array {
    if ($group_id) {
      $stmt = $this->db->prepare(
        'SELECT * FROM submissions WHERE assignment_id = ? AND group_id = ? LIMIT 1'
      );
      $stmt->bind_param('ii', $assignment_id, $group_id);
    } else {
      $stmt = $this->db->prepare(
        'SELECT * FROM submissions WHERE assignment_id = ? AND student_id = ? LIMIT 1'
      );
      $stmt->bind_param('ii', $assignment_id, $student_id);
    }
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return $row ?: null;
  }

  public function getOrCreate(int $assignment_id, ?int $student_id, ?int $group_id): int|false {
    $existing = $this->getMine($assignment_id, $student_id, $group_id);
    if ($existing) return (int)$existing['id'];

    $stmt = $this->db->prepare(
      'INSERT INTO submissions (assignment_id, student_id, group_id, status) VALUES (?,?,?, "ENVIADA")'
    );
    $stmt->bind_param('iii', $assignment_id, $student_id, $group_id);
    if (!$stmt->execute()) {
      // si falló por unique, intentamos leer de nuevo
      $existing2 = $this->getMine($assignment_id, $student_id, $group_id);
      if ($existing2) return (int)$existing2['id'];
      return false;
    }
    return (int)$this->db->insert_id;
  }

  public function addFile(
    int $submission_id,
    string $original_name,
    ?string $mime,
    int $size,
    string $storage_provider,
    ?string $storage_item_id,
    ?string $public_url
  ): int|false {
    $stmt = $this->db->prepare(
      'INSERT INTO submission_files (submission_id, original_name, mime, size, storage_provider, storage_item_id, public_url)
       VALUES (?,?,?,?,?,?,?)'
    );
    $stmt->bind_param('ississs', $submission_id, $original_name, $mime, $size, $storage_provider, $storage_item_id, $public_url);
    if (!$stmt->execute()) return false;
    return (int)$this->db->insert_id;
  }

  public function getLatestFile(int $submission_id): ?array {
    $stmt = $this->db->prepare(
      'SELECT * FROM submission_files WHERE submission_id = ? ORDER BY id DESC LIMIT 1'
    );
    $stmt->bind_param('i', $submission_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return $row ?: null;
  }

  public function listByAssignment(int $assignment_id): array {
    $stmt = $this->db->prepare(
      'SELECT sub.*, st.nombre AS student_nombre, g.name AS group_name
       FROM submissions sub
       LEFT JOIN students st ON st.id = sub.student_id
       LEFT JOIN submission_groups g ON g.id = sub.group_id
       WHERE sub.assignment_id = ?
       ORDER BY sub.submitted_at DESC, sub.id DESC'
    );
    $stmt->bind_param('i', $assignment_id);
    $stmt->execute();
    $res = $stmt->get_result();
    return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
  }

  public function getCourseInfoFromSubmission(int $submission_id): ?array {
    $stmt = $this->db->prepare(
      'SELECT sub.id AS submission_id, a.id AS assignment_id, s.course_id, c.docente_user_id
       FROM submissions sub
       JOIN assignments a ON a.id = sub.assignment_id
       JOIN course_sections s ON s.id = a.section_id
       JOIN courses c ON c.id = s.course_id
       WHERE sub.id = ? LIMIT 1'
    );
    $stmt->bind_param('i', $submission_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return $row ?: null;
  }
}
