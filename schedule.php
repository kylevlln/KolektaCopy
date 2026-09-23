<?php
/**
 * Kolekta — collection schedules.
 *   GET             (no action) resident's weekly schedule for their zone
 *   GET  list       admin — all schedules (filters: zone_id, waste_type, weekday, is_active)
 *   POST create     admin — add a schedule
 *   POST update     admin — edit a schedule
 *   POST toggle     admin — activate / deactivate
 *   POST delete     admin — remove a schedule
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$action = $_GET['action'] ?? '';

function schedule_validate_input(array $in): array
{
    $fields['zone_id']    = (int)($in['zone_id'] ?? 0);
    $fields['weekday']    = (int)($in['weekday'] ?? 0);
    $fields['waste_type'] = trim((string)($in['waste_type'] ?? ''));
    $fields['window_start'] = trim((string)($in['window_start'] ?? ''));
    $fields['window_end']   = trim((string)($in['window_end'] ?? ''));
    $fields['is_active']  = !empty($in['is_active']) ? 1 : 0;

    $z = db()->prepare('SELECT id FROM zones WHERE id = ?');
    $z->execute([$fields['zone_id']]);
    if (!$z->fetch()) {
        fail('Select a valid purok.');
    }
    if ($fields['weekday'] < 1 || $fields['weekday'] > 7) {
        fail('Choose a valid day (Monday to Sunday).');
    }
    if (!waste_type_exists($fields['waste_type'], true)) {
        fail('Select a valid waste type.');
    }
    foreach (['window_start', 'window_end'] as $w) {
        if ($fields[$w] !== '' && !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $fields[$w])) {
            fail('Collection times must look like 05:00.');
        }
    }
    if (($fields['window_start'] === '') !== ($fields['window_end'] === '')) {
        fail('Provide both a start and an end time (or leave both empty).');
    }
    return $fields;
}

if ($action === '') {
    /* ================= resident view ================= */
    $user = current_user();

    $zoneId = (int)($user['zone_id'] ?? 0);
    $scope  = $zoneId > 0 ? $zoneId : 0;

    $week  = weekday_labels();
    $short = week_short();

    $schedule = [];
    $stmt = db()->prepare(
        'SELECT weekday, waste_type, window_start, window_end
           FROM schedules
          WHERE zone_id = ? AND is_active = 1
          ORDER BY weekday'
    );
    $stmt->execute([$scope]);
    while ($row = $stmt->fetch()) {
        $schedule[] = $row;
    }

    /* Aggregate into a 7-day grid ---------------------------------------- */
    $grid = [];
    foreach ($week as $d => $label) {
        $grid[$d] = [
            'weekday'     => $d,
            'label'       => $label,
            'short'       => $short[$d],
            'waste_types' => [],
        ];
    }
    foreach ($schedule as $s) {
        $grid[(int)$s['weekday']]['waste_types'][] = [
            'code'         => $s['waste_type'],
            'label'        => waste_label($s['waste_type']),
            'window_start' => $s['window_start'],
            'window_end'   => $s['window_end'],
        ];
    }

    /* Determine the next scheduled collection day ------------------------ */
    $today = (int)date('N'); // 1 = Monday ... 7 = Sunday
    $next  = null;
    for ($offset = 0; $offset <= 7; $offset++) {
        $day = ($today + $offset) > 7 ? (($today + $offset) % 7) : ($today + $offset);
        if ($day === 0) {
            $day = 7;
        }
        if (!empty($grid[$day]['waste_types'])) {
            $dateDay = date('Y-m-d', strtotime('+' . $offset . ' day'));
            $next    = [
                'weekday'    => $grid[$day]['label'],
                'date'       => $dateDay,
                'in_days'    => $offset,
                'waste_types' => $grid[$day]['waste_types'],
            ];
            break;
        }
    }

    ok([
        'zone'   => ['id' => $zoneId, 'name' => $user['zone_name'] ?? null, 'code' => $user['zone_code'] ?? null],
        'grid'   => array_values($grid),
        'next'   => $next,
        'waste_types' => waste_types_rows(true),
    ]);
}

/* ================= admin actions ================= */
require_csrf();
require_admin();
$in = input();

if ($action === 'list') {
    $zoneId   = (int)($_GET['zone_id'] ?? 0);
    $wasteType = trim((string)($_GET['waste_type'] ?? ''));
    $weekday  = (int)($_GET['weekday'] ?? 0);
    $status   = trim((string)($_GET['status'] ?? ''));

    $sql = 'SELECT s.id, s.zone_id, s.weekday, s.waste_type, s.window_start, s.window_end,
                   s.is_active, z.name AS zone_name, z.code AS zone_code
              FROM schedules s
              JOIN zones z ON z.id = s.zone_id
             WHERE 1 = 1';
    $args = [];
    if ($zoneId > 0) {
        $sql .= ' AND s.zone_id = ?';
        $args[] = $zoneId;
    }
    if ($wasteType !== '') {
        $sql .= ' AND s.waste_type = ?';
        $args[] = $wasteType;
    }
    if ($weekday > 0) {
        $sql .= ' AND s.weekday = ?';
        $args[] = $weekday;
    }
    if ($status === 'active' || $status === 'inactive') {
        $sql .= ' AND s.is_active = ?';
        $args[] = $status === 'active' ? 1 : 0;
    }
    $sql .= ' ORDER BY s.weekday, s.waste_type, z.name';

    $stmt = db()->prepare($sql);
    $stmt->execute($args);
    $rows = $stmt->fetchAll();

    $week = weekday_labels();
    foreach ($rows as &$row) {
        $row['day_label']   = $week[(int)$row['weekday']];
        $row['waste_label'] = waste_label($row['waste_type']);
        $row['is_active']   = (int)$row['is_active'] === 1;
    }
    unset($row);

    ok($rows);
}

if ($action === 'create') {
    $f = schedule_validate_input($in);

    $dup = db()->prepare('SELECT id FROM schedules WHERE zone_id = ? AND weekday = ? AND waste_type = ?');
    $dup->execute([$f['zone_id'], $f['weekday'], $f['waste_type']]);
    if ($dup->fetch()) {
        fail('A schedule for that purok, day and waste type already exists.');
    }

    $stmt = db()->prepare(
        'INSERT INTO schedules (zone_id, weekday, waste_type, window_start, window_end, is_active)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $f['zone_id'],
        $f['weekday'],
        $f['waste_type'],
        $f['window_start'] !== '' ? $f['window_start'] : null,
        $f['window_end'] !== '' ? $f['window_end'] : null,
        $f['is_active'],
    ]);

    ok(['message' => 'Schedule created successfully.', 'id' => (int)db()->lastInsertId()], 201);
}

if ($action === 'update') {
    $id = (int)($in['id'] ?? 0);
    if ($id < 1) {
        fail('Missing schedule.');
    }
    $f = schedule_validate_input($in);

    $dup = db()->prepare('SELECT id FROM schedules WHERE zone_id = ? AND weekday = ? AND waste_type = ? AND id <> ?');
    $dup->execute([$f['zone_id'], $f['weekday'], $f['waste_type'], $id]);
    if ($dup->fetch()) {
        fail('Another schedule already uses that purok, day and waste type.');
    }

    db()->prepare(
        'UPDATE schedules
            SET zone_id = ?, weekday = ?, waste_type = ?, window_start = ?, window_end = ?, is_active = ?
          WHERE id = ?'
    )->execute([
        $f['zone_id'],
        $f['weekday'],
        $f['waste_type'],
        $f['window_start'] !== '' ? $f['window_start'] : null,
        $f['window_end'] !== '' ? $f['window_end'] : null,
        $f['is_active'],
        $id,
    ]);

    ok(['message' => 'Schedule updated successfully.', 'id' => $id]);
}

if ($action === 'toggle') {
    $id = (int)($in['id'] ?? 0);
    $stmt = db()->prepare('SELECT id, is_active FROM schedules WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) {
        fail('Schedule not found.', 404);
    }

    $next = $row['is_active'] ? 0 : 1;
    db()->prepare('UPDATE schedules SET is_active = ? WHERE id = ?')
       ->execute([$next, $id]);

    ok(['message' => $next ? 'Schedule activated.' : 'Schedule deactivated.', 'is_active' => $next === 1]);
}

if ($action === 'delete') {
    $id = (int)($in['id'] ?? 0);
    $stmt = db()->prepare('SELECT id FROM schedules WHERE id = ?');
    $stmt->execute([$id]);
    if (!$stmt->fetch()) {
        fail('Schedule not found.', 404);
    }
    db()->prepare('DELETE FROM schedules WHERE id = ?')->execute([$id]);
    ok(['message' => 'Schedule deleted.', 'id' => $id]);
}

fail('Unknown schedule action.', 404);