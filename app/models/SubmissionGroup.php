<?php
require_once __DIR__ . '/../config/db.php';

class SubmissionGroup
{
  private mysqli $db;
  public function __construct()
  {
    $this->db = Database::connect();
  }

  public function create(int $course_id, string $name): int|false
  {
    $stmt = $this->db->prepare('INSERT INTO submission_groups (course_id, name) VALUES (?,?)');
    $stmt->bind_param('is', $course_id, $name);
    if (!$stmt->execute())
      return false;
    return (int) $this->db->insert_id;
  }

  public function get(int $id): ?array
  {
    $stmt = $this->db->prepare('SELECT * FROM submission_groups WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return $row ?: null;
  }

  public function list(int $course_id): array
  {
    $stmt = $this->db->prepare('SELECT * FROM submission_groups WHERE course_id = ? ORDER BY id DESC');
    $stmt->bind_param('i', $course_id);
    $stmt->execute();
    $res = $stmt->get_result();
    return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
  }

  public function listMembers(int $group_id): array
  {
    $stmt = $this->db->prepare(
      'SELECT m.student_id, s.nombre, s.seccion, s.grado
       FROM submission_group_members m
       JOIN students s ON s.id = m.student_id
       WHERE m.group_id = ?
       ORDER BY s.nombre ASC'
    );
    $stmt->bind_param('i', $group_id);
    $stmt->execute();
    $res = $stmt->get_result();
    return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
  }

  public function setMembers(int $group_id, array $student_ids): bool
  {
    $this->db->begin_transaction();
    try {
      $del = $this->db->prepare('DELETE FROM submission_group_members WHERE group_id = ?');
      $del->bind_param('i', $group_id);
      if (!$del->execute()) {
        $this->db->rollback();
        return false;
      }

      $ins = $this->db->prepare('INSERT INTO submission_group_members (group_id, student_id) VALUES (?,?)');
      foreach ($student_ids as $sid) {
        $sid = (int) $sid;
        if ($sid <= 0)
          continue;
        $ins->bind_param('ii', $group_id, $sid);
        if (!$ins->execute()) {
          $this->db->rollback();
          return false;
        }
      }

      $this->db->commit();
      return true;
    } catch (Throwable $e) {
      $this->db->rollback();
      return false;
    }
  }

  public function getGroupForStudent(int $course_id, int $student_id): ?int
  {
    $stmt = $this->db->prepare(
      'SELECT gm.group_id
       FROM submission_group_members gm
       JOIN submission_groups g ON g.id = gm.group_id
       WHERE g.course_id = ? AND gm.student_id = ?
       LIMIT 1'
    );
    $stmt->bind_param('ii', $course_id, $student_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return $row ? (int) $row['group_id'] : null;
  }

  public function update(int $group_id, string $name): bool
  {
    $stmt = $this->db->prepare('UPDATE submission_groups SET name = ? WHERE id = ?');
    $stmt->bind_param('si', $name, $group_id);
    return (bool) $stmt->execute();
  }

  public function delete(int $group_id): bool
  {
    $stmt = $this->db->prepare('DELETE FROM submission_groups WHERE id = ?');
    $stmt->bind_param('i', $group_id);
    return (bool) $stmt->execute();
  }

  public function getWithMembers(int $group_id): ?array
  {
    $g = $this->get($group_id);
    if (!$g)
      return null;
    $g['members'] = $this->listMembers($group_id);
    return $g;
  }

  public function getGroupForStudentDetailed(int $course_id, int $student_id): ?array
  {
    $stmt = $this->db->prepare(
      'SELECT g.*
     FROM submission_group_members gm
     JOIN submission_groups g ON g.id = gm.group_id
     WHERE g.course_id = ? AND gm.student_id = ?
     LIMIT 1'
    );
    $stmt->bind_param('ii', $course_id, $student_id);
    $stmt->execute();
    $g = $stmt->get_result()->fetch_assoc();
    if (!$g)
      return null;
    $g['members'] = $this->listMembers((int) $g['id']);
    return $g;
  }
}
