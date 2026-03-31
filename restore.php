<?php
require_once __DIR__ . '/app/helpers/auth.php';
$config = require __DIR__ . '/app/config/config.php';
$base_url = $config['base_url'] ?? '';
$token = $_GET['token'] ?? '';
include __DIR__ . '/components/header.php';
?>

<section class="card" style="max-width:520px; margin:40px auto;">
  <h2>Restablecer contraseña</h2>

  <?php if (empty($token)): ?>
    <div style="color:red; margin-top:12px;">
      Token no recibido. Abra nuevamente el enlace que llegó al correo.
    </div>
  <?php else: ?>
    <form id="formReset">
      <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

      <div class="field">
        <label for="password">Nueva contraseña</label>
        <input id="password" name="password" type="password" required minlength="6">
      </div>

      <div class="field">
        <label for="confirm_password">Confirmar contraseña</label>
        <input id="confirm_password" name="confirm_password" type="password" required minlength="6">
      </div>

      <button class="btn" type="submit">Guardar nueva contraseña</button>
      <div id="msg" style="margin-top:12px;"></div>
    </form>
  <?php endif; ?>
</section>

<script>
const formReset = document.getElementById('formReset');

if (formReset) {
  formReset.addEventListener('submit', async (e) => {
    e.preventDefault();

    const fd = new FormData(formReset);
    const msg = document.getElementById('msg');
    const password = fd.get('password');
    const confirmPassword = fd.get('confirm_password');

    msg.textContent = '';

    if (password !== confirmPassword) {
      msg.textContent = 'Las contraseñas no coinciden';
      msg.style.color = 'red';
      return;
    }

    try {
      const res = await api('reset_password', {
        data: {
          token: (fd.get('token') || '').toString(),
          password: (fd.get('password') || '').toString(),
          confirm_password: (fd.get('confirm_password') || '').toString()
        }
      });
      msg.textContent = res.message || 'Contraseña actualizada correctamente';
      msg.style.color = 'green';

      setTimeout(() => {
        window.location.href = window.__BASE_URL__ + '/login.php';
      }, 2000);
    } catch (err) {
      msg.textContent = err?.json?.detail || err?.json?.message || 'No se pudo restablecer la contraseña';
      msg.style.color = 'red';
      console.error(err);
    }
  });
}
</script>

<?php include __DIR__ . '/components/footer.php'; ?>