<?php
// components/menu.php
require_once __DIR__ . '/../app/helpers/auth.php';
$config = require __DIR__ . '/../app/config/config.php';
$base_url = $config['base_url'] ?? '';
$u = current_user();
$rol = $u['rol'] ?? '';
?>
<nav class="menu">
  <a href="<?= $base_url ?>/dashboard.php">Inicio</a>

  <?php if ($rol === 'ADMIN'): ?>
    <a href="<?= $base_url ?>/enrollments.php">Matrículas</a>
    <a href="<?= $base_url ?>/reports.php">Reportes</a>
    <a href="<?= $base_url ?>/users.php">Gestión de usuarios</a>
  <?php endif; ?>

  <a href="<?= $base_url ?>/elearning.php">E-Learning</a>
  <a href="<?= $base_url ?>/inbox.php">Bandeja de mensajes</a>

</nav>