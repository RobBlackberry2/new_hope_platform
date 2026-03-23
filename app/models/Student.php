<?php
require_once __DIR__ . '/../config/db.php';

class Student {
    private mysqli $db;

    public function __construct() {
        $this->db = Database::connect();
    }

    private function fullNameExpr(string $alias = 'students'): string {
        return "TRIM(CONCAT({$alias}.nombre, ' ', COALESCE({$alias}.apellidos, '')))";
    }

    public function create(array $data): int|false {
        $user_id = $data['user_id'] ?? null;
        $cedula = $data['cedula'] ?? null;
        $nombre = trim((string)($data['nombre'] ?? ''));
        $apellidos = trim((string)($data['apellidos'] ?? ''));
        $fecha_nacimiento = $data['fecha_nacimiento'] ?? null; // YYYY-MM-DD
        $grado = (int)($data['grado'] ?? 7);
        $seccion = $data['seccion'] ?? null;
        $encargado = $data['encargado'] ?? null;
        $telefono_encargado = $data['telefono_encargado'] ?? null;

        $stmt = $this->db->prepare(
            'INSERT INTO students (user_id, cedula, nombre, apellidos, fecha_nacimiento, grado, seccion, encargado, telefono_encargado, archived_at)
             VALUES (?,?,?,?,?,?,?,?,?,NULL)'
        );
        $stmt->bind_param('issssisss', $user_id, $cedula, $nombre, $apellidos, $fecha_nacimiento, $grado, $seccion, $encargado, $telefono_encargado);
        if (!$stmt->execute()) return false;
        return (int)$this->db->insert_id;
    }

    public function get(int $id): ?array {
        $sql = 'SELECT students.*, ' . $this->fullNameExpr('students') . ' AS nombre_completo FROM students WHERE id = ?';
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        return $row ?: null;
    }

    public function getByUserId(int $user_id): ?array {
        $sql = 'SELECT students.*, ' . $this->fullNameExpr('students') . ' AS nombre_completo FROM students WHERE user_id = ? AND archived_at IS NULL LIMIT 1';
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        return $row ?: null;
    }

    public function list(int $limit = 200, bool $include_archived = false): array {
        $limit = max(1, min(1000, $limit));
        $where = $include_archived ? '' : ' WHERE archived_at IS NULL';
        $res = $this->db->query('SELECT students.*, ' . $this->fullNameExpr('students') . ' AS nombre_completo FROM students' . $where . ' ORDER BY archived_at IS NULL DESC, id DESC LIMIT ' . $limit);
        return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    }

    public function archive(int $id): bool {
        $stmt = $this->db->prepare('UPDATE students SET archived_at = NOW() WHERE id = ? AND archived_at IS NULL');
        $stmt->bind_param('i', $id);
        return (bool)$stmt->execute();
    }

    public function restore(int $id): bool {
        $stmt = $this->db->prepare('UPDATE students SET archived_at = NULL WHERE id = ?');
        $stmt->bind_param('i', $id);
        return (bool)$stmt->execute();
    }

    public function isArchived(int $id): bool {
        $stmt = $this->db->prepare('SELECT archived_at FROM students WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        return !empty($row['archived_at']);
    }

    public function update(int $id, array $data): bool {
        $cedula = $data['cedula'] ?? null;
        $nombre = trim((string)($data['nombre'] ?? ''));
        $apellidos = trim((string)($data['apellidos'] ?? ''));
        $fecha_nacimiento = $data['fecha_nacimiento'] ?? null;
        $grado = (int)($data['grado'] ?? 7);
        $seccion = $data['seccion'] ?? null;
        $encargado = $data['encargado'] ?? null;
        $telefono_encargado = $data['telefono_encargado'] ?? null;

        $stmt = $this->db->prepare(
            'UPDATE students SET cedula = ?, nombre = ?, apellidos = ?, fecha_nacimiento = ?, grado = ?, seccion = ?, encargado = ?, telefono_encargado = ? WHERE id = ?'
        );
        $stmt->bind_param('ssssisssi', $cedula, $nombre, $apellidos, $fecha_nacimiento, $grado, $seccion, $encargado, $telefono_encargado, $id);
        return (bool)$stmt->execute();
    }

    public function updateUserId(int $id, ?int $user_id): bool {
        if (!$user_id) {
            $stmt = $this->db->prepare('UPDATE students SET user_id = NULL WHERE id = ?');
            $stmt->bind_param('i', $id);
            return (bool)$stmt->execute();
        }

        $stmt = $this->db->prepare('UPDATE students SET user_id = ? WHERE id = ?');
        $stmt->bind_param('ii', $user_id, $id);
        return (bool)$stmt->execute();
    }
}
