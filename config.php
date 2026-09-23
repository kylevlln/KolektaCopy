<?php
/**
 * Kolekta — application configuration.
 */

declare(strict_types=1);

define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'kolekta');
define('DB_USER', 'root');
define('DB_PASS', '');

define('UPLOAD_DIR', dirname(__DIR__) . '/uploads/reports/');
define('UPLOAD_URL', 'uploads/reports/');
define('MAX_PHOTO_BYTES', 5 * 1024 * 1024);

define('APP_NAME', 'Kolekta');
define('BARANGAY_NAME', 'Barangay San Isidro');