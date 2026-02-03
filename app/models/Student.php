<?php
require_once __DIR__ . '/../config/db.php';

class Student {
    private mysqli $db;

    public function __construct() {
        $this->db = Database::connect();
    }

    public function create(array $data): int|false {
        $user_id = $data['user_id'] ?? null;
        $cedula = $data['cedula'] ?? null;
        $nombre = $data['nombre'] ?? '';
        $fecha_nacimiento = $data['fecha_nacimiento'] ?? null; // YYYY-MM-DD
        $grado = (int)($data['grado'] ?? 7);
        $seccion = $data['seccion'] ?? null;
        $encargado = $data['encargado'] ?? null;
        $telefono_encargado = $data['telefono_encargado'] ?? null;

        $stmt = $this->db->prepare(
            'INSERT INTO students (user_id, cedula, nombre, fecha_nacimiento, grado, seccion, encargado, telefono_encargado)
             VALUES (?,?,?,?,?,?,?,?)'
        );
        $stmt->bind_param('isssisss', $user_id, $cedula, $nombre, $fecha_nacimiento, $grado, $seccion, $encargado, $telefono_encargado);
        if (!$stmt->execute()) return false;
        return (int)$this->db->insert_id;
    }

    public function get(int $id): ?array {
        $stmt = $this->db->prepare('SELECT * FROM students WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        return $row ?: null;
    }

    public function list(int $limit = 200): array {
        $limit = max(1, min(1000, $limit));
        $res = $this->db->query('SELECT * FROM students ORDER BY id DESC LIMIT ' . $limit);
        return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    }

    public function update(int $id, array $data): bool {
        $cedula = $data['cedula'] ?? null;
        $nombre = $data['nombre'] ?? '';
        $fecha_nacimiento = $data['fecha_nacimiento'] ?? null;
        $grado = (int)($data['grado'] ?? 7);
        $seccion = $data['seccion'] ?? null;
        $encargado = $data['encargado'] ?? null;
        $telefono_encargado = $data['telefono_encargado'] ?? null;

        $stmt = $this->db->prepare(
            'UPDATE students SET cedula = ?, nombre = ?, fecha_nacimiento = ?, grado = ?, seccion = ?, encargado = ?, telefono_encargado = ? WHERE id = ?'
        );
        $stmt->bind_param('sssisssi', $cedula, $nombre, $fecha_nacimiento, $grado, $seccion, $encargado, $telefono_encargado, $id);
        return (bool)$stmt->execute();
    }

    public function getByUserId(int $user_id): ?array {
        $stmt = $this->db->prepare('SELECT * FROM students WHERE user_id = ? LIMIT 1');
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        return $row ?: null;
    }

    public function delete(int $id): bool {
        $stmt = $this->db->prepare('DELETE FROM students WHERE id = ?');
        $stmt->bind_param('i', $id);
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
