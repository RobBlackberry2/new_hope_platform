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
    <label>Para usuario
  <select name="to_user_id" id="to_user_id_select">
    <option value="">(Seleccione usuario activo)</option>
  </select>
</label>
    <label>Para rol
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
function escapeHtml(v){
  return String(v || '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}

function renderMessages(list, boxType){
  if (!list || !list.length) return '<div class="muted">Sin mensajes.</div>';
  return list.slice(0,20).map(m=>`<div class="card card-compact">
    <div style="display:flex; justify-content:space-between; gap:12px; align-items:flex-start; margin-bottom:8px;">
      <div style="flex:1; min-width:0;">
        <div><strong>${escapeHtml(m.asunto)}</strong></div>
        <div class="muted">${escapeHtml(m.created_at)}</div>
      </div>
      <button class="btn danger btn-sm" data-kind="deleteMessage" data-box="${boxType}" data-id="${m.id}">Eliminar</button>
    </div>
    <div>${escapeHtml(m.cuerpo)}</div>
  </div>`).join('');
}

let ACTIVE_USERS = [];

function userOptionLabel(u) {
  const rol = u.rol ? ` [${u.rol}]` : '';
  return `${u.nombre || u.username} (@${u.username})${rol}`;
}

async function loadActiveUsersForInbox() {
  const sel = document.getElementById('to_user_id_select');
  if (!sel) return;

  sel.innerHTML = `<option value="">Cargando usuarios activos...</option>`;

  try {
    const j = await api('users_list_active', { method: 'GET', params: { limit: 1000 } });
    ACTIVE_USERS = j.data || [];

    const opts = ['<option value="">(Seleccione usuario activo)</option>'];
    for (const u of ACTIVE_USERS) {
      opts.push(`<option value="${u.id}">${userOptionLabel(u).replace(/</g, '&lt;')}</option>`);
    }
    sel.innerHTML = opts.join('');
  } catch (err) {
    sel.innerHTML = `<option value="">Error cargando usuarios</option>`;
  }
}

async function loadAll(){
  try {
    const i = await api('messages_inbox', { method:'GET' });
    document.getElementById('inbox').innerHTML = renderMessages(i.data, 'inbox');
  } catch (err){
    document.getElementById('inbox').textContent = err?.json?.message || 'Error';
  }
  try {
    const s = await api('messages_sent', { method:'GET' });
    document.getElementById('sent').innerHTML = renderMessages(s.data, 'sent');
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



document.addEventListener('click', async (e) => {
  const btn = e.target.closest('[data-kind="deleteMessage"]');
  if (!btn) return;

  const id = btn.getAttribute('data-id');
  if (!id) return;
  if (!confirm('¿Desea eliminar este mensaje?')) return;

  const originalText = btn.textContent;
  btn.disabled = true;
  btn.textContent = 'Eliminando...';

  try {
    const fd = new FormData();
    fd.append('id', id);
    await api('messages_delete', { data: fd, isForm: true });
    await loadAll();
  } catch (err) {
    alert(err?.json?.message || 'No se pudo eliminar el mensaje');
    btn.disabled = false;
    btn.textContent = originalText;
  }
});

(async () => {
  await loadActiveUsersForInbox();
  await loadAll();
})();
</script>

<?php include __DIR__ . '/components/footer.php'; ?>
