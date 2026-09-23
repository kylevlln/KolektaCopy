/* Kolekta — legal helpers: acknowledge-only cookie notice. */

const COOKIE_PREF_KEY = 'kolekta_cookie_ack';

function dismissCookieNotice() {
  try {
    localStorage.setItem(COOKIE_PREF_KEY, '1');
  } catch (e) { /* private mode: still dismiss for this visit */ }
  const el = document.getElementById('cookieNotice');
  if (el) el.hidden = true;
}

document.addEventListener('DOMContentLoaded', () => {
  const el = document.getElementById('cookieNotice');
  if (!el) return;
  let acknowledged = false;
  try {
    acknowledged = localStorage.getItem(COOKIE_PREF_KEY) === '1';
  } catch (e) { /* ignore */ }
  if (!acknowledged) setTimeout(() => { el.hidden = false; }, 250);
});