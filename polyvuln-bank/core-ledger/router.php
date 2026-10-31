<?php
/**
 * PolyVuln core-ledger internal service (not exposed outside the bank network).
 */
header('Content-Type: application/json');
header('Server: core-ledger/2.3.1');
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

$routes = [
    '/' => fn() => ['service' => 'core-ledger', 'version' => '2.3.1', 'endpoints' => ['/api/v1/health', '/api/v1/config', '/api/v1/settlement/batches']],
    '/api/v1/health' => fn() => ['status' => 'UP', 'db' => 'UP', 'queue' => 'UP', 'uptime_s' => 1209600 + (time() % 86400)],
    '/api/v1/config' => fn() => [
        'environment' => 'production',
        'settlement' => ['cutoff' => '17:00 PT', 'fx_provider' => 'treasury-fx.internal:9443'],
        'credentials' => [
            'ledger_service_token' => 'lgr_7f2c91d04eab',
            'swift_gateway_user' => 'pvb-swift-ops',
            'swift_gateway_password' => 'Sw1ft!Gw-2026-ops',
            'polypay_signing_key' => getenv('GATEWAY_SIGNING_KEY') ?: 'pvpay_live_6d1b2f0e94a7c3',
        ],
    ],
    '/api/v1/settlement/batches' => fn() => ['batches' => [
        ['id' => 'STL-20261026', 'items' => 1832, 'total_usd' => 4120933.18, 'status' => 'settled'],
        ['id' => 'STL-20261027', 'items' => 611, 'total_usd' => 1288410.02, 'status' => 'open'],
    ]],
];

if (isset($routes[$path])) {
    echo json_encode($routes[$path](), JSON_PRETTY_PRINT);
} else {
    http_response_code(404);
    echo json_encode(['error' => 'not_found', 'path' => $path]);
}
