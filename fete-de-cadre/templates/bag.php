<div class="container py-5">
  <h1 class="mb-4">Your bag</h1>
  <?php if (!$items): ?>
    <div class="panel panel-cream text-center py-5"><h3>Your bag is empty</h3><p class="text-muted">Add a frame to get started. Free shipping over $120.</p><a class="btn btn-primary" href="/shop">Shop frames</a></div>
  <?php else: ?>
  <div class="row g-5">
    <div class="col-lg-8">
      <?php foreach ($items as $idx => $i): ?>
      <div class="d-flex gap-3 align-items-center border-bottom py-3">
        <div class="flex-shrink-0" style="width:84px;height:84px;overflow:hidden;background:var(--sage);border-radius:3px"><img src="<?= e($i['image']) ?>" alt="" style="width:100%;height:100%;object-fit:cover"></div>
        <div class="flex-grow-1"><h3 class="h5 mb-1"><?= e($i['name']) ?></h3><span class="text-muted">Size <?= e($i['size']) ?> · <?= money($i['price']) ?> each</span></div>
        <form method="post" action="/bag/update">
          <input type="hidden" name="_csrf" value="<?= e($__csrf) ?>"><input type="hidden" name="product_id" value="<?= (int) $i['id'] ?>"><input type="hidden" name="size" value="<?= e($i['size']) ?>">
          <label class="visually-hidden" for="q<?= $idx ?>">Quantity</label>
          <input id="q<?= $idx ?>" class="form-control js-autosubmit text-center" style="width:72px" type="number" name="qty" min="0" max="20" value="<?= (int) $i['qty'] ?>">
        </form>
        <strong style="width:92px" class="text-end"><?= money($i['price'] * $i['qty']) ?></strong>
        <form method="post" action="/bag/remove"><input type="hidden" name="_csrf" value="<?= e($__csrf) ?>"><input type="hidden" name="product_id" value="<?= (int) $i['id'] ?>"><input type="hidden" name="size" value="<?= e($i['size']) ?>"><button class="btn btn-link" style="color:var(--wine)" aria-label="Remove <?= e($i['name']) ?>"><i class="bi bi-x-lg"></i></button></form>
      </div>
      <?php endforeach; ?>
      <a href="/shop" class="d-inline-block mt-3"><i class="bi bi-arrow-left"></i> Continue shopping</a>
    </div>
    <div class="col-lg-4">
      <div class="panel">
        <h3 class="mb-3">Summary</h3>
        <div class="d-flex justify-content-between mb-2"><span>Subtotal</span><span><?= money($t['subtotal']) ?></span></div>
        <?php if ($t['discount']): ?><div class="d-flex justify-content-between mb-2" style="color:var(--wine)"><span><?= $coupon && $coupon['pct'] >= $t['pct'] ? e($coupon['code']) : 'Trade' ?> (<?= $t['pct'] ?>% off)</span><span>−<?= money($t['discount']) ?></span></div><?php endif; ?>
        <div class="d-flex justify-content-between mb-2"><span>Standard shipping</span><span><?= $t['shipping'] ? money($t['shipping']) : 'Free' ?></span></div>
        <?php if ($t['shipping']): ?><small class="text-muted d-block mb-2">Spend <?= money($free_over - ($t['subtotal'] - $t['discount'])) ?> more for free shipping.</small><?php endif; ?>
        <hr>
        <div class="d-flex justify-content-between fs-5 fw-semibold mb-3"><span>Total</span><span><?= money($t['total']) ?></span></div>
        <?php if ($coupon): ?>
          <form method="post" action="/bag/coupon/remove" class="mb-3 d-flex justify-content-between align-items-center"><input type="hidden" name="_csrf" value="<?= e($__csrf) ?>"><span class="status status-delivered"><i class="bi bi-tag"></i> <?= e($coupon['code']) ?></span><button class="btn btn-link btn-sm">Remove code</button></form>
        <?php else: ?>
          <form method="post" action="/bag/coupon" class="input-group mb-3"><input type="hidden" name="_csrf" value="<?= e($__csrf) ?>"><input class="form-control" name="code" placeholder="Promo or gift card code" aria-label="Promo code"><button class="btn btn-outline-primary">Apply</button></form>
        <?php endif; ?>
        <a href="/checkout" class="btn btn-primary w-100 btn-lg">Go to checkout</a>
      </div>
    </div>
  </div>
  <?php endif; ?>
</div>
