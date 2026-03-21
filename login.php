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

<style>
  .login-center-wrap {
    min-height: 60vh;
    display: flex;
    justify-content: center;
    align-items: center;
    width: 100%;
  }

  .login-center-form {
    width: 100%;
    max-width: 420px;
    margin: 0 auto;
  }

  .login-center-form .field {
    margin-bottom: 12px;
  }

  .login-center-form label {
    display: block;
    margin-bottom: 6px;
    text-align: left;
  }

  .login-center-form input {
    width: 100%;
    box-sizing: border-box;
  }

  .login-center-form .btn-row {
    display: flex;
    justify-content: center;
    margin-top: 10px;
  }

  .login-center-form #msg {
    text-align: center;
    margin-top: 10px;
  }
</style>

<section class="card">
  <div class="login-center-wrap">
    <form id="formLogin" class="login-center-form">
      <h2 style="text-align:center; margin-bottom:16px;">Iniciar sesión</h2>

      <div class="field">
        <label for="username">Usuario</label>
        <input id="username" name="username" required />
      </div>

      <div class="field">
        <label for="password">Contraseña</label>
        <input id="password" name="password" type="password" required />
      </div>

      <div class="btn-row">
        <button class="btn" type="submit">Entrar</button>
      </div>

      <div id="msg" class="muted"></div>
    </form>
  </div>
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