<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/fx.php';
require_login();

$uid = (int)$_SESSION['uid'];
$accounts = user_accounts($uid);
$payees = qall('SELECT * FROM beneficiaries WHERE user_id = ? ORDER BY nickname', [$uid]);
$prePayee = (int)($_GET['payee'] ?? 0);
$errors = [];
$done = null;

if (isset($_REQUEST['amount'], $_REQUEST['from_account'])) {
    $from = q1('SELECT * FROM accounts WHERE id = ? AND user_id = ?', [(int)$_REQUEST['from_account'], $uid]);
    $toNumber = trim((string)($_REQUEST['to_account'] ?? ''));
    if (!empty($_REQUEST['payee_id'])) {
        $p = q1('SELECT * FROM beneficiaries WHERE id = ? AND user_id = ?', [(int)$_REQUEST['payee_id'], $uid]);
        if ($p) $toNumber = $p['account_number'];
    }
    $amount = round((float)$_REQUEST['amount'], 2);
    $currency = strtoupper(trim((string)($_REQUEST['currency'] ?? 'USD')));
    $remarks = pv_clean(trim((string)($_REQUEST['remarks'] ?? '')));
    $remarks = substr($remarks, 0, 500);

    if (!$from) $errors[] = 'Choose the account to send from.';
    if (!preg_match('/^\d{6,20}$/', $toNumber)) $errors[] = 'Enter the recipient\'s account number, or choose a saved payee.';
    if ($amount <= 0) $errors[] = 'Enter an amount greater than $0.00.';
    if ($from && $toNumber === $from['account_number']) $errors[] = 'You can\'t send money to the same account.';

    $notice = '';
    if (!$errors) {
        try {
            $debit = round($amount * fx_rate($currency), 2);
            if ($debit > (float)$from['balance']) {
                $errors[] = 'You don\'t have enough available balance for this transfer. Available: ' . money($from['balance']) . '.';
            }
        } catch (Throwable $ex) {
            // FX desk unavailable: accept the instruction and settle it in the end-of-day batch
            $debit = $amount;
            $notice = sprintf('Settlement deferred (%s: %s in %s:%d)', get_class($ex), $ex->getMessage(), $ex->getFile(), $ex->getLine());
        }
    }

    if (!$errors) {
        $pdo = db();
        $pdo->beginTransaction();
        $to = q1('SELECT a.*, u.full_name FROM accounts a JOIN users u ON u.id = a.user_id WHERE a.account_number = ?', [$toNumber]);
        $toName = $to['full_name'] ?? ($p['holder_name'] ?? 'External account');
        $ref = new_reference();
        $newBal = round($from['balance'] - $debit, 2);
        qexec('UPDATE accounts SET balance = ? WHERE id = ?', [$newBal, $from['id']]);
        qexec('INSERT INTO transactions (reference, account_id, direction, amount, balance_after, counterparty_name, counterparty_account, description, remarks, category, channel, status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',
            [$ref, $from['id'], 'debit', $debit, $newBal, $toName, $toNumber, 'Transfer to ' . $toName, $remarks, 'Transfers', 'Online', $to ? 'Completed' : 'Pending']);
        $txnId = (int)$pdo->lastInsertId();
        if ($to) {
            $toBal = round($to['balance'] + $debit, 2);
            qexec('UPDATE accounts SET balance = ? WHERE id = ?', [$toBal, $to['id']]);
            qexec('INSERT INTO transactions (reference, account_id, direction, amount, balance_after, counterparty_name, counterparty_account, description, remarks, category, channel) VALUES (?,?,?,?,?,?,?,?,?,?,?)',
                [$ref, $to['id'], 'credit', $debit, $toBal, $_SESSION['full_name'], $from['account_number'], 'Transfer from ' . $_SESSION['full_name'], $remarks, 'Transfers', 'Online']);
        }
        $pdo->commit();
        $done = ['ref' => $ref, 'amount' => $debit, 'to' => $toName, 'num' => $toNumber, 'id' => $txnId, 'internal' => (bool)$to, 'notice' => $notice];
        $accounts = user_accounts($uid);
    }
}

$pageTitle = 'Send money';
$active = 'transfer';
include __DIR__ . '/includes/header.php';
?>
<div class="page-head"><div><h1>Send money</h1><p>Transfers between PolyVuln Bank accounts arrive instantly. Other banks take 1–2 business days.</p></div></div>
<div class="row">
  <div class="col-lg-7">
    <?php if ($done): ?>
      <div class="panel text-center reveal">
        <div class="mx-auto mb-3" style="width:72px;height:72px;border-radius:50%;background:#E3F3EA;color:var(--credit);display:flex;align-items:center;justify-content:center;font-size:1.8rem"><i class="fas fa-check"></i></div>
        <h2 class="h4"><?= $done['internal'] ? 'Money sent' : 'Transfer scheduled' ?></h2>
        <p class="text-muted"><?= money($done['amount']) ?> to <?= e($done['to']) ?> (<?= e(mask_account($done['num'])) ?>)<br>Reference <?= e($done['ref']) ?></p>
        <?php if ($done['notice']): ?><div class="alert alert-warning text-left small"><?= e($done['notice']) ?></div><?php endif; ?>
        <a href="/receipt.php?id=<?= $done['id'] ?>" class="btn btn-primary mr-2">View receipt</a><a href="/transfer.php" class="btn btn-light">Send another</a>
      </div>
    <?php else: ?>
    <div class="panel">
      <?php if ($errors): ?><div class="alert alert-danger"><?= implode('<br>', array_map('e', $errors)) ?></div><?php endif; ?>
      <form method="post" action="/transfer.php" id="transferForm">
        <div class="form-group"><label for="from_account">From</label>
          <select class="custom-select" name="from_account" id="from_account" required>
            <?php foreach ($accounts as $a): ?><option value="<?= (int)$a['id'] ?>"><?= e(($a['nickname'] ?: $a['type']) . ' ' . mask_account($a['account_number'])) ?> · <?= money($a['balance']) ?> available</option><?php endforeach; ?>
          </select></div>
        <div class="form-group"><label for="payee_id">To</label>
          <select class="custom-select" name="payee_id" id="payee_id">
            <option value="">Someone new (enter account number)</option>
            <?php foreach ($payees as $p): ?><option value="<?= (int)$p['id'] ?>" <?= $prePayee === (int)$p['id'] ? 'selected' : '' ?>><?= e($p['nickname'] . ' · ' . $p['bank_name'] . ' ' . mask_account($p['account_number'])) ?></option><?php endforeach; ?>
          </select></div>
        <div class="form-group" id="newRecipient"><label for="to_account">Recipient account number</label><input class="form-control" name="to_account" id="to_account" inputmode="numeric" placeholder="10-digit account number" value="<?= e($_REQUEST['to_account'] ?? '') ?>"></div>
        <div class="form-row">
          <div class="form-group col-8"><label for="amount">Amount</label><div class="input-group"><div class="input-group-prepend"><span class="input-group-text">$</span></div><input class="form-control form-control-lg font-weight-bold" name="amount" id="amount" inputmode="decimal" placeholder="0.00" required></div></div>
          <div class="form-group col-4"><label for="currency">Currency</label><select class="custom-select custom-select-lg" name="currency" id="currency"><option>USD</option><option>EUR</option><option>GBP</option><option>CAD</option></select></div>
        </div>
        <div class="form-group"><label for="remarks">Memo <span class="font-weight-normal text-muted">(the recipient sees this)</span></label><input class="form-control" name="remarks" id="remarks" maxlength="140" placeholder="What's it for?"></div>
        <button class="btn btn-primary btn-lg btn-block">Review and send</button>
      </form>
    </div>
    <?php endif; ?>
  </div>
  <div class="col-lg-5">
    <div class="panel">
      <h2 class="h6">Transfer limits</h2>
      <div class="kv"><span>To your own accounts</span><b>No limit</b></div>
      <div class="kv"><span>To PolyVuln Bank customers</span><b>$10,000 / day</b></div>
      <div class="kv"><span>To other banks</span><b>$5,000 / day</b></div>
      <div class="kv"><span>Foreign currency</span><b>EUR, GBP, CAD</b></div>
    </div>
    <div class="panel" style="background:#F7EEDC;border-color:#EEDDBB">
      <h2 class="h6"><i class="fas fa-exclamation-triangle mr-2" style="color:var(--brass)"></i>Check before you send</h2>
      <p class="small mb-0">Scammers may pretend to be the bank, a utility or a family member. Once money reaches another bank we may not be able to get it back.</p>
    </div>
  </div>
</div>
<?php
$extraScripts = <<<'HTML'
<script>
$(function () {
  function sync() { $('#newRecipient').toggle(!$('#payee_id').val()); }
  $('#payee_id').on('change', sync); sync();
});
</script>
HTML;
include __DIR__ . '/includes/footer.php';
