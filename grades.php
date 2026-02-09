<?php
require_once __DIR__ . '/app/helpers/auth.php';
$config = require __DIR__ . '/app/config/config.php';
$base_url = $config['base_url'] ?? '';
$u = current_user();
if (!$u) { header('Location: ' . $base_url . '/login.php'); exit; }
require_role(['ADMIN', 'DOCENTE']);
include __DIR__ . '/components/header.php';
?>
<section class="card">
  <h2>📝 Gestión de Calificaciones</h2>
  <p class="muted">Registro y administración de calificaciones de estudiantes</p>
</section>

<section class="grid2">
  <div class="card">
    <h3>Registrar Calificación</h3>
    <form id="gradeForm">
      <label>Estudiante ID</label>
      <input type="number" name="student_id" required>
      
      <label>Curso</label>
      <select name="course_id" id="courseSelectGrade" required>
        <option value="">-- Seleccionar --</option>
      </select>
      
      <label>Periodo</label>
      <select name="periodo" required>
        <option value="I">Periodo I</option>
        <option value="II">Periodo II</option>
        <option value="III">Periodo III</option>
      </select>
      
      <label>Calificación (0-100)</label>
      <input type="number" name="calificacion" min="0" max="100" step="0.01" required>
      
      <button type="submit" class="btn">Guardar</button>
    </form>
    <div id="gradeResult"></div>
  </div>

  <div class="card">
    <h3>Buscar Calificaciones</h3>
    <form id="searchGradesForm">
      <label>Estudiante ID</label>
      <input type="number" name="student_id">
      
      <label>Periodo (opcional)</label>
      <select name="periodo">
        <option value="">Todos</option>
        <option value="I">Periodo I</option>
        <option value="II">Periodo II</option>
        <option value="III">Periodo III</option>
      </select>
      
      <button type="submit" class="btn">Buscar</button>
    </form>
  </div>
</section>

<section class="card">
  <h3>Lista de Calificaciones Recientes</h3>
  <div id="gradesList">Cargando...</div>
</section>

<script>
const baseUrl = '<?= $base_url ?>';

// Cargar cursos
fetch(baseUrl + '/router.php?action=courses_list')
  .then(r => r.json())
  .then(data => {
    if (data.status === 'success') {
      const select = document.getElementById('courseSelectGrade');
      data.data.forEach(c => {
        const opt = document.createElement('option');
        opt.value = c.id;
        opt.textContent = c.nombre;
        select.appendChild(opt);
      });
    }
  });

// Registrar calificación
document.getElementById('gradeForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  const formData = new FormData(e.target);
  const res = await fetch(baseUrl + '/router.php?action=grades_create', {
    method: 'POST',
    body: formData
  });
  const data = await res.json();
  document.getElementById('gradeResult').innerHTML = 
    `<p class="${data.status === 'success' ? 'muted' : 'error'}">${data.message || JSON.stringify(data)}</p>`;
  if (data.status === 'success') {
    e.target.reset();
    loadGrades();
  }
});

// Buscar calificaciones
document.getElementById('searchGradesForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  const formData = new FormData(e.target);
  const params = new URLSearchParams(formData);
  const res = await fetch(baseUrl + '/router.php?action=grades_student&' + params);
  const data = await res.json();
  
  if (data.status === 'success') {
    let html = '<table><tr><th>Curso</th><th>Periodo</th><th>Calificación</th><th>Docente</th></tr>';
    data.data.grades.forEach(g => {
      html += `<tr><td>${g.course_name}</td><td>${g.periodo}</td><td>${g.calificacion}</td><td>${g.docente_name}</td></tr>`;
    });
    html += `</table><p><strong>Promedio: ${data.data.average.toFixed(2)}</strong></p>`;
    document.getElementById('gradesList').innerHTML = html;
  }
});

// Cargar calificaciones recientes
function loadGrades() {
  fetch(baseUrl + '/router.php?action=grades_list&limit=50')
    .then(r => r.json())
    .then(data => {
      if (data.status === 'success') {
        let html = '<table><tr><th>Estudiante</th><th>Curso</th><th>Periodo</th><th>Nota</th><th>Fecha</th></tr>';
        data.data.forEach(g => {
          html += `<tr><td>${g.student_name}</td><td>${g.course_name}</td><td>${g.periodo}</td><td>${g.calificacion}</td><td>${g.created_at}</td></tr>`;
        });
        html += '</table>';
        document.getElementById('gradesList').innerHTML = html;
      }
    });
}

loadGrades();
</script>

<?php include __DIR__ . '/components/footer.php'; ?>
