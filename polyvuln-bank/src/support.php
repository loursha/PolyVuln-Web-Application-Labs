<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
$uid = (int)$_SESSION['uid'];
$cats = ['Cards', 'Transfers', 'Disputes', 'Profile', 'Online banking', 'Other'];
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subject = trim($_POST['subject'] ?? '');
    $cat = in_array($_POST['category'] ?? '', $cats, true) ? $_POST['category'] : 'Other';
    $msg = trim($_POST['message'] ?? '');
    if (strlen($subject) < 4) $errors[] = 'Add a short subject so we can route your message.';
    if (strlen($msg) < 10) $errors[] = 'Tell us a bit more about what happened.';
    if (!$errors) {
        qexec('INSERT INTO tickets (user_id, subject, category, message) VALUES (?,?,?,?)', [$uid, substr($subject, 0, 160), $cat, $msg]);
        flash('success', 'Message sent. We usually reply within one business day.');
        redirect('/support.php');
    }
}
$tickets = qall('SELECT * FROM tickets WHERE user_id = ? ORDER BY created_at DESC', [$uid]);
$pageTitle = 'Help & support';
$active = 'support';
include __DIR__ . '/includes/header.php';
$badge = ['Open' => 'badge-wait', 'In Progress' => 'badge-neutral', 'Resolved' => 'badge-ok'];
?>
<div class="page-head"><div><h1>Help & support</h1><p>Send us a secure message, or call 1-800-555-0174 any time.</p></div></div>
<div class="row">
  <div class="col-lg-5">
    <div class="panel">
      <h2 class="h6 mb-3">New message</h2>
      <?php if ($errors): ?><div class="alert alert-danger"><?= implode('<br>', array_map('e', $errors)) ?></div><?php endif; ?>
      <form method="post">
        <div class="form-group"><label for="category">Topic</label><select class="custom-select" id="category" name="category"><?php foreach ($cats as $c): ?><option <?= ($_GET['category'] ?? $_POST['category'] ?? '') === $c ? 'selected' : '' ?>><?= $c ?></option><?php endforeach; ?></select></div>
        <div class="form-group"><label for="subject">Subject</label><input class="form-control" id="subject" name="subject" maxlength="160" value="<?= e($_POST['subject'] ?? $_GET['subject'] ?? '') ?>"></div>
        <div class="form-group"><label for="message">Message</label><textarea class="form-control" id="message" name="message" rows="6" placeholder="Don't include your full card number or password."><?= e($_POST['message'] ?? '') ?></textarea></div>
        <button class="btn btn-primary btn-block">Send message</button>
      </form>
    </div>
  </div>
  <div class="col-lg-7">
    <div class="panel">
      <div class="panel-head"><h2>Your messages</h2></div>
      <?php if (!$tickets): ?><div class="empty"><i class="far fa-comments"></i>No messages yet.</div><?php endif; ?>
      <?php foreach ($tickets as $t): ?>
        <div class="border rounded-lg p-3 mb-3" style="border-radius:14px!important">
          <div class="d-flex justify-content-between align-items-start"><div><div class="font-weight-bold"><?= e($t['subject']) ?></div><div class="small text-muted">#<?= (int)$t['id'] ?> · <?= e($t['category']) ?> · <?= date('M j, Y g:i a', strtotime($t['created_at'])) ?></div></div><span class="badge-soft <?= $badge[$t['status']] ?? 'badge-neutral' ?>"><?= e($t['status']) ?></span></div>
          <p class="mt-2 mb-2 small"><?= nl2br(e($t['message'])) ?></p>
          <?php if ($t['reply']): ?><div class="p-2 small" style="background:var(--paper);border-radius:10px"><i class="fas fa-reply mr-1 text-muted"></i><strong>PolyVuln Bank:</strong> <?= nl2br(e($t['reply'])) ?></div><?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
