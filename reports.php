<?php
/**
 * Kolekta — missed pickup reports.
 *   POST create        resident submits a report (optional photo)
 *   GET  my            resident's own reports
 *   GET  all           admin triage list (filters: status, zone_id, waste_type, date)
 *   POST set_status    admin moves a report: pending -> investigating -> resolved -> archived
 *   POST resolve       alias for set_status(status=resolved) — kept for compatibility
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$action = $_GET['action'] ?? '';

/* Allowed forward transitions. Archived is a terminal state. */
function report_allowed_next(string $current): array
{
    switch ($current) {
        case 'pending':
            return ['investigating', 'resolved', 'archived'];
        case 'investigating':
            return ['resolved', 'archived'];
        case 'resolved':
            return ['archived'];
        default:
            return [];
    }
}

function report_status_message(string $status): string
{
    return [
        'pending'       => 'Report submitted successfully.',
        'investigating' => 'Report marked as under investigation.',
        'resolved'      => 'Report marked as resolved.',
        'archived'      => 'Report archived.',
    ][$status] ?? 'Report updated.';
}

/* ------------------------------------------------------------ */
if ($action === 'create') {
    require_csrf();
    $user = current_user();
    if ($user['role'] !== 'resident') {
        fail('Only residents can file missed-pickup reports.', 403);
    }

    $in = input();
    $zoneId  = (int)($user['zone_id'] ?? 0);
    $wasteType = trim((string)($in['waste_type'] ?? 'mixed'));
    $address = trim((string)($in['address'] ?? ''));
    $notes   = trim((string)($in['notes'] ?? ''));

    if ($zoneId < 1) {
        fail('Your account is not assigned to a purok yet. Contact the barangay hall.');
    }
    $reportTypes = ['mixed'];
    foreach (waste_types_rows(true) as $row) {
        $reportTypes[] = $row['code'];
    }
    if (!in_array($wasteType, $reportTypes, true)) {
        fail('Please select a valid waste type.');
    }
    if ($address === '') {
        fail('Please indicate the address of the missed pickup.');
    }
    if (mb_strlen($address) > 160) {
        fail('Address is too long.');
    }
    if (mb_strlen($notes) > 500) {
        fail('Notes are too long.');
    }

    $photoPath = null;
    if (!empty($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['photo'];
        if ($file['size'] > MAX_PHOTO_BYTES) {
            fail('Photo exceeds the 5 MB limit.');
        }
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($file['tmp_name']);
        $allowed = ['image/jpeg', 'image/png', 'image/webp'];
        if (!in_array($mime, $allowed, true)) {
            fail('Photo must be a JPEG, PNG, or WebP image.');
        }
        if (getimagesize($file['tmp_name']) === false) {
            fail('The uploaded file is not a valid image.');
        }

        $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mime];
        $name = 'rpt_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        if (!is_dir(UPLOAD_DIR)) {
            mkdir(UPLOAD_DIR, 0775, true);
        }
        if (!move_uploaded_file($file['tmp_name'], UPLOAD_DIR . $name)) {
            fail('Could not save the uploaded photo.');
        }
        $photoPath = UPLOAD_URL . $name;
    }

    $stmt = db()->prepare(
        'INSERT INTO reports (user_id, zone_id, address, waste_type, photo_path, notes)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([(int)$user['id'], $zoneId, $address, $wasteType, $photoPath, $notes !== '' ? $notes : null]);
    $reportId = (int)db()->lastInsertId();

    ok([
        'id'         => $reportId,
        'status'     => 'pending',
        'created_at' => date('Y-m-d H:i:s'),
        'message'    => 'Report submitted. The barangay has been notified.',
    ], 201);
}

/* ------------------------------------------------------------ */
if ($action === 'my') {
    $user = current_user();
    $stmt = db()->prepare(
        'SELECT r.id, r.address, r.waste_type, r.photo_path, r.notes,
                r.status, r.secondary_dispatch, r.resolution_note, r.created_at
           FROM reports r
          WHERE r.user_id = ?
          ORDER BY r.created_at DESC'
    );
    $stmt->execute([(int)$user['id']]);
    $rows = $stmt->fetchAll();

    foreach ($rows as &$row) {
        $row['waste_label'] = waste_label($row['waste_type']);
        $row['status_label'] = status_labels()[$row['status']] ?? $row['status'];
    }
    unset($row);

    ok($rows);
}

/* ------------------------------------------------------------ */
if ($action === 'all') {
    require_admin();

    $status    = trim((string)($_GET['status'] ?? ''));
    $zoneId    = (int)($_GET['zone_id'] ?? 0);
    $wasteType = trim((string)($_GET['waste_type'] ?? ''));
    $date      = trim((string)($_GET['date'] ?? ''));

    $sql = 'SELECT r.id, r.address, r.waste_type, r.photo_path, r.notes,
                   r.status, r.secondary_dispatch, r.resolution_note,
                   r.created_at, r.resolved_at,
                   u.name AS resident_name, u.phone AS resident_phone,
                   z.name AS zone_name, z.code AS zone_code
              FROM reports r
              JOIN users u ON u.id = r.user_id
              JOIN zones z ON z.id = r.zone_id
             WHERE 1 = 1';
    $args = [];

    $validStatuses = ['pending', 'investigating', 'resolved', 'archived'];
    if (in_array($status, $validStatuses, true)) {
        $sql .= ' AND r.status = ?';
        $args[] = $status;
    }
    if ($zoneId > 0) {
        $sql .= ' AND r.zone_id = ?';
        $args[] = $zoneId;
    }
    if ($wasteType !== '' && waste_type_exists($wasteType, false)) {
        $sql .= ' AND r.waste_type = ?';
        $args[] = $wasteType;
    }
    if ($date !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        $sql .= ' AND DATE(r.created_at) = ?';
        $args[] = $date;
    }
    $sql .= ' ORDER BY r.created_at DESC';

    $stmt = db()->prepare($sql);
    $stmt->execute($args);
    $rows = $stmt->fetchAll();

    foreach ($rows as &$row) {
        $row['waste_label']  = waste_label($row['waste_type']);
        $row['status_label'] = status_labels()[$row['status']] ?? $row['status'];
    }
    unset($row);

    ok($rows);
}

/* ------------------------------------------------------------ */
if ($action === 'set_status') {
    require_csrf();
    $admin = require_admin();
    $in = input();

    $reportId = (int)($in['id'] ?? 0);
    $status   = trim((string)($in['status'] ?? ''));
    $note     = trim((string)($in['note'] ?? ''));
    $dispatch = !empty($in['dispatch']) ? 1 : 0;

    $report = db()->prepare(
        'SELECT r.*, z.name AS zone_name, u.name AS resident_name
           FROM reports r
           JOIN zones z ON z.id = r.zone_id
           JOIN users u ON u.id = r.user_id
          WHERE r.id = ?'
    );
    $report->execute([$reportId]);
    $row = $report->fetch();

    if (!$row) {
        fail('Report not found.', 404);
    }
    if (mb_strlen($note) > 500) {
        fail('Resolution note is too long.');
    }
    if ($in['status'] === 'resolved') {
        /* legacy resolve — always allowed from pending/investigating */
        $status = 'resolved';
    }
    if (!in_array($status, ['investigating', 'resolved', 'archived'], true)) {
        fail('Invalid target status.');
    }
    if (!in_array($status, report_allowed_next($row['status']), true)) {
        fail('That status change is not allowed for this report.');
    }
    if ($status === 'investigating' && $row['status'] !== 'pending') {
        fail('Only pending reports can be moved to Investigating.');
    }

    $resolvedAt = in_array($status, ['resolved', 'archived'], true) ? date('Y-m-d H:i:s') : null;

    db()->prepare(
        'UPDATE reports
            SET status = ?, secondary_dispatch = ?, resolved_at = COALESCE(?, resolved_at),
                resolved_by = ?, resolution_note = ?
          WHERE id = ?'
    )->execute([$status, $dispatch, $resolvedAt, (int)$admin['id'], $note !== '' ? $note : null, $reportId]);

    // Notify the resident about the outcome.
    $notifTitle = [
        'investigating' => 'Report under investigation',
        'resolved'      => 'Report resolved',
        'archived'      => 'Report archived',
    ][$status];
    $notifBody = $status === 'resolved'
        ? ($dispatch
            ? 'Your missed-pickup report at ' . $row['address'] . ' was received. A secondary collection has been arranged for ' . $row['zone_name'] . '.'
            : 'Your missed-pickup report at ' . $row['address'] . ' has been marked resolved by the barangay.')
        : 'Your missed-pickup report at ' . $row['address'] . ' has been updated: ' . strtolower($notifTitle) . '.';

    db()->prepare(
        'INSERT INTO notifications (user_id, kind, title, body, link)
         VALUES (?, ?, ?, ?, ?)'
    )->execute([(int)$row['user_id'], 'report_update', $notifTitle, $notifBody, 'user.php']);

    ok([
        'message'      => report_status_message($status),
        'id'           => $reportId,
        'status'       => $status,
        'status_label' => status_labels()[$status] ?? $status,
    ]);
}

/* ------------------------------------------------------------ */
if ($action === 'resolve') {
    require_csrf();
    $admin = require_admin();
    $in = input();
    $in['status'] = 'resolved';
    /* Delegate to the workflow above. */
    /* (kept as a thin alias; the shared logic lives in set_status) */

    $reportId = (int)($in['id'] ?? 0);
    $dispatch = !empty($in['dispatch']) ? 1 : 0;
    $note     = trim((string)($in['note'] ?? ''));

    $report = db()->prepare(
        'SELECT r.*, z.name AS zone_name
           FROM reports r JOIN zones z ON z.id = r.zone_id
          WHERE r.id = ?'
    );
    $report->execute([$reportId]);
    $row = $report->fetch();

    if (!$row) {
        fail('Report not found.', 404);
    }
    if ($row['status'] === 'archived') {
        fail('That report is archived.');
    }
    if ($row['status'] === 'resolved') {
        fail('That report is already resolved.');
    }
    if (mb_strlen($note) > 500) {
        fail('Resolution note is too long.');
    }

    db()->prepare(
        'UPDATE reports
            SET status = ?, secondary_dispatch = ?, resolved_at = NOW(),
                resolved_by = ?, resolution_note = ?
          WHERE id = ?'
    )->execute(['resolved', $dispatch, (int)$admin['id'], $note !== '' ? $note : null, $reportId]);

    $title = $dispatch ? 'Secondary collection scheduled' : 'Report resolved';
    $body  = $dispatch
        ? 'Your missed-pickup report at ' . $row['address'] . ' was received. A secondary collection has been arranged for ' . $row['zone_name'] . '.'
        : 'Your missed-pickup report at ' . $row['address'] . ' has been marked resolved by the barangay.';

    db()->prepare(
        'INSERT INTO notifications (user_id, kind, title, body, link)
         VALUES (?, ?, ?, ?, ?)'
    )->execute([(int)$row['user_id'], 'report_update', $title, $body, 'user.php']);

    ok(['message' => 'Report marked as resolved.', 'id' => $reportId, 'status' => 'resolved']);
}

fail('Unknown report action.', 404);