<div class="container py-5">
  <div class="row g-5">
    <div class="col-lg-5">
      <div class="eyebrow mb-1">Bespoke</div>
      <h1 class="mb-3">Request custom framing</h1>
      <p class="text-muted measure">Tell us the piece, the size and your budget. Share a link to a photo of what you want framed and a framer will send a quote within a working day.</p>
      <div class="frame-lg mt-4 d-none d-lg-block"><img src="/assets/img/gallery.jpg" alt="A framed gallery wall"></div>
    </div>
    <div class="col-lg-7">
      <?php if (!$__user): ?>
        <div class="panel panel-cream"><h3>Sign in to request a quote</h3><p class="text-muted">We'll email your quote and mock-up.</p><a class="btn btn-primary" href="/login?next=/custom">Sign in</a> <a class="btn btn-outline-primary" href="/register">Create an account</a></div>
      <?php else: ?>
      <form class="panel" method="post" action="/custom/request">
        <input type="hidden" name="_csrf" value="<?= e($__csrf) ?>">
        <div class="row g-3">
          <div class="col-md-6"><label class="form-label" for="frame_style">Frame style</label><select class="form-select" id="frame_style" name="frame_style"><option>Gallery (minimal)</option><option>Ornate / gilt</option><option>Natural wood</option><option>Metal</option><option>Conservation / archival</option></select></div>
          <div class="col-md-6"><label class="form-label" for="dimensions">Artwork size</label><input class="form-control" id="dimensions" name="dimensions" placeholder="e.g. 50 x 70 cm, or A2"></div>
          <div class="col-12"><label class="form-label" for="budget">Budget: <span id="budgetOut">$150</span></label><input type="range" class="form-range" id="budget" name="budget" min="40" max="1200" step="10" value="150"></div>
          <div class="col-12">
            <label class="form-label" for="url">Reference photo link <span class="text-muted fw-normal">(optional)</span></label>
            <div class="input-group"><input class="form-control" id="url" name="url" type="url" placeholder="https://example.com/my-artwork.jpg"><button class="btn btn-outline-primary" type="button" id="previewBtn">Preview</button></div>
            <div class="form-text">Paste a direct link to an image. We fetch it so your framer can see the piece.</div>
            <div id="previewArea" class="mt-3"></div>
          </div>
          <div class="col-12"><label class="form-label" for="notes">Notes</label><textarea class="form-control" id="notes" name="notes" rows="3" maxlength="600" placeholder="Mount colour, glass type, deadline…"></textarea></div>
        </div>
        <button class="btn btn-primary btn-lg mt-4">Request a quote</button>
      </form>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php $__scripts = <<<'JS'
<script>
document.addEventListener('DOMContentLoaded', function () {
  var b = document.getElementById('budget'); if (!b) return;
  b.addEventListener('input', function () { document.getElementById('budgetOut').textContent = '$' + b.value; });
  document.getElementById('previewBtn').addEventListener('click', function () {
    var area = document.getElementById('previewArea'), btn = this;
    btn.disabled = true; area.innerHTML = '<div class="text-muted"><span class="spinner-border spinner-border-sm"></span> Fetching image…</div>';
    fetch('/custom/preview', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF }, body: JSON.stringify({ url: document.getElementById('url').value }) })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        area.innerHTML = '';
        if (d.ok) { var img = document.createElement('img'); img.src = d.image; img.alt = 'Reference preview'; img.className = 'img-fluid rounded'; img.style.maxHeight = '320px'; area.appendChild(img); return; }
        var msg = document.createElement('div'); msg.className = 'alert alert-danger mb-2'; msg.textContent = d.message; area.appendChild(msg);
        if (d.body) { var pre = document.createElement('pre'); pre.className = 'console'; pre.textContent = d.body; area.appendChild(pre); }
      })
      .catch(function () { area.innerHTML = '<div class="alert alert-danger">Preview failed. Try again.</div>'; })
      .finally(function () { btn.disabled = false; });
  });
});
</script>
JS;
$__scripts = str_replace('CSRF', json_encode($__csrf), $__scripts);
?>
