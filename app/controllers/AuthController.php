<?php
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../helpers/auth.php';

class AuthController {
    public function login(): void {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';

        $userModel = new User();
        $u = $userModel->login($username, $password);
        if ($u) {
            ensure_session_started();
            $_SESSION['user'] = $u;
            echo json_encode(['status' => 'success', 'user' => $u]);
        } else {
            http_response_code(401);
            echo json_encode(['status' => 'error', 'message' => 'Usuario o contraseña inválidos']);
        }
    }

    public function me(): void {
        $u = current_user();
        if (!$u) {
            http_response_code(401);
            echo json_encode(['status' => 'error', 'message' => 'No autenticado']);
            return;
        }
        echo json_encode(['status' => 'success', 'user' => $u]);
    }

    public function logout(): void {
        ensure_session_started();
        session_destroy();
        echo json_encode(['status' => 'success']);
    }

    // Registro básico (por defecto ESTUDIANTE) Los docentes deben ser registrados desde la administracion o su rol debe ser actualizado
    public function register(): void {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        $nombre = $_POST['nombre'] ?? '';
        $correo = $_POST['correo'] ?? '';
        $telefono = $_POST['telefono'] ?? null;

        if (!$username || !$password || !$nombre || !$correo) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Faltan datos']);
            return;
        }

        $user = new User();
        if ($user->usernameExists($username)) {
            http_response_code(409);
            echo json_encode(['status' => 'error', 'message' => 'El username ya existe']);
            return;
        }

        if ($user->create($username, $password, $nombre, $correo, $telefono, 'ESTUDIANTE')) {
            echo json_encode(['status' => 'success']);
        } else {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'No se pudo registrar']);
        }
    }
}
