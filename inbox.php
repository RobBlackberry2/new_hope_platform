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
    <label>Para usuario ID<input name="to_user_id" type="number" min="1" /></label>
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
function renderMessages(list){
  if (!list || !list.length) return '<div class="muted">Sin mensajes.</div>';
  return list.slice(0,20).map(m=>`<div class="card" style="margin:8px 0;">
    <div><strong>${m.asunto}</strong></div>
    <div class="muted">${m.created_at}</div>
    <div>${(m.cuerpo||'').replace(/</g,'&lt;')}</div>
  </div>`).join('');
}

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

document.getElementById('formSend').addEventListener('submit', async (e)=>{
  e.preventDefault();
  const fd = new FormData(e.target);
  const msg = document.getElementById('msgSend');
  msg.textContent='';
  if (fd.get('to_user_id')) fd.set('to_role','');
  try {
    await api('messages_send', { data: fd, isForm:true });
    msg.textContent='Enviado.';
    e.target.reset();
    await loadAll();
  } catch (err){
    msg.textContent = err?.json?.message || 'Error enviando';
  }
});

loadAll();
</script>

<?php include __DIR__ . '/components/footer.php'; ?>
