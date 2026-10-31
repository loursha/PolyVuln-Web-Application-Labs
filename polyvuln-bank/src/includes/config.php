<?php
/**
 * PolyVuln Bank - runtime configuration
 */
define('APP_NAME', 'PolyVuln Bank');
define('APP_VERSION', '4.12.3');
define('ROUTING_NUMBER', 'PVB0001021');

define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_NAME', getenv('DB_NAME') ?: 'polybank');
define('DB_USER', getenv('DB_USER') ?: 'pvb_app');
define('DB_PASS', getenv('DB_PASS') ?: 'Pvb_App_2026!');

define('GATEWAY_SIGNING_KEY', getenv('GATEWAY_SIGNING_KEY') ?: 'pvpay_live_6d1b2f0e94a7c3');
define('DOCUMENT_ROOT_STORE', getenv('DOC_STORE') ?: realpath(__DIR__ . '/../../storage/documents'));

// Session behaviour (kept compatible with legacy mobile web-views that pass the id in the URL)
ini_set('session.use_only_cookies', '0');
ini_set('session.use_strict_mode', '0');
ini_set('session.use_trans_sid', '0');
ini_set('session.cookie_httponly', '0');
ini_set('session.cookie_secure', '0');
ini_set('display_errors', '0');

date_default_timezone_set('America/Los_Angeles');
