<div class="container-fluid">
  <div class="row" style="min-height:calc(100vh - 140px)">
    <div class="col-lg-6 auth-side d-none d-lg-block" style="background-image:url('/assets/img/products/walnut-classic.jpg')"></div>
    <div class="col-lg-6 d-flex align-items-center justify-content-center py-5">
      <div class="auth-card w-100 px-3">
        <h1 class="mb-2">Create an account</h1>
        <p class="text-muted mb-4">Save your sizes and see every order in one place.</p>
        <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
        <form method="post" action="/register">
          <input type="hidden" name="_csrf" value="<?= e($__csrf) ?>">
          <input type="hidden" name="role" value="customer">
          <div class="mb-3"><label class="form-label" for="full_name">Full name</label><input class="form-control" id="full_name" name="full_name" autocomplete="name" required value="<?= e($form['full_name'] ?? '') ?>"></div>
          <div class="mb-3"><label class="form-label" for="email">Email</label><input class="form-control" type="email" id="email" name="email" autocomplete="email" required value="<?= e($form['email'] ?? '') ?>"></div>
          <div class="mb-3"><label class="form-label" for="username">Username</label><input class="form-control" id="username" name="username" autocomplete="username" required value="<?= e($form['username'] ?? '') ?>"></div>
          <div class="row g-3 mb-4">
            <div class="col-sm-6"><label class="form-label" for="password">Password</label><input class="form-control" type="password" id="password" name="password" autocomplete="new-password" minlength="8" required></div>
            <div class="col-sm-6"><label class="form-label" for="confirm">Confirm password</label><input class="form-control" type="password" id="confirm" name="confirm" autocomplete="new-password" minlength="8" required></div>
          </div>
          <button class="btn btn-primary btn-lg w-100">Create account</button>
        </form>
        <p class="mt-4 text-center">Already have an account? <a href="/login">Sign in</a></p>
      </div>
    </div>
  </div>
</div>
