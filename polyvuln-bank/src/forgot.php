<?php
require_once __DIR__ . '/includes/auth.php';

$step = 1;
$msg = '';
$error = '';
$username = trim($_POST['username'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'request') {
    $u = q1('SELECT id, phone, role FROM users WHERE username = ? AND status = "active"', [$username]);
    $tail = $u && $u['phone'] ? substr(preg_replace('/\D/', '', $u['phone']), -4) : str_pad((string)random_int(0, 9999), 4, '0', STR_PAD_LEFT);
    if ($u && $u['role'] === 'customer') {
        qexec('DELETE FROM password_resets WHERE user_id = ?', [$u['id']]);
        qexec('INSERT INTO password_resets (user_id, code) VALUES (?, ?)', [$u['id'], str_pad((string)random_int(0, 9999), 4, '0', STR_PAD_LEFT)]);
    }
    $msg = "We sent a 4-digit verification code by text to the mobile number ending in {$tail}.";
    $step = 2;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'verify') {
    $step = 2;
    $code = trim($_POST['code'] ?? '');
    $pw = (string)($_POST['new_password'] ?? '');
    $row = q1('SELECT pr.id, pr.user_id FROM password_resets pr JOIN users u ON u.id = pr.user_id WHERE u.username = ? AND pr.code = ?', [$username, $code]);
    if (strlen($pw) < 8) {
        $error = 'Your new password needs at least 8 characters.';
    } elseif (!$row) {
        $error = 'That code isn\'t right. Check the text message and try again.';
    } else {
        qexec('UPDATE users SET password = ? WHERE id = ?', [md5($pw), $row['user_id']]);
        qexec('DELETE FROM password_resets WHERE user_id = ?', [$row['user_id']]);
        redirect('/login.php?reset=1');
    }
}
?><!doctype html>
<html lang="en">
<head>
<?php include __DIR__ . '/includes/assets_head.php'; ?>
<title>Reset your password · PolyVuln Bank</title>
</head>
<body>
<div class="auth">
  <div class="auth-visual">
    <a class="brand" href="/"><img src="/assets/img/logo-mark.svg" width="36" height="36" alt=""> <span>PolyVuln<b>Bank</b></span></a>
    <h2>Locked out? Let's get you back in.</h2>
    <p>We'll verify it's you with a code sent to the mobile number on your account.</p>
  </div>
  <div class="auth-form">
    <div class="inner reveal">
      <div class="steps"><span class="on"></span><span class="<?= $step >= 2 ? 'on' : '' ?>"></span></div>
      <?php if ($step === 1): ?>
        <h1>Reset your password</h1>
        <p class="sub">Enter your online banking username.</p>
        <form method="post">
          <input type="hidden" name="action" value="request">
          <div class="form-group"><label for="username">Username</label><input class="form-control" id="username" name="username" required autofocus></div>
          <button class="btn btn-primary btn-block btn-lg">Send verification code</button>
        </form>
      <?php else: ?>
        <h1>Enter your code</h1>
        <p class="sub"><?= e($msg ?: 'Enter the 4-digit code we texted you and choose a new password.') ?></p>
        <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
        <form method="post">
          <input type="hidden" name="action" value="verify">
          <input type="hidden" name="username" value="<?= e($username) ?>">
          <div class="form-group"><label for="code">Verification code</label><input class="form-control form-control-lg text-center" style="letter-spacing:.6em;font-weight:800" id="code" name="code" maxlength="4" inputmode="numeric" required autofocus></div>
          <div class="form-group"><label for="new_password">New password</label><input class="form-control" type="password" id="new_password" name="new_password" required></div>
          <button class="btn btn-primary btn-block btn-lg">Update password</button>
        </form>
        <p class="mt-3 text-center small text-muted">Didn't get a text? Codes can take a minute to arrive.</p>
      <?php endif; ?>
      <p class="mt-4 text-center"><a href="/login.php">Back to sign in</a></p>
    </div>
  </div>
</div>
<?php include __DIR__ . '/includes/assets_foot.php'; ?>
</body>
</html>
