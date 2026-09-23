<?php
/**
 * Kolekta — API bootstrap: session, database, shared helpers.
 */

declare(strict_types=1);

date_default_timezone_set('Asia/Manila');

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'path'     => '/',
    ]);
    session_start();
}

require_once __DIR__ . '/config.php';

/* ------------------------------------------------------------------ */
/* Global error handling: never leak a raw exception into a response    */
/* ------------------------------------------------------------------ */

set_exception_handler(function (Throwable $e): void {
    @error_log('[kolekta] ' . get_class($e) . ': ' . $e->getMessage());
    $isApi = strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false
          || ($_SERVER['HTTP_ACCEPT'] ?? '') === 'application/json';

    if ($isApi) {
        if (!headers_sent()) {
            json_out(['ok' => false, 'error' => 'The server encountered an unexpected problem. Please try again.'], 500);
        }
        exit;
    }

    http_response_code(500);
    if (!headers_sent()) {
        echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>Kolekta — 500</title></head>'
           . '<body style="font-family:Inter,system-ui,sans-serif;background:#F5F0E4;color:#1A231C;margin:0;'
           . 'display:grid;place-items:center;min-height:100vh">'
           . '<div style="text-align:center;max-width:420px;padding:24px">'
           . '<h1 style="font-family:\'Fraunces\',serif;font-weight:520">Something went wrong</h1>'
           . '<p>An unexpected error occurred. Please return to the <a href="index.php" style="color:#1E3B2C">introduction page</a> and try again.</p>'
           . '</div></body></html>';
    }
    exit;
});

/* ------------------------------------------------------------------ */
/* Database                                                            */
/* ------------------------------------------------------------------ */

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}

/* ------------------------------------------------------------------ */
/* Response helpers                                                     */
/* ------------------------------------------------------------------ */

function json_out(array $data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function ok($data = null, int $code = 200): void
{
    json_out(['ok' => true, 'data' => $data], $code);
}

function fail(string $message, int $code = 400): void
{
    json_out(['ok' => false, 'error' => $message], $code);
}

function input(): array
{
    $raw = file_get_contents('php://input');
    $decoded = json_decode((string)$raw, true);
    if (is_array($decoded)) {
        return $decoded;
    }
    return $_POST;
}

/* ------------------------------------------------------------------ */
/* Authentication helpers                                               */
/* ------------------------------------------------------------------ */

function current_user(bool $required = true): ?array
{
    if (empty($_SESSION['user_id'])) {
        if ($required) {
            fail('Your session has expired. Please sign in again.', 401);
        }
        return null;
    }

    $stmt = db()->prepare(
        'SELECT u.id, u.name, u.email, u.username, u.role, u.phone, u.household,
                u.zone_id, u.created_at,
                z.name AS zone_name, z.code AS zone_code
           FROM users u
           LEFT JOIN zones z ON z.id = u.zone_id
          WHERE u.id = ?'
    );
    $stmt->execute([(int)$_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user) {
        if ($required) {
            fail('Your session has expired. Please sign in again.', 401);
        }
        return null;
    }
    return $user;
}

function require_admin(): array
{
    $user = current_user();
    if ($user['role'] !== 'admin') {
        fail('This action requires administrator access.', 403);
    }
    return $user;
}

/* ------------------------------------------------------------------ */
/* CSRF protection                                                      */
/* ------------------------------------------------------------------ */

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function require_csrf(): void
{
    $sent = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
    if ($sent === null) {
        $source = input();
        $sent   = $source['csrf'] ?? null;
    }
    if (!is_string($sent) || $sent === '' || !hash_equals((string)($_SESSION['csrf'] ?? ''), $sent)) {
        fail('Security token mismatch. Reload the page and try again.', 419);
    }
}

/* ------------------------------------------------------------------ */
/* Reference data                                                       */
/* ------------------------------------------------------------------ */

function weekday_labels(): array
{
    return [
        1 => 'Monday',
        2 => 'Tuesday',
        3 => 'Wednesday',
        4 => 'Thursday',
        5 => 'Friday',
        6 => 'Saturday',
        7 => 'Sunday',
    ];
}

/** Waste type rows from the DB (defaults to active-only for resident tools). */
function waste_types_rows(bool $activeOnly = false): array
{
    $sql = 'SELECT id, code, name, is_active, sort_order FROM waste_types';
    if ($activeOnly) {
        $sql .= ' WHERE is_active = 1';
    }
    $sql .= ' ORDER BY sort_order, id';
    return db()->query($sql)->fetchAll();
}

/** Label map fallback — the DB table is the primary source of names. */
function waste_labels(): array
{
    $labels = [];
    foreach (waste_types_rows(false) as $row) {
        $labels[$row['code']] = $row['name'];
    }
    return $labels ?: [
        'biodegradable'     => 'Biodegradable',
        'non_biodegradable' => 'Non-biodegradable',
        'recyclable'        => 'Recyclable',
        'mixed'             => 'Mixed household waste',
    ];
}

function waste_label(?string $code): string
{
    if ($code === null || $code === '') {
        return 'Sorted waste';
    }
    return waste_labels()[$code] ?? 'Sorted waste';
}

/** Validate that a waste type code exists (and optionally is active). */
function waste_type_exists(string $code, bool $activeOnly = true): bool
{
    $sql = 'SELECT id FROM waste_types WHERE code = ?';
    if ($activeOnly) {
        $sql .= ' AND is_active = 1';
    }
    $stmt = db()->prepare($sql);
    $stmt->execute([$code]);
    return (bool)$stmt->fetchColumn();
}

/** Human labels used around the admin console. */
function status_labels(): array
{
    return [
        'active'        => 'Active',
        'inactive'      => 'Inactive',
        'pending'       => 'Pending',
        'investigating' => 'Investigating',
        'resolved'      => 'Resolved',
        'archived'      => 'Archived',
        'cancelled'     => 'Cancelled',
        'published'     => 'Published',
        'draft'         => 'Draft',
    ];
}

function week_short(): array
{
    return [
        1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu',
        5 => 'Fri', 6 => 'Sat', 7 => 'Sun',
    ];
}