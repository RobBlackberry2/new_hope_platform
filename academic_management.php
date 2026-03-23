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
  <h2>Gestión Académica</h2>
  <p class="muted">
    <?php if ($rol === 'ADMIN'): ?>
      Administra profesores guía por sección, define rubros por materia y registra notas por año lectivo.
    <?php else: ?>
      Consulta únicamente los grupos donde eres profesor guía, define rubros y registra notas por estudiante.
    <?php endif; ?>
  </p>
</section>

<?php if ($rol === 'ADMIN'): ?>
<section class="card">
  <div style="display:flex; justify-content:space-between; gap:12px; align-items:end; flex-wrap:wrap;">
    <div>
      <h3 style="margin:0 0 6px 0;">Profesor guía por sección</h3>
      <div class="muted">El filtro de nivel inicia por defecto en 7 para ahorrar espacio, pero puedes volver a “Todos”.</div>
    </div>
    <div style="display:flex; gap:10px; align-items:end; flex-wrap:wrap;">
      <label>Nivel
        <select id="adminGradeFilter"></select>
      </label>
      <label>Sección
        <select id="adminSectionFilter"></select>
      </label>
    </div>
  </div>
  <div class="muted" id="msgAdminSections"></div>
  <div style="overflow:auto;">
    <table class="table" id="tblAdminSections"></table>
  </div>
</section>
<?php endif; ?>

<section class="card">
  <div style="display:flex; justify-content:space-between; gap:12px; align-items:end; flex-wrap:wrap;">
    <div>
      <h3 style="margin:0 0 6px 0;">Secciones disponibles</h3>
      <div class="muted">Selecciona una sección para ver, a la par, los estudiantes y las materias donde se administran los rubros.</div>
    </div>
  </div>
  <div class="muted" id="msgSections"></div>
  <div id="sectionsGrid" class="grid3"></div>
</section>

<section id="sectionWorkspace" style="display:none;">
  <div style="display:grid; grid-template-columns:repeat(2, minmax(320px, 1fr)); gap:16px; align-items:start;">
    <section class="card" id="studentsCard" style="margin:0;">
      <div style="display:flex; justify-content:space-between; gap:12px; align-items:center; flex-wrap:wrap;">
        <div>
          <h3 style="margin:0;">Estudiantes de la sección</h3>
          <div class="muted" id="studentsMeta"></div>
        </div>
      </div>
      <div class="muted" id="msgStudents"></div>
      <div style="overflow:auto; margin-top:10px;">
        <table class="table" id="tblStudents"></table>
      </div>
    </section>

    <section class="card" id="subjectsCard" style="margin:0;">
      <div style="display:flex; justify-content:space-between; gap:12px; align-items:center; flex-wrap:wrap;">
        <div>
          <h3 style="margin:0;">Materias de la sección</h3>
          <div class="muted" id="subjectsMeta"></div>
        </div>
      </div>
      <div class="muted" id="msgSubjects"></div>
      <div style="overflow:auto; margin-top:10px;">
        <table class="table" id="tblSectionSubjects"></table>
      </div>
    </section>
  </div>
</section>

<div id="studentModal" style="display:none; position:fixed; inset:0; background:rgba(15,23,42,.58); z-index:9999; padding:20px;">
  <div class="card" style="max-width:1200px; margin:0 auto; max-height:90vh; overflow:auto;">
    <div style="display:flex; justify-content:space-between; gap:12px; align-items:center; flex-wrap:wrap;">
      <div>
        <h3 style="margin:0;" id="studentModalTitle">Materias del estudiante</h3>
        <div class="muted" id="studentModalMeta"></div>
      </div>
      <div style="display:flex; gap:8px; align-items:end; flex-wrap:wrap;">
        <label>Año lectivo
          <select id="studentYearSelect"></select>
        </label>
        <button class="btn" type="button" id="btnCloseStudentModal">Cerrar</button>
      </div>
    </div>
    <div class="muted" id="msgStudentModal" style="margin-top:10px;"></div>
    <div style="overflow:auto; margin-top:10px;">
      <table class="table" id="tblStudentSubjects"></table>
    </div>
  </div>
</div>

<div id="subjectModal" style="display:none; position:fixed; inset:0; background:rgba(15,23,42,.58); z-index:9999; padding:20px;">
  <div class="card" style="max-width:1200px; margin:0 auto; max-height:90vh; overflow:auto;">
    <div style="display:flex; justify-content:space-between; gap:12px; align-items:center; flex-wrap:wrap;">
      <div>
        <h3 style="margin:0;" id="subjectModalTitle">Submódulo de materia</h3>
        <div class="muted" id="subjectModalMeta"></div>
      </div>
      <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
        <button class="btn" type="button" id="btnSaveGradebook" style="display:none;">Guardar / actualizar notas</button>
        <button class="btn" type="button" id="btnCloseSubjectModal">Cerrar</button>
      </div>
    </div>
    <div class="muted" id="msgSubjectModal" style="margin-top:10px;"></div>
    <div id="subjectSummaryWrap" style="margin-top:12px;"></div>
    <div id="subjectRubricsWrap" style="margin-top:14px;"></div>
    <div id="subjectGradebookWrap" style="overflow:auto; margin-top:16px; display:none;">
      <table class="table" id="tblSubjectGradebook"></table>
    </div>
  </div>
</div>

<script>
const ROLE = <?= json_encode($rol) ?>;
const SUBJECTS = [
  'Artes Plásticas','Biología','Educación Física','Educación Hogar','Español','Filosofía','Física','Francés','Informática','Inglés','Matemáticas','Música','Psicología'
];
let SECTIONS = [];
let CURRENT_SECTION_ID = null;
let CURRENT_SECTION = null;
let CURRENT_STUDENTS = [];
let CURRENT_STUDENT = null;
let CURRENT_SUBJECT = null;
let CURRENT_SUBJECT_MODE = 'rubrics';
let CURRENT_SUBJECT_YEAR = null;
let CURRENT_GRADEBOOK = null;
let ADMIN_TEACHERS = [];

function escapeHtml(v) {
  return String(v ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

function badgeStatus(statusKey, label) {
  const palette = {
    aprobado: 'background:rgba(34,197,94,.15); border:1px solid rgba(34,197,94,.45); color:#166534;',
    aplazado: 'background:rgba(250,204,21,.18); border:1px solid rgba(234,179,8,.45); color:#854d0e;',
    reprobado: 'background:rgba(239,68,68,.14); border:1px solid rgba(239,68,68,.42); color:#991b1b;'
  };
  return `<span style="display:inline-block; padding:5px 10px; border-radius:999px; font-weight:700; font-size:12px; ${palette[statusKey] || palette.aplazado}">${escapeHtml(label)}</span>`;
}

function sectionCard(section) {
  return `
    <button type="button" class="card" data-kind="open-section" data-id="${section.id}" style="text-align:left; cursor:pointer; width:100%;">
      <h3 style="margin-bottom:8px;">Sección ${escapeHtml(section.codigo)}</h3>
      <div class="muted">Nivel ${escapeHtml(section.grado)}</div>
      <div class="muted">Profesor guía: ${section.docente_nombre ? escapeHtml(section.docente_nombre) : 'Sin asignar'}</div>
      <div class="muted">${Number(section.total_estudiantes || 0)} estudiante(s)</div>
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
    </tr>
  `;
}

function fillAdminFilters() {
  if (ROLE !== 'ADMIN') return;
  const gradeSel = document.getElementById('adminGradeFilter');
  const sectionSel = document.getElementById('adminSectionFilter');
  const grades = [...new Set(SECTIONS.map(s => String(s.grado || '')).filter(Boolean))].sort((a,b) => Number(a) - Number(b));
  const alreadyLoaded = gradeSel.dataset.loaded === '1';
  const currentGrade = alreadyLoaded ? (gradeSel.value || '') : (grades.includes('7') ? '7' : '');
  gradeSel.innerHTML = '<option value="">Todos</option>' + grades.map(g => `<option value="${g}">${g}</option>`).join('');
  gradeSel.value = grades.includes(currentGrade) ? currentGrade : '';
  gradeSel.dataset.loaded = '1';
  fillAdminSectionFilter();
  sectionSel.value = '';
}

function fillAdminSectionFilter() {
  if (ROLE !== 'ADMIN') return;
  const gradeSel = document.getElementById('adminGradeFilter');
  const sectionSel = document.getElementById('adminSectionFilter');
  const selectedGrade = gradeSel?.value || '';
  const availableSections = SECTIONS.filter(s => !selectedGrade || String(s.grado) === String(selectedGrade));
  const currentSection = sectionSel?.value || '';
  sectionSel.innerHTML = '<option value="">Todas</option>' + availableSections.map(s => `<option value="${s.id}">${escapeHtml(s.codigo)}</option>`).join('');
  if (availableSections.some(s => String(s.id) === String(currentSection))) {
    sectionSel.value = currentSection;
  }
}

function studentRow(student) {
  return `
    <tr>
      <td>${escapeHtml(student.nombre_completo || student.nombre || '')}</td>
      <td>${escapeHtml(student.cedula || '')}</td>
      <td>${escapeHtml(student.seccion || '')}</td>
      <td><button class="btn" type="button" data-kind="view-student" data-id="${student.id}">Ver estudiante</button></td>
    </tr>
  `;
}

function sectionSubjectRow(subjectName) {
  return `
    <tr>
      <td>${escapeHtml(subjectName)}</td>
      <td><button class="btn" type="button" data-kind="open-rubrics" data-subject="${escapeHtml(subjectName)}">Rubros</button></td>
    </tr>
  `;
}

function subjectRow(item) {
  return `
    <tr>
      <td>${escapeHtml(item.subject_name)}</td>
      <td>${Number(item.trimester_1).toFixed(2)}</td>
      <td>${Number(item.trimester_2).toFixed(2)}</td>
      <td>${Number(item.trimester_3).toFixed(2)}</td>
      <td>${Number(item.final_average).toFixed(2)}</td>
      <td>${badgeStatus(item.status_key, item.status_label)}</td>
      <td>${item.convocatoria_1 !== null ? Number(item.convocatoria_1).toFixed(2) : '<span class="muted">—</span>'}</td>
      <td>${item.convocatoria_2 !== null ? Number(item.convocatoria_2).toFixed(2) : '<span class="muted">—</span>'}</td>
      <td><button class="btn" type="button" data-kind="open-student-subject" data-subject="${escapeHtml(item.subject_name)}">Registrar notas</button></td>
    </tr>
  `;
}

function rubricBlock(trimester, rubrics) {
  const total = rubrics.reduce((acc, item) => acc + Number(item.percentage_value || 0), 0);
  return `
    <div class="card" style="margin-bottom:12px;">
      <div style="display:flex; justify-content:space-between; gap:10px; align-items:center; flex-wrap:wrap;">
        <h4 style="margin:0;">Trimestre ${trimester}</h4>
        <div class="muted">Total actual: ${total.toFixed(2)}%</div>
      </div>
      <div style="overflow:auto; margin-top:10px;">
        <table class="table">
          <tr><th>Rubro</th><th>%</th><th>Acciones</th></tr>
          ${rubrics.map(r => `
            <tr>
              <td>${escapeHtml(r.rubric_name)}</td>
              <td>${Number(r.percentage_value).toFixed(2)}%</td>
              <td>
                <button class="btn" type="button" data-kind="edit-rubric" data-trimester="${trimester}" data-id="${r.id}" data-name="${escapeHtml(r.rubric_name)}" data-percentage="${r.percentage_value}">Editar</button>
                <button class="btn" type="button" data-kind="delete-rubric" data-id="${r.id}">Eliminar</button>
              </td>
            </tr>
          `).join('') || '<tr><td colspan="3" class="muted">No hay rubros definidos.</td></tr>'}
        </table>
      </div>
      <div style="display:flex; gap:8px; align-items:end; flex-wrap:wrap; margin-top:10px;">
        <label>Nombre
          <input type="text" id="rubricName${trimester}" placeholder="Ej. Examen">
        </label>
        <label>Porcentaje
          <input type="number" id="rubricPct${trimester}" min="0.01" max="100" step="0.01" placeholder="0.00">
        </label>
        <button class="btn" type="button" data-kind="add-rubric" data-trimester="${trimester}">Agregar rubro</button>
      </div>
    </div>
  `;
}

function buildGradebookTable(payload) {
  const rubricsByTrimester = payload.rubrics || {};
  const rows = payload.data || [];
  const grouped = {};

  rows.forEach(row => {
    const sid = row.student_id;
    if (!grouped[sid]) {
      grouped[sid] = {
        student_id: row.student_id,
        student_nombre: row.student_nombre,
        cedula: row.cedula,
        scores: {}
      };
    }
    if (row.rubric_id) {
      grouped[sid].scores[`${row.trimester}_${row.rubric_id}`] = row.score ?? '';
    }
  });

  const students = Object.values(grouped);
  const headers = ['<th>Estudiante</th><th>Cédula</th>'];
  [1,2,3].forEach(t => {
    (rubricsByTrimester[t] || []).forEach(r => {
      headers.push(`<th>T${t} · ${escapeHtml(r.rubric_name)} (${Number(r.percentage_value).toFixed(2)}%)</th>`);
    });
  });

  const bodyRows = students.map(st => {
    let cols = `<td>${escapeHtml(st.student_nombre)}</td><td>${escapeHtml(st.cedula || '')}</td>`;
    [1,2,3].forEach(t => {
      (rubricsByTrimester[t] || []).forEach(r => {
        const key = `${t}_${r.id}`;
        const val = st.scores[key] ?? '';
        cols += `<td><input type="number" min="0" max="100" step="0.01" data-kind="grade" data-student="${st.student_id}" data-trimester="${t}" data-rubric="${r.id}" value="${escapeHtml(val)}" style="width:95px;"></td>`;
      });
    });
    return `<tr>${cols}</tr>`;
  }).join('');

  return `<tr>${headers.join('')}</tr>${bodyRows || '<tr><td colspan="999" class="muted">No hay rubros o notas registradas.</td></tr>'}`;
}

function buildRecoveryFields(summary = null) {
  const isAplazado = summary?.status_key === 'aplazado';
  const conv1 = summary?.convocatoria_1;
  const conv2 = summary?.convocatoria_2;
  if (!summary) return '';
  return `
    <div class="card" style="margin-bottom:12px;">
      <div style="display:flex; justify-content:space-between; gap:12px; align-items:center; flex-wrap:wrap;">
        <div>
          <h4 style="margin:0 0 6px 0;">Resultado de la materia</h4>
          <div class="muted">Promedio final: ${Number(summary.final_average || 0).toFixed(2)} · ${badgeStatus(summary.status_key, summary.status_label)}</div>
        </div>
      </div>
      <div style="display:flex; gap:10px; align-items:end; flex-wrap:wrap; margin-top:10px; ${isAplazado ? '' : 'display:none;'}" id="recoveryFieldsWrap">
        <label>Convocatoria I
          <input type="number" min="0" max="100" step="0.01" id="convocatoria1Input" value="${conv1 !== null ? escapeHtml(conv1) : ''}">
        </label>
        <label>Convocatoria II
          <input type="number" min="0" max="100" step="0.01" id="convocatoria2Input" value="${conv2 !== null ? escapeHtml(conv2) : ''}">
        </label>
      </div>
      ${!isAplazado ? '<div class="muted" style="margin-top:10px;">Las convocatorias solo se habilitan cuando el estado es aplazado.</div>' : ''}
    </div>
  `;
}

async function loadSections() {
  const msg = document.getElementById('msgSections');
  const grid = document.getElementById('sectionsGrid');
  msg.textContent = 'Cargando...';
  try {
    const res = await api('academic_sections', { method: 'GET' });
    SECTIONS = res.data || [];
    grid.innerHTML = SECTIONS.map(sectionCard).join('');
    fillAdminFilters();
    msg.textContent = SECTIONS.length ? '' : 'No hay secciones disponibles.';
  } catch (err) {
    msg.textContent = err?.json?.message || 'Error cargando secciones';
  }
}

async function loadAdminSectionTable() {
  if (ROLE !== 'ADMIN') return;
  const msg = document.getElementById('msgAdminSections');
  const tbl = document.getElementById('tblAdminSections');
  msg.textContent = 'Cargando...';
  try {
    if (!ADMIN_TEACHERS.length) {
      const teachersRes = await api('users_list_docentes', { method: 'GET', params: { limit: 500 } });
      ADMIN_TEACHERS = teachersRes.data || [];
    }
    const gradeFilter = document.getElementById('adminGradeFilter')?.value || '';
    const sectionFilter = document.getElementById('adminSectionFilter')?.value || '';
    const filtered = SECTIONS.filter(s => {
      if (gradeFilter && String(s.grado) !== String(gradeFilter)) return false;
      if (sectionFilter && String(s.id) !== String(sectionFilter)) return false;
      return true;
    });
    tbl.innerHTML = `<tr><th>Sección</th><th>Nivel</th><th>Profesor guía</th><th>Asignado</th></tr>` +
      (filtered.map(s => adminSectionRow(s, ADMIN_TEACHERS)).join('') || '<tr><td colspan="4" class="muted">No hay secciones para ese filtro.</td></tr>');
    msg.textContent = '';
  } catch (err) {
    msg.textContent = err?.json?.message || 'Error cargando profesores';
  }
}

function enrollmentStyleYearOptions(firstYear, selectedYear) {
  const currentYear = new Date().getFullYear();
  const startYear = Number(firstYear || selectedYear || currentYear);
  const chosenYear = Number(selectedYear || currentYear);
  let html = '';
  for (let y = startYear; y <= currentYear; y++) {
    html += `<option value="${y}" ${y === chosenYear ? 'selected' : ''}>${y}</option>`;
  }
  return html;
}

async function openSection(sectionId) {
  CURRENT_SECTION_ID = sectionId;
  CURRENT_SECTION = SECTIONS.find(s => String(s.id) === String(sectionId)) || null;
  document.getElementById('sectionWorkspace').style.display = 'block';
  await Promise.all([loadSectionStudents(), loadSectionSubjects()]);
}

async function loadSectionStudents() {
  if (!CURRENT_SECTION_ID) return;
  const msg = document.getElementById('msgStudents');
  const tbl = document.getElementById('tblStudents');
  const meta = document.getElementById('studentsMeta');
  msg.textContent = 'Cargando estudiantes...';
  tbl.innerHTML = '';
  try {
    const res = await api('academic_section_students', { method: 'GET', params: { section_id: CURRENT_SECTION_ID } });
    CURRENT_STUDENTS = res.data || [];
    const section = res.section || CURRENT_SECTION || {};
    CURRENT_SECTION = section;
    meta.textContent = `Sección ${section.codigo || ''} · Nivel ${section.grado || ''}`;
    tbl.innerHTML = `<tr><th>Nombre</th><th>Cédula</th><th>Sección</th><th>Acciones</th></tr>` + (CURRENT_STUDENTS.map(studentRow).join('') || '<tr><td colspan="4" class="muted">No hay estudiantes registrados en la sección seleccionada.</td></tr>');
    msg.textContent = '';
  } catch (err) {
    msg.textContent = err?.json?.message || 'Error cargando estudiantes';
  }
}

async function loadSectionSubjects() {
  const msg = document.getElementById('msgSubjects');
  const meta = document.getElementById('subjectsMeta');
  const tbl = document.getElementById('tblSectionSubjects');
  meta.textContent = CURRENT_SECTION ? `Sección ${CURRENT_SECTION.codigo} · Rubros comunes por materia` : 'Materias estándar';
  msg.textContent = '';
  tbl.innerHTML = `<tr><th>Materia</th><th>Rubros</th></tr>` + SUBJECTS.map(sectionSubjectRow).join('');
}

async function renderStudentSubjects(studentId, yearOverride = null) {
  const year = yearOverride || document.getElementById('studentYearSelect').value;
  const msg = document.getElementById('msgStudentModal');
  const tbl = document.getElementById('tblStudentSubjects');
  const meta = document.getElementById('studentModalMeta');
  msg.textContent = 'Cargando...';
  tbl.innerHTML = '';
  try {
    const subjectRes = await api('academic_student_subjects', { method: 'GET', params: { student_id: studentId, year } });
    meta.textContent = `Año consultado: ${year}.`;
    tbl.innerHTML = `<tr><th>Materia</th><th>I Trimestre</th><th>II Trimestre</th><th>III Trimestre</th><th>Promedio final</th><th>Estado</th><th>Conv. I</th><th>Conv. II</th><th>Notas</th></tr>` + (subjectRes.data || []).map(subjectRow).join('');
    msg.textContent = '';
  } catch (err) {
    msg.textContent = err?.json?.message || 'Error cargando materias';
  }
}

async function openStudent(studentId) {
  const modal = document.getElementById('studentModal');
  const title = document.getElementById('studentModalTitle');
  const msg = document.getElementById('msgStudentModal');
  const yearSelect = document.getElementById('studentYearSelect');
  CURRENT_STUDENT = CURRENT_STUDENTS.find(x => String(x.id) === String(studentId)) || null;
  modal.style.display = 'block';
  title.textContent = `Materias de ${CURRENT_STUDENT?.nombre_completo || CURRENT_STUDENT?.nombre || 'estudiante'}`;
  msg.textContent = 'Cargando...';

  try {
    const yearsRes = await api('academic_student_years', { method: 'GET', params: { student_id: studentId } });
    const years = (yearsRes.data || []).map(Number).sort((a,b) => a-b);
    const currentYear = new Date().getFullYear();
    const firstYear = years.length ? years[0] : currentYear;
    const selectedYear = years.includes(currentYear) ? currentYear : (years.length ? years[years.length - 1] : currentYear);
    yearSelect.innerHTML = enrollmentStyleYearOptions(firstYear, selectedYear);
    yearSelect.value = String(selectedYear);
    await renderStudentSubjects(studentId, selectedYear);
  } catch (err) {
    msg.textContent = err?.json?.message || 'Error cargando materias';
  }
}

async function openSubjectRubrics(subjectName) {
  const modal = document.getElementById('subjectModal');
  const title = document.getElementById('subjectModalTitle');
  const meta = document.getElementById('subjectModalMeta');
  const msg = document.getElementById('msgSubjectModal');
  CURRENT_SUBJECT = subjectName;
  CURRENT_SUBJECT_MODE = 'rubrics';
  modal.style.display = 'block';
  document.getElementById('btnSaveGradebook').style.display = 'none';
  document.getElementById('subjectGradebookWrap').style.display = 'none';
  document.getElementById('subjectSummaryWrap').innerHTML = '';
  document.getElementById('subjectRubricsWrap').style.display = 'block';
  title.textContent = `Rubros de la materia: ${subjectName}`;
  meta.textContent = CURRENT_SECTION ? `Sección ${CURRENT_SECTION.codigo} · Nivel ${CURRENT_SECTION.grado}` : 'Configuración de rubros';
  msg.textContent = 'Cargando rubros...';

  try {
    const res = await api('academic_subject_detail', { method: 'GET', params: { section_id: CURRENT_SECTION_ID, year: new Date().getFullYear(), subject_name: subjectName } });
    CURRENT_GRADEBOOK = res;
    document.getElementById('subjectRubricsWrap').innerHTML = [1,2,3].map(t => rubricBlock(t, res.rubrics?.[t] || [])).join('');
    document.getElementById('tblSubjectGradebook').innerHTML = '';
    msg.textContent = '';
  } catch (err) {
    msg.textContent = err?.json?.message || 'Error cargando el submódulo';
  }
}

async function openStudentSubject(subjectName) {
  const year = document.getElementById('studentYearSelect').value;
  const modal = document.getElementById('subjectModal');
  const title = document.getElementById('subjectModalTitle');
  const meta = document.getElementById('subjectModalMeta');
  const msg = document.getElementById('msgSubjectModal');
  CURRENT_SUBJECT = subjectName;
  CURRENT_SUBJECT_MODE = 'student';
  CURRENT_SUBJECT_YEAR = year;
  modal.style.display = 'block';
  document.getElementById('btnSaveGradebook').style.display = 'inline-flex';
  document.getElementById('subjectGradebookWrap').style.display = 'block';
  document.getElementById('subjectRubricsWrap').style.display = 'none';
  title.textContent = `Notas de ${subjectName}`;
  meta.textContent = `${CURRENT_STUDENT?.nombre_completo || CURRENT_STUDENT?.nombre || 'Estudiante'} · Año lectivo ${year}`;
  msg.textContent = 'Cargando rubros y notas...';

  try {
    const res = await api('academic_subject_detail', { method: 'GET', params: { section_id: CURRENT_SECTION_ID, year, subject_name: subjectName, student_id: CURRENT_STUDENT.id } });
    CURRENT_GRADEBOOK = res;
    document.getElementById('subjectSummaryWrap').innerHTML = buildRecoveryFields(res.summary || null);
    document.getElementById('subjectRubricsWrap').innerHTML = '';
    document.getElementById('tblSubjectGradebook').innerHTML = buildGradebookTable(res);
    msg.textContent = '';
  } catch (err) {
    msg.textContent = err?.json?.message || 'Error cargando el submódulo';
  }
}

async function saveRubric(trimester, rubricId = '') {
  const nameInput = document.getElementById(`rubricName${trimester}`);
  const pctInput = document.getElementById(`rubricPct${trimester}`);
  const name = nameInput?.value?.trim();
  const percentage = pctInput?.value;
  if (!name || !percentage) {
    alert('Completa nombre y porcentaje del rubro.');
    return;
  }
  const msg = document.getElementById('msgSubjectModal');
  msg.textContent = 'Guardando rubro...';
  try {
    await api('academic_rubric_save', {
      method: 'POST',
      data: { subject_name: CURRENT_SUBJECT, trimester, rubric_id: rubricId, rubric_name: name, percentage_value: percentage }
    });
    nameInput.value = '';
    pctInput.value = '';
    if (CURRENT_SUBJECT_MODE === 'student') {
      await openStudentSubject(CURRENT_SUBJECT);
    } else {
      await openSubjectRubrics(CURRENT_SUBJECT);
    }
    msg.textContent = 'Rubro guardado.';
  } catch (err) {
    msg.textContent = err?.json?.message || 'Error guardando el rubro';
  }
}

async function deleteRubric(rubricId) {
  const msg = document.getElementById('msgSubjectModal');
  msg.textContent = 'Eliminando rubro...';
  try {
    await api('academic_rubric_delete', { method: 'POST', data: { rubric_id: rubricId } });
    if (CURRENT_SUBJECT_MODE === 'student') {
      await openStudentSubject(CURRENT_SUBJECT);
    } else {
      await openSubjectRubrics(CURRENT_SUBJECT);
    }
    msg.textContent = 'Rubro eliminado.';
  } catch (err) {
    msg.textContent = err?.json?.message || 'Error eliminando el rubro';
  }
}

async function saveGradebook() {
  if (!CURRENT_STUDENT) return;
  const year = CURRENT_SUBJECT_YEAR || document.getElementById('studentYearSelect').value;
  const inputs = Array.from(document.querySelectorAll('#tblSubjectGradebook input[data-kind="grade"]'));
  const items = inputs
    .filter(i => String(i.value).trim() !== '')
    .map(i => ({
      student_id: i.dataset.student,
      trimester: i.dataset.trimester,
      rubric_id: i.dataset.rubric,
      score: i.value
    }));

  const conv1 = document.getElementById('convocatoria1Input')?.value ?? '';
  const conv2 = document.getElementById('convocatoria2Input')?.value ?? '';

  const msg = document.getElementById('msgSubjectModal');
  msg.textContent = 'Guardando notas...';
  try {
    await api('academic_grades_save', {
      method: 'POST',
      data: { subject_name: CURRENT_SUBJECT, year, student_id: CURRENT_STUDENT.id, items: JSON.stringify(items), convocatoria_1: conv1, convocatoria_2: conv2 }
    });
    await openStudentSubject(CURRENT_SUBJECT);
    await renderStudentSubjects(CURRENT_STUDENT.id, year);
    msg.textContent = 'Notas actualizadas correctamente.';
  } catch (err) {
    msg.textContent = err?.json?.message || 'Error guardando notas';
  }
}

function closeModal(id) {
  document.getElementById(id).style.display = 'none';
}

document.addEventListener('click', async (e) => {
  const openSectionBtn = e.target.closest('[data-kind="open-section"]');
  if (openSectionBtn) return openSection(openSectionBtn.dataset.id);

  const viewStudentBtn = e.target.closest('[data-kind="view-student"]');
  if (viewStudentBtn) return openStudent(viewStudentBtn.dataset.id);

  const openRubricsBtn = e.target.closest('[data-kind="open-rubrics"]');
  if (openRubricsBtn) return openSubjectRubrics(openRubricsBtn.dataset.subject);

  const openStudentSubjectBtn = e.target.closest('[data-kind="open-student-subject"]');
  if (openStudentSubjectBtn) return openStudentSubject(openStudentSubjectBtn.dataset.subject);

  const addRubricBtn = e.target.closest('[data-kind="add-rubric"]');
  if (addRubricBtn) return saveRubric(addRubricBtn.dataset.trimester);

  const editRubricBtn = e.target.closest('[data-kind="edit-rubric"]');
  if (editRubricBtn) {
    const name = prompt('Nombre del rubro', editRubricBtn.dataset.name || '');
    if (name === null) return;
    const percentage = prompt('Porcentaje del rubro', editRubricBtn.dataset.percentage || '0');
    if (percentage === null) return;
    document.getElementById(`rubricName${editRubricBtn.dataset.trimester}`).value = name;
    document.getElementById(`rubricPct${editRubricBtn.dataset.trimester}`).value = percentage;
    return saveRubric(editRubricBtn.dataset.trimester, editRubricBtn.dataset.id);
  }

  const deleteRubricBtn = e.target.closest('[data-kind="delete-rubric"]');
  if (deleteRubricBtn) return deleteRubric(deleteRubricBtn.dataset.id);
});

document.addEventListener('change', async (e) => {
  const teacherSel = e.target.closest('select[data-kind="teacher"]');
  if (teacherSel) {
    const msg = document.getElementById('msgAdminSections');
    msg.textContent = 'Guardando asignación...';
    try {
      await api('attendance_assign_teacher', { method: 'POST', data: { section_id: teacherSel.dataset.id, teacher_user_id: teacherSel.value } });
      msg.textContent = 'Profesor guía actualizado.';
      await loadSections();
      await loadAdminSectionTable();
    } catch (err) {
      msg.textContent = err?.json?.message || 'Error guardando asignación';
    }
    return;
  }

  if (e.target.id === 'adminGradeFilter') {
    fillAdminSectionFilter();
    await loadAdminSectionTable();
    return;
  }

  if (e.target.id === 'adminSectionFilter') {
    await loadAdminSectionTable();
  }
});

document.getElementById('btnCloseStudentModal').addEventListener('click', () => closeModal('studentModal'));
document.getElementById('btnCloseSubjectModal').addEventListener('click', () => closeModal('subjectModal'));
document.getElementById('btnSaveGradebook').addEventListener('click', saveGradebook);
document.getElementById('studentYearSelect').addEventListener('change', async (e) => { if (CURRENT_STUDENT) await renderStudentSubjects(CURRENT_STUDENT.id, e.target.value); });
document.getElementById('studentModal').addEventListener('click', (e) => { if (e.target.id === 'studentModal') closeModal('studentModal'); });
document.getElementById('subjectModal').addEventListener('click', (e) => { if (e.target.id === 'subjectModal') closeModal('subjectModal'); });

loadSections().then(() => {
  if (ROLE === 'ADMIN') loadAdminSectionTable();
});
</script>

<?php include __DIR__ . '/components/footer.php'; ?>
