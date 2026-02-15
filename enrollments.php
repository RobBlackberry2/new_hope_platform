<?php
require_once __DIR__ . '/app/helpers/auth.php';
$config = require __DIR__ . '/app/config/config.php';
$base_url = $config['base_url'] ?? '';
$u = current_user();
if (!$u) {
  header('Location: ' . $base_url . '/login.php');
  exit;
}
if (($u['rol'] ?? '') !== 'ADMIN') {
  http_response_code(403);
  die('Sin permisos');
}
include __DIR__ . '/components/header.php';
?>
<section class="card">
  <h2>Administrativo: Matrículas</h2>
  <p class="muted">Página para gestionar matriculas y ver la lista de todos los estudiantes.</p>
</section>

<section class="card">
  <h3>Cupos por nivel (7° a 11°)</h3>
  <div class="muted" id="msgCupos"></div>
  <div style="overflow:auto;">
    <table class="table" id="tblCupos"></table>
  </div>
</section>

<section class="card">
  <h3>Matricular estudiante</h3>
  <form id="formStudent" class="grid2">
    <label>Nombre<input name="nombre" required /></label>
    <label>Cédula<input name="cedula" /></label>
    <label>Fecha nacimiento<input name="fecha_nacimiento" type="date" /></label>
    <label>Grado (7-11)<input name="grado" type="number" min="7" max="11" value="7" /></label>
    <label>Sección<input name="seccion" /></label>
    <label>Encargado<input name="encargado" /></label>
    <label>Teléfono encargado<input name="telefono_encargado" /></label>
    <label>Año lectivo<input name="year" type="number" value="<?= date('Y') ?>" /></label>
    <button class="btn" type="submit">Matricular</button>
    <div id="msgStudent" class="muted"></div>
  </form>
</section>

<section class="card">
  <h3>Estudiantes</h3>
  <div class="muted" id="msg"></div>
  <div style="overflow:auto;">
    <table class="table" id="tblStudents"></table>
  </div>
</section>

<section class="card">
  <h3>Matrículas</h3>
  <div class="muted" id="msgEnr"></div>
  <div style="overflow:auto;">
    <table class="table" id="tblEnr"></table>
  </div>
</section>

<div id="modalEdit" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.9); padding:20px;">
  <div class="card" style="max-width:700px; margin:40px auto;">
    <h3>Editar estudiante</h3>
    <form id="formEditStudent" class="grid2">
      <input type="hidden" name="id" />

      <label>Nombre
        <input name="nombre" disabled />
      </label>

      <label>Cédula
        <input name="cedula" disabled />
      </label>

      <label>Fecha nacimiento
        <input name="fecha_nacimiento" type="date" disabled />
      </label>

      <label>Grado (7-11)
        <input name="grado" type="number" min="7" max="11" />
      </label>

      <label>Sección
        <input name="seccion" />
      </label>

      <label>Encargado
        <input name="encargado" />
      </label>

      <label>Teléfono encargado
        <input name="telefono_encargado" />
      </label>

      <div style="display:flex; gap:10px; align-items:center;">
        <button class="btn" type="submit">Guardar</button>
        <button class="btn" type="button" id="btnCloseEdit">Cerrar</button>
        <div class="muted" id="msgEdit"></div>
      </div>
    </form>
  </div>
</div>


<script>
  const tbl = document.getElementById('tblStudents');
  const msg = document.getElementById('msg');
  const tblEnr = document.getElementById('tblEnr');
  const msgEnr = document.getElementById('msgEnr');
  const tblCupos = document.getElementById('tblCupos');
  const msgCupos = document.getElementById('msgCupos');
  let USERS = [];

  function studentRow(s) {
    return `<tr>
    <td>${s.nombre || ''}</td>
    <td>${s.cedula || ''}</td>
    <td>${s.grado || ''}</td>
    <td>${s.seccion || ''}</td>
    <td>
      <select data-kind="user_id" data-id="${s.id}">
        ${userOptionsHtml(s.user_id)}
      </select>
    </td>
    <td style="display:flex; gap:8px; flex-wrap:wrap;">
      <button class="btn" data-kind="edit" data-id="${s.id}">Editar</button>
      <button class="btn danger" data-kind="del" data-id="${s.id}">Eliminar</button>
    </td>
  </tr>`;
  }


  function enrRow(e) {
    return `<tr>
    <td>${e.student_nombre || ''}</td>
    <td>${e.grado || ''}${e.seccion ? (' - ' + e.seccion) : ''}</td>
    <td>${e.year}</td>
    <td>
      <select data-kind="estado" data-id="${e.id}">
        <option ${e.estado === 'ACTIVA' ? 'selected' : ''}>ACTIVA</option>
        <option ${e.estado === 'PENDIENTE' ? 'selected' : ''}>PENDIENTE</option>
        <option ${e.estado === 'BLOQUEADO' ? 'selected' : ''}>BLOQUEADO</option>
      </select>
    </td>
    <td><button class="btn danger" data-kind="del_enr" data-id="${e.id}">Eliminar</button></td>
  </tr>`;
  }


  async function loadStudents() {
    msg.textContent = 'Cargando...';
    try {
      const j = await api('students_list', { method: 'GET', params: { limit: 200 } });
      const data = j.data || [];
      tbl.innerHTML = `<tr><th>Nombre</th><th>Cédula</th><th>Grado</th><th>Sección</th><th>User</th><th></th></tr>` + data.map(studentRow).join('');
      msg.textContent = '';
    } catch (err) {
      msg.textContent = err?.json?.message || 'Error cargando estudiantes';
    }
  }



  async function loadUsers() {
    try {
      const j = await api('users_list_for_students', { method: 'GET', params: { limit: 500 } });
      USERS = j.data || [];
    } catch {
      USERS = [];
    }
  }

  function userOptionsHtml(selectedId) {
    const sel = selectedId ? String(selectedId) : '';
    const opts = [`<option value="">(sin usuario)</option>`];

    for (const u of USERS) {
      const label = `${u.nombre} (@${u.username}) [${u.id}]`;
      const selected = (String(u.id) === sel) ? 'selected' : '';
      opts.push(`<option value="${u.id}" ${selected}>${label}</option>`);
    }
    return opts.join('');
  }

  async function loadEnrollments() {
    msgEnr.textContent = 'Cargando...';
    try {
      const j = await api('enrollments_list', { method: 'GET', params: { limit: 200 } });
      const data = j.data || [];
      tblEnr.innerHTML = `<tr><th>Estudiante</th><th>Grado</th><th>Año</th><th>Estado</th><th></th></tr>` + data.map(enrRow).join('');
      msgEnr.textContent = '';
    } catch (err) {
      msgEnr.textContent = err?.json?.message || 'Error cargando matrículas';
    }
  }

  document.getElementById('formStudent').addEventListener('submit', async (e) => {
    e.preventDefault();
    const fd = new FormData(e.target);
    const ms = document.getElementById('msgStudent');
    ms.textContent = '';
    const year = parseInt(fd.get('year') || new Date().getFullYear(), 10);
    const grado = parseInt(fd.get('grado') || '7', 10);

    const cap = await api('enrollments_capacity', { method: 'GET', params: { year } });
    const row = (cap.data || []).find(x => Number(x.grado) === grado);
    if (row && row.available <= 0) {
      ms.textContent = `No hay cupos disponibles para grado ${grado} en ${year}.`;
      return;
    }

    try {

      const created = await api('students_create', { data: fd, isForm: true });
      const studentId = created?.id;
      if (!studentId) throw new Error('No se recibió id del estudiante');

      const year = parseInt(fd.get('year') || new Date().getFullYear(), 10);
      await api('enrollments_create', { data: { student_id: studentId, year } });

      ms.textContent = 'Estudiante creado y matriculado.';
      e.target.reset();

      await loadStudents();
      await loadEnrollments();
      await loadCapacity();
    } catch (err) {
      ms.textContent = err?.json?.message || err?.message || 'Error matriculando estudiante';
    }
  });

  tbl.addEventListener('click', async (e) => {
    const kind = e.target.getAttribute('data-kind');
    const id = e.target.getAttribute('data-id');
    if (!kind || !id) return;

    if (kind === 'del') {
      if (!confirm('¿Eliminar estudiante #' + id + '?')) return;
      try { await api('students_delete', { data: { id } }); await loadStudents(); }
      catch (err) { alert(err?.json?.message || 'Error'); }
    }

    if (kind === 'enroll') {
      const year = prompt('Año de matrícula', new Date().getFullYear());
      if (!year) return;
      try { await api('enrollments_create', { data: { student_id: id, year } }); await loadEnrollments(); }
      catch (err) { alert(err?.json?.message || 'Error'); }
    }

    if (kind === 'edit') {
      try {
        const j = await api('students_get', { method: 'GET', params: { id } });
        const s = j.data;

        const modal = document.getElementById('modalEdit');
        const f = document.getElementById('formEditStudent');
        document.getElementById('msgEdit').textContent = '';

        f.id.value = s.id;
        f.nombre.value = s.nombre || '';
        f.cedula.value = s.cedula || '';
        f.fecha_nacimiento.value = s.fecha_nacimiento || '';
        f.grado.value = s.grado || 7;
        f.seccion.value = s.seccion || '';
        f.encargado.value = s.encargado || '';
        f.telefono_encargado.value = s.telefono_encargado || '';

        modal.style.display = 'block';
      } catch (err) {
        alert(err?.json?.message || 'Error abriendo edición');
      }
    }
  });

  document.getElementById('btnCloseEdit').addEventListener('click', () => {
    document.getElementById('modalEdit').style.display = 'none';
  });

  document.getElementById('formEditStudent').addEventListener('submit', async (e) => {
    e.preventDefault();
    const f = e.target;
    const ms = document.getElementById('msgEdit');
    ms.textContent = '';

    const data = {
      id: f.id.value,
      grado: f.grado.value,
      seccion: f.seccion.value,
      encargado: f.encargado.value,
      telefono_encargado: f.telefono_encargado.value,
    };

    try {
      await api('students_update', { data });
      ms.textContent = 'Guardado.';
      await loadStudents();
      await loadEnrollments();
      await loadCapacity();
    } catch (err) {
      ms.textContent = err?.json?.message || 'Error guardando';
    }
  });


  tblEnr.addEventListener('change', async (e) => {
    const kind = e.target.getAttribute('data-kind');
    const id = e.target.getAttribute('data-id');
    if (kind !== 'estado') return;
    try {
      await api('enrollments_updateEstado', { data: { id, estado: e.target.value } });
      await loadCapacity();
      await loadUsers();
      await loadStudents();
    } catch (err) { alert(err?.json?.message || 'Error'); }
  });

  tbl.addEventListener('change', async (e) => {
    const kind = e.target.getAttribute('data-kind');
    const id = e.target.getAttribute('data-id');
    if (kind !== 'user_id') return;

    const user_id = e.target.value; // '' o un id
    try {
      await api('students_updateUserId', { data: { id, user_id } });
    } catch (err) {
      alert(err?.json?.message || 'Error actualizando user_id');
      await loadStudents();
    }
  });

  tblEnr.addEventListener('click', async (e) => {
    const kind = e.target.getAttribute('data-kind');
    const id = e.target.getAttribute('data-id');
    if (kind !== 'del_enr') return;
    if (!confirm('¿Eliminar matrícula #' + id + '?')) return;
    try { await api('enrollments_delete', { data: { id } }); await loadEnrollments(); await loadCapacity(); }
    catch (err) { alert(err?.json?.message || 'Error'); }
  });

  function cupoRow(r) {
    return `<tr>
    <td>${r.grado}</td>
    <td>${r.used} / ${r.limit}</td>
    <td>${r.available}</td>
  </tr>`;
  }

  async function loadCapacity() {
    msgCupos.textContent = 'Cargando...';
    const year = document.querySelector('#formStudent [name="year"]').value || new Date().getFullYear();
    try {
      const j = await api('enrollments_capacity', { method: 'GET', params: { year } });
      const data = j.data || [];
      tblCupos.innerHTML = `<tr><th>Grado</th><th>Ocupados</th><th>Disponibles</th></tr>` + data.map(cupoRow).join('');
      msgCupos.textContent = '';
    } catch (err) {
      msgCupos.textContent = err?.json?.message || 'Error cargando cupos';
    }
  }

  (async () => {
    await loadUsers();
    await loadStudents();
    await loadEnrollments();
    await loadCapacity();
  })();

</script>

<?php include __DIR__ . '/components/footer.php'; ?>