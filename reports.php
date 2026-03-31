<?php
require_once __DIR__ . '/app/helpers/auth.php';
$config = require __DIR__ . '/app/config/config.php';
$base_url = $config['base_url'] ?? '';
$u = current_user();
if (!$u) { header('Location: ' . $base_url . '/login.php'); exit; }
$rol = $u['rol'] ?? '';
if (!in_array($rol, ['ADMIN', 'DOCENTE'], true)) { http_response_code(403); die('Sin permisos'); }
include __DIR__ . '/components/header.php';
?>
<section class="card">
  <h2>Módulo de Reportes</h2>
  <p class="muted">
    <?= $rol === 'ADMIN'
      ? 'Crea, actualiza, elimina y exporta reportes académicos y administrativos en PDF.'
      : 'Crea, actualiza, elimina y exporta reportes académicos de notas y asistencia en PDF para tus secciones.' ?>
  </p>
</section>
<section class="card">
  <h3>Crear / actualizar reporte</h3>
  <div class="muted">Guarda una configuración reutilizable y luego exporta el resultado en PDF.</div>
  <form id="reportForm" style="display:grid; grid-template-columns:repeat(3,minmax(220px,1fr)); gap:12px; margin-top:12px;">
    <input type="hidden" id="reportId">
    <label>Título
      <input type="text" id="title" required placeholder="Título del reporte">
    </label>
    <label>Tipo de reporte
      <select id="reportType" required></select>
    </label>
    <label>Año
      <input type="number" id="year" min="2020" max="2100" value="<?= date('Y') ?>">
    </label>
    <label id="sectionWrap">Sección
      <select id="sectionId"></select>
    </label>
    <label id="subjectWrap">Materia
      <input type="text" id="subjectName" placeholder="Opcional">
    </label>
    <label id="monthWrap">Cuota
      <select id="monthKey">
        <option value="">Todas</option>
        <option value="matricula">Matrícula</option><option value="febrero">Febrero</option><option value="marzo">Marzo</option><option value="abril">Abril</option><option value="mayo">Mayo</option><option value="junio">Junio</option><option value="julio">Julio</option><option value="agosto">Agosto</option><option value="septiembre">Septiembre</option><option value="octubre">Octubre</option><option value="noviembre">Noviembre</option><option value="diciembre">Diciembre</option>
      </select>
    </label>
    <label id="paidWrap">Estado de pago
      <select id="isPaid"><option value="">Todos</option><option value="1">Pagados</option><option value="0">Pendientes</option></select>
    </label>
    <label id="dateFromWrap">Desde
      <input type="date" id="dateFrom">
    </label>
    <label id="dateToWrap">Hasta
      <input type="date" id="dateTo">
    </label>
  </form>
  <div style="display:flex; gap:10px; flex-wrap:wrap; margin-top:14px;">
    <button class="btn" id="btnSaveReport">Guardar reporte</button>
    <button class="btn" id="btnPreviewReport" type="button">Vista previa</button>
    <button class="btn" id="btnResetForm" type="button">Limpiar</button>
  </div>
  <div class="muted" id="msgReports"></div>
</section>
<section class="card">
  <h3>Vista previa</h3>
  <div class="muted" id="msgPreview"></div>
  <div style="overflow:auto; margin-top:10px;"><table class="table" id="tblPreview"></table></div>
</section>
<section class="card">
  <h3>Reportes guardados</h3>
  <div class="muted">Desde aquí puedes editar, eliminar o descargar el reporte.</div>
  <div style="overflow:auto; margin-top:10px;"><table class="table" id="tblReports"></table></div>
</section>
<script>
const ROLE = <?= json_encode($rol) ?>;
const BASE = window.__BASE_URL__ || '';
const TYPES = ROLE === 'ADMIN'
  ? [
    ['ACADEMIC_NOTES','Académico - notas'],
    ['ACADEMIC_ATTENDANCE','Académico - asistencia'],
    ['ADMIN_PAYMENTS','Administrativo - pagos'],
    ['ADMIN_ENROLLMENTS_LEVEL','Administrativo - matrículas por nivel'],
    ['ADMIN_ENROLLMENTS_SECTION','Administrativo - matrículas por sección'],
  ]
  : [
    ['ACADEMIC_NOTES','Académico - notas'],
    ['ACADEMIC_ATTENDANCE','Académico - asistencia'],
  ];
let SECTIONS = [];
let REPORTS = [];
function escapeHtml(v){return String(v ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');}
function fillTypes(){ const sel=document.getElementById('reportType'); sel.innerHTML=TYPES.map(([v,l])=>`<option value="${v}">${l}</option>`).join(''); }
function fillSections(){ const sel=document.getElementById('sectionId'); sel.innerHTML='<option value="">Todas / Seleccione</option>'+SECTIONS.map(s=>`<option value="${s.id}">${escapeHtml(s.codigo)} - Nivel ${escapeHtml(s.grado)}</option>`).join(''); }
function toggleFields(){
  const t = document.getElementById('reportType').value;
  const show = (id,yes)=>document.getElementById(id).style.display=yes?'block':'none';
  show('sectionWrap', ['ACADEMIC_NOTES','ACADEMIC_ATTENDANCE'].includes(t));
  show('subjectWrap', t==='ACADEMIC_NOTES');
  show('monthWrap', t==='ADMIN_PAYMENTS');
  show('paidWrap', t==='ADMIN_PAYMENTS');
  show('dateFromWrap', t==='ACADEMIC_ATTENDANCE');
  show('dateToWrap', t==='ACADEMIC_ATTENDANCE');
}
function collectForm(){ return {
  id: document.getElementById('reportId').value,
  title: document.getElementById('title').value.trim(),
  report_type: document.getElementById('reportType').value,
  year: document.getElementById('year').value,
  section_id: document.getElementById('sectionId').value,
  subject_name: document.getElementById('subjectName').value.trim(),
  month_key: document.getElementById('monthKey').value,
  is_paid: document.getElementById('isPaid').value,
  date_from: document.getElementById('dateFrom').value,
  date_to: document.getElementById('dateTo').value,
}; }
async function loadSections(){ const r=await api('reports_sections', { method:'GET' }); SECTIONS = r.data || []; fillSections(); }
async function loadReports(){ const r=await api('reports_list', { method:'GET' }); REPORTS = r.data || []; renderReports(); }
function renderReports(){
  const tbl = document.getElementById('tblReports');
  tbl.innerHTML = '<tr><th>Título</th><th>Tipo</th><th>Actualizado</th><th>Acciones</th></tr>' + REPORTS.map(r=>{
    return `<tr>
      <td>${escapeHtml(r.title)}</td>
      <td>${escapeHtml(r.report_type)}</td>
      <td>${escapeHtml(r.updated_at || r.created_at || '')}</td>
      <td style="display:flex; gap:8px; flex-wrap:wrap;">
        <button class="btn" type="button" onclick="editReport(${r.id})">Editar</button>
        <button class="btn" type="button" onclick="deleteReport(${r.id})">Eliminar</button>
        <a class="btn" href="${BASE}/router.php?action=reports_generate_pdf&id=${r.id}">Descargar</a>
      </td>
    </tr>`;
  }).join('');
}
async function previewReport(){
  const f = collectForm();
  const qs = new URLSearchParams({ action:'reports_preview', ...f });
  const res = await fetch(`${BASE}/router.php?${qs.toString()}`, { credentials:'same-origin' });
  const json = await res.json();
  if(json.status !== 'success'){ document.getElementById('msgPreview').textContent = json.message || 'No se pudo generar la vista previa'; return; }
  const data = json.data || {}; renderPreview(data); document.getElementById('msgPreview').textContent = `Filas: ${(data.rows || []).length}`;
}
function renderPreview(data){ const tbl=document.getElementById('tblPreview'); const headers=data.headers||[]; const rows=data.rows||[]; tbl.innerHTML = (headers.length?`<tr>${headers.map(h=>`<th>${escapeHtml(h)}</th>`).join('')}</tr>`:'') + (rows.length?rows.map(r=>`<tr>${r.map(c=>`<td>${escapeHtml(c)}</td>`).join('')}</tr>`).join(''):'<tr><td class="muted">Sin resultados</td></tr>'); }
async function saveReport(){
  const f = collectForm();
  if(!f.title){ document.getElementById('msgReports').textContent = 'Ingrese un título'; return; }
  const body = new FormData(); Object.entries(f).forEach(([k,v])=>body.append(k,v ?? ''));
  const action = f.id ? 'reports_update' : 'reports_create';
  const r = await api(action, { method:'POST', data: body, isForm: true });
  document.getElementById('msgReports').textContent = r.message || (r.status==='success' ? 'Reporte guardado' : 'No se pudo guardar');
  if(r.status==='success'){ resetForm(); await loadReports(); }
}
function editReport(id){ const r = REPORTS.find(x=>String(x.id)===String(id)); if(!r) return; const f=r.filters||{}; document.getElementById('reportId').value=r.id; document.getElementById('title').value=r.title||''; document.getElementById('reportType').value=r.report_type||''; document.getElementById('year').value=f.year||new Date().getFullYear(); document.getElementById('sectionId').value=f.section_id||''; document.getElementById('subjectName').value=f.subject_name||''; document.getElementById('monthKey').value=f.month_key||''; document.getElementById('isPaid').value=(f.is_paid ?? ''); document.getElementById('dateFrom').value=f.date_from||''; document.getElementById('dateTo').value=f.date_to||''; toggleFields(); window.scrollTo({top:0,behavior:'smooth'}); }
async function deleteReport(id){ if(!confirm('¿Eliminar este reporte?')) return; const body = new FormData(); body.append('id', id); const r = await api('reports_delete', { method:'POST', data: body, isForm: true }); document.getElementById('msgReports').textContent = r.message || (r.status==='success' ? 'Reporte eliminado' : 'No se pudo eliminar'); if(r.status==='success') await loadReports(); }
function resetForm(){ document.getElementById('reportForm').reset(); document.getElementById('reportId').value=''; document.getElementById('year').value=new Date().getFullYear(); fillTypes(); fillSections(); toggleFields(); }
document.getElementById('reportType').addEventListener('change', toggleFields);
document.getElementById('btnSaveReport').addEventListener('click', saveReport);
document.getElementById('btnPreviewReport').addEventListener('click', previewReport);
document.getElementById('btnResetForm').addEventListener('click', resetForm);
(async function init(){ fillTypes(); await loadSections(); resetForm(); await loadReports(); })();
</script>
<?php include __DIR__ . '/components/footer.php'; ?>