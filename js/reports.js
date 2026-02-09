// reports.js - Funcionalidad para el módulo de Reportes
const baseUrl = window.location.origin + window.location.pathname.replace('/reports.php', '');

// ============================================
// FUNCIONES PARA ADMINISTRADORES
// ============================================

// Crear reporte
const createForm = document.getElementById('createReportForm');
if (createForm) {
  createForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const formData = new FormData(e.target);
    const res = await fetch(baseUrl + '/router.php?action=reports_create', {
      method: 'POST',
      body: formData
    });
    const data = await res.json();
    document.getElementById('createResult').innerHTML = 
      `<p class="${data.status === 'success' ? 'muted' : 'error'}">${data.message}</p>`;
    if (data.status === 'success') {
      e.target.reset();
      loadReports();
    }
  });
}

// Filtrar y listar reportes
const filterForm = document.getElementById('filterForm');
if (filterForm) {
  filterForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const formData = new FormData(e.target);
    const params = new URLSearchParams(formData);
    await loadReports(params);
  });
}

async function loadReports(params = '') {
  const res = await fetch(baseUrl + '/router.php?action=reports_list&' + params);
  const data = await res.json();
  
  if (data.status === 'success') {
    let html = '<table><tr><th>ID</th><th>Título</th><th>Tipo</th><th>Estado</th><th>Periodo</th><th>Acciones</th></tr>';
    data.data.forEach(r => {
      html += `<tr>
        <td>${r.id}</td>
        <td>${r.titulo}</td>
        <td>${r.tipo}</td>
        <td>${r.estado}</td>
        <td>${r.periodo_inicio || ''} - ${r.periodo_fin || ''}</td>
        <td>
          <button onclick="exportReport(${r.id})">CSV</button>
          <button onclick="archiveReport(${r.id})">Archivar</button>
          <button onclick="deleteReport(${r.id})">Eliminar</button>
        </td>
      </tr>`;
    });
    html += '</table>';
    document.getElementById('reportsList').innerHTML = html;
  }
}

window.exportReport = function(id) {
  window.location.href = baseUrl + '/router.php?action=reports_export&id=' + id + '&format=csv';
}

window.archiveReport = async function(id) {
  if (!confirm('¿Archivar este reporte?')) return;
  const formData = new FormData();
  formData.append('id', id);
  const res = await fetch(baseUrl + '/router.php?action=reports_archive', {
    method: 'POST',
    body: formData
  });
  const data = await res.json();
  alert(data.message);
  loadReports();
}

window.deleteReport = async function(id) {
  if (!confirm('¿Eliminar permanentemente este reporte?')) return;
  const formData = new FormData();
  formData.append('id', id);
  const res = await fetch(baseUrl + '/router.php?action=reports_delete', {
    method: 'POST',
    body: formData
  });
  const data = await res.json();
  alert(data.message);
  loadReports();
}

// Reporte institucional
const institutionalForm = document.getElementById('institutionalForm');
if (institutionalForm) {
  institutionalForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const formData = new FormData(e.target);
    const params = new URLSearchParams(formData);
    const res = await fetch(baseUrl + '/router.php?action=reports_institutional&' + params);
    const data = await res.json();
    
    if (data.status === 'success') {
      document.getElementById('institutionalResult').innerHTML = 
        `<pre>${JSON.stringify(data.data, null, 2)}</pre>`;
    }
  });
}

// Inicializar si estamos en vista admin
if (document.getElementById('reportsList')) {
  loadReports();
}

// ============================================
// FUNCIONES PARA DOCENTES
// ============================================

// Cargar cursos del docente
if (document.getElementById('courseSelect')) {
  fetch(baseUrl + '/router.php?action=courses_list')
    .then(r => r.json())
    .then(data => {
      if (data.status === 'success') {
        const select = document.getElementById('courseSelect');
        data.data.forEach(c => {
          const opt = document.createElement('option');
          opt.value = c.id;
          opt.textContent = c.nombre;
          select.appendChild(opt);
        });
      }
    });
}

// Generar reporte de grupo
const groupForm = document.getElementById('groupReportForm');
if (groupForm) {
  groupForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const formData = new FormData(e.target);
    const params = new URLSearchParams(formData);
    const res = await fetch(baseUrl + '/router.php?action=reports_group&' + params);
    const data = await res.json();
    
    if (data.status === 'success') {
      const report = data.data;
      let html = `<h4>Promedio del Grupo: ${report.average.toFixed(2)}</h4>`;
      html += '<table><tr><th>Estudiante</th><th>Calificación</th></tr>';
      report.grades.forEach(g => {
        html += `<tr><td>${g.student_name}</td><td>${g.calificacion}</td></tr>`;
      });
      html += '</table>';
      document.getElementById('groupResult').innerHTML = html;
    } else {
      document.getElementById('groupResult').innerHTML = `<p class="error">${data.message}</p>`;
    }
  });
  
  document.getElementById('exportGroup')?.addEventListener('click', () => {
    const formData = new FormData(groupForm);
    const params = new URLSearchParams(formData);
    window.location.href = baseUrl + '/router.php?action=reports_group_export&' + params + '&format=csv';
  });
}

// Agregar observación
const obsForm = document.getElementById('observationForm');
if (obsForm) {
  obsForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const formData = new FormData(e.target);
    const res = await fetch(baseUrl + '/router.php?action=observations_add', {
      method: 'POST',
      body: formData
    });
    const data = await res.json();
    document.getElementById('obsResult').innerHTML = 
      `<p class="${data.status === 'success' ? 'muted' : 'error'}">${data.message}</p>`;
    if (data.status === 'success') {
      e.target.reset();
    }
  });
}

// ============================================
// FUNCIONES PARA PADRES
// ============================================

// Cargar estudiantes del padre
if (document.getElementById('studentsList')) {
  // En una implementación completa, aquí se cargaría la lista de estudiantes
  // asociados al padre desde la API
  document.getElementById('studentsList').innerHTML = 
    '<p class="muted">Funcionalidad de lista de estudiantes. Debe configurarse la relación padre-estudiante en la base de datos.</p>';
}

// Ver reporte académico del estudiante
const parentReportForm = document.getElementById('parentReportForm');
if (parentReportForm) {
  parentReportForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const formData = new FormData(e.target);
    const params = new URLSearchParams(formData);
    const res = await fetch(baseUrl + '/router.php?action=reports_student_view&' + params);
    const data = await res.json();
    
    if (data.status === 'success') {
      const report = data.data;
      let html = `<h4>Promedio: ${report.average.toFixed(2)}</h4>`;
      html += '<table><tr><th>Curso</th><th>Calificación</th></tr>';
      report.grades.forEach(g => {
        html += `<tr><td>${g.course_name}</td><td>${g.calificacion}</td></tr>`;
      });
      html += '</table>';
      
      const att = report.attendance;
      html += `<h4>Asistencia</h4>`;
      html += `<p>Presente: ${att.presentes} | Ausente: ${att.ausentes} | Tardanza: ${att.tardanzas}</p>`;
      
      document.getElementById('reportResult').innerHTML = html;
    } else {
      document.getElementById('reportResult').innerHTML = `<p class="error">${data.message}</p>`;
    }
  });
}

// ============================================
// FUNCIONES PARA ESTUDIANTES
// ============================================

// Ver mi reporte
const myReportForm = document.getElementById('myReportForm');
if (myReportForm) {
  myReportForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const formData = new FormData(e.target);
    const params = new URLSearchParams(formData);
    const res = await fetch(baseUrl + '/router.php?action=reports_my_view&' + params);
    const data = await res.json();
    
    if (data.status === 'success') {
      const report = data.data;
      let html = `<h4>Mi Promedio: ${report.average.toFixed(2)}</h4>`;
      html += '<table><tr><th>Curso</th><th>Calificación</th><th>Docente</th></tr>';
      report.grades.forEach(g => {
        html += `<tr><td>${g.course_name}</td><td>${g.calificacion}</td><td>${g.docente_name}</td></tr>`;
      });
      html += '</table>';
      document.getElementById('myReportResult').innerHTML = html;
    } else {
      document.getElementById('myReportResult').innerHTML = `<p class="error">${data.message}</p>`;
    }
  });
  
  document.getElementById('downloadMyReport')?.addEventListener('click', () => {
    const formData = new FormData(myReportForm);
    const params = new URLSearchParams(formData);
    window.location.href = baseUrl + '/router.php?action=reports_my_download&' + params;
  });
}

// Comparar periodos
const compareForm = document.getElementById('compareForm');
if (compareForm) {
  compareForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const formData = new FormData(e.target);
    const params = new URLSearchParams(formData);
    const res = await fetch(baseUrl + '/router.php?action=reports_my_compare&' + params);
    const data = await res.json();
    
    if (data.status === 'success') {
      const p1 = data.data.periodo1;
      const p2 = data.data.periodo2;
      let html = `<div class="grid2">`;
      html += `<div><h4>Periodo 1: Promedio ${p1.average.toFixed(2)}</h4></div>`;
      html += `<div><h4>Periodo 2: Promedio ${p2.average.toFixed(2)}</h4></div>`;
      html += `</div>`;
      const diff = p2.average - p1.average;
      html += `<p><strong>Diferencia: ${diff > 0 ? '+' : ''}${diff.toFixed(2)}</strong></p>`;
      document.getElementById('compareResult').innerHTML = html;
    } else {
      document.getElementById('compareResult').innerHTML = `<p class="error">${data.message}</p>`;
    }
  });
}

// Enviar comentario
const commentForm = document.getElementById('commentForm');
if (commentForm) {
  commentForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const formData = new FormData(e.target);
    const res = await fetch(baseUrl + '/router.php?action=reports_my_comment', {
      method: 'POST',
      body: formData
    });
    const data = await res.json();
    document.getElementById('commentResult').innerHTML = 
      `<p class="${data.status === 'success' ? 'muted' : 'error'}">${data.message}</p>`;
    if (data.status === 'success') {
      e.target.reset();
    }
  });
}
