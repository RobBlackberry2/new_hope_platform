<?php
require_once __DIR__ . '/app/helpers/auth.php';
$config = require __DIR__ . '/app/config/config.php';
$base_url = $config['base_url'] ?? '';
$u = current_user();
if (!$u) { header('Location: ' . $base_url . '/login.php'); exit; }
include __DIR__ . '/components/header.php';
?>
<section class="card">
  <h2>Mensajes</h2>
  <p class="muted">Bandeja de entrada y enviados.</p>
</section>

<section class="card">
  <h3>Enviar</h3>
  <form id="formSend" class="grid2">

    <label>Enviar a usuario
      <select name="to_user_id" id="selectUser">
        <option value="">Cargando usuarios...</option>
      </select>
    </label>

    <label>O para rol
      <select name="to_role">
        <option value="">(Opcional)</option>
        <option>ADMIN</option>
        <option>DOCENTE</option>
        <option>ESTUDIANTE</option>
      </select>
    </label>

    <label>Asunto<input name="asunto" required /></label>
    <label>Cuerpo<textarea name="cuerpo" required rows="4"></textarea></label>

    <button class="btn" type="submit">Enviar</button>
    <div id="msgSend" class="muted"></div>
  </form>
</section>

<section class="grid2">
  <div class="card">
    <h3>Entrada</h3>
    <div id="inbox" class="muted">Cargando...</div>
  </div>
  <div class="card">
    <h3>Enviados</h3>
    <div id="sent" class="muted">Cargando...</div>
  </div>
</section>

<script>

/* =========================
   CARGAR USUARIOS AL SELECT
========================= */
async function cargarUsuariosMensaje(){
  const select = document.getElementById('selectUser');
  if(!select) return;

  try{
    const j = await api('users_list_for_students', { method:'GET', params:{ limit:500 } });
    const users = j.data || [];

    if(users.length === 0){
      select.innerHTML = '<option value="">No hay usuarios</option>';
      return;
    }

    select.innerHTML = '<option value="">Seleccione un usuario</option>';

    for(const u of users){
      const option = document.createElement('option');
      option.value = u.id;
      option.textContent = `${u.nombre} (@${u.username})`;
      select.appendChild(option);
    }

  }catch(e){
    select.innerHTML = '<option value="">Error cargando usuarios</option>';
  }
}

/* =========================
   RENDER MENSAJES
========================= */
function renderMessages(list){
  if (!list || !list.length) return '<div class="muted">Sin mensajes.</div>';
  return list.slice(0,20).map(m=>`<div class="card" style="margin:8px 0;">
    <div><strong>${m.asunto}</strong></div>
    <div class="muted">${m.created_at}</div>
    <div>${(m.cuerpo||'').replace(/</g,'&lt;')}</div>
  </div>`).join('');
}

/* =========================
   CARGAR BANDEJAS
========================= */
async function loadAll(){
  try {
    const i = await api('messages_inbox', { method:'GET' });
    document.getElementById('inbox').innerHTML = renderMessages(i.data);
  } catch (err){
    document.getElementById('inbox').textContent = err?.json?.message || 'Error';
  }

  try {
    const s = await api('messages_sent', { method:'GET' });
    document.getElementById('sent').innerHTML = renderMessages(s.data);
  } catch (err){
    document.getElementById('sent').textContent = err?.json?.message || 'Error';
  }
}

/* =========================
   ENVIAR MENSAJE
========================= */
document.getElementById('formSend').addEventListener('submit', async (e)=>{
  e.preventDefault();
  const fd = new FormData(e.target);
  const msg = document.getElementById('msgSend');
  msg.textContent='';

  // si selecciona usuario -> borra rol
  if (fd.get('to_user_id')) fd.set('to_role','');

  // si selecciona rol -> borra usuario
  if (fd.get('to_role')) fd.set('to_user_id','');

  try {
    await api('messages_send', { data: fd, isForm:true });
    msg.textContent='Enviado.';
    e.target.reset();
    await cargarUsuariosMensaje();
    await loadAll();
  } catch (err){
    msg.textContent = err?.json?.message || 'Error enviando';
  }
});

/* =========================
   INIT
========================= */
(async ()=>{
  await cargarUsuariosMensaje();
  await loadAll();
})();

</script>

<?php include __DIR__ . '/components/footer.php'; ?>