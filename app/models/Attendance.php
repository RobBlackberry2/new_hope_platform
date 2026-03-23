<?php
require_once __DIR__ . '/../config/db.php';

class Attendance
{
    private mysqli $db;

    public function __construct()
    {
        $this->db = Database::connect();
    }

    public function saveForSectionDate(array $section, string $attendanceDate, array $items, int $takenByUserId): bool
    {
        $sectionId = (int) ($section['id'] ?? 0);
        $sectionCode = (string) ($section['codigo'] ?? '');

        $insert = $this->db->prepare(
            'INSERT INTO attendance_records (student_id, section_id, section_code, attendance_date, status, taken_by_user_id, is_justified)
             VALUES (?,?,?,?,?,?,0)'
        );

        foreach ($items as $item) {
            $studentId = (int) ($item['student_id'] ?? 0);
            $status = strtoupper(trim((string) ($item['status'] ?? 'PRESENTE')));

            if ($studentId <= 0) {
                continue;
            }
            if (!in_array($status, ['AUSENTE', 'TARDIA'], true)) {
                continue;
            }

            $insert->bind_param('iisssi', $studentId, $sectionId, $sectionCode, $attendanceDate, $status, $takenByUserId);
            if (!$insert->execute()) {
                return false;
            }
        }

        return true;
    }

    public function listHistoryBySection(int $sectionId, ?string $attendanceDate = null): array
    {
        if ($attendanceDate !== null && $attendanceDate !== '') {
            $stmt = $this->db->prepare(
                "SELECT ar.*, CONCAT(TRIM(COALESCE(st.nombre, '')), CASE WHEN COALESCE(st.apellidos, '') <> '' THEN ' ' ELSE '' END, TRIM(COALESCE(st.apellidos, ''))) AS student_nombre, st.cedula, s.codigo AS section_codigo,
                        tj.nombre AS justified_by_nombre, tk.nombre AS taken_by_nombre
                 FROM attendance_records ar
                 JOIN students st ON st.id = ar.student_id
                 JOIN sections s ON s.id = ar.section_id
                 LEFT JOIN users tj ON tj.id = ar.justified_by_user_id
                 LEFT JOIN users tk ON tk.id = ar.taken_by_user_id
                 WHERE ar.section_id = ?
                   AND ar.attendance_date = ?
                 ORDER BY ar.attendance_date DESC, COALESCE(NULLIF(TRIM(st.apellidos), ''), 'ZZZZZZ') ASC, st.nombre ASC, ar.id DESC"
            );
            $stmt->bind_param('is', $sectionId, $attendanceDate);
        } else {
            $stmt = $this->db->prepare(
                "SELECT ar.*, CONCAT(TRIM(COALESCE(st.nombre, '')), CASE WHEN COALESCE(st.apellidos, '') <> '' THEN ' ' ELSE '' END, TRIM(COALESCE(st.apellidos, ''))) AS student_nombre, st.cedula, s.codigo AS section_codigo,
                        tj.nombre AS justified_by_nombre, tk.nombre AS taken_by_nombre
                 FROM attendance_records ar
                 JOIN students st ON st.id = ar.student_id
                 JOIN sections s ON s.id = ar.section_id
                 LEFT JOIN users tj ON tj.id = ar.justified_by_user_id
                 LEFT JOIN users tk ON tk.id = ar.taken_by_user_id
                 WHERE ar.section_id = ?
                 ORDER BY ar.attendance_date DESC, COALESCE(NULLIF(TRIM(st.apellidos), ''), 'ZZZZZZ') ASC, st.nombre ASC, ar.id DESC"
            );
            $stmt->bind_param('i', $sectionId);
        }

        $stmt->execute();
        $res = $stmt->get_result();
        return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    }

    public function justify(int $attendanceId, int $justifiedByUserId): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE attendance_records
             SET is_justified = 1,
                 justified_at = NOW(),
                 justified_by_user_id = ?
             WHERE id = ?'
        );
        $stmt->bind_param('ii', $justifiedByUserId, $attendanceId);
        return (bool) $stmt->execute();
    }
}
