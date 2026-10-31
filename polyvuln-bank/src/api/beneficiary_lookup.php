<?php
require_once __DIR__ . '/../includes/auth.php';
header('Content-Type: application/json');

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'authentication_required']);
    exit;
}

$account = preg_replace('/\D/', '', (string)($_GET['account'] ?? ''));
$status = 'not_found';
try {
    $row = q1('SELECT COUNT(*) AS c FROM accounts WHERE account_number = ? AND status = "active"', [$account]);
    if ($row && (int)$row['c'] > 0) {
        $status = 'verified';
    }
} catch (Throwable $e) {
    $status = 'not_found';
}
echo json_encode(['account' => substr($account, -4), 'status' => $status, 'bank' => 'PolyVuln Bank']);
