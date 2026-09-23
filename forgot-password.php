<?php
/**
 * Kolekta — request a password reset link.
 */

declare(strict_types=1);

require_once __DIR__ . '/inc/page.php';

page_guest_only();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Forgot password — Kolekta</title>
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
      <a class="back-btn" href="login.php" aria-label="Back to Sign in" title="Back to Sign in">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
      </a>
      <span class="auth-sg"><?= esc(BARANGAY_NAME) ?></span>
    </div>

    <h1 class="auth-title">Forgot password</h1>
    <p class="auth-sub">Enter the email you registered with and we'll send you a link to reset your password.</p>

    <form id="forgotForm" novalidate autocomplete="on">
      <div class="field">
        <label for="fpEmail">Email</label>
        <input class="input" type="email" id="fpEmail" maxlength="160" placeholder="name@email.com" autocomplete="email" autofocus>
        <p class="field-error"></p>
      </div>

      <div class="auth-actions">
        <button type="submit" class="btn btn--primary">Send reset link</button>
      </div>
    </form>

    <p class="form-note" style="margin-top:16px">
      The link is valid for one hour. If you don't see the email, check your spam
      folder — and make sure you entered the exact email you signed up with.
    </p>

    <div class="auth-or">or</div>

    <p class="auth-foot">
      Remembered your password?
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