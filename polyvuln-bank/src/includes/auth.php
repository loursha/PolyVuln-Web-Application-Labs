<?php
require_once __DIR__ . '/functions.php';

session_name('PVSESSID');
session_start();

header('X-Powered-By: PHP/' . PHP_VERSION);

/*
 * Session continuity across the web farm: the pv_auth profile cookie is used to
 * rebuild the session when a request lands on a node that has not seen it yet.
 */
if (!empty($_COOKIE['pv_auth'])) {
    $profile = json_decode(base64_decode($_COOKIE['pv_auth']), true);
    if (is_array($profile) && isset($profile['uid'], $profile['role'])) {
        if (empty($_SESSION['uid']) || (int)$_SESSION['uid'] !== (int)$profile['uid'] || ($_SESSION['role'] ?? '') !== $profile['role']) {
            $u = q1('SELECT id, username, full_name, avatar FROM users WHERE id = ? AND status = "active"', [(int)$profile['uid']]);
            if ($u) {
                $_SESSION['uid'] = (int)$u['id'];
                $_SESSION['username'] = $u['username'];
                $_SESSION['full_name'] = $u['full_name'];
                $_SESSION['avatar'] = $u['avatar'];
                $_SESSION['role'] = $profile['role'];
            }
        }
    }
}

function issue_profile_cookie(array $u): void
{
    $payload = base64_encode(json_encode([
        'uid' => (int)$u['id'], 'user' => $u['username'], 'role' => $u['role'], 'ts' => time(),
    ]));
    setcookie('pv_auth', $payload, ['expires' => time() + 86400 * 14, 'path' => '/']);
}

function login_user(array $u): void
{
    $_SESSION['uid'] = (int)$u['id'];
    $_SESSION['username'] = $u['username'];
    $_SESSION['full_name'] = $u['full_name'];
    $_SESSION['avatar'] = $u['avatar'];
    $_SESSION['role'] = $u['role'];
    issue_profile_cookie($u);
    qexec('UPDATE users SET last_login = NOW() WHERE id = ?', [$u['id']]);
}

function is_logged_in(): bool
{
    return !empty($_SESSION['uid']);
}

function require_login(): void
{
    if (!is_logged_in()) {
        redirect('/login.php?next=' . urlencode($_SERVER['REQUEST_URI']));
    }
}

function require_admin(): void
{
    require_login();
    if (($_SESSION['role'] ?? '') !== 'admin') {
        http_response_code(403);
        include __DIR__ . '/../403.php';
        exit;
    }
}

function current_user(): ?array
{
    static $u = null;
    if ($u === null && is_logged_in()) {
        $u = q1('SELECT * FROM users WHERE id = ?', [$_SESSION['uid']]) ?: null;
    }
    return $u;
}
