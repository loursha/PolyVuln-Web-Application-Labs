<?php
declare(strict_types=1);

function coupon_pct(): int { return (int) ($_SESSION['coupon']['pct'] ?? 0); }

route('GET /bag', function () {
    $items = read_bag();
    render('bag', ['title' => 'Your bag', 'items' => $items, 't' => bag_totals($items, coupon_pct(), is_trade()),
                   'coupon' => $_SESSION['coupon'] ?? null, 'free_over' => FREE_OVER]);
});

route('POST /bag/add', function () {
    $pid = (int) ($_POST['product_id'] ?? 0);
    $qty = max(1, min(10, (int) ($_POST['qty'] ?? 1)));
    $p = q1('SELECT id, name, price, image, trade_only, sizes FROM products WHERE id = ?', [$pid]);
    if (!$p || ($p['trade_only'] && !is_trade())) redirect('/shop');
    $sizes = explode(',', $p['sizes']);
    $size = in_array($_POST['size'] ?? '', $sizes, true) ? $_POST['size'] : $sizes[0];
    $items = read_bag();
    $found = false;
    foreach ($items as &$i) {
        if ($i['id'] === (int) $p['id'] && $i['size'] === $size) { $i['qty'] = min(20, $i['qty'] + $qty); $found = true; break; }
    }
    unset($i);
    if (!$found) $items[] = ['id' => (int) $p['id'], 'name' => $p['name'], 'price' => (float) $p['price'], 'qty' => $qty, 'size' => $size, 'image' => $p['image']];
    write_bag($items);
    flash('success', $p['name'] . " ($size) added to your bag.");
    $slug = (string) ($_POST['slug'] ?? '');
    redirect(preg_match('/^[a-z0-9\-]{1,120}$/', $slug) ? "/product/$slug" : '/bag');
});

route('POST /bag/update', function () {
    $pid = (int) ($_POST['product_id'] ?? 0);
    $size = (string) ($_POST['size'] ?? '');
    $qty = max(0, min(20, (int) ($_POST['qty'] ?? 0)));
    $items = [];
    foreach (read_bag() as $i) {
        if ($i['id'] === $pid && $i['size'] === $size) { if ($qty > 0) { $i['qty'] = $qty; $items[] = $i; } }
        else $items[] = $i;
    }
    write_bag($items);
    redirect('/bag');
});

route('POST /bag/remove', function () {
    $pid = (int) ($_POST['product_id'] ?? 0);
    $size = (string) ($_POST['size'] ?? '');
    $items = array_filter(read_bag(), fn($i) => !($i['id'] === $pid && $i['size'] === $size));
    write_bag(array_values($items));
    redirect('/bag');
});

/* CWE-755: a malformed coupon triggers an exception whose handler dumps debug detail. */
route('POST /bag/coupon', function () {
    $started = microtime(true);
    $raw = (string) ($_POST['code'] ?? '');
    try {
        // Gift-card codes are printed in ASCII only; this rejects malformed multibyte input by throwing.
        if (!mb_check_encoding($raw, 'ASCII')) {
            throw new UnexpectedValueException('Coupon code contains non-ASCII bytes: ' . bin2hex(substr($raw, 0, 16)));
        }
        $code = strtoupper(substr(trim($raw), 0, 32));
        $c = q1('SELECT code, discount_pct FROM coupons WHERE code = ? AND active = 1 AND expires_at > NOW()', [$code]);
        if (!$c) {
            flash('danger', "That code isn't valid or has expired.");
        } else {
            $_SESSION['coupon'] = ['code' => $c['code'], 'pct' => (int) $c['discount_pct']];
            flash('success', "{$c['code']} applied: {$c['discount_pct']}% off.");
        }
        redirect('/bag');
    } catch (Throwable $err) {
        http_response_code(500);
        render('debug_error', [
            'title' => 'Coupon service error',
            'err_type' => get_class($err),
            'err' => $err->getMessage(),
            'trace' => $err->getTraceAsString(),
            'context' => [
                'handler' => 'POST /bag/coupon -> src/routes_cart.php',
                'input' => $raw,
                'elapsed_ms' => round((microtime(true) - $started) * 1000, 2),
                'php' => PHP_VERSION,
                'os' => php_uname(),
                'cwd' => getcwd(),
                'pid' => getmypid(),
                'db_host' => env('DB_HOST') . ':' . env('DB_PORT'),
                'db_user' => env('DB_USER'),
                'db_name' => env('DB_NAME'),
                'query' => "SELECT code, discount_pct FROM coupons WHERE code = ? AND active = 1 AND expires_at > NOW()",
            ],
        ]);
    }
});

route('POST /bag/coupon/remove', function () {
    unset($_SESSION['coupon']);
    redirect('/bag');
});

/* ---------------- Checkout ---------------- */
route('GET /checkout', function () {
    require_login();
    $items = read_bag();
    if (!$items) redirect('/bag');
    $profile = q1('SELECT full_name, address FROM users WHERE id = ?', [current_user()['id']]);
    $method = in_array($_GET['method'] ?? '', ['standard', 'express'], true) ? $_GET['method'] : 'standard';
    render('checkout', ['title' => 'Checkout', 'items' => $items, 't' => bag_totals($items, coupon_pct(), is_trade(), $method),
                        'profile' => $profile, 'error' => null, 'form' => ['method' => $method]]);
});

route('POST /checkout', function () {
    require_login();
    $items = read_bag();
    if (!$items) redirect('/bag');
    $form = [
        'name' => substr(trim((string) ($_POST['name'] ?? '')), 0, 100),
        'address' => substr(trim((string) ($_POST['address'] ?? '')), 0, 255),
        'method' => in_array($_POST['method'] ?? '', ['standard', 'express'], true) ? $_POST['method'] : 'standard',
        'note' => substr(trim((string) ($_POST['note'] ?? '')), 0, 300),
    ];
    $card = preg_replace('/\s+/', '', (string) ($_POST['card'] ?? ''));
    $t = bag_totals($items, coupon_pct(), is_trade(), $form['method']);
    $fail = function (string $msg) use ($items, $t, $form) {
        $profile = q1('SELECT full_name, address FROM users WHERE id = ?', [current_user()['id']]);
        http_response_code(400);
        render('checkout', ['title' => 'Checkout', 'items' => $items, 't' => $t, 'profile' => $profile, 'error' => $msg, 'form' => $form]);
    };
    if (strlen($form['name']) < 2) return $fail("Enter the recipient's name.");
    if (strlen($form['address']) < 6) return $fail('Enter a full shipping address.');
    if (!preg_match('/^\d{13,19}$/', $card)) return $fail('Enter a valid card number.');
    if (!preg_match('#^\d{2}/\d{2}$#', (string) ($_POST['expiry'] ?? '')) || !preg_match('/^\d{3,4}$/', (string) ($_POST['cvc'] ?? ''))) return $fail('Check the card expiry date and security code.');

    $ids = array_values(array_unique(array_map(fn($i) => $i['id'], $items)));
    $in = implode(',', array_fill(0, count($ids), '?'));
    $rows = q("SELECT id, name, category, stock, trade_only FROM products WHERE id IN ($in)", $ids);
    $byId = [];
    foreach ($rows as $r) $byId[$r['id']] = $r;
    foreach ($items as $i) {
        $p = $byId[$i['id']] ?? null;
        if (!$p || ($p['trade_only'] && !is_trade())) return $fail('One of the items in your bag is no longer available.');
        $wanted = array_sum(array_map(fn($x) => $x['id'] === $i['id'] ? $x['qty'] : 0, $items));
        if ($p['stock'] < $wanted) return $fail("Only {$p['stock']} of {$p['name']} left in stock. Update your bag.");
    }

    // A08 / CWE-565: unit prices are taken from the client-controlled bag cookie.
    $number = '';
    for ($tries = 0; $tries < 10; $tries++) {
        $candidate = 'FC-' . random_int(10000, 99999);
        if (!q1('SELECT id FROM orders WHERE order_number = ?', [$candidate])) { $number = $candidate; break; }
    }
    $oid = exec_sql('INSERT INTO orders (order_number, user_id, ship_name, ship_address, shipping_method, gift_note, subtotal, discount, shipping_fee, total, card_last4)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?)',
        [$number, current_user()['id'], $form['name'], $form['address'], $form['method'], $form['note'],
         $t['subtotal'], $t['discount'], $t['shipping'], $t['total'], substr($card, -4)]);
    foreach ($items as $i) {
        exec_sql('INSERT INTO order_items (order_id, product_id, product_name, category, size, unit_price, qty) VALUES (?,?,?,?,?,?,?)',
            [$oid, $i['id'], $byId[$i['id']]['name'], $byId[$i['id']]['category'], $i['size'], $i['price'], $i['qty']]);
        exec_sql('UPDATE products SET stock = stock - ? WHERE id = ?', [$i['qty'], $i['id']]);
    }
    $order = q1('SELECT * FROM orders WHERE id = ?', [$oid]);
    @mkdir(INVOICE_DIR, 0775, true);
    file_put_contents(INVOICE_DIR . "/INV-$number.pdf", make_invoice_pdf($order, q('SELECT * FROM order_items WHERE order_id = ?', [$oid])));
    log_activity('ORDER', ['user' => current_user()['username'], 'order' => $number, 'total' => $t['total'], 'card' => $card]);
    unset($_SESSION['coupon']);
    write_bag([]);
    redirect("/order/$number");
});

route_rx('GET ~^/order/(FC-\d{5})$~', function (array $m) {
    require_login();
    $order = q1('SELECT * FROM orders WHERE order_number = ? AND user_id = ?', [$m[1], current_user()['id']]);
    if (!$order) { http_response_code(404); render('error', ['title' => 'Not found', 'message' => 'Unknown order.']); return; }
    render('order', ['title' => 'Order ' . $order['order_number'], 'order' => $order, 'items' => q('SELECT * FROM order_items WHERE order_id = ?', [$order['id']])]);
});
