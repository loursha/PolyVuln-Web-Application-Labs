<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title ?? 'Back office') ?> | Fête de Cadre</title>
  <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🖼️</text></svg>">
  <link rel="stylesheet" href="/assets/vendor/bootstrap/bootstrap.min.css">
  <link rel="stylesheet" href="/assets/vendor/bi/bootstrap-icons.min.css">
  <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<nav class="navbar site-nav px-3" style="height:66px">
  <a class="navbar-brand brand" href="/"><span class="mark"></span><span>Fête de Cadre <small class="d-inline" style="letter-spacing:.1em">workshop</small></span></a>
  <div class="d-flex align-items-center gap-3">
    <span class="text-muted d-none d-sm-inline"><i class="bi bi-person-badge"></i> <?= e($__user['fullName']) ?> (<?= e($__user['role']) ?>)</span>
    <form action="/logout" method="post"><input type="hidden" name="_csrf" value="<?= e($__csrf) ?>"><button class="btn btn-sm btn-outline-primary">Sign out</button></form>
  </div>
</nav>
<?php if ($__flash): ?><div class="alert alert-<?= e($__flash['type']) ?> mb-0"><?= e($__flash['message']) ?></div><?php endif; ?>
<div class="container-fluid bo-shell"><div class="row">
  <aside class="col-md-3 col-lg-2 bo-side p-3">
    <div class="grp">Workshop</div>
    <a href="/staff" class="<?= $__path === '/staff' ? 'active' : '' ?>"><i class="bi bi-hammer"></i> Order queue</a>
    <a href="/staff/diagnostics" class="<?= $__path === '/staff/diagnostics' ? 'active' : '' ?>"><i class="bi bi-broadcast"></i> Courier depot check</a>
    <a href="/staff/logs" class="<?= $__path === '/staff/logs' ? 'active' : '' ?>"><i class="bi bi-journal-text"></i> Activity log</a>
    <?php if ($__user['role'] === 'admin'): ?>
    <div class="grp">Admin</div>
    <a href="/admin" class="<?= $__path === '/admin' ? 'active' : '' ?>"><i class="bi bi-graph-up"></i> Dashboard</a>
    <a href="/admin/users" class="<?= $__path === '/admin/users' ? 'active' : '' ?>"><i class="bi bi-people"></i> Customers &amp; staff</a>
    <a href="/admin/products" class="<?= $__path === '/admin/products' ? 'active' : '' ?>"><i class="bi bi-images"></i> Catalogue</a>
    <?php endif; ?>
    <a href="/" class="mt-4"><i class="bi bi-shop"></i> View shop</a>
  </aside>
  <main class="col-md-9 col-lg-10 p-4"><?= $__content ?></main>
</div></div>
<script src="/assets/vendor/jquery-1.12.4.min.js"></script>
<script src="/assets/vendor/bootstrap/bootstrap.bundle.min.js"></script>
<?= $__scripts ?? '' ?>
</body>
</html>
