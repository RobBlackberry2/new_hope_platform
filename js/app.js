async function api(action, { method = 'POST', data = null, params = null, isForm = false } = {}) {
  const base = window.__BASE_URL__ || '';
  const url = new URL(base + '/router.php', window.location.origin);
  url.searchParams.set('action', action);
  if (params) Object.entries(params).forEach(([k, v]) => url.searchParams.set(k, v));

  const opts = { method, credentials: 'include', headers: {} };

  if (data) {
    if (isForm) {
      opts.body = data; // FormData
    } else {
      opts.headers['Content-Type'] = 'application/x-www-form-urlencoded; charset=UTF-8';
      opts.body = new URLSearchParams(data).toString();
    }
  }

  const res = await fetch(url.toString(), opts);

  // Leer respuesta como JSON si se puede
  let json = null;
  try {
    json = await res.json();
  } catch (_) {
    const text = await res.text();
    json = { message: text || (res.ok ? 'OK' : 'Error') };
  }

  if (!res.ok) {
    console.error('API error payload:', json);

    // 👇 aquí metemos detail dentro del mensaje del Error
    const msg = (json.message || 'Error') + (json.detail ? `: ${json.detail}` : '');
    throw Object.assign(new Error(msg), { status: res.status, json });
  }

  return json;
}

document.addEventListener('click', async (e) => {
  if (e.target && e.target.id === 'btnLogout') {
    try {
      await api('logout', { method: 'POST' });
    } catch (err) { }
    window.location.href = (window.__BASE_URL__ || '') + '/login.php';
  }
});

