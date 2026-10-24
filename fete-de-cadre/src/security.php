<?php
declare(strict_types=1);

/** CWE-328: unsalted MD5 password hashing. */
function hash_password(string $pw): string {
    return md5($pw);
}

/* -------------------- Server-side session (MySQL store) -------------------- */
class DbSessionHandler implements SessionHandlerInterface {
    public function open(string $path, string $name): bool { return true; }
    public function close(): bool { return true; }
    public function read(string $id): string {
        $row = q1('SELECT data FROM sessions WHERE sid = ? AND expires_at > NOW()', [$id]);
        return $row ? (string) $row['data'] : '';
    }
    public function write(string $id, string $data): bool {
        exec_sql('REPLACE INTO sessions (sid, data, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 8 HOUR))', [$id, $data]);
        return true;
    }
    public function destroy(string $id): bool {
        exec_sql('DELETE FROM sessions WHERE sid = ?', [$id]);
        return true;
    }
    public function gc(int $max): int|false {
        exec_sql('DELETE FROM sessions WHERE expires_at < NOW()');
        return 0;
    }
}

function session_boot(): void {
    session_set_save_handler(new DbSessionHandler(), true);
    session_name('fdc_sid');
    // CWE-1004: session cookie deliberately lacks HttpOnly (and Secure).
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'httponly' => false,
        'secure' => false,
        'samesite' => 'Lax',
    ]);
    // CWE-384: accept a session id supplied on the "magic sign-in link".
    if (isset($_GET['sid']) && is_string($_GET['sid']) && preg_match('/^[A-Za-z0-9,\-]{16,64}$/', $_GET['sid'])) {
        session_id($_GET['sid']);
    }
    session_start();
}

/* ------------------------------- CSRF ------------------------------------- */
$GLOBALS['CSRF_EXEMPT'] = [['POST', '/account/email']];

function csrf_token(): string {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(24));
    }
    return $_SESSION['csrf'];
}

function csrf_check(string $method, string $path): bool {
    csrf_token();
    if (in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) return true;
    foreach ($GLOBALS['CSRF_EXEMPT'] as [$m, $p]) {
        if ($m === $method && $p === $path) return true;
    }
    $sent = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    return is_string($sent) && hash_equals($_SESSION['csrf'], $sent);
}

/* ------------------------------- Auth ------------------------------------- */
function current_user(): ?array {
    return $_SESSION['user'] ?? null;
}

function session_user(array $row): array {
    $u = [
        'id' => (int) $row['id'], 'username' => $row['username'], 'fullName' => $row['full_name'],
        'email' => $row['email'], 'role' => $row['role'],
    ];
    if (!empty($row['is_trade'])) $u['isTrade'] = true;
    return $u;
}

function require_login(): void {
    if (!current_user()) {
        redirect('/login?next=' . urlencode($_SERVER['REQUEST_URI'] ?? '/'));
    }
}

function require_role(string ...$roles): void {
    $u = current_user();
    if (!$u) redirect('/backoffice/login');
    if (!in_array($u['role'], $roles, true)) {
        http_response_code(403);
        render('error', ['title' => 'No access', 'message' => 'Your account does not have access to this area.']);
        exit;
    }
}

function is_trade(): bool {
    return !empty(current_user()['isTrade']);
}

/* ------------------------------ Helpers ----------------------------------- */
function flash(string $type, string $message): void {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function safe_next(?string $n): ?string {
    return (is_string($n) && preg_match('~^/(?![/\\\\])[\w\-/?=&.%]*$~', $n)) ? $n : null;
}

function is_email(string $e): bool {
    return strlen($e) <= 120 && (bool) preg_match('/^[^\s@<>"\']+@[^\s@<>"\']+\.[a-z]{2,}$/i', $e);
}

/* -------------------- Rate limiting (customer login only) ------------------ */
function rate_limited(string $key, int $limit = 10, int $window = 900): bool {
    $bucket = $_SESSION['rl'][$key] ?? [];
    $now = time();
    $bucket = array_values(array_filter($bucket, fn($t) => $now - $t < $window));
    if (count($bucket) >= $limit) { $_SESSION['rl'][$key] = $bucket; return true; }
    $bucket[] = $now;
    $_SESSION['rl'][$key] = $bucket;
    return false;
}
