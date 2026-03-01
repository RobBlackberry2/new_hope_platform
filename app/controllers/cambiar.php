<?php
require_once __DIR__ . '/app/helpers/auth.php';
$config   = require __DIR__ . '/app/config/config.php';
$base_url = $config['base_url'] ?? '';

// Si no hay sesión, redirigir al login
if (!current_user()) {
    header('Location: ' . $base_url . '/login.php');
    exit;
}

include __DIR__ . '/components/header.php';
?>

<section class="card">
  <h2>Cambiar contraseña</h2>

  <form id="formChangePassword" style="max-width:400px;margin:0 auto;">
    <div class="field">
      <label for="current_password">Contraseña actual</label>
      <input
        type="password"
        id="current_password"
        name="current_password"
        required
      />
    </div>

    <div class="field">
      <label for="password">Nueva contraseña</label>
      <input
        type="password"
        id="password"
        name="password"
        required
      />
    </div>

    <div class="field">
      <label for="password_confirm">Confirmar nueva contraseña</label>
      <input
        type="password"
        id="password_confirm"
        name="password_confirm"
        required
      />
    </div>

    <div style="text-align:center;margin-top:12px;">
      <button type="submit" class="btn">Guardar cambios</button>
    </div>

    <div id="msg" style="margin-top:10px;text-align:center;"></div>
  </form>
</section>

<script>
document.getElementById('formChangePassword').addEventListener('submit', async (e) => {
  e.preventDefault();

  const form    = e.target;
  const fd      = new FormData(form);
  const msgElem = document.getElementById('msg');

  msgElem.textContent = '';

  const newPass     = fd.get('password');
  const newPassConf = fd.get('password_confirm');

  if (newPass !== newPassConf) {
    msgElem.style.color = 'red';
    msgElem.textContent = 'Las contraseñas nuevas no coinciden';
    return;
  }

  if (String(newPass).length < 6) {
    msgElem.style.color = 'red';
    msgElem.textContent = 'La nueva contraseña debe tener al menos 6 caracteres';
    return;
  }

  try {
    const res = await api('change_password', { data: fd, isForm: true });
    if (res.status === 'success') {
      msgElem.style.color = 'green';
      msgElem.textContent = res.message || 'Contraseña actualizada correctamente';
      form.reset();
    } else {
      msgElem.style.color = 'red';
      msgElem.textContent = res.message || 'No se pudo actualizar la contraseña';
    }
  } catch (err) {
    msgElem.style.color = 'red';
    msgElem.textContent = (err?.json?.message) || 'Error al actualizar la contraseña';
  }
});
</script>

<?php include __DIR__ . '/components/footer.php'; ?>