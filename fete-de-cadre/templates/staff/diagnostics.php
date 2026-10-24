<h1 class="h2 mb-1">Courier depot check</h1>
<p class="text-muted mb-4">Check that a courier depot or label-printer gateway is reachable from the workshop server.</p>
<div class="row g-4">
  <div class="col-lg-5">
    <form method="post" action="/staff/diagnostics" class="panel">
      <input type="hidden" name="_csrf" value="<?= e($__csrf) ?>">
      <label class="form-label" for="host">Depot hostname or IP</label>
      <input class="form-control mb-2" id="host" name="host" value="<?= e($host) ?>" list="depots" required>
      <datalist id="depots"><option value="127.0.0.1">Workshop server</option><option value="depot-east.fetedecadre.local"></option><option value="labels.fetedecadre.local"></option></datalist>
      <?php if ($error): ?><div class="alert alert-danger py-2 mt-2"><?= e($error) ?></div><?php endif; ?>
      <button class="btn btn-primary mt-2"><i class="bi bi-broadcast"></i> Run connectivity check</button>
    </form>
  </div>
  <div class="col-lg-7"><h2 class="h5">Result</h2><pre class="console"><?= $output === null ? 'Run a check to see results here.' : e($output) ?></pre></div>
</div>
