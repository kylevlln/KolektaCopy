<?php
/**
 * Kolekta — password reset link sent confirmation.
 */

declare(strict_types=1);

require_once __DIR__ . '/inc/page.php';

page_guest_only();

$devUrl = $_SESSION['dev_reset_url'] ?? '';
unset($_SESSION['dev_reset_url']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Check your inbox — Kolekta</title>
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

    <h1 class="auth-title">Check your inbox</h1>
    <p class="auth-sub">If an account exists for that email address, a password reset link has been sent to it.</p>

    <p class="form-note">
      The link is valid for one hour. Open it to choose a new password, then sign
      in with your new password.
    </p>

    <?php if ($devUrl !== ''): ?>
      <p class="dev-hint" role="note">
        <strong>Local testing — no mail server is configured.</strong>
        Use this one-time link to reset your password:
        <a href="<?= esc($devUrl) ?>"><?= esc($devUrl) ?></a>
      </p>
    <?php endif; ?>

    <div class="auth-or">or</div>

    <p class="auth-foot">
      <a href="login.php">← Back to Sign in</a>
    </p>
  </div>
</main>

<?php include __DIR__ . '/inc/auth_promo.php'; ?>

<?= cookie_notice() ?>
<script src="assets/js/app.js"></script>
<script src="assets/js/legal.js"></script>
</body>
</html>