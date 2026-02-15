<?php
require_once __DIR__ . '/app/helpers/auth.php';
$config = require __DIR__ . '/app/config/config.php';
$base_url = $config['base_url'] ?? '';
$u = current_user();
if (!$u) { header('Location: ' . $base_url . '/login.php'); exit; }
include __DIR__ . '/components/header.php';
$rol = $u['rol'] ?? '';
?>
<section class="card">
  <h2>E-Learning</h2>
  <p class="muted">Cursos, secciones y archivos. (Evaluaciones/foros/gamificación después)</p>
</section>

<?php if ($rol === 'ADMIN'): ?>
<section class="card">
  <h3>Crear curso</h3>
  <form id="formCourse" class="grid2">
    <label>Nombre<input name="nombre" required /></label>
    <label>Grado (7-11)<input name="grado" type="number" min="7" max="11" value="7" /></label>
    <label>Sección<input name="seccion" required /></label>
    <label style="grid-column:1/-1">Descripción<textarea name="descripcion" rows="3"></textarea></label>
    <?php if ($rol === 'ADMIN'): ?>
      <label>Docente (user_id)<input name="docente_user_id" type="number" min="1" placeholder="(opcional)" /></label>
    <?php endif; ?>
    <button class="btn" type="submit">Crear</button>
    <div id="msgCourse" class="muted"></div>
  </form>
</section>
<?php endif; ?>

<section class="grid2">
  <div class="card">
    <h3>Mis cursos</h3>
    <div id="courses" class="muted">Cargando...</div>
  </div>

  <div class="card">
    <h3>Detalle</h3>
    <div id="detail" class="muted">Selecciona un curso.</div>
  </div>
</section>

<script>
let currentCourse = null;
let currentSections = [];
const MAX_WEEKS = 40; //Aqui se modificaria el numero de semanas
// { [courseId: string]: Set<number> }
const selectedWeekByCourse = {};
const IS_ADMIN = <?php echo json_encode($rol === 'ADMIN'); ?>;

function escapeHtml(v){
  return String(v ?? '')
    .replace(/&/g,'&amp;')
    .replace(/</g,'&lt;')
    .replace(/>/g,'&gt;')
    .replace(/"/g,'&quot;')
    .replace(/'/g,'&#39;');
}

function getSelectedWeek(courseId){
  const id = String(courseId);
  if (!selectedWeekByCourse[id]) selectedWeekByCourse[id] = 1; 
  return selectedWeekByCourse[id];
}

function courseCard(c){
  const delBtn = IS_ADMIN
    ? `<button class="btn" data-kind="deleteCourse" data-id="${c.id}">Eliminar</button>`
    : '';

  return `<div class="card" style="text-align:left; width:100%; margin:8px 0;">
    <div style="display:flex; justify-content:space-between; gap:8px; align-items:flex-start;">
      <div style="flex:1;">
        <div><strong>${c.nombre}</strong></div>
        <div class="muted">Grado: ${c.grado} — Docente: ${c.docente_nombre||''}</div>
        <div class="muted">${(c.descripcion||'').replace(/</g,'&lt;')}</div>
        <div class="muted">${c.seccion}</div>
      </div>

      <div style="display:flex; gap:8px;">
        <button class="btn" data-kind="select" data-id="${c.id}">Abrir</button>
        ${delBtn}
      </div>
    </div>
  </div>`;
}


async function loadCourses(){
  const el = document.getElementById('courses');
  el.textContent = 'Cargando...';
  try {
    const j = await api('courses_list', { method:'GET', params:{limit:200} });
    const data = j.data || [];
    if (!data.length) { el.textContent = 'No hay cursos aún.'; return; }
    el.innerHTML = data.map(courseCard).join('');
  } catch (err){
    el.textContent = err?.json?.message || 'Error cargando cursos';
  }
}

function buildWeekFilterHtml(courseId){
  const selected = getSelectedWeek(courseId);

  const options = Array.from({length: MAX_WEEKS}, (_,i)=>{
    const w = i + 1;
    const sel = (w === selected) ? 'selected' : '';
    return `<option value="${w}" ${sel}>Semana ${w}</option>`;
  }).join('');

  return `
    <div style="display:flex; justify-content:space-between; align-items:center; gap:10px;">
      <strong>Semana</strong>
      <div style="display:flex; align-items:center; gap:8px;">
        <select class="btn" style="padding:6px 10px;" data-kind="weekSelect">
          ${options}
        </select>
      </div>
    </div>
  `;
}

function sectionCardHtml(s){
  const titulo = escapeHtml(s.titulo);
  const descripcion = escapeHtml(s.descripcion || '');
  const semana = escapeHtml(s.semana);
  const orden = escapeHtml(s.orden);

  return `<div class="card" style="margin:8px 0;">
    <div style="display:flex; justify-content:space-between; gap:8px; align-items:center;">
      <strong>${s.titulo}</strong>
      <div style="display:flex; gap:8px; align-items:center;">
        <button class="btn" data-kind="loadRes" data-section="${s.id}">Ver archivos</button>
        <?php if ($rol==='ADMIN' || $rol==='DOCENTE'): ?>
          <button class="btn" data-kind="deleteSection" data-section="${s.id}">Eliminar</button>
        <?php endif; ?>
      </div>
    </div>

    ${descripcion ? `<p>${descripcion}</p>` : ''}

    <div class="muted">Semana: ${semana} — Orden: ${orden}</div>

    <div id="res_${s.id}" class="muted" style="margin-top:6px;"></div>

    <div style="margin-top:8px;">
      <form data-kind="upload" data-section="${s.id}">
        <input type="file" name="file" required />
        <button class="btn" type="submit">Subir</button>
      </form>
    </div>
  </div>`;
}

function renderSectionsHtml(courseId, sections){
  const selectedWeek = Number(getSelectedWeek(courseId));

  const filtered = (sections || [])
    .map(s=>{
      const week = Number(s.semana) || 0;
      const order = Number(s.orden) || 0;
      return { ...s, _week: week, _order: order };
    })
    .filter(s => s._week === selectedWeek);

  if (!filtered.length) {
    return `<div class="muted">No hay secciones para la semana ${escapeHtml(selectedWeek)}.</div>`;
  }

  filtered.sort((a,b)=>
    (a._order - b._order) ||
    (a.id - b.id)
  );

  // Ya no agrupamos por semana porque solo se ve una
  return filtered.map(sectionCardHtml).join('');
}

function refreshDetailSections(){
  if (!currentCourse) return;
  const courseId = String(currentCourse);

  const weekFilterEl = document.getElementById('weekFilter');
  if (weekFilterEl) weekFilterEl.innerHTML = buildWeekFilterHtml(courseId);

  const sectionsEl = document.getElementById('sectionsContainer');
  if (sectionsEl) sectionsEl.innerHTML = renderSectionsHtml(courseId, currentSections);
}

async function loadDetail(courseId){
  currentCourse = courseId;
  const d = document.getElementById('detail');
  d.textContent = 'Cargando detalle...';

  try {
    const sec = await api('sections_list', { method:'GET', params:{course_id: courseId} });
    currentSections = sec.data || [];

    const createSectionHtml = `<?php if ($rol==='ADMIN' || $rol==='DOCENTE'): ?>
      <hr class="sep" />
      <h4>Crear sección</h4>
      <form id="formSection" class="grid">
        <label>Título<input name="titulo" required /></label>
        <label>Descripcion<input name="descripcion"/></label>
        <label>Semana<input name="semana" type="number" value="1" /></label>
        <label>Orden<input name="orden" type="number" value="0" /></label>
        <button class="btn" type="submit">Crear sección</button>
        <div id="msgSection" class="muted"></div>
      </form>
    <?php else: ?>
      <hr class="sep" />
      <div class="muted">Solo docentes/admin pueden crear secciones y subir archivos.</div>
    <?php endif; ?>`;

    // Estructura fija (así el filtro puede re-renderizar SOLO la lista)
    d.innerHTML = `
      <div class="muted">Curso #${escapeHtml(courseId)}</div>
      <div id="weekFilter" class="card" style="margin:8px 0; padding:10px;"></div>
      <div id="sectionsContainer"></div>
    ` + createSectionHtml;

    refreshDetailSections();

    const formSection = document.getElementById('formSection');
    if (formSection) {
      formSection.addEventListener('submit', async (e)=>{
        e.preventDefault();
        const fd = new FormData(e.target);
        fd.append('course_id', String(courseId));
        const msg = document.getElementById('msgSection');
        msg.textContent='';
        try {
          await api('sections_create', { data: fd, isForm:true });
          msg.textContent='Sección creada.';
          e.target.reset();
          await loadDetail(courseId); // refetch
        } catch (err){
          msg.textContent = err?.json?.message || 'Error creando sección';
        }
      });
    }

  } catch (err){
    d.textContent = err?.json?.message || 'Error cargando detalle';
  }
}

document.getElementById('courses').addEventListener('click', async (e)=>{
  const el = e.target.closest('[data-kind]');
  if (!el) return;

  const kind = el.getAttribute('data-kind');

  if (kind === 'deleteCourse') {
    const courseId = el.getAttribute('data-id');
    const ok = confirm('¿Seguro que deseas eliminar este curso? (Esto borra secciones y archivos)');
    if (!ok) return;

    try {
      const fd = new FormData();
      fd.append('id', String(courseId));
      await api('courses_delete', { data: fd, isForm: true });

      alert('Curso eliminado');
      // refrescar lista y limpiar detalle si estabas viendo ese curso
      if (String(currentCourse) === String(courseId)) {
        document.getElementById('detail').textContent = 'Selecciona un curso.';
        currentCourse = null;
      }
      await loadCourses();
    } catch (err){
      alert(err?.json?.message || 'Error eliminando curso');
    }
    return;
  }

  if (kind === 'select') {
    await loadDetail(el.getAttribute('data-id'));
  }
});


document.getElementById('detail').addEventListener('change', (e)=>{
  const sel = e.target.closest('select[data-kind="weekSelect"]');
  if (!sel || !currentCourse) return;

  const week = Number(sel.value) || 1;
  selectedWeekByCourse[String(currentCourse)] = week;

  refreshDetailSections();
});


document.getElementById('detail').addEventListener('click', async (e)=>{
  const el = e.target.closest('[data-kind]');
  if (!el) return;

  const kind = el.getAttribute('data-kind');

  if (kind === 'deleteSection') {
    const sectionId = el.getAttribute('data-section');
    if (!sectionId || !currentCourse) return;

    const ok = confirm('¿Seguro que deseas eliminar esta sección?');
    if (!ok) return;

    try {
      const fd = new FormData();
      fd.append('id', String(sectionId));
      await api('sections_delete', { data: fd, isForm: true });

      alert('Sección eliminada');
      await loadDetail(currentCourse); // refresca la lista
    } catch (err){
      alert(err?.json?.message || 'Error eliminando la sección');
    }
    return;
  }

  if (kind === 'loadRes') {
    const sectionId = el.getAttribute('data-section');
    const box = document.getElementById('res_' + sectionId);
    box.textContent='Cargando archivos...';
    try {
      const j = await api('resources_list', { method:'GET', params:{section_id: sectionId} });
      const files = j.data||[];
      if (!files.length) { box.textContent='Sin archivos.'; return; }
      box.innerHTML = files.map(f=>{
        const href = window.__BASE_URL__ + '/uploads/' + (f.stored_name||'');
        return `<div><a class="link" href="${href}" target="_blank">${f.original_name}</a> <span class="muted">(${Math.round((f.size||0)/1024)} KB)</span></div>`;
      }).join('');
    } catch (err){
      box.textContent = err?.json?.message || 'Error';
    }
  }
});


document.getElementById('detail').addEventListener('submit', async (e)=>{
  const form = e.target;
  if (!form.matches('form[data-kind="upload"]')) return;
  e.preventDefault();
  if (!currentCourse) return;
  const sectionId = form.getAttribute('data-section');
  const fd = new FormData(form);
  fd.append('course_id', String(currentCourse));
  fd.append('section_id', String(sectionId));
  try {
    await api('resources_upload', { data: fd, isForm:true });
    alert('Archivo subido');
    // refrescar lista
    const btn = document.querySelector(`[data-kind="loadRes"][data-section="${sectionId}"]`);
    if (btn) btn.click();
    form.reset();
  } catch (err){
    alert(err?.json?.message || 'Error subiendo');
  }
});

<?php if ($rol === 'ADMIN'): ?>
document.getElementById('formCourse')?.addEventListener('submit', async (e)=>{
  e.preventDefault();
  const fd = new FormData(e.target);
  const msg = document.getElementById('msgCourse');
  msg.textContent='';
  try {
    await api('courses_create', { data: fd, isForm:true });
    msg.textContent='Curso creado.';
    e.target.reset();
    await loadCourses();
  } catch (err){
    msg.textContent = err?.json?.message || 'Error creando curso';
  }
});
<?php endif; ?>

loadCourses();
</script>


<?php include __DIR__ . '/components/footer.php'; ?>
