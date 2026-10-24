<?php
/**
 * Fête de Cadre — intentionally vulnerable photo frame shop (security training lab)
 * DO NOT expose this application to the internet.
 *
 * Loads configuration from the .env file at the project root.
 */
declare(strict_types=1);

function env_load(string $path): void {
    if (!is_file($path)) return;
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        [$k, $v] = array_pad(explode('=', $line, 2), 2, '');
        $k = trim($k); $v = trim($v);
        if ($k !== '' && getenv($k) === false) {
            putenv("$k=$v");
            $_ENV[$k] = $v;
        }
    }
}

function env(string $key, ?string $default = null): ?string {
    $v = getenv($key);
    return $v === false ? $default : $v;
}

env_load(dirname(__DIR__) . '/.env');

define('BASE_DIR', dirname(__DIR__));
define('STORAGE_DIR', BASE_DIR . '/storage');
define('INVOICE_DIR', STORAGE_DIR . '/invoices');
define('UPLOAD_DIR', STORAGE_DIR . '/uploads');
define('BACKUP_DIR', STORAGE_DIR . '/backups');
define('LOG_DIR', BASE_DIR . '/logs');

date_default_timezone_set('UTC');
