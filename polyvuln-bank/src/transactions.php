<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$uid = (int)$_SESSION['uid'];
$accounts = user_accounts($uid);
$ids = array_map('intval', array_column($accounts, 'id')) ?: [0];

$acct = (int)($_GET['account'] ?? 0);
$dir = in_array($_GET['dir'] ?? '', ['credit', 'debit'], true) ? $_GET['dir'] : '';
$from = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['from'] ?? '') ? $_GET['from'] : '';
$to = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['to'] ?? '') ? $_GET['to'] : '';
$q = isset($_GET['q']) ? pv_clean(trim((string)$_GET['q'])) : '';
$page = max(1, (int)($_GET['page'] ?? 1));
$per = 15;

$where = ['t.account_id IN (' . implode(',', $ids) . ')'];
$args = [];
if ($acct && in_array($acct, $ids, true)) { $where[] = 't.account_id = ?'; $args[] = $acct; }
if ($dir) { $where[] = 't.direction = ?'; $args[] = $dir; }
if ($from) { $where[] = 't.created_at >= ?'; $args[] = $from . ' 00:00:00'; }
if ($to) { $where[] = 't.created_at <= ?'; $args[] = $to . ' 23:59:59'; }
if ($q !== '') {
    // Match the search term against the merchant / description text.
    $where[] = "t.counterparty_name LIKE '%$q%'";
}
$w = implode(' AND ', $where);
$count = (int)q1("SELECT COUNT(*) c FROM transactions t WHERE $w", $args)['c'];
$sum = q1("SELECT COALESCE(SUM(CASE WHEN direction='credit' THEN amount END),0) cr, COALESCE(SUM(CASE WHEN direction='debit' THEN amount END),0) db FROM transactions t WHERE $w", $args);
$rows = qall("SELECT t.*, a.account_number FROM transactions t JOIN accounts a ON a.id = t.account_id WHERE $w ORDER BY t.created_at DESC, t.id DESC LIMIT $per OFFSET " . (($page - 1) * $per), $args);
$pages = max(1, (int)ceil($count / $per));
$qs = function (array $o) { return '?' . http_build_query(array_merge($_GET, $o)); };

$pageTitle = 'Transactions';
$active = 'transactions';
include __DIR__ . '/includes/header.php';
?>
<div class="page-head">
  <div><h1>Transactions</h1><p><?= number_format($count) ?> transactions · <span class="text-credit">+<?= money($sum['cr']) ?></span> in · <span><?= money($sum['db']) ?></span> out</p></div>
  <a href="/documents.php" class="btn btn-light"><i class="far fa-file-pdf mr-2"></i>Statements</a>
</div>

<div class="panel">
  <form class="form-row align-items-end" method="get">
    <div class="col-md-4 mb-2"><label for="q">Search</label><input class="form-control" id="q" name="q" value="<?= e($q) ?>" placeholder="Merchant, person, memo or reference"></div>
    <div class="col-md-2 col-6 mb-2"><label for="account">Account</label>
      <select class="custom-select" id="account" name="account"><option value="">All accounts</option>
        <?php foreach ($accounts as $a): ?><option value="<?= (int)$a['id'] ?>" <?= $acct === (int)$a['id'] ? 'selected' : '' ?>><?= e($a['type'] . ' ' . mask_account($a['account_number'])) ?></option><?php endforeach; ?>
      </select></div>
    <div class="col-md-2 col-6 mb-2"><label for="dir">Type</label>
      <select class="custom-select" id="dir" name="dir"><option value="">Money in and out</option><option value="credit" <?= $dir === 'credit' ? 'selected' : '' ?>>Money in</option><option value="debit" <?= $dir === 'debit' ? 'selected' : '' ?>>Money out</option></select></div>
    <div class="col-md-2 col-6 mb-2"><label for="from">From</label><input type="date" class="form-control" id="from" name="from" value="<?= e($from) ?>"></div>
    <div class="col-md-2 col-6 mb-2"><label for="to">To</label><input type="date" class="form-control" id="to" name="to" value="<?= e($to) ?>"></div>
    <div class="col-12 mt-1"><button class="btn btn-primary mr-2">Apply filters</button><a href="/transactions.php" class="btn btn-light">Clear</a></div>
  </form>
</div>

<div class="panel p-0 overflow-hidden">
  <?php if ($q !== ''): ?>
    <div class="px-4 pt-4 pb-2"><h2 class="h6 mb-0">Results for “<?= $q ?>”</h2></div>
  <?php endif; ?>
  <?php if (!$rows): ?>
    <div class="empty"><i class="fas fa-search"></i><div class="font-weight-bold">No transactions match “<?= $q ?>”</div><div class="small">Try a different merchant name or widen the date range.</div></div>
  <?php else: ?>
  <div class="table-responsive">
    <table class="table table-pv">
      <thead><tr><th class="pl-4">Date</th><th>Description</th><th>Category</th><th>Account</th><th class="num">Amount</th><th class="num pr-4">Balance</th></tr></thead>
      <tbody>
      <?php foreach ($rows as $t): $inc = $t['direction'] === 'credit'; ?>
        <tr>
          <td class="pl-4 text-nowrap"><div class="font-weight-bold"><?= date('M j, Y', strtotime($t['created_at'])) ?></div><div class="small text-muted"><?= date('g:i a', strtotime($t['created_at'])) ?></div></td>
          <td><a class="font-weight-bold text-reset" href="/receipt.php?id=<?= (int)$t['id'] ?>"><?= e($t['counterparty_name'] ?: $t['description']) ?></a>
            <div class="small text-muted"><?= e($t['description']) ?> · <?= e($t['reference']) ?></div>
            <?php if ($t['remarks']): ?><div class="small" style="color:var(--slate)"><i class="far fa-comment-dots mr-1"></i><?= $t['remarks'] ?></div><?php endif; ?></td>
          <td><span class="badge-soft badge-neutral"><i class="fas <?= category_icon($t['category']) ?> mr-1"></i><?= e($t['category']) ?></span></td>
          <td class="text-muted text-nowrap"><?= e(mask_account($t['account_number'])) ?></td>
          <td class="num <?= $inc ? 'text-credit' : '' ?>"><?= $inc ? '+' : '−' ?><?= money($t['amount']) ?></td>
          <td class="num pr-4 text-muted"><?= $t['balance_after'] !== null ? money($t['balance_after']) : '—' ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php if ($pages > 1): ?>
  <nav class="px-4 py-3 d-flex justify-content-between align-items-center border-top">
    <span class="small text-muted">Page <?= $page ?> of <?= $pages ?></span>
    <ul class="pagination pagination-sm mb-0">
      <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>"><a class="page-link" href="<?= e($qs(['page' => $page - 1])) ?>">Previous</a></li>
      <li class="page-item <?= $page >= $pages ? 'disabled' : '' ?>"><a class="page-link" href="<?= e($qs(['page' => $page + 1])) ?>">Next</a></li>
    </ul>
  </nav>
  <?php endif; ?>
  <?php endif; ?>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
