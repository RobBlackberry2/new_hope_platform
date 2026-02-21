<?php
require_once __DIR__ . '/app/helpers/auth.php';
$config = require __DIR__ . '/app/config/config.php';
$base_url = $config['base_url'] ?? '';
$u = current_user();
if (!$u) {
  header('Location: ' . $base_url . '/login.php');
  exit;
}

$rol = $u['rol'] ?? '';
$course_id = (int) ($_GET['course_id'] ?? 0);
$section_id = (int) ($_GET['section_id'] ?? 0);
$tipo = (string) ($_GET['tipo'] ?? 'QUIZ');
$title = (string) ($_GET['title'] ?? '');

include __DIR__ . '/components/header.php';
?>

<section class="card">
  <div style="display:flex; justify-content:space-between; gap:12px; align-items:center;">
    <div>
      <h2 id="pageTitle">Quiz</h2>
      <div id="courseMeta" class="muted">Cargando curso...</div>
    </div>
    <div style="display:flex; gap:10px;">
      <a class="btn" href="<?= $base_url ?>/virtualcampus.php?course_id=<?= $course_id ?>">Volver al Campus</a>
      <a class="btn" href="<?= $base_url ?>/elearning.php">E-Learning</a>
    </div>
  </div>
</section>

<section class="card">
  <h3 id="quizHeader">Contenido</h3>
  <div id="quizRoot" class="muted">Cargando...</div>
</section>

<script>
  const COURSE_ID = <?php echo json_encode($course_id); ?>;
  const SECTION_ID = <?php echo json_encode($section_id); ?>;
  const TIPO = <?php echo json_encode($tipo); ?>;
  const SECTION_TITLE = <?php echo json_encode($title); ?>;
  const ROLE = <?php echo json_encode($rol); ?>;
  const IS_ADMIN = ROLE === 'ADMIN';
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

  function toLocalInputValue(dt) {
    if (!dt) return '';
    return String(dt).replace(' ', 'T').slice(0, 16);
  }

  async function initQuizPage() {
    const pageTitle = document.getElementById('pageTitle');
    const metaEl = document.getElementById('courseMeta');
    const headerEl = document.getElementById('quizHeader');

    const tipoTxt = 'Quiz/Examen';
    pageTitle.textContent = tipoTxt;
    headerEl.textContent = (SECTION_TITLE ? `${tipoTxt}: ${SECTION_TITLE}` : tipoTxt);

    if (!COURSE_ID || !SECTION_ID) {
      metaEl.textContent = 'Faltan parámetros (course_id / section_id).';
      document.getElementById('quizRoot').textContent = 'No se puede cargar.';
      return;
    }

    try {
      const cj = await api('course_get', { method: 'GET', params: { id: COURSE_ID } });
      const c = cj.data || {};
      metaEl.innerHTML = `
        <div><strong>Curso:</strong> ${escapeHtml(c.nombre || ('#' + COURSE_ID))}</div>
        <div><strong>Docente:</strong> ${escapeHtml(c.docente_nombre || '')}</div>
      `;
    } catch (err) {
      metaEl.textContent = err?.json?.message || 'Error cargando curso';
    }

    await renderQuiz();
  }

  function renderQuizAdminUI(sectionId, quiz, tipo) {
    const q = quiz || {};
    return `
      <div class="muted">${escapeHtml(q.title || SECTION_TITLE || '')}</div>
      <form data-kind="quizUpsert" data-section="${sectionId}" class="grid2" style="margin-top:8px;">
        <label>Título<input name="title" required value="${escapeHtml(q.title || SECTION_TITLE || '')}"/></label>
        <label>Tiempo (min)
          <input name="time_limit_minutes" type="number" min="1" required value="${q.time_limit_minutes ?? 60}"/>
        </label>
        <label>Disponible desde (opcional)
          <input name="available_from" type="datetime-local" value="${escapeHtml(toLocalInputValue(q.available_from || ''))}">
        </label>
        <label>Cierra (due_at) (opcional)
          <input name="due_at" type="datetime-local" value="${escapeHtml(toLocalInputValue(q.due_at || ''))}">
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
        <input type="hidden" name="section_id" value="${sectionId}">

        <div style="grid-column:1/-1; display:flex; gap:10px; align-items:center;">
          <button class="btn" type="submit">Guardar</button>
          <span class="muted" data-kind="msgQuiz" data-section="${sectionId}"></span>
        </div>
      </form>

      <hr class="sep" />
      <div class="card" style="padding:10px;">
        <strong>Preguntas</strong>
        <form data-kind="questionUpsert" data-section="${sectionId}" style="margin-top:8px;" class="grid2">
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
          <span class="muted" data-kind="msgQuestion" data-section="${sectionId}"></span>
        </form>
        <div id="questions_${sectionId}" class="muted" style="margin-top:10px;">Cargando preguntas...</div>
      </div>

      <hr class="sep" />
      <div class="card" style="padding:10px;">
        <strong>Intentos</strong>
        <div id="attempts_${sectionId}" class="muted" style="margin-top:8px;">(Guarda el quiz para ver intentos)</div>
      </div>
    `;
  }

  function renderQuizStudentUI(sectionId, quiz) {
    if (!quiz) return `<div class="muted">Aún no hay quiz configurado.</div>`;
    return `
      <div class="muted">${escapeHtml(quiz.title || '')}</div>
      ${quiz.instructions ? `<div style="margin-top:6px;">${escapeHtml(quiz.instructions)}</div>` : ''}
      <div class="muted" style="margin-top:6px;">
        ${quiz.time_limit_minutes ? `Tiempo: ${quiz.time_limit_minutes} min.` : 'Sin límite de tiempo.'}
        ${quiz.due_at ? ` — Cierra: ${escapeHtml(quiz.due_at)}` : ''}
      </div>
      <div style="margin-top:10px;">
        <button class="btn" data-kind="attemptStart" data-section="${sectionId}" data-quiz="${quiz.id}">Iniciar intento</button>
        <span class="muted" data-kind="msgAttempt" data-section="${sectionId}"></span>
      </div>
      <div id="attemptBox_${sectionId}" style="margin-top:10px;"></div>
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
      const optionsHtml = (q.type === 'SHORT')
        ? `<div class="muted">Respuesta corta (se califica manual).</div>`
        : (q.options || []).map(o => `
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
    <div style="display:flex; justify-content:space-between; gap:10px; align-items:center;">
      <div>
        <strong>${escapeHtml(r.student_nombre || '')}</strong>
        <div class="muted">Estado: ${escapeHtml(r.status)} — Nota: ${r.score}</div>
        <div class="muted">Inicio: ${escapeHtml(r.started_at)} — Fin: ${escapeHtml(r.finished_at || '')}</div>
      </div>
      <button class="btn" data-kind="attemptReview" data-attempt="${r.id}" data-quiz="${quizId}">
        Revisar
      </button>
    </div>
    <div id="review_${r.id}" style="margin-top:10px; display:none;"></div>
  </div>
`).join('');
  }

  function renderAttemptReviewUI(payload, quizId, attemptId) {
    const att = payload.attempt;
    const questions = payload.questions || [];
    const answers = payload.answers || [];
    const amap = {};
    for (const a of answers) amap[String(a.question_id)] = a;

    const blocks = questions.map(q => {
      const a = amap[String(q.id)] || {};
      const maxPts = Number(q.points || 0);

      if (q.type === 'SHORT') {
        return `
        <div class="card" style="padding:10px; margin-top:8px;">
          <div><strong>[SHORT] (${maxPts} pts)</strong></div>
          <div style="margin-top:6px;">${escapeHtml(q.question_text)}</div>
          <div class="muted" style="margin-top:6px;">Respuesta del estudiante:</div>
          <div style="margin-top:4px;">${escapeHtml(a.answer_text || '(sin respuesta)')}</div>

          <div style="margin-top:8px; display:flex; gap:10px; align-items:center;">
            <label style="display:flex; gap:8px; align-items:center;">
              Puntos:
              <input type="number" min="0" max="${maxPts}" value="${Number(a.points_awarded || 0)}"
                     data-grade-qid="${q.id}" style="width:90px;">
            </label>
            <span class="muted">/ ${maxPts}</span>
          </div>
        </div>
      `;
      }

      const selId = a.selected_option_id ? String(a.selected_option_id) : '';
      const chosen = (q.options || []).find(o => String(o.id) === selId);
      const correct = (q.options || []).find(o => String(o.id) === String(q.correct_option_id || ''));

      return `
      <div class="card" style="padding:10px; margin-top:8px;">
        <div><strong>[${escapeHtml(q.type)}] (${maxPts} pts)</strong></div>
        <div style="margin-top:6px;">${escapeHtml(q.question_text)}</div>
        <div class="muted" style="margin-top:6px;">Elegida: ${escapeHtml(chosen?.option_text || '(sin respuesta)')}</div>
        <div class="muted">Correcta: ${escapeHtml(correct?.option_text || '(no definida)')}</div>
        <div class="muted">Otorgados: ${Number(a.points_awarded || 0)} / ${maxPts}</div>
      </div>
    `;
    }).join('');

    return `
    <div class="card" style="padding:10px;">
      <strong>Revisión — ${escapeHtml(att.student_nombre || '')}</strong>
      <div class="muted">Estado actual: ${escapeHtml(att.status)}</div>

      ${blocks}

      <form data-kind="shortGradeForm" data-quiz="${quizId}" data-attempt="${attemptId}" style="margin-top:10px;">
        <button class="btn" type="submit">Guardar calificación SHORT</button>
        <span class="muted" data-kind="msgGrade" data-attempt="${attemptId}"></span>
      </form>
    </div>
  `;
  }

  function canShowResults(quiz) {
    const mode = (quiz?.show_results || 'AFTER_SUBMIT');
    if (mode === 'AFTER_SUBMIT') return true;
    if (mode === 'NO') return false;

    // AFTER_DUE
    if (!quiz?.due_at) return false;
    const due = new Date(String(quiz.due_at).replace(' ', 'T'));
    return Date.now() >= due.getTime();
  }

  async function loadStudentAttemptInto(sectionId, quizId, quizCfg) {
    const box = document.getElementById('attemptBox_' + sectionId);
    if (!box) return;

    const mine = await api('quiz_attempt_mine', { method: 'GET', params: { quiz_id: quizId } });
    const data = mine.data;

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
      const allowed = canShowResults(quizCfg);

      const msgDue = (quizCfg?.show_results === 'AFTER_DUE' && quizCfg?.due_at)
        ? `<div class="muted">Resultados disponibles después de: ${escapeHtml(quizCfg.due_at)}</div>`
        : '';

      box.innerHTML = `<div class="card" style="padding:10px;">
    <strong>Intento enviado</strong>
    <div class="muted">Estado: ${escapeHtml(att.status)}</div>

    ${allowed ? `
      <div class="muted">Nota: ${att.score}</div>
      <div class="muted">Puntos: ${att.raw_points}/${att.max_points}</div>
    ` : `
      <div class="muted">Los resultados no están disponibles según la configuración del quiz.</div>
      ${msgDue}
    `}

    <div class="muted">Si hay respuesta corta, el docente debe calificar.</div>
  </div>`;
      return;
    }

    const formQs = qs.map(q => {
      const a = amap[String(q.id)] || {};
      if (q.type === 'SHORT') {
        return `<div class="card" style="margin-top:8px; padding:10px;">
          <strong>${escapeHtml(q.question_text)}</strong>
          <textarea name="short_${q.id}" rows="2" style="margin-top:6px;">${escapeHtml(a.answer_text || '')}</textarea>
        </div>`;
      }

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

  async function renderQuiz() {
    const root = document.getElementById('quizRoot');
    root.textContent = 'Cargando...';

    const qj = await api('quizzes_getBySection', { method: 'GET', params: { section_id: SECTION_ID } });
    const quiz = qj.data || null;

    if (IS_ADMIN || IS_DOCENTE) {
      root.innerHTML = renderQuizAdminUI(SECTION_ID, quiz, TIPO);
      await loadQuestionsInto(SECTION_ID, quiz?.id || 0);
      await loadAttemptsInto(SECTION_ID, quiz?.id || 0);
      return;
    }

    if (IS_STUDENT) {
      root.innerHTML = renderQuizStudentUI(SECTION_ID, quiz);
      if (quiz?.id) await loadStudentAttemptInto(SECTION_ID, quiz.id, quiz);
      return;
    }

    root.innerHTML = '<div class="muted">Rol no soportado.</div>';
  }

  // Delegación de eventos
  document.getElementById('quizRoot').addEventListener('click', async (e) => {
    const el = e.target.closest('[data-kind]');
    if (!el) return;
    const kind = el.getAttribute('data-kind');

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

    if (kind === 'attemptReview') {
      const attemptId = el.getAttribute('data-attempt');
      const quizId = el.getAttribute('data-quiz');
      const box = document.getElementById('review_' + attemptId);

      if (!box) return;

      // toggle
      if (box.style.display === 'none') {
        box.style.display = 'block';
      } else {
        box.style.display = 'none';
        return;
      }

      box.innerHTML = '<div class="muted">Cargando revisión...</div>';
      try {
        const j = await api('quiz_attempts_list', { method: 'GET', params: { quiz_id: quizId, attempt_id: attemptId } });
        box.innerHTML = renderAttemptReviewUI(j.data, quizId, attemptId);
      } catch (err) {
        box.innerHTML = `<div class="muted">${escapeHtml(err?.json?.message || 'Error cargando revisión')}</div>`;
      }
      return;
    }
  });

  document.getElementById('quizRoot').addEventListener('submit', async (e) => {
    const form = e.target;

    if (form.matches('form[data-kind="quizUpsert"]')) {
      e.preventDefault();
      const sectionId = form.getAttribute('data-section');
      const fd = new FormData(form);

      const af = fd.get('available_from'); fd.set('available_from', af ? String(af).replace('T', ' ') + ':00' : '');
      const du = fd.get('due_at'); fd.set('due_at', du ? String(du).replace('T', ' ') + ':00' : '');

      const msg = document.querySelector(`[data-kind="msgQuiz"][data-section="${sectionId}"]`);
      if (msg) msg.textContent = '';

      try {
        await api('quizzes_upsert', { data: fd, isForm: true });
        if (msg) msg.textContent = 'Guardado.';
        await renderQuiz();
      } catch (err) {
        if (msg) msg.textContent = err?.json?.message || 'Error guardando';
      }
      return;
    }

    if (form.matches('form[data-kind="questionUpsert"]')) {
      e.preventDefault();
      const sectionId = form.getAttribute('data-section');

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

    if (form.matches('form[data-kind="attemptSubmit"]')) {
      e.preventDefault();
      const sectionId = form.getAttribute('data-section');
      const quizId = form.getAttribute('data-quiz');

      const msg = document.querySelector(`[data-kind="msgSubmitAttempt"][data-section="${sectionId}"]`);
      if (msg) msg.textContent = '';

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

    if (form.matches('form[data-kind="shortGradeForm"]')) {
      e.preventDefault();
      const quizId = form.getAttribute('data-quiz');
      const attemptId = form.getAttribute('data-attempt');
      const msg = form.querySelector(`[data-kind="msgGrade"][data-attempt="${attemptId}"]`);
      if (msg) msg.textContent = '';

      const wrap = document.getElementById('review_' + attemptId);
      const inputs = wrap.querySelectorAll('[data-grade-qid]');
      const grades = [];
      for (const inp of inputs) {
        const qid = Number(inp.getAttribute('data-grade-qid'));
        const pts = Number(inp.value || 0);
        grades.push({ question_id: qid, points_awarded: pts });
      }

      try {
        await api('quiz_attempt_grade_short', {
          data: { quiz_id: quizId, attempt_id: attemptId, grades: JSON.stringify(grades) }
        });

        if (msg) msg.textContent = 'Calificación guardada.';

        // refresca la lista de intentos (para ver estado/nota actualizada)
        await loadAttemptsInto(SECTION_ID, Number(quizId));
      } catch (err) {
        if (msg) msg.textContent = err?.json?.message || 'Error guardando calificación';
      }
      return;
    }
  });

  initQuizPage();
</script>

<?php include __DIR__ . '/components/footer.php'; ?>