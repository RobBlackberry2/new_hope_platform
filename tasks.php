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
$section_id = (int)($_GET['section_id'] ?? 0);
?>
<section class="card">
  <div style="display:flex; justify-content:space-between; gap:12px; align-items:center; flex-wrap:wrap;">
    <div>
      <h2 id="taskTitle">Tarea</h2>
      <div id="taskMeta" class="muted">Cargando tarea...</div>
    </div>
    <div style="display:flex; gap:8px;">
      <a class="btn" href="<?= $base_url ?>/virtualcampus.php?course_id=<?= urlencode((string)$course_id) ?>">Volver al Campus</a>
      <a class="btn" href="<?= $base_url ?>/elearning.php">E-Learning</a>
    </div>
  </div>
</section>

<section class="card">
  <h3>Detalle de la tarea</h3>
  <div id="taskDetail" class="muted">Cargando...</div>
</section>

<section class="card">
  <h3>Archivos adjuntos a la tarea</h3>
  <div id="resBox" class="muted">Cargando...</div>
</section>

<script>
  const COURSE_ID = <?php echo json_encode($course_id); ?>;
  const SECTION_ID = <?php echo json_encode($section_id); ?>;
  const ROLE = <?php echo json_encode($rol); ?>;
  const IS_ADMIN = ROLE === 'ADMIN';
  const IS_DOCENTE = ROLE === 'DOCENTE';
  const IS_STUDENT = ROLE === 'ESTUDIANTE';

  let currentAssignment = null;
  let currentSection = null;
  let currentCourse = null;

  const SUBMISSIONS_PAGE_SIZE = 5;
  let submissionsCache = [];
  let submissionsPage = 1;

  function escapeHtml(v) {
    return String(v ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function humanSize(bytes) {
    const n = Number(bytes || 0);
    if (n < 1024) return `${n} B`;
    if (n < 1024 * 1024) return `${(n / 1024).toFixed(1)} KB`;
    return `${(n / (1024 * 1024)).toFixed(1)} MB`;
  }

  function toLocalInputValue(dt) {
    if (!dt) return '';
    return dt.replace(' ', 'T').slice(0, 16);
  }

  function fromLocalInputValue(v) {
    if (!v) return '';
    return v.replace('T', ' ') + ':00';
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

  function dueExpired(a) {
    return !!(a?.due_at && (new Date(a.due_at.replace(' ', 'T')) < new Date()));
  }

  function renderTaskPage(section, assignment) {
    const s = section || {};
    const a = assignment || null;
    const expired = dueExpired(a);
    const isGroupTask = Number(a?.is_group || 0) === 1;

    const publicInfo = `
      <div class="card card-tight mt-8">
        <strong>${escapeHtml(a?.title || s?.titulo || 'Tarea')}</strong>
        <div class="muted">${a?.due_at ? `Fecha límite: ${escapeHtml(a.due_at)}` : 'Sin fecha límite'}</div>
        <div class="muted">
          ${a?.weight_percent != null ? `Peso: ${escapeHtml(a.weight_percent)}%` : 'Peso no definido'}
          · Max: ${escapeHtml(a?.max_score ?? 100)}
          · Aprobación: ${escapeHtml(a?.passing_score ?? 70)}
          ${isGroupTask ? ' · Modalidad: Grupal' : ' · Modalidad: Individual'}
        </div>
        ${a?.instructions ? `<div class="mt-6">${escapeHtml(a.instructions)}</div>` : `<div class="muted mt-6">Sin instrucciones.</div>`}
      </div>
    `;

    const teacherConfigBox = (IS_ADMIN || IS_DOCENTE) ? `
      <div class="card card-tight mt-8">
        <strong>Configurar tarea</strong>
        <form id="formAssignment" class="grid2 mt-8">
          <label>Título
            <input name="title" required value="${escapeHtml(a?.title || s?.titulo || '')}" />
          </label>

          <label>Fecha límite
            <input name="due_at" type="datetime-local" value="${escapeHtml(toLocalInputValue(a?.due_at || ''))}" />
          </label>

          <label>Peso (% del curso)
            <input name="weight_percent" type="number" min="0" max="100" value="${escapeHtml(a?.weight_percent ?? '')}" />
          </label>

          <label>
            <input name="is_group" type="checkbox" value="1" ${(Number(a?.is_group || 0) === 1) ? 'checked' : ''} />
            Tarea grupal
          </label>

          <label class="span-all">Instrucciones
            <textarea name="instructions" rows="3">${escapeHtml(a?.instructions || '')}</textarea>
          </label>

          <div style="grid-column:1/-1; display:flex; gap:10px; align-items:center;">
            <button class="btn" type="submit">Guardar tarea</button>
            <span class="muted" id="msgAssignment"></span>
          </div>
        </form>
      </div>
    ` : '';

    const groupsBox = ((IS_ADMIN || IS_DOCENTE) && isGroupTask) ? `
      <div class="card card-tight mt-8">
        <strong>Grupos de trabajo</strong>
        <div class="muted mt-6">Administre grupos y miembros para esta tarea grupal.</div>

        <form id="formCreateGroup" class="mt-8" style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
          <input name="name" placeholder="Nombre del grupo (ej. Grupo 1)" required />
          <button class="btn" type="submit">Crear grupo</button>
          <span class="muted" id="msgGroup"></span>
        </form>

        <div id="groupsBox" class="mt-10 muted">Cargando grupos...</div>
      </div>
    ` : '';

    const filesUploadBox = (IS_ADMIN || IS_DOCENTE) ? `
      <div class="card card-tight mt-8">
        <strong>Archivos adjuntos a la tarea</strong>
        <div class="muted mt-6">Puede subir uno o varios archivos (enunciado, rúbrica, anexos, etc.).</div>
        <form id="formUploadInstructions" method="post" enctype="multipart/form-data" class="mt-8">
          <input type="file" name="files[]" multiple required accept=".pdf,.zip,.jpg,.jpeg">
              <div class="muted mt-6">Formatos permitidos: PDF, ZIP o JPG. Tamaño máximo: 500 KB por archivo.</div>
          <button class="btn" type="submit">Subir archivo(s)</button>
          <span class="muted" id="msgUploadRes"></span>
        </form>
      </div>
    ` : '';

    const studentGroupInfoBox = (IS_STUDENT && isGroupTask) ? `
      <div class="card card-tight mt-8">
        <strong>Grupo de trabajo</strong>
        <div id="myGroupBox" class="muted mt-6">Cargando grupo...</div>
      </div>
    ` : '';

    const studentBox = IS_STUDENT ? `
      <div class="card card-tight mt-8">
        <strong>Entrega ${isGroupTask ? '(grupal)' : ''}</strong>
        <div class="muted" id="studentAssignmentInfo">
          Cargando información de tu entrega...
        </div>

        <form id="formSubmissionUpload" method="post" enctype="multipart/form-data" class="mt-8">
          <input type="file" name="files[]" multiple ${expired ? 'disabled' : 'required'}>
          <button class="btn" type="submit" ${expired ? 'disabled' : ''}>Añadir entrega</button>
          <span class="muted" id="msgSubmission"></span>
        </form>

        ${expired ? `<div class="muted mt-6">La fecha límite ya venció. No puedes subir más archivos.</div>` : ''}
      </div>
    ` : '';

    const teacherSubmissionsBox = (IS_ADMIN || IS_DOCENTE) ? `
      <div class="card card-tight mt-8">
        <strong>Entregas de estudiantes${isGroupTask ? ' / grupos' : ''}</strong>
        <div class="muted mt-6" id="submissionsList">
          ${a?.id ? 'Cargando entregas...' : 'Primero guarde la tarea para habilitar entregas.'}
        </div>
      </div>
    ` : '';

    return publicInfo + teacherConfigBox + groupsBox + filesUploadBox + studentGroupInfoBox + studentBox + teacherSubmissionsBox;
  }

  async function loadResourcesForSection() {
    const box = document.getElementById('resBox');
    if (!box) return;

    box.innerHTML = '<span class="muted">Cargando archivos…</span>';
    try {
      const rj = await api('resources_list', { method: 'GET', params: { section_id: SECTION_ID } });
      const items = rj.data || [];

      if (!items.length) {
        box.innerHTML = '<span class="muted">No hay archivos adjuntos a esta tarea.</span>';
        return;
      }

      const rows = items.map(r => {
        const url = `${window.__BASE_URL__}/router.php?action=resources_download&id=${r.id}`;
        const delBtn = (IS_ADMIN || IS_DOCENTE)
          ? `<button class="btn danger" data-kind="resDelete" data-id="${r.id}">Eliminar</button>`
          : '';

        return `
          <div style="display:flex; justify-content:space-between; gap:10px; align-items:center; padding:6px 0; border-bottom:1px solid #eee;">
            <div>
              <strong><a href="${escapeHtml(url)}" target="_blank" rel="noopener">
                ${escapeHtml(r.original_name || ('Archivo #' + r.id))}
              </a></strong>
              <div class="muted">
                ${humanSize(r.size)} · Subido por ${escapeHtml(r.uploaded_by_nombre || '')}
              </div>
            </div>
            <div>${delBtn}</div>
          </div>
        `;
      }).join('');

      box.innerHTML = `<div class="card card-tight mt-8">
        <strong>Archivos adjuntos a la tarea</strong>
        <div class="mt-8">${rows}</div>
      </div>`;
    } catch (err) {
      box.innerHTML = `<span class="muted">${escapeHtml(err?.json?.message || 'Error cargando archivos')}</span>`;
    }
  }

  async function loadGroupsUI() {
    if (!currentAssignment || Number(currentAssignment?.is_group || 0) !== 1) return;

    // Docente/Admin: lista y administración
    const groupsBox = document.getElementById('groupsBox');
    if ((IS_ADMIN || IS_DOCENTE) && groupsBox) {
      groupsBox.textContent = 'Cargando grupos...';
      try {
        const j = await api('groups_list', { method: 'GET', params: { course_id: COURSE_ID } });
        const payload = j.data || {};
        const groups = payload.groups || [];
        const students = payload.students || [];

        if (!groups.length) {
          groupsBox.innerHTML = '<div class="muted">No hay grupos aún.</div>';
          return;
        }

        groupsBox.innerHTML = groups.map(g => {
          const members = Array.isArray(g.members) ? g.members : [];
          const selectedIds = new Set(members.map(m => String(m.student_id || m.id)));

          const options = students.map(s => `
            <label style="display:block; margin:4px 0;">
              <input type="checkbox" name="student_ids[]" value="${s.id}" ${selectedIds.has(String(s.id)) ? 'checked' : ''}>
              ${escapeHtml(s.nombre)} ${s.seccion ? `(${escapeHtml(s.seccion)})` : ''}
            </label>
          `).join('');

          const membersLabel = members.length
            ? members.map(m => escapeHtml(m.nombre || ('#' + (m.student_id || m.id)))).join(', ')
            : 'Sin miembros';

          return `
            <div class="card mt-8" style="padding:10px;">
              <div><strong>${escapeHtml(g.name)}</strong> <span class="muted">#${g.id}</span></div>
              <div class="muted mt-6">Miembros actuales: ${escapeHtml(membersLabel)}</div>

              <form class="formSetGroupMembers mt-8" data-group-id="${g.id}">
                <div style="max-height:180px; overflow:auto; border:1px solid rgba(255,255,255,.08); padding:8px; border-radius:8px;">
                  ${options || '<span class="muted">Sin estudiantes en este curso.</span>'}
                </div>
                <div class="mt-8" style="display:flex; gap:8px; align-items:center;">
                  <button class="btn" type="submit">Guardar miembros</button>
                  <span class="muted" data-kind="msgGroupMembers" data-group-id="${g.id}"></span>
                </div>
              </form>
            </div>
          `;
        }).join('');
      } catch (err) {
        groupsBox.textContent = err?.json?.message || 'Error cargando grupos';
      }
    }

    // Estudiante: ver mi grupo
    const myGroupBox = document.getElementById('myGroupBox');
    if (IS_STUDENT && myGroupBox) {
      myGroupBox.textContent = 'Cargando grupo...';
      try {
        const j = await api('groups_list', { method: 'GET', params: { course_id: COURSE_ID } });
        const my = j.data?.my_group || null;

        if (!my) {
          myGroupBox.innerHTML = '<div class="muted">No tienes grupo asignado aún.</div>';
          return;
        }

        const members = Array.isArray(my.members) ? my.members : [];
        myGroupBox.innerHTML = `
          <div><strong>${escapeHtml(my.name || 'Grupo')}</strong></div>
          <div class="muted mt-6">Miembros:</div>
          <div class="mt-6">
            ${members.length
              ? members.map(m => `<div>• ${escapeHtml(m.nombre || ('#' + (m.student_id || m.id)))}</div>`).join('')
              : '<span class="muted">Sin miembros.</span>'}
          </div>
        `;
      } catch (err) {
        myGroupBox.textContent = err?.json?.message || 'Error cargando grupo';
      }
    }
  }

  async function loadMySubmission() {
    const infoBox = document.getElementById('studentAssignmentInfo');
    if (!infoBox) return;

    const assignment = currentAssignment;
    const assignmentId = assignment?.id;

    if (!assignmentId) {
      infoBox.innerHTML = `<div class="muted">La tarea aún no ha sido configurada.</div>`;
      return;
    }

    try {
      const j = await api('submissions_getMine', { method: 'GET', params: { assignment_id: assignmentId } });
      const data = j.data;

      const maxScore = Number(assignment?.max_score ?? 100);
      const passingScore = Number(assignment?.passing_score ?? 70);
      const weightPercent = assignment?.weight_percent != null ? Number(assignment.weight_percent) : null;
      const expired = dueExpired(assignment);
      const isGroupTask = Number(assignment?.is_group || 0) === 1;

      if (!data || !data.submission) {
        infoBox.innerHTML = `
          <div class="muted">${isGroupTask ? 'Tu grupo aún no ha entregado.' : 'Aún no has entregado.'}</div>
          ${expired ? `<div class="muted mt-6">La fecha límite ya venció.</div>` : ''}
        `;
        return;
      }

      const files = Array.isArray(data.files) ? data.files : [];
      const grade = data.grade || null;

      let filesHtml = `<div class="muted">No hay archivos subidos aún.</div>`;
      if (files.length) {
        filesHtml = files.map(f => {
          const dl = `${window.__BASE_URL__}/router.php?action=submission_files_download&file_id=${encodeURIComponent(String(f.id))}`;
          const delBtn = expired ? '' : `
            <button class="btn danger btn-sm"
                    type="button"
                    data-kind="submissionFileDelete"
                    data-file-id="${f.id}">
              Eliminar
            </button>
          `;

          return `
            <div style="display:flex; justify-content:space-between; gap:10px; align-items:center; padding:6px 0; border-bottom:1px solid #eee;">
              <div>
                <a href="${escapeHtml(dl)}" target="_blank" rel="noopener">${escapeHtml(f.original_name || ('Archivo #' + f.id))}</a>
                <div class="muted">${humanSize(f.size)}</div>
              </div>
              <div>${delBtn}</div>
            </div>
          `;
        }).join('');
      }

      let gradeHtml = `<div class="muted mt-8">Sin calificación aún.</div>`;
      if (grade) {
        const storedScore = Number(grade.score || 0);
        const score100 = maxScore > 0 ? ((storedScore / maxScore) * 100) : 0;
        const pass = storedScore >= passingScore;
        const weighted = (weightPercent != null && maxScore > 0)
          ? ((storedScore / maxScore) * weightPercent).toFixed(2)
          : null;

        gradeHtml = `
          <div class="mt-8">
            <strong>Calificación:</strong> ${score100.toFixed(2)} / 100
            <span class="muted">(guardado: ${storedScore}/${maxScore})</span>
            — ${pass ? 'Aprobado' : 'Reprobado'}
          </div>
          ${weighted !== null ? `<div class="muted mt-6">Aporte al curso: ${weighted}% de ${weightPercent}%</div>` : ''}
          ${grade.feedback ? `<div class="mt-6"><strong>Retroalimentación:</strong> ${escapeHtml(grade.feedback)}</div>` : ''}
        `;
      }

      infoBox.innerHTML = `
        <div><strong>${isGroupTask ? 'Entrega de tu grupo' : 'Tu entrega'}</strong></div>
        <div class="muted">Estado: ${escapeHtml(data.submission.status || 'ENVIADA')}</div>
        <div class="muted">Fecha de entrega: ${escapeHtml(data.submission.submitted_at || '')}</div>
        <div class="mt-8">${filesHtml}</div>
        ${gradeHtml}
        ${expired ? `<div class="muted mt-8">La fecha límite ya venció. No puedes subir ni reemplazar archivos.</div>` : ''}
      `;
    } catch (err) {
      infoBox.textContent = err?.json?.message || 'Error cargando tu entrega';
    }
  }

  function submissionRowHtml(r, assignment) {
    const isGroupTask = Number(assignment?.is_group || 0) === 1;

    let who = '';
    if (r.group_id) {
      who = `Grupo: ${escapeHtml(r.group_name || ('#' + r.group_id))}`;
      const members = Array.isArray(r.group_members) ? r.group_members : [];
      if (members.length) {
        who += `<div class="muted mt-6">Miembros: ${members.map(m => escapeHtml(m.nombre || ('#' + (m.student_id || m.id)))).join(', ')}</div>`;
      }
    } else {
      who = escapeHtml(r.student_nombre || ('Estudiante #' + r.student_id));
    }

    const files = Array.isArray(r.files) ? r.files : [];
    const grade = r.grade || null;

    const maxScore = Number(assignment?.max_score ?? 100);
    const weightPercent = assignment?.weight_percent != null ? Number(assignment.weight_percent) : null;

    const score100ForInput = grade
      ? (maxScore > 0 ? ((Number(grade.score || 0) / maxScore) * 100) : 0)
      : '';

    const feedbackVal = grade ? (grade.feedback ?? '') : '';

    const filesHtml = files.length
      ? files.map(f => {
          const dl = `${window.__BASE_URL__}/router.php?action=submission_files_download&file_id=${encodeURIComponent(String(f.id))}`;
          return `
            <div>
              <a href="${escapeHtml(dl)}" target="_blank" rel="noopener">${escapeHtml(f.original_name || ('Archivo #' + f.id))}</a>
              <span class="muted">(${humanSize(f.size)})</span>
            </div>
          `;
        }).join('')
      : `<span class="muted">Sin archivos</span>`;

    let gradeInfo = `<div class="muted mt-6">Sin calificación aún.</div>`;
    if (grade) {
      const storedScore = Number(grade.score || 0);
      const weighted = (weightPercent != null && maxScore > 0)
        ? ((storedScore / maxScore) * weightPercent).toFixed(2)
        : null;

      gradeInfo = `
        <div class="muted mt-6">
          Guardado: ${storedScore}/${maxScore}
          ${weighted !== null ? ` · Aporte al curso: ${weighted}%` : ''}
        </div>
      `;
    }

    return `
      <div class="card" style="margin:8px 0; padding:10px;">
        <div style="display:flex; justify-content:space-between; gap:10px; align-items:flex-start; flex-wrap:wrap;">
          <div style="flex:1; min-width:260px;">
            <div><strong>${isGroupTask && r.group_id ? '' : ''}</strong>${who}</div>
            <div class="muted">Entregado: ${escapeHtml(r.submitted_at || '')}</div>
            <div class="mt-6"><strong>Archivos:</strong></div>
            <div class="mt-6">${filesHtml}</div>
            ${gradeInfo}
          </div>

          <form class="formGradeSet" data-submission="${r.id}" style="min-width:280px;">
            <label>Nota (0 a 100)
              <input name="score" type="number" min="0" max="100" step="0.01" value="${score100ForInput === '' ? '' : escapeHtml(Number(score100ForInput).toFixed(2))}" required />
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

  function renderSubmissionsPage() {
    const box = document.getElementById('submissionsList');
    if (!box) return;

    const rows = submissionsCache || [];
    if (!rows.length) {
      box.textContent = 'Aún no hay entregas.';
      return;
    }

    const totalPages = Math.max(1, Math.ceil(rows.length / SUBMISSIONS_PAGE_SIZE));
    submissionsPage = Math.min(Math.max(submissionsPage, 1), totalPages);

    const start = (submissionsPage - 1) * SUBMISSIONS_PAGE_SIZE;
    const pageRows = rows.slice(start, start + SUBMISSIONS_PAGE_SIZE);

    const pager = `
      <div class="card card-tight" style="margin-bottom:8px;">
        <div style="display:flex; justify-content:space-between; align-items:center; gap:10px; flex-wrap:wrap;">
          <div class="muted">
            Mostrando ${start + 1}-${Math.min(start + SUBMISSIONS_PAGE_SIZE, rows.length)} de ${rows.length} entregas
          </div>
          <div style="display:flex; gap:8px; align-items:center;">
            <button class="btn btn-sm" type="button" data-kind="subsPrevPage" ${submissionsPage <= 1 ? 'disabled' : ''}>Anterior</button>
            <span class="muted">Página ${submissionsPage} / ${totalPages}</span>
            <button class="btn btn-sm" type="button" data-kind="subsNextPage" ${submissionsPage >= totalPages ? 'disabled' : ''}>Siguiente</button>
          </div>
        </div>
      </div>
    `;

    box.innerHTML = pager + pageRows.map(r => submissionRowHtml(r, currentAssignment)).join('') + pager;
  }

  async function loadSubmissions() {
    const box = document.getElementById('submissionsList');
    if (!box) return;

    const assignmentId = currentAssignment?.id;
    if (!assignmentId) {
      box.textContent = 'Primero guarde la tarea para habilitar entregas.';
      return;
    }

    box.textContent = 'Cargando entregas...';
    try {
      const j = await api('submissions_listByAssignment', { method: 'GET', params: { assignment_id: assignmentId } });
      submissionsCache = j.data || [];
      submissionsPage = 1;
      renderSubmissionsPage();
    } catch (err) {
      box.textContent = err?.json?.message || 'Error cargando entregas';
    }
  }

  async function loadSectionAndAssignment() {
    const detail = document.getElementById('taskDetail');
    const titleEl = document.getElementById('taskTitle');
    const metaEl = document.getElementById('taskMeta');

    if (!COURSE_ID || !SECTION_ID) {
      detail.innerHTML = `<div class="muted">Faltan parámetros (course_id / section_id).</div>`;
      return;
    }

    try {
      const [courseJ, sectionsJ, assignmentJ] = await Promise.all([
        api('course_get', { method: 'GET', params: { id: COURSE_ID } }),
        api('sections_list', { method: 'GET', params: { course_id: COURSE_ID } }),
        api('assignments_getBySection', { method: 'GET', params: { section_id: SECTION_ID } })
      ]);

      currentCourse = courseJ.data || null;
      const sections = sectionsJ.data || [];
      currentSection = sections.find(x => Number(x.id) === Number(SECTION_ID)) || null;
      currentAssignment = assignmentJ.data || null;

      titleEl.textContent = currentAssignment?.title || currentSection?.titulo || 'Tarea';
      metaEl.innerHTML = `
        <div><strong>Curso:</strong> ${escapeHtml(currentCourse?.nombre || ('#' + COURSE_ID))}</div>
        <div><strong>Sección:</strong> ${escapeHtml(currentSection?.titulo || ('#' + SECTION_ID))}</div>
        <div><strong>Rol:</strong> ${escapeHtml(ROLE || '')}</div>
      `;

      detail.innerHTML = renderTaskPage(currentSection, currentAssignment);

      if (IS_STUDENT && currentAssignment?.id) {
        await loadMySubmission();
      }
      if ((IS_ADMIN || IS_DOCENTE) && currentAssignment?.id) {
        await loadSubmissions();
      }

      if (Number(currentAssignment?.is_group || 0) === 1) {
        await loadGroupsUI();
      }

      await loadResourcesForSection();
    } catch (err) {
      detail.innerHTML = `<div class="muted">${escapeHtml(err?.json?.message || 'Error cargando tarea')}</div>`;
    }
  }

  document.addEventListener('click', async (e) => {
    const el = e.target.closest('[data-kind]');
    if (!el) return;

    const kind = el.getAttribute('data-kind');

    if (kind === 'subsPrevPage') {
      if (submissionsPage > 1) {
        submissionsPage--;
        renderSubmissionsPage();
      }
      return;
    }

    if (kind === 'subsNextPage') {
      const totalPages = Math.max(1, Math.ceil((submissionsCache || []).length / SUBMISSIONS_PAGE_SIZE));
      if (submissionsPage < totalPages) {
        submissionsPage++;
        renderSubmissionsPage();
      }
      return;
    }

    if (kind === 'resDelete') {
      const id = el.getAttribute('data-id');
      if (!confirm('¿Eliminar este archivo?')) return;

      try {
        const fd = new FormData();
        fd.append('id', String(id));
        await api('resources_delete', { data: fd, isForm: true });
        await loadResourcesForSection();
      } catch (err) {
        alert(err?.json?.message || 'Error eliminando archivo');
      }
      return;
    }

    if (kind === 'submissionFileDelete') {
      const fileId = el.getAttribute('data-file-id');
      if (!confirm('¿Eliminar este archivo de la entrega?')) return;

      try {
        const fd = new FormData();
        fd.append('file_id', String(fileId));
        await api('submission_files_delete', { data: fd, isForm: true });

        const aj = await api('assignments_getBySection', { method: 'GET', params: { section_id: SECTION_ID } });
        currentAssignment = aj.data || currentAssignment;
        await loadMySubmission();
      } catch (err) {
        alert(err?.json?.message || 'Error eliminando archivo de entrega');
      }
      return;
    }
  });

  document.addEventListener('submit', async (e) => {
    // Guardar tarea
    const formAssignment = e.target.closest('#formAssignment');
    if (formAssignment) {
      e.preventDefault();
      const msg = document.getElementById('msgAssignment');
      if (msg) msg.textContent = '';

      try {
        const fd = new FormData(formAssignment);
        fd.append('section_id', String(SECTION_ID));

        const dueAtRaw = fd.get('due_at');
        if (typeof dueAtRaw === 'string') {
          fd.set('due_at', fromLocalInputValue(dueAtRaw));
        }

        await api('assignments_upsert', { data: fd, isForm: true });

        if (msg) msg.textContent = 'Tarea guardada.';
        await loadSectionAndAssignment();
      } catch (err) {
        if (msg) msg.textContent = err?.json?.message || 'Error guardando tarea';
      }
      return;
    }

    // Crear grupo
    const formCreateGroup = e.target.closest('#formCreateGroup');
    if (formCreateGroup) {
      e.preventDefault();
      const msg = document.getElementById('msgGroup');
      if (msg) msg.textContent = '';

      try {
        const fd = new FormData(formCreateGroup);
        fd.append('course_id', String(COURSE_ID));

        await api('groups_create', { data: fd, isForm: true });
        if (msg) msg.textContent = 'Grupo creado.';
        formCreateGroup.reset();
        await loadGroupsUI();
      } catch (err) {
        if (msg) msg.textContent = err?.json?.message || 'Error creando grupo';
      }
      return;
    }

    // Guardar miembros de grupo
    const formSetGroupMembers = e.target.closest('.formSetGroupMembers');
    if (formSetGroupMembers) {
      e.preventDefault();
      const groupId = formSetGroupMembers.getAttribute('data-group-id');
      const msg = document.querySelector(`[data-kind="msgGroupMembers"][data-group-id="${groupId}"]`);
      if (msg) msg.textContent = '';

      try {
        const fd = new FormData(formSetGroupMembers);
        fd.append('group_id', String(groupId));

        await api('groups_setMembers', { data: fd, isForm: true });
        if (msg) msg.textContent = 'Miembros guardados.';
        await loadGroupsUI();
      } catch (err) {
        if (msg) msg.textContent = err?.json?.message || 'Error guardando miembros';
      }
      return;
    }

    // Subir instrucciones
    const formUploadInstructions = e.target.closest('#formUploadInstructions');
    if (formUploadInstructions) {
      e.preventDefault();
      const msg = document.getElementById('msgUploadRes');
      if (msg) msg.textContent = 'Subiendo archivo...';

      try {
        const fd = new FormData(formUploadInstructions);
        fd.append('section_id', String(SECTION_ID));

        await api('resources_upload', { data: fd, isForm: true });

        if (msg) msg.textContent = 'Archivo subido.';
        formUploadInstructions.reset();
        await loadResourcesForSection();
      } catch (err) {
        if (msg) msg.textContent = err?.json?.message || 'Error subiendo archivo';
      }
      return;
    }

    // Subir entrega (individual o grupal)
    const formSubmissionUpload = e.target.closest('#formSubmissionUpload');
    if (formSubmissionUpload) {
      e.preventDefault();
      const msg = document.getElementById('msgSubmission');

      try {
        if (msg) msg.textContent = 'Subiendo entrega...';

        const aj = await api('assignments_getBySection', {
          method: 'GET',
          params: { section_id: SECTION_ID }
        });
        currentAssignment = aj.data || currentAssignment;

        if (!currentAssignment?.id) {
          throw new Error('La tarea aún no ha sido configurada.');
        }

        const fd = new FormData(formSubmissionUpload);
        fd.append('assignment_id', String(currentAssignment.id));

        await api('submissions_upload', { data: fd, isForm: true });

        if (msg) msg.textContent = 'Entrega subida.';
        await loadMySubmission();
        formSubmissionUpload.reset();
      } catch (err) {
        if (msg) msg.textContent = err?.json?.message || err?.message || 'Error subiendo entrega';
      }
      return;
    }

    // Guardar nota (docente/admin)
    const formGrade = e.target.closest('.formGradeSet');
    if (formGrade) {
      e.preventDefault();

      const submissionId = formGrade.getAttribute('data-submission');
      const msg = document.querySelector(`[data-kind="msgGrade"][data-submission="${submissionId}"]`);
      if (msg) msg.textContent = '';

      try {
        const fd = new FormData(formGrade);
        fd.append('submission_id', String(submissionId));

        await api('grades_set', { data: fd, isForm: true });

        if (msg) msg.textContent = 'Nota guardada.';

        // refrescar lista (para ver info recalculada)
        await loadSubmissions();
      } catch (err) {
        if (msg) msg.textContent = err?.json?.message || 'Error guardando nota';
      }
      return;
    }
  });

  loadSectionAndAssignment();
</script>

<?php include __DIR__ . '/components/footer.php'; ?>