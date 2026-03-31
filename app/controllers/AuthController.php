<?php
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/mailer.php';

class AuthController
{
    private function requestData(): array
    {
        static $data = null;

        if ($data !== null) {
            return $data;
        }

        $data = $_POST;

        if (!empty($data)) {
            return $data;
        }

        $raw = file_get_contents('php://input');
        if (!is_string($raw) || trim($raw) === '') {
            return $data;
        }

        $json = json_decode($raw, true);
        if (is_array($json)) {
            $data = $json;
            return $data;
        }

        parse_str($raw, $parsed);
        if (is_array($parsed) && !empty($parsed)) {
            $data = $parsed;
        }

        return $data;
    }

    private function requestValue(array $keys, string $default = ''): string
    {
        $data = $this->requestData();

        foreach ($keys as $key) {
            if (array_key_exists($key, $data)) {
                return trim((string) $data[$key]);
            }
        }

        return $default;
    }
    public function login(): void
    {
        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        $userModel = new User();
        $raw = $userModel->getByUsernameRaw($username);

        if ($raw) {
            $estado = strtoupper((string) ($raw['estado'] ?? 'ACTIVO'));

            if (password_verify($password, (string) $raw['password_hash'])) {
                if ($estado !== 'ACTIVO') {
                    http_response_code(401);
                    echo json_encode([
                        'status' => 'error',
                        'message' => 'Usuario inactivo, diríjase a la dirección del colegio'
                    ]);
                    return;
                }

                unset($raw['password_hash']);
                ensure_session_started();
                $_SESSION['user'] = $raw;

                echo json_encode([
                    'status' => 'success',
                    'user' => $raw
                ]);
                return;
            }
        }

        http_response_code(401);
        echo json_encode([
            'status' => 'error',
            'message' => 'Usuario o contraseña inválidos'
        ]);
    }

    public function me(): void
    {
        $u = current_user();

        if (!$u) {
            http_response_code(401);
            echo json_encode([
                'status' => 'error',
                'message' => 'No autenticado'
            ]);
            return;
        }

        echo json_encode([
            'status' => 'success',
            'user' => $u
        ]);
    }

    public function logout(): void
    {
        ensure_session_started();
        session_destroy();

        echo json_encode([
            'status' => 'success'
        ]);
    }

    public function register(): void
    {
        $username = $this->requestValue(['username', 'user', 'usuario']);
        $password = $this->requestValue(['password', 'contrasena', 'clave']);
        $nombre   = $this->requestValue(['nombre', 'name', 'full_name']);
        $correo   = $this->requestValue(['correo', 'email']);
        $telefono = $this->requestValue(['telefono', 'phone']);

        if ($username === '' || $password === '' || $nombre === '' || $correo === '') {
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => 'Faltan datos'
            ]);
            return;
        }

        if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => 'El correo no tiene un formato válido'
            ]);
            return;
        }

        $telefono = $telefono !== '' ? $telefono : null;

        $userModel = new User();

        if ($userModel->usernameExists($username)) {
            http_response_code(409);
            echo json_encode([
                'status' => 'error',
                'message' => 'El nombre de usuario ya existe'
            ]);
            return;
        }

        if ($userModel->correoExists($correo)) {
            http_response_code(409);
            echo json_encode([
                'status' => 'error',
                'message' => 'El correo ya se encuentra registrado'
            ]);
            return;
        }

        $ok = $userModel->create($username, $password, $nombre, $correo, $telefono, 'ESTUDIANTE');

        if ($ok) {
            echo json_encode([
                'status' => 'success',
                'message' => 'Usuario registrado correctamente'
            ]);
        } else {
            http_response_code(500);
            echo json_encode([
                'status' => 'error',
                'message' => 'No se pudo registrar el usuario'
            ]);
        }
    }

    public function forgotPassword(): void
    {
        $correo = trim((string) ($_POST['correo'] ?? ''));

        if ($correo === '') {
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => 'Debe indicar un correo'
            ]);
            return;
        }

        if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => 'El correo no tiene un formato válido'
            ]);
            return;
        }

        $userModel = new User();
        $user = $userModel->getByCorreo($correo);

        if (!$user) {
            http_response_code(404);
            echo json_encode([
                'status' => 'error',
                'message' => 'No existe un usuario con ese correo'
            ]);
            return;
        }

        $config = require __DIR__ . '/../config/config.php';
        $mailConfig = $config['mail'] ?? [];

        try {
            $token = bin2hex(random_bytes(32));
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'status' => 'error',
                'message' => 'No se pudo generar el token de seguridad'
            ]);
            return;
        }

        $expires = (new DateTime('+1 hour'))->format('Y-m-d H:i:s');

        if (!$userModel->saveResetToken((int) $user['id'], $token, $expires)) {
            http_response_code(500);
            echo json_encode([
                'status' => 'error',
                'message' => 'No se pudo guardar el token de recuperación'
            ]);
            return;
        }

        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $baseUrl = rtrim((string) ($config['base_url'] ?? ''), '/');

        $resetLink = $scheme . '://' . $host . $baseUrl . '/restore.php?token=' . urlencode($token);

        $nombreUsuario = (string) ($user['nombre'] ?? 'usuario');
        $nombreSeguro = htmlspecialchars($nombreUsuario, ENT_QUOTES, 'UTF-8');
        $resetLinkSeguro = htmlspecialchars($resetLink, ENT_QUOTES, 'UTF-8');

        $htmlBody = '
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restablecimiento de contraseña</title>
</head>
<body style="margin:0; padding:0; background-color:#f4f6f8; font-family:Arial, Helvetica, sans-serif; color:#333333;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color:#f4f6f8; padding:30px 15px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:600px; background-color:#ffffff; border-radius:14px; overflow:hidden; box-shadow:0 4px 16px rgba(0,0,0,0.08);">
                    
                    <tr>
                        <td style="background:linear-gradient(135deg, #1f4e79, #2f6fa5); padding:28px; text-align:center;">
                            <h1 style="margin:0; color:#ffffff; font-size:26px;">New Hope School</h1>
                            <p style="margin:8px 0 0; color:#dbe8f4; font-size:14px;">Recuperación de acceso a su cuenta</p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:36px 32px;">
                            <p style="margin:0 0 18px; font-size:16px; line-height:1.6;">
                                Hola <strong>' . $nombreSeguro . '</strong>,
                            </p>

                            <p style="margin:0 0 16px; font-size:15px; line-height:1.7; color:#444444;">
                                Hemos recibido una solicitud para restablecer la contraseña de su cuenta en <strong>New Hope School</strong>.
                            </p>

                            <p style="margin:0 0 24px; font-size:15px; line-height:1.7; color:#444444;">
                                Para continuar con el proceso, haga clic en el siguiente botón:
                            </p>

                            <table role="presentation" cellspacing="0" cellpadding="0" style="margin:0 auto 28px auto;">
                                <tr>
                                    <td align="center" style="border-radius:8px; background-color:#1f4e79;">
                                        <a href="' . $resetLinkSeguro . '" style="display:inline-block; padding:14px 28px; color:#ffffff; text-decoration:none; font-size:15px; font-weight:bold;">
                                            Restablecer contraseña
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <div style="background-color:#f8fafc; border-left:4px solid #1f4e79; padding:14px 16px; margin-bottom:22px; border-radius:6px;">
                                <p style="margin:0; font-size:14px; line-height:1.6; color:#555555;">
                                    Este enlace estará disponible durante <strong>1 hora</strong>.
                                </p>
                            </div>

                            <p style="margin:0 0 12px; font-size:14px; line-height:1.6; color:#555555;">
                                Si el botón no funciona, puede copiar y pegar este enlace en su navegador:
                            </p>

                            <p style="margin:0 0 24px; font-size:13px; line-height:1.7; word-break:break-all;">
                                <a href="' . $resetLinkSeguro . '" style="color:#1f4e79; text-decoration:none;">' . $resetLinkSeguro . '</a>
                            </p>

                            <p style="margin:0; font-size:14px; line-height:1.7; color:#666666;">
                                Si usted no solicitó este cambio, puede ignorar este mensaje con total seguridad.
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="background-color:#f0f3f6; padding:20px 30px; text-align:center;">
                            <p style="margin:0 0 6px; font-size:12px; color:#666666;">
                                Este es un mensaje automático del sistema de New Hope School.
                            </p>
                            <p style="margin:0; font-size:12px; color:#666666;">
                                Por favor, no responda a este correo.
                            </p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>';

        $textBody = "Hola {$nombreUsuario},

Hemos recibido una solicitud para restablecer la contraseña de su cuenta en New Hope School.

Para crear una nueva contraseña, ingrese al siguiente enlace:
{$resetLink}

Este enlace estará disponible durante 1 hora.

Si usted no solicitó este cambio, puede ignorar este mensaje con total seguridad.

New Hope School
Este es un mensaje automático. Por favor, no responda este correo.";

        try {
            $mailer = new Mailer($mailConfig);
            $mailer->send(
                (string) $user['correo'],
                $nombreUsuario,
                'Restablecimiento de contraseña - New Hope School',
                $htmlBody,
                $textBody
            );

            echo json_encode([
                'status' => 'success',
                'message' => 'Se envió el correo de recuperación correctamente'
            ]);
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'status' => 'error',
                'message' => 'No se pudo enviar el correo',
                'detail' => $e->getMessage()
            ]);
        }
    }

    public function resetPassword(): void
    {
        $token = $this->requestValue(['token']);
        $password = $this->requestValue(['password', 'contrasena', 'clave']);
        $confirmPassword = $this->requestValue(['confirm_password', 'confirmPassword', 'confirmar_password']);

        if ($token === '' || $password === '' || $confirmPassword === '') {
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => 'Faltan datos'
            ]);
            return;
        }

        if ($password !== $confirmPassword) {
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => 'Las contraseñas no coinciden'
            ]);
            return;
        }

        if (strlen($password) < 6) {
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => 'La contraseña debe tener al menos 6 caracteres'
            ]);
            return;
        }

        $userModel = new User();
        $user = $userModel->getByResetToken($token);

        if (!$user) {
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => 'Token inválido'
            ]);
            return;
        }

        $expiresAt = strtotime((string) ($user['reset_expires_at'] ?? ''));
        if (!$expiresAt || $expiresAt < time()) {
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => 'El token ha expirado'
            ]);
            return;
        }

        if (!$userModel->updatePassword((int) $user['id'], $password)) {
            http_response_code(500);
            echo json_encode([
                'status' => 'error',
                'message' => 'No se pudo actualizar la contraseña'
            ]);
            return;
        }

        $updatedHash = $userModel->getPasswordHashById((int) $user['id']);
        if (!$updatedHash || !password_verify($password, $updatedHash)) {
            http_response_code(500);
            echo json_encode([
                'status' => 'error',
                'message' => 'La contraseña no pudo verificarse después de la actualización'
            ]);
            return;
        }

        echo json_encode([
            'status' => 'success',
            'message' => 'Contraseña actualizada correctamente'
        ]);
    }
}