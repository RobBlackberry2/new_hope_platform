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
  <h3>Crear usuario</h3>
  <form id="formCreate" class="grid2">
    <label>Username<input name="username" required /></label>
    <label>Contraseña<input name="password" type="password" required /></label>
    <label>Nombre<input name="nombre" required /></label>
    <label>Correo<input name="correo" type="email" required /></label>
    <label>Teléfono<input name="telefono" /></label>
    <button class="btn" type="submit">Crear</button>
    <div id="msgCreate" class="muted"></div>
  </form>
</section>
<script>
document.getElementById('formCreate').addEventListener('submit', async (e)=>{
  e.preventDefault();
  const fd = new FormData(e.target);
  const msgCreate = document.getElementById('msgCreate');
  msgCreate.textContent = '';
  try {
    await api('register', { data: fd, isForm:true }); // <-- aquí el cambio
    msgCreate.textContent = 'Usuario creado. Ahora inicia sesión.';
    e.target.reset();

    //Redirigir al login automáticamente
    setTimeout(()=> window.location.href = "<?= $base_url ?>/login.php", 800);

  } catch (err) {
    msgCreate.textContent = err?.json?.message || 'Error creando usuario';
  }
});
</script>
<?php include __DIR__ . '/components/footer.php'; ?>
