<?php
require_once __DIR__ . '/../config/db.php';

class ReportNotification {
    private mysqli $db;

    public function __construct() {
        $this->db = Database::connect();
    }

    public function create(int $user_id, ?int $report_id, string $tipo): int|false {
        $stmt = $this->db->prepare(
            'INSERT INTO report_notifications (user_id, report_id, tipo) VALUES (?,?,?)'
        );
        $stmt->bind_param('iis', $user_id, $report_id, $tipo);
        if (!$stmt->execute()) return false;
        return (int)$this->db->insert_id;
    }

    public function markAsRead(int $id): bool {
        $stmt = $this->db->prepare('UPDATE report_notifications SET read_at = NOW() WHERE id = ?');
        $stmt->bind_param('i', $id);
        return (bool)$stmt->execute();
    }

    public function markAllAsRead(int $user_id): bool {
        $stmt = $this->db->prepare('UPDATE report_notifications SET read_at = NOW() WHERE user_id = ? AND read_at IS NULL');
        $stmt->bind_param('i', $user_id);
        return (bool)$stmt->execute();
    }

    public function getByUser(int $user_id, bool $unread_only = false): array {
        if ($unread_only) {
            $stmt = $this->db->prepare(
                'SELECT rn.*, r.titulo as report_title 
                 FROM report_notifications rn 
                 LEFT JOIN reports r ON rn.report_id = r.id 
                 WHERE rn.user_id = ? AND rn.read_at IS NULL 
                 ORDER BY rn.sent_at DESC'
            );
        } else {
            $stmt = $this->db->prepare(
                'SELECT rn.*, r.titulo as report_title 
                 FROM report_notifications rn 
                 LEFT JOIN reports r ON rn.report_id = r.id 
                 WHERE rn.user_id = ? 
                 ORDER BY rn.sent_at DESC'
            );
        }
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $res = $stmt->get_result();
        return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    }

    public function sendEmailNotification(int $user_id, int $report_id): bool {
        // Obtener información del usuario
        $stmt = $this->db->prepare('SELECT nombre, correo FROM users WHERE id = ?');
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        
        if (!$user || !$user['correo']) {
            return false;
        }

        // Obtener información del reporte
        $stmt = $this->db->prepare('SELECT titulo, tipo FROM reports WHERE id = ?');
        $stmt->bind_param('i', $report_id);
        $stmt->execute();
        $report = $stmt->get_result()->fetch_assoc();
        
        if (!$report) {
            return false;
        }

        $to = $user['correo'];
        $subject = "Nuevo Reporte Disponible - " . $report['titulo'];
        $message = "Estimado/a {$user['nombre']},\n\n";
        $message .= "Se ha generado un nuevo reporte académico: {$report['titulo']}\n";
        $message .= "Tipo: {$report['tipo']}\n\n";
        $message .= "Por favor, inicie sesión en la plataforma para consultarlo.\n\n";
        $message .= "Saludos,\n";
        $message .= "Colegio Bilingüe New Hope";

        $headers = "From: noreply@colegio.local\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

        // Intentar enviar el email
        $sent = @mail($to, $subject, $message, $headers);
        
        // Registrar la notificación en la base de datos
        $this->create($user_id, $report_id, 'NUEVO_REPORTE');
        
        return $sent;
    }

    public function delete(int $id): bool {
        $stmt = $this->db->prepare('DELETE FROM report_notifications WHERE id = ?');
        $stmt->bind_param('i', $id);
        return (bool)$stmt->execute();
    }
}
