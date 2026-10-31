<?php
/**
 * PolyPay card-acquirer notification endpoint.
 */
require_once __DIR__ . '/../includes/functions.php';
header('Content-Type: application/json');

$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload) || empty($payload['intent']) || empty($payload['account']) || !isset($payload['amount'])) {
    http_response_code(400);
    echo json_encode(['result' => 'error', 'message' => 'malformed notification']);
    exit;
}

$intent = q1('SELECT * FROM topup_intents WHERE intent_ref = ? AND status = "pending"', [$payload['intent']]);
if (!$intent) {
    http_response_code(404);
    echo json_encode(['result' => 'error', 'message' => 'unknown or settled intent']);
    exit;
}

$amount = number_format((float)$payload['amount'], 2, '.', '');
if (isset($payload['signature'])) {
    $expected = hash_hmac('sha256', "{$payload['intent']}|{$payload['account']}|{$amount}", GATEWAY_SIGNING_KEY);
    if (!hash_equals($expected, (string)$payload['signature'])) {
        http_response_code(403);
        echo json_encode(['result' => 'rejected', 'message' => 'signature mismatch']);
        exit;
    }
}

if (($payload['status'] ?? '') !== 'captured') {
    qexec('UPDATE topup_intents SET status = "failed" WHERE id = ?', [$intent['id']]);
    echo json_encode(['result' => 'declined']);
    exit;
}

$acct = q1('SELECT * FROM accounts WHERE account_number = ?', [(string)$payload['account']]);
if (!$acct) {
    http_response_code(404);
    echo json_encode(['result' => 'error', 'message' => 'account not found']);
    exit;
}

$pdo = db();
$pdo->beginTransaction();
$bal = round($acct['balance'] + (float)$amount, 2);
qexec('UPDATE accounts SET balance = ? WHERE id = ?', [$bal, $acct['id']]);
qexec('INSERT INTO transactions (reference, account_id, direction, amount, balance_after, counterparty_name, description, category, channel) VALUES (?,?,?,?,?,?,?,?,?)',
    [new_reference(), $acct['id'], 'credit', $amount, $bal, 'PolyPay', 'Card top-up •••• ' . substr(preg_replace('/\D/', '', (string)($payload['card_last4'] ?? '')), -4), 'Top-up', 'Card']);
qexec('UPDATE topup_intents SET status = "captured" WHERE id = ?', [$intent['id']]);
$pdo->commit();

echo json_encode(['result' => 'credited', 'intent' => $intent['intent_ref'], 'amount' => $amount]);
