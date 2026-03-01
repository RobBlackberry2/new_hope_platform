<?php include __DIR__.'/components/header.php'; ?>

<section class="card">
<h2>Recuperar contraseña</h2>

<form id="formRecover">
    <input name="correo" type="email" placeholder="Correo" required>
    <button class="btn">Enviar</button>
</form>

<div id="msg"></div>
</section>

<script>
document.getElementById('formRecover').addEventListener('submit', async (e)=>{
  e.preventDefault();
  const fd  = new FormData(e.target);
  const msg = document.getElementById('msg');

  msg.innerHTML = 'Procesando...';

  try{
    const res = await api('forgot_password',{data:fd,isForm:true});

    if(res.status === 'success'){
        msg.innerHTML = `
            <p style="color:green;">${res.message}</p>
            ${res.reset_link ? `
            <p>
              <a href="${res.reset_link}" style="color:blue;font-weight:bold;">
                Haga clic aquí para restablecer su contraseña
              </a>
            </p>` : ''}
        `;
    } else {
        msg.innerHTML = `<p style="color:red;">${res.message}</p>`;
    }

  }catch(err){
    msg.innerHTML = `<p style="color:red;">Error en el servidor</p>`;
  }
});
</script>

<?php include __DIR__.'/components/footer.php'; ?>