<h1 class="h2 mb-4">Catalogue</h1>
<div class="panel p-0"><div class="table-responsive"><table class="table align-middle mb-0">
  <thead><tr><th class="ps-3">Product</th><th>Category</th><th style="width:130px">Price ($)</th><th style="width:110px">Stock</th><th>Featured</th><th class="pe-3"></th></tr></thead>
  <tbody><?php foreach ($products as $p): ?><tr>
    <td class="ps-3"><div class="d-flex gap-2 align-items-center"><img src="<?= e($p['image']) ?>" alt="" width="40" height="40" style="object-fit:cover;border-radius:3px"><span><?= e($p['name']) ?><?php if ($p['trade_only']): ?> <small class="text-muted">(trade)</small><?php endif; ?></span></div></td>
    <td><?= e($p['category']) ?></td>
    <td><input form="pf<?= (int)$p['id'] ?>" class="form-control form-control-sm" name="price" type="number" step="0.01" min="0.01" value="<?= e((string)$p['price']) ?>" aria-label="Price"></td>
    <td><input form="pf<?= (int)$p['id'] ?>" class="form-control form-control-sm" name="stock" type="number" min="0" value="<?= (int)$p['stock'] ?>" aria-label="Stock"></td>
    <td><input form="pf<?= (int)$p['id'] ?>" class="form-check-input" type="checkbox" name="featured" <?= $p['featured'] ? 'checked' : '' ?> aria-label="Featured"></td>
    <td class="pe-3"><form id="pf<?= (int)$p['id'] ?>" method="post" action="/admin/products/<?= (int)$p['id'] ?>"><input type="hidden" name="_csrf" value="<?= e($__csrf) ?>"><button class="btn btn-sm btn-primary">Save</button></form></td>
  </tr><?php endforeach; ?></tbody>
</table></div></div>
