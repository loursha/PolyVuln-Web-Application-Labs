<div class="container py-5" style="max-width:820px">
  <div class="text-center mb-5">
    <h1>Thank you. Your order is in.</h1>
    <p class="text-muted">Order <strong><?= e($order['order_number']) ?></strong> ships <?= $order['shipping_method'] === 'express' ? 'tomorrow by express courier' : 'within 3-5 days' ?> to <?= e($order['ship_name']) ?>.</p>
  </div>
  <div class="panel">
    <?php foreach ($items as $i): ?><div class="d-flex justify-content-between mb-2"><span><?= (int) $i['qty'] ?> × <?= e($i['product_name']) ?> (<?= e($i['size']) ?>)</span><span><?= money($i['unit_price'] * $i['qty']) ?></span></div><?php endforeach; ?>
    <hr><div class="d-flex justify-content-between fw-semibold fs-5"><span>Total paid</span><span><?= money($order['total']) ?></span></div>
  </div>
  <div class="d-flex gap-2 justify-content-center mt-4">
    <a class="btn btn-primary" href="/account/invoices/download?file=INV-<?= e($order['order_number']) ?>.pdf"><i class="bi bi-file-earmark-pdf"></i> Download invoice</a>
    <a class="btn btn-outline-primary" href="/track?order=<?= e($order['order_number']) ?>">Track order</a>
  </div>
</div>
