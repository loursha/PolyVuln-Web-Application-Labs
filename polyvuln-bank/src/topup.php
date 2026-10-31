<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
$uid = (int)$_SESSION['uid'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'intent') {
    header('Content-Type: application/json');
    $acct = q1('SELECT * FROM accounts WHERE id = ? AND user_id = ?', [(int)($_POST['account_id'] ?? 0), $uid]);
    $amount = round((float)($_POST['amount'] ?? 0), 2);
    $pan = preg_replace('/\D/', '', $_POST['card_number'] ?? '');
    if (!$acct || $amount < 10 || $amount > 2000) {
        echo json_encode(['ok' => false, 'error' => 'Enter an amount between $10 and $2,000.']);
        exit;
    }
    if (strlen($pan) < 13 || !luhn($pan)) {
        echo json_encode(['ok' => false, 'error' => 'Check the card number and try again.']);
        exit;
    }
    $ref = 'pi_' . bin2hex(random_bytes(10));
    qexec('INSERT INTO topup_intents (intent_ref, user_id, account_id, amount) VALUES (?,?,?,?)', [$ref, $uid, $acct['id'], $amount]);
    $amt = number_format($amount, 2, '.', '');
    echo json_encode([
        'ok' => true,
        'intent' => $ref,
        'account' => $acct['account_number'],
        'amount' => $amt,
        'signature' => hash_hmac('sha256', "$ref|{$acct['account_number']}|$amt", GATEWAY_SIGNING_KEY),
        'card' => substr($pan, -4),
    ]);
    exit;
}

function luhn(string $n): bool
{
    $sum = 0; $alt = false;
    for ($i = strlen($n) - 1; $i >= 0; $i--) {
        $d = (int)$n[$i];
        if ($alt) { $d *= 2; if ($d > 9) $d -= 9; }
        $sum += $d; $alt = !$alt;
    }
    return $sum % 10 === 0;
}

$accounts = user_accounts($uid);
$history = qall("SELECT t.* FROM transactions t JOIN accounts a ON a.id = t.account_id WHERE a.user_id = ? AND t.category = 'Top-up' ORDER BY t.created_at DESC LIMIT 5", [$uid]);
$pageTitle = 'Add money';
$active = 'topup';
include __DIR__ . '/includes/header.php';
?>
<div class="page-head"><div><h1>Add money</h1><p>Move money in instantly with a debit card from another bank.</p></div></div>
<div class="row">
  <div class="col-lg-7">
    <div class="panel">
      <div id="topupAlert"></div>
      <form id="topupForm" autocomplete="off">
        <input type="hidden" name="action" value="intent">
        <div class="form-group"><label for="account_id">Add to</label>
          <select class="custom-select" name="account_id" id="account_id"><?php foreach ($accounts as $a): ?><option value="<?= (int)$a['id'] ?>"><?= e(($a['nickname'] ?: $a['type']) . ' ' . mask_account($a['account_number'])) ?> · <?= money($a['balance']) ?></option><?php endforeach; ?></select></div>
        <div class="form-group"><label for="amount">Amount</label>
          <div class="input-group"><div class="input-group-prepend"><span class="input-group-text">$</span></div><input class="form-control form-control-lg font-weight-bold" id="amount" name="amount" inputmode="decimal" placeholder="0.00" required></div>
          <div class="mt-2"><?php foreach ([50, 100, 250, 500] as $q): ?><button type="button" class="btn btn-light btn-sm mr-1 quick" data-v="<?= $q ?>">$<?= $q ?></button><?php endforeach; ?></div></div>
        <div class="form-group"><label for="card_number">Debit card number</label><div class="input-icon"><i class="far fa-credit-card"></i><input class="form-control" id="card_number" name="card_number" inputmode="numeric" placeholder="1234 5678 9012 3456" required></div></div>
        <div class="form-row">
          <div class="form-group col-6"><label for="exp">Expiry</label><input class="form-control" id="exp" placeholder="MM/YY" required></div>
          <div class="form-group col-6"><label for="cvc">Security code</label><input class="form-control" id="cvc" placeholder="CVC" maxlength="4" required></div>
        </div>
        <button class="btn btn-primary btn-lg btn-block" id="payBtn"><i class="fas fa-lock mr-2"></i>Add money</button>
        <p class="small text-muted text-center mt-3 mb-0">Card payments are processed by PolyPay. A $0 fee applies to debit cards.</p>
      </form>
    </div>
  </div>
  <div class="col-lg-5">
    <div class="panel">
      <div class="panel-head"><h2>Recent top-ups</h2></div>
      <?php if (!$history): ?><p class="text-muted mb-0">Your card top-ups will appear here.</p><?php endif; ?>
      <?php foreach ($history as $t): ?><div class="txn"><div class="ic in"><i class="fas fa-credit-card"></i></div><div class="d"><div class="n"><?= e($t['description']) ?></div><div class="m"><?= date('M j, g:i a', strtotime($t['created_at'])) ?></div></div><div class="a text-credit">+<?= money($t['amount']) ?></div></div><?php endforeach; ?>
    </div>
  </div>
</div>
<div class="modal fade" id="payModal" data-backdrop="static" tabindex="-1"><div class="modal-dialog modal-dialog-centered modal-sm"><div class="modal-content text-center p-4" style="border-radius:18px">
  <div class="spinner-border mx-auto mb-3" style="color:var(--forest-3)" role="status"></div><div class="font-weight-bold">Contacting your card issuer…</div><div class="small text-muted">Don't close this window.</div>
</div></div></div>
<?php
$extraScripts = <<<'HTML'
<script>
$(function () {
  $('.quick').on('click', function () { $('#amount').val($(this).data('v')); });
  $('#topupForm').on('submit', function (e) {
    e.preventDefault();
    var $a = $('#topupAlert').empty();
    $.post('/topup.php', $(this).serialize(), null, 'json').done(function (r) {
      if (!r.ok) { $a.html('<div class="alert alert-danger"></div>').find('.alert').text(r.error); return; }
      $('#payModal').modal('show');
      setTimeout(function () {
        $.ajax({url: '/api/topup_callback.php', method: 'POST', contentType: 'application/json',
          data: JSON.stringify({intent: r.intent, account: r.account, amount: r.amount, status: 'captured', card_last4: r.card, signature: r.signature})})
          .done(function (cb) {
            $('#payModal').modal('hide');
            if (cb.result === 'credited') { window.location = '/dashboard.php'; }
            else { $a.html('<div class="alert alert-danger">Your card was declined. Try another card.</div>'); }
          }).fail(function () { $('#payModal').modal('hide'); $a.html('<div class="alert alert-danger">Your card was declined. Try another card.</div>'); });
      }, 1600);
    });
  });
});
</script>
HTML;
include __DIR__ . '/includes/footer.php';
