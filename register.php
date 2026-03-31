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
  .auth-center-wrap {
    min-height: 60vh;
    display: flex;
    justify-content: center;
    align-items: center;
    width: 100%;
  }

  .auth-center-form {
    width: 100%;
    max-width: 520px; /* un poco más ancho que login por más campos */
    margin: 0 auto;
  }

  .auth-center-form .field {
    margin-bottom: 12px;
  }

  .auth-center-form label {
    display: block;
    margin-bottom: 6px;
    text-align: left;
  }

  .auth-center-form input {
    width: 100%;
    box-sizing: border-box;
  }

  .auth-center-form .btn-row {
    display: flex;
    justify-content: center;
    margin-top: 12px;
  }

  .auth-center-form #msgCreate {
    text-align: center;
    margin-top: 10px;
  }
</style>

<section class="card">
  <div class="auth-center-wrap">
    <form id="formCreate" class="auth-center-form">
      <h3 style="text-align:center; margin-bottom:16px;">Crear usuario</h3>

      <div class="field">
        <label for="username">Username</label>
        <input id="username" name="username" required />
      </div>

      <div class="field">
        <label for="password">Contraseña</label>
        <input id="password" name="password" type="password" required />
      </div>

      <div class="field">
        <label for="nombre">Nombre</label>
        <input id="nombre" name="nombre" required />
      </div>

      <div class="field">
        <label for="correo">Correo</label>
        <input id="correo" name="correo" type="email" required />
      </div>

      <div class="field">
        <label for="telefono">Teléfono</label>
        <input id="telefono" name="telefono" />
      </div>

      <div class="btn-row">
        <button class="btn" type="submit">Crear</button>
      </div>

      <div id="msgCreate" class="muted"></div>
    </form>
  </div>
</section>

<script>
document.getElementById('formCreate').addEventListener('submit', async (e) => {
  e.preventDefault();
  const form = e.target;
  const fd = new FormData(form);
  const msgCreate = document.getElementById('msgCreate');
  msgCreate.textContent = '';

  try {
    await api('register', {
      data: {
        username: (fd.get('username') || '').toString().trim(),
        password: (fd.get('password') || '').toString(),
        nombre: (fd.get('nombre') || '').toString().trim(),
        correo: (fd.get('correo') || '').toString().trim(),
        telefono: (fd.get('telefono') || '').toString().trim()
      }
    });
    msgCreate.textContent = 'Usuario creado. Ahora inicia sesión.';
    e.target.reset();

    setTimeout(() => {
      window.location.href = "<?= $base_url ?>/login.php";
    }, 800);

  } catch (err) {
    msgCreate.textContent = err?.json?.message || 'Error creando usuario';
  }
});
</script>

<?php include __DIR__ . '/components/footer.php'; ?>