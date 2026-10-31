<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$gateways = [
    'core-ledger' => 'Core ledger',
    'db' => 'Customer database',
    '127.0.0.1' => 'Web node (local)',
];

/**
 * Builds the reachability probe for a payment gateway host.
 * Operators may chain a supported node-inspection utility after the probe.
 */
function build_probe(string $target): ?string
{
    $target = str_replace("\r", '', $target);
    if (preg_match('/[`$<>\\\\\'"]/', $target)) {
        return null;
    }
    $parts = preg_split('/(\|\||&&|;|\||&|\n)/', $target, -1, PREG_SPLIT_DELIM_CAPTURE);
    $host = trim((string)array_shift($parts));
    if (!preg_match('/^[A-Za-z0-9.\-:]{1,253}$/', $host)) {
        return null;
    }
    $cmd = 'ping -c 2 -W 2 ' . $host;
    $inspect = ['id', 'uname', 'pwd', 'whoami'];
    for ($i = 0; $i < count($parts); $i += 2) {
        $sep = $parts[$i];
        $seg = trim($parts[$i + 1] ?? '');
        if ($seg === '') continue;
        $tok = preg_split('/\s+/', $seg);
        $bin = array_shift($tok);
        if (!in_array($bin, $inspect, true)) continue;
        foreach ($tok as $arg) {
            if (!preg_match('/^-{1,2}[A-Za-z-]+$/', $arg)) continue 2;
        }
        $sep = in_array($sep, ['&', "\n"], true) ? ';' : $sep;
        $cmd .= ' ' . $sep . ' ' . $bin . ($tok ? ' ' . implode(' ', $tok) : '');
    }
    return $cmd;
}

$target = $_POST['target'] ?? '';
$output = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cmd = build_probe((string)$target);
    $output = $cmd === null ? 'Invalid gateway address.' : (string)shell_exec($cmd . ' 2>&1');
}

$pageTitle = 'Gateway health';
$active = 'admin-diag';
include __DIR__ . '/../includes/header.php';
?>
<div class="page-head"><div><h1>Gateway health</h1><p>Check that payment and ledger gateways are reachable from this web node.</p></div></div>
<div class="row">
  <div class="col-lg-5">
    <div class="panel">
      <form method="post">
        <div class="form-group"><label for="target">Gateway host or IP</label><input class="form-control" id="target" name="target" value="<?= e($target) ?>" placeholder="core-ledger" required></div>
        <button class="btn btn-primary btn-block"><i class="fas fa-satellite-dish mr-2"></i>Run reachability check</button>
      </form>
      <hr>
      <div class="small font-weight-bold mb-2">Known gateways</div>
      <?php foreach ($gateways as $h => $label): ?>
        <form method="post" class="d-flex justify-content-between align-items-center py-1"><input type="hidden" name="target" value="<?= e($h) ?>"><span><?= e($label) ?> <span class="text-muted small"><?= e($h) ?></span></span><button class="btn btn-sm btn-light">Check</button></form>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="col-lg-7">
    <div class="panel">
      <div class="panel-head"><h2>Result</h2><?php if ($output !== null): ?><span class="small text-muted"><?= date('g:i:s a') ?></span><?php endif; ?></div>
      <pre class="diag-out mb-0"><?= $output === null ? 'Run a check to see the result.' : e($output) ?></pre>
    </div>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
