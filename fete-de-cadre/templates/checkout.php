<div class="container py-5">
  <h1 class="mb-4">Checkout</h1>
  <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
  <form method="post" action="/checkout" class="row g-5" novalidate>
    <input type="hidden" name="_csrf" value="<?= e($__csrf) ?>">
    <div class="col-lg-7">
      <h3 class="mb-3">Shipping</h3>
      <div class="row g-3 mb-4">
        <div class="col-md-6"><label class="form-label" for="name">Full name</label><input class="form-control" id="name" name="name" required value="<?= e($form['name'] ?? $profile['full_name']) ?>"></div>
        <div class="col-md-6"><label class="form-label" for="method">Delivery</label><select class="form-select" id="method" name="method" onchange="location='/checkout?method='+this.value"><option value="standard" <?= ($form['method'] ?? '') === 'standard' ? 'selected' : '' ?>>Standard, 3-5 days</option><option value="express" <?= ($form['method'] ?? '') === 'express' ? 'selected' : '' ?>>Express, next day</option></select></div>
        <div class="col-12"><label class="form-label" for="address">Shipping address</label><input class="form-control" id="address" name="address" required value="<?= e($form['address'] ?? $profile['address']) ?>"></div>
        <div class="col-12"><label class="form-label" for="note">Gift note <span class="text-muted fw-normal">(optional)</span></label><textarea class="form-control" id="note" name="note" rows="2" maxlength="300"><?= e($form['note'] ?? '') ?></textarea></div>
      </div>
      <h3 class="mb-3">Payment</h3>
      <div class="row g-3">
        <div class="col-12"><label class="form-label" for="card">Card number</label><input class="form-control" id="card" name="card" inputmode="numeric" autocomplete="cc-number" placeholder="4242 4242 4242 4242" required></div>
        <div class="col-6"><label class="form-label" for="expiry">Expiry (MM/YY)</label><input class="form-control" id="expiry" name="expiry" placeholder="08/28" autocomplete="cc-exp" required></div>
        <div class="col-6"><label class="form-label" for="cvc">Security code</label><input class="form-control" id="cvc" name="cvc" inputmode="numeric" autocomplete="cc-csc" placeholder="123" required></div>
      </div>
      <p class="small text-muted mt-3"><i class="bi bi-info-circle"></i> Training lab: use any test card number such as 4242 4242 4242 4242.</p>
    </div>
    <div class="col-lg-5">
      <div class="panel">
        <h3 class="mb-3">Your order</h3>
        <?php foreach ($items as $i): ?><div class="d-flex justify-content-between mb-2"><span><?= (int) $i['qty'] ?> × <?= e($i['name']) ?> <span class="text-muted">(<?= e($i['size']) ?>)</span></span><span><?= money($i['price'] * $i['qty']) ?></span></div><?php endforeach; ?>
        <hr>
        <div class="d-flex justify-content-between mb-1"><span>Subtotal</span><span><?= money($t['subtotal']) ?></span></div>
        <?php if ($t['discount']): ?><div class="d-flex justify-content-between mb-1"><span>Discount</span><span>−<?= money($t['discount']) ?></span></div><?php endif; ?>
        <div class="d-flex justify-content-between mb-3"><span>Shipping</span><span><?= $t['shipping'] ? money($t['shipping']) : 'Free' ?></span></div>
        <div class="d-flex justify-content-between fs-4 fw-semibold mb-3"><span>Total</span><span><?= money($t['total']) ?></span></div>
        <button class="btn btn-primary btn-lg w-100">Pay <?= money($t['total']) ?></button>
      </div>
    </div>
  </form>
</div>
