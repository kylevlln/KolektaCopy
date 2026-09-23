<?php
/**
 * Kolekta — waste type management.
 *   GET         list waste types (admins see all; residents see active only)
 *   POST create admin adds a waste type (code is immutable)
 *   POST update admin edits the display name / order
 *   POST toggle admin activates / deactivates (existing records keep the code)
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$action = $_GET['action'] ?? '';

if ($action === '') {
    $user = current_user();

    if ($user['role'] === 'admin') {
        $rows = waste_types_rows(false);
    } else {
        $rows = waste_types_rows(true);
    }
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

function waste_type_validate(array $in): array
{
    $code = strtolower(trim((string)($in['code'] ?? '')));
    $name = trim((string)($in['name'] ?? ''));
    $sort = (int)($in['sort_order'] ?? 0);

    if (!preg_match('/^[a-z][a-z0-9_]{1,31}$/', $code)) {
        fail('Use a short lowercase code (letters, numbers, underscores; no spaces).');
    }
    if (mb_strlen($name) < 2 || mb_strlen($name) > 60) {
        fail('Please provide a display name (2–60 characters).');
    }
    return ['code' => $code, 'name' => $name, 'sort_order' => max(0, $sort)];
}

if ($action === 'create') {
    $f = waste_type_validate($in);

    $dup = db()->prepare('SELECT id FROM waste_types WHERE code = ?');
    $dup->execute([$f['code']]);
    if ($dup->fetch()) {
        fail('A waste type with that code already exists.');
    }

    $stmt = db()->prepare(
        'INSERT INTO waste_types (code, name, is_active, sort_order) VALUES (?, ?, 1, ?)'
    );
    $stmt->execute([$f['code'], $f['name'], $f['sort_order']]);

    ok(['message' => 'Waste type created successfully.', 'id' => (int)db()->lastInsertId()], 201);
}

if ($action === 'update') {
    $id = (int)($in['id'] ?? 0);
    $cur = db()->prepare('SELECT id FROM waste_types WHERE id = ?');
    $cur->execute([$id]);
    if (!$cur->fetch()) {
        fail('Waste type not found.', 404);
    }

    // Only the display name and sort order change; the code stays stable
    // because schedules, dispatches and reports reference it.
    $name = trim((string)($in['name'] ?? ''));
    $sort = (int)($in['sort_order'] ?? 0);
    if (mb_strlen($name) < 2 || mb_strlen($name) > 60) {
        fail('Please provide a display name (2–60 characters).');
    }

    db()->prepare('UPDATE waste_types SET name = ?, sort_order = ? WHERE id = ?')
       ->execute([$name, max(0, $sort), $id]);

    ok(['message' => 'Waste type updated successfully.', 'id' => $id]);
}

if ($action === 'toggle') {
    $id = (int)($in['id'] ?? 0);
    $cur = db()->prepare('SELECT id, is_active, code FROM waste_types WHERE id = ?');
    $cur->execute([$id]);
    $row = $cur->fetch();
    if (!$row) {
        fail('Waste type not found.', 404);
    }

    $next = $row['is_active'] ? 0 : 1;
    db()->prepare('UPDATE waste_types SET is_active = ? WHERE id = ?')->execute([$next, $id]);

    $msg = $next
        ? 'Waste type activated.'
        : 'Waste type deactivated. Existing schedules and reports are kept.';
    ok(['message' => $msg, 'id' => $id, 'is_active' => $next === 1]);
}

fail('Unknown waste type action.', 404);