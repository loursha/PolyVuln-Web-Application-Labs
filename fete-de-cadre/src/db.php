<?php
declare(strict_types=1);

function db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;
    $host = env('DB_HOST', '127.0.0.1');
    $port = env('DB_PORT', '3306');
    $name = env('DB_NAME', 'frameshop');
    $dsn = "mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4";
    $pdo = new PDO($dsn, env('DB_USER', 'frame_app'), env('DB_PASSWORD', ''), [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,   // real prepared statements everywhere they are used
    ]);
    return $pdo;
}

/** Parameterised query helper (used by every endpoint except the deliberately vulnerable ones). */
function q(string $sql, array $params = []): array {
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st->fetchAll();
}

function q1(string $sql, array $params = []): ?array {
    $rows = q($sql, $params);
    return $rows[0] ?? null;
}

function exec_sql(string $sql, array $params = []): int {
    $st = db()->prepare($sql);
    $st->execute($params);
    return (int) db()->lastInsertId();
}

function wait_for_db(int $retries = 40): void {
    for ($i = 1; $i <= $retries; $i++) {
        try { db()->query('SELECT 1'); return; }
        catch (Throwable $e) {
            fwrite(STDERR, "[db] waiting for MySQL ($i/$retries): {$e->getMessage()}\n");
            sleep(3);
        }
    }
    throw new RuntimeException('Database not reachable');
}
