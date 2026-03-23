<?php
require_once __DIR__ . '/../config/db.php';

class Enrollment
{
    private mysqli $db;

    public function __construct()
    {
        $this->db = Database::connect();
    }

    public function create(int $student_id, int $year, string $estado = 'ACTIVA'): int|false
    {
        $stmt = $this->db->prepare('INSERT INTO enrollments (student_id, year, start_year, estado, archived_at) VALUES (?,?,?,?,NULL)');
        $stmt->bind_param('iiis', $student_id, $year, $year, $estado);
        if (!$stmt->execute())
            return false;
        return (int) $this->db->insert_id;
    }

    public function list(int $limit = 200, bool $include_archived = false): array
    {
        $limit = max(1, min(1000, $limit));
        $where = $include_archived
            ? ''
            : ' WHERE e.archived_at IS NULL AND s.archived_at IS NULL';
        $sql = 'SELECT e.*, TRIM(CONCAT(s.nombre, " ", COALESCE(s.apellidos, ""))) AS student_nombre, s.grado, s.seccion, s.archived_at AS student_archived_at
'
            . 'FROM enrollments e
'
            . 'JOIN students s ON s.id = e.student_id'
            . $where
            . ' ORDER BY e.archived_at IS NULL DESC, e.id DESC LIMIT ' . $limit;
        $res = $this->db->query($sql);
        return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    }

    public function updateEstado(int $id, string $estado): bool
    {
        $stmt = $this->db->prepare('UPDATE enrollments SET estado = ? WHERE id = ?');
        $stmt->bind_param('si', $estado, $id);
        return (bool) $stmt->execute();
    }

    public function updateYear(int $id, int $year): bool
    {
        $stmt = $this->db->prepare('UPDATE enrollments SET year = ? WHERE id = ?');
        $stmt->bind_param('ii', $year, $id);
        return (bool) $stmt->execute();
    }

    public function archive(int $id): bool
    {
        $stmt = $this->db->prepare('UPDATE enrollments SET archived_at = NOW() WHERE id = ? AND archived_at IS NULL');
        $stmt->bind_param('i', $id);
        return (bool) $stmt->execute();
    }

    public function restore(int $id): bool
    {
        $stmt = $this->db->prepare('UPDATE enrollments SET archived_at = NULL WHERE id = ?');
        $stmt->bind_param('i', $id);
        return (bool) $stmt->execute();
    }

    public function get(int $id): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT e.*, s.user_id, s.grado
         FROM enrollments e
         JOIN students s ON s.id = e.student_id
         WHERE e.id = ?
         LIMIT 1"
        );
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        return $row ?: null;
    }

    public function countUsedForGrade(int $grado, int $year): int
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) AS c
         FROM enrollments e
         JOIN students s ON s.id = e.student_id
         WHERE e.year = ?
           AND s.grado = ?
           AND e.estado <> 'BLOQUEADO'
           AND e.archived_at IS NULL
           AND s.archived_at IS NULL"
        );
        $stmt->bind_param('ii', $year, $grado);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        return (int) ($row['c'] ?? 0);
    }

    public function countUsedByGrade(int $year): array
    {
        $stmt = $this->db->prepare(
            "SELECT s.grado, COUNT(*) AS c
         FROM enrollments e
         JOIN students s ON s.id = e.student_id
         WHERE e.year = ?
           AND e.estado <> 'BLOQUEADO'
           AND e.archived_at IS NULL
           AND s.archived_at IS NULL
           AND s.grado BETWEEN 7 AND 11
         GROUP BY s.grado"
        );
        $stmt->bind_param('i', $year);
        $stmt->execute();
        $res = $stmt->get_result();
        $out = [];
        while ($row = $res->fetch_assoc()) {
            $out[(int) $row['grado']] = (int) $row['c'];
        }
        return $out;
    }

    public function countUsedForGradeExcludingEnrollment(int $grado, int $year, int $excludeEnrollmentId): int
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) AS c
         FROM enrollments e
         JOIN students s ON s.id = e.student_id
         WHERE e.year = ?
           AND s.grado = ?
           AND e.estado <> 'BLOQUEADO'
           AND e.archived_at IS NULL
           AND s.archived_at IS NULL
           AND e.id <> ?"
        );
        $stmt->bind_param('iii', $year, $grado, $excludeEnrollmentId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        return (int) ($row['c'] ?? 0);
    }

    public function archiveByStudentId(int $student_id): bool
    {
        $stmt = $this->db->prepare('UPDATE enrollments SET archived_at = NOW() WHERE student_id = ? AND archived_at IS NULL');
        $stmt->bind_param('i', $student_id);
        return (bool) $stmt->execute();
    }

    public function restoreByStudentId(int $student_id): bool
    {
        $stmt = $this->db->prepare('UPDATE enrollments SET archived_at = NULL WHERE student_id = ?');
        $stmt->bind_param('i', $student_id);
        return (bool) $stmt->execute();
    }

    public function getPaymentControl(int $enrollmentId, int $paymentYear): array
    {
        $months = $this->paymentMonths();
        $defaults = [];
        foreach ($months as $month) {
            $defaults[$month] = [
                'month_key' => $month,
                'invoice_number' => '',
                'is_paid' => 0,
            ];
        }

        $stmt = $this->db->prepare(
            'SELECT month_key, invoice_number, is_paid
     FROM enrollment_payments
     WHERE enrollment_id = ?
       AND payment_year = ?'
        );
        $stmt->bind_param('ii', $enrollmentId, $paymentYear);
        $stmt->execute();
        $res = $stmt->get_result();

        while ($row = $res->fetch_assoc()) {
            $key = (string) ($row['month_key'] ?? '');
            if ($key !== '' && isset($defaults[$key])) {
                $defaults[$key] = [
                    'month_key' => $key,
                    'invoice_number' => (string) ($row['invoice_number'] ?? ''),
                    'is_paid' => (int) ($row['is_paid'] ?? 0),
                ];
            }
        }

        return array_values($defaults);
    }

    public function savePaymentControl(int $enrollmentId, int $paymentYear, array $items): bool
    {
        $allowedMonths = array_flip($this->paymentMonths());

        $delete = $this->db->prepare(
            'DELETE FROM enrollment_payments
     WHERE enrollment_id = ?
       AND payment_year = ?'
        );
        $delete->bind_param('ii', $enrollmentId, $paymentYear);
        if (!$delete->execute()) {
            return false;
        }

        $insert = $this->db->prepare(
            'INSERT INTO enrollment_payments (enrollment_id, payment_year, month_key, invoice_number, is_paid)
     VALUES (?,?,?,?,?)'
        );

        foreach ($items as $item) {
            $monthKey = (string) ($item['month_key'] ?? '');
            if (!isset($allowedMonths[$monthKey])) {
                continue;
            }

            $invoiceNumber = trim((string) ($item['invoice_number'] ?? ''));
            $isPaid = !empty($item['is_paid']) ? 1 : 0;

            $insert->bind_param('iissi', $enrollmentId, $paymentYear, $monthKey, $invoiceNumber, $isPaid);
            if (!$insert->execute()) {
                return false;
            }
        }

        return true;
    }

    private function paymentMonths(): array
    {
        return [
            'matricula',
            'febrero',
            'marzo',
            'abril',
            'mayo',
            'junio',
            'julio',
            'agosto',
            'septiembre',
            'octubre',
            'noviembre',
            'diciembre',
        ];
    }
}
