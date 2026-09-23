/* Kolekta — shared application helpers. */

let CSRF = (() => {
  const m = document.querySelector('meta[name="csrf"]');
  return m ? m.content : '';
})();

function getCsrf() { return CSRF; }
function setCsrf(token) { CSRF = token || ''; }

const REDUCED_MOTION = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

const API = {
  async call(url, opts = {}, retried = false) {
    const headers = Object.assign({}, opts.headers || {});
    let body = opts.body;
    if (body && !(body instanceof FormData) && typeof body !== 'string') {
      headers['Content-Type'] = 'application/json';
      body = JSON.stringify(body);
    }
    if (getCsrf()) headers['X-CSRF-Token'] = getCsrf();

    let res;
    try {
      res = await fetch(url, Object.assign({}, opts, { headers, body }));
    } catch (e) {
      throw { message: 'Cannot reach the server. Check that Kolekta is running and that you are connected, then try again.' };
    }

    let data = null;
    try { data = await res.json(); } catch (e) { /* no body */ }

    if (!res.ok || !data || data.ok !== true) {
      /* Stale CSRF token (e.g. a second tab re-authenticated): refresh the
         token from the server and retry once before giving up. */
      if (res.status === 419 && !retried) {
        const refreshed = await refreshCsrf();
        if (refreshed) return API.call(url, opts, true);
        window.location.reload();
        return undefined;
      }
      if (res.status === 401) redirectToLogin();
      throw { status: res.status, message: (data && data.error) || 'Request failed.' };
    }
    return data.data;
  },

  get(url) { return this.call(url); },
  post(url, body) { return this.call(url, { method: 'POST', body }); },
};

async function refreshCsrf() {
  try {
    const res = await fetch('api/auth.php?action=csrf', { credentials: 'same-origin' });
    const j = await res.json();
    if (j && j.ok && j.data && j.data.csrf) {
      setCsrf(j.data.csrf);
      return true;
    }
  } catch (e) { /* fall through */ }
  return false;
}

function redirectToLogin() {
  const page = location.pathname.split('/').pop();
  if (page === 'login.php' || page === 'register.php') return;
  const next = encodeURIComponent(location.pathname + location.search);
  window.location.href = 'login.php?next=' + next;
}

function escapeHtml(value) {
  return String(value ?? '')
    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
}

/* Parse ISO strings robustly: bare dates become local (no UTC shift). */
function parseLocal(iso) {
  if (!iso) return null;
  if (/^\d{4}-\d{2}-\d{2}$/.test(iso)) {
    const [y, m, d] = iso.split('-').map(Number);
    return new Date(y, m - 1, d);
  }
  const d = new Date(iso.replace(' ', 'T'));
  return isNaN(d) ? null : d;
}

function fmtTime(iso) {
  const d = parseLocal(iso);
  if (!d) return iso || '';
  return d.toLocaleString('en-PH', { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' });
}

function fmtDate(iso) {
  const d = parseLocal(iso);
  if (!d) return iso || '';
  return d.toLocaleDateString('en-PH', { weekday: 'long', month: 'long', day: 'numeric' });
}

let toastTimer = null;
function toast(message, error = false) {
  let el = document.querySelector('.toast');
  if (!el) {
    el = document.createElement('div');
    el.className = 'toast';
    el.innerHTML = '<span class="t-dot"></span><span class="t-text"></span>';
    document.body.appendChild(el);
  }
  el.classList.toggle('err', error);
  el.querySelector('.t-text').textContent = message;
  el.classList.remove('show');
  void el.offsetWidth; /* restart the entrance animation */
  el.classList.add('show');
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => el.classList.remove('show'), 3200);
}

function unhideErrors() {
  document.querySelectorAll('.field').forEach((f) => {
    f.classList.remove('has-error');
    const m = f.querySelector('.field-error');
    if (m) { m.textContent = ''; m.classList.remove('ok'); }
  });
}

function markError(fieldId, message) {
  const wrap = document.getElementById((fieldId || '').toString())?.closest('.field');
  unhideErrors();
  if (wrap) {
    wrap.classList.add('has-error');
    const msg = wrap.querySelector('.field-error');
    if (msg) { msg.textContent = message; msg.classList.remove('ok'); }
  } else {
    toast(message, true);
  }
}

function addFieldError(el, message) {
  const wrap = el.closest('.field');
  unhideErrors();
  if (wrap) {
    wrap.classList.add('has-error');
    const msg = wrap.querySelector('.field-error');
    if (msg) { msg.textContent = message; msg.classList.remove('ok'); }
  }
}

/* --- scroll-reveal + animated counters ------------------------------- */

function initReveal() {
  const els = document.querySelectorAll('.reveal');
  if (!els.length) return;
  if (REDUCED_MOTION || !('IntersectionObserver' in window)) {
    els.forEach((el) => el.classList.add('in'));
    return;
  }
  const io = new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
      if (entry.isIntersecting) {
        entry.target.classList.add('in');
        io.unobserve(entry.target);
      }
    });
  }, { threshold: 0.12, rootMargin: '0px 0px -6% 0px' });
  els.forEach((el) => io.observe(el));
}

function countUp(el, target, duration = 700) {
  const value = Number(target) || 0;
  if (REDUCED_MOTION || duration <= 0) { el.textContent = value; return; }
  const start = performance.now();
  const from = parseInt(el.textContent.replace(/[^\d]/g, ''), 10) || 0;
  const step = (now) => {
    const p = Math.min((now - start) / duration, 1);
    const eased = 1 - Math.pow(1 - p, 3);
    el.textContent = Math.round(from + (value - from) * eased);
    if (p < 1) requestAnimationFrame(step);
  };
  requestAnimationFrame(step);
}

/* --- icon set (thin-line, consistent) -------------------------------- */
const Icons = {
  bell: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/></svg>',
  truck: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 18V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v11a1 1 0 0 0 1 1h2"/><path d="M15 18h-5"/><path d="M15 8h4l3 4v5a1 1 0 0 1-1 1h-1"/><circle cx="7" cy="18" r="2"/><circle cx="17.5" cy="18" r="2"/></svg>',
  calendar: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>',
  flag: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/><line x1="4" y1="22" x2="4" y2="15"/></svg>',
  shield: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>',
  camera: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>',
  inbox: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 16 12 14 15 10 15 8 12 2 12"/><path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/></svg>',
  megaphone: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m3 11 18-5v12L3 14v-3z"/><path d="M11.6 16.8a3 3 0 1 1-5.8-1.6"/></svg>',
  check: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>',
  logout: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>',
  x: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>',
  trashCan: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>',
  bioLeaf: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M11 20A7 7 0 0 1 9.8 6.1C15.5 5 17 4.5 19 2c1 2 2 4.2 2 8 0 5.5-4.8 10-10 10Z"/><path d="M2 21c0-3 1.85-5.4 5.1-6"/></svg>',
  recycle: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M7 19H4.8a1.8 1.8 0 0 1-1.6-2.7L6 11"/><path d="M17 19h2.2a1.8 1.8 0 0 0 1.6-2.7L18 11"/><path d="m9 21 2.5-3.5h5L19 21"/><path d="M12 10.5 9.5 7"/><path d="m14.5 7 4 7"/><path d="M9.6 13H8a2 2 0 0 1-1.7-3l1-1.7"/></svg>',
  nonbio: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 3h12l-1.5 18H7.5L6 3z"/><line x1="9.5" y1="9" x2="14.5" y2="9"/></svg>',
  clock: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>',
  mapPin: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>',
  fileText: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>',
  arrowRight: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>',
  home: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>',
  send: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>',
  users: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
  image: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>',
  lock: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>',
  plus: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>',
  pencil: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>',
  eye: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>',
  eyeOff: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/></svg>',
  archive: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><polyline points="21 8 21 21 3 21 3 8"/><rect x="1" y="3" width="22" height="5" rx="1"/><line x1="10" y1="12" x2="14" y2="12"/></svg>',
};

function icon(name) { return Icons[name] || ''; }

document.addEventListener('DOMContentLoaded', () => {
  /* auto-trigger any pending toast via data attr */
  const t = document.querySelector('[data-toast]');
  if (t) { toast(t.getAttribute('data-toast')); }
  initReveal();
});