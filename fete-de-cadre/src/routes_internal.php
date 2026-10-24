<?php
declare(strict_types=1);

function is_loopback(): bool {
    return in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true);
}

/* Supplier sync endpoint - reachable from the server itself only (SSRF target). */
route('GET /internal/supplier-sync', function () {
    if (!is_loopback()) {
        http_response_code(404);
        render('error', ['title' => 'Page not found', 'message' => "That page isn't on the wall any more."]);
        return;
    }
    json_out([
        'service' => 'moulurepro-supplier-sync', 'version' => '2.1.0',
        'lastSync' => gmdate('c', time() - 2400),
        'supplier' => ['name' => 'MoulurePro Wholesale', 'endpoint' => 'https://api.moulurepro.example/v2', 'apiKey' => env('SUPPLIER_API_KEY')],
        'flag' => 'FLAG{ssrf_reached_the_moulding_store}',
    ]);
});

/* CWE-526: debug endpoint left enabled, returns the whole environment. */
route('GET /debug/env', function () {
    json_out(['status' => 'ok', 'php' => PHP_VERSION, 'sapi' => PHP_SAPI, 'env' => $_ENV ?: getenv()]);
});

/* robots.txt (hints at the sensitive paths, as a real deployment often does). */
route('GET /robots.txt', function () {
    header('Content-Type: text/plain');
    echo "User-agent: *\nDisallow: /account/\nDisallow: /backoffice/\nDisallow: /staff/\nDisallow: /admin/\nDisallow: /backups/\nDisallow: /debug/\n";
    exit;
});

/* CWE-548: legacy backup folder exposed with an auto-generated directory index. */
route('GET /backups', 'backups_index');
route('GET /backups/', 'backups_index');
function backups_index(): void {
    $base = BACKUP_DIR;
    $rows = '';
    foreach (scandir($base) ?: [] as $name) {
        if ($name[0] === '.' || !is_file("$base/$name")) continue;
        $size = filesize("$base/$name");
        $mod = date('Y-m-d H:i', filemtime("$base/$name"));
        $rows .= '<tr><td><a href="/backups/' . e($name) . '">' . e($name) . '</a></td><td>' . $mod . '</td><td align="right">' . $size . "</td></tr>\n";
    }
    header('Content-Type: text/html');
    echo "<!doctype html><html><head><title>Index of /backups/</title></head><body><h1>Index of /backups/</h1><hr><table>"
       . "<tr><th align=\"left\">Name</th><th align=\"left\">Last modified</th><th align=\"right\">Size</th></tr>$rows</table><hr><address>Apache/2.4 Server at localhost</address></body></html>";
    exit;
}

route_rx('GET ~^/backups/([A-Za-z0-9._\-]+)$~', function (array $m) {
    $file = BACKUP_DIR . '/' . $m[1];
    if (!is_file($file)) { http_response_code(404); echo 'Not found'; exit; }
    header('Content-Type: text/plain');
    readfile($file);
    exit;
});
