/* Kolekta — authentication pages. */

document.addEventListener('DOMContentLoaded', () => {
  const loginForm = document.getElementById('loginForm');
  if (loginForm) initLogin(loginForm);

  const registerForm = document.getElementById('registerForm');
  if (registerForm) initRegister(registerForm);

  const forgotForm = document.getElementById('forgotForm');
  if (forgotForm) initForgot(forgotForm);

  const resetForm = document.getElementById('resetForm');
  if (resetForm) initReset(resetForm);
});

/* Only allow local relative redirect targets (from login.php?next=...). */
function safeNext() {
  try {
    const n = new URLSearchParams(location.search).get('next');
    if (n && /^[\w./&-]{1,120}$/.test(n) && !n.startsWith('/') && n.indexOf('://') === -1) return n;
  } catch (e) { /* ignore */ }
  return null;
}

/* ------------------------------------------------------------- email validation */

const EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
const EMAIL_CHARS_RE = /^[A-Za-z0-9.!#$%&'*+\/=?^_`{|}~@-]+$/;

/* mode 'signin': values without "@" are usernames (neutral, no message) and the
   "valid characters" message is not used. mode 'signup': full validation.
   Returns { ok, msg }, or null when neutral. */
function checkEmail(value, mode) {
  if (value.trim() === '') return { ok: false, msg: 'Email is required.' };
  if (/\s/.test(value)) return { ok: false, msg: 'Email cannot contain spaces.' };
  if (mode === 'signin' && value.indexOf('@') === -1) return null;
  if (mode === 'signup' && !EMAIL_CHARS_RE.test(value)) return { ok: false, msg: 'Email can only contain valid characters.' };
  if (!EMAIL_RE.test(value)) return { ok: false, msg: 'Please enter a valid email address.' };
  return { ok: true, msg: 'Valid email address.' };
}

/* Paint feedback under the email/identifier input (single .field-error line).
   showValid=false keeps Sign In simple: green border only, no success message. */
function paintEmail(input, res, showValid) {
  const wrap = input.closest('.field');
  const msg = wrap ? wrap.querySelector('.field-error') : null;
  if (wrap) wrap.classList.remove('has-error');

  if (!res) {
    if (msg) { msg.textContent = ''; msg.classList.remove('ok'); }
    input.classList.remove('ok', 'err');
    return;
  }
  if (res.ok) {
    if (msg) {
      msg.textContent = showValid ? '✓ ' + res.msg : '';
      msg.classList.toggle('ok', showValid);
    }
    input.classList.add('ok');
    input.classList.remove('err');
  } else {
    if (msg) { msg.textContent = '✕ ' + res.msg; msg.classList.remove('ok'); }
    input.classList.add('err');
    input.classList.remove('ok');
  }
}

/* Drop a field's error/valid state so stale messages never linger while typing. */
function clearFieldFeedback(el) {
  const wrap = el.closest('.field');
  if (wrap) wrap.classList.remove('has-error');
  const msg = wrap ? wrap.querySelector('.field-error') : null;
  if (msg) { msg.textContent = ''; msg.classList.remove('ok'); }
  el.classList.remove('ok', 'err');
}

/* ------------------------------------------------- password rules (sign up only) */

const PASS_RULES = [
  { rule: 'len',   test: (v) => v.length >= 8 },
  { rule: 'num',   test: (v) => /[0-9]/.test(v) },
  { rule: 'space', test: (v) => v.length > 0 && !/\s/.test(v) },
  { rule: 'sym',   test: (v) => v.length > 0 && /^[A-Za-z0-9]+$/.test(v) },
];

/* Paint the live password checklist. Returns true when every rule passes. */
function renderPasswordRules(input, rulesEl, feedbackEl) {
  const v = input.value;
  let allOk = true;
  PASS_RULES.forEach((r) => {
    const ok = r.test(v);
    const li = rulesEl ? rulesEl.querySelector('[data-rule="' + r.rule + '"]') : null;
    if (li) li.classList.toggle('ok', ok);
    if (!ok) allOk = false;
  });
  input.classList.toggle('ok', allOk);
  input.classList.toggle('err', !allOk);
  const wrap = input.closest('.field');
  if (wrap) wrap.classList.remove('has-error');
  if (feedbackEl) feedbackEl.textContent = '';
  return allOk;
}

/* Show a mismatch message under the confirm-password field when the two
   values differ. Shared by Sign Up and password reset. */
function clearMismatch(a, b) {
  const wrap = b.closest('.field');
  const msg = wrap ? wrap.querySelector('.field-error') : null;
  if (wrap) wrap.classList.remove('has-error');
  if (msg) { msg.textContent = ''; msg.classList.remove('ok'); }
}
function matchConfirm(a, b) {
  if (!b.value || a.value === b.value) {
    clearMismatch(a, b);
    return;
  }
  const wrap = b.closest('.field');
  const msg = wrap ? wrap.querySelector('.field-error') : null;
  if (wrap) wrap.classList.add('has-error');
  if (msg) {
    msg.textContent = 'Passwords do not match.';
    msg.classList.remove('ok');
  }
}

/* ---------------------------------------------------------------- login */
/* Sign In keeps validation simple: errors appear on submit only,
   no password checklist, no live requirements. */
function initLogin(form) {
  const btn = form.querySelector('button[type="submit"]');
  const identifier = document.getElementById('loginId');
  const password = document.getElementById('loginPass');

  identifier.addEventListener('input', () => clearFieldFeedback(identifier));
  password.addEventListener('input', () => clearFieldFeedback(password));

  form.addEventListener('submit', async (e) => {
    e.preventDefault();

    const idValue = identifier.value.trim();
    const passValue = password.value;

    const emailCheck = checkEmail(identifier.value, 'signin');
    if (emailCheck && !emailCheck.ok) {
      paintEmail(identifier, emailCheck, false);
      identifier.focus();
      return;
    }
    paintEmail(identifier, emailCheck, false); /* null (username) = neutral */

    if (!passValue) {
      addFieldError(password, 'Password is required.');
      password.focus();
      return;
    }

    const original = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner" style="border-top-color:var(--evergreen)"></span> Signing in…';

    try {
      const data = await API.post('api/auth.php?action=login', { identifier: idValue, password: passValue });
      toast(`Welcome back, ${data.name.split(' ')[0]}.`);
      const target = safeNext() || data.redirect;
      setTimeout(() => { window.location.href = target; }, 450);
    } catch (err) {
      if (err.status === 419) {
        window.location.reload();
        return;
      }
      if (err.status === 401) {
        addFieldError(password, 'Incorrect password.');
        password.focus();
      } else {
        toast(err.message, true);
        markError('loginId', err.message);
      }
      btn.disabled = false;
      btn.innerHTML = original;
    }
  });
}

/* -------------------------------------------------------------- register */
/* Sign Up uses live validation, but each field only starts showing feedback
   after the user has typed into it or attempted to submit. */
function initRegister(form) {
  const btn = form.querySelector('button[type="submit"]');
  const pass = document.getElementById('regPass');
  const confirm = document.getElementById('regConfirm');
  const passField = pass ? pass.closest('.field') : null;
  const passRules = document.getElementById('regPassRules');
  const email = document.getElementById('regEmail');

  let emailTouched = false;
  let passTouched = false;

  function updateEmail() {
    if (!email || !emailTouched) return;
    paintEmail(email, checkEmail(email.value, 'signup'), true);
  }

  if (email) {
    email.addEventListener('input', () => { emailTouched = true; updateEmail(); });
    email.addEventListener('blur', () => { if (email.value) { emailTouched = true; updateEmail(); } });
  }

  function updatePassword() {
    if (!pass) return false;
    if (passTouched && passRules) passRules.classList.add('show');
    const allOk = renderPasswordRules(pass, passRules, passField ? passField.querySelector('.field-error') : null);

    const strength = document.getElementById('passStrength');
    if (strength) {
      const s = PASS_RULES.filter((r) => r.test(pass.value)).length;
      const i = strength.querySelector('i');
      i.style.width = (s / PASS_RULES.length * 100) + '%';
      if (s === PASS_RULES.length) i.className = 's3';
      else if (s >= 2) i.className = 's2';
      else i.className = '';
    }
    if (confirm && confirm.value) matchConfirm(pass, confirm);
    return allOk;
  }

  if (pass) pass.addEventListener('input', () => { passTouched = true; updatePassword(); });
  if (pass) pass.addEventListener('blur', () => { if (pass.value) { passTouched = true; updatePassword(); } });
  confirm && confirm.addEventListener('input', () => matchConfirm(pass, confirm));

  form.addEventListener('submit', async (e) => {
    e.preventDefault();

    const name = document.getElementById('regName').value.trim();
    const emailVal = document.getElementById('regEmail').value;
    const username = document.getElementById('regUser').value.trim();
    const phone = document.getElementById('regPhone').value.trim();
    const household = document.getElementById('regHouse').value.trim();
    const zone = document.getElementById('regZone').value;
    const password = pass.value;
    const conf = confirm.value;
    const consent = document.getElementById('regConsent');

    if (name.length < 3 || name.length > 90) {
      addFieldError(document.getElementById('regName'), name.length < 3 ? 'Enter your full name.' : 'Keep your name to 90 characters or fewer.');
      return;
    }

    emailTouched = true;
    updateEmail();
    if (!checkEmail(emailVal, 'signup').ok) {
      email.focus();
      return;
    }

    if (username.length > 60) return addFieldError(document.getElementById('regUser'), 'Keep your username to 60 characters or fewer.');
    if (phone && !/^[0-9+\-(). ]{7,24}$/.test(phone)) return addFieldError(document.getElementById('regPhone'), 'Enter a valid mobile number.');
    if (household.length > 120) return addFieldError(document.getElementById('regHouse'), 'Keep the household name to 120 characters or fewer.');
    if (!zone) return addFieldError(document.getElementById('regZone'), 'Choose your purok.');

    passTouched = true;
    if (!updatePassword()) {
      pass.focus();
      return;
    }
    if (password !== conf) {
      addFieldError(document.getElementById('regConfirm'), 'Passwords do not match.');
      document.getElementById('regConfirm').focus();
      return;
    }
    if (!consent || !consent.checked) return addFieldError(document.getElementById('regConsent'), 'Please accept the Privacy Policy and Terms of Service to continue.');

    const original = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner" style="border-top-color:var(--evergreen)"></span> Creating account…';

    try {
      const data = await API.post('api/auth.php?action=register', {
        name, email: emailVal, username, phone, household, zone_id: zone, password,
        accept: consent.checked ? 1 : 0,
      });
      toast('Account created successfully.');
      setTimeout(() => { window.location.href = data.redirect || 'login.php?registered=1'; }, 500);
    } catch (err) {
      if (err.status === 419) { window.location.reload(); return; }
      const msg = err.message || 'Registration failed.';
      if (/email/i.test(msg)) markError('regEmail', msg);
      else if (/username/i.test(msg)) markError('regUser', msg);
      else if (/zone/i.test(msg)) markError('regZone', msg);
      else if (/password/i.test(msg)) markError('regPass', msg);
      else toast(msg, true);
      btn.disabled = false;
      btn.innerHTML = original;
    }
  });
}

/* ---------------------------------------------------------- forgot password */
/* request a reset link; always lands on the "check your inbox" page */
function initForgot(form) {
  const btn = form.querySelector('button[type="submit"]');
  const email = document.getElementById('fpEmail');

  email.addEventListener('input', () => clearFieldFeedback(email));

  form.addEventListener('submit', async (e) => {
    e.preventDefault();

    const res = checkEmail(email.value, 'signup');
    if (!res.ok) {
      paintEmail(email, res, true);
      email.focus();
      return;
    }

    const original = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner" style="border-top-color:var(--evergreen)"></span> Sending…';

    try {
      const data = await API.post('api/auth.php?action=forgot', { email: email.value.trim() });
      toast('Reset link sent.');
      setTimeout(() => { window.location.href = data.redirect || 'forgot-sent.php'; }, 500);
    } catch (err) {
      if (err.status === 419) { window.location.reload(); return; }
      markError('fpEmail', err.message || 'Could not send the reset link.');
      btn.disabled = false;
      btn.innerHTML = original;
    }
  });
}

/* ----------------------------------------------------------- password reset */
/* exchanged for a new password once the emailed token is verified */
function initReset(form) {
  const btn = form.querySelector('button[type="submit"]');
  const pass = document.getElementById('rpPass');
  const confirm = document.getElementById('rpConfirm');
  const passField = pass ? pass.closest('.field') : null;
  const passRules = document.getElementById('rpPassRules');
  const token = document.getElementById('rpToken');
  const email = document.getElementById('rpEmail');

  let passTouched = false;

  function updatePassword() {
    if (!pass) return false;
    if (passTouched && passRules) passRules.classList.add('show');
    const allOk = renderPasswordRules(pass, passRules, passField ? passField.querySelector('.field-error') : null);

    const strength = document.getElementById('rpPassStrength');
    if (strength) {
      const s = PASS_RULES.filter((r) => r.test(pass.value)).length;
      const i = strength.querySelector('i');
      i.style.width = (s / PASS_RULES.length * 100) + '%';
      if (s === PASS_RULES.length) i.className = 's3';
      else if (s >= 2) i.className = 's2';
      else i.className = '';
    }
    if (confirm && confirm.value) matchConfirm(pass, confirm);
    return allOk;
  }

  if (pass) pass.addEventListener('input', () => { passTouched = true; updatePassword(); });
  if (pass) pass.addEventListener('blur', () => { if (pass.value) { passTouched = true; updatePassword(); } });
  confirm && confirm.addEventListener('input', () => matchConfirm(pass, confirm));

  form.addEventListener('submit', async (e) => {
    e.preventDefault();

    passTouched = true;
    if (!pass || !updatePassword()) {
      if (pass) pass.focus();
      return;
    }
    if (confirm && pass.value !== confirm.value) {
      addFieldError(confirm, 'Passwords do not match.');
      confirm.focus();
      return;
    }

    const original = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner" style="border-top-color:var(--evergreen)"></span> Updating…';

    try {
      const data = await API.post('api/auth.php?action=reset', {
        token: token.value, email: email.value, password: pass.value,
      });
      toast('Password updated.');
      setTimeout(() => { window.location.href = data.redirect || 'login.php?reset=1'; }, 600);
    } catch (err) {
      if (err.status === 419) { window.location.reload(); return; }
      markError('rpPass', err.message || 'Could not reset the password.');
      if (passRules) passRules.classList.add('show');
      btn.disabled = false;
      btn.innerHTML = original;
    }
  });
}

function toggleShow(inputId, btn) {
  const input = document.getElementById(inputId);
  const show = input.type === 'password';
  input.type = show ? 'text' : 'password';
  btn.textContent = show ? 'Hide' : 'Show';
}