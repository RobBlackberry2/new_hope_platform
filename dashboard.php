<?php
require_once __DIR__ . '/app/helpers/auth.php';
$config = require __DIR__ . '/app/config/config.php';
$base_url = $config['base_url'] ?? '';
$u = current_user();
if (!$u) { header('Location: ' . $base_url . '/login.php'); exit; }
include __DIR__ . '/components/header.php';
$rol = $u['rol'] ?? '';
?>
<section class="grid2">
  <div class="card">
    <h2>Bienvenido, <?= htmlspecialchars($u['nombre'] ?? $u['username'] ?? '') ?></h2>
    <p class="muted">Rol: <strong><?= htmlspecialchars($rol) ?></strong></p>
    <p class="muted">Página principal</p>
  </div>
  <div class="card">
    <h3>Reportes</h3>
    <div class="muted">Seccion destinada para los Reportes</div>
  </div>
</section>

<?php if ($rol === 'ADMIN'): ?>
<section class="grid3">
  <a class="card" href="<?= $base_url ?>/users.php"><h3>Gestión de usuarios</h3><p class="muted">Administrador de usuarios, roles y estados.</p></a>
  <a class="card" href="<?= $base_url ?>/enrollments.php"><h3>Matrículas</h3><p class="muted">Administrador de estudiantes y matrículas.</p></a>
  <a class="card" href="<?= $base_url ?>/inbox.php"><h3>Mensajería</h3><p class="muted">Enviar comunicados por rol o usuario.</p></a>
</section>
<?php endif; ?>

<section class="card">
  <h3>E-Learning</h3>
  <p class="muted">Cursos, secciones, archivos (subidas). Más adelante: evaluaciones, foros y gamificación.</p>
  <a class="btn" href="<?= $base_url ?>/elearning.php">Ir a E-Learning</a>
</section>

<?php include __DIR__ . '/components/footer.php'; ?>
