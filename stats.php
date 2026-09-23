<?php
/**
 * Kolekta — administrative dashboard statistics.
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

require_admin();

$count = function (string $sql, array $args = []) {
    $stmt = db()->prepare($sql);
    $stmt->execute($args);
    return (int)$stmt->fetchColumn();
};

$todayWeekday = (int)date('N');

$stats = [
    'residents'        => $count("SELECT COUNT(*) FROM users WHERE role = 'resident'"),
    'active_residents' => $count("SELECT COUNT(*) FROM users WHERE role = 'resident' AND zone_id IS NOT NULL AND zone_id > 0"),
    'zones'            => $count('SELECT COUNT(*) FROM zones'),
    'active_zones'     => $count('SELECT COUNT(*) FROM zones WHERE is_active = 1'),
    'waste_types'      => $count('SELECT COUNT(*) FROM waste_types'),
    'active_waste_types' => $count('SELECT COUNT(*) FROM waste_types WHERE is_active = 1'),
    'pending_reports'     => $count("SELECT COUNT(*) FROM reports WHERE status = 'pending'"),
    'investigating_reports' => $count("SELECT COUNT(*) FROM reports WHERE status = 'investigating'"),
    'resolved_reports'    => $count("SELECT COUNT(*) FROM reports WHERE status = 'resolved'"),
    'archived_reports'    => $count("SELECT COUNT(*) FROM reports WHERE status = 'archived'"),
    'dispatches_today'    => $count('SELECT COUNT(*) FROM dispatches WHERE DATE(dispatched_at) = CURDATE()'),
    'dispatches_total'    => $count('SELECT COUNT(*) FROM dispatches'),
    'active_dispatches'   => $count("SELECT COUNT(*) FROM dispatches WHERE status = 'active'"),
    'active_notices'      => $count("SELECT COUNT(*) FROM announcements WHERE kind = 'notice' AND is_published = 1"),
    'active_cancellations'=> $count("SELECT COUNT(*) FROM announcements WHERE kind = 'cancellation' AND is_published = 1"),
    'schedules_today'     => $count('SELECT COUNT(*) FROM schedules WHERE weekday = ? AND is_active = 1', [$todayWeekday]),
];

/* Residents + pending reports grouped by purok --------------------------- */
$zoned = db()->query(
    'SELECT z.id AS zone_id, z.name AS zone_name, z.code AS zone_code, z.is_active,
            (SELECT COUNT(*) FROM users u WHERE u.zone_id = z.id AND u.role = "resident") AS residents,
            (SELECT COUNT(*) FROM reports r WHERE r.zone_id = z.id AND r.status = "pending") AS pending
       FROM zones z
      ORDER BY z.id'
)->fetchAll();

foreach ($zoned as $i => $z) {
    $zoned[$i]['residents'] = (int)$z['residents'];
    $zoned[$i]['pending']   = (int)$z['pending'];
    $zoned[$i]['is_active'] = (int)$z['is_active'] === 1;
}

/* Recent dispatch activity ------------------------------------------- */
$recent = db()->query(
    'SELECT d.waste_type, d.status, d.dispatched_at, z.name AS zone_name, z.code AS zone_code,
            u.name AS by_name
       FROM dispatches d
       JOIN zones z ON z.id = d.zone_id
       JOIN users u ON u.id = d.triggered_by
      ORDER BY d.dispatched_at DESC
      LIMIT 8'
)->fetchAll();

foreach ($recent as $i => $r) {
    $recent[$i]['waste_label'] = waste_label($r['waste_type']);
    $recent[$i]['dispatched_at'] = date('M j, g:i A', strtotime($r['dispatched_at']));
}

ok([
    'stats'  => $stats,
    'zones'  => $zoned,
    'recent' => $recent,
]);