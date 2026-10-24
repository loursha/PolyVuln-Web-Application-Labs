<div class="container py-5">
  <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="/shop">Shop</a></li><li class="breadcrumb-item"><a href="/shop?category=<?= e($product['category']) ?>"><?= e($product['category']) ?></a></li><li class="breadcrumb-item active" aria-current="page"><?= e($product['name']) ?></li></ol></nav>
  <div class="row g-5">
    <div class="col-lg-6"><div class="frame-lg"><img src="<?= e($product['image']) ?>" alt="<?= e($product['name']) ?>"></div></div>
    <div class="col-lg-6">
      <div class="eyebrow mb-1"><?= e($product['category']) ?></div>
      <h1 class="mb-2"><?= e($product['name']) ?></h1>
      <?php if ($reviews): ?><a href="#reviews" class="text-decoration-none text-muted"><span class="stars"><?= str_repeat('★', (int) round($avg)) ?></span> <?= number_format($avg, 1) ?> from <?= count($reviews) ?> review<?= count($reviews) === 1 ? '' : 's' ?></a><?php endif; ?>
      <p class="fs-2 fw-semibold mt-3" style="color:var(--umber)"><?= money($product['price']) ?></p>
      <p class="measure"><?= e($product['description']) ?></p>
      <p class="text-muted"><i class="bi bi-tag me-1"></i><?= e($product['material']) ?></p>
      <?php if ($product['stock'] > 0): ?>
      <form method="post" action="/bag/add" class="my-4">
        <input type="hidden" name="_csrf" value="<?= e($__csrf) ?>"><input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>"><input type="hidden" name="slug" value="<?= e($product['slug']) ?>">
        <fieldset class="mb-3"><legend class="form-label fs-6">Size</legend>
          <div class="size-picker"><?php foreach ($sizes as $idx => $s): ?><input type="radio" name="size" id="size-<?= $idx ?>" value="<?= e($s) ?>" <?= $idx === 0 ? 'checked' : '' ?>><label for="size-<?= $idx ?>"><?= e($s) ?></label><?php endforeach; ?></div>
        </fieldset>
        <div class="d-flex gap-2 align-items-center">
          <label class="visually-hidden" for="qty">Quantity</label>
          <select class="form-select w-auto" id="qty" name="qty"><?php for ($n = 1; $n <= 5; $n++): ?><option><?= $n ?></option><?php endfor; ?></select>
          <button class="btn btn-primary btn-lg flex-grow-1"><i class="bi bi-bag me-1"></i> Add to bag</button>
        </div>
        <?php if ($product['stock'] < 10): ?><p class="stock-low mt-2 mb-0">Only <?= (int) $product['stock'] ?> left in the workshop.</p><?php endif; ?>
      </form>
      <?php else: ?><div class="alert alert-danger">Out of stock. Our framers can make this to order — request custom framing.</div><?php endif; ?>
      <div class="panel panel-cream">
        <div class="d-flex gap-3 mb-2"><i class="bi bi-truck fs-4"></i><div><strong>Free standard shipping over $120</strong><br><span class="text-muted">Express next-day $14.</span></div></div>
        <div class="d-flex gap-3"><i class="bi bi-arrow-repeat fs-4"></i><div><strong>30-day returns</strong><br><span class="text-muted">Unused, in original packaging.</span></div></div>
      </div>
    </div>
  </div>

  <section id="reviews" class="mt-5 pt-4">
    <div class="row g-5">
      <div class="col-lg-7">
        <h2 class="mb-4">Reviews</h2>
        <?php if (!$reviews): ?><p class="text-muted">No reviews yet. Bought this frame? Tell others how it looks on the wall.</p><?php endif; ?>
        <?php foreach ($reviews as $r): ?>
          <div class="border-bottom pb-3 mb-3">
            <div class="d-flex justify-content-between"><strong><?= e($r['full_name']) ?></strong><small class="text-muted"><?= substr((string) $r['created_at'], 0, 10) ?></small></div>
            <div class="stars small mb-1"><?= str_repeat('★', (int) $r['rating']) ?><span class="text-muted"><?= str_repeat('★', 5 - (int) $r['rating']) ?></span></div>
            <!-- Stored review body (CWE-80): intentionally rendered unescaped. -->
            <div class="review-body"><?= $r['body'] ?></div>
          </div>
        <?php endforeach; ?>
      </div>
      <div class="col-lg-5">
        <div class="panel">
          <h3 class="mb-3">Write a review</h3>
          <?php if ($__user): ?>
          <form method="post" action="/product/<?= e($product['slug']) ?>/review">
            <input type="hidden" name="_csrf" value="<?= e($__csrf) ?>">
            <label class="form-label" for="rating">Rating</label>
            <select class="form-select mb-3" id="rating" name="rating"><option value="5">5 – Excellent</option><option value="4">4 – Very good</option><option value="3">3 – Fine</option><option value="2">2 – Disappointing</option><option value="1">1 – Poor</option></select>
            <label class="form-label" for="body">Your review</label>
            <textarea class="form-control mb-3" id="body" name="body" rows="4" maxlength="1000" placeholder="How does it look? How was it packaged?" required></textarea>
            <button class="btn btn-primary">Publish review</button>
          </form>
          <?php else: ?><p class="text-muted mb-3">Sign in to share how your frame arrived.</p><a class="btn btn-outline-primary" href="/login?next=/product/<?= e($product['slug']) ?>">Sign in to review</a><?php endif; ?>
        </div>
      </div>
    </div>
  </section>

  <?php if ($related): ?>
  <section class="mt-5"><h2 class="mb-4">Pairs well with</h2><div class="row g-4">
    <?php foreach ($related as $p): ?>
    <div class="col-6 col-md-4"><a class="product-card" href="/product/<?= e($p['slug']) ?>"><div class="frame"><img src="<?= e($p['image']) ?>" alt="<?= e($p['name']) ?>" loading="lazy"></div><h3><?= e($p['name']) ?></h3><div class="d-flex justify-content-between"><span class="meta"><?= e($p['category']) ?></span><span class="price"><?= money($p['price']) ?></span></div></a></div>
    <?php endforeach; ?>
  </div></section>
  <?php endif; ?>
</div>
