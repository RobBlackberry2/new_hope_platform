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
$course_id = (int)($_GET['course_id'] ?? 0);
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
  const MAX_WEEKS = 40; //Aqui se modificaria el numero de semanas
  // { [courseId: string]: Set<number> }
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
        <select class="btn" style="padding:6px 10px;" data-kind="weekSelect">
          ${options}
        </select>
      </div>
    </div>
  `;
  }

  function sectionTypeSelect(s) {
    const tipos = ['RECURSOS', 'TAREA', 'QUIZ', 'EXAMEN', 'AVISO'];
    const current = (s.tipo || 'RECURSOS');
    if (!(IS_ADMIN || IS_DOCENTE)) return '';
    const opts = tipos.map(t => {
      const sel = (t === current) ? 'selected' : '';
      return `<option value="${t}" ${sel}>${t}</option>`;
    }).join('');
    return `<select class="btn" style="padding:6px 10px;" data-kind="sectionType" data-section="${s.id}">
    ${opts}
  </select>`;
  }

  function sectionCardHtml(s) {
    const titulo = escapeHtml(s.titulo);
    const descripcion = escapeHtml(s.descripcion || '');
    const semana = escapeHtml(s.semana);
    const orden = escapeHtml(s.orden);

    return `<div class="card" style="margin:8px 0;">
  <div style="display:flex; justify-content:space-between; gap:8px; align-items:center;">
    <div style="display:flex; gap:10px; align-items:center;">
      <strong>${s.titulo}</strong>
      
    </div>

    <div style="display:flex; gap:8px; align-items:center;">
    
      <div>${sectionTypeSelect(s)}</div>
      <button class="btn" data-kind="loadRes" data-section="${s.id}">Ver archivos</button>
      <?php if ($rol === 'ADMIN' || $rol === 'DOCENTE'): ?>
        <button class="btn" data-kind="deleteSection" data-section="${s.id}">Eliminar</button>
      <?php endif; ?>
    </div>
  </div>

  ${descripcion ? `<p>${descripcion}</p>` : ''}

  <div class="muted">Semana: ${semana} — Orden: ${orden}</div>

  <div id="section_body_${s.id}" style="margin-top:10px;"></div>

  <div id="res_${s.id}" class="muted" style="margin-top:6px;"></div>
</div>`;
  }

  function renderRecursosUI(s) {
    // upload solo admin/docente
    const upload = (IS_ADMIN || IS_DOCENTE) ? `
    <form data-kind="uploadRes" data-section="${s.id}" style="margin-top:8px;">
      <input type="file" name="file" required />
      <button class="btn" type="submit">Subir recurso</button>
      <span class="muted" data-kind="msgUploadRes" data-section="${s.id}"></span>
    </form>
  ` : `<div class="muted" style="margin-top:8px;">Recursos del docente.</div>`;

    return `
    <div class="muted">Materiales (presentaciones/lecturas).</div>
    ${upload}
  `;
  }

  function renderAvisoUI(s) {
    const txt = escapeHtml(s.descripcion || '');
    return `<div class="card" style="margin-top:8px; padding:10px;">
    <strong>Aviso</strong>
    <div style="margin-top:6px;">${txt || '<span class="muted">Sin contenido.</span>'}</div>
  </div>`;
  }

  function renderQuizLinkUI(s, tipo) {
    const label = (tipo === 'EXAMEN') ? 'Examen' : 'Quiz';
    const url = `${window.__BASE_URL__}/quiz.php?course_id=${encodeURIComponent(String(currentCourse))}&section_id=${encodeURIComponent(String(s.id))}&tipo=${encodeURIComponent(String(tipo))}&title=${encodeURIComponent(String(s.titulo || ''))}`;
    return `
      <div class="card" style="margin-top:8px; padding:10px;">
        <strong>${label}</strong>
        <div style="margin-top:10px;">
          <a class="btn" href="${escapeHtml(url)}">Abrir ${label}</a>
        </div>
      </div>
    `;
  }

  // Nota: lógica de Quiz/Examen se movió a quiz.php

  function toLocalInputValue(dt) {
    // dt viene tipo "2026-02-15 13:00:00" (MySQL)
    if (!dt) return '';
    // convertimos a "YYYY-MM-DDTHH:MM"
    return dt.replace(' ', 'T').slice(0, 16);
  }

  function fromLocalInputValue(v) {
    // "YYYY-MM-DDTHH:MM" => "YYYY-MM-DD HH:MM:00"
    if (!v) return '';
    return v.replace('T', ' ') + ':00';
  }

  function renderTareaUI(s, assignment) {
    const a = assignment || null;

    const docenteBox = (IS_ADMIN || IS_DOCENTE) ? `
    <div class="card" style="margin-top:8px; padding:10px;">
      <strong>Configurar tarea</strong>
      <form data-kind="assignmentUpsert" data-section="${s.id}" style="margin-top:8px;" class="grid2">
        <label>Título<input name="title" required value="${escapeHtml(a?.title || s.titulo || '')}" /></label>
        <label>Fecha límite
          <input name="due_at" type="datetime-local" value="${escapeHtml(toLocalInputValue(a?.due_at || ''))}" />
        </label>

        <label style="grid-column:1/-1">Instrucciones
          <textarea name="instructions" rows="3">${escapeHtml(a?.instructions || '')}</textarea>
        </label>

        <div style="grid-column:1/-1; display:flex; gap:10px; align-items:center;">
          <button class="btn" type="submit">Guardar tarea</button>
          <span class="muted" data-kind="msgAssignment" data-section="${s.id}"></span>
        </div>
      </form>
    </div>
  ` : '';

    const studentBox = IS_STUDENT ? `
    <div class="card" style="margin-top:8px; padding:10px;">
      <strong>Entrega</strong>
      <div class="muted" data-kind="studentAssignmentInfo" data-section="${s.id}">
        Cargando información de tu entrega...
      </div>

      <form data-kind="submissionUpload" data-section="${s.id}" style="margin-top:8px;">
        <input type="file" name="file" required />
        <button class="btn" type="submit">Añadir entrega</button>
        <span class="muted" data-kind="msgSubmission" data-section="${s.id}"></span>
      </form>
    </div>
  ` : '';

    const publicInfo = `
    <div class="card" style="margin-top:8px; padding:10px;">
      <strong>${escapeHtml(a?.title || 'Tarea')}</strong>
      <div class="muted">${a?.due_at ? `Fecha límite: ${escapeHtml(a.due_at)}` : 'Sin fecha límite'}</div>
      ${a?.instructions ? `<div style="margin-top:6px;">${escapeHtml(a.instructions)}</div>` : `<div class="muted" style="margin-top:6px;">Sin instrucciones.</div>`}
    </div>
  `;

    return publicInfo + docenteBox + studentBox;
  }

  async function renderSectionBody(s) {
    const box = document.getElementById('section_body_' + s.id);
    if (!box) return;

    const tipo = (s.tipo || 'RECURSOS');

    // QUIZ / EXAMEN
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
      const aj = await api('assignments_getBySection', {
        method: 'GET',
        params: { section_id: s.id }
      });
      const assignment = aj.data || null;

      box.innerHTML = renderTareaUI(s, assignment);

      if (IS_STUDENT && assignment?.id) {
        await loadMySubmissionIntoSection(s.id, assignment.id);
      }

      if ((IS_ADMIN || IS_DOCENTE) && assignment?.id) {
        await loadSubmissionsIntoSection(s.id, assignment.id);
      } else if ((IS_ADMIN || IS_DOCENTE) && !assignment?.id) {
        const listBox = box.querySelector(
          `[data-kind="submissionsList"][data-section="${s.id}"]`
        );
        if (listBox) listBox.textContent = 'Primero guarda la tarea para habilitar entregas.';
      }

      box.setAttribute('data-assignment-id', assignment?.id ? String(assignment.id) : '');
      return;
    }

    // fallback
    box.innerHTML = `<div class="muted">Tipo de sección no soportado: ${escapeHtml(tipo)}</div>`;
  }

  function renderSectionsHtml(courseId, sections) {
    const selectedWeek = Number(getSelectedWeek(courseId));

    const filtered = (sections || [])
      .map(s => {
        const week = Number(s.semana) || 0;
        const order = Number(s.orden) || 0;
        return { ...s, _week: week, _order: order };
      })
      .filter(s => s._week === selectedWeek);

    if (!filtered.length) {
      return `<div class="muted">No hay secciones para la semana ${escapeHtml(selectedWeek)}.</div>`;
    }

    filtered.sort((a, b) =>
      (a._order - b._order) ||
      (a.id - b.id)
    );

    // Ya no agrupamos por semana porque solo se ve una
    return filtered.map(sectionCardHtml).join('');
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

    // render bodies async
    for (const s of list) {
      try { await renderSectionBody(s); }
      catch (e) {
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
            <option value="QUIZ">Quiz/Examen</option>
            <option value="AVISO">Aviso</option>
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

      // Estructura fija (así el filtro puede re-renderizar SOLO la lista)
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
            await loadDetail(courseId); // refetch
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
    // cambio de semana (tu código)
    const weekSel = e.target.closest('select[data-kind="weekSelect"]');
    if (weekSel && currentCourse) {
      const week = Number(weekSel.value) || 1;
      selectedWeekByCourse[String(currentCourse)] = week;
      await refreshDetailSections();
      return;
    }

    // cambio tipo de sección
    const typeSel = e.target.closest('select[data-kind="sectionType"]');
    if (typeSel) {
      const sectionId = typeSel.getAttribute('data-section');
      const tipo = typeSel.value;

      try {
        const fd = new FormData();
        fd.append('id', sectionId);
        fd.append('tipo', tipo);
        await api('sections_updateTipo', { data: fd, isForm: true });

        // refrescar secciones desde servidor para que "tipo" quede en currentSections
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
        const url = `uploads/${r.stored_name}`; // stored_name ya incluye "course_X/archivo.ext"
        const delBtn = (IS_ADMIN || IS_DOCENTE)
          ? `<button class="btn danger" data-kind="resDelete" data-id="${r.id}" data-section="${sectionId}">Eliminar</button>`
          : '';

        return `
        <div style="display:flex; justify-content:space-between; gap:10px; align-items:center; padding:6px 0; border-bottom:1px solid #eee;">
          <div>
            <a href="${escapeHtml(url)}" target="_blank" rel="noopener">
              ${escapeHtml(r.original_name || ('Archivo #' + r.id))}
            </a>
            <div class="muted">
              ${humanSize(r.size)} · Subido por ${escapeHtml(r.uploaded_by_nombre || '')}
            </div>
          </div>
          <div style="display:flex; gap:8px; align-items:center;">
            ${delBtn}
          </div>
        </div>
      `;
      }).join('');

      box.innerHTML = `<div class="card" style="margin-top:8px; padding:10px;">
      <strong>Archivos</strong>
      <div style="margin-top:8px;">${rows}</div>
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
        await loadDetail(currentCourse); // refresca secciones
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

  // Nota: submit handlers para quiz/examen están en quiz.php
  async function loadMySubmissionIntoSection(sectionId, assignmentId) {
    const infoBox = document.querySelector(`[data-kind="studentAssignmentInfo"][data-section="${sectionId}"]`);
    if (!infoBox) return;

    try {
      const j = await api('submissions_getMine', { method: 'GET', params: { assignment_id: assignmentId } });
      const data = j.data;

      if (!data || !data.submission) {
        infoBox.innerHTML = `<div class="muted">Aún no has entregado.</div>`;
        return;
      }

      const file = data.file;
      const grade = data.grade;

      const fileHtml = file?.public_url
        ? `<div>Tu archivo: <a class="link" href="${window.__BASE_URL__ + '/' + file.public_url}" target="_blank">${escapeHtml(file.original_name)}</a></div>`
        : `<div class="muted">Entrega registrada (sin archivo).</div>`;

      let gradeHtml = `<div class="muted">Sin calificación aún.</div>`;
      if (grade) {
        const score = Number(grade.score || 0);
        const pass = score >= 70; // regla general (puedes leer passing_score luego)
        gradeHtml = `
        <div style="margin-top:6px;"><strong>Nota:</strong> ${score} — ${pass ? 'Aprobado' : 'Reprobado'}</div>
        ${grade.feedback ? `<div style="margin-top:6px;"><strong>Retroalimentación:</strong> ${escapeHtml(grade.feedback)}</div>` : ''}
      `;
      }

      infoBox.innerHTML = fileHtml + gradeHtml;

    } catch (err) {
      infoBox.textContent = err?.json?.message || 'Error cargando tu entrega';
    }
  }

  function submissionRowHtml(r) {
    const who = r.group_id ? `Grupo: ${escapeHtml(r.group_name || ('#' + r.group_id))}` : escapeHtml(r.student_nombre || ('Estudiante #' + r.student_id));
    const file = r.latest_file;

    const fileHtml = file?.public_url
      ? `<a class="link" href="${window.__BASE_URL__ + '/' + file.public_url}" target="_blank">${escapeHtml(file.original_name)}</a>`
      : `<span class="muted">Sin archivo</span>`;

    const grade = r.grade;
    const scoreVal = grade ? String(grade.score ?? '') : '';
    const feedbackVal = grade ? (grade.feedback ?? '') : '';

    return `
    <div class="card" style="margin:8px 0; padding:10px;">
      <div style="display:flex; justify-content:space-between; gap:10px;">
        <div>
          <div><strong>${who}</strong></div>
          <div class="muted">Entregado: ${escapeHtml(r.submitted_at || '')}</div>
          <div style="margin-top:6px;">Archivo: ${fileHtml}</div>
        </div>

        <form data-kind="gradeSet" data-submission="${r.id}" style="min-width:260px;">
          <label>Nota
            <input name="score" type="number" min="0" value="${escapeHtml(scoreVal)}" required />
          </label>
          <label>Feedback
            <textarea name="feedback" rows="2">${escapeHtml(feedbackVal)}</textarea>
          </label>
          <button class="btn" type="submit">Guardar</button>
          <div class="muted" data-kind="msgGrade" data-submission="${r.id}"></div>
        </form>
      </div>
    </div>
  `;
  }

  async function loadSubmissionsIntoSection(sectionId, assignmentId) {
    const box = document.querySelector(`[data-kind="submissionsList"][data-section="${sectionId}"]`);
    if (!box) return;

    box.textContent = 'Cargando entregas...';
    try {
      const j = await api('submissions_listByAssignment', { method: 'GET', params: { assignment_id: assignmentId } });
      const rows = j.data || [];
      if (!rows.length) { box.textContent = 'Aún no hay entregas.'; return; }
      box.innerHTML = rows.map(submissionRowHtml).join('');
    } catch (err) {
      box.textContent = err?.json?.message || 'Error cargando entregas';
    }
  }

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
        ${c.descripcion ? `<div style="margin-top:6px;">${escapeHtml(c.descripcion)}</div>` : ''}
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
