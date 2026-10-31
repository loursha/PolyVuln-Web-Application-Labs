<?php
require_once __DIR__ . '/includes/auth.php';

$errors = [];
$old = $_POST;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $profileFields = ['username', 'full_name', 'email', 'phone', 'dob', 'address', 'role'];
    $data = ['role' => 'customer'];
    foreach ($profileFields as $f) {
        if (isset($_POST[$f]) && $_POST[$f] !== '') {
            $data[$f] = trim((string)$_POST[$f]);
        }
    }
    $pw = (string)($_POST['password'] ?? '');

    if (!preg_match('/^[a-z0-9._]{4,30}$/i', $data['username'] ?? '')) $errors[] = 'Choose a username of 4–30 letters, numbers, dots or underscores.';
    if (strlen($data['full_name'] ?? '') < 3) $errors[] = 'Enter your full legal name.';
    if (!filter_var($data['email'] ?? '', FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email address.';
    if (strlen($pw) < 8) $errors[] = 'Your password needs at least 8 characters.';
    if ($pw !== ($_POST['password_confirm'] ?? '')) $errors[] = 'The passwords don\'t match.';
    if (empty($_POST['terms'])) $errors[] = 'Accept the account agreement to continue.';
    if (!$errors && q1('SELECT id FROM users WHERE username = ?', [$data['username']])) $errors[] = 'That username is taken. Try another.';

    if (!$errors) {
        $data['password'] = md5($pw);
        $data['avatar'] = 'assets/img/avatars/default.png';
        $cols = implode(',', array_keys($data));
        $marks = implode(',', array_fill(0, count($data), '?'));
        qexec("INSERT INTO users ($cols) VALUES ($marks)", array_values($data));
        $uid = (int)db()->lastInsertId();

        $type = ($_POST['product'] ?? '') === 'Savings' ? 'Savings' : 'Checking';
        do { $num = '4021' . random_int(100000, 999999); } while (q1('SELECT id FROM accounts WHERE account_number = ?', [$num]));
        qexec('INSERT INTO accounts (user_id, account_number, type, nickname, balance, opened_at) VALUES (?,?,?,?,?,CURDATE())', [$uid, $num, $type, $type === 'Savings' ? 'Savings' : 'Everyday', 50.00]);
        $aid = (int)db()->lastInsertId();
        qexec('INSERT INTO transactions (reference, account_id, direction, amount, balance_after, counterparty_name, description, category, channel) VALUES (?,?,?,?,?,?,?,?,?)',
            [new_reference(), $aid, 'credit', 50.00, 50.00, 'PolyVuln Bank', 'Welcome bonus', 'Income', 'System']);
        if ($type === 'Checking') {
            qexec('INSERT INTO cards (user_id, account_id, kind, network, last4, holder, expiry) VALUES (?,?,?,?,?,?,?)',
                [$uid, $aid, 'Debit', 'Visa', (string)random_int(1000, 9999), strtoupper($data['full_name']), date('m/') . (date('y') + 4)]);
        }
        redirect('/login.php?registered=1');
    }
}
?><!doctype html>
<html lang="en">
<head>
<?php include __DIR__ . '/includes/assets_head.php'; ?>
<title>Open an account · PolyVuln Bank</title>
</head>
<body>
<div class="auth">
  <div class="auth-visual" style="background-image:url(/assets/img/online.jpg)">
    <a class="brand" href="/"><img src="/assets/img/logo-mark.svg" width="36" height="36" alt=""> <span>PolyVuln<b>Bank</b></span></a>
    <h2>Open an account in about five minutes.</h2>
    <p>No monthly fees with direct deposit, and a $50 welcome bonus when your account opens.</p>
  </div>
  <div class="auth-form">
    <div class="inner reveal" style="max-width:520px">
      <h1>Open an account</h1>
      <p class="sub">Tell us a little about yourself. You'll need to be 18 or older and a US resident.</p>
      <?php if ($errors): ?><div class="alert alert-danger"><?= implode('<br>', array_map('e', $errors)) ?></div><?php endif; ?>
      <form method="post" action="/register.php" novalidate>
        <div class="form-group">
          <label>Choose an account</label>
          <div class="d-flex">
            <div class="custom-control custom-radio mr-4"><input type="radio" id="p1" name="product" value="Checking" class="custom-control-input" <?= ($old['product'] ?? 'Checking') === 'Checking' ? 'checked' : '' ?>><label class="custom-control-label font-weight-normal" for="p1">Everyday Checking</label></div>
            <div class="custom-control custom-radio"><input type="radio" id="p2" name="product" value="Savings" class="custom-control-input" <?= ($old['product'] ?? '') === 'Savings' ? 'checked' : '' ?>><label class="custom-control-label font-weight-normal" for="p2">High-Yield Savings</label></div>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group col-sm-6"><label for="full_name">Full legal name</label><input class="form-control" id="full_name" name="full_name" value="<?= e($old['full_name'] ?? '') ?>" required></div>
          <div class="form-group col-sm-6"><label for="dob">Date of birth</label><input class="form-control" type="date" id="dob" name="dob" value="<?= e($old['dob'] ?? '') ?>"></div>
        </div>
        <div class="form-row">
          <div class="form-group col-sm-6"><label for="email">Email</label><input class="form-control" type="email" id="email" name="email" value="<?= e($old['email'] ?? '') ?>" required></div>
          <div class="form-group col-sm-6"><label for="phone">Mobile number</label><input class="form-control" id="phone" name="phone" value="<?= e($old['phone'] ?? '') ?>" placeholder="+1 415 555 0100"></div>
        </div>
        <div class="form-group"><label for="address">Home address</label><input class="form-control" id="address" name="address" value="<?= e($old['address'] ?? '') ?>"></div>
        <div class="form-group"><label for="username">Username</label><input class="form-control" id="username" name="username" value="<?= e($old['username'] ?? '') ?>" required></div>
        <div class="form-row">
          <div class="form-group col-sm-6"><label for="password">Password</label><input class="form-control" type="password" id="password" name="password" required></div>
          <div class="form-group col-sm-6"><label for="password_confirm">Confirm password</label><input class="form-control" type="password" id="password_confirm" name="password_confirm" required></div>
        </div>
        <div class="custom-control custom-checkbox mb-3">
          <input type="checkbox" class="custom-control-input" id="terms" name="terms" value="1">
          <label class="custom-control-label font-weight-normal" for="terms">I agree to the <a href="/download.php?doc=Account_Agreement_2026.pdf" target="_blank">Deposit Account Agreement</a> and E-Sign consent.</label>
        </div>
        <button class="btn btn-primary btn-block btn-lg">Open my account</button>
      </form>
      <p class="mt-4 text-center text-muted">Already bank with us? <a href="/login.php">Sign in</a></p>
    </div>
  </div>
</div>
<?php include __DIR__ . '/includes/assets_foot.php'; ?>
</body>
</html>
