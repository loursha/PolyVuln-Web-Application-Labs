<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id'])) {
    $status = ($_POST['status'] ?? '') === 'locked' ? 'locked' : 'active';
    qexec('UPDATE users SET status = ? WHERE id = ? AND role = "customer"', [$status, (int)$_POST['user_id']]);
    flash('success', $status === 'locked' ? 'Online banking locked for this customer.' : 'Online banking unlocked.');
    redirect('/admin/customers.php');
}
$s = trim($_GET['s'] ?? '');
$args = [];
$w = '1=1';
if ($s !== '') { $w = '(u.full_name LIKE ? OR u.username LIKE ? OR u.email LIKE ? OR a.account_number LIKE ?)'; $args = array_fill(0, 4, "%$s%"); }
$rows = qall("SELECT u.*, COUNT(a.id) n_accounts, COALESCE(SUM(a.balance),0) total, GROUP_CONCAT(a.account_number SEPARATOR ', ') nums
              FROM users u LEFT JOIN accounts a ON a.user_id = u.id WHERE $w GROUP BY u.id ORDER BY u.id", $args);
$pageTitle = 'Customers';
$active = 'admin-customers';
include __DIR__ . '/../includes/header.php';
?>
<div class="page-head"><div><h1>Customers</h1><p><?= count($rows) ?> profiles</p></div>
  <form class="form-inline" method="get"><input class="form-control mr-2" name="s" value="<?= e($s) ?>" placeholder="Name, username, email or account"><button class="btn btn-primary">Search</button></form></div>
<div class="panel p-0 overflow-hidden"><div class="table-responsive"><table class="table table-pv">
  <thead><tr><th class="pl-4">Customer</th><th>Contact</th><th>Accounts</th><th>Role</th><th class="num">Total balance</th><th>Online banking</th><th class="pr-4"></th></tr></thead><tbody>
  <?php foreach ($rows as $u): ?>
    <tr>
      <td class="pl-4"><div class="d-flex align-items-center"><img src="/<?= e(ltrim($u['avatar'], '/')) ?>" width="36" height="36" class="rounded-circle mr-2" style="object-fit:cover" alt=""><div><div class="font-weight-bold"><?= e($u['full_name']) ?></div><div class="small text-muted"><?= e($u['username']) ?></div></div></div></td>
      <td class="small"><?= e($u['email']) ?><br><span class="text-muted"><?= e($u['phone']) ?></span></td>
      <td class="small"><?= e($u['nums'] ?: '—') ?></td>
      <td><span class="badge-soft <?= $u['role'] === 'admin' ? 'badge-wait' : 'badge-neutral' ?>"><?= e(ucfirst($u['role'])) ?></span></td>
      <td class="num"><?= money($u['total']) ?></td>
      <td><span class="badge-soft <?= $u['status'] === 'active' ? 'badge-ok' : 'badge-bad' ?>"><?= e(ucfirst($u['status'])) ?></span></td>
      <td class="pr-4 text-right"><?php if ($u['role'] === 'customer'): ?><form method="post"><input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>"><input type="hidden" name="status" value="<?= $u['status'] === 'active' ? 'locked' : 'active' ?>"><button class="btn btn-sm btn-light"><?= $u['status'] === 'active' ? 'Lock' : 'Unlock' ?></button></form><?php endif; ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody></table></div></div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
