<div class="container py-5">
  <div class="row align-items-end g-3 mb-4">
    <div class="col-lg-7">
      <div class="eyebrow mb-1">The collection</div>
      <h1 class="mb-2"><?= $category !== '' ? e($category) : 'All frames' ?></h1>
      <?php if ($term !== ''): ?>
        <?php if ($blocked): ?>
          <p class="text-muted mb-0">Your search contains characters that aren't allowed. Showing results for <strong><?= e($term) ?></strong>.</p>
        <?php else: ?>
          <!-- Reflected search term (CWE-80): intentionally not escaped. -->
          <p class="text-muted mb-0"><?= count($products) ?> result<?= count($products) === 1 ? '' : 's' ?> for <strong><?= $term ?></strong></p>
        <?php endif; ?>
      <?php else: ?>
        <p class="text-muted mb-0"><?= count($products) ?> frames in the workshop right now.</p>
      <?php endif; ?>
    </div>
    <div class="col-lg-5">
      <form class="d-flex gap-2" method="get" action="/shop">
        <?php if ($category !== ''): ?><input type="hidden" name="category" value="<?= e($category) ?>"><?php endif; ?>
        <input class="form-control" type="search" name="q" value="<?= e($term) ?>" placeholder="Search by frame or material" aria-label="Search">
        <select class="form-select w-auto" name="sort" aria-label="Sort" onchange="this.form.submit()">
          <option value="">Featured</option>
          <option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : '' ?>>Price: low to high</option>
          <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Price: high to low</option>
          <option value="name" <?= $sort === 'name' ? 'selected' : '' ?>>Name</option>
        </select>
      </form>
    </div>
  </div>
  <div class="mb-4">
    <a class="chip <?= $category === '' ? 'active' : '' ?>" href="/shop">All</a>
    <?php foreach ($categories as $c): ?><a class="chip <?= $category === $c ? 'active' : '' ?>" href="/shop?category=<?= e($c) ?>"><?= e($c) ?></a><?php endforeach; ?>
  </div>
  <?php if (!$products): ?>
    <div class="panel panel-cream text-center py-5"><h3>Nothing matches that search</h3><p class="text-muted">Try a frame type like "oak" or a material like "walnut", or browse the whole collection.</p><a class="btn btn-primary" href="/shop">Browse all frames</a></div>
  <?php endif; ?>
  <div class="row g-4">
    <?php foreach ($products as $p): ?>
    <div class="col-6 col-md-4 col-xl-3">
      <a class="product-card" href="/product/<?= e($p['slug']) ?>">
        <div class="frame"><img src="<?= e($p['image']) ?>" alt="<?= e($p['name']) ?>" loading="lazy"></div>
        <h3><?= e($p['name']) ?></h3>
        <div class="d-flex justify-content-between align-items-baseline"><span class="meta"><?= e($p['category']) ?></span><span class="price"><?= money($p['price']) ?></span></div>
        <?php if ($p['stock'] < 10): ?><div class="stock-low mt-1">Only <?= (int) $p['stock'] ?> left</div><?php endif; ?>
      </a>
    </div>
    <?php endforeach; ?>
  </div>
</div>
