<?php
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../services/OneDriveService.php';

class MicrosoftOAuthController {

  // Solo ADMIN para conectar la cuenta OneDrive del sistema
  public function connect(): void {
    require_login();
    require_role(['ADMIN']);

    $svc = new OneDriveService();
    header('Location: ' . $svc->getAuthorizeUrl());
    exit;
  }

  public function callback(): void {
    // Puede ser sin login si Microsoft vuelve directo; pero lo normal es mantener sesión
    // Igual puedes dejarlo libre si lo prefieres.
    $code = $_GET['code'] ?? '';
    if (!$code) {
      http_response_code(400);
      echo "Falta code";
      return;
    }

    try {
      $svc = new OneDriveService();
      $svc->exchangeCodeForTokens($code);

      // Redirige a algún panel
      $config = require __DIR__ . '/../config/config.php';
      $base = $config['base_url'] ?? '/new_hope_platform';
      header("Location: {$base}/dashboard.php?onedrive=connected");
      exit;
    } catch (Throwable $e) {
      http_response_code(500);
      echo "Error OAuth: " . $e->getMessage();
    }
  }
}