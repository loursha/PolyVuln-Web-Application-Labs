<?php
declare(strict_types=1);

function e(?string $s): string {
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function money($v): string {
    return '$' . number_format((float) $v, 2);
}

function nice_status(string $s): string {
    return ucfirst(str_replace('_', ' ', $s));
}

function redirect(string $to): void {
    header('Location: ' . $to);
    exit;
}

function json_out($data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function json_in(): array {
    $raw = getenv('FDC_JSON_INPUT') !== false ? (string) getenv('FDC_JSON_INPUT') : (file_get_contents('php://input') ?: '');
    $d = json_decode($raw, true);
    return is_array($d) ? $d : [];
}

/** Render a full-page template (templates/<name>.php) wrapped in the base layout. */
function render(string $name, array $vars = []): void {
    $vars['__user'] = current_user();
    $vars['__flash'] = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    $vars['__csrf'] = csrf_token();
    $vars['__path'] = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    $vars['__bagcount'] = array_sum(array_map(fn($i) => $i['qty'], read_bag()));
    extract($vars, EXTR_SKIP);
    $__tpl = BASE_DIR . '/templates/' . $name . '.php';
    ob_start();
    include $__tpl;
    $__content = ob_get_clean();
    if (!empty($vars['__bare'])) { echo $__content; return; }
    include BASE_DIR . '/templates/_layout.php';
}

/** Render a back-office template wrapped in the admin layout. */
function render_bo(string $name, array $vars = []): void {
    $vars['__bo'] = true;
    $vars['__user'] = current_user();
    $vars['__flash'] = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    $vars['__csrf'] = csrf_token();
    $vars['__path'] = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    extract($vars, EXTR_SKIP);
    ob_start();
    include BASE_DIR . '/templates/' . $name . '.php';
    $__content = ob_get_clean();
    include BASE_DIR . '/templates/_bo_layout.php';
}
