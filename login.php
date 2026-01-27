<?php
require_once __DIR__ . '/app/helpers/auth.php';
$config = require __DIR__ . '/app/config/config.php';
$base_url = $config['base_url'] ?? '';
if (current_user()) {
  header('Location: ' . $base_url . '/dashboard.php');
  exit;
}
include __DIR__ . '/components/header.php';
?>
<section class="card">
  <h2>Iniciar sesión</h2>
  <form id="formLogin" class="grid">
    <label>Usuario<input name="username" required /></label>
    <label>Contraseña<input name="password" type="password" required /></label>
    <button class="btn" type="submit">Entrar</button>
    <div id="msg" class="muted"></div>
  </form>
</section>
<script>
document.getElementById('formLogin').addEventListener('submit', async (e) => {
  e.preventDefault();
  const fd = new FormData(e.target);
  const msg = document.getElementById('msg');
  msg.textContent = '';
  try {
    await api('login', { data: fd, isForm: true });
    location.href = window.__BASE_URL__ + '/dashboard.php';
  } catch (err) {
    msg.textContent = (err?.json?.message) || 'No se pudo iniciar sesión';
  }
});
</script>
<?php include __DIR__ . '/components/footer.php'; ?>
