<?php include __DIR__.'/components/header.php'; ?>

<section class="card">
<h2>Restablecer contraseña</h2>

<form id="formReset">
  <input type="hidden" name="token" value="<?php echo $_GET['token'] ?? ''; ?>">
  <input name="password" type="password" placeholder="Nueva contraseña" required>
  <button class="btn">Guardar</button>
</form>

<div id="msg"></div>
</section>

<script>
document.getElementById('formReset').addEventListener('submit', async (e)=>{
  e.preventDefault();
  const fd  = new FormData(e.target);
  const msg = document.getElementById('msg');
  msg.textContent = 'Procesando...';
  try{
    const res = await api('reset_password',{data:fd,isForm:true});
    if (res.status === 'success') {
      msg.textContent = 'Contraseña restablecida correctamente';
    } else {
      msg.textContent = res.message || 'Error al restablecer la contraseña';
    }
  }catch(err){
    msg.textContent = 'Error en el servidor';
  }
});
</script>

<?php include __DIR__.'/components/footer.php'; ?>