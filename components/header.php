<?php
require_once __DIR__ . '/../app/helpers/auth.php';
$config = require __DIR__ . '/../app/config/config.php';
$base_url = $config['base_url'] ?? '';
$u = current_user();
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>New Hope School | Plataforma</title>
  <link rel="stylesheet" href="<?= $base_url ?>/css/app.css" />
  <script>window.__BASE_URL__ = "<?= ($base_url ?? '') ?>";</script>
  <!-- app.js define api(), logout, etc. Cargado sin defer para poder usarlo en scripts inline -->
  <script src="<?= ($base_url ?? '') ?>/js/app.js"></script>
</head>
<body>
<div class="container">
  <header class="topbar">
    <div class="brand">New Hope Platform</div>
    <div class="topbar-right">
      <?php if ($u): ?>
        <span class="badge"><?= htmlspecialchars($u['rol'] ?? '') ?></span>
        <span><?= htmlspecialchars($u['nombre'] ?? $u['username'] ?? '') ?></span>
        <button class="link" id="btnLogout">Cerrar sesión</button>
      <?php else: ?>
        <a class="link" href="<?= $base_url ?>/index.php">Inicio</a>
        <a class="link" href="<?= $base_url ?>/login.php">Iniciar sesión</a>
        <a class="link" href="<?= $base_url ?>/register.php">Registrarse</a>
      <?php endif; ?>
    </div>
  </header>

  <?php if ($u): ?>
    <?php include __DIR__ . '/menu.php'; ?>
  <?php endif; ?>

  <main class="main">
