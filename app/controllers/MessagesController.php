<?php
require_once __DIR__ . '/../models/Message.php';
require_once __DIR__ . '/../helpers/auth.php';

class MessagesController {
    public function inbox(): void {
        require_login();
        $u = current_user();
        $model = new Message();
        $data = $model->inbox((int)$u['id'], (string)$u['rol'], (int)($_GET['limit'] ?? 200));
        echo json_encode(['status' => 'success', 'data' => $data]);
    }

    public function sent(): void {
        require_login();
        $u = current_user();
        $model = new Message();
        $data = $model->sent((int)$u['id'], (int)($_GET['limit'] ?? 200));
        echo json_encode(['status' => 'success', 'data' => $data]);
    }

    public function send(): void {
        require_login();
        $u = current_user();
        $to_user_id = $_POST['to_user_id'] ?? null;
        $to_role = $_POST['to_role'] ?? null;
        $asunto = $_POST['asunto'] ?? '';
        $cuerpo = $_POST['cuerpo'] ?? '';

        if (!$asunto || !$cuerpo || (!$to_user_id && !$to_role)) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Faltan datos (destino, asunto y cuerpo)']);
            return;
        }

        $to_user_id_int = $to_user_id ? (int)$to_user_id : null;
        $to_role = $to_role ? strtoupper(trim($to_role)) : null;

        $model = new Message();
        $id = $model->send((int)$u['id'], $to_user_id_int, $to_role, $asunto, $cuerpo);
        if ($id) echo json_encode(['status' => 'success', 'id' => $id]);
        else { http_response_code(500); echo json_encode(['status' => 'error', 'message' => 'No se pudo enviar']); }
    }
}
