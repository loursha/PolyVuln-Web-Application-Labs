<section class="hero">
  <img class="bg" src="/assets/img/hero.jpg" alt="A gallery wall of framed pictures">
  <div class="container inner">
    <div class="eyebrow mb-3" style="color:#e9c9a8">Hand-framed in Providence</div>
    <h1 class="mb-3">Frame the moments that matter.</h1>
    <p class="lede mb-4">Ready-made and made-to-measure frames, cut and joined by hand. Mount a print, a diploma or a whole wall of memories.</p>
    <div class="d-flex flex-wrap gap-2"><a class="btn btn-clay btn-lg" href="/shop">Shop frames</a><a class="btn btn-outline-light btn-lg" href="/custom">Request custom framing</a></div>
  </div>
</section>

<section class="section">
  <div class="container">
    <div class="d-flex justify-content-between align-items-end flex-wrap gap-2 mb-4"><div><div class="eyebrow mb-1">New this season</div><h2 class="mb-0">Favourites on the wall</h2></div><a href="/shop">See everything</a></div>
    <div class="row g-4">
      <?php foreach ($featured as $p): ?>
      <div class="col-6 col-lg-4">
        <a class="product-card" href="/product/<?= e($p['slug']) ?>">
          <div class="frame"><img src="<?= e($p['image']) ?>" alt="<?= e($p['name']) ?>" loading="lazy"></div>
          <h3><?= e($p['name']) ?></h3>
          <div class="d-flex justify-content-between align-items-baseline"><span class="meta"><?= e($p['category']) ?></span><span class="price"><?= money($p['price']) ?></span></div>
        </a>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="section section-cream">
  <div class="container">
    <div class="row g-5 align-items-center">
      <div class="col-lg-6"><div class="frame-lg"><img src="/assets/img/workshop.jpg" alt="Inside the framing workshop"></div></div>
      <div class="col-lg-6">
        <div class="eyebrow mb-2">The workshop</div>
        <h2 class="mb-3">Made to measure, by hand</h2>
        <p class="measure mb-4">Send us the dimensions or a photo of what you want framed. We cut the moulding, join the corners, hand-cut the mat and mount your piece — usually within a week.</p>
        <div class="row g-4 mb-4 service">
          <div class="col-sm-4"><i class="bi bi-rulers"></i><h3 class="h5 mt-2">Any size</h3><p class="text-muted small mb-0">Standard sizes or made to your measurements.</p></div>
          <div class="col-sm-4"><i class="bi bi-shield-check"></i><h3 class="h5 mt-2">Conservation</h3><p class="text-muted small mb-0">Acid-free mats and UV glass for pieces you keep.</p></div>
          <div class="col-sm-4"><i class="bi bi-truck"></i><h3 class="h5 mt-2">Delivered safe</h3><p class="text-muted small mb-0">Corner-protected and double-boxed.</p></div>
        </div>
        <a class="btn btn-primary" href="/custom">Request custom framing</a>
      </div>
    </div>
  </div>
</section>

<section class="section section-ink">
  <div class="container">
    <div class="eyebrow mb-2" style="color:#e9c9a8">Reviews</div>
    <h2 class="mb-4">What customers say</h2>
    <div class="row g-4">
      <?php foreach ($reviews as $r): ?>
      <div class="col-md-4"><div class="stars mb-2"><?= str_repeat('★', (int) $r['rating']) ?></div><p class="serif fs-4 mb-2" style="line-height:1.3"><?= e($r['body']) ?></p><small class="opacity-75"><?= e($r['full_name']) ?> · <?= e($r['product']) ?></small></div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
