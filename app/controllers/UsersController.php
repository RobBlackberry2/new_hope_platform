<?php
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../helpers/auth.php';

class UsersController {
    public function list(): void {
        require_login();
        require_role(['ADMIN']);
        $model = new User();
        $limit = (int)($_GET['limit'] ?? 200);
        echo json_encode(['status' => 'success', 'data' => $model->list($limit)]);
    }

    public function create(): void {
        require_login();
        require_role(['ADMIN']);
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        $nombre = $_POST['nombre'] ?? '';
        $correo = $_POST['correo'] ?? '';
        $telefono = $_POST['telefono'] ?? null;
        $rol = $_POST['rol'] ?? 'ESTUDIANTE';

        if (!$username || !$password || !$nombre || !$correo) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Faltan datos']);
            return;
        }

        $model = new User();
        if ($model->usernameExists($username)) {
            http_response_code(409);
            echo json_encode(['status' => 'error', 'message' => 'El username ya existe']);
            return;
        }

        if ($model->create($username, $password, $nombre, $correo, $telefono, $rol)) {
            echo json_encode(['status' => 'success']);
        } else {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'No se pudo crear el usuario']);
        }
    }

    public function update(): void {
        require_login();
        require_role(['ADMIN']);
        $id = (int)($_POST['id'] ?? 0);
        $nombre = $_POST['nombre'] ?? '';
        $correo = $_POST['correo'] ?? '';
        $telefono = $_POST['telefono'] ?? null;

        if (!$id || !$nombre || !$correo) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Faltan datos']);
            return;
        }

        $model = new User();
        if ($model->update($id, $nombre, $correo, $telefono)) {
            echo json_encode(['status' => 'success']);
        } else {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'No se pudo actualizar']);
        }
    }

    public function setRole(): void {
        require_login();
        require_role(['ADMIN']);
        $id = (int)($_POST['id'] ?? 0);
        $rol = $_POST['rol'] ?? '';
        if (!$id || !$rol) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Faltan datos']);
            return;
        }
        $model = new User();
        if ($model->setRole($id, $rol)) echo json_encode(['status' => 'success']);
        else { http_response_code(500); echo json_encode(['status' => 'error', 'message' => 'No se pudo cambiar el rol']); }
    }

    public function setEstado(): void {
        require_login();
        require_role(['ADMIN']);
        $id = (int)($_POST['id'] ?? 0);
        $estado = $_POST['estado'] ?? '';
        if (!$id || !$estado) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Faltan datos']);
            return;
        }
        $model = new User();
        if ($model->setEstado($id, $estado)) echo json_encode(['status' => 'success']);
        else { http_response_code(500); echo json_encode(['status' => 'error', 'message' => 'No se pudo cambiar el estado']); }
    }

    public function delete(): void {
        require_login();
        require_role(['ADMIN']);
        $id = (int)($_POST['id'] ?? 0);
        if (!$id) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Falta id']);
            return;
        }
        $model = new User();
        if ($model->delete($id)) echo json_encode(['status' => 'success']);
        else { http_response_code(500); echo json_encode(['status' => 'error', 'message' => 'No se pudo eliminar']); }
    }
}
