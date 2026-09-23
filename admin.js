/* Kolekta — administration console. */

const KO = window.KOLEKTA || {};
const WEEK_NAMES = KO.weekNames || ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
const WEEK_DAYS = KO.weekDays || [
  { v: 1, label: 'Monday' }, { v: 2, label: 'Tuesday' }, { v: 3, label: 'Wednesday' },
  { v: 4, label: 'Thursday' }, { v: 5, label: 'Friday' }, { v: 6, label: 'Saturday' }, { v: 7, label: 'Sunday' },
];

const TYPE_CLASS = {
  biodegradable: 'bio',
  non_biodegradable: 'non-bio',
  recyclable: 'recyc',
  mixed: 'mixed',
};

const STATUS_BADGE = {
  active: 'b-active',
  inactive: 'b-inactive',
  pending: 'b-pending',
  investigating: 'b-investigating',
  resolved: 'b-resolved',
  archived: 'b-archived',
  cancelled: 'b-cancelled',
  published: 'b-published',
  draft: 'b-draft',
  expired: 'b-expired',
};

const REPORT_NEXT = {
  pending: ['investigating', 'resolved', 'archived'],
  investigating: ['resolved', 'archived'],
  resolved: ['archived'],
  archived: [],
};

const state = {
  wasteType: '',
  zones: KO.zones || [],
  wasteTypes: KO.wasteTypes || [],
  schedules: [],
  dispatches: [],
  notices: [],
  reports: [],
  edit: { schedule: null, dispatch: null, notice: null, report: null, purok: null, waste: null },
  confirmCb: null,
};

document.addEventListener('DOMContentLoaded', init);

async function init() {
  renderMatrix();
  initWasteSeg();
  initNav();
  bindModals();
  bindFilters();
  bindForms();

  await loadZones();
  await loadWasteTypes();
  buildSelectors();

  await Promise.all([loadSchedules(), loadDispatches(), loadNotices(), loadReports(), refreshStats()]);

  if (location.hash.slice(1) === 'dispatch') {
    document.getElementById('dispatch').scrollIntoView();
  }
}

/* ============================================================ navigation */
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
      const opener = b.getAttribute('data-opens');
      if (opener) openEntity(opener, null);
    });
  });
  document.querySelectorAll('[data-opens]:not([data-goto])').forEach((b) => {
    b.addEventListener('click', () => openEntity(b.getAttribute('data-opens'), null));
  });
  window.addEventListener('hashchange', () => gotoSection(location.hash));
  gotoSection(location.hash || '#overview');
}

function openEntity(kind, id) {
  if (kind === 'schedule') openSchedule(id);
  else if (kind === 'purok') openPurok(id);
  else if (kind === 'waste') openWaste(id);
}

/* ============================================================ modals */
function openModal(id) {
  document.querySelectorAll('.modal-backdrop.open').forEach((m) => m.classList.remove('open'));
  const el = document.getElementById(id);
  if (el) el.classList.add('open');
}

function closeModal(id) {
  const el = document.getElementById(id);
  if (el) el.classList.remove('open');
  if (id === 'confirmModal') state.confirmCb = null;
}

function showConfirm(title, message, onYes, danger = true) {
  document.getElementById('confirmTitle').textContent = title;
  document.getElementById('confirmMsg').textContent = message;
  const btn = document.getElementById('confirmYes');
  btn.className = danger ? 'btn btn--danger-soft' : 'btn btn--primary';
  state.confirmCb = onYes;
  openModal('confirmModal');
}

function bindModals() {
  document.querySelectorAll('.modal-backdrop').forEach((m) => {
    m.addEventListener('click', (e) => {
      if (e.target === m) closeModal(m.id);
    });
  });
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      document.querySelectorAll('.modal-backdrop.open').forEach((m) => closeModal(m.id));
    }
  });
  document.getElementById('confirmYes').addEventListener('click', () => {
    const cb = state.confirmCb;
    closeModal('confirmModal');
    if (cb) cb();
  });
}

/* ============================================================ helpers */
function typeShort(code, name) {
  const map = { biodegradable: 'Bio', non_biodegradable: 'Nonbio', recyclable: 'Recyc', mixed: 'Mixed' };
  if (map[code]) return map[code];
  if (!name) return code;
  return name.length > 8 ? name.slice(0, 7) + '…' : name;
}

function wasteLabel(code) {
  if (!code) return 'Sorted (all)';
  const w = state.wasteTypes.find((x) => x.code === code) || KO.wasteTypes.find((x) => x.code === code);
  return w ? w.name : code.replace(/_/g, ' ');
}

function badge(status) {
  const cls = STATUS_BADGE[status] || '';
  const label = status.charAt(0).toUpperCase() + status.slice(1);
  return `<span class="badge ${cls}">${escapeHtml(label)}</span>`;
}

function typePill(code) {
  const cls = TYPE_CLASS[code] || 'mixed';
  return `<span class="type-dot ${cls}">${escapeHtml(typeShort(code, wasteLabel(code)))}</span>`;
}

function fill(selectId, html) {
  const el = document.getElementById(selectId);
  if (el) el.innerHTML = html;
}

function zoneOptions(placeholder, includeInactive = true) {
  const list = includeInactive ? state.zones : state.zones.filter((z) => z.is_active);
  let html = placeholder !== null ? `<option value="">${placeholder}</option>` : '';
  html += list.map((z) => `<option value="${z.id}">${escapeHtml(z.name)}${z.is_active ? '' : ' (inactive)'}</option>`).join('');
  return html;
}

function wasteOptions(placeholder, emptyLabel) {
  const list = state.wasteTypes.filter((w) => w.is_active);
  let html = '';
  if (emptyLabel !== undefined) html += `<option value="">${emptyLabel}</option>`;
  else if (placeholder) html += `<option value="">${placeholder}</option>`;
  html += list.map((w) => `<option value="${w.code}">${escapeHtml(w.name)}</option>`).join('');
  return html;
}

function buildSelectors() {
  /* Keep any active filter choices while the option lists are rebuilt. */
  const keep = {};
  ['schedFilterZone', 'schedFilterWaste', 'schedFilterDay', 'schedFilterStatus',
    'dispatchFilterZone', 'dispatchFilterStatus',
    'reportFilterStatus', 'reportFilterZone', 'reportFilterWaste'].forEach((id) => {
    const el = document.getElementById(id);
    if (el) keep[id] = el.value;
  });

  const zoneAll = zoneOptions('All puroks');
  fill('schedFilterZone', zoneAll);
  fill('reportFilterZone', zoneAll);
  fill('dispatchFilterZone', zoneAll);

  fill('schedFilterWaste', wasteOptions('All waste types'));
  fill('reportFilterWaste', wasteOptions('All waste types'));

  const dayOpts = '<option value="">All days</option>' + WEEK_DAYS.map((d) => `<option value="${d.v}">${d.label}</option>`).join('');
  fill('schedFilterDay', dayOpts);

  fill('schedZone', zoneOptions('Select purok…'));
  fill('schedDay', '<option value="">Select…</option>' + WEEK_DAYS.map((d) => `<option value="${d.v}">${d.label}</option>`).join(''));
  fill('schedWaste', wasteOptions('Select…'));

  fill('dispatchZone', zoneOptions(null));
  fill('dispatchWaste', wasteOptions(undefined, 'Sorted (all)'));

  fill('editAnnScope', '<option value="all">All puroks</option>' + zoneOptions(null));
}

/* ============================================================ stats */
async function refreshStats() {
  try {
    const data = await API.get('api/stats.php');
    const s = data.stats;

    const row1 = document.getElementById('statsRow').querySelectorAll('.s-val');
    countUp(row1[0], s.residents);
    countUp(row1[1], s.active_residents);
    countUp(row1[2], s.schedules_today);
    countUp(row1[3], s.active_dispatches);

    const row2 = document.getElementById('statsRow2').querySelectorAll('.s-val');
    countUp(row2[0], s.pending_reports);
    countUp(row2[1], s.investigating_reports);
    countUp(row2[2], s.resolved_reports);
    countUp(row2[3], s.active_zones);

    document.getElementById('navPending').textContent = s.pending_reports;
    renderActivity(data.recent || []);
    renderZoneGrid((data.zones || []).filter((z) => z.is_active));
    renderPurokStats(data.zones || []);
  } catch (err) {
    toast(err.message, true);
  }
}

function renderActivity(log) {
  const el = document.getElementById('activityLog');
  if (!el) return;
  if (!log.length) {
    el.innerHTML = '<div class="empty">No dispatches yet.</div>';
    return;
  }
  el.innerHTML = '<div class="log">' + log.map((d) => `
    <div class="log-item">
      <span class="dot"></span>
      <div class="log-body">
        <b>${escapeHtml(d.zone_name)}</b>
        <span>${escapeHtml(d.waste_label)} · by ${escapeHtml(d.by_name)} · ${escapeHtml(d.status)}</span>
      </div>
      <span class="log-time">${escapeHtml(d.dispatched_at)}</span>
    </div>`).join('') + '</div>';
}

function renderPurokStats(zones) {
  const el = document.getElementById('purokStats');
  if (!el || !zones.length) return;
  el.innerHTML = zones.map((z) => `
    <div class="purok-stat">
      <span class="ps-name">${escapeHtml(z.zone_name)} <span class="mono">${escapeHtml(z.zone_code)}</span></span>
      <span class="ps-meta">${z.residents} resident${z.residents === 1 ? '' : 's'}${z.pending ? ` · <b>${z.pending} pending</b>` : ''}</span>
    </div>`).join('');
}

/* ============================================================ dispatch */
function initWasteSeg() {
  const seg = document.getElementById('wasteSeg');
  if (!seg) return;
  const active = state.wasteTypes.filter((w) => w.is_active);
  seg.innerHTML = active.map((w) => `<button type="button" data-waste="${escapeHtml(w.code)}">${escapeHtml(w.name)}</button>`).join('')
    + '<button type="button" data-waste="" class="on">Sorted (all)</button>';
  seg.querySelectorAll('button').forEach((btn) => {
    btn.addEventListener('click', () => {
      seg.querySelectorAll('button').forEach((b) => b.classList.remove('on'));
      btn.classList.add('on');
      state.wasteType = btn.getAttribute('data-waste') || '';
    });
  });
}

function renderZoneGrid(zones) {
  const el = document.getElementById('zoneGrid');
  if (!el) return;

  if (!zones.length) {
    el.innerHTML = '<div class="empty">No active puroks yet. Add one under Puroks.</div>';
    return;
  }

  el.innerHTML = zones.map((z) => {
    const pending = z.pending || 0;
    return `
      <div class="zone-tile" data-zone="${z.zone_id || z.id}">
        <div class="zt-top">
          <span class="zt-code">${escapeHtml(z.zone_code || z.code)}</span>
          <span class="status-led"></span>
        </div>
        <span class="zt-name">${escapeHtml(z.zone_name || z.name)}</span>
        <div class="zt-meta">
          <span>${pending ? `${pending} pending report${pending > 1 ? 's' : ''}` : 'No pending reports'}</span>
        </div>
        <button class="btn btn--primary btn--sm" data-trigger="${z.zone_id || z.id}">Dispatch to ${escapeHtml(z.zone_name || z.name)}</button>
      </div>`;
  }).join('');

  el.querySelectorAll('[data-trigger]').forEach((btn) => {
    btn.addEventListener('click', () => triggerDispatch(parseInt(btn.getAttribute('data-trigger'), 10), btn));
  });
}

async function triggerDispatch(zoneId, btn) {
  const original = btn.textContent;
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner"></span> Dispatching…';

  try {
    const data = await API.post('api/dispatch.php?action=trigger', {
      zone_id: zoneId,
      waste_type: state.wasteType,
    });
    toast(`Alert sent — ${data.dispatch.zone} (${data.dispatch.waste_label}).`);

    const tile = btn.closest('.zone-tile');
    const led = tile.querySelector('.status-led');
    led.classList.add('live');
    btn.textContent = 'Dispatch sent';

    setTimeout(() => {
      btn.disabled = false;
      btn.textContent = original;
      setTimeout(() => { if (led) led.classList.remove('live'); }, 6000);
    }, 2600);

    await Promise.all([refreshStats(), loadDispatches()]);
  } catch (err) {
    toast(err.message, true);
    btn.disabled = false;
    btn.textContent = original;
  }
}

async function loadDispatches() {
  try {
    state.dispatches = await API.get('api/dispatch.php?action=list');
    renderDispatchList();
  } catch (err) {
    document.getElementById('dispatchList').innerHTML = '<div class="empty">Could not load dispatches.</div>';
  }
}

function renderDispatchList() {
  const el = document.getElementById('dispatchList');
  const zone = document.getElementById('dispatchFilterZone').value;
  const status = document.getElementById('dispatchFilterStatus').value;

  let rows = state.dispatches;
  if (zone) rows = rows.filter((d) => String(d.zone_id) === String(zone));
  if (status) rows = rows.filter((d) => d.status === status);

  if (!rows.length) {
    el.innerHTML = '<div class="empty">No dispatch alerts match these filters.</div>';
    return;
  }

  el.innerHTML = '<div class="table-wrap"><table class="table"><thead><tr>'
    + '<th>Purok</th><th>Collecting</th><th>Message</th><th>Status</th><th>When</th><th class="ta-r">Actions</th>'
    + '</tr></thead><tbody>'
    + rows.map((d) => `
      <tr>
        <td><b>${escapeHtml(d.zone_name)}</b> <span class="mono">${escapeHtml(d.zone_code)}</span></td>
        <td>${d.waste_type ? typePill(d.waste_type) : '<span class="muted">Sorted (all)</span>'}</td>
        <td class="cell-msg">${d.message ? escapeHtml(d.message) : '<span class="muted">—</span>'}</td>
        <td>${badge(d.status)}</td>
        <td class="mono nowrap">${escapeHtml(fmtTime(d.dispatched_at))}</td>
        <td class="ta-r nowrap">
          <button class="ic-btn" title="Edit" data-act="edit-dispatch" data-id="${d.id}">${icon('pencil')}</button>
          ${d.status === 'active' ? `<button class="ic-btn danger" title="Cancel" data-act="cancel-dispatch" data-id="${d.id}">${icon('x')}</button>` : ''}
          ${d.status !== 'archived' ? `<button class="ic-btn" title="Archive" data-act="archive-dispatch" data-id="${d.id}">${icon('archive')}</button>` : ''}
          ${d.status !== 'active' ? `<button class="ic-btn" title="Re-activate" data-act="reactivate-dispatch" data-id="${d.id}">${icon('check')}</button>` : ''}
        </td>
      </tr>`).join('')
    + '</tbody></table></div>';
}

function openDispatch(id) {
  const d = state.dispatches.find((x) => x.id === id);
  if (!d) return;
  state.edit.dispatch = d;
  document.getElementById('dispatchZone').value = d.zone_id;
  document.getElementById('dispatchWaste').value = d.waste_type || '';
  document.getElementById('dispatchMessage').value = d.message || '';
  openModal('dispatchModal');
}

/* ============================================================ schedules */
async function loadSchedules() {
  try {
    state.schedules = await API.get('api/schedule.php?action=list');
    renderMatrix(state.schedules);
    renderScheduleList();
  } catch (err) {
    document.getElementById('schedList').innerHTML = '<div class="empty">Could not load schedules.</div>';
  }
}

function renderScheduleList() {
  const el = document.getElementById('schedList');
  const zone = document.getElementById('schedFilterZone').value;
  const waste = document.getElementById('schedFilterWaste').value;
  const day = document.getElementById('schedFilterDay').value;
  const status = document.getElementById('schedFilterStatus').value;

  let rows = state.schedules;
  if (zone) rows = rows.filter((s) => String(s.zone_id) === String(zone));
  if (waste) rows = rows.filter((s) => s.waste_type === waste);
  if (day) rows = rows.filter((s) => String(s.weekday) === String(day));
  if (status === 'active') rows = rows.filter((s) => s.is_active);
  if (status === 'inactive') rows = rows.filter((s) => !s.is_active);

  if (!rows.length) {
    el.innerHTML = '<div class="empty">No schedules match these filters.</div>';
    return;
  }

  el.innerHTML = '<div class="table-wrap"><table class="table"><thead><tr>'
    + '<th>Purok</th><th>Day</th><th>Waste type</th><th>Window</th><th>Status</th><th class="ta-r">Actions</th>'
    + '</tr></thead><tbody>'
    + rows.map((s) => `
      <tr>
        <td><b>${escapeHtml(s.zone_name)}</b> <span class="mono">${escapeHtml(s.zone_code)}</span></td>
        <td>${escapeHtml(s.day_label)}</td>
        <td>${typePill(s.waste_type)}</td>
        <td class="mono nowrap">${s.window_start ? escapeHtml(s.window_start) + '–' + escapeHtml(s.window_end) : '<span class="muted">Flexible</span>'}</td>
        <td>${badge(s.is_active ? 'active' : 'inactive')}</td>
        <td class="ta-r nowrap">
          <button class="ic-btn" title="Edit" data-act="edit-sched" data-id="${s.id}">${icon('pencil')}</button>
          <button class="ic-btn" title="${s.is_active ? 'Deactivate' : 'Activate'}" data-act="toggle-sched" data-id="${s.id}">${s.is_active ? icon('eyeOff') : icon('eye')}</button>
          <button class="ic-btn danger" title="Delete" data-act="del-sched" data-id="${s.id}">${icon('trashCan')}</button>
        </td>
      </tr>`).join('')
    + '</tbody></table></div>';
}

function openSchedule(id) {
  buildSelectors();
  document.getElementById('scheduleForm').reset();
  const title = document.getElementById('scheduleModalTitle');
  const submit = document.getElementById('scheduleSubmit');

  if (id) {
    const s = state.schedules.find((x) => x.id === id);
    if (!s) return;
    state.edit.schedule = s;
    title.textContent = 'Edit schedule';
    submit.textContent = 'Save changes';
    document.getElementById('schedZone').value = s.zone_id;
    document.getElementById('schedDay').value = s.weekday;
    document.getElementById('schedWaste').value = s.waste_type;
    document.getElementById('schedStart').value = s.window_start || '';
    document.getElementById('schedEnd').value = s.window_end || '';
    document.getElementById('schedActive').checked = !!s.is_active;
  } else {
    state.edit.schedule = null;
    title.textContent = 'Add schedule';
    submit.textContent = 'Save schedule';
    document.getElementById('schedActive').checked = true;
  }
  openModal('scheduleModal');
}

/* ============================================================ notices */
async function loadNotices() {
  try {
    state.notices = await API.get('api/announcements.php?action=list');
  } catch (err) {
    state.notices = [];
  }
  renderNoticeList();
  renderAnnPreview();
}

function noticeExpired(n) {
  if (!n.expires_at) return false;
  const d = parseLocal(n.expires_at);
  return d ? d.getTime() < Date.now() : false;
}

function renderAnnPreview() {
  const el = document.getElementById('adminAnnPreview');
  if (!el) return;
  const items = state.notices.slice(0, 4);
  el.innerHTML = items.length ? items.map((a) => `
    <div class="ann-item ${a.kind === 'cancellation' ? 'kind-cancel' : ''}" style="padding:12px 14px;gap:12px">
      <div class="a-ico" style="width:32px;height:32px">${icon(a.kind === 'cancellation' ? 'x' : 'megaphone')}</div>
      <div class="ann-body">
        <b style="font-size:13px">${escapeHtml(a.title)}</b>
        <p>${escapeHtml(a.body)}</p>
        <div class="ann-meta">${escapeHtml(fmtTime(a.created_at))} · ${escapeHtml(a.zone_name || 'All puroks')}</div>
      </div>
    </div>`).join('') : '<div class="empty">No notices yet.</div>';
}

function renderNoticeList() {
  const el = document.getElementById('adminAnnList');
  const filter = document.getElementById('noticeFilterStatus').value;

  let rows = state.notices;
  if (filter === 'published') rows = rows.filter((n) => n.is_published);
  if (filter === 'draft') rows = rows.filter((n) => !n.is_published);

  if (!rows.length) {
    el.innerHTML = '<div class="empty">No notices match this filter.</div>';
    return;
  }

  el.innerHTML = rows.map((a) => {
    const expired = noticeExpired(a);
    return `
    <div class="ann-item ${a.kind === 'cancellation' ? 'kind-cancel' : ''}">
      <div class="a-ico">${icon(a.kind === 'cancellation' ? 'x' : 'megaphone')}</div>
      <div class="ann-body">
        <div class="ri-top">
          <b>${escapeHtml(a.title)}</b>
          ${badge(a.is_published ? 'published' : 'draft')}
          ${expired ? badge('expired') : ''}
        </div>
        <p>${escapeHtml(a.body)}</p>
        <div class="ann-meta">${escapeHtml(a.kind)} · ${escapeHtml(a.zone_name || 'All puroks')} · ${escapeHtml(fmtTime(a.created_at))}${a.expires_at ? ` · expires ${escapeHtml(fmtTime(a.expires_at))}` : ''}</div>
        <div class="ann-actions">
          <button class="ic-btn" title="Edit" data-act="edit-notice" data-id="${a.id}">${icon('pencil')}</button>
          <button class="ic-btn" title="${a.is_published ? 'Unpublish' : 'Publish'}" data-act="publish-notice" data-id="${a.id}" data-publish="${a.is_published ? 0 : 1}">${a.is_published ? icon('eyeOff') : icon('eye')}</button>
          <button class="ic-btn danger" title="Delete" data-act="del-notice" data-id="${a.id}">${icon('trashCan')}</button>
        </div>
      </div>
    </div>`;
  }).join('');
}

function openNotice(id) {
  const a = state.notices.find((x) => x.id === id);
  if (!a) return;
  state.edit.notice = a;
  buildSelectors();
  document.getElementById('editAnnScope').value = a.zone_id ? String(a.zone_id) : 'all';
  const kindRadio = document.querySelector(`input[name="editAnnKind"][value="${a.kind}"]`);
  if (kindRadio) kindRadio.checked = true;
  document.getElementById('editAnnTitle').value = a.title;
  document.getElementById('editAnnBody').value = a.body;
  document.getElementById('editAnnPublished').checked = !!a.is_published;
  document.getElementById('editAnnExpires').value = a.expires_at ? String(a.expires_at).replace(' ', 'T').slice(0, 16) : '';
  openModal('noticeModal');
}

/* ============================================================ reports */
async function loadReports() {
  const el = document.getElementById('triageList');
  const params = new URLSearchParams({ action: 'all' });
  const status = document.getElementById('reportFilterStatus').value;
  const zone = document.getElementById('reportFilterZone').value;
  const waste = document.getElementById('reportFilterWaste').value;
  const date = document.getElementById('reportFilterDate').value;
  if (status) params.set('status', status);
  if (zone) params.set('zone_id', zone);
  if (waste) params.set('waste_type', waste);
  if (date) params.set('date', date);

  try {
    state.reports = await API.get('api/reports.php?' + params.toString());
    renderTriageSummary();
    renderTriageList();
  } catch (err) {
    el.innerHTML = '<div class="empty">Could not load reports.</div>';
  }
}

function renderTriageSummary() {
  const el = document.getElementById('zoneTriageSummary');
  const status = document.getElementById('reportFilterStatus').value;
  if (status !== 'pending' && status !== '') { el.innerHTML = ''; return; }
  const byZone = {};
  state.reports.forEach((r) => { byZone[r.zone_code] = (byZone[r.zone_code] || 0) + 1; });
  const keys = Object.keys(byZone);
  el.innerHTML = keys.length
    ? keys.map((code) => `<span class="chip pending">${escapeHtml(code)} — ${byZone[code]} pending</span>`).join('')
    : '<span class="empty">No pending reports right now.</span>';
}

function renderTriageList() {
  const el = document.getElementById('triageList');
  if (!state.reports.length) {
    el.innerHTML = '<div class="empty">No reports match these filters.</div>';
    return;
  }

  el.innerHTML = state.reports.map((r) => {
    const next = REPORT_NEXT[r.status] || [];
    const actions = next.map((s) => {
      const label = s === 'investigating' ? 'Investigate' : s === 'resolved' ? 'Resolve' : 'Archive';
      const cls = s === 'resolved' ? 'btn--primary' : 'btn--ghost';
      return `<button class="btn ${cls} btn--sm" data-act="report-status" data-id="${r.id}" data-status="${s}">${label}</button>`;
    }).join('');

    return `
    <div class="report-item">
      <div class="ph">${r.photo_path
        ? `<img src="${escapeHtml(r.photo_path)}" alt="Report photo">`
        : icon('image')}
      </div>
      <div class="ri-body">
        <div class="ri-top">
          <b>${escapeHtml(r.resident_name)}</b>
          ${badge(r.status)}
          ${r.secondary_dispatch ? '<span class="badge b-active">Secondary truck</span>' : ''}
        </div>
        <div class="ri-meta">
          <span class="addr">${escapeHtml(r.address)}</span>
          · ${escapeHtml(r.zone_code)} · ${escapeHtml(r.waste_label)}
          ${r.resident_phone ? ` · ${escapeHtml(r.resident_phone)}` : ''}
        </div>
        ${r.notes ? `<div class="ri-note">${escapeHtml(r.notes)}</div>` : ''}
        ${r.resolution_note ? `<div class="ri-note" style="color:var(--fern)">${escapeHtml(r.resolution_note)}</div>` : ''}
        <div class="ri-time">
          Filed ${escapeHtml(fmtTime(r.created_at))}
          ${r.resolved_at ? ` · closed ${escapeHtml(fmtTime(r.resolved_at))}` : ''}
        </div>
      </div>
      ${actions ? `<div class="ri-actions">${actions}</div>` : ''}
    </div>`;
  }).join('');
}

function openReport(id, preselect) {
  const r = state.reports.find((x) => x.id === id);
  if (!r) return;
  state.edit.report = r;
  const options = REPORT_NEXT[r.status] || [];
  document.getElementById('reportModalTitle').textContent = 'Update report — ' + r.resident_name;
  document.getElementById('reportSub').textContent = r.address + ' · ' + r.zone_code;
  const sel = document.getElementById('reportNewStatus');
  sel.innerHTML = options.map((s) => `<option value="${s}">${s.charAt(0).toUpperCase() + s.slice(1)}</option>`).join('');
  sel.value = preselect && options.includes(preselect) ? preselect : options[0];
  document.getElementById('reportNote').value = '';
  document.getElementById('reportDispatch').checked = false;
  syncReportDispatchRow();
  sel.onchange = syncReportDispatchRow;
  openModal('reportModal');
}

function syncReportDispatchRow() {
  const val = document.getElementById('reportNewStatus').value;
  document.getElementById('reportDispatchRow').style.display = val === 'resolved' ? '' : 'none';
}

/* ============================================================ zones */
async function loadZones() {
  try {
    state.zones = await API.get('api/zones.php');
  } catch (err) {
    state.zones = KO.zones || [];
  }
  renderPurokList();
}

function renderPurokList() {
  const el = document.getElementById('purokList');
  if (!state.zones.length) {
    el.innerHTML = '<div class="empty">No puroks yet.</div>';
    return;
  }
  el.innerHTML = '<div class="table-wrap"><table class="table"><thead><tr>'
    + '<th>Purok</th><th>Code</th><th>Residents</th><th>Pending reports</th><th>Status</th><th class="ta-r">Actions</th>'
    + '</tr></thead><tbody>'
    + state.zones.map((z) => `
      <tr>
        <td><b>${escapeHtml(z.name)}</b>${z.description ? `<div class="sub">${escapeHtml(z.description)}</div>` : ''}</td>
        <td class="mono">${escapeHtml(z.code)}</td>
        <td>${z.resident_count != null ? z.resident_count : '—'}</td>
        <td>—</td>
        <td>${badge(z.is_active ? 'active' : 'inactive')}</td>
        <td class="ta-r nowrap">
          <button class="ic-btn" title="Edit" data-act="edit-purok" data-id="${z.id}">${icon('pencil')}</button>
          <button class="ic-btn" title="${z.is_active ? 'Deactivate' : 'Activate'}" data-act="toggle-purok" data-id="${z.id}">${z.is_active ? icon('eyeOff') : icon('eye')}</button>
        </td>
      </tr>`).join('')
    + '</tbody></table></div>';
}

function openPurok(id) {
  document.getElementById('purokForm').reset();
  const title = document.getElementById('purokModalTitle');
  const submit = document.getElementById('purokSubmit');
  if (id) {
    const z = state.zones.find((x) => x.id === id);
    if (!z) return;
    state.edit.purok = z;
    title.textContent = 'Edit purok';
    submit.textContent = 'Save changes';
    document.getElementById('purokName').value = z.name;
    document.getElementById('purokCode').value = z.code;
    document.getElementById('purokDesc').value = z.description || '';
  } else {
    state.edit.purok = null;
    title.textContent = 'Add purok';
    submit.textContent = 'Save purok';
  }
  openModal('purokModal');
}

/* ============================================================ waste types */
async function loadWasteTypes() {
  try {
    state.wasteTypes = await API.get('api/waste-types.php');
  } catch (err) {
    state.wasteTypes = KO.wasteTypes || [];
  }
  renderWasteList();
}

function renderWasteList() {
  const el = document.getElementById('wasteList');
  if (!state.wasteTypes.length) {
    el.innerHTML = '<div class="empty">No waste types yet.</div>';
    return;
  }
  el.innerHTML = '<div class="table-wrap"><table class="table"><thead><tr>'
    + '<th>Code</th><th>Display name</th><th>Order</th><th>Status</th><th class="ta-r">Actions</th>'
    + '</tr></thead><tbody>'
    + state.wasteTypes.map((w) => `
      <tr>
        <td class="mono">${escapeHtml(w.code)}</td>
        <td><b>${escapeHtml(w.name)}</b></td>
        <td>${w.sort_order != null ? w.sort_order : 0}</td>
        <td>${badge(w.is_active ? 'active' : 'inactive')}</td>
        <td class="ta-r nowrap">
          <button class="ic-btn" title="Edit" data-act="edit-waste" data-id="${w.id}">${icon('pencil')}</button>
          <button class="ic-btn" title="${w.is_active ? 'Deactivate' : 'Activate'}" data-act="toggle-waste" data-id="${w.id}">${w.is_active ? icon('eyeOff') : icon('eye')}</button>
        </td>
      </tr>`).join('')
    + '</tbody></table></div>';
}

function openWaste(id) {
  document.getElementById('wasteForm').reset();
  const title = document.getElementById('wasteModalTitle');
  const submit = document.getElementById('wasteSubmit');
  const codeInput = document.getElementById('wasteCode');
  if (id) {
    const w = state.wasteTypes.find((x) => x.id === id);
    if (!w) return;
    state.edit.waste = w;
    title.textContent = 'Edit waste type';
    submit.textContent = 'Save changes';
    codeInput.value = w.code;
    codeInput.disabled = true;
    document.getElementById('wasteName').value = w.name;
    document.getElementById('wasteSort').value = w.sort_order || 0;
  } else {
    state.edit.waste = null;
    title.textContent = 'Add waste type';
    submit.textContent = 'Save waste type';
    codeInput.disabled = false;
  }
  openModal('wasteModal');
}

/* ============================================================ matrix */
function renderMatrix(rows) {
  const table = document.getElementById('schedMatrix');
  if (!table) return;

  const header = '<tr><th>Purok</th>' + WEEK_NAMES.map((d) => `<th>${d}</th>`).join('') + '</tr>';

  const byZoneDay = {};
  if (rows) {
    rows.forEach((s) => {
      byZoneDay[s.zone_id] = byZoneDay[s.zone_id] || {};
      byZoneDay[s.zone_id][s.weekday] = byZoneDay[s.zone_id][s.weekday] || [];
      byZoneDay[s.zone_id][s.weekday].push(s);
    });
  }

  const zones = state.zones.length ? state.zones : (KO.zones || []);
  const body = zones.map((z) => {
    const cells = [1, 2, 3, 4, 5, 6, 7].map((d) => {
      const list = (byZoneDay[z.id] || {})[d] || [];
      const pills = list.map((s) => {
        const cls = TYPE_CLASS[s.waste_type] || 'mixed';
        const label = typeShort(s.waste_type, s.waste_label || wasteLabel(s.waste_type));
        return `<span class="type-dot ${cls}${s.is_active ? '' : ' off'}">${escapeHtml(label)}</span>`;
      }).join('');
      return `<td>${pills || '<span style="color:var(--faint)">—</span>'}</td>`;
    }).join('');
    return `<tr><td style="font-weight:600">${escapeHtml(z.name)} <span class="mono" style="color:var(--muted)">${escapeHtml(z.code)}</span>${z.is_active ? '' : ' <span class="badge b-inactive">inactive</span>'}</td>${cells}</tr>`;
  }).join('');

  table.innerHTML = header + body;
}

/* ============================================================ bindings */
function bindFilters() {
  ['schedFilterZone', 'schedFilterWaste', 'schedFilterDay', 'schedFilterStatus'].forEach((id) =>
    document.getElementById(id).addEventListener('change', renderScheduleList));

  ['dispatchFilterZone', 'dispatchFilterStatus'].forEach((id) =>
    document.getElementById(id).addEventListener('change', renderDispatchList));

  document.getElementById('noticeFilterStatus').addEventListener('change', renderNoticeList);

  ['reportFilterStatus', 'reportFilterZone', 'reportFilterWaste', 'reportFilterDate'].forEach((id) =>
    document.getElementById(id).addEventListener('change', loadReports));
}

function bindForms() {
  /* notice create */
  document.getElementById('annForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const title = document.getElementById('annTitle').value.trim();
    const body = document.getElementById('annBody').value.trim();
    if (!title) return addFieldError(document.getElementById('annTitle'), 'Enter a short title.');
    if (!body) return addFieldError(document.getElementById('annBody'), 'Enter a message.');

    const btn = e.target.querySelector('button[type="submit"]');
    const original = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner"></span> Publishing…';

    try {
      await API.post('api/announcements.php?action=create', {
        zone_id: document.getElementById('annScope').value,
        title,
        body,
        kind: document.querySelector('input[name="annKind"]:checked').value,
        is_published: document.getElementById('annPublished').checked ? 1 : 0,
        expires_at: document.getElementById('annExpires').value,
      });
      toast('Notice saved.');
      e.target.reset();
      document.querySelector('input[name="annKind"][value="notice"]').checked = true;
      document.getElementById('annPublished').checked = true;
      await Promise.all([loadNotices(), refreshStats()]);
    } catch (err) {
      toast(err.message, true);
    } finally {
      btn.disabled = false;
      btn.innerHTML = original;
    }
  });

  /* schedule create/update */
  document.getElementById('scheduleForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const payload = {
      zone_id: document.getElementById('schedZone').value,
      weekday: document.getElementById('schedDay').value,
      waste_type: document.getElementById('schedWaste').value,
      window_start: document.getElementById('schedStart').value,
      window_end: document.getElementById('schedEnd').value,
      is_active: document.getElementById('schedActive').checked ? 1 : 0,
    };
    const editing = state.edit.schedule;
    const action = editing ? 'update' : 'create';
    if (editing) payload.id = editing.id;

    const btn = document.getElementById('scheduleSubmit');
    const original = btn.textContent;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner"></span> Saving…';
    try {
      await API.post('api/schedule.php?action=' + action, payload);
      toast(editing ? 'Schedule updated.' : 'Schedule added.');
      closeModal('scheduleModal');
      await Promise.all([loadSchedules(), refreshStats()]);
    } catch (err) {
      toast(err.message, true);
    } finally {
      btn.disabled = false;
      btn.textContent = original;
    }
  });

  /* dispatch edit */
  document.getElementById('dispatchForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const d = state.edit.dispatch;
    if (!d) return;
    const btn = e.target.querySelector('button[type="submit"]');
    const original = btn.textContent;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner"></span> Saving…';
    try {
      await API.post('api/dispatch.php?action=update', {
        id: d.id,
        zone_id: document.getElementById('dispatchZone').value,
        waste_type: document.getElementById('dispatchWaste').value,
        message: document.getElementById('dispatchMessage').value.trim(),
      });
      toast('Dispatch alert updated.');
      closeModal('dispatchModal');
      await Promise.all([loadDispatches(), refreshStats()]);
    } catch (err) {
      toast(err.message, true);
    } finally {
      btn.disabled = false;
      btn.textContent = original;
    }
  });

  /* notice edit */
  document.getElementById('noticeForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const a = state.edit.notice;
    if (!a) return;
    const btn = e.target.querySelector('button[type="submit"]');
    const original = btn.textContent;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner"></span> Saving…';
    try {
      await API.post('api/announcements.php?action=update', {
        id: a.id,
        zone_id: document.getElementById('editAnnScope').value,
        title: document.getElementById('editAnnTitle').value.trim(),
        body: document.getElementById('editAnnBody').value.trim(),
        kind: document.querySelector('input[name="editAnnKind"]:checked').value,
        is_published: document.getElementById('editAnnPublished').checked ? 1 : 0,
        expires_at: document.getElementById('editAnnExpires').value,
      });
      toast('Notice updated.');
      closeModal('noticeModal');
      await Promise.all([loadNotices(), refreshStats()]);
    } catch (err) {
      toast(err.message, true);
    } finally {
      btn.disabled = false;
      btn.textContent = original;
    }
  });

  /* purok create/update */
  document.getElementById('purokForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const editing = state.edit.purok;
    const payload = {
      name: document.getElementById('purokName').value.trim(),
      code: document.getElementById('purokCode').value.trim(),
      description: document.getElementById('purokDesc').value.trim(),
    };
    const action = editing ? 'update' : 'create';
    if (editing) payload.id = editing.id;
    const btn = document.getElementById('purokSubmit');
    const original = btn.textContent;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner"></span> Saving…';
    try {
      await API.post('api/zones.php?action=' + action, payload);
      toast(editing ? 'Purok updated.' : 'Purok added.');
      closeModal('purokModal');
      await loadZones();
      buildSelectors();
      renderMatrix(state.schedules);
      await refreshStats();
    } catch (err) {
      toast(err.message, true);
    } finally {
      btn.disabled = false;
      btn.textContent = original;
    }
  });

  /* waste type create/update */
  document.getElementById('wasteForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const editing = state.edit.waste;
    const payload = {
      name: document.getElementById('wasteName').value.trim(),
      sort_order: document.getElementById('wasteSort').value || 0,
    };
    const action = editing ? 'update' : 'create';
    if (editing) payload.id = editing.id;
    else payload.code = document.getElementById('wasteCode').value.trim();
    const btn = document.getElementById('wasteSubmit');
    const original = btn.textContent;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner"></span> Saving…';
    try {
      await API.post('api/waste-types.php?action=' + action, payload);
      toast(editing ? 'Waste type updated.' : 'Waste type added.');
      closeModal('wasteModal');
      await loadWasteTypes();
      initWasteSeg();
      buildSelectors();
      renderMatrix(state.schedules);
    } catch (err) {
      toast(err.message, true);
    } finally {
      btn.disabled = false;
      btn.textContent = original;
    }
  });

  /* report status */
  document.getElementById('reportSubmit').addEventListener('click', submitReport);
}

/* ============================================================ actions (delegated) */
document.addEventListener('click', async (e) => {
  const btn = e.target.closest('[data-act]');
  if (!btn) return;
  const act = btn.getAttribute('data-act');
  const id = parseInt(btn.getAttribute('data-id'), 10);

  try {
    if (act === 'edit-sched') openSchedule(id);

    else if (act === 'toggle-sched') {
      await API.post('api/schedule.php?action=toggle', { id });
      toast('Schedule status updated.');
      await Promise.all([loadSchedules(), refreshStats()]);
    }

    else if (act === 'del-sched') {
      showConfirm('Delete this schedule?', 'This removes the collection entry for that purok, day and waste type.', async () => {
        await API.post('api/schedule.php?action=delete', { id });
        toast('Schedule deleted.');
        await Promise.all([loadSchedules(), refreshStats()]);
      });
    }

    else if (act === 'edit-dispatch') openDispatch(id);

    else if (act === 'cancel-dispatch') {
      showConfirm('Cancel this dispatch alert?', 'Residents in the purok will be notified that the collection was cancelled.', async () => {
        await API.post('api/dispatch.php?action=set_status', { id, status: 'cancelled' });
        toast('Dispatch alert cancelled.');
        await Promise.all([loadDispatches(), refreshStats()]);
      });
    }

    else if (act === 'archive-dispatch') {
      await API.post('api/dispatch.php?action=set_status', { id, status: 'archived' });
      toast('Dispatch alert archived.');
      await Promise.all([loadDispatches(), refreshStats()]);
    }

    else if (act === 'reactivate-dispatch') {
      await API.post('api/dispatch.php?action=set_status', { id, status: 'active' });
      toast('Dispatch alert re-activated.');
      await Promise.all([loadDispatches(), refreshStats()]);
    }

    else if (act === 'edit-notice') openNotice(id);

    else if (act === 'publish-notice') {
      const publish = btn.getAttribute('data-publish') === '1';
      await API.post('api/announcements.php?action=set_published', { id, is_published: publish ? 1 : 0 });
      toast(publish ? 'Notice published.' : 'Notice unpublished.');
      await Promise.all([loadNotices(), refreshStats()]);
    }

    else if (act === 'del-notice') {
      showConfirm('Delete this notice?', 'This cannot be undone.', async () => {
        await API.post('api/announcements.php?action=delete', { id });
        toast('Notice deleted.');
        await Promise.all([loadNotices(), refreshStats()]);
      });
    }

    else if (act === 'report-status') openReport(id, btn.getAttribute('data-status'));

    else if (act === 'edit-purok') openPurok(id);

    else if (act === 'toggle-purok') {
      await API.post('api/zones.php?action=toggle', { id });
      toast('Purok status updated.');
      await Promise.all([loadZones(), refreshStats()]);
      buildSelectors();
      renderMatrix(state.schedules);
    }

    else if (act === 'edit-waste') openWaste(id);

    else if (act === 'toggle-waste') {
      await API.post('api/waste-types.php?action=toggle', { id });
      toast('Waste type status updated.');
      await loadWasteTypes();
      initWasteSeg();
      buildSelectors();
      renderMatrix(state.schedules);
    }
  } catch (err) {
    if (state.confirmCb) closeModal('confirmModal');
    toast(err.message, true);
  }
});

async function submitReport() {
  const r = state.edit.report;
  if (!r) return;
  const status = document.getElementById('reportNewStatus').value;
  const btn = document.getElementById('reportSubmit');
  const original = btn.textContent;
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner"></span> Updating…';
  try {
    const res = await API.post('api/reports.php?action=set_status', {
      id: r.id,
      status,
      note: document.getElementById('reportNote').value.trim(),
      dispatch: status === 'resolved' && document.getElementById('reportDispatch').checked ? 1 : 0,
    });
    toast(res.message || 'Report updated.');
    closeModal('reportModal');
    await Promise.all([loadReports(), refreshStats()]);
  } catch (err) {
    toast(err.message, true);
  } finally {
    btn.disabled = false;
    btn.textContent = original;
  }
}

/* ============================================================ misc */
async function signOut() {
  try {
    await API.post('api/auth.php?action=logout', {});
    window.location.href = 'index.php';
  } catch (err) {
    toast(err.message || 'Could not sign out. Try again.', true);
  }
}