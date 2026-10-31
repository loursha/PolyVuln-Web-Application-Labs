<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
$uid = (int)$_SESSION['uid'];
$accounts = user_accounts($uid);
$months = [];
for ($i = 1; $i <= 6; $i++) { $months[] = date('Y-m', strtotime("first day of -$i month")); }
$library = [
    ['Account_Agreement_2026.pdf', 'Deposit Account Agreement', 'Terms for checking and savings accounts', 'fa-file-contract'],
    ['Fee_Schedule_2026.pdf', 'Schedule of Fees', 'Account, card and wire transfer fees', 'fa-file-invoice-dollar'],
    ['Privacy_Notice_2026.pdf', 'Privacy Notice', 'How we collect, use and share your information', 'fa-user-shield'],
    ['Wire_Transfer_Request_Form.pdf', 'Wire Transfer Request', 'Domestic and international wire form', 'fa-file-signature'],
    ['Direct_Deposit_Form.pdf', 'Direct Deposit Form', 'Give this to your employer to get paid early', 'fa-file-alt'],
];
$pageTitle = 'Statements & documents';
$active = 'documents';
include __DIR__ . '/includes/header.php';
?>
<div class="page-head"><div><h1>Statements & documents</h1><p>Monthly statements are available for 7 years. Tax forms appear here each January.</p></div></div>
<div class="row">
  <div class="col-lg-7">
    <div class="panel">
      <div class="panel-head"><h2>Monthly statements</h2></div>
      <?php foreach ($accounts as $a): ?>
        <div class="font-weight-bold mt-2 mb-1"><?= e(($a['nickname'] ?: $a['type']) . ' ' . mask_account($a['account_number'])) ?></div>
        <div class="table-responsive"><table class="table table-pv mb-3"><tbody>
        <?php foreach ($months as $m): ?>
          <tr><td><i class="far fa-file-pdf mr-2 text-debit"></i><?= date('F Y', strtotime($m . '-01')) ?> statement</td><td class="text-right"><a href="/statement.php?account=<?= (int)$a['id'] ?>&month=<?= $m ?>" class="btn btn-sm btn-light">View</a></td></tr>
        <?php endforeach; ?>
        </tbody></table></div>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="col-lg-5">
    <div class="panel">
      <div class="panel-head"><h2>Forms and disclosures</h2></div>
      <?php foreach ($library as [$file, $title, $desc, $icon]): ?>
        <a class="acct-tile" href="/download.php?doc=<?= urlencode($file) ?>">
          <div class="ic"><i class="fas <?= $icon ?>"></i></div>
          <div><div class="t"><?= e($title) ?></div><div class="s"><?= e($desc) ?></div></div>
          <div class="amt text-muted"><i class="fas fa-download"></i></div>
        </a>
      <?php endforeach; ?>
    </div>
    <div class="panel">
      <h2 class="h6">Go paperless</h2>
      <p class="small text-muted mb-0">You're receiving electronic statements. We'll email <?= e(current_user()['email']) ?> when a new statement is ready.</p>
    </div>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
