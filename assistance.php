<?php
require_once __DIR__ . '/app/helpers/auth.php';
$config = require __DIR__ . '/app/config/config.php';
$base_url = $config['base_url'] ?? '';
$u = current_user();
if (!$u) {
  header('Location: ' . $base_url . '/login.php');
  exit;
}
$rol = $u['rol'] ?? '';
if (!in_array($rol, ['ADMIN', 'DOCENTE'], true)) {
  http_response_code(403);
  die('Sin permisos');
}
include __DIR__ . '/components/header.php';
?>

<section class="card">
  <h2>Módulo de Asistencia</h2>
  <p class="muted">
    <?php if ($rol === 'ADMIN'): ?>
      Administra profesores guía, consulta el historial y toma asistencia por sección.
    <?php else: ?>
      Consulta todas las secciones del sistema y registra ausencias o tardías por fecha.
    <?php endif; ?>
  </p>
</section>

<?php if ($rol === 'ADMIN'): ?>
<section class="card">
  <div style="display:flex; justify-content:space-between; gap:12px; align-items:end; flex-wrap:wrap;">
    <div>
      <h3 style="margin:0 0 6px 0;">Historial de ausencias y tardías</h3>
      <div class="muted">Filtra por sección y fecha. Puedes dejar la fecha vacía para ver todo el historial de la sección.</div>
    </div>
    <div style="display:flex; gap:10px; align-items:end; flex-wrap:wrap;">
      <label>Sección
        <select id="historySectionFilter"></select>
      </label>
      <label>Fecha
        <input type="date" id="historyDateFilter">
      </label>
    </div>
  </div>
  <div class="muted" id="msgHistory"></div>
  <div style="overflow:auto; margin-top:10px;">
    <table class="table" id="tblHistory"></table>
  </div>
</section>
<?php endif; ?>


<section class="card">
  <div style="display:flex; justify-content:space-between; gap:12px; align-items:center; flex-wrap:wrap;">
    <h3 style="margin:0;">Secciones del sistema</h3>
  </div>
  <div class="muted" id="msgSections"></div>
  <div id="sectionsGrid" class="grid3"></div>
</section>

<section class="card" id="attendanceCard" style="display:none;">
  <div style="display:flex; justify-content:space-between; gap:12px; align-items:center; flex-wrap:wrap;">
    <div>
      <h3 style="margin:0;">Tomar asistencia</h3>
      <div class="muted" id="attendanceMeta">Selecciona una sección.</div>
      <div class="muted">Cada envío genera nuevos registros de ausencias o tardías para esa fecha.</div>
    </div>
    <div style="display:flex; gap:10px; align-items:end; flex-wrap:wrap;">
      <label>
        Fecha
        <input type="date" id="attendanceDate" value="<?= date('Y-m-d') ?>">
      </label>
      <button class="btn" type="button" id="btnSubmitAttendance">Enviar</button>
    </div>
  </div>
  <div class="muted" id="msgAttendance"></div>
  <div style="overflow:auto; margin-top:10px;">
    <table class="table" id="tblAttendance"></table>
  </div>
</section>

<div id="studentsModal" style="display:none; position:fixed; inset:0; background:rgba(15,23,42,.58); z-index:9999; padding:20px;">
  <div class="card" style="max-width:900px; margin:0 auto; max-height:85vh; overflow:auto;">
    <div style="display:flex; justify-content:space-between; gap:12px; align-items:center; flex-wrap:wrap;">
      <div>
        <h3 style="margin:0;" id="studentsModalTitle">Estudiantes de la sección</h3>
        <div class="muted" id="studentsModalMeta"></div>
      </div>
      <button class="btn" type="button" id="btnCloseStudentsModal">Cerrar</button>
    </div>
    <div class="muted" id="msgStudentsModal" style="margin-top:10px;"></div>
    <div style="overflow:auto; margin-top:10px;">
      <table class="table" id="tblStudentsModal"></table>
    </div>
  </div>
</div>

<script>
  const ROLE = <?= json_encode($rol) ?>;
  let SECTIONS = [];
  let SELECTED_SECTION_ID = null;
  let CURRENT_ROSTER = [];

  function escapeHtml(v) {
    return String(v ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function stateBadge(label, type) {
    const map = {
      presente: 'background:rgba(34,197,94,.14); border:1px solid rgba(34,197,94,.3); color:#bbf7d0;',
      ausente: 'background:rgba(239,68,68,.14); border:1px solid rgba(239,68,68,.32); color:#fecaca;',
      tardia: 'background:rgba(245,158,11,.14); border:1px solid rgba(245,158,11,.34); color:#fde68a;',
      justificada: 'background:rgba(59,130,246,.14); border:1px solid rgba(59,130,246,.32); color:#bfdbfe;'
    };
    return `<span style="display:inline-block; padding:4px 10px; border-radius:999px; font-size:12px; font-weight:700; ${map[type] || map.presente}">${label}</span>`;
  }

  function sectionCard(section) {
    const teacher = section.docente_nombre ? escapeHtml(section.docente_nombre) : '<span class="muted">Sin profesor guía</span>';
    const count = section.total_estudiantes != null ? `<div class="muted">${section.total_estudiantes} estudiante(s)</div>` : '';
    return `
      <button type="button" class="card" data-kind="open-section" data-id="${section.id}" style="text-align:left; cursor:pointer; width:100%;">
        <h3 style="margin-bottom:8px;">Sección ${escapeHtml(section.codigo)}</h3>
        <div class="muted">Nivel ${escapeHtml(section.grado)}</div>
        <div class="muted">Profesor guía: ${teacher}</div>
        ${count}
      </button>
    `;
  }

  function adminSectionRow(section, teachers) {
    const opts = ['<option value="">(sin asignar)</option>'].concat(
      teachers.map(t => `<option value="${t.id}" ${String(t.id) === String(section.docente_guia_user_id || '') ? 'selected' : ''}>${escapeHtml(t.nombre)} (@${escapeHtml(t.username)})</option>`)
    ).join('');

    return `
      <tr>
        <td>${escapeHtml(section.codigo)}</td>
        <td>${escapeHtml(section.grado)}</td>
        <td><select data-kind="teacher" data-id="${section.id}">${opts}</select></td>
        <td>${section.docente_nombre ? escapeHtml(section.docente_nombre) : '<span class="muted">Sin asignar</span>'}</td>
        <td><button class="btn" type="button" data-kind="view-students" data-id="${section.id}">Ver estudiantes</button></td>
      </tr>
    `;
  }

  function attendanceRow(student) {
    const options = ['PRESENTE', 'AUSENTE', 'TARDIA'].map(s => `<option value="${s}" ${student.status === s ? 'selected' : ''}>${s === 'PRESENTE' ? 'Presente' : (s === 'AUSENTE' ? 'Ausente' : 'Tardía')}</option>`).join('');
    return `
      <tr>
        <td>${escapeHtml(student.nombre_completo || student.nombre || '')}</td>
        <td>${escapeHtml(student.cedula || '')}</td>
        <td>${escapeHtml(student.seccion || '')}</td>
        <td>
          <select data-kind="attendance-status" data-id="${student.id}">${options}</select>
        </td>
      </tr>
    `;
  }

  function historyRow(item) {
    const status = item.status === 'AUSENTE' ? stateBadge('Ausente', 'ausente') : stateBadge('Tardía', 'tardia');
    const justify = Number(item.is_justified)
      ? `${status} ${stateBadge('Justificada', 'justificada')}`
      : `${status} <button class="btn" type="button" data-kind="justify" data-id="${item.id}">Justificar</button>`;

    return `
      <tr>
        <td>${escapeHtml(item.attendance_date || '')}</td>
        <td>${escapeHtml(item.student_nombre || '')}</td>
        <td>${escapeHtml(item.cedula || '')}</td>
        <td>${justify}</td>
        <td>${item.taken_by_nombre ? escapeHtml(item.taken_by_nombre) : '<span class="muted">-</span>'}</td>
        <td>${Number(item.is_justified) ? `${escapeHtml(item.justified_by_nombre || '')}<br><span class="muted">${escapeHtml(item.justified_at || '')}</span>` : '<span class="muted">Pendiente</span>'}</td>
      </tr>
    `;
  }

  function modalStudentRow(student) {
    return `
      <tr>
        <td>${escapeHtml(student.nombre_completo || student.nombre || '')}</td>
        <td>${escapeHtml(student.cedula || '')}</td>
        <td>${escapeHtml(student.grado || '')}</td>
        <td>${escapeHtml(student.seccion || '')}</td>
      </tr>
    `;
  }

  function renderHistorySectionOptions() {
    if (ROLE !== 'ADMIN') return;
    const sel = document.getElementById('historySectionFilter');
    if (!sel) return;
    sel.innerHTML = '<option value="">Selecciona una sección</option>' + SECTIONS.map(s =>
      `<option value="${s.id}">${escapeHtml(s.codigo)} · Nivel ${escapeHtml(s.grado)}</option>`
    ).join('');
  }

  async function loadAdminSectionTable() {
    if (ROLE !== 'ADMIN') return;
    const msg = document.getElementById('msgAdminSections');
    const tbl = document.getElementById('tblAdminSections');
    msg.textContent = 'Cargando...';
    tbl.innerHTML = '';

    try {
      const teachersRes = await api('users_list_docentes', { method: 'GET', params: { limit: 500 } });
      const teachers = teachersRes.data || [];
      tbl.innerHTML = `<tr><th>Sección</th><th>Nivel</th><th>Profesor guía</th><th>Asignado</th><th>Lista</th></tr>` +
        SECTIONS.map(s => adminSectionRow(s, teachers)).join('');
      msg.textContent = '';
    } catch (err) {
      msg.textContent = err?.json?.message || 'Error cargando secciones';
    }
  }

  async function loadSectionCards() {
    const msg = document.getElementById('msgSections');
    const grid = document.getElementById('sectionsGrid');
    msg.textContent = 'Cargando...';
    grid.innerHTML = '';

    try {
      const res = await api('attendance_sections', { method: 'GET' });
      SECTIONS = res.data || [];
      if (!SECTIONS.length) {
        msg.textContent = 'No hay secciones disponibles.';
        renderHistorySectionOptions();
        return;
      }
      grid.innerHTML = SECTIONS.map(sectionCard).join('');
      msg.textContent = '';
      renderHistorySectionOptions();
      await loadAdminSectionTable();
    } catch (err) {
      msg.textContent = err?.json?.message || 'Error cargando secciones';
    }
  }

  async function openSection(sectionId) {
    SELECTED_SECTION_ID = sectionId;
    await loadRoster();
  }

  async function loadRoster() {
    if (!SELECTED_SECTION_ID) return;
    const date = document.getElementById('attendanceDate').value;
    const card = document.getElementById('attendanceCard');
    const meta = document.getElementById('attendanceMeta');
    const msg = document.getElementById('msgAttendance');
    const tbl = document.getElementById('tblAttendance');
    card.style.display = 'block';

    msg.textContent = 'Cargando estudiantes...';
    tbl.innerHTML = '';

    try {
      const res = await api('attendance_section_roster', { method: 'GET', params: { section_id: SELECTED_SECTION_ID, attendance_date: date } });
      CURRENT_ROSTER = res.data || [];
      const section = res.section || {};
      meta.textContent = `Sección ${section.codigo || ''} · Fecha ${date}. Todos salen por defecto como Presente y solo se guardan Ausente o Tardía.`;

      tbl.innerHTML = `<tr><th>Estudiante</th><th>Cédula</th><th>Sección</th><th>Estado</th></tr>` + CURRENT_ROSTER.map(attendanceRow).join('');
      msg.textContent = CURRENT_ROSTER.length ? '' : 'No hay estudiantes registrados en esta sección.';
    } catch (err) {
      msg.textContent = err?.json?.message || 'Error cargando estudiantes';
    }
  }

  async function submitAttendance() {
    if (!SELECTED_SECTION_ID) {
      alert('Selecciona una sección.');
      return;
    }

    const date = document.getElementById('attendanceDate').value;
    const msg = document.getElementById('msgAttendance');
    const items = CURRENT_ROSTER.map(s => {
      const sel = document.querySelector(`select[data-kind="attendance-status"][data-id="${s.id}"]`);
      return { student_id: s.id, status: sel ? sel.value : 'PRESENTE' };
    });

    msg.textContent = 'Guardando...';

    try {
      await api('attendance_save', {
        method: 'POST',
        data: { section_id: SELECTED_SECTION_ID, attendance_date: date, items: JSON.stringify(items) }
      });
      const savedCount = items.filter(x => x.status === 'AUSENTE' || x.status === 'TARDIA').length;
      msg.textContent = savedCount
        ? `Asistencia registrada. Se agregaron ${savedCount} ausencia(s)/tardía(s) para ${date}.`
        : `Asistencia enviada. No se agregaron registros porque todos quedaron presentes.`;
      if (ROLE === 'ADMIN') {
        const sectionFilter = document.getElementById('historySectionFilter');
        if (sectionFilter && String(sectionFilter.value) === String(SELECTED_SECTION_ID)) {
          await loadHistory();
        }
      }
      await loadRoster();
    } catch (err) {
      msg.textContent = err?.json?.message || 'Error guardando asistencia';
    }
  }

  async function loadHistory() {
    if (ROLE !== 'ADMIN') return;
    const sectionId = document.getElementById('historySectionFilter').value;
    const date = document.getElementById('historyDateFilter').value;
    const msg = document.getElementById('msgHistory');
    const tbl = document.getElementById('tblHistory');

    if (!sectionId) {
      tbl.innerHTML = '';
      msg.textContent = 'Selecciona una sección para consultar el historial.';
      return;
    }

    msg.textContent = 'Cargando historial...';
    tbl.innerHTML = '';

    try {
      const params = { section_id: sectionId };
      if (date) params.attendance_date = date;
      const res = await api('attendance_history', { method: 'GET', params });
      const rows = res.data || [];
      tbl.innerHTML = `<tr><th>Fecha</th><th>Estudiante</th><th>Cédula</th><th>Estado</th><th>Registrado por</th><th>Justificación</th></tr>` + rows.map(historyRow).join('');
      msg.textContent = rows.length ? '' : 'No hay ausencias o tardías para ese filtro.';
    } catch (err) {
      msg.textContent = err?.json?.message || 'Error cargando historial';
    }
  }

  async function justifyAttendance(attendanceId) {
    await api('attendance_justify', { method: 'POST', data: { attendance_id: attendanceId } });
    await loadHistory();
  }

  async function showStudentsModal(sectionId) {
    const modal = document.getElementById('studentsModal');
    const msg = document.getElementById('msgStudentsModal');
    const tbl = document.getElementById('tblStudentsModal');
    const title = document.getElementById('studentsModalTitle');
    const meta = document.getElementById('studentsModalMeta');
    const section = SECTIONS.find(s => String(s.id) === String(sectionId)) || {};

    title.textContent = `Estudiantes de la sección ${section.codigo || ''}`;
    meta.textContent = `Nivel ${section.grado || ''}`;
    modal.style.display = 'block';
    msg.textContent = 'Cargando estudiantes...';
    tbl.innerHTML = '';

    try {
      const date = document.getElementById('attendanceDate').value;
      const res = await api('attendance_section_roster', { method: 'GET', params: { section_id: sectionId, attendance_date: date } });
      const students = res.data || [];
      tbl.innerHTML = `<tr><th>Nombre</th><th>Cédula</th><th>Nivel</th><th>Sección</th></tr>` + students.map(modalStudentRow).join('');
      msg.textContent = students.length ? '' : 'No hay estudiantes registrados en esta sección.';
    } catch (err) {
      msg.textContent = err?.json?.message || 'Error cargando estudiantes';
    }
  }

  function closeStudentsModal() {
    document.getElementById('studentsModal').style.display = 'none';
  }

  document.addEventListener('click', async (e) => {
    const openBtn = e.target.closest('[data-kind="open-section"]');
    if (openBtn) {
      await openSection(openBtn.dataset.id);
      return;
    }

    const justifyBtn = e.target.closest('[data-kind="justify"]');
    if (justifyBtn) {
      await justifyAttendance(justifyBtn.dataset.id);
      return;
    }

    const viewStudentsBtn = e.target.closest('[data-kind="view-students"]');
    if (viewStudentsBtn) {
      await showStudentsModal(viewStudentsBtn.dataset.id);
      return;
    }
  });

  document.addEventListener('change', async (e) => {
    const teacherSel = e.target.closest('select[data-kind="teacher"]');
    if (teacherSel) {
      const msg = document.getElementById('msgAdminSections');
      msg.textContent = 'Guardando asignación...';
      try {
        await api('attendance_assign_teacher', {
          method: 'POST',
          data: { section_id: teacherSel.dataset.id, teacher_user_id: teacherSel.value }
        });
        msg.textContent = 'Profesor guía actualizado.';
        await loadSectionCards();
      } catch (err) {
        msg.textContent = err?.json?.message || 'Error guardando asignación';
      }
      return;
    }

    if (e.target.id === 'historySectionFilter' || e.target.id === 'historyDateFilter') {
      await loadHistory();
      return;
    }

    if (e.target.id === 'attendanceDate' && SELECTED_SECTION_ID) {
      await loadRoster();
    }
  });

  document.getElementById('btnSubmitAttendance').addEventListener('click', submitAttendance);
  document.getElementById('btnCloseStudentsModal').addEventListener('click', closeStudentsModal);
  document.getElementById('studentsModal').addEventListener('click', (e) => {
    if (e.target.id === 'studentsModal') closeStudentsModal();
  });

  loadSectionCards();
  if (ROLE === 'ADMIN') {
    loadHistory();
  }
</script>
