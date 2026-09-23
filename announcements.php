<?php
/**
 * Kolekta — barangay / zone notices.
 *   GET  list           notices (scope + published/expiry rules depend on role)
 *   POST create         admin publishes a notice / cancellation
 *   POST update         admin edits a notice
 *   POST set_published  admin publishing / unpublish toggle
 *   POST delete         admin removes a notice
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$action = $_GET['action'] ?? '';

function notice_validate_input(array $in): array
{
    $title = trim((string)($in['title'] ?? ''));
    $body  = trim((string)($in['body'] ?? ''));
    $kind  = trim((string)($in['kind'] ?? 'notice'));

    if ($title === '' || $body === '') {
        fail('Notices need both a title and a message.');
    }
    if (mb_strlen($title) > 160) {
        fail('Please keep the notice title to 160 characters or fewer.');
    }
    if (mb_strlen($body) > 600) {
        fail('Please keep the notice message to 600 characters or fewer.');
    }
    if (!in_array($kind, ['notice', 'cancellation'], true)) {
        fail('Invalid notice type.');
    }

    $zoneRaw = $in['zone_id'] ?? 'all';
    $zoneId  = null;
    if ($zoneRaw !== '' && $zoneRaw !== null && $zoneRaw !== 'all') {
        $zoneId = (int)$zoneRaw;
        $z = db()->prepare('SELECT id FROM zones WHERE id = ?');
        $z->execute([$zoneId]);
        if (!$z->fetch()) {
            fail('The selected purok does not exist.');
        }
    }

    $isPublished = !empty($in['is_published']);
    $expires     = trim((string)($in['expires_at'] ?? ''));
    $expiresAt   = null;
    if ($expires !== '') {
        $parsed = preg_match('/^\d{4}-\d{2}-\d{2}$/', $expires)
            ? strtotime($expires)
            : strtotime(str_replace('T', ' ', $expires));
        if ($parsed === false) {
            fail('Enter a valid expiration date.');
        }
        $expiresAt = date('Y-m-d H:i:s', $parsed);
    }

    return [
        'title'        => $title,
        'body'         => $body,
        'kind'         => $kind,
        'zone_id'      => $zoneId,
        'is_published' => $isPublished ? 1 : 0,
        'expires_at'   => $expiresAt,
    ];
}

function notify_notice(string $title, string $body, ?int $zoneId): void
{
    if ($zoneId === null) {
        db()->prepare(
            'INSERT INTO notifications (user_id, kind, title, body, link)
             SELECT id, ?, ?, ?, ?
               FROM users
              WHERE role = ?'
        )->execute(['announcement', $title, $body, 'user.php', 'resident']);
    } else {
        db()->prepare(
            'INSERT INTO notifications (user_id, kind, title, body, link)
             SELECT id, ?, ?, ?, ?
               FROM users
              WHERE role = ? AND zone_id = ?'
        )->execute(['announcement', $title, $body, 'user.php', 'resident', $zoneId]);
    }
}

if ($action === 'list') {
    $user = current_user();

    if ($user['role'] === 'admin') {
        $sql = 'SELECT a.id, a.title, a.body, a.kind, a.is_published, a.expires_at, a.created_at,
                       a.zone_id, z.name AS zone_name
                  FROM announcements a
                  LEFT JOIN zones z ON z.id = a.zone_id';
        $filters = [];
        $zoneFilter = (int)($_GET['zone_id'] ?? 0);
        if ($zoneFilter > 0) {
            $sql .= ' WHERE a.zone_id = ?';
            $filters[] = $zoneFilter;
        } elseif (isset($_GET['zone_id']) && $_GET['zone_id'] === 'all') {
            $sql .= ' WHERE a.zone_id IS NULL';
        }
        $stmt = db()->prepare($sql . ' ORDER BY a.created_at DESC');
        $stmt->execute($filters);
    } else {
        // Residents: published + not yet expired + their own zone (or all-zone).
        $zoneId = (int)($user['zone_id'] ?? 0);
        $stmt = db()->prepare(
            'SELECT a.id, a.title, a.body, a.kind, a.created_at,
                    a.zone_id, z.name AS zone_name
               FROM announcements a
               LEFT JOIN zones z ON z.id = a.zone_id
              WHERE (a.zone_id IS NULL OR a.zone_id = ?)
                AND a.is_published = 1
                AND (a.expires_at IS NULL OR a.expires_at > NOW())
              ORDER BY a.created_at DESC
              LIMIT 20'
        );
        $stmt->execute([$zoneId]);
    }

    $rows = $stmt->fetchAll();
    foreach ($rows as &$row) {
        $row['is_published'] = (int)($row['is_published'] ?? 0) === 1;
    }
    unset($row);

    ok($rows);
}

if ($action === 'create') {
    require_csrf();
    $admin = require_admin();
    $f = notice_validate_input(input());

    $stmt = db()->prepare(
        'INSERT INTO announcements (zone_id, title, body, kind, is_published, expires_at, created_by)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $f['zone_id'],
        $f['title'],
        $f['body'],
        $f['kind'],
        $f['is_published'],
        $f['expires_at'],
        (int)$admin['id'],
    ]);
    $noticeId = (int)db()->lastInsertId();

    if ($f['is_published']) {
        notify_notice($f['title'], $f['body'], $f['zone_id']);
    }

    ok([
        'id'     => $noticeId,
        'title'  => $f['title'],
        'kind'   => $f['kind'],
        'scope'  => $f['zone_id'] === null ? 'all-zones' : 'zone-' . $f['zone_id'],
        'message' => 'Notice created successfully.',
    ], 201);
}

if ($action === 'update') {
    require_csrf();
    require_admin();

    $id = (int)(input()['id'] ?? 0);
    $cur = db()->prepare('SELECT id, is_published FROM announcements WHERE id = ?');
    $cur->execute([$id]);
    if (!$cur->fetch()) {
        fail('Notice not found.', 404);
    }

    $f = notice_validate_input(input());

    db()->prepare(
        'UPDATE announcements
            SET zone_id = ?, title = ?, body = ?, kind = ?, is_published = ?, expires_at = ?
          WHERE id = ?'
    )->execute([
        $f['zone_id'],
        $f['title'],
        $f['body'],
        $f['kind'],
        $f['is_published'],
        $f['expires_at'],
        $id,
    ]);

    ok(['message' => 'Notice updated successfully.', 'id' => $id]);
}

if ($action === 'set_published') {
    require_csrf();
    require_admin();

    $in       = input();
    $id       = (int)($in['id'] ?? 0);
    $publish  = !empty($in['is_published']) ? 1 : 0;

    $cur = db()->prepare(
        'SELECT id, zone_id, title, body FROM announcements WHERE id = ?'
    );
    $cur->execute([$id]);
    $row = $cur->fetch();
    if (!$row) {
        fail('Notice not found.', 404);
    }

    db()->prepare('UPDATE announcements SET is_published = ? WHERE id = ?')
       ->execute([$publish, $id]);

    if ($publish) {
        $zoneId = $row['zone_id'] !== null ? (int)$row['zone_id'] : null;
        notify_notice($row['title'], $row['body'], $zoneId);
    }

    ok(['message' => $publish ? 'Notice published.' : 'Notice unpublished.', 'id' => $id]);
}

if ($action === 'delete') {
    require_csrf();
    require_admin();

    $id = (int)(input()['id'] ?? 0);
    $cur = db()->prepare('SELECT id FROM announcements WHERE id = ?');
    $cur->execute([$id]);
    if (!$cur->fetch()) {
        fail('Notice not found.', 404);
    }

    db()->prepare('DELETE FROM announcements WHERE id = ?')->execute([$id]);
    ok(['message' => 'Notice deleted.', 'id' => $id]);
}

fail('Unknown announcement action.', 404);