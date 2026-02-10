<?php
require_once __DIR__ . '/app/helpers/auth.php';
$config = require __DIR__ . '/app/config/config.php';
$base_url = $config['base_url'] ?? '';

$user = current_user();
if (!$user) {
  header('location: ' . $base_url . '/login.php');
  exit;
}

if (($user['rol'] ?? '') !== 'ADMIN') {
  http_response_code(403);
  die('sin permisos');
}

include __DIR__ . '/components/header.php';
?>

<style>
.table th,
.table td {
  text-align: center;
  vertical-align: middle;
}

.table select,
.table button {
  margin: auto;
}

#search {
  width: 100%;
  margin-bottom: 10px;
}

#pagination {
  margin-top: 15px;
  text-align: center;
}

#pagination button {
  margin: 0 3px;
  padding: 5px 10px;
}

#pagination button.active {
  background: #4ade80;
  color: #000;
}
</style>

<section class="card">
  <h2>gestión de usuarios (admin)</h2>
  <p class="muted">administración básica de usuarios.</p>
</section>

<section class="card">
  <h3>crear usuario</h3>
  <form id="formCreate" class="grid2">
    <label>username<input name="username" required></label>
    <label>contraseña<input name="password" type="password" required></label>
    <label>nombre<input name="nombre" required></label>
    <label>correo<input name="correo" type="email" required></label>
    <label>teléfono<input name="telefono"></label>
    <label>rol
      <select name="rol">
        <option>ADMIN</option>
        <option>DOCENTE</option>
        <option selected>ESTUDIANTE</option>
      </select>
    </label>
    <button class="btn" type="submit">crear</button>
    <div id="msgCreate" class="muted"></div>
  </form>
</section>

<section class="card">
  <h3>usuarios</h3>

  <input type="text" id="search" placeholder="buscar usuario...">

  <div class="muted" id="msg"></div>

  <div style="overflow:auto">
    <table class="table" id="tbl"></table>
  </div>

  <div id="pagination"></div>
</section>

<script>
const tbl = document.getElementById('tbl');
const msg = document.getElementById('msg');
const search = document.getElementById('search');
const pagination = document.getElementById('pagination');

let data = [];
let page = 1;
const perPage = 5;

function makeRow(u) {
  return `
    <tr>
      <td>${u.id}</td>
      <td>${u.username}</td>
      <td>${u.nombre || ''}</td>
      <td>${u.correo || ''}</td>
      <td>${u.telefono || ''}</td>
      <td>
        <select data-id="${u.id}" data-type="rol">
          <option ${u.rol === 'ADMIN' ? 'selected' : ''}>ADMIN</option>
          <option ${u.rol === 'DOCENTE' ? 'selected' : ''}>DOCENTE</option>
          <option ${u.rol === 'ESTUDIANTE' ? 'selected' : ''}>ESTUDIANTE</option>
        </select>
      </td>
      <td>
        <select data-id="${u.id}" data-type="estado">
          <option ${u.estado === 'ACTIVO' ? 'selected' : ''}>ACTIVO</option>
          <option ${u.estado === 'INACTIVO' ? 'selected' : ''}>INACTIVO</option>
        </select>
      </td>
      <td>
        <button class="btn danger" data-id="${u.id}" data-type="del">eliminar</button>
      </td>
    </tr>
  `;
}

function drawTable(list) {
  const start = (page - 1) * perPage;
  const end = start + perPage;

  tbl.innerHTML = `
    <tr>
      <th>id</th>
      <th>username</th>
      <th>nombre</th>
      <th>correo</th>
      <th>teléfono</th>
      <th>rol</th>
      <th>estado</th>
      <th></th>
    </tr>
  `;

  list.slice(start, end).forEach(u => {
    tbl.innerHTML += makeRow(u);
  });

  drawPagination(list.length);
}

function drawPagination(total) {
  pagination.innerHTML = '';
  const pages = Math.ceil(total / perPage);

  for (let i = 1; i <= pages; i++) {
    const b = document.createElement('button');
    b.textContent = i;
    if (i === page) b.classList.add('active');
    b.onclick = () => {
      page = i;
      filter();
    };
    pagination.appendChild(b);
  }
}

function filter() {
  const q = search.value.toLowerCase();
  const filtered = data.filter(u =>
    u.username.toLowerCase().includes(q) ||
    (u.nombre || '').toLowerCase().includes(q) ||
    (u.correo || '').toLowerCase().includes(q)
  );
  drawTable(filtered);
}

search.addEventListener('input', () => {
  page = 1;
  filter();
});

async function loadUsers() {
  msg.textContent = 'cargando...';
  try {
    const r = await api('users_list', { method: 'GET', params: { limit: 200 } });
    data = r.data || [];
    page = 1;
    filter();
    msg.textContent = '';
  } catch {
    msg.textContent = 'error cargando usuarios';
  }
}

document.getElementById('formCreate').addEventListener('submit', async e => {
  e.preventDefault();
  const fd = new FormData(e.target);
  const out = document.getElementById('msgCreate');
  out.textContent = '';
  try {
    await api('users_create', { data: fd, isForm: true });
    out.textContent = 'usuario creado';
    e.target.reset();
    loadUsers();
  } catch {
    out.textContent = 'error al crear usuario';
  }
});

tbl.addEventListener('change', async e => {
  const id = e.target.dataset.id;
  const type = e.target.dataset.type;
  if (!id) return;

  try {
    if (type === 'rol') {
      await api('users_setRole', { data: { id, rol: e.target.value } });
    }
    if (type === 'estado') {
      await api('users_setEstado', { data: { id, estado: e.target.value } });
    }
  } catch {
    alert('error');
  }
});

tbl.addEventListener('click', async e => {
  if (e.target.dataset.type !== 'del') return;
  const id = e.target.dataset.id;
  if (!confirm('¿eliminar usuario #' + id + '?')) return;

  try {
    await api('users_delete', { data: { id } });
    loadUsers();
  } catch {
    alert('error');
  }
});

loadUsers();
</script>

<?php include __DIR__ . '/components/footer.php'; ?>
