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
  <p class="muted">Cursos, secciones y archivos. (Evaluaciones/foros/gamificación después)</p>
</section>

<?php if ($rol === 'ADMIN'): ?>
  <section class="card">
    <h3>Crear curso</h3>

    <form id="formCourse" class="grid2" style="max-width:900px; margin:0 auto;">
      <label>Nombre<input name="nombre" required /></label>
      <label>Grado (7-11)<input name="grado" type="number" min="7" max="11" value="7" /></label>

      <label>Sección<input name="seccion" required /></label>

      <label>Docente
        <select name="docente_user_id" id="docente_user_id" required>
          <option value="" disabled selected>Seleccione un docente...</option>
        </select>
      </label>

      <label style="grid-column:1/-1">Descripción
        <textarea name="descripcion" rows="3"></textarea>
      </label>

      <div style="grid-column:1/-1; display:flex; gap:12px; align-items:center;">
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

  function courseCard(c) {
    const delBtn = IS_ADMIN
      ? `<button class="btn" data-kind="deleteCourse" data-id="${c.id}">Eliminar</button>`
      : '';

    return `<div class="card" style="text-align:left; width:100%; margin:8px 0;">
    <div style="display:flex; justify-content:space-between; gap:8px; align-items:flex-start;">
      <div style="flex:1;">
        <div><strong>${c.nombre}</strong></div>
        <div class="muted">Grado: ${c.grado} — Docente: ${c.docente_nombre || ''}</div>
        <div class="muted">${(c.descripcion || '').replace(/</g, '&lt;')}</div>
        <div class="muted">${c.seccion}</div>
      </div>

      <div style="display:flex; gap:8px;">
        <button class="btn" data-kind="select" data-id="${c.id}">Abrir</button>
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
        opts.push(`<option value="${d.id}">${label}</option>`);
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
      ${sectionTypeSelect(s)}
    </div>

    <div style="display:flex; gap:8px; align-items:center;">
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

  function renderQuizExamPlaceholder(s) {
    return `<div class="muted" style="margin-top:8px;">
    (Pendiente: ${escapeHtml(s.tipo)} se implementa en Fase 2)
  </div>`;
  }

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
      await renderQuizSection(s, tipo);
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
            <option value="QUIZ">Quiz</option>
            <option value="EXAMEN">Examen</option>
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
        // refrescar lista y limpiar detalle si estabas viendo ese curso
        if (String(currentCourse) === String(courseId)) {
          document.getElementById('detail').textContent = 'Selecciona un curso.';
          currentCourse = null;
        }
        await loadCourses();
      } catch (err) {
        alert(err?.json?.message || 'Error eliminando curso');
      }
      return;
    }

    if (kind === 'select') {
      await loadDetail(el.getAttribute('data-id'));
    }
  });

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


    if (kind === 'attemptStart') {
      const sectionId = el.getAttribute('data-section');
      const quizId = el.getAttribute('data-quiz');
      const msg = document.querySelector(`[data-kind="msgAttempt"][data-section="${sectionId}"]`);
      if (msg) msg.textContent = '';

      try {
        await api('quiz_attempt_start', { data: { quiz_id: quizId } });
        if (msg) msg.textContent = 'Intento iniciado.';
        await loadStudentAttemptInto(sectionId, Number(quizId));
      } catch (err) {
        if (msg) msg.textContent = err?.json?.message || 'Error iniciando intento';
      }
      return;
    }

    if (kind === 'qDel') {
      const id = el.getAttribute('data-id');
      const sectionId = el.getAttribute('data-section');
      const quizId = el.getAttribute('data-quiz');
      if (!confirm('¿Eliminar pregunta?')) return;

      await api('quiz_questions_delete', { data: { id, quiz_id: quizId } });
      await loadQuestionsInto(sectionId, Number(quizId));
      return;
    }

    if (kind === 'optDel') {
      const id = el.getAttribute('data-id');
      const sectionId = el.getAttribute('data-section');
      const quizId = el.getAttribute('data-quiz');
      if (!confirm('¿Eliminar opción?')) return;

      await api('quiz_options_delete', { data: { id } });
      await loadQuestionsInto(sectionId, Number(quizId));
      return;
    }
  });

  document.getElementById('detail').addEventListener('submit', async (e) => {
    const form = e.target;

    // Guardar quiz config
    if (form.matches('form[data-kind="quizUpsert"]')) {
      e.preventDefault();
      const sectionId = form.getAttribute('data-section');
      const fd = new FormData(form);

      // datetime-local => MySQL datetime
      const af = fd.get('available_from'); fd.set('available_from', af ? String(af).replace('T', ' ') + ':00' : '');
      const du = fd.get('due_at'); fd.set('due_at', du ? String(du).replace('T', ' ') + ':00' : '');

      const msg = document.querySelector(`[data-kind="msgQuiz"][data-section="${sectionId}"]`);
      if (msg) msg.textContent = '';

      try {
        await api('quizzes_upsert', { data: fd, isForm: true });
        if (msg) msg.textContent = 'Quiz guardado.';
        await loadDetail(currentCourse);
      } catch (err) {
        if (msg) msg.textContent = err?.json?.message || 'Error guardando quiz';
      }
      return;
    }

    // Agregar pregunta
    if (form.matches('form[data-kind="questionUpsert"]')) {
      e.preventDefault();
      const sectionId = form.getAttribute('data-section');

      // necesitamos quiz_id -> lo tomamos del server
      const qj = await api('quizzes_getBySection', { method: 'GET', params: { section_id: sectionId } });
      const quiz = qj.data;
      if (!quiz?.id) { alert('Guarda el quiz primero.'); return; }

      const fd = new FormData(form);
      fd.append('quiz_id', String(quiz.id));

      await api('quiz_questions_upsert', { data: fd, isForm: true });
      form.reset();
      await loadQuestionsInto(sectionId, quiz.id);
      return;
    }

    // Agregar opción
    if (form.matches('form[data-kind="optAdd"]')) {
      e.preventDefault();
      const questionId = form.getAttribute('data-question');
      const sectionId = form.getAttribute('data-section');
      const quizId = form.getAttribute('data-quiz');

      const fd = new FormData(form);
      fd.append('question_id', String(questionId));
      fd.append('is_correct', fd.get('is_correct') ? '1' : '0');

      await api('quiz_options_upsert', { data: fd, isForm: true });
      form.reset();
      await loadQuestionsInto(sectionId, Number(quizId));
      return;
    }

    // Enviar intento estudiante
    if (form.matches('form[data-kind="attemptSubmit"]')) {
      e.preventDefault();
      const sectionId = form.getAttribute('data-section');
      const quizId = form.getAttribute('data-quiz');

      const msg = document.querySelector(`[data-kind="msgSubmitAttempt"][data-section="${sectionId}"]`);
      if (msg) msg.textContent = '';

      // construir answers desde inputs
      const fd = new FormData(form);
      const qj = await api('quiz_questions_list', { method: 'GET', params: { quiz_id: quizId } });
      const qs = qj.data || [];

      const answers = [];
      for (const q of qs) {
        if (q.type === 'SHORT') {
          const val = fd.get('short_' + q.id);
          answers.push({ question_id: q.id, answer_text: String(val || '') });
        } else {
          const val = fd.get('opt_' + q.id);
          if (val) answers.push({ question_id: q.id, selected_option_id: Number(val) });
        }
      }

      try {
        const sendFd = new FormData();
        sendFd.append('quiz_id', String(quizId));
        sendFd.append('answers', JSON.stringify(answers));
        await api('quiz_attempt_submit', { data: sendFd, isForm: true });
        if (msg) msg.textContent = 'Enviado.';
        await loadStudentAttemptInto(sectionId, Number(quizId));
      } catch (err) {
        if (msg) msg.textContent = err?.json?.message || 'Error enviando intento';
      }
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

  async function renderQuizSection(s, tipo) {
    const box = document.getElementById('section_body_' + s.id);
    if (!box) return;

    // 1) cargar quiz por section
    const qj = await api('quizzes_getBySection', { method: 'GET', params: { section_id: s.id } });
    const quiz = qj.data || null;

    // UI base
    box.innerHTML = `
    <div class="card" style="margin-top:8px; padding:10px;">
      <strong>${tipo === 'EXAMEN' ? 'Examen' : 'Quiz'}</strong>
      <div class="muted">${quiz ? 'Configurado' : 'Aún no configurado'}</div>
      <div id="quiz_area_${s.id}" class="muted" style="margin-top:8px;">Cargando...</div>
    </div>
  `;

    const area = document.getElementById('quiz_area_' + s.id);

    // ADMIN/DOCENTE: config + preguntas
    if (IS_ADMIN || IS_DOCENTE) {
      area.innerHTML = renderQuizAdminUI(s, quiz, tipo);
      await loadQuestionsInto(s.id, quiz?.id || 0);
      await loadAttemptsInto(s.id, quiz?.id || 0);
    }

    // ESTUDIANTE: intento
    if (IS_STUDENT) {
      area.innerHTML = renderQuizStudentUI(s, quiz);
      if (quiz?.id) {
        await loadStudentAttemptInto(s.id, quiz.id);
      }
    }
  }

  function renderQuizAdminUI(s, quiz, tipo) {
    const q = quiz || {};
    return `
    <form data-kind="quizUpsert" data-section="${s.id}" class="grid2" style="margin-top:8px;">
      <label>Título<input name="title" required value="${escapeHtml(q.title || s.titulo || '')}"/></label>
      <label>Tiempo (min)
      <input name="time_limit_minutes" type="number" min="1" required
            value="${q.time_limit_minutes ?? 60}"/>
      </label>
      <label>Disponible desde (opcional)
        <input name="available_from" type="datetime-local" value="${q.available_from ? escapeHtml(q.available_from.replace(' ', 'T').slice(0, 16)) : ''}">
      </label>
      <label>Cierra (due_at) (opcional)
        <input name="due_at" type="datetime-local" value="${q.due_at ? escapeHtml(q.due_at.replace(' ', 'T').slice(0, 16)) : ''}">
      </label>

      <label>Aprobación<input name="passing_score" type="number" min="0" max="100" value="${q.passing_score ?? 70}"/></label>
      <label>Mostrar resultados
        <select name="show_results">
          <option value="NO" ${(q.show_results === 'NO') ? 'selected' : ''}>NO</option>
          <option value="AFTER_SUBMIT" ${(q.show_results === 'AFTER_SUBMIT' || !q.show_results) ? 'selected' : ''}>DESPUÉS DE ENVIAR</option>
          <option value="AFTER_DUE" ${(q.show_results === 'AFTER_DUE') ? 'selected' : ''}>DESPUÉS DE CIERRE</option>
        </select>
      </label>

      <label style="grid-column:1/-1">Instrucciones
        <textarea name="instructions" rows="3">${escapeHtml(q.instructions || '')}</textarea>
      </label>

      <input type="hidden" name="section_id" value="${s.id}">
      <input type="hidden" name="is_exam" value="${tipo === 'EXAMEN' ? 1 : 0}">

      <div style="grid-column:1/-1; display:flex; gap:10px; align-items:center;">
        <button class="btn" type="submit">Guardar</button>
        <span class="muted" data-kind="msgQuiz" data-section="${s.id}"></span>
      </div>
    </form>

    <hr class="sep" />
    <div class="card" style="padding:10px;">
      <strong>Preguntas</strong>
      <form data-kind="questionUpsert" data-section="${s.id}" style="margin-top:8px;" class="grid2">
        <label>Tipo
          <select name="type">
            <option value="MCQ">Selección</option>
            <option value="TF">Selección única</option>
            <option value="SHORT">Respuesta corta</option>
          </select>
        </label>
        <label>Puntos<input name="points" type="number" min="1" value="10"></label>

        <label style="grid-column:1/-1">Pregunta<textarea name="question_text" rows="2" required></textarea></label>
        <label>Orden<input name="orden" type="number" value="0"></label>

        <button class="btn" type="submit">Agregar pregunta</button>
        <span class="muted" data-kind="msgQuestion" data-section="${s.id}"></span>
      </form>

      <div id="questions_${s.id}" class="muted" style="margin-top:10px;">Cargando preguntas...</div>
    </div>

    <hr class="sep" />
    <div class="card" style="padding:10px;">
      <strong>Intentos</strong>
      <div id="attempts_${s.id}" class="muted" style="margin-top:8px;">(Guarda el quiz para ver intentos)</div>
    </div>
  `;
  }

  function renderQuizStudentUI(s, quiz) {
    if (!quiz) return `<div class="muted">Aún no hay quiz configurado.</div>`;

    return `
    <div class="muted">${escapeHtml(quiz.title || '')}</div>
    ${quiz.instructions ? `<div style="margin-top:6px;">${escapeHtml(quiz.instructions)}</div>` : ''}
    <div class="muted" style="margin-top:6px;">
      ${quiz.time_limit_minutes ? `Tiempo: ${quiz.time_limit_minutes} min.` : 'Sin límite de tiempo.'}
      ${quiz.due_at ? ` — Cierra: ${escapeHtml(quiz.due_at)}` : ''}
    </div>

    <div style="margin-top:10px;">
      <button class="btn" data-kind="attemptStart" data-section="${s.id}" data-quiz="${quiz.id}">Iniciar intento</button>
      <span class="muted" data-kind="msgAttempt" data-section="${s.id}"></span>
    </div>

    <div id="attemptBox_${s.id}" style="margin-top:10px;"></div>
  `;
  }

  async function loadQuestionsInto(sectionId, quizId) {
    const box = document.getElementById('questions_' + sectionId);
    if (!box) return;

    if (!quizId) { box.textContent = 'Guarda el quiz para gestionar preguntas.'; return; }

    box.textContent = 'Cargando preguntas...';
    const j = await api('quiz_questions_list', { method: 'GET', params: { quiz_id: quizId } });
    const qs = j.data || [];

    if (!qs.length) { box.textContent = 'Sin preguntas.'; return; }

    box.innerHTML = qs.map(q => {
      const optionsHtml = (q.type === 'SHORT') ? `<div class="muted">Respuesta corta (se califica manual).</div>` :
        (q.options || []).map(o => `
        <div style="display:flex; gap:8px; align-items:center; margin-top:4px;">
          <span>${o.is_correct == 1 ? '✅' : '⬜'} ${escapeHtml(o.option_text)}</span>
          <button class="btn danger" data-kind="optDel" data-id="${o.id}" data-section="${sectionId}" data-quiz="${quizId}">X</button>
        </div>
      `).join('') + `
        <form data-kind="optAdd" data-question="${q.id}" data-section="${sectionId}" data-quiz="${quizId}" style="margin-top:6px; display:flex; gap:8px; align-items:center;">
          <input name="option_text" placeholder="Opción" required />
          <label style="display:flex; gap:6px; align-items:center;">
            <input type="checkbox" name="is_correct" value="1"> Correcta
          </label>
          <button class="btn" type="submit">Agregar</button>
        </form>
      `;

      return `
      <div class="card" style="margin-top:8px; padding:10px;">
        <div style="display:flex; justify-content:space-between; gap:8px;">
          <div>
            <strong>[${escapeHtml(q.type)}] (${q.points} pts)</strong>
            <div style="margin-top:6px;">${escapeHtml(q.question_text)}</div>
          </div>
          <button class="btn danger" data-kind="qDel" data-id="${q.id}" data-section="${sectionId}" data-quiz="${quizId}">Eliminar</button>
        </div>
        <div style="margin-top:8px;">${optionsHtml}</div>
      </div>
    `;
    }).join('');
  }

  async function loadAttemptsInto(sectionId, quizId) {
    const box = document.getElementById('attempts_' + sectionId);
    
    if (!box) return;
    if (!quizId) { box.textContent = 'Guarda el quiz para ver intentos.'; return; }

    box.textContent = 'Cargando intentos...';
    const j = await api('quiz_attempts_list', { method: 'GET', params: { quiz_id: quizId } });
    const rows = j.data || [];
    if (!rows.length) { box.textContent = 'Sin intentos.'; return; }

    box.innerHTML = rows.map(r => `
    <div class="card" style="margin-top:8px; padding:10px;">
      <strong>${escapeHtml(r.student_nombre || '')}</strong>
      <div class="muted">Estado: ${escapeHtml(r.status)} — Nota: ${r.score}</div>
      <div class="muted">Inicio: ${escapeHtml(r.started_at)} — Fin: ${escapeHtml(r.finished_at || '')}</div>
      <div class="muted">Para SHORT: califica desde la DB/UI avanzada (lo hacemos después si querés).</div>
    </div>
  `).join('');
  }

  async function loadStudentAttemptInto(sectionId, quizId) {
    const box = document.getElementById('attemptBox_' + sectionId);
    if (!box) return;

    const mine = await api('quiz_attempt_mine', { method: 'GET', params: { quiz_id: quizId } });
    const data = mine.data;

    // cargar preguntas
    const qj = await api('quiz_questions_list', { method: 'GET', params: { quiz_id: quizId } });
    const qs = qj.data || [];

    if (!data) {
      box.innerHTML = `<div class="muted">Aún no has iniciado intento.</div>`;
      return;
    }

    const att = data.attempt;
    const answers = data.answers || [];
    const amap = {};
    for (const a of answers) amap[String(a.question_id)] = a;

    if (att.status !== 'IN_PROGRESS') {
      // mostrar resultado simple
      box.innerHTML = `<div class="card" style="padding:10px;">
      <strong>Intento enviado</strong>
      <div class="muted">Estado: ${escapeHtml(att.status)} — Nota: ${att.score}</div>
      <div class="muted">Puntos: ${att.raw_points}/${att.max_points}</div>
      <div class="muted">Si hay respuesta corta, el docente debe calificar.</div>
    </div>`;
      return;
    }

    // formulario
    const formQs = qs.map(q => {
      const a = amap[String(q.id)] || {};
      if (q.type === 'SHORT') {
        return `<div class="card" style="margin-top:8px; padding:10px;">
        <strong>${escapeHtml(q.question_text)}</strong>
        <textarea name="short_${q.id}" rows="2" style="margin-top:6px;">${escapeHtml(a.answer_text || '')}</textarea>
      </div>`;
      }

      // MCQ/TF radios
      const opts = (q.options || []).map(o => {
        const checked = String(a.selected_option_id || '') === String(o.id) ? 'checked' : '';
        return `<label style="display:block; margin-top:4px;">
        <input type="radio" name="opt_${q.id}" value="${o.id}" ${checked} />
        ${escapeHtml(o.option_text)}
      </label>`;
      }).join('');

      return `<div class="card" style="margin-top:8px; padding:10px;">
      <strong>${escapeHtml(q.question_text)}</strong>
      <div style="margin-top:6px;">${opts || '<span class="muted">Sin opciones.</span>'}</div>
    </div>`;
    }).join('');

    box.innerHTML = `
    <form data-kind="attemptSubmit" data-section="${sectionId}" data-quiz="${quizId}">
      ${formQs}
      <div style="display:flex; gap:10px; align-items:center; margin-top:10px;">
        <button class="btn" type="submit">Enviar</button>
        <span class="muted" data-kind="msgSubmitAttempt" data-section="${sectionId}"></span>
      </div>
    </form>
  `;
  }


  loadCourses();
  if (IS_ADMIN) loadDocentes();
</script>


<?php include __DIR__ . '/components/footer.php'; ?>