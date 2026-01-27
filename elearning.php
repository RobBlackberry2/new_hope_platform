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

<?php if ($rol === 'ADMIN' || $rol === 'DOCENTE'): ?>
<section class="card">
  <h3>Crear curso</h3>
  <form id="formCourse" class="grid2">
    <label>Nombre<input name="nombre" required /></label>
    <label>Grado (7-11)<input name="grado" type="number" min="7" max="11" value="7" /></label>
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

function courseCard(c){
  return `<button class="card" style="text-align:left; width:100%; margin:8px 0;" data-kind="select" data-id="${c.id}">
    <div><strong>${c.nombre}</strong></div>
    <div class="muted">Grado: ${c.grado} — Docente: ${c.docente_nombre||''}</div>
    <div class="muted">${(c.descripcion||'').replace(/</g,'&lt;')}</div>
  </button>`;
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

async function loadDetail(courseId){
  currentCourse = courseId;
  const d = document.getElementById('detail');
  d.textContent = 'Cargando detalle...';

  try {
    const sec = await api('sections_list', { method:'GET', params:{course_id: courseId} });
    const sections = sec.data || [];

    const sectionsHtml = sections.map(s=>`<div class="card" style="margin:8px 0;">
      <div style="display:flex; justify-content:space-between; gap:8px; align-items:center;">
        <strong>${s.titulo}</strong>
        <button class="btn" data-kind="loadRes" data-section="${s.id}">Ver archivos</button>
      </div>
      <div id="res_${s.id}" class="muted" style="margin-top:6px;"></div>
      <div style="margin-top:8px;">
        <form data-kind="upload" data-section="${s.id}">
          <input type="file" name="file" required />
          <button class="btn" type="submit">Subir</button>
        </form>
      </div>
    </div>`).join('') || '<div class="muted">Sin secciones.</div>';

    const createSectionHtml = `<?php if ($rol==='ADMIN' || $rol==='DOCENTE'): ?>
      <hr class="sep" />
      <h4>Crear sección</h4>
      <form id="formSection" class="grid">
        <label>Título<input name="titulo" required /></label>
        <label>Orden<input name="orden" type="number" value="0" /></label>
        <button class="btn" type="submit">Crear sección</button>
        <div id="msgSection" class="muted"></div>
      </form>
    <?php else: ?>
      <hr class="sep" />
      <div class="muted">Solo docentes/admin pueden crear secciones y subir archivos.</div>
    <?php endif; ?>`;

    d.innerHTML = `<div class="muted">Curso #${courseId}</div>` + sectionsHtml + createSectionHtml;

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
          await loadDetail(courseId);
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
  const btn = e.target.closest('[data-kind="select"]');
  if (!btn) return;
  await loadDetail(btn.getAttribute('data-id'));
});

document.getElementById('detail').addEventListener('click', async (e)=>{
  const btn = e.target.closest('[data-kind="loadRes"]');
  if (!btn) return;
  const sectionId = btn.getAttribute('data-section');
  const el = document.getElementById('res_' + sectionId);
  el.textContent='Cargando archivos...';
  try {
    const j = await api('resources_list', { method:'GET', params:{section_id: sectionId} });
    const files = j.data||[];
    if (!files.length) { el.textContent='Sin archivos.'; return; }
    el.innerHTML = files.map(f=>{
      const href = window.__BASE_URL__ + '/uploads/' + (f.stored_name||'');
      return `<div><a class="link" href="${href}" target="_blank">${f.original_name}</a> <span class="muted">(${Math.round((f.size||0)/1024)} KB)</span></div>`;
    }).join('');
  } catch (err){
    el.textContent = err?.json?.message || 'Error';
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

<?php if ($rol === 'ADMIN' || $rol === 'DOCENTE'): ?>
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
