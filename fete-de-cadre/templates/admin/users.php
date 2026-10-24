<h1 class="h2 mb-4">Customers &amp; staff</h1>
<div class="panel p-0"><div class="table-responsive"><table class="table align-middle mb-0">
  <thead><tr><th class="ps-3">User</th><th>Email</th><th>Role</th><th>Trade</th><th>Status</th><th class="pe-3 text-end">Actions</th></tr></thead>
  <tbody><?php foreach ($users as $u): ?><tr>
    <td class="ps-3"><strong><?= e($u['full_name']) ?></strong><br><small class="text-muted">@<?= e($u['username']) ?></small></td>
    <td><?= e($u['email']) ?></td>
    <td><form method="post" action="/admin/users/<?= (int)$u['id'] ?>"><input type="hidden" name="_csrf" value="<?= e($__csrf) ?>"><select class="form-select form-select-sm" name="role" aria-label="Role" onchange="this.form.submit()" <?= $u['id'] === $__user['id'] ? 'disabled' : '' ?>><?php foreach (['customer','staff','admin'] as $r): ?><option <?= $r === $u['role'] ? 'selected' : '' ?>><?= $r ?></option><?php endforeach; ?></select></form></td>
    <td><?= $u['is_trade'] ? 'Trade' : '—' ?></td>
    <td><span class="status <?= $u['active'] ? 'status-delivered' : 'status-cancelled' ?>"><?= $u['active'] ? 'Active' : 'Closed' ?></span></td>
    <td class="pe-3 text-end"><?php if ($u['id'] !== $__user['id']): ?>
      <form method="post" action="/admin/users/<?= (int)$u['id'] ?>" class="d-inline"><input type="hidden" name="_csrf" value="<?= e($__csrf) ?>"><input type="hidden" name="action" value="toggle-trade"><button class="btn btn-sm btn-outline-primary"><?= $u['is_trade'] ? 'Remove trade' : 'Make trade' ?></button></form>
      <form method="post" action="/admin/users/<?= (int)$u['id'] ?>" class="d-inline"><input type="hidden" name="_csrf" value="<?= e($__csrf) ?>"><input type="hidden" name="action" value="toggle-active"><button class="btn btn-sm <?= $u['active'] ? 'btn-outline-danger' : 'btn-primary' ?>"><?= $u['active'] ? 'Close' : 'Reactivate' ?></button></form>
    <?php endif; ?></td>
  </tr><?php endforeach; ?></tbody>
</table></div></div>
