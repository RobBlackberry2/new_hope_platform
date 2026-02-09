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
  <h2>📊 Reportes e Informes</h2>
  <p class="muted">Gestión completa de reportes académicos, calificaciones y asistencia</p>
</section>

<?php if ($rol === 'ADMIN'): ?>
<!-- Vista para Administradores -->
<section class="grid2">
  <div class="card">
    <h3>Crear Nuevo Reporte</h3>
    <form id="createReportForm">
      <label>Tipo de Reporte</label>
      <select name="tipo" required>
        <option value="">-- Seleccionar --</option>
        <option value="ACADEMICO">Académico</option>
        <option value="ASISTENCIA">Asistencia</option>
        <option value="RENDIMIENTO_INSTITUCIONAL">Rendimiento Institucional</option>
        <option value="COMPARATIVO">Comparativo</option>
      </select>
      
      <label>Título</label>
      <input type="text" name="titulo" required>
      
      <label>Descripción</label>
      <textarea name="descripcion" rows="3"></textarea>
      
      <label>Periodo Inicio</label>
      <input type="date" name="periodo_inicio">
      
      <label>Periodo Fin</label>
      <input type="date" name="periodo_fin">
      
      <button type="submit" class="btn">Crear Reporte</button>
    </form>
    <div id="createResult"></div>
  </div>

  <div class="card">
    <h3>Filtrar Reportes</h3>
    <form id="filterForm">
      <label>Tipo</label>
      <select name="tipo">
        <option value="">Todos</option>
        <option value="ACADEMICO">Académico</option>
        <option value="ASISTENCIA">Asistencia</option>
        <option value="RENDIMIENTO_INSTITUCIONAL">Rendimiento Institucional</option>
        <option value="COMPARATIVO">Comparativo</option>
      </select>
      
      <label>Estado</label>
      <select name="estado">
        <option value="">Todos</option>
        <option value="ACTIVO">Activo</option>
        <option value="ARCHIVADO">Archivado</option>
      </select>
      
      <button type="submit" class="btn">Filtrar</button>
    </form>
  </div>
</section>

<section class="card">
  <h3>Lista de Reportes</h3>
  <div id="reportsList">Cargando...</div>
</section>

<section class="card">
  <h3>Reporte Institucional</h3>
  <form id="institutionalForm">
    <label>Tipo de Análisis</label>
    <select name="tipo">
      <option value="general">General</option>
      <option value="comparativo_anual">Comparativo Anual</option>
    </select>
    <button type="submit" class="btn">Generar</button>
  </form>
  <div id="institutionalResult"></div>
</section>

<?php elseif ($rol === 'DOCENTE'): ?>
<!-- Vista para Docentes -->
<section class="card">
  <h3>Reporte de Grupo</h3>
  <form id="groupReportForm">
    <label>Curso</label>
    <select name="course_id" id="courseSelect" required>
      <option value="">-- Seleccionar --</option>
    </select>
    
    <label>Periodo</label>
    <select name="periodo" required>
      <option value="">-- Seleccionar --</option>
      <option value="I">Periodo I</option>
      <option value="II">Periodo II</option>
      <option value="III">Periodo III</option>
    </select>
    
    <button type="submit" class="btn">Generar Reporte</button>
    <button type="button" id="exportGroup" class="btn">Exportar CSV</button>
  </form>
  <div id="groupResult"></div>
</section>

<section class="card">
  <h3>Agregar Observación</h3>
  <form id="observationForm">
    <label>Estudiante ID</label>
    <input type="number" name="student_id" required>
    
    <label>Observación</label>
    <textarea name="observacion" rows="4" required></textarea>
    
    <button type="submit" class="btn">Guardar Observación</button>
  </form>
  <div id="obsResult"></div>
</section>

<?php elseif ($rol === 'PADRE'): ?>
<!-- Vista para Padres -->
<section class="card">
  <h3>Reportes de mis Hijos</h3>
  <p class="muted">Seleccione un estudiante para ver sus reportes académicos y de asistencia.</p>
  <div id="studentsList">Cargando...</div>
</section>

<section class="card" id="studentReportSection" style="display:none">
  <h3>Reporte Académico</h3>
  <form id="parentReportForm">
    <input type="hidden" name="student_id" id="selectedStudentId">
    
    <label>Periodo</label>
    <select name="periodo" required>
      <option value="I">Periodo I</option>
      <option value="II">Periodo II</option>
      <option value="III">Periodo III</option>
    </select>
    
    <button type="submit" class="btn">Ver Reporte</button>
    <button type="button" id="downloadReport" class="btn">Descargar PDF</button>
  </form>
  <div id="reportResult"></div>
</section>

<section class="card" id="attendanceSection" style="display:none">
  <h3>Asistencia</h3>
  <form id="attendanceForm">
    <label>Mes/Periodo</label>
    <input type="month" name="month" required>
    
    <button type="submit" class="btn">Ver Asistencia</button>
    <button type="button" id="exportAttendance" class="btn">Exportar</button>
  </form>
  <div id="attendanceResult"></div>
</section>

<?php elseif ($rol === 'ESTUDIANTE'): ?>
<!-- Vista para Estudiantes -->
<section class="card">
  <h3>Mis Calificaciones y Asistencia</h3>
  <form id="myReportForm">
    <label>Periodo</label>
    <select name="periodo">
      <option value="">Actual</option>
      <option value="I">Periodo I</option>
      <option value="II">Periodo II</option>
      <option value="III">Periodo III</option>
    </select>
    
    <button type="submit" class="btn">Ver Reporte</button>
    <button type="button" id="downloadMyReport" class="btn">Descargar</button>
  </form>
  <div id="myReportResult"></div>
</section>

<section class="card">
  <h3>Comparar Periodos</h3>
  <form id="compareForm">
    <label>Periodo 1</label>
    <select name="periodo1" required>
      <option value="I">Periodo I</option>
      <option value="II">Periodo II</option>
      <option value="III">Periodo III</option>
    </select>
    
    <label>Periodo 2</label>
    <select name="periodo2" required>
      <option value="I">Periodo I</option>
      <option value="II">Periodo II</option>
      <option value="III">Periodo III</option>
    </select>
    
    <button type="submit" class="btn">Comparar</button>
  </form>
  <div id="compareResult"></div>
</section>

<section class="card">
  <h3>Enviar Comentario</h3>
  <form id="commentForm">
    <label>Comentario sobre mi Reporte</label>
    <textarea name="comentario" rows="4" required placeholder="Escribe tu comentario o solicitud de revisión..."></textarea>
    
    <button type="submit" class="btn">Enviar al Docente</button>
  </form>
  <div id="commentResult"></div>
</section>

<?php endif; ?>

<script src="<?= $base_url ?>/js/reports.js"></script>
<?php include __DIR__ . '/components/footer.php'; ?>
