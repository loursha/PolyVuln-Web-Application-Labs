<div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-4">
  <div><h1 class="h2 mb-1">Dashboard</h1><p class="text-muted mb-0">Sales for the last 14 days. FLAG{admin_dashboard_unlocked}</p></div>
</div>
<div class="row g-3 mb-4">
  <div class="col-6 col-xl-3"><div class="kpi"><div class="v"><?= money($kpi['revenue']) ?></div><div class="l">Revenue</div></div></div>
  <div class="col-6 col-xl-3"><div class="kpi"><div class="v"><?= (int)$kpi['orders'] ?></div><div class="l">Orders</div></div></div>
  <div class="col-6 col-xl-3"><div class="kpi"><div class="v"><?= money($kpi['aov']) ?></div><div class="l">Average order</div></div></div>
  <div class="col-6 col-xl-3"><div class="kpi"><div class="v"><?= (int)$customers ?></div><div class="l">Customers</div></div></div>
</div>
<div class="row g-4 mb-4">
  <div class="col-xl-8"><div class="panel h-100"><h2 class="h5">Daily revenue</h2><div style="height:280px"><canvas id="revChart" role="img" aria-label="Daily revenue chart"></canvas></div></div></div>
  <div class="col-xl-4"><div class="panel h-100"><h2 class="h5">Revenue by category</h2><div style="height:280px"><canvas id="catChart" role="img" aria-label="Revenue by category chart"></canvas></div></div></div>
</div>
<div class="row g-4">
  <div class="col-xl-8"><div class="panel p-0"><div class="p-3 pb-0"><h2 class="h5">Recent orders</h2></div><div class="table-responsive"><table class="table align-middle mb-0">
    <thead><tr><th class="ps-3">Order</th><th>Customer</th><th>Status</th><th class="text-end pe-3">Total</th></tr></thead>
    <tbody><?php foreach ($recent as $o): ?><tr><td class="ps-3"><?= e($o['order_number']) ?></td><td><?= e($o['username']) ?></td><td><span class="status status-<?= e($o['status']) ?>"><?= e(nice_status($o['status'])) ?></span></td><td class="text-end pe-3"><?= money($o['total']) ?></td></tr><?php endforeach; ?></tbody>
  </table></div></div></div>
  <div class="col-xl-4"><div class="panel"><h2 class="h5 mb-3">Best sellers</h2><?php foreach ($top as $t): ?><div class="d-flex justify-content-between mb-2"><span><?= e($t['product_name']) ?></span><strong><?= (int)$t['units'] ?></strong></div><?php endforeach; ?></div></div>
</div>
<?php
$__days = json_encode($days);
$__cats = json_encode($byCat);
$__scripts = '<script src="/assets/vendor/chart.umd.min.js"></script><script>'
  . 'document.addEventListener("DOMContentLoaded",function(){'
  . 'var days=' . $__days . ',cats=' . $__cats . ';'
  . 'Chart.defaults.font.family="Manrope, system-ui, sans-serif";Chart.defaults.color="#6f6557";'
  . 'new Chart(document.getElementById("revChart"),{type:"bar",data:{labels:days.map(function(d){return d.d.slice(5);}),datasets:[{label:"Revenue ($)",data:days.map(function(d){return d.revenue;}),backgroundColor:"#3f332a"}]},options:{maintainAspectRatio:false,plugins:{legend:{display:false}},scales:{y:{beginAtZero:true,grid:{color:"#efe9dd"}},x:{grid:{display:false}}}}});'
  . 'new Chart(document.getElementById("catChart"),{type:"doughnut",data:{labels:cats.map(function(c){return c.category;}),datasets:[{data:cats.map(function(c){return Number(c.revenue);}),backgroundColor:["#3f332a","#b06a3f","#b08a4f","#9a3b34","#7a6a52","#c9b79a","#55473a"],borderWidth:0}]},options:{maintainAspectRatio:false,cutout:"64%",plugins:{legend:{position:"bottom"}}}});'
  . '});</script>';
?>
