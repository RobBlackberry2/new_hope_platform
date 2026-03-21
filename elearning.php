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
  <p class="muted">Cursos, acceso al Campus Virtual y módulo de gamificación.</p>
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
    <div class="row-between-center gap-8">
      <div>
        <h3 style="margin-bottom: 6px;">Gamificación</h3>
        <div class="muted">Retos, misiones, medallas y trofeos.</div>
      </div>
      <?php if ($rol === 'ADMIN' || $rol === 'DOCENTE'): ?>
        <button class="btn" id="btnToggleChallengeForm" type="button">Nuevo reto o misión</button>
      <?php endif; ?>
    </div>

    <?php if ($rol === 'ADMIN' || $rol === 'DOCENTE'): ?>
      <form id="formChallenge" class="grid2 form-shell" style="display:none; margin-top:16px;">
        <input type="hidden" name="id" id="challenge_id" />

        <label>Tipo
          <select name="tipo" id="challenge_tipo">
            <option value="RETO">Reto</option>
            <option value="MISION">Misión</option>
          </select>
        </label>
        <label class="span-all">Título
          <input name="titulo" id="challenge_titulo" required />
        </label>
        <label class="span-all">Instrucciones
          <textarea name="instrucciones" id="challenge_instrucciones" rows="3"></textarea>
        </label>

        <label>Recompensa
          <select name="recompensa_tipo" id="challenge_recompensa_tipo">
            <option value="MEDALLA">Medalla</option>
            <option value="TROFEO">Trofeo</option>
          </select>
        </label>

        <label>Nombre de la recompensa
          <input name="recompensa_nombre" id="challenge_recompensa_nombre" required />
        </label>

        <label>Fecha de inicio
          <input type="datetime-local" name="fecha_inicio" id="challenge_fecha_inicio" />
        </label>

        <label>Fecha de fin
          <input type="datetime-local" name="fecha_fin" id="challenge_fecha_fin" />
        </label>

        <div class="span-all row gap-8 align-center">
          <button class="btn" type="submit" id="btnSubmitChallenge">Guardar</button>
          <button class="btn btn-ghost" type="button" id="btnResetChallengeForm">Cancelar</button>
          <div id="msgChallenge" class="muted"></div>
        </div>
      </form>
    <?php endif; ?>

    <div id="gamificationPanel" style="margin-top:16px;">
      <div class="muted">Cargando gamificación...</div>
    </div>
  </div>
</section>

<script>
  const IS_ADMIN = <?php echo json_encode($rol === 'ADMIN'); ?>;
  const ROLE = <?php echo json_encode($rol); ?>;
  const CAN_MANAGE_GAMIFICATION = <?php echo json_encode(in_array($rol, ['ADMIN', 'DOCENTE'], true)); ?>;
  const GAMIFICATION_PAGE_SIZE = 5;
  let currentChallengePage = 1;
  let currentParticipantsChallengeId = null;
  let currentParticipantsPages = {};

  function escapeHtml(v) {
    return String(v ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function formatDate(v) {
    if (!v) return '—';
    const d = new Date(v.replace(' ', 'T'));
    if (Number.isNaN(d.getTime())) return escapeHtml(v);
    return d.toLocaleString('es-CR');
  }

  function toDateTimeLocal(value) {
    if (!value) return '';
    return String(value).replace(' ', 'T').slice(0, 16);
  }

  function badge(text) {
    return `<span class="badge">${escapeHtml(text)}</span>`;
  }

  function courseCard(c) {
    const delBtn = IS_ADMIN
      ? `<button class="btn" data-kind="deleteCourse" data-id="${c.id}">Eliminar</button>`
      : '';

    return `<div class="card card-row">
    <div class="gm-participant-head">
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

  function studentRewardsHtml(rewards) {
    const safeRewards = Array.isArray(rewards) ? rewards : [];
    const medals = safeRewards.filter(r => r.reward_type === 'MEDALLA');
    const trophies = safeRewards.filter(r => r.reward_type === 'TROFEO');

    const rewardItem = (r) => {
      const rewardIcon = r.reward_type === 'MEDALLA' ? '🏅' : '🏆';
      return `<div class="gm-reward-item">
      <div><strong>${rewardIcon} ${escapeHtml(r.reward_name || (r.reward_type === 'MEDALLA' ? 'Medalla' : 'Trofeo'))}</strong></div>
      <div class="muted">Reto/Misión: ${escapeHtml(r.reto_titulo || 'Reto o misión eliminado')}</div>
      <div class="muted">Asignado por: ${escapeHtml(r.asignado_por_nombre || '')}</div>
      <div class="muted">Fecha: ${formatDate(r.assigned_at)}</div>
      ${r.feedback ? `<div class="muted">Retroalimentación: ${escapeHtml(r.feedback)}</div>` : ''}
    </div>`;
    };

    return `
      <div class="gm-student-rewards">
        <div class="gm-mini-card">
          <div class="gm-mini-head">
            <div class="gm-mini-title">🏅 Mis Medallas (${medals.length})</div>
            <button class="btn btn-sm" type="button" data-kind="toggleRewardPanel" data-target="studentMedalsPanel">Ver medallas</button>
          </div>
          <div id="studentMedalsPanel" class="gm-drop-panel hidden">
            ${medals.length ? medals.map(rewardItem).join('') : '<div class="muted">No tienes medallas todavía.</div>'}
          </div>
        </div>

        <div class="gm-mini-card">
          <div class="gm-mini-head">
            <div class="gm-mini-title">🏆 Mis Trofeos (${trophies.length})</div>
            <button class="btn btn-sm" type="button" data-kind="toggleRewardPanel" data-target="studentTrophiesPanel">Ver trofeos</button>
          </div>
          <div id="studentTrophiesPanel" class="gm-drop-panel hidden">
            ${trophies.length ? trophies.map(rewardItem).join('') : '<div class="muted">No tienes trofeos todavía.</div>'}
          </div>
        </div>
      </div>
    `;
  }

  function studentChallengeCard(ch) {
    const status = ch.mi_estado_inscripcion || 'NO_INSCRITO';
    const canEnroll = status !== 'INSCRITO';
    const actionBtn = canEnroll
      ? `<button class="btn" data-kind="enrollChallenge" data-id="${ch.id}">Inscribirme</button>`
      : `<button class="btn btn-ghost" data-kind="unenrollChallenge" data-id="${ch.id}">Desinscribirme</button>`;

    return `<div class="card card-row">
      <div class="row-between-start gap-12">
        <div style="flex:1;">
          <div><strong>${escapeHtml(ch.titulo)}</strong> ${badge(ch.tipo)}</div>
          <div class="muted">Creado por: ${escapeHtml(ch.creador_nombre || '')}</div>
          ${ch.instrucciones ? `<div class="muted">Instrucciones: ${escapeHtml(ch.instrucciones)}</div>` : ''}
          <div class="gm-meta-strip">
            <span class="gm-meta-chip">Recompensa: ${escapeHtml(ch.recompensa_tipo)} - ${escapeHtml(ch.recompensa_nombre)}</span>
            <span class="gm-meta-chip">Inicio: ${formatDate(ch.fecha_inicio)}</span>
            <span class="gm-meta-chip">Fin: ${formatDate(ch.fecha_fin)}</span>
          </div>
          ${Number(ch.ya_recompensado) ? `<div class="muted">Ya tienes una recompensa registrada en este reto o misión.</div>` : ''}
        </div>
        <div class="gm-actions">${actionBtn}</div>
      </div>
    </div>`;
  }

  function drawPager(containerId, currentPage, totalItems, perPage, kind, extraAttrs = '') {
    const container = document.getElementById(containerId);
    if (!container) return;
    container.innerHTML = '';

    const totalPages = Math.max(1, Math.ceil(totalItems / perPage));
    if (totalPages <= 1) return;

    const wrap = document.createElement('div');
    wrap.style.display = 'flex';
    wrap.style.flexWrap = 'wrap';
    wrap.style.gap = '6px';
    wrap.style.alignItems = 'center';
    wrap.style.justifyContent = 'center';
    wrap.style.marginTop = '12px';

    const info = document.createElement('span');
    info.textContent = `Pág. ${currentPage} de ${totalPages}`;
    info.style.padding = '6px 10px';
    info.style.borderRadius = '8px';
    info.style.background = 'rgba(255,255,255,0.08)';
    info.style.border = '1px solid rgba(255,255,255,0.12)';
    info.style.color = '#e5eefc';
    info.style.fontSize = '13px';
    info.style.lineHeight = '1';
    wrap.appendChild(info);

    function addBtn(label, page, opts = {}) {
      const b = document.createElement('button');
      b.type = 'button';
      b.className = 'btn btn-sm';
      b.textContent = label;
      b.style.minWidth = '32px';
      b.style.height = '30px';
      b.style.padding = '0 10px';
      b.style.borderRadius = '9px';
      b.style.border = '1px solid rgba(79, 209, 255, 0.18)';
      b.style.background = 'rgba(14, 36, 66, 0.9)';
      b.style.color = '#dbeafe';
      b.style.boxShadow = 'inset 0 0 0 1px rgba(255,255,255,0.02)';
      b.style.cursor = 'pointer';
      b.setAttribute('data-kind', kind);
      if (typeof page === 'number') b.setAttribute('data-page', String(page));
      if (extraAttrs) {
        for (const part of extraAttrs.split('||')) {
          if (!part) continue;
          const idx = part.indexOf('=');
          if (idx > -1) b.setAttribute(part.slice(0, idx), part.slice(idx + 1));
        }
      }

      if (opts.disabled) {
        b.disabled = true;
        b.style.opacity = '0.45';
        b.style.cursor = 'not-allowed';
      }

      if (opts.active) {
        b.style.background = 'linear-gradient(180deg, #4ade80, #22c55e)';
        b.style.border = '1px solid rgba(34, 197, 94, 0.65)';
        b.style.color = '#052e16';
        b.style.fontWeight = '700';
        b.style.boxShadow = '0 0 0 2px rgba(74,222,128,0.12)';
      }

      wrap.appendChild(b);
    }

    function addEllipsis() {
      const el = document.createElement('span');
      el.textContent = '...';
      el.style.padding = '0 4px';
      el.style.color = 'rgba(219,234,254,0.75)';
      el.style.fontWeight = '600';
      wrap.appendChild(el);
    }

    const pages = new Set();
    for (let i = 1; i <= Math.min(3, totalPages); i++) pages.add(i);
    for (let i = Math.max(1, currentPage - 2); i <= Math.min(totalPages, currentPage + 2); i++) pages.add(i);
    for (let i = Math.max(1, totalPages - 2); i <= totalPages; i++) pages.add(i);

    const sorted = Array.from(pages).sort((a, b) => a - b);

    addBtn('«', 1, { disabled: currentPage === 1 });
    addBtn('<', currentPage - 1, { disabled: currentPage === 1 });

    let prev = 0;
    for (const p of sorted) {
      if (prev && p - prev > 1) addEllipsis();
      addBtn(String(p), p, { active: p === currentPage });
      prev = p;
    }

    addBtn('>', currentPage + 1, { disabled: currentPage === totalPages });
    addBtn('»', totalPages, { disabled: currentPage === totalPages });

    container.appendChild(wrap);
  }

  function managerChallengeCard(ch) {
    return `<div class="card card-row gm-card">
      <div class="gm-card-top">
        <div class="gm-card-title-wrap">
          <div class="gm-card-title">${escapeHtml(ch.titulo)}</div>
          <div>${badge(ch.tipo)}</div>
        </div>
        <div class="gm-card-actions">
          <button class="btn" data-kind="editChallenge" data-id="${ch.id}">Editar</button>
          <button class="btn btn-ghost" data-kind="viewParticipants" data-id="${ch.id}">Participantes</button>
          <button class="btn danger" data-kind="deleteChallenge" data-id="${ch.id}">Eliminar</button>
        </div>
      </div>
      <div class="gm-card-content">
        ${ch.creador_nombre ? `<div class="muted"><strong>Creador:</strong> ${escapeHtml(ch.creador_nombre)}</div>` : ''}
        ${ch.instrucciones ? `<div class="muted gm-instructions"><strong>Instrucciones:</strong> ${escapeHtml(ch.instrucciones)}</div>` : ''}
        <div class="muted"><strong>Recompensa objetivo:</strong> ${escapeHtml(ch.recompensa_tipo)} - ${escapeHtml(ch.recompensa_nombre)}</div>
        <div class="muted"><strong>Inicio:</strong> ${formatDate(ch.fecha_inicio)} | <strong>Fin:</strong> ${formatDate(ch.fecha_fin)}</div>
        <div class="muted"><strong>Inscritos:</strong> ${escapeHtml(ch.inscritos || 0)} | <strong>Recompensas asignadas:</strong> ${escapeHtml(ch.recompensas_asignadas || 0)}</div>
      </div>
      <div id="participantsWrap_${ch.id}" style="display:none; margin-top:14px;"></div>
    </div>`;
  }

  function participantRow(p, challengeId, challenge) {
    return `<div class="card card-row">
      <div class="row-between-start gap-12">
        <div style="flex:1;">
          <div><strong>${escapeHtml(p.estudiante_nombre || '')}</strong></div>
          <div class="muted">Grado: ${escapeHtml(p.grado || '')} | Sección: ${escapeHtml(p.seccion || '')}</div>
          <div class="muted">Correo: ${escapeHtml(p.correo || 'Sin correo')}</div>
          <div class="muted">Se inscribió: ${formatDate(p.enrolled_at)}</div>
          <div class="muted">Recompensa a asignar: ${escapeHtml(challenge?.recompensa_tipo || '')} - ${escapeHtml(challenge?.recompensa_nombre || '')}</div>
        </div>
        <form class="participantRewardForm" data-challenge-id="${challengeId}" data-student-id="${p.student_id}" style="min-width:280px;">
          <textarea name="feedback" rows="2" placeholder="Retroalimentación al estudiante"></textarea>
          <div class="row gap-8 align-center" style="margin-top:8px;">
            <button class="btn" type="submit">Asignar</button>
            <span class="muted participantMsg"></span>
          </div>
        </form>
      </div>
    </div>`;
  }

  function managerPanelHtml(challengesData) {
    const items = challengesData?.items || [];
    const pagination = challengesData?.pagination || null;
    const content = items.length
      ? items.map(managerChallengeCard).join('')
      : '<div class="muted">Todavía no has creado retos o misiones.</div>';
    return `${content}<div id="gamificationChallengePager"></div>`;
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

  function resetChallengeForm() {
    const form = document.getElementById('formChallenge');
    if (!form) return;
    form.reset();
    document.getElementById('challenge_id').value = '';
    document.getElementById('btnSubmitChallenge').textContent = 'Guardar';
    document.getElementById('msgChallenge').textContent = '';
  }

  async function loadGamification(page = currentChallengePage) {
    currentChallengePage = page;
    const panel = document.getElementById('gamificationPanel');
    panel.innerHTML = '<div class="muted">Cargando gamificación...</div>';

    try {
      const params = { page: String(page), per_page: String(GAMIFICATION_PAGE_SIZE) };
      const j = await api('gamification_dashboard', { method: 'GET', params });
      const data = j.data || {};
      const challengesMeta = data.challenges?.pagination || null;
      if ((challengesMeta?.total_pages || 0) > 0 && page > challengesMeta.total_pages) {
        await loadGamification(challengesMeta.total_pages);
        return;
      }

      if (ROLE === 'ESTUDIANTE') {
        const rewardsHtml = studentRewardsHtml(data.rewards || []);
        const challenges = data.challenges || { items: [], pagination: null };
        panel.innerHTML = `
          <div>
            <h4>Mi espacio de gamificación</h4>
            ${rewardsHtml}
            <div style="margin-top:18px;">
              <h4>Retos y misiones publicados</h4>
              <div id="studentChallengesList">${(challenges.items || []).length
            ? challenges.items.map(studentChallengeCard).join('')
            : '<div class="muted">No hay retos o misiones publicados en este momento.</div>'}</div>
              <div id="gamificationChallengePager"></div>
            </div>
          </div>
        `;
        drawPager('gamificationChallengePager', challenges.pagination?.page || 1, challenges.pagination?.total || 0, challenges.pagination?.per_page || GAMIFICATION_PAGE_SIZE, 'changeChallengePage');
        return;
      }

      panel.innerHTML = `
        <div>
          <h4>Retos y misiones creados</h4>
          ${managerPanelHtml(data.challenges || {})}
        </div>
      `;
      const challengePagination = data.challenges?.pagination || null;
      drawPager('gamificationChallengePager', challengePagination?.page || 1, challengePagination?.total || 0, challengePagination?.per_page || GAMIFICATION_PAGE_SIZE, 'changeChallengePage');
    } catch (err) {
      panel.innerHTML = `<div class="muted">${escapeHtml(err?.json?.message || 'Error cargando gamificación')}</div>`;
    }
  }

  async function loadChallengeIntoForm(id) {
    const j = await api('gamification_get', { method: 'GET', params: { id: String(id) } });
    const ch = j.data || {};
    document.getElementById('challenge_id').value = ch.id || '';
    document.getElementById('challenge_tipo').value = ch.tipo || 'RETO';
    document.getElementById('challenge_titulo').value = ch.titulo || '';
    document.getElementById('challenge_instrucciones').value = ch.instrucciones || '';
    document.getElementById('challenge_recompensa_tipo').value = ch.recompensa_tipo || 'MEDALLA';
    document.getElementById('challenge_recompensa_nombre').value = ch.recompensa_nombre || '';
    document.getElementById('challenge_fecha_inicio').value = toDateTimeLocal(ch.fecha_inicio);
    document.getElementById('challenge_fecha_fin').value = toDateTimeLocal(ch.fecha_fin);
    document.getElementById('btnSubmitChallenge').textContent = 'Actualizar';
    document.getElementById('formChallenge').style.display = 'grid';
  }

  async function loadParticipants(challengeId, page = 1) {
    const wrap = document.getElementById(`participantsWrap_${challengeId}`);
    if (!wrap) return;

    wrap.style.display = 'block';
    wrap.innerHTML = '<div class="muted">Cargando participantes...</div>';
    currentParticipantsChallengeId = challengeId;
    currentParticipantsPages[challengeId] = page;

    try {
      const j = await api('gamification_participants', { method: 'GET', params: { challenge_id: String(challengeId), page: String(page), per_page: String(GAMIFICATION_PAGE_SIZE) } });
      const data = j.data || {};
      const participantsData = data.participants || { items: [], pagination: null };
      const participantsPagination = participantsData.pagination || null;
      if ((participantsPagination?.total_pages || 0) > 0 && page > participantsPagination.total_pages) {
        await loadParticipants(challengeId, participantsPagination.total_pages);
        return;
      }
      const participants = participantsData.items || [];
      wrap.innerHTML = `
        <div class="card" style="margin-top:8px;">
          <h4>${escapeHtml(data.challenge?.titulo || 'Participantes')}</h4>
          ${participants.length
          ? participants.map(p => participantRow(p, challengeId, data.challenge)).join('')
          : '<div class="muted">No hay estudiantes pendientes de recompensa en este reto o misión.</div>'}
          <div id="participantsPager_${challengeId}"></div>
        </div>
      `;
      const pagination = participantsData.pagination || null;
      drawPager(`participantsPager_${challengeId}`, pagination?.page || 1, pagination?.total || 0, pagination?.per_page || GAMIFICATION_PAGE_SIZE, 'changeParticipantsPage', `data-id=${challengeId}`);
    } catch (err) {
      wrap.innerHTML = `<div class="muted">${escapeHtml(err?.json?.message || 'Error cargando participantes')}</div>`;
    }
  }

  async function toggleParticipants(challengeId) {
    const wrap = document.getElementById(`participantsWrap_${challengeId}`);
    if (!wrap) return;

    if (currentParticipantsChallengeId === challengeId && wrap.style.display !== 'none') {
      wrap.style.display = 'none';
      currentParticipantsChallengeId = null;
      return;
    }

    await loadParticipants(challengeId, currentParticipantsPages[challengeId] || 1);
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

  document.getElementById('gamificationPanel').addEventListener('click', async (e) => {
    const el = e.target.closest('[data-kind]');
    if (!el) return;
    const kind = el.getAttribute('data-kind');
    const id = el.getAttribute('data-id');

    try {
      if (kind === 'enrollChallenge') {
        const fd = new FormData();
        fd.append('challenge_id', String(id));
        await api('gamification_enroll', { data: fd, isForm: true });
        await loadGamification(currentChallengePage);
        return;
      }

      if (kind === 'unenrollChallenge') {
        const fd = new FormData();
        fd.append('challenge_id', String(id));
        await api('gamification_unenroll', { data: fd, isForm: true });
        await loadGamification(currentChallengePage);
        return;
      }

      if (kind === 'changeChallengePage') {
        const page = Number(el.getAttribute('data-page') || '1');
        if (page >= 1) await loadGamification(page);
        return;
      }

      if (kind === 'toggleRewardPanel') {
        const targetId = el.getAttribute('data-target');
        const panel = targetId ? document.getElementById(targetId) : null;
        if (panel) {
          const isHidden = panel.classList.toggle('hidden');
          if (targetId === 'studentMedalsPanel') {
            el.textContent = isHidden ? 'Ver medallas' : 'Ocultar medallas';
          } else if (targetId === 'studentTrophiesPanel') {
            el.textContent = isHidden ? 'Ver trofeos' : 'Ocultar trofeos';
          }
        }
        return;
      }

      if (kind === 'editChallenge') {
        await loadChallengeIntoForm(id);
        return;
      }

      if (kind === 'viewParticipants') {
        await toggleParticipants(id);
        return;
      }

      if (kind === 'changeParticipantsPage') {
        const page = Number(el.getAttribute('data-page') || '1');
        const challengeId = el.getAttribute('data-id');
        if (page >= 1 && challengeId) await loadParticipants(challengeId, page);
        return;
      }

      if (kind === 'deleteChallenge') {
        const ok = confirm('¿Seguro que deseas eliminar este reto o misión? Las recompensas ya otorgadas se conservarán.');
        if (!ok) return;
        const fd = new FormData();
        fd.append('id', String(id));
        await api('gamification_delete', { data: fd, isForm: true });
        await loadGamification(1);
        return;
      }
    } catch (err) {
      alert(err?.json?.message || 'Ocurrió un error en gamificación');
    }
  });

  document.getElementById('gamificationPanel').addEventListener('submit', async (e) => {
    const form = e.target.closest('.participantRewardForm');
    if (!form) return;
    e.preventDefault();

    const msg = form.querySelector('.participantMsg');
    msg.textContent = '';

    try {
      const fd = new FormData(form);
      fd.append('challenge_id', form.getAttribute('data-challenge-id'));
      fd.append('student_id', form.getAttribute('data-student-id'));
      await api('gamification_assign_reward', { data: fd, isForm: true });
      msg.textContent = 'Recompensa asignada';
      const challengeId = form.getAttribute('data-challenge-id');
      const page = currentParticipantsPages[challengeId] || 1;
      await loadParticipants(challengeId, page);
      await loadGamification(currentChallengePage);
    } catch (err) {
      msg.textContent = err?.json?.message || 'Error asignando recompensa';
    }
  });

  document.getElementById('btnToggleChallengeForm')?.addEventListener('click', () => {
    const form = document.getElementById('formChallenge');
    if (!form) return;
    const visible = form.style.display !== 'none';
    if (visible) {
      form.style.display = 'none';
      resetChallengeForm();
    } else {
      form.style.display = 'grid';
    }
  });

  document.getElementById('btnResetChallengeForm')?.addEventListener('click', () => {
    resetChallengeForm();
    document.getElementById('formChallenge').style.display = 'none';
  });

  document.getElementById('formChallenge')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const fd = new FormData(e.target);
    const msg = document.getElementById('msgChallenge');
    msg.textContent = '';

    try {
      const id = document.getElementById('challenge_id').value;
      const action = id ? 'gamification_update' : 'gamification_create';
      await api(action, { data: fd, isForm: true });
      msg.textContent = id ? 'Reto o misión actualizado.' : 'Reto o misión creado.';
      resetChallengeForm();
      document.getElementById('formChallenge').style.display = 'none';
      await loadGamification(1);
    } catch (err) {
      msg.textContent = err?.json?.message || 'Error guardando reto o misión';
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
  loadGamification();
  if (IS_ADMIN) loadDocentes();
</script>

<style>
  .btn-ghost {
    background: transparent;
    border: 1px solid rgba(255, 255, 255, .18);
  }

  .wrap {
    flex-wrap: wrap;
  }

  .row-between-center {
    display: flex;
    justify-content: space-between;
    align-items: center;
  }

  .row-between-start {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
  }

  .participantRewardForm textarea,
  .participantRewardForm input,
  .participantRewardForm select {
    width: 100%;
    margin-top: 6px;
  }

  .gm-student-rewards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
    gap: 16px;
    margin-bottom: 20px;
  }

  .gm-mini-card {
    border: 1px solid rgba(0, 180, 255, .35);
    border-radius: 14px;
    padding: 16px;
  }

  .gm-mini-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
  }

  .gm-mini-title {
    font-weight: 700;
    font-size: 16px;
  }

  .gm-drop-panel {
    margin-top: 12px;
    padding-top: 12px;
    border-top: 1px solid rgba(255, 255, 255, .08);
  }

  .gm-reward-item {
    padding: 10px 0;
    border-bottom: 1px solid rgba(255, 255, 255, .06);
  }

  .gm-reward-item:last-child {
    border-bottom: none;
  }

  .hidden {
    display: none;
  }

  .gm-card {
    border: 1px solid rgba(255, 255, 255, .12);
    border-radius: 16px;
    padding: 16px 18px;
    background: transparent;
    box-shadow: none;
  }

  .gm-card-top {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 16px;
    margin-bottom: 14px;
    flex-wrap: wrap;
  }

  .gm-card-title-wrap {
    min-width: 220px;
    flex: 1 1 320px;
  }

  .gm-card-title {
    font-size: 20px;
    font-weight: 700;
    margin-bottom: 6px;
    line-height: 1.25;
  }

  .gm-card-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    justify-content: flex-end;
  }

  .gm-card-content {
    display: grid;
    gap: 10px;
    line-height: 1.6;
    width: 100%;
  }

  .gm-card-content div {
    word-break: break-word;
  }

  .gm-instructions {
    white-space: pre-line;
  }
</style>

<?php include __DIR__ . '/components/footer.php'; ?>