<?php
/**
 * Kolekta — dispatch alerts.
 *   GET  latest       active dispatch for a zone (resident view)
 *   POST trigger      admin blasts "truck inbound" to a zone
 *   GET  list         admin — alert history (filters: zone_id, status)
 *   POST update       admin — edit a dispatch (message / waste type)
 *   POST set_status   admin — cancel / archive / re-activate an alert
 *
 *   status: active -> cancelled / archived
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$action = $_GET['action'] ?? '';

if ($action === 'latest') {
    $user = current_user();
    $zoneId = (int)($user['zone_id'] ?? 0);

    $stmt = db()->prepare(
        'SELECT d.id, d.waste_type, d.message, d.status, d.dispatched_at,
                u.name AS triggered_by_name
           FROM dispatches d
           JOIN users u ON u.id = d.triggered_by
          WHERE d.zone_id = ? AND d.status = ?
          ORDER BY d.dispatched_at DESC
          LIMIT 1'
    );
    $stmt->execute([$zoneId, 'active']);
    $latest = $stmt->fetch();

    if ($latest) {
        $latest['waste_label'] = waste_label($latest['waste_type']);
    }

    ok(['latest' => $latest]);
}

if ($action === 'trigger') {
    require_csrf();
    $admin = require_admin();
    $in = input();

    $zoneId    = (int)($in['zone_id'] ?? 0);
    $wasteType = trim((string)($in['waste_type'] ?? ''));
    $message   = trim((string)($in['message'] ?? ''));

    $zone = db()->prepare('SELECT id, name, code FROM zones WHERE id = ?');
    $zone->execute([$zoneId]);
    $zoneRow = $zone->fetch();
    if (!$zoneRow) {
        fail('Select a valid purok / zone.');
    }

    if ($wasteType !== '' && !waste_type_exists($wasteType, true)) {
        fail('Invalid waste type selected.');
    }
    if (mb_strlen($message) > 300) {
        fail('Keep the dispatch message to 300 characters or fewer.');
    }

    $stmt = db()->prepare(
        'INSERT INTO dispatches (zone_id, waste_type, message, triggered_by)
         VALUES (?, ?, ?, ?)'
    );
    $stmt->execute([$zoneId, $wasteType !== '' ? $wasteType : null, $message !== '' ? $message : null, (int)$admin['id']]);
    $dispatchId = (int)db()->lastInsertId();

    $title = 'Collection underway — ' . $zoneRow['name'];
    $body  = $message !== ''
        ? $message
        : ($wasteType !== ''
            ? 'The collection team is now servicing ' . $zoneRow['name'] .
              '. Please bring out your ' . strtolower(waste_label($wasteType)) . ' waste.'
            : 'The collection team is now servicing ' . $zoneRow['name'] . '. Please bring out your sorted waste.');

    $notif = db()->prepare(
        'INSERT INTO notifications (user_id, kind, title, body, link)
         SELECT id, ?, ?, ?, ?
           FROM users
          WHERE role = ? AND zone_id = ?'
    );
    $notif->execute(['dispatch', $title, $body, 'user.php', 'resident', $zoneId]);

    ok([
        'dispatch' => [
            'id'            => $dispatchId,
            'zone'          => $zoneRow['name'],
            'zone_code'     => $zoneRow['code'],
            'waste_label'   => waste_label($wasteType),
            'status'        => 'active',
            'dispatched_at' => date('Y-m-d H:i:s'),
        ],
    ], 201);
}

if ($action === 'list') {
    require_admin();

    $zoneId  = (int)($_GET['zone_id'] ?? 0);
    $status  = trim((string)($_GET['status'] ?? ''));

    $sql = 'SELECT d.id, d.zone_id, d.waste_type, d.message, d.status, d.dispatched_at,
                   z.name AS zone_name, z.code AS zone_code,
                   u.name AS triggered_by_name
              FROM dispatches d
              JOIN zones z ON z.id = d.zone_id
              JOIN users u ON u.id = d.triggered_by
             WHERE 1 = 1';
    $args = [];
    if ($zoneId > 0) {
        $sql .= ' AND d.zone_id = ?';
        $args[] = $zoneId;
    }
    if (in_array($status, ['active', 'cancelled', 'archived'], true)) {
        $sql .= ' AND d.status = ?';
        $args[] = $status;
    }
    $sql .= ' ORDER BY d.dispatched_at DESC LIMIT 100';

    $stmt = db()->prepare($sql);
    $stmt->execute($args);
    $rows = $stmt->fetchAll();

    foreach ($rows as &$row) {
        $row['waste_label'] = waste_label($row['waste_type']);
    }
    unset($row);

    ok($rows);
}

if ($action === 'update') {
    require_csrf();
    require_admin();
    $in = input();

    $id        = (int)($in['id'] ?? 0);
    $wasteType = trim((string)($in['waste_type'] ?? ''));
    $message   = trim((string)($in['message'] ?? ''));
    $zoneId    = (int)($in['zone_id'] ?? 0);

    $cur = db()->prepare('SELECT id FROM dispatches WHERE id = ?');
    $cur->execute([$id]);
    if (!$cur->fetch()) {
        fail('Dispatch not found.', 404);
    }
    if ($zoneId > 0) {
        $z = db()->prepare('SELECT id FROM zones WHERE id = ?');
        $z->execute([$zoneId]);
        if (!$z->fetch()) {
            fail('Select a valid purok.');
        }
    }
    if ($wasteType !== '' && !waste_type_exists($wasteType, true)) {
        fail('Invalid waste type selected.');
    }
    if (mb_strlen($message) > 300) {
        fail('Keep the dispatch message to 300 characters or fewer.');
    }

    db()->prepare(
        'UPDATE dispatches
            SET zone_id = COALESCE(?, zone_id),
                waste_type = CASE WHEN ? = \'\' THEN waste_type ELSE ? END,
                message = CASE WHEN ? = \'\' THEN message ELSE ? END
          WHERE id = ?'
    )->execute([
        $zoneId > 0 ? $zoneId : null,
        $wasteType, $wasteType,
        $message, $message,
        $id,
    ]);

    ok(['message' => 'Dispatch alert updated.', 'id' => $id]);
}

if ($action === 'set_status') {
    require_csrf();
    $admin = require_admin();
    $in = input();

    $id     = (int)($in['id'] ?? 0);
    $status = trim((string)($in['status'] ?? ''));

    if (!in_array($status, ['active', 'cancelled', 'archived'], true)) {
        fail('Invalid dispatch status.');
    }

    $cur = db()->prepare(
        'SELECT d.id, d.status, d.zone_id, z.name AS zone_name
           FROM dispatches d JOIN zones z ON z.id = d.zone_id
          WHERE d.id = ?'
    );
    $cur->execute([$id]);
    $row = $cur->fetch();
    if (!$row) {
        fail('Dispatch not found.', 404);
    }

    db()->prepare('UPDATE dispatches SET status = ? WHERE id = ?')
       ->execute([$status, $id]);

    // Residents should know when a scheduled pickup is cancelled.
    if ($status === 'cancelled') {
        $title = 'Collection update — ' . $row['zone_name'];
        $body  = 'The scheduled collection for ' . $row['zone_name'] .
                 ' has been cancelled. A new schedule will be posted.';
        db()->prepare(
            'INSERT INTO notifications (user_id, kind, title, body, link)
             SELECT id, ?, ?, ?, ?
               FROM users
              WHERE role = ? AND zone_id = ?'
        )->execute(['announcement', $title, $body, 'user.php', 'resident', (int)$row['zone_id']]);
    }

    ok(['message' => 'Dispatch alert ' . $status . '.', 'id' => $id, 'status' => $status]);
}

fail('Unknown dispatch action.', 404);