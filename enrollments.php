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
  <input type="text" id="searchStudents" placeholder="Buscar estudiante..." style="width:100%; margin-bottom:10px;">
  <div class="muted" id="msg"></div>
  <div style="overflow:auto;">
    <table class="table" id="tblStudents"></table>
  </div>
  <div id="paginationStudents" style="margin-top:15px; text-align:center;"></div>
</section>

<section class="card">
  <h3>Matrículas</h3>
  <input type="text" id="searchEnrollments" placeholder="Buscar matrícula..." style="width:100%; margin-bottom:10px;">
  <div class="muted" id="msgEnr"></div>
  <div style="overflow:auto;">
    <table class="table" id="tblEnr"></table>
  </div>
  <div id="paginationEnrollments" style="margin-top:15px; text-align:center;"></div>
</section>

<div id="modalEdit" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.9); padding:20px;">
  <div class="card modal-card">
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


  let STUDENTS_DATA = [];
  let ENROLLMENTS_DATA = [];

  let studentsPage = 1;
  let enrollmentsPage = 1;
  const PER_PAGE = 5;

  const searchStudents = document.getElementById('searchStudents');
  const searchEnrollments = document.getElementById('searchEnrollments');
  const paginationStudents = document.getElementById('paginationStudents');
  const paginationEnrollments = document.getElementById('paginationEnrollments');

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

  function drawPager(container, currentPage, totalItems, perPage, onPageClick) {
    container.innerHTML = '';

    const totalPages = Math.max(1, Math.ceil(totalItems / perPage));
    if (totalPages <= 1) return;

    const wrap = document.createElement('div');
    wrap.style.display = 'flex';
    wrap.style.flexWrap = 'wrap';
    wrap.style.gap = '6px';
    wrap.style.alignItems = 'center';
    wrap.style.justifyContent = 'center';

    const info = document.createElement('span');
    info.textContent = `Pág. ${currentPage} de ${totalPages}`;
    info.style.padding = '6px 10px';
    info.style.borderRadius = '8px';
    info.style.background = 'rgba(255,255,255,0.08)';
    info.style.border = '1px solid rgba(255,255,255,0.12)';
    info.style.color = '#e5eefc';
    info.style.fontSize = '13px';
    info.style.lineHeight = '1';
    wrap.appendChild(info);

    function addBtn(label, page, opts = {}) {
      const b = document.createElement('button');
      b.type = 'button';
      b.className = 'btn btn-sm';
      b.textContent = label;

      b.style.minWidth = '32px';
      b.style.height = '30px';
      b.style.padding = '0 10px';
      b.style.borderRadius = '9px';
      b.style.border = '1px solid rgba(79, 209, 255, 0.18)';
      b.style.background = 'rgba(14, 36, 66, 0.9)';
      b.style.color = '#dbeafe';
      b.style.boxShadow = 'inset 0 0 0 1px rgba(255,255,255,0.02)';
      b.style.cursor = 'pointer';

      if (opts.disabled) {
        b.disabled = true;
        b.style.opacity = '0.45';
        b.style.cursor = 'not-allowed';
      }

      if (opts.active) {
        b.style.background = 'linear-gradient(180deg, #4ade80, #22c55e)';
        b.style.border = '1px solid rgba(34, 197, 94, 0.65)';
        b.style.color = '#052e16';
        b.style.fontWeight = '700';
        b.style.boxShadow = '0 0 0 2px rgba(74,222,128,0.12)';
      }

      if (!opts.disabled && typeof page === 'number') {
        b.onclick = () => onPageClick(page);
      }

      wrap.appendChild(b);
    }

    function addEllipsis() {
      const el = document.createElement('span');
      el.textContent = '...';
      el.style.padding = '0 4px';
      el.style.color = 'rgba(219,234,254,0.75)';
      el.style.fontWeight = '600';
      wrap.appendChild(el);
    }

    const pages = new Set();

    for (let i = 1; i <= Math.min(3, totalPages); i++) pages.add(i);
    for (let i = Math.max(1, currentPage - 2); i <= Math.min(totalPages, currentPage + 2); i++) pages.add(i);
    for (let i = Math.max(1, totalPages - 2); i <= totalPages; i++) pages.add(i);

    const sorted = Array.from(pages).sort((a, b) => a - b);

    addBtn('«', 1, { disabled: currentPage === 1 });
    addBtn('<', currentPage - 1, { disabled: currentPage === 1 });

    let prev = 0;
    for (const p of sorted) {
      if (prev && p - prev > 1) addEllipsis();
      addBtn(String(p), p, { active: p === currentPage });
      prev = p;
    }

    addBtn('>', currentPage + 1, { disabled: currentPage === totalPages });
    addBtn('»', totalPages, { disabled: currentPage === totalPages });

    container.appendChild(wrap);
  }

  function renderStudentsTable(filtered) {
    const start = (studentsPage - 1) * PER_PAGE;
    const end = start + PER_PAGE;
    const rows = filtered.slice(start, end);

    tbl.innerHTML = `<tr><th>Nombre</th><th>Cédula</th><th>Grado</th><th>Sección</th><th>User</th><th></th></tr>` +
      rows.map(studentRow).join('');

    drawPager(paginationStudents, studentsPage, filtered.length, PER_PAGE, (p) => {
      studentsPage = p;
      applyStudentsFilter();
    });
  }

  function renderEnrollmentsTable(filtered) {
    const start = (enrollmentsPage - 1) * PER_PAGE;
    const end = start + PER_PAGE;
    const rows = filtered.slice(start, end);

    tblEnr.innerHTML = `<tr><th>Estudiante</th><th>Grado</th><th>Año</th><th>Estado</th><th></th></tr>` +
      rows.map(enrRow).join('');

    drawPager(paginationEnrollments, enrollmentsPage, filtered.length, PER_PAGE, (p) => {
      enrollmentsPage = p;
      applyEnrollmentsFilter();
    });
  }

  function applyStudentsFilter() {
    const q = (searchStudents?.value || '').toLowerCase().trim();
    const filtered = STUDENTS_DATA.filter(s =>
      (s.nombre || '').toLowerCase().includes(q) ||
      (s.cedula || '').toLowerCase().includes(q) ||
      String(s.grado || '').includes(q) ||
      (s.seccion || '').toLowerCase().includes(q)
    );
    renderStudentsTable(filtered);
  }

  function applyEnrollmentsFilter() {
    const q = (searchEnrollments?.value || '').toLowerCase().trim();
    const filtered = ENROLLMENTS_DATA.filter(e =>
      (e.student_nombre || '').toLowerCase().includes(q) ||
      String(e.grado || '').includes(q) ||
      String(e.year || '').includes(q) ||
      (e.seccion || '').toLowerCase().includes(q) ||
      (e.estado || '').toLowerCase().includes(q)
    );
    renderEnrollmentsTable(filtered);
  }

  async function loadStudents() {
    msg.textContent = 'Cargando...';
    try {
      const j = await api('students_list', { method: 'GET', params: { limit: 200 } });
      STUDENTS_DATA = j.data || [];
      studentsPage = 1;
      applyStudentsFilter();
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
      ENROLLMENTS_DATA = j.data || [];
      enrollmentsPage = 1;
      applyEnrollmentsFilter();
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

  if (searchStudents) {
    searchStudents.addEventListener('input', () => {
      studentsPage = 1;
      applyStudentsFilter();
    });
  }

  if (searchEnrollments) {
    searchEnrollments.addEventListener('input', () => {
      enrollmentsPage = 1;
      applyEnrollmentsFilter();
    });
  }

  (async () => {
    await loadUsers();
    await loadStudents();
    await loadEnrollments();
    await loadCapacity();
  })();

</script>

<?php include __DIR__ . '/components/footer.php'; ?>