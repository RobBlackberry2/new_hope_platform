<?php
require_once __DIR__ . '/app/helpers/auth.php';
$config = require __DIR__ . '/app/config/config.php';
$base_url = $config['base_url'] ?? '';
include __DIR__ . '/components/header.php';
?>

<section class="card" style="max-width:520px; margin:40px auto;">
  <h2>Recuperar contraseña</h2>
  <p>Digite su correo para enviarle un enlace de restablecimiento.</p>

  <form id="formRecover">
    <div class="field">
      <label for="correo">Correo electrónico</label>
      <input id="correo" name="correo" type="email" required>
    </div>

    <button class="btn" type="submit">Enviar correo</button>
    <div id="msg" style="margin-top:12px;"></div>
  </form>
</section>

<script>
document.getElementById('formRecover').addEventListener('submit', async (e) => {
  e.preventDefault();

  const fd = new FormData(e.target);
  const msg = document.getElementById('msg');
  msg.textContent = '';

  try {
    const res = await api('forgot_password', { data: fd, isForm: true });
    msg.textContent = res.message || 'Correo enviado correctamente';
    msg.style.color = 'green';
  } catch (err) {
    msg.textContent = err?.json?.detail || err?.json?.message || 'No se pudo enviar el correo';
    msg.style.color = 'red';
    console.error(err);
  }
});
</script>

<?php include __DIR__ . '/components/footer.php'; ?>