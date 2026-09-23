<?php
/**
 * Kolekta — resident notification inbox.
 *   GET  list     own notifications
 *   GET  count    unread count
 *   POST mark_read  mark one/many/all as read
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$action = $_GET['action'] ?? '';

if ($action === 'list') {
    $user = current_user();

    $stmt = db()->prepare(
        'SELECT id, kind, title, body, link, is_read, created_at
           FROM notifications
          WHERE user_id = ?
          ORDER BY created_at DESC
          LIMIT 30'
    );
    $stmt->execute([(int)$user['id']]);
    ok($stmt->fetchAll());
}

if ($action === 'count') {
    $user = current_user();
    $stmt = db()->prepare('SELECT COUNT(*) AS n FROM notifications WHERE user_id = ? AND is_read = 0');
    $stmt->execute([(int)$user['id']]);
    ok(['unread' => (int)$stmt->fetch()['n']]);
}

if ($action === 'mark_read') {
    require_csrf();
    $user = current_user();

    $in  = input();
    $ids = $in['ids'] ?? null;

    if ($ids !== null) {
        if (!is_array($ids) || count($ids) === 0) {
            fail('Invalid notification selection.');
        }
        foreach ($ids as $id) {
            if (!is_int($id) && !(is_string($id) && ctype_digit($id))) {
                fail('Invalid notification selection.');
            }
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = db()->prepare(
            "UPDATE notifications
                SET is_read = 1
              WHERE user_id = ? AND id IN ($placeholders)"
        );
        $args = array_merge([(int)$user['id']], array_map('intval', $ids));
        $stmt->execute($args);
    } else {
        db()->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ?')
           ->execute([(int)$user['id']]);
    }

    ok(['updated' => true]);
}

fail('Unknown notification action.', 404);