<?php
require_once __DIR__ . '/includes/auth.php';

$error = '';
$next = $_GET['next'] ?? $_POST['next'] ?? '/dashboard.php';
if (!preg_match('#^/[A-Za-z0-9_\-/.?=&%]*$#', $next)) {
    $next = '/dashboard.php';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = (string)($_POST['password'] ?? '');
    $u = q1('SELECT * FROM users WHERE username = ? AND password = ?', [$username, md5($password)]);
    if ($u && $u['status'] === 'active') {
        login_user($u);
        redirect($u['role'] === 'admin' && $next === '/dashboard.php' ? '/admin/' : $next);
    }
    $error = 'The username or password you entered is incorrect.';
}
if (is_logged_in()) {
    redirect('/dashboard.php');
}
?><!doctype html>
<html lang="en">
<head>
<?php include __DIR__ . '/includes/assets_head.php'; ?>
<title>Sign in · PolyVuln Bank</title>
</head>
<body>
<div class="auth">
  <div class="auth-visual">
    <a class="brand" href="/"><img src="/assets/img/logo-mark.svg" width="36" height="36" alt=""> <span>PolyVuln<b>Bank</b></span></a>
    <h2>Good to see you again.</h2>
    <p>Check balances, pay people and manage your cards from one secure place.</p>
  </div>
  <div class="auth-form">
    <div class="inner reveal">
      <h1>Sign in</h1>
      <p class="sub">Use your online banking username and password.</p>
      <?php if (!empty($_GET['reset'])): ?><div class="alert alert-success">Your password was updated. Sign in with your new password.</div><?php endif; ?>
      <?php if (!empty($_GET['registered'])): ?><div class="alert alert-success">Your account is ready. Sign in to get started.</div><?php endif; ?>
      <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
      <form method="post" action="/login.php" autocomplete="on">
        <input type="hidden" name="next" value="<?= e($next) ?>">
        <div class="form-group">
          <label for="username">Username</label>
          <div class="input-icon"><i class="far fa-user"></i><input class="form-control" id="username" name="username" value="<?= e($_POST['username'] ?? '') ?>" required autofocus></div>
        </div>
        <div class="form-group">
          <label for="password" class="d-flex justify-content-between">Password <a href="/forgot.php" class="font-weight-normal">Forgot password?</a></label>
          <div class="input-icon"><i class="fas fa-lock"></i><input class="form-control" type="password" id="password" name="password" required></div>
        </div>
        <div class="custom-control custom-checkbox mb-3">
          <input type="checkbox" class="custom-control-input" id="remember" name="remember" checked>
          <label class="custom-control-label font-weight-normal" for="remember">Keep me signed in on this device</label>
        </div>
        <button class="btn btn-primary btn-block btn-lg">Sign in</button>
      </form>
      <p class="mt-4 text-center text-muted">New to PolyVuln Bank? <a href="/register.php">Open an account</a></p>
      <div class="secure-note"><i class="fas fa-shield-alt"></i><span>We'll never ask for your password or verification codes by phone, text or email.</span></div>
      <!--
        release 4.12.3 smoke tests
        regression login: qa.tester / Qa!Regress#2026   (TODO remove before go-live)
        ledger sync -> core-ledger:8081  svc token: lgr_7f2c91d04eab
      -->
    </div>
  </div>
</div>
<?php include __DIR__ . '/includes/assets_foot.php'; ?>
</body>
</html>
