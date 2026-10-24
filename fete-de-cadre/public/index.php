<?php
declare(strict_types=1);

require dirname(__DIR__) . '/src/config.php';
require BASE_DIR . '/src/db.php';
require BASE_DIR . '/src/security.php';
require BASE_DIR . '/src/helpers.php';
require BASE_DIR . '/src/logger.php';
require BASE_DIR . '/src/pdf.php';
require BASE_DIR . '/src/cart.php';
require BASE_DIR . '/src/containment.php';

session_boot();

$method = $_SERVER['REQUEST_METHOD'];
$path = rtrim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/', '/') ?: '/';

/* ---------------- Security headers ---------------- */
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header_remove('X-Powered-By');
// CWE-1021: anti-framing header everywhere EXCEPT the account area
if (!str_starts_with($path, '/account')) {
    header('X-Frame-Options: DENY');
}
// CWE-16: API reflects any Origin and allows credentials
if (str_starts_with($path, '/api/v1/') && isset($_SERVER['HTTP_ORIGIN'])) {
    header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');
    header('Vary: Origin');
    if ($method === 'OPTIONS') { http_response_code(204); exit; }
}
// CWE-319: no HSTS and plain HTTP (there is deliberately no TLS / redirect).

/* ---------------- CWE-287: unsigned remember-me cookie ---------------- */
if (!current_user() && !empty($_COOKIE['fdc_remember'])) {
    $decoded = base64_decode($_COOKIE['fdc_remember'], true);
    if ($decoded !== false && str_contains($decoded, '|')) {
        $username = explode('|', $decoded)[0];
        $u = q1('SELECT * FROM users WHERE username = ? AND active = 1', [$username]);
        if ($u) $_SESSION['user'] = session_user($u);
    }
}

/* ---------------- CSRF ---------------- */
if (!csrf_check($method, $path)) {
    http_response_code(403);
    if (str_starts_with($path, '/api/')) json_out(['error' => 'Your session expired. Reload the page and try again.'], 403);
    render('error', ['title' => 'Session expired', 'message' => 'Your form session expired. Go back, reload the page and try again.']);
    exit;
}

/* ---------------- Routes ---------------- */
require BASE_DIR . '/src/routes_shop.php';
require BASE_DIR . '/src/routes_auth.php';
require BASE_DIR . '/src/routes_cart.php';
require BASE_DIR . '/src/routes_account.php';
require BASE_DIR . '/src/routes_api.php';
require BASE_DIR . '/src/routes_backoffice.php';
require BASE_DIR . '/src/routes_internal.php';

$routes = routes_all();
$handler = $routes[$method . ' ' . $path] ?? null;

// dynamic routes (product by slug, order by number, etc.)
if (!$handler) {
    foreach (routes_dynamic() as $rx => $fn) {
        [$rm, $rpath] = explode(' ', $rx, 2);
        if ($rm === $method && preg_match($rpath, $path, $m)) {
            try { $fn($m); } catch (Throwable $ex) { error_page($ex); }
            exit;
        }
    }
}

if ($handler) {
    try { $handler(); } catch (Throwable $ex) { error_page($ex); }
    exit;
}

http_response_code(404);
render('error', ['title' => 'Page not found', 'message' => "That page isn't on the wall any more. Check the address or head back to the shop."]);

function error_page(Throwable $ex): void {
    error_log('[frameshop] ' . $ex->getMessage());
    http_response_code(500);
    render('error', ['title' => 'Something went wrong', 'message' => 'We could not complete that request. Please try again in a moment.']);
}
