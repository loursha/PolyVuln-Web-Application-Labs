<div class="container py-5">
  <div class="alert alert-danger"><strong><?= e($err_type) ?>:</strong> <?= e($err) ?></div>
  <h1 class="h2 mb-3">Unhandled exception in coupon service</h1>
  <div class="row g-4">
    <div class="col-lg-7"><h2 class="h4">Stack trace</h2><pre class="console"><?= e($trace) ?></pre></div>
    <div class="col-lg-5"><h2 class="h4">Request context</h2><pre class="console"><?= e(json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?></pre></div>
  </div>
  <a href="/bag" class="btn btn-outline-primary mt-3">Back to bag</a>
</div>
