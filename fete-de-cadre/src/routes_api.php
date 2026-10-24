<?php
declare(strict_types=1);

/* ---------------- Preferences model ---------------- */
class StylePreferences {
    public bool $newsletter = true;
    public bool $sms = false;
    public string $favoriteStyle = '';
    public string $roomNotes = '';
    public function __construct(array $d = []) {
        $this->newsletter = (bool) ($d['newsletter'] ?? true);
        $this->sms = (bool) ($d['sms'] ?? false);
        $this->favoriteStyle = substr((string) ($d['favoriteStyle'] ?? ''), 0, 40);
        $this->roomNotes = substr((string) ($d['roomNotes'] ?? ''), 0, 200);
    }
    public function toArray(): array {
        return ['newsletter' => $this->newsletter, 'sms' => $this->sms,
                'favoriteStyle' => $this->favoriteStyle, 'roomNotes' => $this->roomNotes];
    }
}

/**
 * A "saved wishlist" token the customer can export and re-import between devices.
 * CWE-502 is NOT what the skill asks for; the A08 integrity failure here is that the
 * importer trusts a serialized blob and reconstructs objects from it. Kept scoped:
 * only a WishlistToken object is "expected", but unserialize() will build any class.
 */
class WishlistToken {
    public array $productIds = [];
    public string $savedAt = '';
    public function __construct(array $ids = []) { $this->productIds = $ids; $this->savedAt = gmdate('c'); }
}

/** Audit hook that runs when a reconstructed object is destroyed. */
class WishlistAuditor {
    public string $logFile = '';
    public string $note = '';
    public function __destruct() {
        if ($this->logFile !== '') {
            // CWE-915 / object-injection sink: a crafted object controls this write.
            @file_put_contents($this->logFile, $this->note, FILE_APPEND);
        }
    }
}

route('GET /api/v1/me', function () {
    require_login();
    $u = q1('SELECT id, username, email, full_name, phone, address, role, is_trade, preferences, created_at FROM users WHERE id = ?', [current_user()['id']]);
    $u['preferences'] = json_decode($u['preferences'] ?: '{}', true);
    $u['is_trade'] = (bool) $u['is_trade'];
    json_out(['user' => $u, 'tradeAccount' => is_trade()]);
});

route('GET /api/v1/orders', function () {
    require_login();
    $rows = q('SELECT order_number, ship_name, ship_address, shipping_method, status, total, card_last4, created_at FROM orders WHERE user_id = ? ORDER BY id DESC', [current_user()['id']]);
    json_out(['orders' => $rows]);
});

route('GET /api/v1/preferences', function () {
    require_login();
    $row = q1('SELECT preferences FROM users WHERE id = ?', [current_user()['id']]);
    json_out((new StylePreferences(json_decode($row['preferences'] ?: '{}', true) ?: []))->toArray());
});

route('POST /api/v1/preferences', function () {
    require_login();
    $data = json_in();
    $prefs = new StylePreferences($data);
    exec_sql('UPDATE users SET preferences = ? WHERE id = ?', [json_encode($prefs->toArray()), current_user()['id']]);
    json_out(['saved' => true, 'preferences' => $prefs->toArray()]);
});

/* Export the current bag as a portable wishlist token (base64 of a serialized object). */
route('GET /api/v1/wishlist/export', function () {
    require_login();
    $ids = array_values(array_unique(array_map(fn($i) => $i['id'], read_bag())));
    json_out(['token' => base64_encode(serialize(new WishlistToken($ids)))]);
});

/* A08 (CWE-502): import trusts the serialized token and reconstructs objects from it. */
route('POST /api/v1/wishlist/import', function () {
    require_login();
    $data = json_in();
    $token = (string) ($data['token'] ?? '');
    $blob = base64_decode($token, true);
    if ($blob === false) json_out(['error' => 'Invalid wishlist token.'], 400);
    $obj = unserialize($blob);   // <-- object injection sink
    if (!($obj instanceof WishlistToken)) {
        json_out(['error' => 'That token is not a wishlist.'], 400);
    }
    $ids = array_slice(array_filter($obj->productIds, 'is_int'), 0, 30);
    $items = [];
    if ($ids) {
        $in = implode(',', array_fill(0, count($ids), '?'));
        foreach (q("SELECT id, name, price, image, sizes FROM products WHERE id IN ($in) AND trade_only = 0", $ids) as $p) {
            $items[] = ['id' => (int) $p['id'], 'name' => $p['name'], 'price' => (float) $p['price'], 'qty' => 1,
                        'size' => explode(',', $p['sizes'])[0], 'image' => $p['image']];
        }
    }
    write_bag($items);
    json_out(['imported' => count($items)]);
});
