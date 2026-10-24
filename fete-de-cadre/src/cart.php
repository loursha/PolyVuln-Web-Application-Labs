<?php
declare(strict_types=1);

const STANDARD_FEE = 7.50;
const EXPRESS_FEE = 14.00;
const FREE_OVER = 120.00;
const TRADE_PCT = 15;

/** The bag lives client-side in the `fdc_bag` cookie (base64 JSON). */
function read_bag(): array {
    $raw = $_COOKIE['fdc_bag'] ?? '';
    if ($raw === '') return [];
    $decoded = json_decode(base64_decode($raw, true) ?: '', true);
    if (!is_array($decoded)) return [];
    $out = [];
    foreach (array_slice($decoded, 0, 30) as $i) {
        if (!is_array($i)) continue;
        $ok = isset($i['id'], $i['name'], $i['price'], $i['qty'])
            && is_int($i['id']) && is_string($i['name']) && strlen($i['name']) <= 120
            && (is_int($i['price']) || is_float($i['price'])) && $i['price'] >= 0 && $i['price'] < 1e6
            && is_int($i['qty']) && $i['qty'] >= 1 && $i['qty'] <= 20;
        if (!$ok) continue;
        $size = (isset($i['size']) && is_string($i['size']) && preg_match('/^[A-Za-z0-9x .\-]{1,20}$/', $i['size'])) ? $i['size'] : 'One size';
        $image = (isset($i['image']) && is_string($i['image']) && preg_match('~^/assets/img/products/[a-z0-9\-]+\.jpg$~', $i['image'])) ? $i['image'] : '/assets/img/products/black-gallery.jpg';
        $out[] = ['id' => $i['id'], 'name' => $i['name'], 'price' => round((float) $i['price'], 2), 'qty' => $i['qty'], 'size' => $size, 'image' => $image];
    }
    return $out;
}

function write_bag(array $items): void {
    setcookie('fdc_bag', base64_encode(json_encode(array_values($items))), [
        'expires' => time() + 7 * 86400, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax',
    ]);
    $_COOKIE['fdc_bag'] = base64_encode(json_encode(array_values($items)));
}

function bag_totals(array $items, int $couponPct = 0, bool $trade = false, string $method = 'standard'): array {
    $subtotal = 0.0;
    foreach ($items as $i) $subtotal += $i['price'] * $i['qty'];
    $subtotal = round($subtotal, 2);
    $pct = max($couponPct, $trade ? TRADE_PCT : 0);
    $discount = round($subtotal * $pct / 100, 2);
    if (!$items || $trade) {
        $shipping = 0.0;
    } elseif ($method === 'express') {
        $shipping = EXPRESS_FEE;
    } else {
        $shipping = ($subtotal - $discount) >= FREE_OVER ? 0.0 : STANDARD_FEE;
    }
    return [
        'subtotal' => $subtotal, 'pct' => $pct, 'discount' => $discount,
        'shipping' => $shipping, 'total' => max(0.0, round($subtotal - $discount + $shipping, 2)),
    ];
}
