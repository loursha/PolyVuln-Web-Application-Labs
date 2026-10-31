<?php
/** @var string $pageTitle */
/** @var string $active */
$me = current_user();
$isAdmin = ($_SESSION['role'] ?? '') === 'admin';
$nav = [
    ['dashboard', '/dashboard.php', 'fa-th-large', 'Overview'],
    ['transactions', '/transactions.php', 'fa-list-ul', 'Transactions'],
    ['transfer', '/transfer.php', 'fa-paper-plane', 'Send money'],
    ['payees', '/beneficiaries.php', 'fa-user-friends', 'Payees'],
    ['cards', '/cards.php', 'fa-credit-card', 'Cards'],
    ['topup', '/topup.php', 'fa-plus-circle', 'Add money'],
    ['documents', '/documents.php', 'fa-file-alt', 'Statements & documents'],
    ['support', '/support.php', 'fa-life-ring', 'Help & support'],
];
$unread = (int)(q1('SELECT COUNT(*) c FROM tickets WHERE user_id = ? AND status <> "Resolved"', [$_SESSION['uid']])['c'] ?? 0);
?><!doctype html>
<html lang="en">
<head>
<?php include __DIR__ . '/assets_head.php'; ?>
<title><?= e($pageTitle) ?> · <?= APP_NAME ?></title>
</head>
<body class="app">
<div class="app-shell">
  <aside class="sidebar" id="sidebar">
    <a class="brand" href="/dashboard.php">
      <img src="/assets/img/logo-mark.svg" alt="" width="34" height="34">
      <span>PolyVuln<b>Bank</b></span>
    </a>
    <nav class="side-nav">
      <?php foreach ($nav as [$key, $href, $icon, $label]): ?>
        <a href="<?= $href ?>" class="<?= ($active ?? '') === $key ? 'active' : '' ?>">
          <i class="fas <?= $icon ?>"></i><span><?= $label ?></span>
          <?php if ($key === 'support' && $unread): ?><em class="pill"><?= $unread ?></em><?php endif; ?>
        </a>
      <?php endforeach; ?>
      <?php if ($isAdmin): ?>
        <div class="side-sep">Bank operations</div>
        <a href="/admin/" class="<?= ($active ?? '') === 'admin' ? 'active' : '' ?>"><i class="fas fa-landmark"></i><span>Operations console</span></a>
        <a href="/admin/customers.php" class="<?= ($active ?? '') === 'admin-customers' ? 'active' : '' ?>"><i class="fas fa-users"></i><span>Customers</span></a>
        <a href="/admin/tickets.php" class="<?= ($active ?? '') === 'admin-tickets' ? 'active' : '' ?>"><i class="fas fa-inbox"></i><span>Service desk</span></a>
        <a href="/admin/diagnostics.php" class="<?= ($active ?? '') === 'admin-diag' ? 'active' : '' ?>"><i class="fas fa-network-wired"></i><span>Gateway health</span></a>
      <?php endif; ?>
    </nav>
    <div class="side-foot">
      <div class="help-card">
        <i class="fas fa-headset"></i>
        <div><strong>24/7 support</strong><span>1-800-555-0174</span></div>
      </div>
    </div>
  </aside>
  <div class="main">
    <header class="topbar">
      <button class="btn btn-icon d-lg-none" id="navToggle" aria-label="Open menu"><i class="fas fa-bars"></i></button>
      <form class="top-search d-none d-md-flex" action="/transactions.php" method="get" role="search">
        <i class="fas fa-search"></i>
        <input type="search" name="q" placeholder="Search transactions, merchants, references" aria-label="Search transactions">
      </form>
      <div class="top-actions">
        <a class="btn btn-icon" href="/support.php" aria-label="Messages"><i class="far fa-bell"></i><?php if ($unread): ?><span class="dot"></span><?php endif; ?></a>
        <div class="dropdown">
          <a href="#" class="user-chip" data-toggle="dropdown" aria-expanded="false">
            <img src="/<?= e(ltrim($me['avatar'] ?? 'assets/img/avatars/default.png', '/')) ?>" alt="">
            <span class="d-none d-sm-inline"><?= e($me['full_name'] ?? '') ?></span>
            <i class="fas fa-chevron-down small"></i>
          </a>
          <div class="dropdown-menu dropdown-menu-right">
            <h6 class="dropdown-header"><?= e($me['email'] ?? '') ?></h6>
            <a class="dropdown-item" href="/profile.php"><i class="far fa-user mr-2"></i>Profile & security</a>
            <a class="dropdown-item" href="/documents.php"><i class="far fa-file-alt mr-2"></i>Statements</a>
            <div class="dropdown-divider"></div>
            <a class="dropdown-item" href="/logout.php"><i class="fas fa-sign-out-alt mr-2"></i>Sign out</a>
          </div>
        </div>
      </div>
    </header>
    <main class="content">
      <?php foreach (flashes() as [$flashType, $flashMsg]): ?>
        <div class="alert alert-<?= $flashType ?> alert-dismissible fade show" role="alert"><?= $flashMsg ?><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button></div>
      <?php endforeach; ?>
