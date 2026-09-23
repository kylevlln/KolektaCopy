<?php
/**
 * Kolekta — authentication: register, login, logout, session.
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$action = $_GET['action'] ?? '';

switch ($action) {

    /* ------------------------------------------------------------ */
    case 'register':
        require_csrf();
        $in = input();

        $name     = trim((string)($in['name'] ?? ''));
        $email    = mb_strtolower(trim((string)($in['email'] ?? '')));
        $username = trim((string)($in['username'] ?? ''));
        $phone    = trim((string)($in['phone'] ?? ''));
        $household = trim((string)($in['household'] ?? ''));
        $zoneId   = (int)($in['zone_id'] ?? 0);
        $password = (string)($in['password'] ?? '');

        if (empty($in['accept'])) {
            fail('Please accept the Privacy Policy and Terms of Service to create an account.');
        }
        if (mb_strlen($name) < 3) {
            fail('Please provide your full name.');
        }
        if (mb_strlen($name) > 90) {
            fail('Please keep your name to 90 characters or fewer.');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            fail('Please provide a valid email address.');
        }
        if (mb_strlen($email) > 160) {
            fail('Please keep your email to 160 characters or fewer.');
        }
        if ($username !== '' && mb_strlen($username) > 60) {
            fail('Please keep your username to 60 characters or fewer.');
        }
        if ($phone !== '' && (mb_strlen($phone) > 24 || !preg_match('/^[0-9+\-(). ]{7,24}$/', $phone))) {
            fail('Please enter a valid mobile number (digits, spaces, +, - and parentheses only).');
        }
        if (mb_strlen($household) > 120) {
            fail('Please keep your household name to 120 characters or fewer.');
        }
        if (mb_strlen($password) < 8) {
            fail('Password must be at least 8 characters.');
        }
        if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
            fail('Password must contain both letters and numbers.');
        }
        if (!preg_match('/^[A-Za-z0-9]+$/', $password)) {
            fail('Password must contain only letters and numbers (no special symbols).');
        }
        if ($zoneId < 1) {
            fail('Please choose your purok / zone.');
        }

        $zone = db()->prepare('SELECT id FROM zones WHERE id = ?');
        $zone->execute([$zoneId]);
        if (!$zone->fetch()) {
            fail('The selected zone does not exist.');
        }

        if ($username !== '') {
            $u = db()->prepare('SELECT id FROM users WHERE username = ?');
            $u->execute([$username]);
            if ($u->fetch()) {
                fail('That username is already taken.');
            }
        }

        $chk = db()->prepare('SELECT id FROM users WHERE email = ?');
        $chk->execute([$email]);
        if ($chk->fetch()) {
            fail('An account with that email already exists.');
        }

        $stmt = db()->prepare(
            'INSERT INTO users (name, email, username, password_hash, role, phone, household, zone_id)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $name,
            $email,
            $username !== '' ? $username : null,
            password_hash($password, PASSWORD_DEFAULT),
            'resident',
            $phone !== '' ? $phone : null,
            $household !== '' ? $household : null,
            $zoneId,
        ]);

        $newId = (int)db()->lastInsertId();

        // Welcome notification.
        db()->prepare(
            'INSERT INTO notifications (user_id, kind, title, body) VALUES (?, ?, ?, ?)'
        )->execute([
            $newId,
            'announcement',
            'Welcome to ' . APP_NAME,
            'Your household is registered under your zone. You will receive dispatch alerts when collection begins.',
        ]);

        // No automatic sign-in: the new resident is sent to the Sign In page
        // so they can sign in manually with the credentials they chose.
        ok([
            'redirect' => 'login.php?registered=1',
            'csrf'     => csrf_token(),
        ], 201);

    /* ------------------------------------------------------------ */
    case 'login':
        require_csrf();
        $in = input();

        $identifier = trim((string)($in['identifier'] ?? ''));
        $password   = (string)($in['password'] ?? '');

        if ($identifier === '' || $password === '') {
            fail('Enter your email or username and your password.');
        }

        $stmt = db()->prepare('SELECT * FROM users WHERE email = ? OR username = ?');
        $stmt->execute([$identifier, $identifier]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, (string)$user['password_hash'])) {
            fail('Incorrect credentials. Please check your details.', 401);
        }

        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['csrf']    = bin2hex(random_bytes(16));

        ok([
            'redirect' => $user['role'] === 'admin' ? 'admin.php' : 'user.php',
            'role'     => $user['role'],
            'name'     => $user['name'],
            'csrf'     => csrf_token(),
        ]);

    /* ------------------------------------------------------------ */
    case 'logout':
        require_csrf();
        $_SESSION = [];
        session_destroy();
        ok(['redirect' => 'index.php']);

    /* ------------------------------------------------------------ */
    // Forgot password: create a one-time, expiring reset token and email it.
    case 'forgot':
        require_csrf();
        $in   = input();
        $email = mb_strtolower(trim((string)($in['email'] ?? '')));

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            fail('Please enter a valid email address.');
        }

        // Self-healing for existing installs that pre-date the table.
        db()->exec('CREATE TABLE IF NOT EXISTS password_resets (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id INT UNSIGNED NOT NULL,
            token_hash CHAR(64) NOT NULL,
            expires_at DATETIME NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_pr_user (user_id),
            KEY idx_pr_token (token_hash)
        ) ENGINE = InnoDB');

        $stmt = db()->prepare('SELECT id, email FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $account = $stmt->fetch();

        // Always answer identically so the endpoint can't be used to
        // enumerate which emails have accounts.
        if ($account) {
            db()->prepare('DELETE FROM password_resets WHERE user_id = ?')->execute([(int)$account['id']]);

            $token = bin2hex(random_bytes(32));
            db()->prepare('INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?, ?, ?)')
                ->execute([(int)$account['id'], hash('sha256', $token), date('Y-m-d H:i:s', time() + 3600)]);

            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host   = preg_replace('/[^A-Za-z0-9.:\-\[\]]/', '', (string)($_SERVER['HTTP_HOST'] ?? 'localhost'));
            $appDir = rtrim(dirname(str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '')), '/');
            $appDir = rtrim(str_replace('/api', '', $appDir), '/');
            $resetUrl = $scheme . '://' . ($host ?: 'localhost') . $appDir . '/reset-password.php'
                      . '?token=' . urlencode($token) . '&email=' . urlencode($account['email']);

            $subject = '[' . APP_NAME . '] Reset your password';
            $body    = "Hello,\n\n"
                     . "We received a request to reset your " . APP_NAME . " password.\n\n"
                     . "Open this link within the next hour to choose a new password:\n\n"
                     . $resetUrl . "\n\n"
                     . "If you did not request this, you can ignore this email.\n";
            $sent = @mail($account['email'], $subject, $body, 'From: no-reply@kolekta.local');

            // Local development (XAMPP) usually has no mailer, so surface the
            // link once on the confirmation page instead of silently dropping it.
            if (!$sent) {
                $_SESSION['dev_reset_url'] = $resetUrl;
            }
        }

        ok(['redirect' => 'forgot-sent.php']);

    /* ------------------------------------------------------------ */
    // Reset password: exchange a valid token for a new password.
    case 'reset':
        require_csrf();
        $in   = input();
        $token = trim((string)($in['token'] ?? ''));
        $email = mb_strtolower(trim((string)($in['email'] ?? '')));
        $pass  = (string)($in['password'] ?? '');

        if (mb_strlen($pass) < 8) {
            fail('Password must be at least 8 characters.');
        }
        if (!preg_match('/[A-Za-z]/', $pass) || !preg_match('/[0-9]/', $pass)) {
            fail('Password must contain both letters and numbers.');
        }
        if (!preg_match('/^[A-Za-z0-9]+$/', $pass)) {
            fail('Password must contain only letters and numbers (no special symbols).');
        }
        if (!preg_match('/^[a-f0-9]{64}$/', $token) || $email === '') {
            fail('This password reset link is invalid or has expired. Please request a new one.');
        }

        $stmt = db()->prepare(
            'SELECT pr.user_id, u.email AS account
               FROM password_resets pr
               JOIN users u ON u.id = pr.user_id
              WHERE pr.token_hash = ? AND pr.expires_at > NOW()'
        );
        $stmt->execute([hash('sha256', $token)]);
        $row = $stmt->fetch();

        if (!$row || mb_strtolower((string)$row['account']) !== $email) {
            fail('This password reset link is invalid or has expired. Please request a new one.');
        }

        db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
            ->execute([password_hash($pass, PASSWORD_DEFAULT), (int)$row['user_id']]);
        db()->prepare('DELETE FROM password_resets WHERE user_id = ?')->execute([(int)$row['user_id']]);

        ok(['redirect' => 'login.php?reset=1']);

    /* ------------------------------------------------------------ */
    case 'csrf':
        ok(['csrf' => csrf_token()]);

    /* ------------------------------------------------------------ */
    case 'me':
        ok(['user' => current_user(), 'csrf' => csrf_token()]);

    /* ------------------------------------------------------------ */
    default:
        fail('Unknown authentication action.', 404);
}