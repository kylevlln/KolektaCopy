<?php
/**
 * Kolekta — resident registration.
 */

declare(strict_types=1);

require_once __DIR__ . '/inc/page.php';

page_guest_only();

$zones = db()->query('SELECT id, name, code FROM zones WHERE is_active = 1 ORDER BY id')->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Register your household — Kolekta</title>
<?= csrf_meta() ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,400;0,9..144,480;0,9..144,560;1,9..144,400;1,9..144,480&family=Inter:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='9' fill='%231E3B2C'/%3E%3Cpath d='M10 20c3 3 9 3 12 0s3-8 0-11' stroke='%23F5F0E4' stroke-width='2.4' fill='none' stroke-linecap='round'/%3E%3C/svg%3E">
<link rel="stylesheet" href="assets/css/kolekta.css">
</head>
<body class="auth">

<main class="auth-left">
  <div class="auth-card" style="max-width:460px">
    <div class="auth-top">
      <a class="back-btn" href="login.php" aria-label="Back to Sign in" title="Back to Sign in">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
      </a>
      <span class="auth-sg"><?= esc(BARANGAY_NAME) ?></span>
    </div>

    <h1 class="auth-title">Create an account</h1>
    <p class="auth-sub">Secure Your Household Waste &amp; Route Dispatch Account</p>
    <p class="auth-sub-note">After creating your account you'll be sent to the Sign In page.</p>

    <form id="registerForm" novalidate autocomplete="on">
      <div class="field">
        <label for="regName">Full name</label>
        <input class="input" type="text" id="regName" maxlength="90" placeholder="e.g. Juan Dela Cruz">
        <p class="field-error"></p>
      </div>

      <div class="form-row">
        <div class="field">
          <label for="regEmail">Email</label>
          <input class="input" type="email" id="regEmail" maxlength="160" placeholder="name@email.com">
          <p class="field-error"></p>
        </div>
        <div class="field">
          <label for="regUser">Username <span class="opt">optional</span></label>
          <input class="input" type="text" id="regUser" maxlength="60" placeholder="juandelacruz">
          <p class="field-error"></p>
        </div>
      </div>

      <div class="form-row">
        <div class="field">
          <label for="regZone">Purok / zone</label>
          <select class="select" id="regZone">
            <option value="">Select your purok…</option>
            <?php foreach ($zones as $z): ?>
              <option value="<?= (int)$z['id'] ?>"><?= esc($z['name']) ?> — <?= esc($z['code']) ?></option>
            <?php endforeach; ?>
          </select>
          <p class="field-error"></p>
        </div>
        <div class="field">
          <label for="regPhone">Mobile <span class="opt">optional</span></label>
          <input class="input" type="tel" id="regPhone" maxlength="24" placeholder="0917 123 4567">
          <p class="field-error"></p>
        </div>
      </div>

      <div class="field">
        <label for="regHouse">Household / street address <span class="opt">optional</span></label>
        <input class="input" type="text" id="regHouse" maxlength="120" placeholder="e.g. 14 Rizal St, Sitio Proper">
        <p class="field-error"></p>
      </div>

      <div class="form-row">
        <div class="field">
          <label for="regPass">Password</label>
          <div class="input-wrap" style="display:flex;gap:0">
            <input class="input" type="password" id="regPass" maxlength="72"
                   style="border-right:0;border-top-right-radius:0;border-bottom-right-radius:0">
            <button type="button" onclick="toggleShow('regPass', this)"
                    style="border:1px solid var(--hairline);border-left:0;border-top-right-radius:10px;border-bottom-right-radius:10px;background:var(--white);padding:0 14px;font-size:12px;font-weight:600;color:var(--ink-2)">Show</button>
          </div>
          <div class="pass-strength" id="passStrength"><i></i></div>
          <ul class="pass-rules" id="regPassRules">
            <li data-rule="len">At least 8 characters</li>
            <li data-rule="num">Contains a number</li>
            <li data-rule="space">No spaces</li>
            <li data-rule="sym">No special symbols</li>
          </ul>
          <p class="field-error"></p>
        </div>
        <div class="field">
          <label for="regConfirm">Confirm password</label>
          <input class="input" type="password" id="regConfirm" maxlength="72">
          <p class="field-error"></p>
        </div>
      </div>

      <div class="field">
        <label class="consent-check" for="regConsent">
          <input type="checkbox" id="regConsent">
          <span>I agree to the
            <a href="legal/terms.php">Terms of Service</a> and the
            <a href="legal/privacy.php">Privacy Policy</a>, including how my household's
            information and photos are processed under the Data Privacy Act (R.A. 10173).</span>
        </label>
        <p class="field-error"></p>
      </div>

      <div class="auth-actions">
        <button type="submit" class="btn btn--primary">Create account</button>
      </div>
    </form>

    <div class="auth-or">or</div>

    <p class="auth-foot">
      Already registered?
      <a href="login.php">Sign in</a>
    </p>
  </div>
</main>

<?php include __DIR__ . '/inc/auth_promo.php'; ?>

<?= cookie_notice() ?>
<div class="toast"><span class="t-dot"></span><span class="t-text"></span></div>
<script src="assets/js/app.js"></script>
<script src="assets/js/auth.js"></script>
<script src="assets/js/legal.js"></script>
</body>
</html>