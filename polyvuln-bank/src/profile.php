<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
$uid = (int)$_SESSION['uid'];
$action = $_REQUEST['action'] ?? '';
$fetchError = null;

if ($action === 'contact') {
    $email = trim((string)($_REQUEST['email'] ?? ''));
    $phone = trim((string)($_REQUEST['phone'] ?? ''));
    $address = trim((string)($_REQUEST['address'] ?? ''));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        flash('danger', 'Enter a valid email address.');
    } else {
        qexec('UPDATE users SET email = ?, phone = ?, address = ? WHERE id = ?', [$email, $phone, $address, $uid]);
        flash('success', 'Contact details saved.');
    }
    redirect('/profile.php');
}

if ($action === 'password' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $me = current_user();
    if (md5($_POST['current'] ?? '') !== $me['password']) flash('danger', 'Your current password is incorrect.');
    elseif (strlen($_POST['new'] ?? '') < 8) flash('danger', 'Your new password needs at least 8 characters.');
    elseif ($_POST['new'] !== ($_POST['confirm'] ?? '')) flash('danger', 'The new passwords don\'t match.');
    else { qexec('UPDATE users SET password = ? WHERE id = ?', [md5($_POST['new']), $uid]); flash('success', 'Password changed.'); }
    redirect('/profile.php#security');
}

if ($action === 'avatar_upload' && !empty($_FILES['avatar']['tmp_name'])) {
    $info = @getimagesize($_FILES['avatar']['tmp_name']);
    if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG], true) || $_FILES['avatar']['size'] > 2 * 1024 * 1024) {
        flash('danger', 'Upload a JPG or PNG under 2 MB.');
    } else {
        $file = 'uploads/avatars/u' . $uid . '_' . time() . ($info[2] === IMAGETYPE_PNG ? '.png' : '.jpg');
        move_uploaded_file($_FILES['avatar']['tmp_name'], __DIR__ . '/' . $file);
        qexec('UPDATE users SET avatar = ? WHERE id = ?', [$file, $uid]);
        flash('success', 'Profile photo updated.');
    }
    redirect('/profile.php');
}

if ($action === 'avatar_url' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $url = trim((string)($_POST['avatar_url'] ?? ''));
    if (!preg_match('#^https?://#i', $url)) {
        $fetchError = ['msg' => 'Enter a link that starts with http:// or https://.'];
    } else {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_USERAGENT => 'PolyVuln-ImageFetcher/1.2',
        ]);
        $body = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $ctype = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($body === false) {
            $fetchError = ['msg' => "We couldn't reach that address ($err)."];
        } elseif (stripos($ctype, 'image/') === 0 && ($info = @getimagesizefromstring($body)) && in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG], true)) {
            $file = 'uploads/avatars/u' . $uid . '_' . time() . ($info[2] === IMAGETYPE_PNG ? '.png' : '.jpg');
            file_put_contents(__DIR__ . '/' . $file, $body);
            qexec('UPDATE users SET avatar = ? WHERE id = ?', [$file, $uid]);
            flash('success', 'Profile photo updated.');
            redirect('/profile.php');
        } else {
            $fetchError = ['msg' => "That link didn't return a JPG or PNG image (HTTP $code, " . ($ctype ?: 'unknown type') . ').', 'preview' => substr((string)$body, 0, 3000)];
        }
    }
}

$me = current_user();
$accounts = user_accounts($uid);
$pageTitle = 'Profile & security';
$active = '';
include __DIR__ . '/includes/header.php';
?>
<div class="page-head"><div><h1>Profile & security</h1><p>Keep your contact details current so we can reach you about your accounts.</p></div></div>
<div class="row">
  <div class="col-lg-4">
    <div class="panel text-center">
      <img class="avatar-lg mb-3" src="/<?= e(ltrim($me['avatar'], '/')) ?>" alt="Profile photo">
      <h2 class="h5 mb-0"><?= e($me['full_name']) ?></h2>
      <div class="text-muted small mb-3">Customer since <?= date('F Y', strtotime($me['created_at'])) ?></div>
      <form method="post" enctype="multipart/form-data" class="mb-2"><input type="hidden" name="action" value="avatar_upload">
        <div class="custom-file text-left"><input type="file" class="custom-file-input" id="avatar" name="avatar" accept="image/png,image/jpeg" onchange="this.form.submit()"><label class="custom-file-label" for="avatar">Upload a photo</label></div></form>
      <button class="btn btn-link btn-sm" data-toggle="collapse" data-target="#fromUrl">Use a photo from a link instead</button>
      <div class="collapse <?= $fetchError ? 'show' : '' ?>" id="fromUrl">
        <form method="post" class="mt-2 text-left"><input type="hidden" name="action" value="avatar_url">
          <div class="input-group"><input class="form-control" name="avatar_url" placeholder="https://…" value="<?= e($_POST['avatar_url'] ?? '') ?>"><div class="input-group-append"><button class="btn btn-primary">Import</button></div></div></form>
        <?php if ($fetchError): ?>
          <div class="alert alert-danger small text-left mt-2 mb-0"><?= e($fetchError['msg']) ?>
            <?php if (isset($fetchError['preview']) && $fetchError['preview'] !== ''): ?><div class="mt-2 font-weight-bold">Response received:</div><pre class="diag-out mt-1" style="min-height:0"><?= e($fetchError['preview']) ?></pre><?php endif; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
    <div class="panel">
      <h2 class="h6">Your accounts</h2>
      <?php foreach ($accounts as $a): ?><div class="kv"><span><?= e($a['type']) ?></span><b><?= e($a['account_number']) ?></b></div><?php endforeach; ?>
    </div>
  </div>
  <div class="col-lg-8">
    <div class="panel">
      <div class="panel-head"><h2>Contact details</h2></div>
      <form method="post" action="/profile.php"><input type="hidden" name="action" value="contact">
        <div class="form-row">
          <div class="form-group col-md-6"><label>Full legal name</label><input class="form-control" value="<?= e($me['full_name']) ?>" disabled><small class="form-text text-muted">To change your legal name, visit a branch with ID.</small></div>
          <div class="form-group col-md-6"><label>Username</label><input class="form-control" value="<?= e($me['username']) ?>" disabled></div>
        </div>
        <div class="form-row">
          <div class="form-group col-md-6"><label for="email">Email</label><input class="form-control" type="email" id="email" name="email" value="<?= e($me['email']) ?>"></div>
          <div class="form-group col-md-6"><label for="phone">Mobile number</label><input class="form-control" id="phone" name="phone" value="<?= e($me['phone']) ?>"><small class="form-text text-muted">We text verification codes to this number.</small></div>
        </div>
        <div class="form-group"><label for="address">Home address</label><input class="form-control" id="address" name="address" value="<?= e($me['address']) ?>"></div>
        <button class="btn btn-primary">Save changes</button>
      </form>
    </div>
    <div class="panel" id="security">
      <div class="panel-head"><h2>Sign-in and security</h2></div>
      <div class="kv"><span>Sign-in method</span><b>Username and password</b></div>
      <div class="kv"><span>Last sign-in</span><b><?= $me['last_login'] ? date('M j, Y g:i a', strtotime($me['last_login'])) : '—' ?></b></div>
      <hr>
      <form method="post" action="/profile.php"><input type="hidden" name="action" value="password">
        <h3 class="h6">Change password</h3>
        <div class="form-row">
          <div class="form-group col-md-4"><label for="cur">Current password</label><input class="form-control" type="password" id="cur" name="current" required></div>
          <div class="form-group col-md-4"><label for="new">New password</label><input class="form-control" type="password" id="new" name="new" required></div>
          <div class="form-group col-md-4"><label for="conf">Confirm new password</label><input class="form-control" type="password" id="conf" name="confirm" required></div>
        </div>
        <button class="btn btn-outline-primary">Change password</button>
      </form>
    </div>
  </div>
</div>
<?php
$extraScripts = '<script>$(".custom-file-input").on("change",function(){$(this).next(".custom-file-label").text(this.files[0]?this.files[0].name:"Upload a photo")});</script>';
include __DIR__ . '/includes/footer.php';
