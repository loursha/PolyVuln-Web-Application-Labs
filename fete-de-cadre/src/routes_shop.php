<?php
declare(strict_types=1);

/* ---------------- Tiny route registry ---------------- */
function &route_registry(): array {
    static $r = ['static' => [], 'dynamic' => []];
    return $r;
}
function route(string $key, callable $fn): void {
    $r = &route_registry();
    $r['static'][$key] = $fn;
}
function route_rx(string $key, callable $fn): void {
    $r = &route_registry();
    $r['dynamic'][$key] = $fn;
}
function routes_all(): array { return route_registry()['static']; }
function routes_dynamic(): array { return route_registry()['dynamic']; }

const CATEGORIES = ['Ornate', 'Gallery', 'Wood', 'Metal', 'Document', 'Tabletop', 'Collage'];

/* ---------------- Home ---------------- */
route('GET /', function () {
    $featured = q('SELECT * FROM products WHERE featured = 1 AND trade_only = 0 ORDER BY id LIMIT 6');
    $reviews = q('SELECT r.rating, r.body, u.full_name, p.name AS product FROM reviews r
                  JOIN users u ON u.id = r.user_id JOIN products p ON p.id = r.product_id
                  WHERE r.id <= 5 ORDER BY r.rating DESC, r.id LIMIT 3');
    render('home', ['title' => 'Frame the moments that matter', 'featured' => $featured, 'reviews' => $reviews]);
});

/* ---------------- Shop listing (CWE-80 reflected) ---------------- */
route('GET /shop', function () {
    $term = isset($_GET['q']) ? substr((string) $_GET['q'], 0, 300) : '';
    $category = in_array($_GET['category'] ?? '', CATEGORIES, true) ? $_GET['category'] : '';
    $sortMap = ['price_asc' => 'price ASC', 'price_desc' => 'price DESC', 'name' => 'name ASC'];
    $sort = $sortMap[$_GET['sort'] ?? ''] ?? 'featured DESC, id ASC';
    $where = ['trade_only = 0'];
    $params = [];
    if ($category !== '') { $where[] = 'category = ?'; $params[] = $category; }
    $blocked = false;
    if ($term !== '') {
        $blocked = xss_blocked($term);
        $where[] = '(name LIKE ? OR description LIKE ? OR material LIKE ?)';
        $params = array_merge($params, ["%$term%", "%$term%", "%$term%"]);
    }
    // Parameterised query (SQLi lives only in /track).
    $products = q('SELECT * FROM products WHERE ' . implode(' AND ', $where) . " ORDER BY $sort", $params);
    render('shop', ['title' => 'Shop frames', 'products' => $products, 'term' => $term, 'blocked' => $blocked,
                    'category' => $category, 'categories' => CATEGORIES, 'sort' => $_GET['sort'] ?? '']);
});

/* ---------------- Product detail ---------------- */
route_rx('GET ~^/product/([a-z0-9\-]+)$~', function (array $m) {
    $p = q1('SELECT * FROM products WHERE slug = ?', [$m[1]]);
    if (!$p || ($p['trade_only'] && !is_trade())) {
        http_response_code(404);
        render('error', ['title' => 'Not found', 'message' => "That frame isn't on the wall any more."]);
        return;
    }
    $reviews = q('SELECT r.*, u.full_name FROM reviews r JOIN users u ON u.id = r.user_id WHERE r.product_id = ? ORDER BY r.created_at DESC', [$p['id']]);
    $related = q('SELECT * FROM products WHERE category = ? AND id <> ? AND trade_only = 0 LIMIT 3', [$p['category'], $p['id']]);
    $avg = $reviews ? array_sum(array_column($reviews, 'rating')) / count($reviews) : 0;
    render('product', ['title' => $p['name'], 'product' => $p, 'sizes' => explode(',', $p['sizes']),
                       'reviews' => $reviews, 'related' => $related, 'avg' => $avg]);
});

/* ---------------- Submit review (CWE-80 stored) ---------------- */
route_rx('POST ~^/product/([a-z0-9\-]+)/review$~', function (array $m) {
    require_login();
    $p = q1('SELECT id, slug FROM products WHERE slug = ?', [$m[1]]);
    if (!$p) { http_response_code(404); render('error', ['title' => 'Not found', 'message' => 'Unknown product.']); return; }
    $rating = max(1, min(5, (int) ($_POST['rating'] ?? 5)));
    $body = substr(trim((string) ($_POST['body'] ?? '')), 0, 1000);
    if (strlen($body) < 3) {
        flash('danger', 'Write at least a few words about the frame.');
    } elseif (xss_blocked($body)) {
        flash('danger', "Your review contains content that can't be published. Edit it and try again.");
    } else {
        exec_sql('INSERT INTO reviews (product_id, user_id, rating, body) VALUES (?,?,?,?)', [$p['id'], current_user()['id'], $rating, $body]);
        flash('success', 'Thanks! Your review is now live.');
    }
    redirect('/product/' . $p['slug'] . '#reviews');
});

/* ---------------- Order tracking (CWE-89 SQL injection) ---------------- */
route('GET /track', function () {
    $ref = trim((string) ($_GET['order'] ?? ''));
    $results = null; $error = null;
    if ($ref !== '') {
        try {
            // Deliberately concatenated: the ONLY SQL-injectable sink in the app.
            $sql = "SELECT order_number, status, ship_name, shipping_method, total FROM orders WHERE order_number = '" . $ref . "'";
            $results = db()->query($sql)->fetchAll();
        } catch (Throwable $e) {
            $error = 'We could not look up that order number. Check it and try again.';
        }
    }
    render('track', ['title' => 'Track your order', 'ref' => $ref, 'results' => $results, 'error' => $error]);
});

/* ---------------- Custom framing: reference image preview (CWE-918 SSRF) ---------------- */
route('GET /custom', function () {
    render('custom', ['title' => 'Request custom framing']);
});

route('POST /custom/preview', function () {
    require_login();
    $data = json_in();
    $url = trim((string) ($data['url'] ?? ''));
    $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
    if (!in_array($scheme, ['http', 'https'], true) || !parse_url($url, PHP_URL_HOST)) {
        json_out(['ok' => false, 'message' => 'Enter a full web address starting with http:// or https://']);
    }
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 4,
        CURLOPT_TIMEOUT => 6, CURLOPT_USERAGENT => 'FeteDeCadre-ImageFetcher/1.4',
    ]);
    $body = curl_exec($ch);
    if ($body === false) {
        curl_close($ch);
        json_out(['ok' => false, 'message' => "We couldn't reach that address. Check the link and try again."]);
    }
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $ctype = strtolower(explode(';', (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE))[0]);
    curl_close($ch);
    $body = substr($body, 0, 2 * 1024 * 1024);
    if (in_array($ctype, ['image/png', 'image/jpeg', 'image/gif', 'image/webp'], true)) {
        json_out(['ok' => true, 'image' => "data:$ctype;base64," . base64_encode($body)]);
    }
    json_out(['ok' => false, 'message' => "That address didn't return an image (HTTP $status, " . ($ctype ?: 'unknown type') . ').',
              'body' => substr($body, 0, 4000)]);
});

route('POST /custom/request', function () {
    require_login();
    $budget = max(40, min(5000, (int) ($_POST['budget'] ?? 150)));
    exec_sql('INSERT INTO custom_requests (user_id, frame_style, dimensions, budget, notes, reference_url) VALUES (?,?,?,?,?,?)',
        [current_user()['id'], substr((string) ($_POST['frame_style'] ?? 'Gallery'), 0, 60),
         substr((string) ($_POST['dimensions'] ?? ''), 0, 60), $budget,
         substr((string) ($_POST['notes'] ?? ''), 0, 600), substr((string) ($_POST['url'] ?? ''), 0, 500)]);
    flash('success', 'Request received. A framer will email you a quote within one working day.');
    redirect('/custom');
});

/* ---------------- Trade (trade-only catalogue) ---------------- */
route('GET /trade', function () {
    require_login();
    $products = is_trade() ? q('SELECT * FROM products WHERE trade_only = 1') : [];
    render('trade', ['title' => 'Trade & Conservation', 'products' => $products]);
});
