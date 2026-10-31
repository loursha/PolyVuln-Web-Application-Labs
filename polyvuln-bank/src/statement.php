<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
$uid = (int)$_SESSION['uid'];
$acct = q1('SELECT a.*, u.full_name, u.address FROM accounts a JOIN users u ON u.id = a.user_id WHERE a.id = ? AND a.user_id = ?', [(int)($_GET['account'] ?? 0), $uid]);
$month = preg_match('/^\d{4}-\d{2}$/', $_GET['month'] ?? '') ? $_GET['month'] : date('Y-m', strtotime('first day of last month'));
if (!$acct) { redirect('/documents.php'); }
$start = $month . '-01 00:00:00';
$end = date('Y-m-t 23:59:59', strtotime($start));
$rows = qall('SELECT * FROM transactions WHERE account_id = ? AND created_at BETWEEN ? AND ? ORDER BY created_at, id', [$acct['id'], $start, $end]);
$open = q1('SELECT balance_after FROM transactions WHERE account_id = ? AND created_at < ? ORDER BY created_at DESC, id DESC LIMIT 1', [$acct['id'], $start]);
$opening = $open ? (float)$open['balance_after'] : 0.0;
$cr = array_sum(array_map(fn($r) => $r['direction'] === 'credit' ? $r['amount'] : 0, $rows));
$db = array_sum(array_map(fn($r) => $r['direction'] === 'debit' ? $r['amount'] : 0, $rows));
?><!doctype html>
<html lang="en"><head><?php include __DIR__ . '/includes/assets_head.php'; ?><title>Statement <?= e($month) ?> · PolyVuln Bank</title>
<style>body{background:#E9EEEC}.sheet{max-width:860px;margin:2rem auto;background:#fff;padding:3rem;border-radius:6px;box-shadow:0 10px 30px rgba(0,0,0,.08)}@media print{body{background:#fff}.sheet{box-shadow:none;margin:0}.no-print{display:none}}</style></head>
<body><div class="sheet">
  <div class="d-flex justify-content-between align-items-start mb-4">
    <div class="d-flex align-items-center"><img src="/assets/img/logo-mark.svg" width="44" class="mr-2" alt=""><div><div class="h5 mb-0 font-weight-bold">PolyVuln Bank, N.A.</div><div class="small text-muted">200 Market St, San Francisco, CA 94105</div></div></div>
    <div class="text-right"><div class="font-weight-bold">Account statement</div><div class="small text-muted"><?= date('F 1', strtotime($start)) ?> – <?= date('F t, Y', strtotime($start)) ?></div></div>
  </div>
  <div class="row mb-4"><div class="col-6"><div class="font-weight-bold"><?= e($acct['full_name']) ?></div><div class="small"><?= e($acct['address']) ?></div></div>
  <div class="col-6 text-right small"><div><?= e($acct['type']) ?> account <?= e($acct['account_number']) ?></div><div>Routing <?= ROUTING_NUMBER ?></div></div></div>
  <div class="row text-center mb-4">
    <div class="col"><div class="small text-muted">Opening balance</div><div class="h5"><?= money($opening) ?></div></div>
    <div class="col"><div class="small text-muted">Money in</div><div class="h5 text-credit">+<?= money($cr) ?></div></div>
    <div class="col"><div class="small text-muted">Money out</div><div class="h5">−<?= money($db) ?></div></div>
    <div class="col"><div class="small text-muted">Closing balance</div><div class="h5"><?= money($opening + $cr - $db) ?></div></div>
  </div>
  <table class="table table-sm table-pv"><thead><tr><th>Date</th><th>Description</th><th class="num">Amount</th><th class="num">Balance</th></tr></thead><tbody>
  <?php foreach ($rows as $r): ?><tr><td class="text-nowrap"><?= date('M j', strtotime($r['created_at'])) ?></td><td><?= e($r['description']) ?> <span class="text-muted small"><?= e($r['reference']) ?></span></td><td class="num <?= $r['direction'] === 'credit' ? 'text-credit' : '' ?>"><?= $r['direction'] === 'credit' ? '+' : '−' ?><?= money($r['amount']) ?></td><td class="num"><?= money($r['balance_after']) ?></td></tr><?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="4" class="text-center text-muted py-4">No activity this period.</td></tr><?php endif; ?>
  </tbody></table>
  <p class="small text-muted mt-4">Deposits are FDIC insured up to $250,000 per depositor. © PolyVuln 2026.</p>
  <div class="no-print text-center mt-3"><button class="btn btn-primary" onclick="window.print()">Print or save as PDF</button> <a href="/documents.php" class="btn btn-light">Back</a></div>
</div></body></html>
