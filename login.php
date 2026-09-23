<?php
/**
 * Kolekta — sign in.
 */

declare(strict_types=1);

require_once __DIR__ . '/inc/page.php';

page_guest_only();

$prefill = isset($_GET['email']) ? $_GET['email'] : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sign in — Kolekta</title>
<?= csrf_meta() ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,400;0,9..144,480;0,9..144,560;1,9..144,400;1,9..144,480&family=Inter:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='9' fill='%231E3B2C'/%3E%3Cpath d='M10 20c3 3 9 3 12 0s3-8 0-11' stroke='%23F5F0E4' stroke-width='2.4' fill='none' stroke-linecap='round'/%3E%3C/svg%3E">
<link rel="stylesheet" href="assets/css/kolekta.css">
</head>
<body class="auth">

<main class="auth-left">
  <div class="auth-card">
    <div class="auth-top">
      <a class="back-btn" href="index.php" aria-label="Back to the introduction" title="Back to the introduction">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
      </a>
      <span class="auth-sg"><?= esc(BARANGAY_NAME) ?></span>
    </div>

    <h1 class="auth-title">Sign in</h1>
    <p class="auth-sub">Welcome Back — Access Your Barangay Waste Console</p>

    <?php if (isset($_GET['registered']) && $_GET['registered'] === '1'): ?>
      <p class="auth-success" role="status">✓ <?= esc('Account created successfully. Please sign in to continue.') ?></p>
    <?php endif; ?>

    <?php if (isset($_GET['reset']) && $_GET['reset'] === '1'): ?>
      <p class="auth-success" role="status">✓ <?= esc('Your password has been updated. Please sign in with your new password.') ?></p>
    <?php endif; ?>

    <form id="loginForm" novalidate autocomplete="on">
      <div class="field">
        <label for="loginId">Email or username</label>
        <div class="input-wrap">
          <input class="input" type="text" id="loginId" name="identifier"
                 value="<?= esc($prefill) ?>" placeholder="name@barangay.ph" autofocus>
        </div>
        <p class="field-error"></p>
      </div>

      <div class="field">
        <label for="loginPass">Password</label>
        <div class="input-wrap" style="display:flex;gap:0">
          <input class="input" type="password" id="loginPass" name="password"
                 placeholder="••••••••" style="border-right:0;border-top-right-radius:0;border-bottom-right-radius:0">
          <button type="button"
                  onclick="toggleShow('loginPass', this)"
                  style="border:1px solid var(--hairline);border-left:0;border-top-right-radius:10px;border-bottom-right-radius:10px;background:var(--white);padding:0 16px;font-size:12px;font-weight:600;color:var(--ink-2)">Show</button>
        </div>
        <p class="field-error"></p>
        <div style="margin-top:10px;text-align:right">
          <a class="forgot-link" href="forgot-password.php">Forgot password?</a>
        </div>
      </div>

      <div class="auth-actions">
        <button type="submit" class="btn btn--primary">Sign in</button>
      </div>
    </form>

    <div class="auth-or">or</div>

    <p class="auth-foot">
      New to Kolekta?
      <a href="register.php">Register your household</a>
    </p>

    <p class="auth-foot admin-hint" style="margin-top:18px">
      By signing in you agree to the
      <a href="legal/terms.php" style="color:var(--brass)">Terms of Service</a>
      and acknowledge the
      <a href="legal/privacy.php" style="color:var(--brass)">Privacy Policy</a>.
    </p>
    <p class="admin-hint" style="margin-top:8px">Barangay staff sign in with their assigned account.</p>
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