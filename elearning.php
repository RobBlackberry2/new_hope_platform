<?php
require_once __DIR__ . '/app/helpers/auth.php';
$config = require __DIR__ . '/app/config/config.php';
$base_url = $config['base_url'] ?? '';
$u = current_user();
if (!$u) {
  header('Location: ' . $base_url . '/login.php');
  exit;
}
include __DIR__ . '/components/header.php';
$rol = $u['rol'] ?? '';
?>
<section class="card">
  <h2>E-Learning</h2>
  <p class="muted">Cursos y acceso al Campus Virtual. (Gamificación en desarrollo)</p>
</section>

<?php if ($rol === 'ADMIN'): ?>
  <section class="card">
    <h3>Crear curso</h3>

    <form id="formCourse" class="grid2 form-shell">
      <label>Nombre<input name="nombre" required /></label>
      <label>Grado (7-11)<input name="grado" type="number" min="7" max="11" value="7" /></label>

      <label>Sección<input name="seccion" required /></label>

      <label>Docente
        <select name="docente_user_id" id="docente_user_id" required>
          <option value="" disabled selected>Seleccione un docente...</option>
        </select>
      </label>

      <label class="span-all">Descripción
        <textarea name="descripcion" rows="3"></textarea>
      </label>

      <div class="span-all row gap-12 align-center">
        <button class="btn" type="submit">Crear</button>
        <div id="msgCourse" class="muted"></div>
      </div>
    </form>
  </section>
<?php endif; ?>

<section class="grid2">
  <div class="card">
    <h3>Mis cursos</h3>
    <div id="courses" class="muted">Cargando...</div>
  </div>

  <div class="card">
    <h3>Gamificación</h3>
    <div class="muted">
      Aqui implemetaremos la gamificacion
    </div>
  </div>
</section>

<script>
  const IS_ADMIN = <?php echo json_encode($rol === 'ADMIN'); ?>;
  const ROLE = <?php echo json_encode($rol); ?>; // ✅ agregado para detectar estudiante

  function escapeHtml(v) {
    return String(v ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function courseCard(c) {
    const delBtn = IS_ADMIN
      ? `<button class="btn" data-kind="deleteCourse" data-id="${c.id}">Eliminar</button>`
      : '';

    return `<div class="card card-row">
      <div class="row-between-start gap-8">
        <div style="flex:1;">
          <div><strong>${escapeHtml(c.nombre)}</strong></div>
          <div class="muted">Grado: ${escapeHtml(c.grado)} — Docente: ${escapeHtml(c.docente_nombre || '')}</div>

          ${(ROLE === 'ESTUDIANTE' && c.nota_actual != null)
            ? `<div class="muted">Nota actual: ${escapeHtml(c.nota_actual)} / 100</div>`
            : ''}

          <div class="muted">${escapeHtml(c.descripcion || '')}</div>
          <div class="muted">${escapeHtml(c.seccion || '')}</div>
        </div>

        <div class="row gap-8">
          <button class="btn" data-kind="open" data-id="${c.id}">Abrir</button>
          ${delBtn}
        </div>
      </div>
    </div>`;
  }

  async function loadDocentes() {
    const sel = document.getElementById('docente_user_id');
    if (!sel) return;

    try {
      const j = await api('users_list_docentes', { method: 'GET', params: { limit: 500 } });
      const docs = j.data || [];

      const opts = [`<option value="" disabled selected>Seleccione un docente...</option>`];
      for (const d of docs) {
        const label = `${(d.nombre || '')} (@${(d.username || '')})`;
        opts.push(`<option value="${d.id}">${escapeHtml(label)}</option>`);
      }
      sel.innerHTML = opts.join('');
    } catch (err) {
      sel.innerHTML = `<option value="" disabled selected>Error cargando docentes</option>`;
    }
  }

  async function loadCourses() {
    const el = document.getElementById('courses');
    el.textContent = 'Cargando...';
    try {
      const j = await api('courses_list', { method: 'GET', params: { limit: 200 } });
      const data = j.data || [];
      if (!data.length) { el.textContent = 'No hay cursos aún.'; return; }
      el.innerHTML = data.map(courseCard).join('');
    } catch (err) {
      el.textContent = err?.json?.message || 'Error cargando cursos';
    }
  }

  document.getElementById('courses').addEventListener('click', async (e) => {
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
        await loadCourses();
      } catch (err) {
        alert(err?.json?.message || 'Error eliminando curso');
      }
      return;
    }

    if (kind === 'open') {
      const id = el.getAttribute('data-id');
      const base = window.__BASE_URL__ || '';
      window.location.href = `${base}/virtualcampus.php?course_id=${encodeURIComponent(id)}`;
      return;
    }
  });

  <?php if ($rol === 'ADMIN'): ?>
    document.getElementById('formCourse')?.addEventListener('submit', async (e) => {
      e.preventDefault();
      const fd = new FormData(e.target);
      const msg = document.getElementById('msgCourse');
      msg.textContent = '';
      try {
        await api('courses_create', { data: fd, isForm: true });
        msg.textContent = 'Curso creado.';
        e.target.reset();
        await loadCourses();
      } catch (err) {
        msg.textContent = err?.json?.message || 'Error creando curso';
      }
    });
  <?php endif; ?>

  loadCourses();
  if (IS_ADMIN) loadDocentes();
</script>

<?php include __DIR__ . '/components/footer.php'; ?>