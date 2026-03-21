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
      <h2 id="forumTitlePage">Foro de discusión</h2>
      <div id="forumMeta" class="muted">Cargando foro...</div>
    </div>
    <div style="display:flex; gap:8px;">
      <a class="btn" href="<?= $base_url ?>/virtualcampus.php?course_id=<?= urlencode((string)$course_id) ?>">Volver al Campus</a>
      <a class="btn" href="<?= $base_url ?>/elearning.php">E-Learning</a>
    </div>
  </div>
</section>

<section class="card">
  <h3>Detalle del foro</h3>
  <div id="forumDetail" class="muted">Cargando...</div>
</section>

<section class="card">
  <h3>Comentarios</h3>
  <div id="forumComments" class="muted">Cargando...</div>
</section>

<script>
const COURSE_ID = <?php echo json_encode($course_id); ?>;
const SECTION_ID = <?php echo json_encode($section_id); ?>;
const ROLE = <?php echo json_encode($rol); ?>;
const IS_ADMIN = ROLE === 'ADMIN';
const IS_DOCENTE = ROLE === 'DOCENTE';
const IS_STUDENT = ROLE === 'ESTUDIANTE';
let currentSection = null;
let currentForum = null;

function escapeHtml(v) {
  return String(v ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

function renderForumDetail(section, forum) {
  const s = section || {};
  const f = forum || null;
  if ((IS_ADMIN || IS_DOCENTE)) {
    return `
      <form id="forumUpsertForm">
        <label>Título
          <input type="text" name="title" required value="${escapeHtml(f?.title || s?.titulo || '')}">
        </label>
        <label>Descripción
          <textarea name="description" rows="5">${escapeHtml(f?.description || s?.descripcion || '')}</textarea>
        </label>
        <div class="row gap-8 mt-10">
          <button class="btn" type="submit">Guardar foro</button>
          ${f?.id ? `<button class="btn danger" type="button" id="btnDeleteForum">Eliminar foro</button>` : ''}
        </div>
        <div class="muted mt-8" id="forumMsg"></div>
      </form>
    `;
  }
  return `
    <div class="card card-tight mt-8">
      <strong>${escapeHtml(f?.title || s?.titulo || 'Foro')}</strong>
      <div class="mt-6">${escapeHtml(f?.description || s?.descripcion || 'Sin descripción.')}</div>
    </div>
  `;
}

function renderComments(comments) {
  const form = IS_STUDENT ? `
    <form id="commentForm" class="mt-8">
      <label>Nuevo comentario
        <textarea name="comment_body" rows="4" required></textarea>
      </label>
      <button class="btn mt-8" type="submit">Publicar comentario</button>
      <div class="muted mt-8" id="commentMsg"></div>
    </form>
  ` : '';

  const rows = (comments || []).length ? comments.map(c => `
    <div class="card card-tight mt-8">
      <div style="display:flex; justify-content:space-between; gap:10px; align-items:flex-start; flex-wrap:wrap;">
        <div>
          <strong>${escapeHtml(c.author_nombre || c.author_username || ('Usuario #' + c.user_id))}</strong>
          <div class="muted">${escapeHtml(c.author_rol || '')} · ${escapeHtml(c.created_at || '')}</div>
        </div>
        <div class="row gap-8">
          ${IS_STUDENT ? `<button class="btn" type="button" data-kind="reportComment" data-id="${c.id}">Reportar</button>` : ''}
          ${(IS_ADMIN || IS_DOCENTE) ? `<button class="btn danger" type="button" data-kind="deleteComment" data-id="${c.id}">Eliminar</button>` : ''}
        </div>
      </div>
      <div class="mt-8">${escapeHtml(c.comment_body || '')}</div>
    </div>
  `).join('') : `<div class="muted">Aún no hay comentarios.</div>`;

  return `${form}<div class="mt-10">${rows}</div>`;
}

async function loadForumPage() {
  const course = await api('course_get', { method: 'GET', params: { id: COURSE_ID } });
  const sec = await api('sections_list', { method: 'GET', params: { course_id: COURSE_ID } });
  currentSection = (sec.data || []).find(x => Number(x.id) === Number(SECTION_ID)) || null;
  const forumRes = await api('forums_getBySection', { method: 'GET', params: { section_id: SECTION_ID } });
  currentForum = forumRes.data || null;

  document.getElementById('forumTitlePage').textContent = currentForum?.title || currentSection?.titulo || 'Foro de discusión';
  document.getElementById('forumMeta').textContent = `Curso: ${(course.data?.nombre || 'Curso')} · Sección: ${(currentSection?.titulo || ('#' + SECTION_ID))}`;
  document.getElementById('forumDetail').innerHTML = renderForumDetail(currentSection, currentForum);

  await loadComments();
}

async function loadComments() {
  const box = document.getElementById('forumComments');
  if (!currentForum?.id) {
    box.innerHTML = `<div class="muted">${(IS_ADMIN || IS_DOCENTE) ? 'Guarda el foro para habilitar comentarios.' : 'Este foro aún no ha sido configurado.'}</div>`;
    return;
  }
  const j = await api('forum_comments_list', { method: 'GET', params: { forum_id: currentForum.id } });
  box.innerHTML = renderComments(j.data || []);
}

document.addEventListener('submit', async (e) => {
  if (e.target && e.target.id === 'forumUpsertForm') {
    e.preventDefault();
    const msg = document.getElementById('forumMsg');
    try {
      const fd = new FormData(e.target);
      fd.append('section_id', String(SECTION_ID));
      await api('forums_upsert', { data: fd, isForm: true });
      msg.textContent = 'Foro guardado correctamente.';
      await loadForumPage();
    } catch (err) {
      msg.textContent = err?.json?.message || 'Error guardando el foro';
    }
  }

  if (e.target && e.target.id === 'commentForm') {
    e.preventDefault();
    const msg = document.getElementById('commentMsg');
    try {
      const fd = new FormData(e.target);
      fd.append('forum_id', String(currentForum.id));
      await api('forum_comments_create', { data: fd, isForm: true });
      e.target.reset();
      msg.textContent = 'Comentario publicado.';
      await loadComments();
    } catch (err) {
      msg.textContent = err?.json?.message || 'Error publicando comentario';
    }
  }
});

document.addEventListener('click', async (e) => {
  const deleteForumBtn = e.target.closest('#btnDeleteForum');
  if (deleteForumBtn && currentForum?.id) {
    if (!confirm('¿Seguro que deseas eliminar este foro?')) return;
    try {
      await api('forums_delete', { data: { id: currentForum.id } });
      window.location.href = `${window.__BASE_URL__}/virtualcampus.php?course_id=${encodeURIComponent(String(COURSE_ID))}`;
    } catch (err) {
      alert(err?.json?.message || 'Error eliminando foro');
    }
  }

  const delComment = e.target.closest('[data-kind="deleteComment"]');
  if (delComment) {
    if (!confirm('¿Eliminar este comentario?')) return;
    try {
      await api('forum_comments_delete', { data: { id: delComment.getAttribute('data-id') } });
      await loadComments();
    } catch (err) {
      alert(err?.json?.message || 'Error eliminando comentario');
    }
  }

  const reportComment = e.target.closest('[data-kind="reportComment"]');
  if (reportComment) {
    const reason = prompt('Motivo del reporte (opcional):') || '';
    try {
      await api('forum_comments_report', { data: { id: reportComment.getAttribute('data-id'), comment_id: reportComment.getAttribute('data-id'), reason } });
      alert('Comentario reportado. Se notificó al docente encargado.');
    } catch (err) {
      alert(err?.json?.message || 'Error reportando comentario');
    }
  }
});

loadForumPage().catch(err => {
  document.getElementById('forumDetail').textContent = err?.json?.message || 'Error cargando el foro';
  document.getElementById('forumComments').textContent = '';
});
</script>
