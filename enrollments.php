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
  <h2>Administrativo: Matrículas</h2>
  <p class="muted">Página para gestionar matriculas y ver la lista de todos los estudiantes.</p>
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

<script>
const tbl = document.getElementById('tblStudents');
const msg = document.getElementById('msg');
const tblEnr = document.getElementById('tblEnr');
const msgEnr = document.getElementById('msgEnr');

function studentRow(s){
  return `<tr>
    <td>${s.id}</td>
    <td>${s.nombre||''}</td>
    <td>${s.cedula||''}</td>
    <td>${s.grado||''}</td>
    <td>${s.seccion||''}</td>
    <td><button class="btn danger" data-kind="del" data-id="${s.id}">Eliminar</button></td>
  </tr>`;
}

function enrRow(e){
  return `<tr>
    <td>${e.id}</td>
    <td>${e.student_id}</td>
    <td>${e.student_nombre||''}</td>
    <td>${e.grado||''}${e.seccion?(' - '+e.seccion):''}</td>
    <td>${e.year}</td>
    <td>
      <select data-kind="estado" data-id="${e.id}">
        <option ${e.estado==='ACTIVA'?'selected':''}>ACTIVA</option>
        <option ${e.estado==='PENDIENTE'?'selected':''}>PENDIENTE</option>
      </select>
    </td>
    <td><button class="btn danger" data-kind="del_enr" data-id="${e.id}">Eliminar</button></td>
  </tr>`;
}

async function loadStudents(){
  msg.textContent = 'Cargando...';
  try {
    const j = await api('students_list', { method:'GET', params:{limit:200} });
    const data = j.data||[];
    tbl.innerHTML = `<tr><th>Id</th><th>Nombre</th><th>Cédula</th><th>Grado</th><th>Sección</th><th></th><th></th></tr>` + data.map(studentRow).join('');
    msg.textContent='';
  } catch (err){
    msg.textContent = err?.json?.message || 'Error cargando estudiantes';
  }
}

async function loadEnrollments(){
  msgEnr.textContent = 'Cargando...';
  try {
    const j = await api('enrollments_list', { method:'GET', params:{limit:200} });
    const data = j.data||[];
    tblEnr.innerHTML = `<tr><th>Id</th><th>Estudiante Id</th><th>Estudiante</th><th>Grado</th><th>Año</th><th>Estado</th><th></th></tr>` + data.map(enrRow).join('');
    msgEnr.textContent='';
  } catch (err){
    msgEnr.textContent = err?.json?.message || 'Error cargando matrículas';
  }
}

document.getElementById('formStudent').addEventListener('submit', async (e)=>{
  e.preventDefault();
  const fd = new FormData(e.target);
  const ms = document.getElementById('msgStudent');
  ms.textContent='';

  try {

    const created = await api('students_create', { data: fd, isForm:true });
    const studentId = created?.id;
    if (!studentId) throw new Error('No se recibió id del estudiante');

    const year = parseInt(fd.get('year') || new Date().getFullYear(), 10);
    await api('enrollments_create', { data: { student_id: studentId, year } });

    ms.textContent = 'Estudiante creado y matriculado.';
    e.target.reset();

    await loadStudents();
    await loadEnrollments();
  } catch (err){
    ms.textContent = err?.json?.message || err?.message || 'Error matriculando estudiante';
  }
});

tbl.addEventListener('click', async (e)=>{
  const kind = e.target.getAttribute('data-kind');
  const id = e.target.getAttribute('data-id');
  if (!kind || !id) return;

  if (kind === 'del') {
    if (!confirm('¿Eliminar estudiante #' + id + '?')) return;
    try { await api('students_delete', { data:{id} }); await loadStudents(); }
    catch (err){ alert(err?.json?.message || 'Error'); }
  }

  if (kind === 'enroll') {
    const year = prompt('Año de matrícula', new Date().getFullYear());
    if (!year) return;
    try { await api('enrollments_create', { data:{student_id:id, year} }); await loadEnrollments(); }
    catch (err){ alert(err?.json?.message || 'Error'); }
  }
});

tblEnr.addEventListener('change', async (e)=>{
  const kind = e.target.getAttribute('data-kind');
  const id = e.target.getAttribute('data-id');
  if (kind !== 'estado') return;
  try { await api('enrollments_updateEstado', { data:{id, estado:e.target.value} }); }
  catch (err){ alert(err?.json?.message || 'Error'); }
});

tblEnr.addEventListener('click', async (e)=>{
  const kind = e.target.getAttribute('data-kind');
  const id = e.target.getAttribute('data-id');
  if (kind !== 'del_enr') return;
  if (!confirm('¿Eliminar matrícula #' + id + '?')) return;
  try { await api('enrollments_delete', { data:{id} }); await loadEnrollments(); }
  catch (err){ alert(err?.json?.message || 'Error'); }
});

loadStudents();
loadEnrollments();
</script>

<?php include __DIR__ . '/components/footer.php'; ?>
