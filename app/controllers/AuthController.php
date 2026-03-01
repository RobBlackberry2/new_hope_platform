<?php
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../helpers/auth.php';

class AuthController
{
    /* =========================
       LOGIN
       ========================= */
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
                        'message' => 'Usuario inactivo, diríjase a la dirección del colegio',
                    ]);
                    return;
                }

                unset($raw['password_hash']);
                ensure_session_started();
                $_SESSION['user'] = $raw;

                echo json_encode(['status' => 'success', 'user' => $raw]);
                return;
            }
        }

        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Usuario o contraseña inválidos']);
    }

    /* =========================
       USUARIO ACTUAL
       ========================= */
    public function me(): void
    {
        $u = current_user();
        if (!$u) {
            http_response_code(401);
            echo json_encode(['status' => 'error', 'message' => 'No autenticado']);
            return;
        }
        echo json_encode(['status' => 'success', 'user' => $u]);
    }

    /* =========================
       LOGOUT
       ========================= */
    public function logout(): void
    {
        ensure_session_started();
        $_SESSION = [];
        session_destroy();
        echo json_encode(['status' => 'success']);
    }

    /* =========================
       REGISTRO
       ========================= */
    public function register(): void
    {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        $nombre   = $_POST['nombre'] ?? '';
        $correo   = $_POST['correo'] ?? '';
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

        if (!$user->create($username, $password, $nombre, $correo, $telefono, 'ESTUDIANTE')) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'No se pudo registrar']);
            return;
        }

        echo json_encode(['status' => 'success']);
    }

    /* =========================
       🔐 RECUPERAR CONTRASEÑA
       ========================= */
    public function forgotPassword(): void
    {
        $correo = trim((string) ($_POST['correo'] ?? ''));

        if (!$correo) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Debe indicar un correo']);
            return;
        }

        $userModel = new User();
        $user = $userModel->getByCorreo($correo);

        if (!$user) {
            echo json_encode([
                'status' => 'success',
                'message' => 'Si el correo existe en el sistema, se ha enviado un enlace de recuperación',
                'reset_link' => null
            ]);
            return;
        }

        // Token + expiración
        $token = bin2hex(random_bytes(32));
        $expires = (new DateTime('+1 hour'))->format('Y-m-d H:i:s');

        if (!$userModel->saveResetToken((int) $user['id'], $token, $expires)) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'No se pudo generar enlace']);
            return;
        }

        // Construir link
        $config = require __DIR__ . '/../config/config.php';
        $baseUrl = $config['base_url'] ?? '';
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $resetLink = $scheme . "://$host$baseUrl/restablecer.php?token=" . urlencode($token);

        // PHPMailer conf
        $mailConfig = $config['mail'];

        require_once __DIR__ . '/../../libs/PHPMailer/PHPMailer.php';
        require_once __DIR__ . '/../../libs/PHPMailer/SMTP.php';
        require_once __DIR__ . '/../../libs/PHPMailer/Exception.php';

        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);

        try {

            $mail->isSMTP();
            $mail->Host = $mailConfig['host'];
            $mail->SMTPAuth = true;
            $mail->Username = $mailConfig['username'];
            $mail->Password = $mailConfig['password'];
            $mail->SMTPSecure = $mailConfig['secure'];
            $mail->Port = $mailConfig['port'];

            $mail->setFrom($mailConfig['from_email'], $mailConfig['from_name']);
            $mail->addAddress($user['correo'], $user['nombre']);

            $mail->isHTML(true);

            // 🔵 Incrustar logo
            $logoPath = __DIR__ . '/../../img/logo_nh.png'; // AJUSTAR SI ES OTRA RUTA
            if (file_exists($logoPath)) {
                $mail->addEmbeddedImage($logoPath, 'nhlogo', 'logo_nh.png');
            }

            $nombre = $user['nombre'] ?? $user['username'];

            // HTML del correo
            $mail->Subject = 'Restablecimiento de contraseña - New Hope School';
            $mail->Body = '
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f7;padding:24px 0;">
  <tr>
    <td align="center">
      <table style="max-width:600px;background:white;border-radius:8px;padding:32px;font-family:system-ui;border:1px solid #e5e7eb;">
        <tr>
          <td style="text-align:center;padding-bottom:16px;border-bottom:1px solid #e5e7eb;">
            <img src="cid:nhlogo" alt="Logo" style="height:60px;margin-bottom:12px;">
            <div style="font-size:20px;font-weight:600;color:#1d4ed8;">New Hope School</div>
            <div style="font-size:12px;color:#6b7280;">Plataforma Académica</div>
          </td>
        </tr>

        <tr>
          <td style="font-size:14px;padding-top:24px;color:#111;">
            <p>Estimado(a) <strong>' . htmlspecialchars($nombre) . '</strong>,</p>
            <p>Hemos recibido una solicitud para restablecer la contraseña de su cuenta en la plataforma New Hope School.</p>

            <p style="text-align:center;margin:20px 0;">
              <a href="' . $resetLink . '" style="background:#2563eb;color:white;padding:12px 24px;border-radius:999px;text-decoration:none;font-weight:500;">
                Restablecer contraseña
              </a>
            </p>

            <p>Si el botón no funciona, copie el siguiente enlace:</p>
            <p style="font-size:12px;color:#1d4ed8;word-break:break-all;">' . $resetLink . '</p>

            <p>Este enlace es válido por <strong>1 hora</strong>.</p>

            <p>Atentamente,<br><strong>Equipo Académico New Hope School</strong></p>
          </td>
        </tr>

        <tr>
          <td style="font-size:11px;color:#9ca3af;text-align:center;border-top:1px solid #e5e7eb;padding-top:20px;">
            Este es un mensaje automático, por favor no responder.
          </td>
        </tr>
      </table>
    </td>
  </tr>
</table>';

            $mail->AltBody = "Restablecimiento de contraseña:\n\n$resetLink\n\n";

            $mail->send();

            echo json_encode([
                'status' => 'success',
                'message' => 'Si el correo existe, se ha enviado un enlace de recuperación.'
            ]);

        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'No se pudo enviar el correo']);
        }
    }

    /* =========================
       🔐 RESTABLECER CONTRASEÑA
       ========================= */
    public function resetPassword(): void
    {
        $token = trim((string) ($_POST['token'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if (!$token || !$password) {
            http_response_code(400);
            echo json_encode(['status'=>'error','message'=>'Datos incompletos']);
            return;
        }

        $userModel = new User();
        $user = $userModel->getByResetToken($token);

        if (!$user) {
            http_response_code(400);
            echo json_encode(['status'=>'error','message'=>'Enlace inválido']);
            return;
        }

        // Expiró
        if (new DateTime() > new DateTime($user['reset_expires_at'])) {
            http_response_code(400);
            echo json_encode(['status'=>'error','message'=>'El enlace ha expirado']);
            return;
        }

        if (strlen($password) < 6) {
            http_response_code(400);
            echo json_encode(['status'=>'error','message'=>'La contraseña debe tener al menos 6 caracteres']);
            return;
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $userModel->updatePasswordById($user['id'], $hash);
        $userModel->clearResetToken($user['id']);

        echo json_encode(['status'=>'success','message'=>'Contraseña restablecida correctamente']);
    }

    /* =========================
       🔐 CAMBIAR CONTRASEÑA (Manual)
       ========================= */
    public function changePassword(): void
    {
        $username = trim((string) ($_POST['username'] ?? ''));
        $current  = (string) ($_POST['current_password'] ?? '');
        $new      = (string) ($_POST['password'] ?? '');
        $confirm  = (string) ($_POST['password_confirm'] ?? '');

        if (!$username || !$current || !$new || !$confirm) {
            http_response_code(400);
            echo json_encode(['status'=>'error','message'=>'Debe completar todos los campos']);
            return;
        }

        if ($new !== $confirm) {
            http_response_code(400);
            echo json_encode(['status'=>'error','message'=>'Las contraseñas no coinciden']);
            return;
        }

        if (strlen($new) < 6) {
            http_response_code(400);
            echo json_encode(['status'=>'error','message'=>'La nueva contraseña debe tener al menos 6 caracteres']);
            return;
        }

        $userModel = new User();
        $raw = $userModel->getByUsernameRaw($username);

        if (!$raw || !password_verify($current, (string)$raw['password_hash'])) {
            http_response_code(401);
            echo json_encode(['status'=>'error','message'=>'Usuario o contraseña actual incorrectos']);
            return;
        }

        $hash = password_hash($new, PASSWORD_DEFAULT);
        $userModel->updatePasswordById($raw['id'], $hash);
        $userModel->clearResetToken($raw['id']);

        echo json_encode(['status'=>'success','message'=>'Contraseña actualizada correctamente']);
    }
}