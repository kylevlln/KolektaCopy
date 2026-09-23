<?php
/**
 * Kolekta — resident self-service profile.
 *   GET  me        current profile (from the session only)
 *   POST update    edit name/email/username/phone/household/zone
 *   POST password  change the account password
 *
 * A resident can only ever modify their OWN account: the user id always comes
 * from the authenticated session, never from a request parameter.
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$action = $_GET['action'] ?? '';

switch ($action) {

    /* ------------------------------------------------------------ */
    case 'me':
        $user = current_user();
        ok([
            'id'         => (int)$user['id'],
            'name'       => $user['name'],
            'email'      => $user['email'],
            'username'   => $user['username'],
            'phone'      => $user['phone'],
            'household'  => $user['household'],
            'zone_id'    => $user['zone_id'] !== null ? (int)$user['zone_id'] : null,
            'zone_name'  => $user['zone_name'],
            'zone_code'  => $user['zone_code'],
            'created_at' => $user['created_at'],
            'role'       => $user['role'],
        ]);

    case 'update':
        require_csrf();
        $user = current_user();

        $in        = input();
        $name      = trim((string)($in['name'] ?? ''));
        $email     = mb_strtolower(trim((string)($in['email'] ?? '')));
        $username  = trim((string)($in['username'] ?? ''));
        $phone     = trim((string)($in['phone'] ?? ''));
        $household = trim((string)($in['household'] ?? ''));
        $zoneId    = (int)($in['zone_id'] ?? 0);

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

        // Email must stay unique across accounts (and unchanged for this one).
        $chk = db()->prepare('SELECT id FROM users WHERE email = ? AND id <> ?');
        $chk->execute([$email, (int)$user['id']]);
        if ($chk->fetch()) {
            fail('Another account already uses that email.');
        }

        if ($username !== '') {
            $u = db()->prepare('SELECT id FROM users WHERE username = ? AND id <> ?');
            $u->execute([$username, (int)$user['id']]);
            if ($u->fetch()) {
                fail('That username is already taken.');
            }
        }

        // The purok must exist AND still be active.
        $zoneChanged = false;
        $newZone = (int)($user['zone_id'] ?? 0);
        if ($zoneId > 0 && $zoneId !== $newZone) {
            $z = db()->prepare('SELECT id, name FROM zones WHERE id = ? AND is_active = 1');
            $z->execute([$zoneId]);
            $zoneRow = $z->fetch();
            if (!$zoneRow) {
                fail('The selected purok does not exist or is not active.');
            }
            $newZone     = $zoneId;
            $zoneChanged = true;
        } elseif ($zoneId < 1) {
            fail('Please choose your purok / zone.');
        }

        $stmt = db()->prepare(
            'UPDATE users
                SET name = ?, email = ?, username = ?, phone = ?, household = ?, zone_id = ?
              WHERE id = ?'
        );
        $stmt->execute([
            $name,
            $email,
            $username !== '' ? $username : null,
            $phone !== '' ? $phone : null,
            $household !== '' ? $household : null,
            $newZone,
            (int)$user['id'],
        ]);

        // A changed purok re-points their schedule, alerts and notices — tell them.
        if ($zoneChanged) {
            $zoneName = $zoneRow['name'];
            db()->prepare(
                'INSERT INTO notifications (user_id, kind, title, body)
                 VALUES (?, ?, ?, ?)'
            )->execute([
                (int)$user['id'],
                'announcement',
                'Your purok was updated',
                'Your household is now registered under ' . $zoneName .
                '. Your collection schedule and dispatch alerts have been updated.',
            ]);
            ok([
                'message' => 'Your purok has been updated. Your collection schedule and alerts have also been updated.',
                'zone_changed' => true,
                'zone_name'    => $zoneName,
            ]);
        }

        ok(['message' => 'Profile updated successfully.', 'zone_changed' => false]);

    case 'password':
        require_csrf();
        $user = current_user();

        $in       = input();
        $current  = (string)($in['current_password'] ?? '');
        $newPass  = (string)($in['new_password'] ?? '');
        $confirm  = (string)($in['new_password_confirm'] ?? '');

        if ($current === '') {
            fail('Enter your current password.');
        }
        if (mb_strlen($newPass) < 8) {
            fail('Password must be at least 8 characters.');
        }
        if (!preg_match('/[A-Za-z]/', $newPass) || !preg_match('/[0-9]/', $newPass)) {
            fail('Password must contain both letters and numbers.');
        }
        if (!preg_match('/^[A-Za-z0-9]+$/', $newPass)) {
            fail('Password must contain only letters and numbers (no special symbols).');
        }
        if ($newPass !== $confirm) {
            fail('New passwords do not match.');
        }

        $stmt = db()->prepare('SELECT password_hash FROM users WHERE id = ?');
        $stmt->execute([(int)$user['id']]);
        $hash = (string)$stmt->fetchColumn();

        if ($hash === '' || !password_verify($current, $hash)) {
            fail('Your current password is incorrect.');
        }

        db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
           ->execute([password_hash($newPass, PASSWORD_DEFAULT), (int)$user['id']]);

        ok(['message' => 'Your password has been updated.', 'redirect' => 'user.php']);

    default:
        fail('Unknown profile action.', 404);
}