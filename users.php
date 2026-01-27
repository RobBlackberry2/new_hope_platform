<?php
require_once __DIR__ . '/app/helpers/auth.php';
$config = require __DIR__ . '/app/config/config.php';
$base_url = $config['base_url'] ?? '';
$u = current_user();
if (!$u) { header('Location: ' . $base_url . '/login.php'); exit; }
if (($u['rol'] ?? '') !== 'ADMIN') { http_response_code(403); die('Sin permisos'); }
include __DIR__ . '/components/header.php';
?>
<section class="card">
  <h2>Gestión de usuarios (ADMIN)</h2>
  <p class="muted">CRUD básico: crear usuarios, cambiar rol y estado.</p>
</section>

<section class="card">
  <h3>Crear usuario</h3>
  <form id="formCreate" class="grid2">
    <label>Username<input name="username" required /></label>
    <label>Contraseña<input name="password" type="password" required /></label>
    <label>Nombre<input name="nombre" required /></label>
    <label>Correo<input name="correo" type="email" required /></label>
    <label>Teléfono<input name="telefono" /></label>
    <label>Rol
      <select name="rol">
        <option>ADMIN</option>
        <option>DOCENTE</option>
        <option selected>ESTUDIANTE</option>
      </select>
    </label>
    <button class="btn" type="submit">Crear</button>
    <div id="msgCreate" class="muted"></div>
  </form>
</section>

<section class="card">
  <h3>Usuarios</h3>
  <div class="muted" id="msg"></div>
  <div style="overflow:auto;">
    <table class="table" id="tbl"></table>
  </div>
</section>

<script>
const tbl = document.getElementById('tbl');
const msg = document.getElementById('msg');

function row(u){
  const rolOptions = ['ADMIN','DOCENTE','ESTUDIANTE'].map(r=>`<option ${u.rol===r?'selected':''}>${r}</option>`).join('');
  const estadoOptions = ['ACTIVO','INACTIVO'].map(s=>`<option ${u.estado===s?'selected':''}>${s}</option>`).join('');
  return `<tr>
    <td>${u.id}</td>
    <td>${u.username}</td>
    <td>${u.nombre||''}</td>
    <td>${u.correo||''}</td>
    <td>${u.telefono||''}</td>
    <td><select data-id="${u.id}" data-kind="rol">${rolOptions}</select></td>
    <td><select data-id="${u.id}" data-kind="estado">${estadoOptions}</select></td>
    <td><button class="btn danger" data-id="${u.id}" data-kind="del">Eliminar</button></td>
  </tr>`;
}

async function load(){
  msg.textContent = 'Cargando...';
  try {
    const j = await api('users_list', { method:'GET', params:{limit:200} });
    const data = j.data||[];
    tbl.innerHTML = `<tr><th>Id</th><th>Username</th><th>Nombre</th><th>Correo</th><th>Teléfono</th><th>Rol</th><th>Estado</th><th></th></tr>` + data.map(row).join('');
    msg.textContent = '';
  } catch (err) {
    msg.textContent = err?.json?.message || 'Error cargando usuarios';
  }
}

document.getElementById('formCreate').addEventListener('submit', async (e)=>{
  e.preventDefault();
  const fd = new FormData(e.target);
  const msgCreate = document.getElementById('msgCreate');
  msgCreate.textContent = '';
  try {
    await api('users_create', { data: fd, isForm:true });
    msgCreate.textContent = 'Usuario creado.';
    e.target.reset();
    await load();
  } catch (err) {
    msgCreate.textContent = err?.json?.message || 'Error creando usuario';
  }
});

// cambios en selects y botones
 tbl.addEventListener('change', async (e)=>{
   const el = e.target;
   const id = el.getAttribute('data-id');
   const kind = el.getAttribute('data-kind');
   if (!id || !kind) return;
   try {
     if (kind === 'rol') await api('users_setRole', { data: {id, rol: el.value} });
     if (kind === 'estado') await api('users_setEstado', { data: {id, estado: el.value} });
   } catch (err) {
     alert(err?.json?.message || 'Error');
   }
 });

 tbl.addEventListener('click', async (e)=>{
   const btn = e.target;
   if (btn.getAttribute('data-kind') !== 'del') return;
   const id = btn.getAttribute('data-id');
   if (!confirm('¿Eliminar usuario #' + id + '?')) return;
   try { await api('users_delete', { data: {id} }); await load(); }
   catch (err) { alert(err?.json?.message || 'Error'); }
 });

load();
</script>

<?php include __DIR__ . '/components/footer.php'; ?>
