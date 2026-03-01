<?php
require_once __DIR__.'/app/helpers/auth.php';
if(!current_user()){ header('Location: login.php'); exit; }
include __DIR__.'/components/header.php';
?>

<section class="card">
<h2>Cambiar contraseña</h2>

<form id="formChange">
  <input name="password" type="password" placeholder="Nueva contraseña" required>
  <button class="btn">Cambiar</button>
</form>

<div id="msg"></div>
</section>

<script>
document.getElementById('formChange').addEventListener('submit', async (e)=>{
  e.preventDefault();
  const fd = new FormData(e.target);
  const msg = document.getElementById('msg');
  try{
    await api('change_password',{data:fd,isForm:true});
    msg.textContent="Contraseña actualizada";
  }catch(err){
    msg.textContent="Error";
  }
});
</script>

<?php include __DIR__.'/components/footer.php'; ?>