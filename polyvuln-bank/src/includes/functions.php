<?php
require_once __DIR__ . '/db.php';

function e($v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function money($v, string $cur = 'USD'): string
{
    $sym = ['USD' => '$', 'EUR' => '€', 'GBP' => '£', 'INR' => '₹'][$cur] ?? '';
    $neg = $v < 0;
    return ($neg ? '−' : '') . $sym . number_format(abs((float)$v), 2);
}

function mask_account(string $n): string
{
    return '•••• ' . substr($n, -4);
}

/**
 * Content filter for free-text customer input (memos, search).
 * Removes script blocks and blocked keywords.
 */
function pv_clean(string $s): string
{
    $s = preg_replace('#<\s*/?\s*script\b[^>]*>#i', '', $s);
    $s = preg_replace('#alert#i', '', $s);
    return $s;
}

function flash(string $type, string $msg): void
{
    $_SESSION['flash'][] = [$type, $msg];
}

function flashes(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

function redirect(string $to): void
{
    header('Location: ' . $to);
    exit;
}

function new_reference(): string
{
    return 'PV' . date('ymd') . random_int(100000, 999999);
}

function initials(string $name): string
{
    $p = preg_split('/\s+/', trim($name));
    return strtoupper(substr($p[0] ?? '', 0, 1) . substr(end($p) ?: '', 0, 1));
}

function user_accounts(int $uid): array
{
    return qall('SELECT * FROM accounts WHERE user_id = ? ORDER BY type, id', [$uid]);
}

function category_icon(string $c): string
{
    return [
        'Groceries' => 'fa-shopping-basket', 'Transport' => 'fa-car', 'Subscriptions' => 'fa-play-circle',
        'Utilities' => 'fa-bolt', 'Shopping' => 'fa-shopping-bag', 'Dining' => 'fa-utensils',
        'Health' => 'fa-heartbeat', 'Travel' => 'fa-plane', 'Income' => 'fa-briefcase',
        'Housing' => 'fa-home', 'Transfers' => 'fa-exchange-alt', 'Interest' => 'fa-percentage',
        'Top-up' => 'fa-credit-card',
    ][$c] ?? 'fa-receipt';
}
