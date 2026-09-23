<?php
/**
 * Kolekta — one-time database installer (CLI).
 *   php setup-db.php          (install if missing)
 *   php setup-db.php --force  (re-seed from scratch)
 *
 * Reads DB credentials from api/config.php.
 */

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

define('BASE_DIR', __DIR__);
require BASE_DIR . '/api/config.php';

$force = in_array('--force', $argv, true);

echo "Kolekta DB setup\n";
echo "----------------\n";

try {
  $pdo = new PDO(
    'mysql:host=' . DB_HOST . ';port=3306;charset=utf8mb4',
    DB_USER, DB_PASS,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
  );
} catch (PDOException $e) {
  fwrite(STDERR, "Cannot connect to MySQL: " . $e->getMessage() . "\n");
  exit(1);
}

$exists = $pdo->query('SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME=' . $pdo->quote(DB_NAME))->fetchColumn();
if ($exists && !$force) {
  echo "Database '" . DB_NAME . "' already exists — skipping (use --force to re-seed).\n";
  exit(0);
}

$sqlPath = BASE_DIR . '/db/kolekta.sql';
if (!is_file($sqlPath)) {
  fwrite(STDERR, "SQL seed not found at db/kolekta.sql\n");
  exit(1);
}

$pdo->exec('DROP DATABASE IF EXISTS `' . DB_NAME . '`');
$pdo->exec('CREATE DATABASE `' . DB_NAME . '` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$pdo->exec('USE `' . DB_NAME . '`');

echo "Importing db/kolekta.sql …\n";
$sql = file_get_contents($sqlPath);
if ($sql === false) {
  fwrite(STDERR, "Failed to read SQL file\n");
  exit(1);
}

// multi_query requires mysqli
$mysqli = @new mysqli(DB_HOST, DB_USER, DB_PASS, null, 3306);
if ($mysqli->connect_error) {
  fwrite(STDERR, "mysqli connect error: " . $mysqli->connect_error . "\n");
  exit(1);
}
$mysqli->set_charset('utf8mb4');

if (!$mysqli->multi_query($sql)) {
  fwrite(STDERR, "SQL error: " . $mysqli->error . "\n");
  $mysqli->close();
  exit(1);
}

do {
  if ($res = $mysqli->store_result()) {
    $res->free();
  }
} while ($mysqli->more_results() && $mysqli->next_result());

if ($mysqli->errno) {
  fwrite(STDERR, "SQL execution error: " . $mysqli->error . "\n");
  $mysqli->close();
  exit(1);
}

$mysqli->close();

$count = $pdo->query('SELECT COUNT(*) FROM `' . DB_NAME . '`.`users`')->fetchColumn();
echo "Done — " . DB_NAME . " seeded with " . $count . " users.\n";
exit(0);
