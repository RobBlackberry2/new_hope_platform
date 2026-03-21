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
  <p class="muted">Página para gestionar matrículas y ver la lista de todos los estudiantes.</p>
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
  <div style="display:flex; justify-content:space-between; gap:12px; align-items:center; flex-wrap:wrap;">
    <h3 style="margin:0;">Estudiantes</h3>
    <label style="display:flex; gap:8px; align-items:center; font-size:14px;">
      <input type="checkbox" id="toggleArchivedStudents">
      Mostrar archivados
    </label>
  </div>
  <input type="text" id="searchStudents" placeholder="Buscar estudiante..." style="width:100%; margin:10px 0;">
  <div class="muted" id="msg"></div>
  <div style="overflow:auto;">
    <table class="table" id="tblStudents"></table>
  </div>
  <div id="paginationStudents" style="margin-top:15px; text-align:center;"></div>
</section>

<section class="card">
  <div style="display:flex; justify-content:space-between; gap:12px; align-items:center; flex-wrap:wrap;">
    <h3 style="margin:0;">Matrículas</h3>
    <label style="display:flex; gap:8px; align-items:center; font-size:14px;">
      <input type="checkbox" id="toggleArchivedEnrollments">
      Mostrar archivadas
    </label>
  </div>
  <input type="text" id="searchEnrollments" placeholder="Buscar matrícula..." style="width:100%; margin:10px 0;">
  <div class="muted" id="msgEnr"></div>
  <div style="overflow:auto;">
    <table class="table" id="tblEnr"></table>
  </div>
  <div id="paginationEnrollments" style="margin-top:15px; text-align:center;"></div>
</section>

<div id="modalEdit"
  style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.9); padding:20px; z-index:2100;">
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

      <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
        <button class="btn" type="submit">Guardar</button>
        <button class="btn" type="button" id="btnCloseEdit">Cerrar</button>
        <div class="muted" id="msgEdit"></div>
      </div>
    </form>
  </div>
</div>

<div id="modalPaymentControl"
  style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.82); padding:20px; z-index:1000; overflow:auto;">
  <div class="card modal-card" style="max-width:1100px;">
    <div
      style="display:flex; justify-content:space-between; align-items:flex-start; gap:16px; margin-bottom:14px; flex-wrap:wrap;">
      <div>
        <h3 style="margin-bottom:6px;">Control de Pagos</h3>
        <div class="muted" id="paymentEnrollmentMeta">Cargando datos...</div>
      </div>
      <button class="btn" type="button" id="btnClosePaymentControl">Cerrar</button>
    </div>

    <form id="formPaymentControl">
      <input type="hidden" name="enrollment_id" />
      <input type="hidden" name="payment_year" />
      <div style="overflow:auto;">
        <table class="table" id="tblPaymentControl"></table>
      </div>
      <div style="display:flex; gap:10px; align-items:center; margin-top:16px; flex-wrap:wrap;">
        <button class="btn" type="submit">Guardar control de pagos</button>
        <div class="muted" id="msgPaymentControl"></div>
      </div>
    </form>
  </div>
</div>

<div id="modalArchiveWarning"
  style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.78); padding:20px; z-index:2200;">
  <div
    style="max-width:620px; margin:48px auto; background:linear-gradient(180deg, #3b0b0b 0%, #1b1111 100%); border:1px solid rgba(248,113,113,.55); border-radius:18px; padding:24px; box-shadow:0 20px 60px rgba(0,0,0,.45), 0 0 0 3px rgba(239,68,68,.12); color:#fff;">
    <div style="display:flex; gap:14px; align-items:flex-start;">
      <div style="font-size:34px; line-height:1;">⚠️</div>
      <div style="flex:1;">
        <h3 id="archiveModalTitle" style="margin:0 0 10px 0; color:#fecaca;">Confirmar archivado</h3>
        <div id="archiveModalText" style="font-size:15px; line-height:1.6; color:#fee2e2;"></div>
        <div
          style="margin-top:14px; padding:12px 14px; border-radius:12px; background:rgba(127,29,29,.48); border:1px solid rgba(248,113,113,.22); color:#fecaca; font-size:14px;">
          Esta acción ocultará el registro de la vista principal, pero lo mantendrá en la base de datos para consulta o
          restauración posterior.
        </div>
        <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:20px; flex-wrap:wrap;">
          <button class="btn" type="button" id="btnArchiveCancel">Cancelar</button>
          <button class="btn danger" type="button" id="btnArchiveConfirm"
            style="background:linear-gradient(180deg, #ef4444, #b91c1c); border-color:#ef4444; color:#fff; font-weight:700;">Sí,
            archivar</button>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
  const tbl = document.getElementById('tblStudents');
  const msg = document.getElementById('msg');
  const tblEnr = document.getElementById('tblEnr');
  const msgEnr = document.getElementById('msgEnr');
  const tblCupos = document.getElementById('tblCupos');
  const msgCupos = document.getElementById('msgCupos');
  const toggleArchivedStudents = document.getElementById('toggleArchivedStudents');
  const toggleArchivedEnrollments = document.getElementById('toggleArchivedEnrollments');
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

  const PAYMENT_LABELS = {
    matricula: 'Matrícula',
    febrero: 'Febrero',
    marzo: 'Marzo',
    abril: 'Abril',
    mayo: 'Mayo',
    junio: 'Junio',
    julio: 'Julio',
    agosto: 'Agosto',
    septiembre: 'Septiembre',
    octubre: 'Octubre',
    noviembre: 'Noviembre',
    diciembre: 'Diciembre'
  };

  function escapeHtml(v) {
    return String(v ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function isArchivedStudent(s) {
    return !!(s && s.archived_at);
  }

  function isArchivedEnrollment(e) {
    return !!(e && e.archived_at);
  }

  function badgeArchived(label = 'Archivado') {
    return `<span style="display:inline-block; padding:4px 10px; border-radius:999px; background:rgba(251,191,36,.14); border:1px solid rgba(251,191,36,.38); color:#fde68a; font-size:12px; font-weight:700;">${label}</span>`;
  }

  function studentRow(s) {
    const archived = isArchivedStudent(s);
    const status = archived ? badgeArchived('Estudiante archivado') : '';
    return `<tr style="${archived ? 'opacity:.78;' : ''}">
      <td>
        <div>${escapeHtml(s.nombre || '')}</div>
        ${status ? `<div class="mt-6">${status}</div>` : ''}
      </td>
      <td>${escapeHtml(s.cedula || '')}</td>
      <td>${escapeHtml(s.grado || '')}</td>
      <td>${escapeHtml(s.seccion || '')}</td>
      <td>
        ${archived ? '<span class="muted">No editable</span>' : `<select data-kind="user_id" data-id="${s.id}">${userOptionsHtml(s.user_id)}</select>`}
      </td>
      <td style="display:flex; gap:8px; flex-wrap:wrap;">
        ${archived
        ? `<button class="btn" data-kind="restore_student" data-id="${s.id}">Restaurar</button>`
        : `<button class="btn" data-kind="edit" data-id="${s.id}">Editar</button>
             <button class="btn danger" data-kind="archive_student" data-id="${s.id}" data-name="${escapeHtml(s.nombre || ('Estudiante #' + s.id))}">Archivar</button>`}
      </td>
    </tr>`;
  }

  function enrollmentYearOptions(e) {
    const currentYear = new Date().getFullYear();
    const maxYear = currentYear + 1;

    const startYear = Number(e.start_year || e.year || currentYear);
    const selectedYear = Number(e.year || currentYear);

    let html = '';
    for (let y = startYear; y <= maxYear; y++) {
      html += `<option value="${y}" ${y === selectedYear ? 'selected' : ''}>${y}</option>`;
    }
    return html;
  }


  function enrRow(e) {
    const archived = isArchivedEnrollment(e);
    const studentArchived = !!e.student_archived_at;
    const status = archived ? badgeArchived('Matrícula archivada') : '';
    const studentBadge = studentArchived
      ? `<div class="mt-6">${badgeArchived('Estudiante archivado')}</div>`
      : '';

    return `<tr style="${archived ? 'opacity:.78;' : ''}">
      <td>
        <div>${escapeHtml(e.student_nombre || '')}</div>
        ${studentBadge}
      </td>
      <td>${escapeHtml(e.grado || '')}${e.seccion ? ' - ' + escapeHtml(e.seccion) : ''}</td>
      <td>
        ${archived
        ? escapeHtml(e.year || '')
        : `<select data-kind="year" data-id="${e.id}">
                ${enrollmentYearOptions(e)}
              </select>`
      }
      </td>
      <td>
        ${archived
        ? status
        : `<select data-kind="estado" data-id="${e.id}">
                <option ${e.estado === 'ACTIVA' ? 'selected' : ''}>ACTIVA</option>
                <option ${e.estado === 'PENDIENTE' ? 'selected' : ''}>PENDIENTE</option>
                <option ${e.estado === 'BLOQUEADO' ? 'selected' : ''}>BLOQUEADO</option>
              </select>`
      }
      </td>
      <td style="display:flex; gap:8px; flex-wrap:wrap;">
        ${!archived
        ? `<button class="btn" data-kind="payment_control" data-id="${e.id}" data-year="${escapeHtml(e.year || '')}">Control de Pagos</button>`
        : ''
      }
        ${archived
        ? `<button class="btn" data-kind="restore_enr" data-id="${e.id}">Restaurar</button>`
        : `<button class="btn danger" data-kind="archive_enr" data-id="${e.id}" data-name="${escapeHtml(e.student_nombre || ('Matrícula #' + e.id))}">Archivar</button>`
      }
      </td>
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

    tbl.innerHTML = `<tr><th>Nombre</th><th>Cédula</th><th>Grado</th><th>Sección</th><th>Usuario</th><th>Acciones</th></tr>` +
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

    tblEnr.innerHTML = `<tr><th>Estudiante</th><th>Grado</th><th>Año</th><th>Estado</th><th>Acciones</th></tr>` +
      rows.map(enrRow).join('');

    drawPager(paginationEnrollments, enrollmentsPage, filtered.length, PER_PAGE, (p) => {
      enrollmentsPage = p;
      applyEnrollmentsFilter();
    });
  }

  function applyStudentsFilter() {
    const q = (searchStudents?.value || '').toLowerCase().trim();
    const showArchived = !!toggleArchivedStudents?.checked;
    const filtered = STUDENTS_DATA.filter(s => {
      const matchesSearch =
        (s.nombre || '').toLowerCase().includes(q) ||
        (s.cedula || '').toLowerCase().includes(q) ||
        String(s.grado || '').includes(q) ||
        (s.seccion || '').toLowerCase().includes(q);

      const matchesArchived = showArchived ? true : !isArchivedStudent(s);
      return matchesSearch && matchesArchived;
    });
    renderStudentsTable(filtered);
  }

  function applyEnrollmentsFilter() {
    const q = (searchEnrollments?.value || '').toLowerCase().trim();
    const showArchived = !!toggleArchivedEnrollments?.checked;
    const filtered = ENROLLMENTS_DATA.filter(e => {
      const matchesSearch =
        (e.student_nombre || '').toLowerCase().includes(q) ||
        String(e.grado || '').includes(q) ||
        String(e.year || '').includes(q) ||
        (e.seccion || '').toLowerCase().includes(q) ||
        (e.estado || '').toLowerCase().includes(q);

      const matchesArchived = showArchived ? true : !isArchivedEnrollment(e);
      return matchesSearch && matchesArchived;
    });
    renderEnrollmentsTable(filtered);
  }

  async function loadStudents() {
    msg.textContent = 'Cargando...';
    try {
      const j = await api('students_list', { method: 'GET', params: { limit: 500, include_archived: 1 } });
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
      opts.push(`<option value="${u.id}" ${selected}>${escapeHtml(label)}</option>`);
    }
    return opts.join('');
  }

  async function loadEnrollments() {
    msgEnr.textContent = 'Cargando...';
    try {
      const j = await api('enrollments_list', { method: 'GET', params: { limit: 500, include_archived: 1 } });
      ENROLLMENTS_DATA = j.data || [];
      enrollmentsPage = 1;
      applyEnrollmentsFilter();
      msgEnr.textContent = '';
    } catch (err) {
      msgEnr.textContent = err?.json?.message || 'Error cargando matrículas';
    }
  }
  function paymentStatusBadge(isPaid) {
    const paid = !!Number(isPaid);
    return `<span class="badge-state ${paid ? 'paid' : 'pending'}">${paid ? 'Pagado' : 'Pendiente'}</span>`;
  }

  function paymentRow(item) {
    const monthKey = item.month_key || '';
    const label = PAYMENT_LABELS[monthKey] || monthKey;
    const invoice = item.invoice_number || '';
    const checked = Number(item.is_paid) ? 'checked' : '';

    return `<tr>
    <td style="font-weight:700;">${label}</td>
    <td>
      <input
        type="text"
        class="payment-invoice-input"
        data-kind="invoice_number"
        data-month="${monthKey}"
        value="${invoice.replace(/"/g, '&quot;')}"
        placeholder="Número de factura"
      />
    </td>
    <td style="text-align:center;">
      <input type="checkbox" data-kind="payment_check" data-month="${monthKey}" ${checked} />
    </td>
    <td><span data-kind="payment_state_badge" data-month="${monthKey}">${paymentStatusBadge(item.is_paid)}</span></td>
  </tr>`;
  }

  function renderPaymentTable(items) {
    tblPaymentControl.innerHTML = `
    <tr>
      <th>Concepto</th>
      <th>Número de factura</th>
      <th>Check</th>
      <th>Estado</th>
    </tr>
  ` + items.map(paymentRow).join('');
  }

  async function openPaymentControl(enrollmentId, paymentYear) {
    const modal = document.getElementById('modalPaymentControl');
    const meta = document.getElementById('paymentEnrollmentMeta');
    const msgBox = document.getElementById('msgPaymentControl');
    const form = document.getElementById('formPaymentControl');

    msgBox.textContent = '';
    meta.textContent = 'Cargando datos...';
    form.enrollment_id.value = enrollmentId;
    form.payment_year.value = paymentYear;
    tblPaymentControl.innerHTML = '<tr><td colspan="4">Cargando...</td></tr>';
    modal.style.display = 'block';

    try {
      const j = await api('enrollments_paymentControl_get', {
        method: 'GET',
        params: { id: enrollmentId, payment_year: paymentYear }
      });

      const enr = j.enrollment || {};
      const loadedYear = j.payment_year || paymentYear;

      form.payment_year.value = loadedYear;
      meta.textContent = `${enr.student_nombre || ''} · ${loadedYear} · ${enr.grado || ''}${enr.seccion ? (' - ' + enr.seccion) : ''}`;
      renderPaymentTable(j.data || []);
    } catch (err) {
      meta.textContent = 'No se pudo cargar el control de pagos.';
      tblPaymentControl.innerHTML = '<tr><td colspan="4">No se pudo cargar la información.</td></tr>';
      msgBox.textContent = err?.json?.message || 'Error cargando control de pagos';
    }
  }

  function collectPaymentControlItems() {
    const rows = Array.from(document.querySelectorAll('#tblPaymentControl [data-month]'));
    const months = [...new Set(rows.map(el => el.getAttribute('data-month')).filter(Boolean))];

    return months.map(monthKey => {
      const invoiceInput = document.querySelector(`#tblPaymentControl [data-kind="invoice_number"][data-month="${monthKey}"]`);
      const checkbox = document.querySelector(`#tblPaymentControl [data-kind="payment_check"][data-month="${monthKey}"]`);
      return {
        month_key: monthKey,
        invoice_number: invoiceInput ? invoiceInput.value.trim() : '',
        is_paid: checkbox && checkbox.checked ? 1 : 0
      };
    });
  }

  const archiveModal = document.getElementById('modalArchiveWarning');
  const archiveModalTitle = document.getElementById('archiveModalTitle');
  const archiveModalText = document.getElementById('archiveModalText');
  const btnArchiveCancel = document.getElementById('btnArchiveCancel');
  const btnArchiveConfirm = document.getElementById('btnArchiveConfirm');
  let archiveAction = null;

  function openArchiveWarning({ title, text, confirmText = 'Sí, archivar', onConfirm }) {
    archiveAction = onConfirm;
    archiveModalTitle.textContent = title;
    archiveModalText.innerHTML = text;
    btnArchiveConfirm.textContent = confirmText;
    archiveModal.style.display = 'block';
  }

  function closeArchiveWarning() {
    archiveAction = null;
    archiveModal.style.display = 'none';
  }

  btnArchiveCancel.addEventListener('click', closeArchiveWarning);
  archiveModal.addEventListener('click', (e) => {
    if (e.target === archiveModal) closeArchiveWarning();
  });
  btnArchiveConfirm.addEventListener('click', async () => {
    const fn = archiveAction;
    closeArchiveWarning();
    if (typeof fn === 'function') await fn();
  });

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

  document.getElementById('btnClosePaymentControl').addEventListener('click', () => {
    document.getElementById('modalPaymentControl').style.display = 'none';
  });

  document.getElementById('tblPaymentControl').addEventListener('change', (e) => {
    if (e.target.getAttribute('data-kind') !== 'payment_check') return;
    const month = e.target.getAttribute('data-month');
    const badge = document.querySelector(`#tblPaymentControl [data-kind="payment_state_badge"][data-month="${month}"]`);
    if (badge) {
      badge.innerHTML = paymentStatusBadge(e.target.checked ? 1 : 0);
    }
  });

  document.getElementById('formPaymentControl').addEventListener('submit', async (e) => {
    e.preventDefault();
    const msgBox = document.getElementById('msgPaymentControl');
    const enrollmentId = e.target.enrollment_id.value;
    msgBox.textContent = '';

    try {
      await api('enrollments_paymentControl_save', {
        data: {
          id: enrollmentId,
          payment_year: e.target.payment_year.value,
          items: JSON.stringify(collectPaymentControlItems())
        }
      });
      msgBox.textContent = 'Control de pagos guardado correctamente.';
    } catch (err) {
      msgBox.textContent = err?.json?.message || 'Error guardando control de pagos';
    }
  });

  tbl.addEventListener('click', async (e) => {
    const kind = e.target.getAttribute('data-kind');
    const id = e.target.getAttribute('data-id');
    if (!kind || !id) return;

    if (kind === 'archive_student') {
      const name = e.target.getAttribute('data-name') || ('Estudiante #' + id);
      openArchiveWarning({
        title: 'Archivar estudiante',
        text: `Vas a archivar a <strong>${name}</strong>.<br>También se archivarán sus matrículas activas y el estudiante dejará de verse en la lista principal.`,
        confirmText: 'Sí, archivar estudiante',
        onConfirm: async () => {
          try {
            await api('students_delete', { data: { id } });
            await loadStudents();
            await loadEnrollments();
            await loadCapacity();
          } catch (err) {
            alert(err?.json?.message || 'Error archivando estudiante');
          }
        }
      });
      return;
    }

    if (kind === 'restore_student') {
      try {
        await api('students_restore', { data: { id } });
        await loadStudents();
        await loadEnrollments();
        await loadCapacity();
      } catch (err) {
        alert(err?.json?.message || 'Error restaurando estudiante');
      }
      return;
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
    if (!kind || !id) return;

    if (kind === 'estado') {
      try {
        await api('enrollments_updateEstado', { data: { id, estado: e.target.value } });
        await loadCapacity();
        await loadUsers();
        await loadStudents();
        await loadEnrollments();
      } catch (err) {
        alert(err?.json?.message || 'Error');
      }
      return;
    }

    if (kind === 'year') {
      try {
        await api('enrollments_updateYear', { data: { id, year: e.target.value } });
        await loadCapacity();
        await loadEnrollments();
      } catch (err) {
        alert(err?.json?.message || 'Error actualizando año de matrícula');
        await loadEnrollments();
      }
    }
  });

  tbl.addEventListener('change', async (e) => {
    const kind = e.target.getAttribute('data-kind');
    const id = e.target.getAttribute('data-id');
    if (kind !== 'user_id') return;

    const user_id = e.target.value;
    try {
      await api('students_updateUserId', { data: { id, user_id } });
    } catch (err) {
      alert(err?.json?.message || 'Error actualizando usuario del estudiante');
      await loadStudents();
    }
  });

  tblEnr.addEventListener('click', async (e) => {
    const kind = e.target.getAttribute('data-kind');
    const id = e.target.getAttribute('data-id');
    if (!kind || !id) return;

    if (kind === 'payment_control') {
      const row = e.target.closest('tr');
      const yearSelect = row ? row.querySelector('select[data-kind="year"]') : null;
      const paymentYear = yearSelect ? yearSelect.value : (e.target.getAttribute('data-year') || new Date().getFullYear());
      await openPaymentControl(id, paymentYear);
      return;
    }

    if (kind === 'archive_enr') {
      const name = e.target.getAttribute('data-name') || ('Matrícula #' + id);
      openArchiveWarning({
        title: 'Archivar matrícula',
        text: `Vas a archivar la matrícula de <strong>${name}</strong>.<br>La matrícula dejará de mostrarse en la vista principal, pero seguirá guardada en la base de datos.`,
        confirmText: 'Sí, archivar matrícula',
        onConfirm: async () => {
          try {
            await api('enrollments_delete', { data: { id } });
            await loadEnrollments();
            await loadCapacity();
          } catch (err) {
            alert(err?.json?.message || 'Error archivando matrícula');
          }
        }
      });
      return;
    }

    if (kind === 'restore_enr') {
      try {
        await api('enrollments_restore', { data: { id } });
        await loadEnrollments();
        await loadCapacity();
      } catch (err) {
        alert(err?.json?.message || 'Error restaurando matrícula');
      }
    }
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

  if (toggleArchivedStudents) {
    toggleArchivedStudents.addEventListener('change', () => {
      studentsPage = 1;
      applyStudentsFilter();
    });
  }

  if (toggleArchivedEnrollments) {
    toggleArchivedEnrollments.addEventListener('change', () => {
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