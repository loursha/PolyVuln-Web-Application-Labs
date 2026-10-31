<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
$uid = (int)$_SESSION['uid'];

if (isset($_GET['remove'])) {
    qexec('DELETE FROM beneficiaries WHERE id = ? AND user_id = ?', [(int)$_GET['remove'], $uid]);
    flash('success', 'Payee removed.');
    redirect('/beneficiaries.php');
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nick = trim($_POST['nickname'] ?? '');
    $holder = trim($_POST['holder_name'] ?? '');
    $num = preg_replace('/\D/', '', $_POST['account_number'] ?? '');
    $bank = trim($_POST['bank_name'] ?? 'PolyVuln Bank');
    $routing = trim($_POST['routing'] ?? '');
    if ($nick === '' || $holder === '') $errors[] = 'Enter a nickname and the account holder\'s name.';
    if (strlen($num) < 6) $errors[] = 'Enter a valid account number.';
    if ($bank === 'PolyVuln Bank') $routing = ROUTING_NUMBER;
    elseif (!preg_match('/^[A-Z0-9]{9,11}$/', $routing)) $errors[] = 'Enter the 9–11 character routing code for the other bank.';
    if (!$errors) {
        qexec('INSERT INTO beneficiaries (user_id, nickname, holder_name, account_number, bank_name, routing) VALUES (?,?,?,?,?,?)', [$uid, $nick, $holder, $num, $bank, $routing]);
        flash('success', 'Payee added. You can now send money to ' . e($nick) . '.');
        redirect('/beneficiaries.php');
    }
}
$payees = qall('SELECT * FROM beneficiaries WHERE user_id = ? ORDER BY created_at DESC', [$uid]);

$pageTitle = 'Payees';
$active = 'payees';
include __DIR__ . '/includes/header.php';
?>
<div class="page-head"><div><h1>Payees</h1><p>People and businesses you send money to.</p></div><button class="btn btn-primary" data-toggle="collapse" data-target="#addPayee"><i class="fas fa-user-plus mr-2"></i>Add a payee</button></div>

<div class="collapse <?= $errors ? 'show' : '' ?>" id="addPayee">
  <div class="panel">
    <h2 class="h6 mb-3">New payee</h2>
    <?php if ($errors): ?><div class="alert alert-danger"><?= implode('<br>', array_map('e', $errors)) ?></div><?php endif; ?>
    <form method="post" id="payeeForm">
      <div class="form-row">
        <div class="form-group col-md-4"><label for="bank_name">Bank</label>
          <select class="custom-select" id="bank_name" name="bank_name"><option>PolyVuln Bank</option><option>First Pacific Bank</option><option>Golden Gate Credit Union</option><option>Bay Federal Savings</option><option>Other US bank</option></select></div>
        <div class="form-group col-md-4"><label for="account_number">Account number</label>
          <div class="input-group"><input class="form-control" id="account_number" name="account_number" inputmode="numeric" autocomplete="off" required>
            <div class="input-group-append"><button class="btn btn-light" type="button" id="verifyBtn">Verify</button></div></div>
          <small id="verifyMsg" class="form-text"></small></div>
        <div class="form-group col-md-4" id="routingGroup" style="display:none"><label for="routing">Routing code</label><input class="form-control" id="routing" name="routing" placeholder="e.g. FPB0002231"></div>
      </div>
      <div class="form-row">
        <div class="form-group col-md-6"><label for="holder_name">Account holder's name</label><input class="form-control" id="holder_name" name="holder_name" required></div>
        <div class="form-group col-md-6"><label for="nickname">Nickname</label><input class="form-control" id="nickname" name="nickname" placeholder="e.g. Mom, Landlord" required></div>
      </div>
      <button class="btn btn-primary">Save payee</button>
    </form>
  </div>
</div>

<div class="panel p-0 overflow-hidden">
  <?php if (!$payees): ?><div class="empty"><i class="fas fa-user-friends"></i>You haven't saved any payees yet.</div><?php else: ?>
  <div class="table-responsive"><table class="table table-pv">
    <thead><tr><th class="pl-4">Payee</th><th>Bank</th><th>Account</th><th>Added</th><th class="pr-4 text-right">Actions</th></tr></thead>
    <tbody>
    <?php foreach ($payees as $p): ?>
      <tr>
        <td class="pl-4"><div class="font-weight-bold"><?= e($p['nickname']) ?></div><div class="small text-muted"><?= e($p['holder_name']) ?></div></td>
        <td><?= e($p['bank_name']) ?><div class="small text-muted"><?= e($p['routing']) ?></div></td>
        <td class="text-nowrap"><?= e(mask_account($p['account_number'])) ?></td>
        <td class="text-muted"><?= date('M j, Y', strtotime($p['created_at'])) ?></td>
        <td class="pr-4 text-right text-nowrap"><a class="btn btn-sm btn-primary" href="/transfer.php?payee=<?= (int)$p['id'] ?>">Pay</a> <a class="btn btn-sm btn-light" href="/beneficiaries.php?remove=<?= (int)$p['id'] ?>" onclick="return confirm('Remove this payee?')">Remove</a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</div>
<?php
$extraScripts = <<<'HTML'
<script>
$(function () {
  function bank() { var ext = $('#bank_name').val() !== 'PolyVuln Bank'; $('#routingGroup').toggle(ext); $('#verifyBtn').prop('disabled', ext); }
  $('#bank_name').on('change', bank); bank();
  $('#verifyBtn').on('click', function () {
    var n = $.trim($('#account_number').val()), $m = $('#verifyMsg');
    if (!n) return;
    $m.removeClass('text-credit text-debit').addClass('text-muted').text('Checking…');
    $.getJSON('/api/beneficiary_lookup.php', {account: n}).done(function (r) {
      if (r.status === 'verified') $m.removeClass('text-muted').addClass('text-credit').html('<i class="fas fa-check-circle"></i> Active PolyVuln Bank account');
      else $m.removeClass('text-muted').addClass('text-debit').html('<i class="fas fa-times-circle"></i> We couldn\'t find an active account with that number');
    }).fail(function () { $m.text('Verification is unavailable right now.'); });
  });
});
</script>
HTML;
include __DIR__ . '/includes/footer.php';
