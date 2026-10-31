<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $st = in_array($_POST['status'] ?? '', ['Open', 'In Progress', 'Resolved'], true) ? $_POST['status'] : 'In Progress';
    qexec('UPDATE tickets SET reply = ?, status = ? WHERE id = ?', [trim($_POST['reply'] ?? ''), $st, (int)$_POST['id']]);
    flash('success', 'Reply sent to the customer.');
    redirect('/admin/tickets.php');
}
$rows = qall('SELECT t.*, u.full_name, u.username FROM tickets t JOIN users u ON u.id = t.user_id ORDER BY FIELD(t.status,"Open","In Progress","Resolved"), t.created_at DESC');
$pageTitle = 'Service desk';
$active = 'admin-tickets';
include __DIR__ . '/../includes/header.php';
?>
<div class="page-head"><div><h1>Service desk</h1><p>Secure messages from customers</p></div></div>
<?php foreach ($rows as $t): ?>
<div class="panel">
  <div class="d-flex justify-content-between"><div><div class="font-weight-bold"><?= e($t['subject']) ?></div><div class="small text-muted">#<?= (int)$t['id'] ?> · <?= e($t['full_name']) ?> (<?= e($t['username']) ?>) · <?= e($t['category']) ?> · <?= date('M j, g:i a', strtotime($t['created_at'])) ?></div></div><span class="badge-soft <?= $t['status'] === 'Resolved' ? 'badge-ok' : 'badge-wait' ?> align-self-start"><?= e($t['status']) ?></span></div>
  <p class="mt-2"><?= nl2br(e($t['message'])) ?></p>
  <form method="post" class="form-row align-items-end"><input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
    <div class="col-md-8 mb-2"><textarea class="form-control" name="reply" rows="2" placeholder="Reply to the customer"><?= e($t['reply']) ?></textarea></div>
    <div class="col-md-2 mb-2"><select class="custom-select" name="status"><?php foreach (['Open', 'In Progress', 'Resolved'] as $s): ?><option <?= $t['status'] === $s ? 'selected' : '' ?>><?= $s ?></option><?php endforeach; ?></select></div>
    <div class="col-md-2 mb-2"><button class="btn btn-primary btn-block">Send reply</button></div>
  </form>
</div>
<?php endforeach; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
