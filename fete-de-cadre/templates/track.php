<div class="container py-5" style="max-width:860px">
  <div class="eyebrow mb-1">Order status</div>
  <h1 class="mb-2">Track your order</h1>
  <p class="text-muted mb-4">Enter the order number from your confirmation email, for example FC-30411.</p>
  <form class="d-flex gap-2 mb-4" method="get" action="/track">
    <label for="order" class="visually-hidden">Order number</label>
    <input class="form-control form-control-lg" id="order" name="order" value="<?= e($ref) ?>" placeholder="FC-30411" required>
    <button class="btn btn-primary btn-lg px-4">Track</button>
  </form>
  <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
  <?php if ($results !== null && !$results): ?><div class="alert alert-danger">We couldn't find an order with that number. Check your confirmation email and try again.</div><?php endif; ?>
  <?php $steps = ['placed','framing','shipped','delivered']; ?>
  <?php foreach (($results ?: []) as $o): ?>
    <div class="panel mb-3">
      <div class="d-flex justify-content-between flex-wrap gap-2 mb-3">
        <div><h3 class="mb-0"><?= e($o['order_number']) ?></h3><span class="text-muted">For <?= e($o['ship_name']) ?> · <?= e($o['shipping_method']) ?> delivery</span></div>
        <div class="text-end"><span class="status status-<?= e($o['status']) ?>"><?= e(nice_status($o['status'])) ?></span><div class="fw-semibold mt-1"><?= is_numeric($o['total']) ? money($o['total']) : e((string)$o['total']) ?></div></div>
      </div>
      <?php $idx = array_search($o['status'], $steps, true); if ($idx !== false): ?>
      <div class="progress rounded-0" role="progressbar" aria-label="Order progress" aria-valuenow="<?= $idx + 1 ?>" aria-valuemin="0" aria-valuemax="4" style="height:6px"><div class="progress-bar" style="width:<?= ($idx + 1) * 25 ?>%;background:var(--clay)"></div></div>
      <div class="d-flex justify-content-between small text-muted mt-2"><span>Placed</span><span>Framing</span><span>Shipped</span><span>Delivered</span></div>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
</div>
