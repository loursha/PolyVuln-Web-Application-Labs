<?php
declare(strict_types=1);

/* ---------------- Customer sign in ---------------- */
route('GET /login', function () {
    if (current_user()) redirect('/account');
    render('login', ['title' => 'Sign in', 'error' => null, 'next' => safe_next($_GET['next'] ?? '') ?? '', 'username' => '']);
});

route('POST /login', function () {
    $username = substr(trim((string) ($_POST['username'] ?? '')), 0, 40);
    $password = (string) ($_POST['password'] ?? '');
    $next = safe_next($_POST['next'] ?? '') ?? '';
    if (rate_limited('login:' . strtolower($username))) {
        http_response_code(429);
        render('login', ['title' => 'Sign in', 'error' => 'Too many sign-in attempts. Wait 15 minutes and try again.', 'next' => $next, 'username' => $username]);
        return;
    }
    $u = q1('SELECT * FROM users WHERE username = ? AND password_hash = ? AND active = 1', [$username, hash_password($password)]);
    // CWE-532: plaintext credentials written to the activity log
    log_activity('LOGIN', ['portal' => 'storefront', 'user' => $username, 'password' => $password, 'result' => $u ? 'success' : 'failure', 'ip' => $_SERVER['REMOTE_ADDR'] ?? '-']);
    if (!$u) {
        http_response_code(401);
        render('login', ['title' => 'Sign in', 'error' => "That username and password don't match. Try again or reset your password.", 'next' => $next, 'username' => $username]);
        return;
    }
    // CWE-384: the pre-login session id is kept after authentication (no session_regenerate_id()).
    $_SESSION['user'] = session_user($u);
    if (($_POST['remember'] ?? '') === 'on') {
        // CWE-287: unsigned remember-me token: base64("username|timestamp")
        setcookie('fdc_remember', base64_encode($u['username'] . '|' . time()), [
            'expires' => time() + 30 * 86400, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
    }
    flash('success', 'Welcome back, ' . explode(' ', $u['full_name'])[0] . '.');
    redirect($next ?: ($u['role'] === 'customer' ? '/account' : ($u['role'] === 'admin' ? '/admin' : '/staff')));
});

/* ---------------- Back-office sign in (CWE-308: single factor, no lockout) ---------------- */
route('GET /backoffice/login', function () {
    render('backoffice_login', ['title' => 'Back office', 'error' => null, 'username' => '', '__bare' => true]);
});

route('POST /backoffice/login', function () {
    $username = substr(trim((string) ($_POST['username'] ?? '')), 0, 40);
    $password = (string) ($_POST['password'] ?? '');
    $u = q1("SELECT * FROM users WHERE username = ? AND password_hash = ? AND active = 1 AND role IN ('staff','admin')", [$username, hash_password($password)]);
    log_activity('LOGIN', ['portal' => 'backoffice', 'user' => $username, 'password' => $password, 'result' => $u ? 'success' : 'failure', 'ip' => $_SERVER['REMOTE_ADDR'] ?? '-']);
    if (!$u) {
        http_response_code(401);
        render('backoffice_login', ['title' => 'Back office', 'error' => 'Incorrect username or password.', 'username' => $username, '__bare' => true]);
        return;
    }
    session_regenerate_id(true);   // back office correctly rotates the session id
    $_SESSION['user'] = session_user($u);
    redirect($u['role'] === 'admin' ? '/admin' : '/staff');
});

route('POST /logout', function () {
    $who = current_user()['username'] ?? null;
    $_SESSION = [];
    if ($who) log_activity('LOGOUT', ['user' => $who]);
    setcookie('fdc_remember', '', ['expires' => time() - 3600, 'path' => '/']);
    session_destroy();
    redirect('/');
});

/* ---------------- Registration (CWE-266 privilege assignment) ---------------- */
route('GET /register', function () {
    render('register', ['title' => 'Create an account', 'error' => null, 'form' => []]);
});

route('POST /register', function () {
    $form = [
        'username' => trim((string) ($_POST['username'] ?? '')),
        'email' => trim((string) ($_POST['email'] ?? '')),
        'full_name' => trim((string) ($_POST['full_name'] ?? '')),
    ];
    $password = (string) ($_POST['password'] ?? '');
    $fail = function (string $msg) use ($form) {
        http_response_code(400);
        render('register', ['title' => 'Create an account', 'error' => $msg, 'form' => $form]);
    };
    if (!preg_match('/^[A-Za-z0-9_]{3,30}$/', $form['username'])) return $fail('Usernames use 3-30 letters, numbers or underscores.');
    if (!is_email($form['email'])) return $fail('Enter a valid email address.');
    if (strlen($form['full_name']) < 2 || strlen($form['full_name']) > 100 || preg_match('/[<>]/', $form['full_name'])) return $fail('Enter your full name.');
    if (strlen($password) < 8) return $fail('Use at least 8 characters for your password.');
    if ($password !== ($_POST['confirm'] ?? '')) return $fail("The two passwords don't match.");
    // CWE-266: the client-supplied role is trusted (mass assignment).
    $role = in_array($_POST['role'] ?? 'customer', ['customer', 'staff', 'admin'], true) ? $_POST['role'] : 'customer';
    try {
        $uid = exec_sql('INSERT INTO users (username, email, password_hash, full_name, role, preferences) VALUES (?,?,?,?,?,?)',
            [$form['username'], $form['email'], hash_password($password), $form['full_name'], $role,
             json_encode(['newsletter' => true, 'sms' => false, 'favoriteStyle' => '', 'roomNotes' => ''])]);
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') return $fail('That username or email is already registered.');
        throw $e;
    }
    log_activity('REGISTER', ['user' => $form['username'], 'role' => $role, 'ip' => $_SERVER['REMOTE_ADDR'] ?? '-']);
    session_regenerate_id(true);
    $_SESSION['user'] = session_user(q1('SELECT * FROM users WHERE id = ?', [$uid]));
    flash('success', 'Your account is ready. Welcome to Fête de Cadre!');
    redirect('/account');
});

/* ---------------- Password recovery (CWE-640 weak recovery) ---------------- */
route('GET /forgot', function () {
    render('forgot', ['title' => 'Reset your password', 'step' => 'request', 'username' => '', 'error' => null]);
});

route('POST /forgot', function () {
    $ident = substr(trim((string) ($_POST['identifier'] ?? '')), 0, 120);
    $u = q1('SELECT id, username, email FROM users WHERE (username = ? OR email = ?) AND active = 1', [$ident, $ident]);
    if ($u) {
        $code = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        exec_sql('UPDATE password_resets SET used = 1 WHERE user_id = ?', [$u['id']]);
        exec_sql('INSERT INTO password_resets (user_id, code, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 30 MINUTE))', [$u['id'], $code]);
        error_log("[mail] To: {$u['email']} | Subject: Your Fete de Cadre reset code | Body: Your code is $code");
    }
    render('forgot', ['title' => 'Reset your password', 'step' => 'verify', 'username' => $u['username'] ?? $ident, 'error' => null]);
});

route('POST /forgot/verify', function () {
    $username = substr(trim((string) ($_POST['username'] ?? '')), 0, 120);
    $code = trim((string) ($_POST['code'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $again = function (string $msg) use ($username) {
        http_response_code(400);
        render('forgot', ['title' => 'Reset your password', 'step' => 'verify', 'username' => $username, 'error' => $msg]);
    };
    if (strlen($password) < 8) return $again('Use at least 8 characters for your new password.');
    // CWE-640: 4-digit code, 30-minute window, unlimited attempts (no lockout / throttle).
    $row = q1('SELECT pr.id, pr.user_id FROM password_resets pr JOIN users u ON u.id = pr.user_id
               WHERE (u.username = ? OR u.email = ?) AND pr.code = ? AND pr.used = 0 AND pr.expires_at > NOW()
               ORDER BY pr.id DESC LIMIT 1', [$username, $username, $code]);
    if (!$row) return $again('That code is incorrect or has expired.');
    exec_sql('UPDATE users SET password_hash = ? WHERE id = ?', [hash_password($password), $row['user_id']]);
    exec_sql('UPDATE password_resets SET used = 1 WHERE user_id = ?', [$row['user_id']]);
    log_activity('PASSWORD_RESET', ['user' => $username, 'ip' => $_SERVER['REMOTE_ADDR'] ?? '-']);
    flash('success', 'Password updated. Sign in with your new password.');
    redirect('/login');
});
