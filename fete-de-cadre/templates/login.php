<!-- TODO(dev): remove before go-live. Workshop test login for the framing team: framer / Studio2024!  (see /backoffice/login) -->
<div class="container-fluid">
  <div class="row" style="min-height:calc(100vh - 140px)">
    <div class="col-lg-6 auth-side d-none d-lg-block" style="background-image:url('/assets/img/products/ornate-gold.jpg')"></div>
    <div class="col-lg-6 d-flex align-items-center justify-content-center py-5">
      <div class="auth-card w-100 px-3">
        <h1 class="mb-2">Sign in</h1>
        <p class="text-muted mb-4">Track orders, save sizes and check out faster.</p>
        <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
        <form method="post" action="/login">
          <input type="hidden" name="_csrf" value="<?= e($__csrf) ?>"><input type="hidden" name="next" value="<?= e($next) ?>">
          <div class="mb-3"><label class="form-label" for="username">Username</label><input class="form-control" id="username" name="username" autocomplete="username" required value="<?= e($username) ?>"></div>
          <div class="mb-2"><label class="form-label" for="password">Password</label><input class="form-control" type="password" id="password" name="password" autocomplete="current-password" required></div>
          <div class="d-flex justify-content-between align-items-center mb-4">
            <div class="form-check"><input class="form-check-input" type="checkbox" id="remember" name="remember"><label class="form-check-label" for="remember">Keep me signed in</label></div>
            <a href="/forgot">Forgot password?</a>
          </div>
          <button class="btn btn-primary btn-lg w-100">Sign in</button>
        </form>
        <p class="mt-4 text-center">New here? <a href="/register">Create an account</a></p>
      </div>
    </div>
  </div>
</div>
