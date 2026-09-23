<?php
/**
 * Kolekta — reference data: zones (puroks).
 *   GET            list zones (admins see all; residents see active only)
 *   POST create    admin adds a purok
 *   POST update    admin edits a purok
 *   POST toggle    admin activates / deactivates a purok (no hard deletes)
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$action = $_GET['action'] ?? '';

if ($action === '') {
    $user = current_user();

    $sql = 'SELECT z.id, z.name, z.code, z.description, z.is_active,
                   (SELECT COUNT(*) FROM users u WHERE u.zone_id = z.id AND u.role = \'resident\') AS resident_count
              FROM zones z';
    if ($user['role'] !== 'admin') {
        $sql .= ' WHERE z.is_active = 1';
    }
    $sql .= ' ORDER BY z.id';

    $rows = db()->query($sql)->fetchAll();
    foreach ($rows as &$row) {
        $row['is_active'] = (int)$row['is_active'] === 1;
    }
    unset($row);

    ok($rows);
}

/* ---------------- admin actions ---------------- */
require_csrf();
require_admin();
$in = input();

function zone_validate(array $in): array
{
    $name = trim((string)($in['name'] ?? ''));
    $code = strtoupper(trim((string)($in['code'] ?? '')));
    $desc = trim((string)($in['description'] ?? ''));

    if (mb_strlen($name) < 3 || mb_strlen($name) > 60) {
        fail('Please provide a purok name (3–60 characters).');
    }
    if (mb_strlen($code) < 1 || mb_strlen($code) > 12 || !preg_match('/^[A-Z0-9]+$/', $code)) {
        fail('Please provide a short code (letters and digits, e.g. P12).');
    }
    if (mb_strlen($desc) > 255) {
        fail('Please keep the description to 255 characters or fewer.');
    }
    return ['name' => $name, 'code' => $code, 'description' => $desc];
}

if ($action === 'create') {
    $f = zone_validate($in);

    $dup = db()->prepare('SELECT id FROM zones WHERE code = ?');
    $dup->execute([$f['code']]);
    if ($dup->fetch()) {
        fail('A purok with that code already exists.');
    }

    $stmt = db()->prepare('INSERT INTO zones (name, code, description, is_active) VALUES (?, ?, ?, 1)');
    $stmt->execute([$f['name'], $f['code'], $f['description'] !== '' ? $f['description'] : null]);

    ok(['message' => 'Purok created successfully.', 'id' => (int)db()->lastInsertId()], 201);
}

if ($action === 'update') {
    $id = (int)($in['id'] ?? 0);
    $cur = db()->prepare('SELECT id FROM zones WHERE id = ?');
    $cur->execute([$id]);
    if (!$cur->fetch()) {
        fail('Purok not found.', 404);
    }

    $f = zone_validate($in);

    $dup = db()->prepare('SELECT id FROM zones WHERE code = ? AND id <> ?');
    $dup->execute([$f['code'], $id]);
    if ($dup->fetch()) {
        fail('Another purok already uses that code.');
    }

    db()->prepare('UPDATE zones SET name = ?, code = ?, description = ? WHERE id = ?')
       ->execute([$f['name'], $f['code'], $f['description'] !== '' ? $f['description'] : null, $id]);

    ok(['message' => 'Purok updated successfully.', 'id' => $id]);
}

if ($action === 'toggle') {
    $id = (int)($in['id'] ?? 0);
    $cur = db()->prepare('SELECT id, is_active FROM zones WHERE id = ?');
    $cur->execute([$id]);
    $row = $cur->fetch();
    if (!$row) {
        fail('Purok not found.', 404);
    }

    $next = $row['is_active'] ? 0 : 1;
    db()->prepare('UPDATE zones SET is_active = ? WHERE id = ?')->execute([$next, $id]);

    $msg = $next ? 'Purok activated.' : 'Purok deactivated. Existing residents and records are kept.';
    ok(['message' => $msg, 'id' => $id, 'is_active' => $next === 1]);
}

fail('Unknown zone action.', 404);