<?php
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../helpers/auth.php';

class AuthController
{
    public function login(): void
    {
        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        $userModel = new User();

        // Revisar usuario de forma explícita para distinguir INACTIVO
        $raw = $userModel->getByUsernameRaw($username);

        if ($raw) {
            $estado = strtoupper((string) ($raw['estado'] ?? 'ACTIVO'));

            // Si existe y contraseña es correcta
            if (password_verify($password, (string) $raw['password_hash'])) {
                // Pero está inactivo -> mensaje especial
                if ($estado !== 'ACTIVO') {
                    http_response_code(401);
                    echo json_encode([
                        'status'  => 'error',
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

    public function logout(): void
    {
        ensure_session_started();
        session_destroy();
        echo json_encode(['status' => 'success']);
    }

    // Registro básico (por defecto ESTUDIANTE)
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

        if ($user->create($username, $password, $nombre, $correo, $telefono, 'ESTUDIANTE')) {
            echo json_encode(['status' => 'success']);
        } else {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'No se pudo registrar']);
        }
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

        // Para no revelar si el correo existe o no, devolvemos siempre un mensaje genérico
        if (!$user) {
            echo json_encode([
                'status'     => 'success',
                'message'    => 'Si el correo existe en el sistema, se ha enviado un enlace de recuperación',
                'reset_link' => null,
            ]);
            return;
        }

        // Generar token y fecha de expiración (1 hora)
        $token   = bin2hex(random_bytes(32));
        $expires = (new DateTime('+1 hour'))->format('Y-m-d H:i:s');

        if (!$userModel->saveResetToken((int) $user['id'], $token, $expires)) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'No se pudo generar el enlace']);
            return;
        }

        // Construir enlace absoluto
        $config  = require __DIR__ . '/../config/config.php';
        $baseUrl = $config['base_url'] ?? '';
        $scheme  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host    = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $resetLink = $scheme . '://' . $host . $baseUrl . '/restablecer.php?token=' . urlencode($token);

        // Enviar correo con PHPMailer
        $mailConfig = $config['mail'] ?? null;
        if (!$mailConfig || empty($mailConfig['username']) || empty($mailConfig['password'])) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Configuración de correo incompleta']);
            return;
        }

        require_once __DIR__ . '/../../libs/PHPMailer/PHPMailer.php';
        require_once __DIR__ . '/../../libs/PHPMailer/SMTP.php';
        require_once __DIR__ . '/../../libs/PHPMailer/Exception.php';

        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host       = $mailConfig['host'] ?? 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = $mailConfig['username'];
            $mail->Password   = $mailConfig['password'];
            $mail->SMTPSecure = $mailConfig['secure'] ?? 'tls';
            $mail->Port       = $mailConfig['port'] ?? 587;

            $fromEmail = $mailConfig['from_email'] ?? $mailConfig['username'];
            $fromName  = $mailConfig['from_name'] ?? 'New Hope Platform';

            $mail->setFrom($fromEmail, $fromName);
            $mail->addAddress($user['correo'], $user['nombre'] ?? $user['username']);

            $mail->isHTML(true);

            $mail->Subject = 'Restablecimiento de contrasena - New Hope School';
$nombreUsuario = $user['nombre'] ?? $user['username'];

$mail->Body = '
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f4f7;padding:24px 0;">
  <tr>
    <td align="center">
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background-color:#ffffff;border-radius:8px;padding:32px;font-family:system-ui,-apple-system,BlinkMacSystemFont,Segoe UI,sans-serif;color:#111827;border:1px solid #e5e7eb;">
        <!-- Encabezado -->
        <tr>
          <td style="text-align:center;padding-bottom:16px;border-bottom:1px solid #e5e7eb;">
            <div style="font-size:20px;font-weight:600;color:#1d4ed8;">New Hope School</div>
            <div style="font-size:12px;color:#6b7280;margin-top:4px;">Plataforma Académica</div>
          </td>
        </tr>

        <!-- Contenido principal -->
        <tr>
          <td style="font-size:14px;line-height:1.6;padding-top:24px;">
            <p style="margin:0 0 12px 0;">Estimado(a) <strong>' . htmlspecialchars($nombreUsuario, ENT_QUOTES, "UTF-8") . '</strong>,</p>
            <p style="margin:0 0 12px 0;">
              Hemos recibido una solicitud para restablecer la contraseña de su cuenta en la
              <strong>Plataforma New Hope School</strong>.
            </p>
            <p style="margin:0 0 16px 0;">
              Para continuar con el proceso, por favor haga clic en el siguiente botón:
            </p>

            <p style="margin:16px 0;text-align:center;">
              <a href="' . $resetLink . '" style="
                  display:inline-block;
                  background-color:#2563eb;
                  color:#ffffff;
                  text-decoration:none;
                  padding:10px 24px;
                  border-radius:999px;
                  font-size:14px;
                  font-weight:500;
                ">
                Restablecer contraseña
              </a>
            </p>

            <p style="margin:0 0 12px 0;font-size:13px;color:#4b5563;">
              Si el botón no funciona, también puede copiar y pegar el siguiente enlace en su navegador:
            </p>

            <p style="margin:0 0 16px 0;font-size:12px;color:#1d4ed8;word-break:break-all;">
              ' . $resetLink . '
            </p>

            <p style="margin:0 0 12px 0;font-size:13px;color:#4b5563;">
              Este enlace tendrá validez por un periodo de <strong>1 hora</strong>. 
              Si usted no solicitó este cambio, puede ignorar este mensaje y su contraseña seguirá siendo la misma.
            </p>

            <p style="margin:16px 0 0 0;">
              Atentamente,<br>
              <strong>Equipo Académico New Hope School</strong>
            </p>
          </td>
        </tr>

        <!-- Pie -->
        <tr>
          <td style="padding-top:24px;border-top:1px solid #e5e7eb;font-size:11px;color:#9ca3af;text-align:center;">
            <p style="margin:0;">
              Este es un mensaje automático, por favor no responder a este correo.
            </p>
          </td>
        </tr>
      </table>
    </td>
  </tr>
</table>
';

$mail->AltBody = 
    "Estimado(a) {$nombreUsuario},\n\n"
  . "Hemos recibido una solicitud para restablecer la contraseña de su cuenta en la Plataforma New Hope School.\n\n"
  . "Para continuar, copie y pegue el siguiente enlace en su navegador (válido por 1 hora):\n"
  . "{$resetLink}\n\n"
  . "Si usted no solicitó este cambio, puede ignorar este mensaje y su contraseña seguirá siendo la misma.\n\n"
  . "Atentamente,\n"
  . "Equipo Académico New Hope School\n";

            $mail->AltBody = "Hola {$nombreUsuario},\n\n"
                . "Has solicitado restablecer tu contraseña en la plataforma New Hope School.\n"
                . "Copia y pega este enlace en tu navegador (válido por 1 hora):\n"
                . "{$resetLink}\n\n"
                . "Si no solicitaste este cambio, puedes ignorar este correo.\n";

            $mail->send();

            echo json_encode([
                'status'     => 'success',
                'message'    => 'Si el correo existe en el sistema, se ha enviado un enlace de recuperación',
                'reset_link' => $resetLink, // para mostrar en pantalla también
            ]);
        } catch (\Throwable $e) {
            // Aquí podrías loguear $e->getMessage()
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'No se pudo enviar el correo de recuperación']);
        }
    }

    public function resetPassword(): void
    {
        $token    = trim((string) ($_POST['token'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if (!$token || !$password) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Datos incompletos']);
            return;
        }

        $userModel = new User();
        $user = $userModel->getByResetToken($token);

        if (!$user) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Enlace inválido o ya utilizado']);
            return;
        }

        // Verificar expiración
        $expiresStr = $user['reset_expires_at'] ?? null;
        if ($expiresStr) {
            $now     = new DateTime();
            $expires = new DateTime($expiresStr);
            if ($now > $expires) {
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => 'El enlace ha expirado']);
                return;
            }
        }

        if (strlen($password) < 6) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'La contraseña debe tener al menos 6 caracteres']);
            return;
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        if (!$userModel->updatePasswordById((int) $user['id'], $hash)) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'No se pudo actualizar la contraseña']);
            return;
        }

        $userModel->clearResetToken((int) $user['id']);

        echo json_encode(['status' => 'success', 'message' => 'Contraseña restablecida correctamente']);
    }

    public function changePassword(): void
    {
        $u = current_user();
        if (!$u) {
            http_response_code(401);
            echo json_encode(['status' => 'error', 'message' => 'No autenticado']);
            return;
        }

        $password = (string) ($_POST['password'] ?? '');
        if (strlen($password) < 6) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'La contraseña debe tener al menos 6 caracteres']);
            return;
        }

        $hash      = password_hash($password, PASSWORD_DEFAULT);
        $userModel = new User();

        if ($userModel->updatePasswordById((int) $u['id'], $hash)) {
            // Si cambió manualmente, limpiamos token por si tenía uno abierto
            $userModel->clearResetToken((int) $u['id']);
            echo json_encode(['status' => 'success', 'message' => 'Contraseña actualizada']);
        } else {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'No se pudo actualizar la contraseña']);
        }
    }
}