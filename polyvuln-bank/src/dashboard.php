<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$uid = (int)$_SESSION['uid'];
$accounts = user_accounts($uid);
$primary = null;
foreach ($accounts as $a) { if ($a['type'] === 'Checking') { $primary = $a; break; } }
$primary = $primary ?: ($accounts[0] ?? null);
$total = array_sum(array_column($accounts, 'balance'));
$ids = array_column($accounts, 'id') ?: [0];
$in = implode(',', array_map('intval', $ids));

$monthStart = date('Y-m-01 00:00:00');
$flows = q1("SELECT COALESCE(SUM(CASE WHEN direction='credit' AND category<>'Transfers' THEN amount END),0) inflow,
                    COALESCE(SUM(CASE WHEN direction='debit' AND category<>'Transfers' THEN amount END),0) outflow
             FROM transactions WHERE account_id IN ($in) AND created_at >= ?", [$monthStart]);
$cats = qall("SELECT category, SUM(amount) total FROM transactions WHERE account_id IN ($in) AND direction='debit'
              AND category NOT IN ('Transfers') AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) GROUP BY category ORDER BY total DESC LIMIT 6");
$trend = $primary ? qall("SELECT DATE(created_at) d, SUBSTRING_INDEX(GROUP_CONCAT(balance_after ORDER BY created_at DESC, id DESC), ',', 1) bal
              FROM transactions WHERE account_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 75 DAY) GROUP BY DATE(created_at) ORDER BY d", [$primary['id']]) : [];
$recent = qall("SELECT t.*, a.account_number, a.type FROM transactions t JOIN accounts a ON a.id = t.account_id
                WHERE t.account_id IN ($in) ORDER BY t.created_at DESC, t.id DESC LIMIT 8");
$payees = qall('SELECT * FROM beneficiaries WHERE user_id = ? ORDER BY id DESC LIMIT 5', [$uid]);
$notices = qall('SELECT * FROM notices ORDER BY id DESC LIMIT 3');
$first = explode(' ', $_SESSION['full_name'] ?? 'there')[0];
$hour = (int)date('G');
$greet = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');

$pageTitle = 'Overview';
$active = 'dashboard';
include __DIR__ . '/includes/header.php';
?>
<div class="page-head">
  <div><h1><?= $greet ?>, <?= e($first) ?></h1><p>Here's where your money stands today, <?= date('l, F j') ?>.</p></div>
  <div><a href="/transfer.php" class="btn btn-primary"><i class="fas fa-paper-plane mr-2"></i>Send money</a></div>
</div>

<div class="row">
  <div class="col-xl-8">
    <?php if ($primary): ?>
    <div class="acct-hero mb-4 reveal">
      <div class="d-flex justify-content-between align-items-start">
        <div>
          <div class="label"><?= e($primary['nickname'] ?: $primary['type']) ?> · <?= e($primary['type']) ?></div>
          <div class="balance mt-2"><span class="js-amt"><?= money($primary['balance']) ?></span><span class="js-amt d-none">$ ••••••</span><small>available</small></div>
        </div>
        <button class="btn btn-ghost btn-sm" id="toggleBalance" aria-label="Hide balances"><i class="far fa-eye"></i></button>
      </div>
      <div class="d-flex flex-wrap justify-content-between align-items-end">
        <div class="meta mb-2">
          <div><span>Account number</span><b><?= e($primary['account_number']) ?></b> <a href="#" class="text-white-50 small ml-1" data-copy="<?= e($primary['account_number']) ?>"><i class="far fa-copy"></i></a></div>
          <div><span>Routing</span><b><?= ROUTING_NUMBER ?></b></div>
          <div><span>Total across accounts</span><b><?= money($total) ?></b></div>
        </div>
        <div class="actions mb-2">
          <a href="/topup.php" class="btn btn-ghost btn-sm"><i class="fas fa-plus mr-1"></i>Add money</a>
          <a href="/transactions.php?account=<?= (int)$primary['id'] ?>" class="btn btn-ghost btn-sm">Activity</a>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <div class="row">
      <div class="col-sm-4"><div class="panel"><div class="stat"><span>Money in · <?= date('M') ?></span><strong class="text-credit"><?= money($flows['inflow']) ?></strong><em class="text-muted">Salary, interest and deposits</em></div></div></div>
      <div class="col-sm-4"><div class="panel"><div class="stat"><span>Money out · <?= date('M') ?></span><strong><?= money($flows['outflow']) ?></strong><em class="text-muted">Bills and card spending</em></div></div></div>
      <div class="col-sm-4"><div class="panel"><div class="stat"><span>Net this month</span><strong class="<?= $flows['inflow'] - $flows['outflow'] >= 0 ? 'text-credit' : 'text-debit' ?>"><?= money($flows['inflow'] - $flows['outflow']) ?></strong><em class="text-muted">Excludes your own transfers</em></div></div></div>
    </div>

    <div class="panel">
      <div class="panel-head"><h2>Balance history</h2><span class="text-muted small"><?= e($primary ? mask_account($primary['account_number']) : '') ?> · last 75 days</span></div>
      <div style="height:240px"><canvas id="trendChart"></canvas></div>
    </div>

    <div class="panel">
      <div class="panel-head"><h2>Recent activity</h2><a href="/transactions.php">See all transactions</a></div>
      <?php if (!$recent): ?><div class="empty"><i class="fas fa-receipt"></i>No transactions yet. Add money to get started.</div><?php endif; ?>
      <?php foreach ($recent as $t): $inc = $t['direction'] === 'credit'; ?>
        <a class="txn text-reset text-decoration-none" href="/receipt.php?id=<?= (int)$t['id'] ?>">
          <div class="ic <?= $inc ? 'in' : '' ?>"><i class="fas <?= category_icon($t['category']) ?>"></i></div>
          <div class="d">
            <div class="n"><?= e($t['counterparty_name'] ?: $t['description']) ?></div>
            <div class="m"><?= e($t['category']) ?> · <?= date('M j, g:i a', strtotime($t['created_at'])) ?> · <?= e(mask_account($t['account_number'])) ?></div>
            <?php if ($t['remarks']): ?><div class="memo"><i class="far fa-comment-dots mr-1"></i><?= $t['remarks'] ?></div><?php endif; ?>
          </div>
          <div class="a <?= $inc ? 'text-credit' : '' ?>"><?= $inc ? '+' : '−' ?><?= money($t['amount']) ?><small><?= e($t['channel']) ?></small></div>
        </a>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="col-xl-4">
    <div class="panel">
      <div class="panel-head"><h2>Your accounts</h2><a href="/documents.php">Statements</a></div>
      <?php foreach ($accounts as $a): ?>
        <a class="acct-tile" href="/transactions.php?account=<?= (int)$a['id'] ?>">
          <div class="ic <?= $a['type'] === 'Savings' ? 'sav' : '' ?>"><i class="fas <?= $a['type'] === 'Savings' ? 'fa-piggy-bank' : 'fa-wallet' ?>"></i></div>
          <div><div class="t"><?= e($a['nickname'] ?: $a['type']) ?></div><div class="s"><?= e($a['type']) ?> <?= e(mask_account($a['account_number'])) ?></div></div>
          <div class="amt"><?= money($a['balance']) ?></div>
        </a>
      <?php endforeach; ?>
    </div>

    <div class="panel">
      <div class="panel-head"><h2>Spending by category</h2><span class="text-muted small">30 days</span></div>
      <?php if ($cats): ?><div style="height:220px"><canvas id="catChart"></canvas></div>
      <?php else: ?><div class="empty py-3">Card spending will show up here.</div><?php endif; ?>
    </div>

    <div class="panel">
      <div class="panel-head"><h2>Pay someone again</h2><a href="/beneficiaries.php">Manage</a></div>
      <?php if (!$payees): ?><p class="text-muted mb-0">Save people you pay often to send money faster.</p><?php endif; ?>
      <?php foreach ($payees as $p): ?>
        <div class="d-flex align-items-center py-2">
          <div class="ic mr-3" style="width:38px;height:38px;border-radius:50%;background:#E5F0EE;color:var(--forest-3);display:flex;align-items:center;justify-content:center;font-weight:800;font-size:.8rem"><?= e(initials($p['holder_name'])) ?></div>
          <div class="flex-fill"><div class="font-weight-bold"><?= e($p['nickname']) ?></div><div class="small text-muted"><?= e($p['bank_name']) ?> <?= e(mask_account($p['account_number'])) ?></div></div>
          <a href="/transfer.php?payee=<?= (int)$p['id'] ?>" class="btn btn-light btn-sm">Pay</a>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="panel">
      <div class="panel-head"><h2>From PolyVuln Bank</h2></div>
      <?php foreach ($notices as $n): ?>
        <div class="mb-3"><div class="font-weight-bold"><?= e($n['title']) ?></div><div class="small text-muted"><?= e($n['body']) ?></div></div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<?php
$trendLabels = json_encode(array_map(fn($r) => date('M j', strtotime($r['d'])), $trend));
$trendData = json_encode(array_map(fn($r) => (float)$r['bal'], $trend));
$catLabels = json_encode(array_column($cats, 'category'));
$catData = json_encode(array_map('floatval', array_column($cats, 'total')));
$extraScripts = <<<HTML
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
Chart.defaults.font.family = 'Manrope, sans-serif';
Chart.defaults.color = '#5A6A66';
var tc = document.getElementById('trendChart');
if (tc) {
  var g = tc.getContext('2d').createLinearGradient(0, 0, 0, 240);
  g.addColorStop(0, 'rgba(30,107,96,.25)'); g.addColorStop(1, 'rgba(30,107,96,0)');
  new Chart(tc, {type: 'line', data: {labels: $trendLabels, datasets: [{data: $trendData, borderColor: '#1E6B60', backgroundColor: g, fill: true, tension: .35, pointRadius: 0, borderWidth: 2.5}]},
    options: {maintainAspectRatio: false, plugins: {legend: {display: false}, tooltip: {callbacks: {label: function (c) { return '$' + c.parsed.y.toLocaleString(undefined, {minimumFractionDigits: 2}); }}}},
      scales: {x: {grid: {display: false}, ticks: {maxTicksLimit: 7}}, y: {grid: {color: '#EEF2F0'}, ticks: {callback: function (v) { return '$' + (v / 1000).toFixed(1) + 'k'; }}}}}});
}
var cc = document.getElementById('catChart');
if (cc) new Chart(cc, {type: 'doughnut', data: {labels: $catLabels, datasets: [{data: $catData, backgroundColor: ['#0E3B34', '#1E6B60', '#B8873B', '#6FA595', '#D4A85A', '#B5C4BF'], borderWidth: 0}]},
  options: {maintainAspectRatio: false, cutout: '68%', plugins: {legend: {position: 'right', labels: {boxWidth: 10, usePointStyle: true}}}}});
</script>
HTML;
include __DIR__ . '/includes/footer.php';
