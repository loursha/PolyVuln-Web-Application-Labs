<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$stats = q1("SELECT (SELECT COUNT(*) FROM users WHERE role='customer') customers,
                    (SELECT COALESCE(SUM(balance),0) FROM accounts) deposits,
                    (SELECT COUNT(*) FROM transactions WHERE created_at >= CURDATE()) today,
                    (SELECT COUNT(*) FROM tickets WHERE status <> 'Resolved') open_tickets");
$daily = qall("SELECT DATE(created_at) d, SUM(CASE WHEN direction='credit' THEN amount ELSE 0 END) cr, SUM(CASE WHEN direction='debit' THEN amount ELSE 0 END) db
               FROM transactions WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) GROUP BY DATE(created_at) ORDER BY d");
$large = qall("SELECT t.*, a.account_number, u.full_name FROM transactions t JOIN accounts a ON a.id=t.account_id JOIN users u ON u.id=a.user_id
               WHERE t.amount >= 1000 ORDER BY t.created_at DESC LIMIT 8");
$mix = qall("SELECT type, COUNT(*) n, SUM(balance) total FROM accounts GROUP BY type");

$pageTitle = 'Operations console';
$active = 'admin';
include __DIR__ . '/../includes/header.php';
?>
<div class="page-head"><div><h1>Operations console</h1><p>Branch network overview · <?= date('l, F j, Y') ?></p></div></div>
<div class="row">
  <div class="col-md-3 col-6"><div class="panel"><div class="stat"><span>Customers</span><strong><?= number_format($stats['customers']) ?></strong></div></div></div>
  <div class="col-md-3 col-6"><div class="panel"><div class="stat"><span>Total deposits</span><strong><?= money($stats['deposits']) ?></strong></div></div></div>
  <div class="col-md-3 col-6"><div class="panel"><div class="stat"><span>Transactions today</span><strong><?= number_format($stats['today']) ?></strong></div></div></div>
  <div class="col-md-3 col-6"><div class="panel"><div class="stat"><span>Open service tickets</span><strong><?= number_format($stats['open_tickets']) ?></strong></div></div></div>
</div>
<div class="row">
  <div class="col-xl-8"><div class="panel"><div class="panel-head"><h2>Daily flows · 30 days</h2></div><div style="height:280px"><canvas id="flowChart"></canvas></div></div></div>
  <div class="col-xl-4"><div class="panel"><div class="panel-head"><h2>Deposit mix</h2></div><div style="height:280px"><canvas id="mixChart"></canvas></div></div></div>
</div>
<div class="panel p-0 overflow-hidden">
  <div class="px-4 pt-4"><div class="panel-head"><h2>Large-value activity (≥ $1,000)</h2><a href="/admin/customers.php">All customers</a></div></div>
  <div class="table-responsive"><table class="table table-pv">
    <thead><tr><th class="pl-4">When</th><th>Customer</th><th>Account</th><th>Description</th><th class="num pr-4">Amount</th></tr></thead><tbody>
    <?php foreach ($large as $t): ?><tr><td class="pl-4 text-nowrap"><?= date('M j, g:i a', strtotime($t['created_at'])) ?></td><td><?= e($t['full_name']) ?></td><td><?= e($t['account_number']) ?></td><td><?= e($t['description']) ?></td><td class="num pr-4 <?= $t['direction'] === 'credit' ? 'text-credit' : '' ?>"><?= $t['direction'] === 'credit' ? '+' : '−' ?><?= money($t['amount']) ?></td></tr><?php endforeach; ?>
    </tbody></table></div>
</div>
<?php
$l = json_encode(array_map(fn($r) => date('M j', strtotime($r['d'])), $daily));
$cr = json_encode(array_map(fn($r) => (float)$r['cr'], $daily));
$db = json_encode(array_map(fn($r) => (float)$r['db'], $daily));
$ml = json_encode(array_column($mix, 'type'));
$mv = json_encode(array_map('floatval', array_column($mix, 'total')));
$extraScripts = <<<HTML
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
Chart.defaults.font.family='Manrope, sans-serif';Chart.defaults.color='#5A6A66';
new Chart(document.getElementById('flowChart'),{type:'bar',data:{labels:$l,datasets:[{label:'Money in',data:$cr,backgroundColor:'#1E6B60',borderRadius:4},{label:'Money out',data:$db,backgroundColor:'#D4A85A',borderRadius:4}]},options:{maintainAspectRatio:false,plugins:{legend:{position:'bottom',labels:{usePointStyle:true,boxWidth:8}}},scales:{x:{grid:{display:false}},y:{grid:{color:'#EEF2F0'}}}}});
new Chart(document.getElementById('mixChart'),{type:'doughnut',data:{labels:$ml,datasets:[{data:$mv,backgroundColor:['#0E3B34','#B8873B'],borderWidth:0}]},options:{maintainAspectRatio:false,cutout:'70%',plugins:{legend:{position:'bottom'}}}});
</script>
HTML;
include __DIR__ . '/../includes/footer.php';
