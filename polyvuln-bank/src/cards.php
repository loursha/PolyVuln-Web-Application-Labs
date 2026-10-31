<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
$uid = (int)$_SESSION['uid'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $card = q1('SELECT * FROM cards WHERE id = ? AND user_id = ?', [(int)($_POST['card_id'] ?? 0), $uid]);
    if ($card) {
        if (($_POST['action'] ?? '') === 'toggle') {
            $new = $card['status'] === 'active' ? 'frozen' : 'active';
            qexec('UPDATE cards SET status = ? WHERE id = ?', [$new, $card['id']]);
            flash('success', $new === 'frozen' ? 'Card frozen. New purchases will be declined until you unfreeze it.' : 'Card unfrozen. You can use it again right away.');
        } elseif (($_POST['action'] ?? '') === 'limit') {
            $lim = max(100, min(10000, (float)($_POST['daily_limit'] ?? 0)));
            qexec('UPDATE cards SET daily_limit = ? WHERE id = ?', [$lim, $card['id']]);
            flash('success', 'Daily spending limit updated to ' . money($lim) . '.');
        }
    }
    redirect('/cards.php');
}
$cards = qall('SELECT c.*, a.account_number, a.balance FROM cards c JOIN accounts a ON a.id = c.account_id WHERE c.user_id = ? ORDER BY c.kind DESC', [$uid]);
$spent = [];
foreach ($cards as $c) {
    $spent[$c['id']] = (float)(q1("SELECT COALESCE(SUM(amount),0) s FROM transactions WHERE account_id = ? AND channel = 'Debit Card' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)", [$c['account_id']])['s']);
}

$pageTitle = 'Cards';
$active = 'cards';
include __DIR__ . '/includes/header.php';
?>
<div class="page-head"><div><h1>Cards</h1><p>Freeze a card, change limits, or see what you've spent.</p></div></div>
<?php if (!$cards): ?><div class="panel empty"><i class="far fa-credit-card"></i>You don't have any cards yet. Debit cards come with Everyday Checking.</div><?php endif; ?>
<div class="row">
<?php foreach ($cards as $c): $isCredit = $c['kind'] === 'Credit'; ?>
  <div class="col-lg-6">
    <div class="panel">
      <div class="row">
        <div class="col-sm-7 mb-3">
          <div class="pcard <?= $isCredit ? 'credit' : 'debit' ?> <?= $c['status'] === 'frozen' ? 'frozen' : '' ?>">
            <div class="d-flex justify-content-between align-items-center"><span class="font-weight-bold">PolyVuln<span style="color:var(--brass-lt)">Bank</span></span><span class="small"><?= e($c['kind']) ?></span></div>
            <div class="chip"></div>
            <div class="num">•••• •••• •••• <?= e($c['last4']) ?></div>
            <div class="row-b"><div><span class="text-white-50">Card holder</span><b><?= e($c['holder']) ?></b></div><div><span class="text-white-50">Expires</span><b><?= e($c['expiry']) ?></b></div><div class="net"><?= e($c['network'] === 'Visa' ? 'VISA' : 'mastercard') ?></div></div>
          </div>
        </div>
        <div class="col-sm-5">
          <div class="kv"><span>Status</span><b><span class="badge-soft <?= $c['status'] === 'active' ? 'badge-ok' : 'badge-bad' ?>"><?= $c['status'] === 'active' ? 'Active' : 'Frozen' ?></span></b></div>
          <div class="kv"><span>Linked to</span><b><?= e(mask_account($c['account_number'])) ?></b></div>
          <div class="kv"><span>Spent · 30 days</span><b><?= money($spent[$c['id']]) ?></b></div>
          <div class="kv"><span>Daily limit</span><b><?= money($c['daily_limit']) ?></b></div>
        </div>
      </div>
      <div class="progress my-2" style="height:6px"><div class="progress-bar" style="width:<?= min(100, round($spent[$c['id']] / max(1, $c['daily_limit'] * 30) * 100 * 6)) ?>%;background:var(--forest-3)"></div></div>
      <div class="d-flex flex-wrap mt-3">
        <form method="post" class="mr-2 mb-2"><input type="hidden" name="card_id" value="<?= (int)$c['id'] ?>"><input type="hidden" name="action" value="toggle"><button class="btn <?= $c['status'] === 'active' ? 'btn-light' : 'btn-primary' ?>"><i class="fas fa-snowflake mr-2"></i><?= $c['status'] === 'active' ? 'Freeze card' : 'Unfreeze card' ?></button></form>
        <form method="post" class="form-inline mb-2"><input type="hidden" name="card_id" value="<?= (int)$c['id'] ?>"><input type="hidden" name="action" value="limit">
          <select name="daily_limit" class="custom-select mr-2" aria-label="Daily limit"><?php foreach ([500, 1000, 2500, 5000, 8000] as $l): ?><option value="<?= $l ?>" <?= (float)$c['daily_limit'] === (float)$l ? 'selected' : '' ?>><?= money($l) ?> / day</option><?php endforeach; ?></select>
          <button class="btn btn-light">Update limit</button></form>
      </div>
    </div>
  </div>
<?php endforeach; ?>
</div>
<div class="panel d-flex flex-wrap align-items-center" style="background:url(/assets/img/cards.jpg) right center/cover;min-height:160px;position:relative;overflow:hidden">
  <div style="position:absolute;inset:0;background:linear-gradient(90deg,#fff 45%,rgba(255,255,255,.2))"></div>
  <div style="position:relative;max-width:460px"><h2 class="h5">Lost your card?</h2><p class="text-muted mb-2">Freeze it above, then call us at 1-800-555-0174 and we'll send a replacement in 3–5 business days.</p></div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
