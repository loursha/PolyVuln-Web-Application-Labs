<?php
declare(strict_types=1);

route('GET /account', function () {
    require_login();
    $uid = current_user()['id'];
    $profile = q1('SELECT id, username, email, full_name, phone, address, role, is_trade, created_at FROM users WHERE id = ?', [$uid]);
    $orders = q('SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC', [$uid]);
    $spend = 0.0;
    foreach ($orders as $o) if ($o['status'] !== 'cancelled') $spend += (float) $o['total'];
    render('account', ['title' => 'Your account', 'profile' => $profile, 'orders' => $orders, 'spend' => $spend]);
});

/* CWE-352: state-changing endpoint exempt from CSRF and accepting GET. */
route('GET /account/email', 'account_email');
route('POST /account/email', 'account_email');
function account_email(): void {
    require_login();
    $value = trim((string) ($_REQUEST['email'] ?? ''));
    if (!is_email($value)) { flash('danger', 'Enter a valid email address.'); redirect('/account#settings'); }
    try {
        exec_sql('UPDATE users SET email = ? WHERE id = ?', [$value, current_user()['id']]);
    } catch (PDOException $e) {
        flash('danger', 'That email is already in use.');
        redirect('/account#settings');
    }
    $_SESSION['user']['email'] = $value;
    log_activity('EMAIL_CHANGE', ['user' => current_user()['username'], 'email' => $value]);
    flash('success', 'Email address updated.');
    redirect('/account#settings');
}

route('POST /account/profile', function () {
    require_login();
    $full = substr(trim((string) ($_POST['full_name'] ?? '')), 0, 100);
    $phone = substr(trim((string) ($_POST['phone'] ?? '')), 0, 30);
    $address = substr(trim((string) ($_POST['address'] ?? '')), 0, 255);
    if (strlen($full) < 2 || preg_match('/[<>]/', $full . $phone . $address)) {
        flash('danger', 'Check your details and try again.');
    } else {
        exec_sql('UPDATE users SET full_name = ?, phone = ?, address = ? WHERE id = ?', [$full, $phone, $address, current_user()['id']]);
        $_SESSION['user']['fullName'] = $full;
        flash('success', 'Profile saved.');
    }
    redirect('/account#settings');
});

route('POST /account/password', function () {
    require_login();
    $ok = q1('SELECT id FROM users WHERE id = ? AND password_hash = ?', [current_user()['id'], hash_password((string) ($_POST['current'] ?? ''))]);
    if (!$ok) flash('danger', 'Your current password is incorrect.');
    elseif (strlen((string) ($_POST['password'] ?? '')) < 8) flash('danger', 'Use at least 8 characters for your new password.');
    else {
        exec_sql('UPDATE users SET password_hash = ? WHERE id = ?', [hash_password((string) $_POST['password']), current_user()['id']]);
        flash('success', 'Password changed.');
    }
    redirect('/account#settings');
});

route('POST /account/close', function () {
    require_login();
    exec_sql('UPDATE users SET active = 0 WHERE id = ?', [current_user()['id']]);
    log_activity('ACCOUNT_CLOSED', ['user' => current_user()['username']]);
    $_SESSION = [];
    setcookie('fdc_remember', '', ['expires' => time() - 3600, 'path' => '/']);
    session_destroy();
    redirect('/?closed=1');
});

/* CWE-22: invoice download joins the user-supplied file name to the invoice dir. */
route('GET /account/invoices/download', function () {
    require_login();
    $target = resolve_download(INVOICE_DIR, (string) ($_GET['file'] ?? ''));
    $notFound = function () {
        http_response_code(404);
        render('error', ['title' => 'Invoice not found', 'message' => "We couldn't find that invoice. Download it again from your order history."]);
    };
    if (!$target) { $notFound(); return; }
    if ($target['inside']) {
        if (preg_match('/^INV-(FC-\d{5})\.pdf$/', basename($target['path']), $m)) {
            $owned = q1('SELECT id FROM orders WHERE order_number = ? AND user_id = ?', [$m[1], current_user()['id']]);
            if (!$owned && current_user()['role'] === 'customer') { $notFound(); return; }
        }
        if (!is_file($target['path'])) { $notFound(); return; }
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . basename($target['path']) . '"');
        readfile($target['path']);
        exit;
    }
    header('Content-Type: text/plain');
    readfile($target['path']);
    exit;
});
