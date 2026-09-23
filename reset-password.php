<?php
/**
 * Kolekta — set a new password via an emailed reset link.
 */

declare(strict_types=1);

require_once __DIR__ . '/inc/page.php';

page_guest_only();

$token = trim((string)($_GET['token'] ?? ''));
$email = mb_strtolower(trim((string)($_GET['email'] ?? '')));
$valid = false;

if (preg_match('/^[a-f0-9]{64}$/', $token) && $email !== '') {
    $stmt = db()->prepare(
        'SELECT pr.user_id, u.email AS account
           FROM password_resets pr
           JOIN users u ON u.id = pr.user_id
          WHERE pr.token_hash = ? AND pr.expires_at > NOW()'
    );
    $stmt->execute([hash('sha256', $token)]);
    $row = $stmt->fetch();
    if ($row && mb_strtolower((string)$row['account']) === $email) {
        $valid = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $valid ? 'Choose a new password' : 'Invalid reset link' ?> — Kolekta</title>
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

    <?php if ($valid): ?>
      <h1 class="auth-title">Choose a new password</h1>
      <p class="auth-sub">Set a new password for your Kolekta account. Keep it somewhere safe.</p>

      <form id="resetForm" novalidate autocomplete="off">
        <input type="hidden" id="rpToken" value="<?= esc($token) ?>">
        <input type="hidden" id="rpEmail" value="<?= esc($email) ?>">

        <div class="field">
          <label for="rpPass">New password</label>
          <div class="input-wrap" style="display:flex;gap:0">
            <input class="input" type="password" id="rpPass" maxlength="72"
                   style="border-right:0;border-top-right-radius:0;border-bottom-right-radius:0">
            <button type="button" onclick="toggleShow('rpPass', this)"
                    style="border:1px solid var(--hairline);border-left:0;border-top-right-radius:10px;border-bottom-right-radius:10px;background:var(--white);padding:0 14px;font-size:12px;font-weight:600;color:var(--ink-2)">Show</button>
          </div>
          <div class="pass-strength" id="rpPassStrength"><i></i></div>
          <ul class="pass-rules" id="rpPassRules">
            <li data-rule="len">At least 8 characters</li>
            <li data-rule="num">Contains a number</li>
            <li data-rule="space">No spaces</li>
            <li data-rule="sym">No special symbols</li>
          </ul>
          <p class="field-error"></p>
        </div>

        <div class="field">
          <label for="rpConfirm">Confirm new password</label>
          <input class="input" type="password" id="rpConfirm" maxlength="72">
          <p class="field-error"></p>
        </div>

        <div class="auth-actions">
          <button type="submit" class="btn btn--primary">Set new password</button>
        </div>
      </form>
    <?php else: ?>
      <h1 class="auth-title">Invalid reset link</h1>
      <p class="auth-sub">This password reset link is invalid or has expired.</p>

      <p class="auth-note">Reset links are single-use and expire one hour after they're sent. Please request a fresh link and try again.</p>

      <div class="auth-actions">
        <a class="btn btn--primary" href="forgot-password.php">Request a new link</a>
      </div>
    <?php endif; ?>

    <p class="auth-foot" style="margin-top:20px">
      Already have your password?
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