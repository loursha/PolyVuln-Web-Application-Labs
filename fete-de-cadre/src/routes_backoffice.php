<?php
declare(strict_types=1);

const STATUSES = ['placed', 'framing', 'shipped', 'delivered', 'cancelled'];

/* ---------------- Staff: order queue ---------------- */
route('GET /staff', function () {
    require_role('staff', 'admin');
    $orders = q("SELECT o.*, u.username FROM orders o JOIN users u ON u.id = o.user_id
                 WHERE o.status IN ('placed','framing','shipped') ORDER BY o.created_at");
    foreach ($orders as &$o) $o['lines'] = q('SELECT product_name, size, qty FROM order_items WHERE order_id = ?', [$o['id']]);
    unset($o);
    $counts = [];
    foreach (q('SELECT status, COUNT(*) n FROM orders GROUP BY status') as $r) $counts[$r['status']] = $r['n'];
    $custom = q('SELECT c.*, u.full_name FROM custom_requests c JOIN users u ON u.id = c.user_id ORDER BY c.id DESC LIMIT 10');
    $low = q('SELECT name, stock FROM products WHERE stock < 10 ORDER BY stock');
    render_bo('staff/index', ['title' => 'Workshop queue', 'orders' => $orders, 'counts' => $counts, 'custom' => $custom, 'low' => $low, 'statuses' => STATUSES]);
});

route_rx('POST ~^/staff/orders/(\d+)/status$~', function (array $m) {
    require_role('staff', 'admin');
    if (in_array($_POST['status'] ?? '', STATUSES, true)) {
        exec_sql('UPDATE orders SET status = ? WHERE id = ?', [$_POST['status'], (int) $m[1]]);
        flash('success', 'Order status updated.');
    }
    redirect('/staff');
});

/* CWE-532 viewer */
route('GET /staff/logs', function () {
    require_role('staff', 'admin');
    render_bo('staff/logs', ['title' => 'Activity log', 'lines' => log_tail(250)]);
});

/* CWE-77: courier depot connectivity check */
route('GET /staff/diagnostics', function () {
    require_role('staff', 'admin');
    render_bo('staff/diagnostics', ['title' => 'Courier depot check', 'host' => '127.0.0.1', 'output' => null, 'error' => null]);
});

route('POST /staff/diagnostics', function () {
    require_role('staff', 'admin');
    $host = (string) ($_POST['host'] ?? '');
    $plan = build_ping($host);
    if (!$plan) {
        render_bo('staff/diagnostics', ['title' => 'Courier depot check', 'host' => $host, 'output' => null,
            'error' => 'Enter a valid hostname or IP address, for example depot-east.fetedecadre.local or 10.0.4.12.']);
        return;
    }
    log_activity('DIAGNOSTICS', ['user' => current_user()['username'], 'target' => $host]);
    if ($plan['shell']) {
        $output = (string) shell_exec($plan['command'] . ' 2>&1');
    } else {
        $descr = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $proc = proc_open($plan['args'], $descr, $pipes);
        $output = is_resource($proc) ? stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]) : '';
        if (is_resource($proc)) { fclose($pipes[1]); fclose($pipes[2]); proc_close($proc); }
    }
    render_bo('staff/diagnostics', ['title' => 'Courier depot check', 'host' => $host, 'output' => trim($output) ?: 'No output', 'error' => null]);
});

/* ---------------- Admin ---------------- */
route('GET /admin', function () {
    require_role('admin');
    $kpi = q1("SELECT COUNT(*) orders, COALESCE(SUM(total),0) revenue, COALESCE(AVG(total),0) aov FROM orders WHERE status <> 'cancelled'");
    $customers = (int) q1("SELECT COUNT(*) n FROM users WHERE role = 'customer'")['n'];
    $dailyRows = q("SELECT DATE(created_at) d, SUM(total) revenue FROM orders WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 13 DAY) AND status <> 'cancelled' GROUP BY DATE(created_at)");
    $daily = [];
    foreach ($dailyRows as $r) $daily[$r['d']] = (float) $r['revenue'];
    $days = [];
    for ($i = 13; $i >= 0; $i--) { $d = date('Y-m-d', strtotime("-$i day")); $days[] = ['d' => $d, 'revenue' => $daily[$d] ?? 0]; }
    $byCat = q("SELECT oi.category, SUM(oi.unit_price*oi.qty) revenue FROM order_items oi JOIN orders o ON o.id = oi.order_id WHERE o.status <> 'cancelled' GROUP BY oi.category ORDER BY revenue DESC");
    $recent = q('SELECT o.*, u.username FROM orders o JOIN users u ON u.id = o.user_id ORDER BY o.created_at DESC LIMIT 8');
    $top = q('SELECT product_name, SUM(qty) units FROM order_items GROUP BY product_name ORDER BY units DESC LIMIT 5');
    render_bo('admin/index', ['title' => 'Admin dashboard', 'kpi' => $kpi, 'customers' => $customers, 'days' => $days, 'byCat' => $byCat, 'recent' => $recent, 'top' => $top]);
});

route('GET /admin/users', function () {
    require_role('admin');
    render_bo('admin/users', ['title' => 'Customers & staff', 'users' => q('SELECT id, username, email, full_name, role, is_trade, active FROM users ORDER BY id')]);
});

route_rx('POST ~^/admin/users/(\d+)$~', function (array $m) {
    require_role('admin');
    $uid = (int) $m[1];
    if ($uid === current_user()['id']) { flash('danger', "You can't change your own account here."); redirect('/admin/users'); }
    $action = $_POST['action'] ?? '';
    if ($action === 'toggle-active') exec_sql('UPDATE users SET active = 1 - active WHERE id = ?', [$uid]);
    if ($action === 'toggle-trade') exec_sql('UPDATE users SET is_trade = 1 - is_trade WHERE id = ?', [$uid]);
    if (in_array($_POST['role'] ?? '', ['customer', 'staff', 'admin'], true)) exec_sql('UPDATE users SET role = ? WHERE id = ?', [$_POST['role'], $uid]);
    log_activity('ADMIN_USER_UPDATE', ['admin' => current_user()['username'], 'target' => $uid, 'action' => $action ?: ($_POST['role'] ?? '')]);
    flash('success', 'Account updated.');
    redirect('/admin/users');
});

route('GET /admin/products', function () {
    require_role('admin');
    render_bo('admin/products', ['title' => 'Catalogue', 'products' => q('SELECT * FROM products ORDER BY category, name')]);
});

route_rx('POST ~^/admin/products/(\d+)$~', function (array $m) {
    require_role('admin');
    $price = (float) ($_POST['price'] ?? 0);
    $stock = (int) ($_POST['stock'] ?? -1);
    if ($price <= 0 || $price >= 100000 || $stock < 0 || $stock >= 100000) {
        flash('danger', 'Enter a valid price and stock level.');
    } else {
        exec_sql('UPDATE products SET price = ?, stock = ?, featured = ? WHERE id = ?', [$price, $stock, isset($_POST['featured']) ? 1 : 0, (int) $m[1]]);
        flash('success', 'Product saved.');
    }
    redirect('/admin/products');
});
