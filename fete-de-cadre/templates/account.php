<div class="container py-5">
  <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
    <div><h1 class="mb-1">Hello, <?= e(explode(' ', $profile['full_name'])[0]) ?></h1><span class="text-muted">Customer since <?= substr((string)$profile['created_at'],0,4) ?><?php if ($profile['is_trade']): ?> · <span style="color:var(--clay-dark)"><i class="bi bi-award"></i> Trade account</span><?php endif; ?></span></div>
    <a class="btn btn-primary" href="/shop">Shop frames</a>
  </div>
  <div class="row g-3 mb-5">
    <div class="col-6 col-md-3"><div class="kpi"><div class="v"><?= count($orders) ?></div><div class="l">Orders</div></div></div>
    <div class="col-6 col-md-3"><div class="kpi"><div class="v"><?= money($spend) ?></div><div class="l">Total spent</div></div></div>
    <div class="col-6 col-md-3"><div class="kpi"><div class="v"><?= count(array_filter($orders, fn($o)=>in_array($o['status'],['placed','framing','shipped'],true))) ?></div><div class="l">In progress</div></div></div>
    <div class="col-6 col-md-3"><div class="kpi"><div class="v"><?= $profile['is_trade'] ? '15%' : '—' ?></div><div class="l">Trade discount</div></div></div>
  </div>

  <ul class="nav nav-underline mb-4 gap-3" role="tablist">
    <li class="nav-item" role="presentation"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#orders" type="button" role="tab">Orders</button></li>
    <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#settings" type="button" role="tab">Settings</button></li>
    <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#prefs" type="button" role="tab">Preferences</button></li>
  </ul>
  <div class="tab-content">
    <div class="tab-pane fade show active" id="orders" role="tabpanel">
      <?php if (!$orders): ?><div class="panel panel-cream text-center py-5"><h3>No orders yet</h3><p class="text-muted">When you order, tracking and invoices appear here.</p><a class="btn btn-primary" href="/shop">Shop frames</a></div>
      <?php else: ?>
      <div class="table-responsive panel p-0"><table class="table table-hover align-middle mb-0">
        <thead><tr><th class="ps-4">Order</th><th>Ship to</th><th>Placed</th><th>Status</th><th class="text-end">Total</th><th class="pe-4 text-end">Invoice</th></tr></thead>
        <tbody><?php foreach ($orders as $o): ?>
          <tr><td class="ps-4"><a href="/order/<?= e($o['order_number']) ?>"><?= e($o['order_number']) ?></a></td><td><?= e($o['ship_name']) ?></td><td><?= substr((string)$o['created_at'],0,10) ?></td>
          <td><span class="status status-<?= e($o['status']) ?>"><?= e(nice_status($o['status'])) ?></span></td><td class="text-end"><?= money($o['total']) ?></td>
          <td class="pe-4 text-end"><a class="btn btn-sm btn-outline-primary" href="/account/invoices/download?file=INV-<?= e($o['order_number']) ?>.pdf"><i class="bi bi-download"></i> PDF</a></td></tr>
        <?php endforeach; ?></tbody>
      </table></div><?php endif; ?>
    </div>
    <div class="tab-pane fade" id="settings" role="tabpanel">
      <div class="row g-4">
        <div class="col-lg-6">
          <form class="panel mb-4" method="post" action="/account/profile">
            <input type="hidden" name="_csrf" value="<?= e($__csrf) ?>">
            <h3 class="mb-3">Profile</h3>
            <div class="mb-3"><label class="form-label" for="full_name">Full name</label><input class="form-control" id="full_name" name="full_name" value="<?= e($profile['full_name']) ?>"></div>
            <div class="mb-3"><label class="form-label" for="phone">Phone</label><input class="form-control" id="phone" name="phone" value="<?= e($profile['phone']) ?>"></div>
            <div class="mb-3"><label class="form-label" for="address">Default shipping address</label><input class="form-control" id="address" name="address" value="<?= e($profile['address']) ?>"></div>
            <button class="btn btn-primary">Save profile</button>
          </form>
          <form class="panel" method="post" action="/account/email">
            <h3 class="mb-3">Email address</h3>
            <p class="text-muted">Order confirmations and reset codes go here.</p>
            <div class="mb-3"><label class="form-label" for="email">Email</label><input class="form-control" type="email" id="email" name="email" value="<?= e($profile['email']) ?>"></div>
            <button class="btn btn-primary">Update email</button>
          </form>
        </div>
        <div class="col-lg-6">
          <form class="panel mb-4" method="post" action="/account/password">
            <input type="hidden" name="_csrf" value="<?= e($__csrf) ?>">
            <h3 class="mb-3">Change password</h3>
            <div class="mb-3"><label class="form-label" for="current">Current password</label><input class="form-control" type="password" id="current" name="current" autocomplete="current-password"></div>
            <div class="mb-3"><label class="form-label" for="newpw">New password</label><input class="form-control" type="password" id="newpw" name="password" minlength="8" autocomplete="new-password"></div>
            <button class="btn btn-primary">Change password</button>
          </form>
          <form class="panel" method="post" action="/account/close" style="border-color:#e7cfc9">
            <input type="hidden" name="_csrf" value="<?= e($__csrf) ?>">
            <h3 class="mb-2">Close account</h3>
            <p class="text-muted">Your order history is kept for tax records. You won't be able to sign in again without contacting us.</p>
            <button class="btn" style="background:var(--wine);color:#fff" id="closeAccountBtn">Close my account</button>
          </form>
        </div>
      </div>
    </div>
    <div class="tab-pane fade" id="prefs" role="tabpanel">
      <form class="panel" id="prefsForm" style="max-width:640px">
        <h3 class="mb-3">Style &amp; notification preferences</h3>
        <div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" id="newsletter"><label class="form-check-label" for="newsletter">New arrivals newsletter</label></div>
        <div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" id="sms"><label class="form-check-label" for="sms">Text me when my order ships</label></div>
        <div class="mb-3"><label class="form-label" for="favoriteStyle">Favourite frame style</label><input class="form-control" id="favoriteStyle" maxlength="40" placeholder="e.g. Oak, gilt, black gallery"></div>
        <div class="mb-3"><label class="form-label" for="roomNotes">Room notes for your framer</label><input class="form-control" id="roomNotes" maxlength="200" placeholder="e.g. North-facing wall, warm lighting"></div>
        <button class="btn btn-primary" type="submit">Save preferences</button> <span id="prefsMsg" class="ms-2 text-muted"></span>
      </form>
    </div>
  </div>
</div>
<?php $__scripts = <<<'JS'
<script>
document.addEventListener('DOMContentLoaded', function () {
  if (location.hash) { var t = document.querySelector('[data-bs-target="' + location.hash + '"]'); if (t) bootstrap.Tab.getOrCreateInstance(t).show(); }
  var ids = ['newsletter','sms','favoriteStyle','roomNotes'];
  fetch('/api/v1/preferences').then(function(r){return r.json();}).then(function(p){
    ids.forEach(function(k){ var el=document.getElementById(k); if(el.type==='checkbox') el.checked=!!p[k]; else el.value=p[k]||''; });
  });
  document.getElementById('prefsForm').addEventListener('submit', function(e){
    e.preventDefault(); var body={};
    ids.forEach(function(k){ var el=document.getElementById(k); body[k]=el.type==='checkbox'?el.checked:el.value; });
    fetch('/api/v1/preferences',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':CSRF},body:JSON.stringify(body)})
      .then(function(r){return r.json();}).then(function(d){ document.getElementById('prefsMsg').textContent=d.saved?'Preferences saved.':(d.error||'Could not save.'); });
  });
});
</script>
JS;
$__scripts = str_replace('CSRF', json_encode($__csrf), $__scripts); ?>
