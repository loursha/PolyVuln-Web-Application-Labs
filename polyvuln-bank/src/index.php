<?php
require_once __DIR__ . '/includes/auth.php';
$rates = ['High-yield savings' => '4.35% APY', '12-month CD' => '4.80% APY', '30-yr fixed mortgage' => '6.42% APR', 'USD/EUR' => '0.9214'];
?><!doctype html>
<html lang="en">
<head>
<?php include __DIR__ . '/includes/assets_head.php'; ?>
<title>PolyVuln Bank · Personal and business banking</title>
<meta name="description" content="Checking, savings, cards and transfers from PolyVuln Bank, N.A.">
</head>
<body>
<nav class="navbar navbar-expand-lg public-nav">
  <div class="container">
    <a class="navbar-brand" href="/"><img src="/assets/img/logo-mark.svg" width="32" height="32" alt=""> PolyVuln <b>Bank</b></a>
    <button class="navbar-toggler" data-toggle="collapse" data-target="#pn" aria-label="Menu"><i class="fas fa-bars"></i></button>
    <div class="collapse navbar-collapse" id="pn">
      <ul class="navbar-nav mr-auto">
        <li class="nav-item"><a class="nav-link" href="#personal">Personal</a></li>
        <li class="nav-item"><a class="nav-link" href="#business">Business</a></li>
        <li class="nav-item"><a class="nav-link" href="#security">Security</a></li>
      </ul>
      <?php if (is_logged_in()): ?>
        <a href="/dashboard.php" class="btn btn-primary">Go to online banking</a>
      <?php else: ?>
        <a href="/login.php" class="btn btn-light mr-2">Sign in</a>
        <a href="/register.php" class="btn btn-primary">Open an account</a>
      <?php endif; ?>
    </div>
  </div>
</nav>
<section class="landing-hero">
  <div class="container wrap">
    <div class="reveal">
      <h1>Banking that keeps its word since 1962.</h1>
      <p class="lead">Checking, savings and cards from a community bank with 48 branches across Northern California, and an app that moves money the moment you ask.</p>
      <a href="/register.php" class="btn btn-brass btn-lg mr-2">Open an account in 5 minutes</a>
      <a href="/login.php" class="btn btn-lg" style="color:#fff">Sign in to online banking</a>
    </div>
    <div class="photo">
      <div class="float-card">
        <div class="font-weight-bold mb-1">Today's rates</div>
        <?php foreach (array_slice($rates, 0, 3) as $k => $v): ?>
          <div class="r"><span class="text-muted"><?= e($k) ?></span><b><?= e($v) ?></b></div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</section>
<div class="rate-strip"><div class="container">
  <?php foreach ($rates as $k => $v): ?><span><?= e($k) ?><b><?= e($v) ?></b></span><?php endforeach; ?>
</div></div>
<section class="feature" id="personal">
  <div class="container">
    <div class="row mb-4"><div class="col-lg-7"><h2>Accounts built around how you actually spend</h2><p class="text-muted">No monthly fees with direct deposit. Instant transfers between PolyVuln accounts, any hour.</p></div></div>
    <div class="row">
      <div class="col-md-4 mb-4"><div class="prod"><div class="img" style="background-image:url(/assets/img/online.jpg)"></div><div class="body"><h3>Everyday Checking</h3><p>Free ATM access at 40,000 locations, early paycheck, and real-time alerts.</p><a href="/register.php">Open checking</a></div></div></div>
      <div class="col-md-4 mb-4"><div class="prod"><div class="img" style="background-image:url(/assets/img/savings.jpg)"></div><div class="body"><h3>High-Yield Savings</h3><p>Earn 4.35% APY on every dollar with no minimum balance to open.</p><a href="/register.php">Start saving</a></div></div></div>
      <div class="col-md-4 mb-4"><div class="prod"><div class="img" style="background-image:url(/assets/img/cards.jpg)"></div><div class="body"><h3>Cards that work everywhere</h3><p>Contactless Visa debit and Mastercard credit with instant freeze from the app.</p><a href="/register.php">Compare cards</a></div></div></div>
    </div>
  </div>
</section>
<section class="feature security-band" id="security">
  <div class="container"><div class="row align-items-center">
    <div class="col-lg-5 mb-4"><h2>Your money, watched over</h2><p class="text-muted">Deposits are FDIC insured up to $250,000 per depositor. Our fraud team monitors card activity around the clock.</p></div>
    <div class="col-lg-7"><div class="row">
      <div class="col-sm-6 mb-3"><div class="panel h-100 mb-0"><i class="fas fa-shield-alt fa-lg text-success mb-2"></i><h3 class="h6">Zero liability</h3><p class="text-muted small mb-0">You're not responsible for unauthorized card purchases reported promptly.</p></div></div>
      <div class="col-sm-6 mb-3"><div class="panel h-100 mb-0"><i class="fas fa-snowflake fa-lg text-info mb-2"></i><h3 class="h6">Freeze in a tap</h3><p class="text-muted small mb-0">Lost your wallet? Lock your card in seconds and unlock it when it turns up.</p></div></div>
    </div></div>
  </div></div>
</section>
<section class="feature" id="business">
  <div class="container"><div class="row align-items-center">
    <div class="col-lg-6 mb-4"><div class="prod"><div class="img" style="height:320px;background-image:url(/assets/img/business.jpg)"></div></div></div>
    <div class="col-lg-6"><h2>Business banking with a banker who knows your name</h2><p class="text-muted">Merchant services, payroll and lines of credit for small businesses, backed by local relationship managers.</p><a href="/register.php" class="btn btn-outline-primary">Talk to a banker</a></div>
  </div></div>
</section>
<?php include __DIR__ . '/includes/public_footer.php'; ?>
</body>
</html>
