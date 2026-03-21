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
$course_id = (int) ($_GET['course_id'] ?? 0);
?>
<section class="card">
  <div style="display:flex; justify-content:space-between; gap:12px; align-items:center;">
    <div>
      <h2 id="courseTitle">Campus Virtual</h2>
      <div id="courseMeta" class="muted">Cargando curso...</div>
    </div>
    <div>
      <a class="btn" href="<?= $base_url ?>/elearning.php">Volver a E-Learning</a>
    </div>
  </div>
</section>

<section class="card">
  <h3>Contenido</h3>
  <div id="detail" class="muted">Cargando...</div>
</section>

<script>
  let currentCourse = null;
  let currentSections = [];
  const MAX_WEEKS = 40;
  const selectedWeekByCourse = {};

  const IS_ADMIN = <?php echo json_encode($rol === 'ADMIN'); ?>;
  const ROLE = <?php echo json_encode($rol); ?>;
  const IS_DOCENTE = ROLE === 'DOCENTE';
  const IS_STUDENT = ROLE === 'ESTUDIANTE';

  function escapeHtml(v) {
    return String(v ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function getSelectedWeek(courseId) {
    const id = String(courseId);
    if (!selectedWeekByCourse[id]) selectedWeekByCourse[id] = 1;
    return selectedWeekByCourse[id];
  }

  function buildWeekFilterHtml(courseId) {
    const selected = getSelectedWeek(courseId);

    const options = Array.from({ length: MAX_WEEKS }, (_, i) => {
      const w = i + 1;
      const sel = (w === selected) ? 'selected' : '';
      return `<option value="${w}" ${sel}>Semana ${w}</option>`;
    }).join('');

    return `
      <div style="display:flex; justify-content:space-between; align-items:center; gap:10px;">
        <strong>Semana</strong>
        <div style="display:flex; align-items:center; gap:8px;">
          <select class="ctrl-select btn-sm" data-kind="weekSelect">
            ${options}
          </select>
        </div>
      </div>
    `;
  }

  function sectionTypeSelect(s) {
    const tipos = ['RECURSOS', 'TAREA', 'QUIZ', 'EXAMEN', 'AVISO', 'FORO'];
    const current = (s.tipo || 'RECURSOS');
    if (!(IS_ADMIN || IS_DOCENTE)) return '';
    const opts = tipos.map(t => {
      const sel = (t === current) ? 'selected' : '';
      return `<option value="${t}" ${sel}>${t}</option>`;
    }).join('');
    return `<select class="ctrl-select btn-sm" data-kind="sectionType" data-section="${s.id}">
      ${opts}
    </select>`;
  }

  // ✅ Cambio #6: ocultar "Ver archivos" en QUIZ/EXAMEN/AVISO
  function canShowFilesButton(s) {
    const tipo = String(s?.tipo || 'RECURSOS').toUpperCase();
    return (tipo === 'RECURSOS' || tipo === 'TAREA');
  }


  function validateFilesBeforeUpload(fileList) {
    const allowedExt = ['pdf', 'zip', 'jpg', 'jpeg'];
    const maxSize = 500 * 1024;
    const files = Array.from(fileList || []);
    if (!files.length) return 'Seleccione al menos un archivo.';
    for (const f of files) {
      const ext = String(f.name || '').split('.').pop().toLowerCase();
      if (!allowedExt.includes(ext)) return 'Solo se permiten archivos PDF, ZIP o JPG.';
      if (Number(f.size || 0) > maxSize) return `El archivo ${f.name} supera el máximo de 500 KB.`;
    }
    return null;
  }

  function sectionCardHtml(s) {
    const descripcion = escapeHtml(s.descripcion || '');
    const showFilesBtn = canShowFilesButton(s);

    return `<div class="card card-compact">
      <div style="display:flex; justify-content:space-between; gap:8px; align-items:center;">
        <div style="display:flex; gap:10px; align-items:center;">
          <strong>${escapeHtml(s.titulo)}</strong>
        </div>

        <div class="row gap-8 align-center">
          ${showFilesBtn ? `<button class="btn" data-kind="loadRes" data-section="${s.id}">Ver archivos</button>` : ''}
          <?php if ($rol === 'ADMIN' || $rol === 'DOCENTE'): ?>
            <button class="btn" data-kind="deleteSection" data-section="${s.id}">Eliminar</button>
          <?php endif; ?>
        </div>
      </div>

      ${descripcion ? `<p>${descripcion}</p>` : ''}

      <div id="section_body_${s.id}" class="mt-10"></div>
      <div id="res_${s.id}" class="muted mt-6"></div>
    </div>`;
  }

  function renderRecursosUI(s) {
    const upload = (IS_ADMIN || IS_DOCENTE) ? `
      <form data-kind="uploadRes" data-section="${s.id}" method="post" enctype="multipart/form-data" class="mt-8">
        <input type="file" name="files[]" multiple required accept=".pdf,.zip,.jpg,.jpeg">
        <div class="muted mt-6">Formatos permitidos: PDF, ZIP o JPG. Tamaño máximo: 500 KB por archivo.</div>
        <button class="btn" type="submit">Subir recurso</button>
        <span class="muted" data-kind="msgUploadRes" data-section="${s.id}"></span>
      </form>
    ` : `<div class="muted mt-8">Recursos del docente.</div>`;

    return `
      <div class="muted">Materiales (presentaciones/lecturas).</div>
      ${upload}
    `;
  }

  function renderAvisoUI(s) {
    const txt = escapeHtml(s.descripcion || '');
    return `<div class="card card-tight mt-8">
      <strong>Aviso</strong>
      <div class="mt-6">${txt || '<span class="muted">Sin contenido.</span>'}</div>
    </div>`;
  }

  function renderQuizLinkUI(s, tipo) {
    const label = (tipo === 'EXAMEN') ? 'Examen' : 'Quiz';
    const url = `${window.__BASE_URL__}/quiz.php?course_id=${encodeURIComponent(String(currentCourse))}&section_id=${encodeURIComponent(String(s.id))}&tipo=${encodeURIComponent(String(tipo))}&title=${encodeURIComponent(String(s.titulo || ''))}`;
    return `
      <div class="card card-tight mt-8">
        <strong>${label}</strong>
        <div class="mt-10">
          <a class="btn" href="${escapeHtml(url)}">Abrir ${label}</a>
        </div>
      </div>
    `;
  }

  function renderForumLinkUI(s) {
    const url = `${window.__BASE_URL__}/forum.php?course_id=${encodeURIComponent(String(currentCourse))}&section_id=${encodeURIComponent(String(s.id))}`;
    return `
      <div class="card card-tight mt-8">
        <strong>Foro de discusión</strong>
        <div class="muted mt-6">Participe en esta conversación en una vista dedicada.</div>
        <div class="mt-10">
          <a class="btn" href="${escapeHtml(url)}">Abrir foro</a>
        </div>
      </div>
    `;
  }

  function renderTaskLinkUI(s) {
    const url = `${window.__BASE_URL__}/tasks.php?course_id=${encodeURIComponent(String(currentCourse))}&section_id=${encodeURIComponent(String(s.id))}`;
    return `
      <div class="card card-tight mt-8">
        <strong>Tarea</strong>
        <div class="muted mt-6">Gestione esta tarea en una vista dedicada.</div>
        <div class="mt-10">
          <a class="btn" href="${escapeHtml(url)}">Abrir tarea</a>
        </div>
      </div>
    `;
  }

  async function renderSectionBody(s) {
    const box = document.getElementById('section_body_' + s.id);
    if (!box) return;

    const tipo = (s.tipo || 'RECURSOS');

    if (tipo === 'QUIZ' || tipo === 'EXAMEN') {
      box.innerHTML = renderQuizLinkUI(s, tipo);
      return;
    }

    if (tipo === 'RECURSOS') {
      box.innerHTML = renderRecursosUI(s);
      return;
    }

    if (tipo === 'AVISO') {
      box.innerHTML = renderAvisoUI(s);
      return;
    }

    if (tipo === 'TAREA') {
      box.innerHTML = renderTaskLinkUI(s);
      return;
    }

    if (tipo === 'FORO') {
      box.innerHTML = renderForumLinkUI(s);
      return;
    }

    box.innerHTML = `<div class="muted">Tipo de sección no soportado: ${escapeHtml(tipo)}</div>`;
  }

  function getSectionsForCurrentWeek(courseId) {
    const selectedWeek = Number(getSelectedWeek(courseId));
    return (currentSections || [])
      .map(s => {
        const week = Number(s.semana) || 0;
        const order = Number(s.orden) || 0;
        return { ...s, _week: week, _order: order };
      })
      .filter(s => s._week === selectedWeek)
      .sort((a, b) => (a._order - b._order) || (a.id - b.id));
  }

  async function refreshDetailSections() {
    if (!currentCourse) return;
    const courseId = String(currentCourse);

    const weekFilterEl = document.getElementById('weekFilter');
    if (weekFilterEl) weekFilterEl.innerHTML = buildWeekFilterHtml(courseId);

    const sectionsEl = document.getElementById('sectionsContainer');
    const list = getSectionsForCurrentWeek(courseId);

    if (sectionsEl) {
      if (!list.length) {
        sectionsEl.innerHTML = `<div class="muted">No hay secciones para la semana ${escapeHtml(getSelectedWeek(courseId))}.</div>`;
        return;
      }
      sectionsEl.innerHTML = list.map(sectionCardHtml).join('');
    }

    for (const s of list) {
      try {
        await renderSectionBody(s);
      } catch (e) {
        console.error('Error renderSectionBody', s, e);
        const box = document.getElementById('section_body_' + s.id);
        if (box) box.innerHTML = `<div class="muted">Error renderizando sección.</div>`;
      }
    }
  }

  async function loadDetail(courseId) {
    currentCourse = courseId;
    const d = document.getElementById('detail');
    d.textContent = 'Cargando detalle...';

    try {
      const sec = await api('sections_list', { method: 'GET', params: { course_id: courseId } });
      currentSections = sec.data || [];

      const createSectionHtml = `<?php if ($rol === 'ADMIN' || $rol === 'DOCENTE'): ?>
        <hr class="sep" />
        <h4>Crear sección</h4>
        <form id="formSection" class="grid">
          <label>Título<input name="titulo" required /></label>
          <label>Descripcion<input name="descripcion"/></label>
          <label>Tipo
            <select name="tipo" required>
              <option value="RECURSOS" selected>Recursos</option>
              <option value="TAREA">Tarea</option>
              <option value="QUIZ">Quiz</option>
              <option value="EXAMEN">Examen</option>
              <option value="AVISO">Aviso</option>
              <option value="FORO">Foro</option>
            </select>
          </label>
          <label>Semana<input name="semana" type="number" value="1" /></label>
          <label>Orden<input name="orden" type="number" value="0" /></label>
          <button class="btn" type="submit">Crear sección</button>
          <div id="msgSection" class="muted"></div>
        </form>
      <?php else: ?>
        <hr class="sep" />
        <div class="muted">Solo docentes/admin pueden crear secciones y subir archivos.</div>
      <?php endif; ?>`;

      d.innerHTML = `
        <div class="muted">Curso #${escapeHtml(courseId)}</div>
        <div id="weekFilter" class="card" style="margin:8px 0; padding:10px;"></div>
        <div id="sectionsContainer"></div>
      ` + createSectionHtml;

      await refreshDetailSections();

      const formSection = document.getElementById('formSection');
      if (formSection) {
        formSection.addEventListener('submit', async (e) => {
          e.preventDefault();
          const fd = new FormData(e.target);
          fd.append('course_id', String(courseId));
          const msg = document.getElementById('msgSection');
          msg.textContent = '';
          try {
            await api('sections_create', { data: fd, isForm: true });
            msg.textContent = 'Sección creada.';
            e.target.reset();
            await loadDetail(courseId);
          } catch (err) {
            msg.textContent = err?.json?.message || 'Error creando sección';
          }
        });
      }

    } catch (err) {
      d.textContent = err?.json?.message || 'Error cargando detalle';
    }
  }

  document.getElementById('detail').addEventListener('change', async (e) => {
    const weekSel = e.target.closest('select[data-kind="weekSelect"]');
    if (weekSel && currentCourse) {
      const week = Number(weekSel.value) || 1;
      selectedWeekByCourse[String(currentCourse)] = week;
      await refreshDetailSections();
      return;
    }

    const typeSel = e.target.closest('select[data-kind="sectionType"]');
    if (typeSel) {
      const sectionId = typeSel.getAttribute('data-section');
      const tipo = typeSel.value;

      try {
        const fd = new FormData();
        fd.append('id', sectionId);
        fd.append('tipo', tipo);
        await api('sections_updateTipo', { data: fd, isForm: true });
        await loadDetail(currentCourse);
      } catch (err) {
        alert(err?.json?.message || 'Error actualizando tipo');
      }
      return;
    }
  });

  function humanSize(bytes) {
    const n = Number(bytes || 0);
    if (n < 1024) return `${n} B`;
    if (n < 1024 * 1024) return `${(n / 1024).toFixed(1)} KB`;
    return `${(n / (1024 * 1024)).toFixed(1)} MB`;
  }

  async function loadResourcesIntoSection(sectionId) {
    const box = document.getElementById('res_' + sectionId);
    if (!box) return;

    box.innerHTML = '<span class="muted">Cargando archivos…</span>';

    try {
      const rj = await api('resources_list', { method: 'GET', params: { section_id: sectionId } });
      const items = rj.data || [];

      if (!items.length) {
        box.innerHTML = '<span class="muted">No hay archivos en esta sección.</span>';
        return;
      }

      const rows = items.map(r => {
        const url = `${window.__BASE_URL__}/router.php?action=resources_download&id=${r.id}`;
        const delBtn = (IS_ADMIN || IS_DOCENTE)
          ? `<button class="btn danger" data-kind="resDelete" data-id="${r.id}" data-section="${sectionId}">Eliminar</button>`
          : '';

        return `
          <div style="display:flex; justify-content:space-between; gap:10px; align-items:center; padding:6px 0; border-bottom:1px solid #eee;">
            <div>
              <strong><a href="${escapeHtml(url)}" target="_blank" rel="noopener" style="color:#fff; text-decoration:none;">
                ${escapeHtml(r.original_name || ('Archivo #' + r.id))}
              </a></strong>
              <div class="muted">
                ${humanSize(r.size)} · Subido por ${escapeHtml(r.uploaded_by_nombre || '')}
              </div>
            </div>
            <div class="row gap-8 align-center">
              ${delBtn}
            </div>
          </div>
        `;
      }).join('');

      box.innerHTML = `<div class="card card-tight mt-8">
        <strong>Archivos</strong>
        <div class="mt-8">${rows}</div>
      </div>`;

    } catch (err) {
      console.error('Error resources_list', err);
      box.innerHTML = `<span class="muted">${escapeHtml(err?.json?.message || 'Error cargando archivos')}</span>`;
    }
  }

  document.getElementById('detail').addEventListener('click', async (e) => {
    const el = e.target.closest('[data-kind]');
    if (!el) return;

    const kind = el.getAttribute('data-kind');

    if (kind === 'loadRes') {
      const sectionId = el.getAttribute('data-section');
      await loadResourcesIntoSection(sectionId);
      return;
    }

    if (kind === 'deleteSection') {
      const sectionId = el.getAttribute('data-section');
      if (!confirm('¿Seguro que deseas eliminar esta sección? (Esto borra también sus archivos)')) return;

      try {
        const fd = new FormData();
        fd.append('id', String(sectionId));
        await api('sections_delete', { data: fd, isForm: true });
        await loadDetail(currentCourse);
      } catch (err) {
        alert(err?.json?.message || 'Error eliminando sección');
      }
      return;
    }

    if (kind === 'resDelete') {
      const id = el.getAttribute('data-id');
      const sectionId = el.getAttribute('data-section');
      if (!confirm('¿Eliminar este archivo?')) return;

      try {
        const fd = new FormData();
        fd.append('id', String(id));
        await api('resources_delete', { data: fd, isForm: true });
        await loadResourcesIntoSection(sectionId);
      } catch (err) {
        alert(err?.json?.message || 'Error eliminando archivo');
      }
      return;
    }
  });

  document.getElementById('detail').addEventListener('submit', async (e) => {
    const form = e.target.closest('form[data-kind]');
    if (!form) return;

    const kind = form.getAttribute('data-kind');

    if (kind === 'uploadRes') {
      e.preventDefault();

      const sectionId = form.getAttribute('data-section');
      const msg = document.querySelector(`[data-kind="msgUploadRes"][data-section="${sectionId}"]`);
      if (msg) msg.textContent = 'Subiendo archivo...'; // ✅ Cambio #5

      try {
        const fd = new FormData(form);
        fd.append('section_id', String(sectionId));

        await api('resources_upload', { data: fd, isForm: true });

        if (msg) msg.textContent = 'Archivo subido.';
        form.reset();
        await loadResourcesIntoSection(sectionId);
      } catch (err) {
        console.error('Error resources_upload', err);
        if (msg) msg.textContent = err?.json?.message || 'Error subiendo archivo';
      }
      return;
    }
  });

  const COURSE_ID = <?php echo json_encode($course_id); ?>;

  async function initVirtualCampus() {
    const titleEl = document.getElementById('courseTitle');
    const metaEl = document.getElementById('courseMeta');

    if (!COURSE_ID) {
      titleEl.textContent = 'Campus Virtual';
      metaEl.textContent = 'Curso no especificado.';
      document.getElementById('detail').textContent = 'Seleccione un curso desde E-Learning.';
      return;
    }

    try {
      const j = await api('course_get', { method: 'GET', params: { id: COURSE_ID } });
      const c = j.data || {};
      titleEl.textContent = c.nombre ? c.nombre : 'Campus Virtual';
      metaEl.innerHTML = `
        <div><strong>Docente:</strong> ${escapeHtml(c.docente_nombre || '')}</div>
        <div><strong>Grado:</strong> ${escapeHtml(c.grado || '')} <strong>— Sección:</strong> ${escapeHtml(c.seccion || '')}</div>
        ${c.descripcion ? `<div class="mt-6">${escapeHtml(c.descripcion)}</div>` : ''}
        ${(IS_STUDENT && c.nota_actual != null) ? `<div><strong>Nota actual del curso:</strong> ${escapeHtml(c.nota_actual)} / 100</div>` : ''}
      `;
    } catch (err) {
      titleEl.textContent = 'Campus Virtual';
      metaEl.textContent = err?.json?.message || 'Error cargando curso';
    }

    await loadDetail(COURSE_ID);
  }

  initVirtualCampus();
</script>

<?php include __DIR__ . '/components/footer.php'; ?>