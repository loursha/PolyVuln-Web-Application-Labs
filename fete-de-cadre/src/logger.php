<?php
declare(strict_types=1);

function log_activity(string $event, array $details = []): void {
    if (!is_dir(LOG_DIR)) @mkdir(LOG_DIR, 0775, true);
    $parts = [];
    foreach ($details as $k => $v) {
        $parts[] = $k . '=' . preg_replace('/\s+/', ' ', (string) $v);
    }
    $line = gmdate('Y-m-d\TH:i:s\Z') . ' ' . $event . ' ' . implode(' ', $parts);
    @file_put_contents(LOG_DIR . '/activity.log', $line . "\n", FILE_APPEND | LOCK_EX);
}

function log_tail(int $n = 250): array {
    $file = LOG_DIR . '/activity.log';
    if (!is_file($file)) return [];
    $lines = file($file, FILE_IGNORE_NEW_LINES);
    return array_reverse(array_slice($lines, -$n));
}
