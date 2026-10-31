<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$t = q1('SELECT t.*, a.account_number, a.type acct_type, u.full_name owner, u.address owner_address
         FROM transactions t JOIN accounts a ON a.id = t.account_id JOIN users u ON u.id = a.user_id
         WHERE t.id = ?', [(int)($_GET['id'] ?? 0)]);

$pageTitle = 'Transaction details';
$active = 'transactions';
include __DIR__ . '/includes/header.php';
if (!$t): ?>
  <div class="panel empty"><i class="fas fa-receipt"></i><h1 class="h5">We couldn't find that transaction</h1><a href="/transactions.php">Back to transactions</a></div>
<?php else: $inc = $t['direction'] === 'credit'; ?>
<div class="page-head">
  <div><h1>Transaction details</h1><p>Reference <?= e($t['reference']) ?></p></div>
  <div><button class="btn btn-light" onclick="window.print()"><i class="fas fa-print mr-2"></i>Print receipt</button></div>
</div>
<div class="row">
  <div class="col-lg-7">
    <div class="panel">
      <div class="text-center py-3">
        <div class="ic mx-auto mb-3" style="width:64px;height:64px;border-radius:18px;background:<?= $inc ? '#E3F3EA' : '#F0F4F2' ?>;display:flex;align-items:center;justify-content:center;font-size:1.5rem;color:<?= $inc ? 'var(--credit)' : 'var(--slate)' ?>"><i class="fas <?= category_icon($t['category']) ?>"></i></div>
        <div class="h2 font-weight-bold mb-1 <?= $inc ? 'text-credit' : '' ?>"><?= $inc ? '+' : '−' ?><?= money($t['amount']) ?></div>
        <div class="text-muted"><?= e($t['counterparty_name'] ?: $t['description']) ?></div>
        <span class="badge-soft <?= $t['status'] === 'Completed' ? 'badge-ok' : 'badge-wait' ?> d-inline-block mt-2"><?= e($t['status']) ?></span>
      </div>
      <div class="kv"><span>Date</span><b><?= date('l, F j, Y \a\t g:i a', strtotime($t['created_at'])) ?></b></div>
      <div class="kv"><span>Description</span><b><?= e($t['description']) ?></b></div>
      <div class="kv"><span>Account</span><b><?= e($t['owner']) ?> · <?= e($t['acct_type']) ?> <?= e($t['account_number']) ?></b></div>
      <div class="kv"><span>Counterparty</span><b><?= e($t['counterparty_name'] ?: '—') ?><?= $t['counterparty_account'] ? ' · ' . e($t['counterparty_account']) : '' ?></b></div>
      <div class="kv"><span>Category</span><b><?= e($t['category']) ?></b></div>
      <div class="kv"><span>Channel</span><b><?= e($t['channel']) ?></b></div>
      <div class="kv"><span>Balance after</span><b><?= $t['balance_after'] !== null ? money($t['balance_after']) : '—' ?></b></div>
      <?php if ($t['remarks']): ?><div class="kv"><span>Memo</span><b><?= $t['remarks'] ?></b></div><?php endif; ?>
    </div>
  </div>
  <div class="col-lg-5">
    <div class="panel">
      <h2 class="h6">Something not right?</h2>
      <p class="text-muted small">If you don't recognise this transaction, tell us within 60 days of your statement date and we'll investigate.</p>
      <a href="/support.php?subject=<?= urlencode('Question about ' . $t['reference']) ?>&category=Disputes" class="btn btn-outline-primary btn-block">Dispute this transaction</a>
    </div>
  </div>
</div>
<?php endif;
include __DIR__ . '/includes/footer.php';
