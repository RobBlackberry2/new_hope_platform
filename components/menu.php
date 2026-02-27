<?php
// components/menu.php
require_once __DIR__ . '/../app/helpers/auth.php';
$config = require __DIR__ . '/../app/config/config.php';
$base_url = $config['base_url'] ?? '';
$u = current_user();
$rol = $u['rol'] ?? '';
$current = basename(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '');
if ($current === '') { $current = 'dashboard.php'; }
$navClass = function(string $file) use ($current) {
  return $current === $file ? ' class="active"' : '';
};
?>
<nav class="menu">
  <a<?= $navClass('dashboard.php') ?> href="<?= $base_url ?>/dashboard.php">Inicio</a>

  <?php if ($rol === 'ADMIN'): ?>
    <a<?= $navClass('enrollments.php') ?> href="<?= $base_url ?>/enrollments.php">Matrículas</a>
    <a href="#" onclick="return false;">Reportes</a>
    <a<?= $navClass('users.php') ?> href="<?= $base_url ?>/users.php">Gestión de usuarios</a>
  <?php endif; ?>

  <a<?= $navClass('elearning.php') ?> href="<?= $base_url ?>/elearning.php">E-Learning</a>
  <a<?= $navClass('inbox.php') ?> href="<?= $base_url ?>/inbox.php">Bandeja de mensajes</a>
</nav>
