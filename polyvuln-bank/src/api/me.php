<?php
/**
 * Profile API used by the mobile web-view and partner widgets.
 */
require_once __DIR__ . '/../includes/auth.php';

$origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
header('Access-Control-Allow-Origin: ' . $origin);
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'authentication_required']);
    exit;
}
$u = current_user();
$accounts = array_map(fn($a) => [
    'id' => (int)$a['id'], 'number' => $a['account_number'], 'type' => $a['type'], 'balance' => (float)$a['balance'], 'currency' => $a['currency'],
], user_accounts((int)$u['id']));
echo json_encode([
    'id' => (int)$u['id'], 'username' => $u['username'], 'full_name' => $u['full_name'], 'email' => $u['email'],
    'phone' => $u['phone'], 'dob' => $u['dob'], 'address' => $u['address'], 'role' => $_SESSION['role'],
    'accounts' => $accounts, 'session' => session_id(),
], JSON_PRETTY_PRINT);
