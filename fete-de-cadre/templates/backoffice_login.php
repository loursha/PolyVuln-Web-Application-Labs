<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> | Fête de Cadre</title>
<link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🖼️</text></svg>">
<link rel="stylesheet" href="/assets/vendor/bootstrap/bootstrap.min.css"><link rel="stylesheet" href="/assets/css/style.css"></head>
<body>
<div class="min-vh-100 d-flex align-items-center justify-content-center" style="background:var(--ink)">
  <div class="bg-white p-4 p-md-5 shadow" style="max-width:420px;width:92%;border-radius:4px">
    <div class="brand mb-1" style="color:var(--umber)"><span class="mark"></span> Fête de Cadre</div>
    <p class="text-muted mb-4">Workshop & admin sign in</p>
    <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
    <form method="post" action="/backoffice/login">
      <input type="hidden" name="_csrf" value="<?= e($__csrf) ?>">
      <div class="mb-3"><label class="form-label" for="username">Username</label><input class="form-control" id="username" name="username" autocomplete="username" required value="<?= e($username) ?>"></div>
      <div class="mb-4"><label class="form-label" for="password">Password</label><input class="form-control" type="password" id="password" name="password" autocomplete="current-password" required></div>
      <button class="btn btn-primary w-100 btn-lg">Sign in</button>
    </form>
    <p class="small text-muted mt-4 mb-0"><a href="/">Back to the shop</a></p>
  </div>
</div>
</body></html>
