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
  <h2>📅 Gestión de Asistencia</h2>
  <p class="muted">Registro diario de asistencia de estudiantes</p>
</section>

<section class="grid2">
  <div class="card">
    <h3>Registrar Asistencia</h3>
    <form id="attendanceForm">
      <label>Estudiante ID</label>
      <input type="number" name="student_id" required>
      
      <label>Fecha</label>
      <input type="date" name="fecha" required value="<?= date('Y-m-d') ?>">
      
      <label>Estado</label>
      <select name="estado" required>
        <option value="PRESENTE">Presente</option>
        <option value="AUSENTE">Ausente</option>
        <option value="TARDANZA">Tardanza</option>
      </select>
      
      <label>Curso (opcional)</label>
      <select name="course_id">
        <option value="">-- General --</option>
      </select>
      
      <button type="submit" class="btn">Registrar</button>
    </form>
    <div id="attResult"></div>
  </div>

  <div class="card">
    <h3>Consultar Asistencia</h3>
    <form id="searchAttForm">
      <label>Estudiante ID</label>
      <input type="number" name="student_id" required>
      
      <label>Desde</label>
      <input type="date" name="fecha_inicio" required>
      
      <label>Hasta</label>
      <input type="date" name="fecha_fin" required>
      
      <button type="submit" class="btn">Consultar</button>
    </form>
  </div>
</section>

<section class="card">
  <h3>Resultados</h3>
  <div id="attList">Seleccione un estudiante y rango de fechas para ver la asistencia</div>
</section>

<script>
const baseUrl = '<?= $base_url ?>';

// Registrar asistencia
document.getElementById('attendanceForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  const formData = new FormData(e.target);
  const res = await fetch(baseUrl + '/router.php?action=attendance_create', {
    method: 'POST',
    body: formData
  });
  const data = await res.json();
  document.getElementById('attResult').innerHTML = 
    `<p class="${data.status === 'success' ? 'muted' : 'error'}">${data.message || JSON.stringify(data)}</p>`;
  if (data.status === 'success') {
    e.target.reset();
    document.querySelector('[name="fecha"]').value = '<?= date('Y-m-d') ?>';
  }
});

// Consultar asistencia
document.getElementById('searchAttForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  const formData = new FormData(e.target);
  const params = new URLSearchParams(formData);
  const res = await fetch(baseUrl + '/router.php?action=attendance_student&' + params);
  const data = await res.json();
  
  if (data.status === 'success') {
    const summary = data.data.summary;
    let html = `<div class="grid3">
      <div class="card" style="background:#e8f5e9"><strong>Presente:</strong> ${summary.presentes}</div>
      <div class="card" style="background:#ffebee"><strong>Ausente:</strong> ${summary.ausentes}</div>
      <div class="card" style="background:#fff9c4"><strong>Tardanza:</strong> ${summary.tardanzas}</div>
    </div>`;
    
    html += '<table><tr><th>Fecha</th><th>Estado</th><th>Curso</th></tr>';
    data.data.attendance.forEach(a => {
      const bg = a.estado === 'PRESENTE' ? '#e8f5e9' : (a.estado === 'AUSENTE' ? '#ffebee' : '#fff9c4');
      html += `<tr style="background:${bg}"><td>${a.fecha}</td><td>${a.estado}</td><td>${a.course_name || 'General'}</td></tr>`;
    });
    html += '</table>';
    document.getElementById('attList').innerHTML = html;
  } else {
    document.getElementById('attList').innerHTML = `<p class="error">${data.message}</p>`;
  }
});
</script>

<?php include __DIR__ . '/components/footer.php'; ?>
