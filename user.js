/* Kolekta — resident dashboard. */

const KO = window.KOLEKTA || {};
const WASTE_TYPES = KO.wasteTypes || [];
const USER = KO.user || {};

const TYPE_LABEL = {};
const TYPE_CLASS = {
  biodegradable: 'bio',
  non_biodegradable: 'non-bio',
  recyclable: 'recyc',
  mixed: 'mixed',
};
WASTE_TYPES.forEach((w) => { TYPE_LABEL[w.code] = w.name; });

const STATUS_BADGE = {
  pending: 'b-pending',
  investigating: 'b-investigating',
  resolved: 'b-resolved',
  archived: 'b-archived',
};

let lastDispatchId = null;
let notifData = [];

document.addEventListener('DOMContentLoaded', init);

async function init() {
  initWastePick();
  initNav();
  initBell();

  await Promise.all([
    loadSchedule(),
    loadDispatch(),
    loadAnnouncements(),
    loadMyReports(),
    loadProfile(),
    loadAlerts(),
  ]);

  await loadNotifCount();
  initReportForm();
  initProfileForms();
}

function gotoSection(hash) {
  const id = (hash || '#overview').replace('#', '');
  const sections = document.querySelectorAll('main > section[id]');
  let found = false;
  sections.forEach((s) => {
    const match = s.id === id;
    s.style.display = match ? '' : 'none';
    if (match) found = true;
  });
  if (!found) {
    sections.forEach((s) => { s.style.display = s.id === 'overview' ? '' : 'none'; });
  }
  document.querySelectorAll('.side-nav a').forEach((a) => {
    a.classList.toggle('active', a.getAttribute('href') === '#' + id);
  });
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

function initNav() {
  document.querySelectorAll('.side-nav a').forEach((a) => {
    a.addEventListener('click', (e) => {
      e.preventDefault();
      location.hash = a.getAttribute('href');
      gotoSection(location.hash);
    });
  });
  document.querySelectorAll('[data-goto]').forEach((b) => {
    b.addEventListener('click', () => {
      const h = '#' + b.getAttribute('data-goto');
      location.hash = h;
      gotoSection(h);
    });
  });
  window.addEventListener('hashchange', () => gotoSection(location.hash));
  gotoSection(location.hash || '#overview');
}

function initBell() {
  const panel = document.getElementById('notifPanel');
  document.addEventListener('click', (e) => {
    if (panel.classList.contains('open') && !panel.contains(e.target) && e.target.id !== 'bell') {
      panel.classList.remove('open');
    }
  });
}

function initWastePick() {
  const pick = document.getElementById('wastePick');
  if (!pick || !WASTE_TYPES.length) return;
  pick.innerHTML = WASTE_TYPES.map((w) => `
    <label class="pick-opt">
      <input type="radio" name="waste" value="${escapeHtml(w.code)}">
      <span class="lbl">${escapeHtml(w.name)}</span>
    </label>`).join('');
  const first = pick.querySelector('input');
  if (first) first.checked = true;
}

/* -------------------------------------------------------- schedule */
async function loadSchedule() {
  try {
    const data = await API.get('api/schedule.php');
    renderWeek(data.grid);
    renderNext(data.next);
    renderScheduleDays(data.grid);
    document.getElementById('weekZone').textContent = data.zone.name || '';
    document.getElementById('scheduleZone').textContent = data.zone.name || '';
  } catch (err) {
    toast(err.message, true);
  }
}

function renderWeek(grid) {
  const el = document.getElementById('week');
  if (!el) return;
  el.innerHTML = '';

  const now = new Date();
  const todayNum = ((now.getDay() + 6) % 7) + 1; // Mon = 1

  grid.forEach((day) => {
    const div = document.createElement('div');
    div.className = 'day' + (day.weekday === todayNum ? ' today' : '') + (day.waste_types.length === 0 ? ' empty' : '');

    const name = document.createElement('span');
    name.className = 'd-name';
    name.textContent = day.short;

    const date = new Date(now);
    const diff = ((day.weekday - todayNum) + 7) % 7;
    date.setDate(now.getDate() + diff);
    const dateEl = document.createElement('span');
    dateEl.className = 'd-date';
    dateEl.textContent = String(date.getDate()).padStart(2, '0');

    const types = document.createElement('div');
    types.className = 'd-types';
    day.waste_types.forEach((t) => {
      const pill = document.createElement('span');
      pill.className = 'type-dot ' + TYPE_CLASS[t];
      pill.textContent = TYPE_LABEL[t] || t;
      types.appendChild(pill);
    });

    div.append(name, dateEl, types);
    el.appendChild(div);
  });
}

function renderNext(next) {
  document.getElementById('nextIn').textContent = next
    ? (next.in_days === 0 ? 'Today' : next.in_days === 1 ? 'Tomorrow' : 'In ' + next.in_days + ' days')
    : 'No schedule';

  document.getElementById('nextDay').textContent = next ? next.weekday : '—';
  document.getElementById('nextDate').textContent = next ? fmtDate(next.date) : '';
  const types = document.getElementById('nextTypes');
  types.innerHTML = '';
  if (next) {
    next.waste_types.forEach((t) => {
      const pill = document.createElement('span');
      pill.className = 'type-dot ' + TYPE_CLASS[t];
      pill.textContent = TYPE_LABEL[t] || t;
      types.appendChild(pill);
    });
  }
}

function renderScheduleDays(grid) {
  const el = document.getElementById('scheduleDays');
  if (!el) return;

  const now = new Date();
  const todayNum = ((now.getDay() + 6) % 7) + 1;

  el.innerHTML = grid.map((day) => {
    const isToday = day.weekday === todayNum;
    const rows = day.waste_types.length ? day.waste_types.map((t) => `
      <div class="sched-day-row">
        <span class="type-dot ${TYPE_CLASS[t.code] || 'mixed'}">${escapeHtml(t.label)}</span>
        <span class="mono">${t.window_start ? escapeHtml(t.window_start) + '–' + escapeHtml(t.window_end) : 'Flexible'}</span>
      </div>`).join('')
      : '<div class="sched-day-row muted">No collection — rest day</div>';

    return `
      <div class="sched-day ${isToday ? 'today' : ''}">
        <div class="sd-head">
          <b>${escapeHtml(day.label)}</b>
          ${isToday ? '<span class="badge b-active">Today</span>' : ''}
        </div>
        ${rows}
      </div>`;
  }).join('');
}

/* -------------------------------------------------------- dispatch */
async function loadDispatch() {
  try {
    const data = await API.get('api/dispatch.php?action=latest');
    const latest = data.latest;
    renderAlertLatest(data.latest);

    if (!latest) return;
    const then = new Date(latest.dispatched_at.replace(' ', 'T'));
    const hoursAgo = (Date.now() - then.getTime()) / 36e5;
    if (hoursAgo > 48) return;
    if (sessionStorage.getItem('kolekta_banner_hidden') === String(latest.id)) return;

    const banner = document.getElementById('dispatchBanner');
    const zoneName = USER.zone_name || 'your purok';
    document.getElementById('bannerTitle').textContent = 'Collection underway — ' + zoneName;
    document.getElementById('bannerBody').textContent =
      `The team is collecting ${latest.waste_label.toLowerCase()} in ${zoneName}. ` +
      `Dispatched ${fmtTime(latest.dispatched_at)}.`;
    banner.style.display = 'flex';
    lastDispatchId = latest.id;
  } catch (err) { /* banner is optional */ }
}

function hideBanner() {
  document.getElementById('dispatchBanner').style.display = 'none';
  if (lastDispatchId !== null) {
    sessionStorage.setItem('kolekta_banner_hidden', String(lastDispatchId));
  }
}

function renderAlertLatest(latest) {
  const el = document.getElementById('alertLatest');
  if (!el) return;
  if (!latest) {
    el.innerHTML = '<div class="empty">No active dispatch alerts for your purok right now.</div>';
    return;
  }
  const zoneName = USER.zone_name || 'your purok';
  el.innerHTML = `
    <div class="dispatch-live">
      <div class="dl-ico">${icon('truck')}</div>
      <div class="dl-body">
        <div class="ri-top">
          <b>Collection underway — ${escapeHtml(zoneName)}</b>
          <span class="badge b-active">Active</span>
        </div>
        <div class="ri-meta">
          Collecting <b>${escapeHtml(latest.waste_label.toLowerCase())}</b> · dispatched ${escapeHtml(fmtTime(latest.dispatched_at))} by ${escapeHtml(latest.triggered_by_name || 'the barangay')}
        </div>
        ${latest.message ? `<div class="ri-note">${escapeHtml(latest.message)}</div>` : ''}
      </div>
    </div>`;
}

async function loadAlerts() {
  try {
    const items = await API.get('api/notifications.php?action=list');
    notifData = items;
    renderAlertHistory(items);
    renderNotifListFull();
  } catch (err) { /* ignore */ }
}

function renderAlertHistory(items) {
  const el = document.getElementById('alertHistory');
  if (!el) return;
  const dispatchNotifs = items.filter((n) => n.kind === 'dispatch');
  if (!dispatchNotifs.length) {
    el.innerHTML = '<div class="empty">No past truck alerts recorded.</div>';
    return;
  }
  el.innerHTML = dispatchNotifs.map((n) => `
    <div class="notif-item ${n.is_read ? '' : 'unread'} kind-dispatch">
      <div class="n-ico">${icon('truck')}</div>
      <div class="n-body">
        <b>${escapeHtml(n.title)}</b>
        <p>${escapeHtml(n.body)}</p>
        <div class="n-time">${escapeHtml(fmtTime(n.created_at))}</div>
      </div>
    </div>`).join('');
}

/* --------------------------------------------------- announcements */
async function loadAnnouncements() {
  const el = document.getElementById('announcements');
  try {
    const items = await API.get('api/announcements.php?action=list');
    if (!items.length) {
      el.innerHTML = '<div class="empty">No current announcements.</div>';
      return;
    }
    el.innerHTML = items.map((a) => `
      <div class="ann-item ${a.kind === 'cancellation' ? 'kind-cancel' : ''}">
        <div class="a-ico">${icon(a.kind === 'cancellation' ? 'x' : 'megaphone')}</div>
        <div class="ann-body">
          <b>${escapeHtml(a.title)}</b>
          <p>${escapeHtml(a.body)}</p>
          <div class="ann-meta">${fmtTime(a.created_at)} · ${escapeHtml(a.zone_name || 'All zones')}</div>
        </div>
      </div>`).join('');
  } catch (err) {
    el.innerHTML = '<div class="empty">Notices are unavailable right now.</div>';
  }
}

/* ---------------------------------------------------- report form */
function initReportForm() {
  const form = document.getElementById('reportForm');
  const photo = document.getElementById('reportPhoto');
  if (!form || !photo) return;

  photo.addEventListener('change', () => {
    const zone = document.getElementById('uploadZone');
    if (photo.files && photo.files[0]) {
      const f = photo.files[0];
      zone.classList.add('has-file');
      document.getElementById('uploadMain').textContent = f.name;
      document.getElementById('uploadSub').textContent =
        Math.round(f.size / 1024) + ' KB · ready to attach';
      if (f.size > 5 * 1024 * 1024) {
        toast('Photo exceeds the 5 MB limit.', true);
        photo.value = '';
        zone.classList.remove('has-file');
        document.getElementById('uploadMain').textContent = 'Attach a photo of the uncollected waste';
        document.getElementById('uploadSub').textContent = 'JPEG, PNG, or WebP';
      }
    }
  });

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const address = document.getElementById('reportAddress').value.trim();
    if (!address) return addFieldError(document.getElementById('reportAddress'), 'Enter the address of the missed pickup.');

    const submitBtn = form.querySelector('button[type="submit"]');
    const original = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner"></span> Submitting…';

    const fd = new FormData();
    fd.append('waste_type', (document.querySelector('input[name="waste"]:checked') || {}).value || 'mixed');
    fd.append('address', address);
    fd.append('notes', document.getElementById('reportNotes').value.trim());
    if (photo.files[0]) fd.append('photo', photo.files[0]);

    try {
      await API.call('api/reports.php?action=create', { method: 'POST', body: fd });
      toast('Report submitted. The barangay has been notified.');
      form.reset();
      document.getElementById('uploadZone').classList.remove('has-file');
      document.getElementById('uploadMain').textContent = 'Attach a photo of the uncollected waste';
      document.getElementById('uploadSub').textContent = 'JPEG, PNG, or WebP';
      const first = document.querySelector('input[name="waste"]');
      if (first) first.checked = true;
      await loadMyReports();
      await loadNotifCount();
    } catch (err) {
      toast(err.message, true);
      submitBtn.disabled = false;
      submitBtn.innerHTML = original;
    }
  });
}

/* ------------------------------------------------------ my reports */
async function loadMyReports() {
  const el = document.getElementById('myReports');
  try {
    const rows = await API.get('api/reports.php?action=my');
    const count = document.getElementById('myReportsCount');
    if (count) count.textContent = rows.length
      ? rows.length + ' filed report' + (rows.length === 1 ? '' : 's')
      : '';
    if (!rows.length) {
      el.innerHTML = '<div class="empty">No reports filed yet.</div>';
      return;
    }
    el.innerHTML = rows.map((r) => `
      <div class="report-item">
        <div class="ph">${r.photo_path
          ? `<img src="${escapeHtml(r.photo_path)}" alt="Report photo">`
          : icon('image')}
        </div>
        <div class="ri-body">
          <div class="ri-top">
            <b>${escapeHtml(r.waste_label)}</b>
            <span class="badge ${STATUS_BADGE[r.status] || ''}">${escapeHtml(r.status_label || r.status)}</span>
          </div>
          <div class="ri-meta"><span class="addr">${escapeHtml(r.address)}</span></div>
          ${r.resolution_note ? `<div class="ri-note" style="color:var(--fern)">${escapeHtml(r.resolution_note)}</div>` : ''}
          ${r.secondary_dispatch ? '<div class="ri-time"><span class="badge b-active">Secondary collection arranged</span></div>' : ''}
          <div class="ri-time">Filed ${escapeHtml(fmtTime(r.created_at))}</div>
        </div>
      </div>`).join('');
  } catch (err) {
    el.innerHTML = '<div class="empty">Could not load your reports.</div>';
  }
}

/* ------------------------------------------------------ profile */
async function loadProfile() {
  try {
    const p = await API.get('api/profile.php?action=me');
    document.getElementById('pfName').value = p.name || '';
    document.getElementById('pfEmail').value = p.email || '';
    document.getElementById('pfUser').value = p.username || '';
    document.getElementById('pfPhone').value = p.phone || '';
    document.getElementById('pfHouse').value = p.household || '';
    if (p.zone_id) document.getElementById('pfZone').value = p.zone_id;
  } catch (err) {
    toast('Could not load your profile.', true);
  }
}

function initProfileForms() {
  const profileForm = document.getElementById('profileForm');
  if (profileForm) {
    profileForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      const btn = profileForm.querySelector('button[type="submit"]');
      const original = btn.innerHTML;
      btn.disabled = true;
      btn.innerHTML = '<span class="spinner"></span> Saving…';
      try {
        const res = await API.post('api/profile.php?action=update', {
          name: document.getElementById('pfName').value.trim(),
          email: document.getElementById('pfEmail').value.trim(),
          username: document.getElementById('pfUser').value.trim(),
          phone: document.getElementById('pfPhone').value.trim(),
          household: document.getElementById('pfHouse').value.trim(),
          zone_id: document.getElementById('pfZone').value,
        });
        toast(res.message || 'Profile updated.');
        if (res.zone_changed) {
          setTimeout(() => window.location.reload(), 1200);
        }
      } catch (err) {
        const m = err.message || 'Could not save your profile.';
        const wrap = document.getElementById('pfName').closest('.field');
        /* surface API messages on the fields where they apply */
        if (/email/i.test(m)) addFieldError(document.getElementById('pfEmail'), m);
        else if (/username/i.test(m)) addFieldError(document.getElementById('pfUser'), m);
        else if (/purok|zone/i.test(m)) addFieldError(document.getElementById('pfZone'), m);
        else if (/name/i.test(m)) addFieldError(document.getElementById('pfName'), m);
        else if (/phone|mobile/i.test(m)) addFieldError(document.getElementById('pfPhone'), m);
        else toast(m, true);
      } finally {
        btn.disabled = false;
        btn.innerHTML = original;
      }
    });
  }

  const passwordForm = document.getElementById('passwordForm');
  if (passwordForm) {
    passwordForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      const btn = passwordForm.querySelector('button[type="submit"]');
      const original = btn.innerHTML;
      btn.disabled = true;
      btn.innerHTML = '<span class="spinner"></span> Updating…';
      try {
        const res = await API.post('api/profile.php?action=password', {
          current_password: document.getElementById('pwCurrent').value,
          new_password: document.getElementById('pwNew').value,
          new_password_confirm: document.getElementById('pwConfirm').value,
        });
        toast(res.message || 'Password updated.');
        passwordForm.reset();
      } catch (err) {
        const m = err.message || 'Could not update your password.';
        if (/current/i.test(m)) addFieldError(document.getElementById('pwCurrent'), m);
        else if (/match/i.test(m)) addFieldError(document.getElementById('pwConfirm'), m);
        else if (/8|letters|numbers/i.test(m)) addFieldError(document.getElementById('pwNew'), m);
        else toast(m, true);
      } finally {
        btn.disabled = false;
        btn.innerHTML = original;
      }
    });
  }
}

/* ----------------------------------------------------- notifications */
async function loadNotifCount() {
  try {
    const data = await API.get('api/notifications.php?action=count');
    document.querySelector('.bell').classList.toggle('has', data.unread > 0);
    document.getElementById('navNotif').textContent = data.unread;
  } catch (e) { /* ignore */ }
}

function toggleNotifs() {
  const panel = document.getElementById('notifPanel');
  const open = panel.classList.toggle('open');
  if (open) loadNotifs();
}

async function loadNotifs() {
  const el = document.getElementById('notifList');
  const items = await notifs();
  if (!items.length) {
    el.innerHTML = '<div class="empty">No notifications yet.</div>';
    return;
  }
  el.innerHTML = renderNotifItems(items);

  const unreadIds = items.filter((n) => !n.is_read).map((n) => n.id);
  if (unreadIds.length && sessionStorage.getItem('kolekta_notif_seen') !== unreadIds.join(',')) {
    sessionStorage.setItem('kolekta_notif_seen', unreadIds.join(','));
    setTimeout(async () => {
      await API.post('api/notifications.php?action=mark_read', { ids: unreadIds });
      await loadNotifCount();
      const badge = document.getElementById('bell');
      badge.classList.remove('has');
    }, 3500);
  }
}

async function notifs() {
  try {
    return await API.get('api/notifications.php?action=list');
  } catch (err) {
    return [];
  }
}

function notifIcon(kind) {
  if (kind === 'dispatch') return icon('truck');
  if (kind === 'report_update') return icon('flag');
  if (kind === 'announcement') return icon('megaphone');
  return icon('bell');
}

function renderNotifItems(items) {
  return items.map((n) => `
    <div class="notif-item ${n.is_read ? '' : 'unread'} kind-${n.kind}">
      <div class="n-ico">${notifIcon(n.kind)}</div>
      <div class="n-body">
        <b>${escapeHtml(n.title)}</b>
        <p>${escapeHtml(n.body)}</p>
        <div class="n-time">${escapeHtml(fmtTime(n.created_at))}</div>
      </div>
    </div>`).join('');
}

function renderNotifListFull() {
  const el = document.getElementById('notifListFull');
  if (!el) return;
  if (!notifData.length) {
    el.innerHTML = '<div class="empty">No notifications yet.</div>';
    return;
  }
  el.innerHTML = renderNotifItems(notifData);
}

async function markAllRead() {
  try {
    await API.post('api/notifications.php?action=mark_read', {});
    document.getElementById('bell').classList.remove('has');
    document.getElementById('navNotif').textContent = '0';
    notifData = notifData.map((n) => ({ ...n, is_read: 1 }));
    loadNotifs();
    renderNotifListFull();
    toast('All notifications marked as read.');
  } catch (err) {
    toast(err.message, true);
  }
}

/* ----------------------------------------------------------- misc */
async function signOut() {
  try {
    await API.post('api/auth.php?action=logout', {});
    window.location.href = 'index.php';
  } catch (err) {
    toast(err.message || 'Could not sign out. Try again.', true);
  }
}