<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title ?? 'Fête de Cadre') ?> | Fête de Cadre</title>
  <meta name="description" content="Fête de Cadre is an independent Providence framer selling ready-made and custom photo frames.">
  <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🖼️</text></svg>">
  <link rel="stylesheet" href="/assets/vendor/bootstrap/bootstrap.min.css">
  <link rel="stylesheet" href="/assets/vendor/bi/bootstrap-icons.min.css">
  <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<div class="announce py-2">
  <div class="container d-flex justify-content-between">
    <span><i class="bi bi-truck me-1"></i> Free standard shipping over $120 · Hand-cut mats · 30-day returns</span>
    <span class="d-none d-md-inline"><a href="/track">Track an order</a><span class="mx-2 opacity-50">|</span><a href="/backoffice/login">Staff</a></span>
  </div>
</div>
<nav class="navbar navbar-expand-lg site-nav sticky-top">
  <div class="container">
    <a class="navbar-brand brand" href="/"><span class="mark"></span><span>Fête de Cadre<small class="d-block">Providence framers</small></span></a>
    <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#nav" aria-controls="nav" aria-expanded="false" aria-label="Toggle navigation"><span class="navbar-toggler-icon"></span></button>
    <div class="collapse navbar-collapse" id="nav">
      <ul class="navbar-nav me-auto ms-lg-4">
        <li class="nav-item"><a class="nav-link <?= $__path === '/shop' ? 'active' : '' ?>" href="/shop">Shop</a></li>
        <li class="nav-item"><a class="nav-link" href="/shop?category=Ornate">Ornate</a></li>
        <li class="nav-item"><a class="nav-link <?= $__path === '/custom' ? 'active' : '' ?>" href="/custom">Custom framing</a></li>
        <li class="nav-item"><a class="nav-link <?= $__path === '/trade' ? 'active' : '' ?>" href="/trade">Trade</a></li>
      </ul>
      <form class="nav-search me-lg-3 my-2 my-lg-0" action="/shop" method="get" role="search">
        <input class="form-control" type="search" name="q" placeholder="Search frames" aria-label="Search frames">
      </form>
      <ul class="navbar-nav align-items-lg-center">
        <li class="nav-item me-lg-2"><a class="nav-link bag-link" href="/bag" aria-label="Shopping bag"><i class="bi bi-bag fs-5"></i><?php if ($__bagcount): ?><span class="badge rounded-pill"><?= $__bagcount ?></span><?php endif; ?></a></li>
        <?php if ($__user): ?>
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false"><i class="bi bi-person me-1"></i><?= e(explode(' ', $__user['fullName'])[0]) ?></a>
            <ul class="dropdown-menu dropdown-menu-end">
              <li><a class="dropdown-item" href="/account">Your account</a></li>
              <?php if ($__user['role'] !== 'customer'): ?><li><a class="dropdown-item" href="/staff">Workshop</a></li><?php endif; ?>
              <?php if ($__user['role'] === 'admin'): ?><li><a class="dropdown-item" href="/admin">Admin dashboard</a></li><?php endif; ?>
              <li><hr class="dropdown-divider"></li>
              <li><form action="/logout" method="post" class="px-3"><input type="hidden" name="_csrf" value="<?= e($__csrf) ?>"><button class="btn btn-link p-0 text-decoration-none" style="color:var(--wine)">Sign out</button></form></li>
            </ul>
          </li>
        <?php else: ?>
          <li class="nav-item"><a class="btn btn-primary btn-sm ms-lg-2" href="/login">Sign in</a></li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</nav>
<?php if ($__flash): ?>
  <div class="container mt-3"><div class="alert alert-<?= e($__flash['type']) ?> alert-dismissible fade show" role="alert"><?= e($__flash['message']) ?><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div></div>
<?php endif; ?>
<main><?= $__content ?></main>
<footer class="site-footer mt-5 pt-5 pb-4">
  <div class="container">
    <div class="row g-4">
      <div class="col-md-4"><div class="brand mb-2" style="color:#fff"><span class="mark"></span> Fête de Cadre</div><p class="measure">An independent framer on Atelier Mews since 2012. Every frame is cut, joined and mounted by hand in our Providence workshop.</p></div>
      <div class="col-6 col-md-2"><h6 class="text-white">Shop</h6><ul class="list-unstyled"><li><a href="/shop?category=Ornate">Ornate</a></li><li><a href="/shop?category=Wood">Wood</a></li><li><a href="/shop?category=Gallery">Gallery</a></li><li><a href="/shop?category=Document">Document</a></li></ul></div>
      <div class="col-6 col-md-2"><h6 class="text-white">Help</h6><ul class="list-unstyled"><li><a href="/track">Track an order</a></li><li><a href="/custom">Custom framing</a></li><li><a href="/account">Your account</a></li></ul></div>
      <div class="col-md-4"><h6 class="text-white">Visit the workshop</h6><p class="mb-1">7 Atelier Mews, Providence, RI</p><p>Tue–Sat 10am–6pm</p></div>
    </div>
    <hr class="border-secondary">
    <div class="d-flex flex-wrap justify-content-between small opacity-75"><span>© Fête de Cadre. Security training lab – not a real shop.</span><span>Images: original renders + Wikimedia Commons (see ATTRIBUTIONS.md)</span></div>
  </div>
</footer>
<script src="/assets/vendor/jquery-1.12.4.min.js"></script>
<script src="/assets/vendor/jquery.validate-1.19.0.min.js"></script>
<script src="/assets/vendor/bootstrap/bootstrap.bundle.min.js"></script>
<script src="/assets/js/app.js"></script>
<?= $__scripts ?? '' ?>
</body>
</html>
