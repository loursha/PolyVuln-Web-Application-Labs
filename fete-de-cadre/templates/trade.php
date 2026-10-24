<div class="container py-5">
  <?php if (empty($__user['isTrade'])): ?>
  <div class="row g-5 align-items-center">
    <div class="col-lg-6">
      <div class="eyebrow mb-1">For the trade</div>
      <h1 class="mb-3">Trade & Conservation</h1>
      <p class="fs-5 text-muted measure">Interior designers, galleries and photographers get 15% off, priority framing and access to our conservation-grade archival range.</p>
      <p class="measure">Trade accounts are opened for businesses that order regularly. Ask a framer in the workshop to enable yours.</p>
      <a class="btn btn-primary" href="/shop">Shop the collection</a>
    </div>
    <div class="col-lg-6"><div class="frame-lg" style="filter:grayscale(.5)"><img src="/assets/img/products/floating-frame.jpg" alt="Conservation framing reserved for trade clients"></div></div>
  </div>
  <?php else: ?>
  <div class="eyebrow mb-1">For the trade</div>
  <h1 class="mb-2">Trade & Conservation</h1>
  <p class="text-muted mb-4">Welcome back. Your 15% trade discount and free shipping are applied automatically at checkout.</p>
  <div class="row g-4">
    <?php foreach ($products as $p): ?>
    <div class="col-6 col-md-4"><a class="product-card" href="/product/<?= e($p['slug']) ?>"><div class="frame"><img src="<?= e($p['image']) ?>" alt="<?= e($p['name']) ?>" loading="lazy"></div><h3><?= e($p['name']) ?></h3><div class="d-flex justify-content-between"><span class="meta"><?= e($p['category']) ?></span><span class="price"><?= money($p['price']) ?></span></div></a></div>
    <?php endforeach; ?>
  </div>
  <div class="panel panel-cream mt-4"><strong>Trade note:</strong> this quarter's conservation reference is FLAG{trade_catalogue_is_not_access_control}.</div>
  <?php endif; ?>
</div>
