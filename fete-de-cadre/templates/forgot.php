<div class="container py-5" style="max-width:520px">
  <h1 class="mb-2">Reset your password</h1>
  <?php if ($step === 'request'): ?>
    <p class="text-muted mb-4">Enter your username or email. We'll email you a 4-digit code.</p>
    <form method="post" action="/forgot" class="panel">
      <input type="hidden" name="_csrf" value="<?= e($__csrf) ?>">
      <label class="form-label" for="identifier">Username or email</label>
      <input class="form-control mb-3" id="identifier" name="identifier" required>
      <button class="btn btn-primary w-100">Email me a code</button>
    </form>
  <?php else: ?>
    <p class="text-muted mb-4">If an account matches, we've emailed a 4-digit code. It expires in 30 minutes.</p>
    <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
    <form method="post" action="/forgot/verify" class="panel">
      <input type="hidden" name="_csrf" value="<?= e($__csrf) ?>"><input type="hidden" name="username" value="<?= e($username) ?>">
      <label class="form-label" for="code">4-digit code</label>
      <input class="form-control mb-3 text-center fs-4" id="code" name="code" inputmode="numeric" maxlength="4" pattern="\d{4}" autocomplete="one-time-code" required>
      <label class="form-label" for="password">New password</label>
      <input class="form-control mb-3" type="password" id="password" name="password" minlength="8" autocomplete="new-password" required>
      <button class="btn btn-primary w-100">Set new password</button>
    </form>
    <p class="mt-3 text-center"><a href="/forgot">Send a new code</a></p>
  <?php endif; ?>
</div>
