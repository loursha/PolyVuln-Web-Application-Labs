<h1 class="h2 mb-4">Order queue</h1>
<div class="row g-3 mb-4">
  <div class="col-6 col-xl-3"><div class="kpi"><div class="v"><?= (int)($counts['placed'] ?? 0) ?></div><div class="l">New orders</div></div></div>
  <div class="col-6 col-xl-3"><div class="kpi"><div class="v"><?= (int)($counts['framing'] ?? 0) ?></div><div class="l">In framing</div></div></div>
  <div class="col-6 col-xl-3"><div class="kpi"><div class="v"><?= (int)($counts['shipped'] ?? 0) ?></div><div class="l">With the courier</div></div></div>
  <div class="col-6 col-xl-3"><div class="kpi"><div class="v"><?= (int)($counts['delivered'] ?? 0) ?></div><div class="l">Delivered</div></div></div>
</div>
<div class="row g-4">
  <div class="col-xl-8"><div class="panel p-0"><div class="table-responsive"><table class="table align-middle mb-0">
    <thead><tr><th class="ps-3">Order</th><th>Pieces</th><th>Ship to</th><th>Gift note</th><th class="pe-3">Status</th></tr></thead>
    <tbody>
      <?php if (!$orders): ?><tr><td colspan="5" class="text-center text-muted py-4">Nothing to frame. New orders appear here as they come in.</td></tr><?php endif; ?>
      <?php foreach ($orders as $o): ?><tr>
        <td class="ps-3"><strong><?= e($o['order_number']) ?></strong><br><small class="text-muted"><?= e($o['username']) ?> · <?= e($o['shipping_method']) ?></small></td>
        <td class="small"><?php foreach ($o['lines'] as $l): ?><?= (int)$l['qty'] ?> × <?= e($l['product_name']) ?> (<?= e($l['size']) ?>)<br><?php endforeach; ?></td>
        <td class="small"><?= e($o['ship_name']) ?><br><span class="text-muted"><?= e($o['ship_address']) ?></span></td>
        <td class="small"><?= e($o['gift_note'] ?: '—') ?></td>
        <td class="pe-3"><form method="post" action="/staff/orders/<?= (int)$o['id'] ?>/status" class="d-flex gap-1"><input type="hidden" name="_csrf" value="<?= e($__csrf) ?>">
          <select name="status" class="form-select form-select-sm" aria-label="Status"><?php foreach ($statuses as $s): ?><option value="<?= e($s) ?>" <?= $s === $o['status'] ? 'selected' : '' ?>><?= e(nice_status($s)) ?></option><?php endforeach; ?></select>
          <button class="btn btn-sm btn-primary">Save</button></form></td>
      </tr><?php endforeach; ?>
    </tbody></table></div></div></div>
  <div class="col-xl-4">
    <div class="panel mb-4"><h2 class="h5 mb-3">Low stock</h2><?php if (!$low): ?><p class="text-muted mb-0">Every frame has 10 or more in stock.</p><?php endif; ?><?php foreach ($low as $p): ?><div class="d-flex justify-content-between mb-1"><span><?= e($p['name']) ?></span><span class="stock-low"><?= (int)$p['stock'] ?></span></div><?php endforeach; ?></div>
    <div class="panel"><h2 class="h5 mb-3">Custom requests</h2><?php if (!$custom): ?><p class="text-muted mb-0">No custom framing requests yet.</p><?php endif; ?><?php foreach ($custom as $r): ?><div class="border-bottom pb-2 mb-2"><strong><?= e($r['full_name']) ?></strong> · <?= e($r['frame_style']) ?> · $<?= (int)$r['budget'] ?><br><small class="text-muted"><?= e($r['dimensions']) ?><?= $r['notes'] ? ' — ' . e($r['notes']) : '' ?></small></div><?php endforeach; ?></div>
  </div>
</div>
