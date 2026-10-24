# Fête de Cadre — Vulnerable Photo Frame Shop (Security Training Lab)

> ⚠️ **Intentionally vulnerable software.** Fête de Cadre contains 25 deliberate security flaws. Run it only on an isolated lab machine or network. Never deploy it to the internet or on a network shared with production systems.
>
> 📕 **This README is the instructor copy.** It contains the full vulnerability matrix and solutions. Give students `STUDENT_BRIEF.md` instead.

---

## 1. Application overview

### Purpose
Fête de Cadre is a realistic online photo-frame boutique used to teach web application security testing and secure coding. Every vulnerability sits inside a believable business feature, and each one is fenced by a server-side containment layer so it can only be exploited the way the lab intends.

**Difficulty:** Intermediate. Vulnerabilities are moderately hidden behind business features; several chain together (for example, leaked credentials → back office → command injection).

### Features
| Area | Features |
|---|---|
| Storefront | Product catalogue with category filters, search and sorting; product pages with reviews; bag with promo codes; checkout with PDF invoices; public order tracking; custom framing requests with a reference-image preview; trade-only catalogue |
| Customer account | Order history and invoice downloads, profile, email and password changes, style/notification preferences (JSON API), wishlist export/import, account closure, password reset by emailed code |
| Workshop (staff) | Live order queue with status updates, low-stock alerts, custom-request inbox, courier-depot connectivity check, activity-log viewer |
| Admin | KPI dashboard with Chart.js revenue and category charts, best sellers, user management (roles, trade flag, activation), catalogue price/stock editing |

### Architecture
```
 Browser ──HTTP──▶ Apache + mod_php  (public/index.php front controller)
                     │   FallbackResource routes every path to index.php
                     ├── src/routes_shop.php        catalogue, reviews, tracking, custom framing, trade
                     ├── src/routes_auth.php        sign in, back-office sign in, register, password reset
                     ├── src/routes_cart.php        bag cookie, coupons, checkout, invoices
                     ├── src/routes_account.php     profile, email, password, invoice download
                     ├── src/routes_api.php         /api/v1 JSON (profile, orders, preferences, wishlist)
                     ├── src/routes_backoffice.php  order queue, activity log, depot check, admin
                     ├── src/routes_internal.php    loopback-only supplier sync, debug endpoint, backups
                     └── src/containment.php        server-side fences around each flaw
                              │
                              ▼  PDO (least-privilege user frame_app), sessions stored in MySQL
                         MySQL 8 / MariaDB  —  products, users, orders, reviews, coupons, sessions
```

### Technology stack
| Layer | Technology |
|---|---|
| Runtime | PHP 8.3, Apache 2.4 with mod_php (no framework; a small front controller + route registry) |
| Views | PHP templates, Bootstrap 5.3.3, Bootstrap Icons 1.11, Chart.js 4.4, jQuery 1.12.4 + jquery-validation 1.19.0 (deliberately outdated) |
| Data | MySQL 8.0 (Docker/Kubernetes) or MariaDB 10.11+ (VM); sessions in a MySQL table |
| Fonts/images | Fraunces + Manrope (self-hosted); product images are original CC0 renders; three editorial photos from Wikimedia Commons (see `ATTRIBUTIONS.md`) |
| Deployment | Docker Compose, Kubernetes (kustomize), Ubuntu/Debian VM with Apache |

All front-end libraries and fonts are bundled under `public/assets/vendor`, so the lab runs without internet access.

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
| `admin` | `gallery1` | admin | Trade account |
| `framer` | `Studio2024!` | staff | Leaked in an HTML comment on `/login` |
| `bianca` | `bianca123` | customer | Trade account, several orders |
| `devon` | `password1` | customer | Standard customer |
| `priya` | `sunshine` | customer | Standard customer |

Working promo codes: `FRAME15`, `WELCOME10`. Test card: any 13–19 digit number, e.g. `4242 4242 4242 4242`.

### Seed data
`db/init.sql` creates the schema, 5 users, 12 frames (one trade-only), 5 reviews, 10 orders spread over the past 14 days (so the admin charts have data), 3 coupons and the least-privilege database account `frame_app` (SELECT/INSERT/UPDATE/DELETE on `frameshop.*` only — no FILE privilege). Invoices for seeded orders are generated on first start by `scripts/bootstrap.php`.

---

## 3. Deployment instructions

### Serving on the machine's IPv4 address
The app is published on the machine's IPv4 by default so other hosts on an isolated lab network can reach it.

- **Docker:** the published port is `${LAB_BIND:-0.0.0.0}:8080:80`. `LAB_BIND` comes from `.env`. Set `LAB_BIND=0.0.0.0` (default) for every interface, or `LAB_BIND=127.0.0.1` for local-only. Find the machine IP with `hostname -I | awk '{print $1}'` and browse to `http://<machine-ip>:8080`.
- **VM:** `sudo ./scripts/install-vm.sh` binds to `0.0.0.0:8080`; `sudo LAB_BIND=127.0.0.1 ./scripts/install-vm.sh` keeps it local. Change the port with `APP_PORT=...`.
- **Kubernetes:** reach it on each node's IP at NodePort `30081`.

### Docker (single command)
```bash
docker compose up -d --build
# find the machine IP, then open http://<machine-ip>:8080
```
MySQL takes 20–40 seconds to initialise on first run; the app container waits and generates invoices on boot. Reset to a clean state with `./scripts/reset-lab.sh docker`.

### Kubernetes (kind, minikube, k3s)
```bash
docker build -t fetedecadre-app:1.0 .
kind load docker-image fetedecadre-app:1.0        # kind
# minikube image load fetedecadre-app:1.0         # minikube
kubectl apply -k k8s/
kubectl -n fetedecadre-lab get pods -w
```
Open the app on NodePort `30081` (node IP, or `minikube service -n fetedecadre-lab fetedecadre-app --url`).

> Use the NodePort, **not** `kubectl port-forward`. Port-forwarding originates from `127.0.0.1` inside the pod, which makes the loopback-only SSRF target directly reachable and spoils that exercise.

`k8s/networkpolicy.yaml` restricts the app's egress to the database and DNS, so SSRF cannot reach cloud metadata or other workloads (your CNI must enforce NetworkPolicy, e.g. Calico or Cilium). Reset with `./scripts/reset-lab.sh k8s`.

### Local VM (Ubuntu / Debian)
```bash
sudo ./scripts/install-vm.sh                     # serves on 0.0.0.0:8080
sudo LAB_BIND=127.0.0.1 ./scripts/install-vm.sh  # local only
```
Installs Apache + mod_php + MariaDB, loads the schema, copies the app to `/var/www/fetedecadre`, and serves it. Logs: `/var/log/apache2/fetedecadre_error.log`. Reset with `./scripts/reset-lab.sh vm`. On Windows Server, use Docker Desktop or WSL 2 with the Docker instructions.

### Manual (development)
```bash
mysql -uroot < db/init.sql
cp .env.example .env            # set DB_HOST etc.
DB_HOST=127.0.0.1 php scripts/bootstrap.php
php -S 0.0.0.0:8080 -t public public/router.php
```

### Verifying a deployment
Two harnesses are included:
- `php scripts/verify_logic.php` — direct-logic checks of the vulnerability sinks and the containment allow-lists (no server needed). Expected: `12 passed, 0 failed`.
- `php scripts/verify_lab.php [base_url]` — full end-to-end checks against a running instance. Run it against a **fresh** deployment (it changes seeded passwords and places orders), then run `./scripts/reset-lab.sh`.

> Note: the single-threaded `php -S` development server cannot serve the SSRF sub-request while handling the preview request, so SSRF only demonstrates fully under Apache/php-fpm (Docker/VM). Every other check works under all modes.

---

## 4. Lab containment (instructor only)

These rules are enforced in `src/containment.php` and are never mentioned in the UI. Rejected input gets a generic message ("Enter a valid hostname or IP address", "Your search contains characters that aren't allowed", "Invoice not found").

| Flaw | What works | What is blocked |
|---|---|---|
| XSS (reflected + stored) | Any payload without a script tag or `alert`, e.g. `<img src=x onerror=confirm(1)>`, `<svg onload=prompt(1)>` | `<script>` in any case/spacing; the word `alert` including HTML-entity, `\u`, `\x` and `%` encoded forms |
| Path traversal | `/etc/passwd`, `/etc/hosts` (relative `../` or absolute) | Every other path: `/etc/shadow`, `.env`, source files, `/proc/*`, null-byte and `....//` tricks |
| Command injection | `uname`, `id`, `pwd`, `hostname`, `whoami` after `;` `|` `&&` `||` `&` or newline, or inside `$( )` / backticks | Any other command, any second injected command, redirection, pipes to other binaries, uppercase variants |
| SQL injection | UNION/boolean/time-based reads of the `frameshop` database (via `/track`) | Stacked queries, `LOAD_FILE`/`INTO OUTFILE` (no FILE privilege), writes outside the app's grants |
| SSRF | `http://` and `https://` URLs, including loopback | `file://`, `gopher://` and other schemes |

Other safeguards that keep the app "secure everywhere else": every other DB query uses PDO prepared statements; every other form carries a CSRF token; output is escaped with `htmlspecialchars` everywhere except the two intentional XSS sinks; the customer login is rate-limited; `X-Frame-Options: DENY` is sent everywhere except `/account`; the back-office login rotates the session id; generic error pages are shown everywhere except the coupon handler.

---

## 5. Vulnerability matrix

Summary:

| # | OWASP 2025 | CWE | Vulnerability | Endpoint |
|---|---|---|---|---|
| 1 | A01 Broken Access Control | CWE-22 | Path traversal | `GET /account/invoices/download?file=` |
| 2 | A01 | CWE-352 | Cross-site request forgery | `GET/POST /account/email` |
| 3 | A01 | CWE-548 | Directory listing | `GET /backups/` |
| 4 | A01 | CWE-918 | Server-side request forgery | `POST /custom/preview` |
| 5 | A01 | CWE-615 | Secrets in source comments | `GET /login`, `/assets/js/app.js` |
| 6 | A02 Security Misconfiguration | CWE-16 | CORS misconfiguration | `/api/v1/*` |
| 7 | A02 | CWE-526 | Environment exposure | `GET /debug/env` |
| 8 | A02 | CWE-1004 | Session cookie without HttpOnly | `fdc_sid` cookie |
| 9 | A03 Supply Chain | CWE-1035 | Known-vulnerable component | jQuery 1.12.4 on every page |
| 10 | A03 | CWE-1395 | Vulnerable dependency | jquery-validation 1.19.0 |
| 11 | A04 Cryptographic Failures | CWE-319 | Cleartext transmission | Whole site (HTTP, no HSTS) |
| 12 | A04 | CWE-328 | Reversible/weak hash (MD5) | `users.password_hash` |
| 13 | A05 Injection | CWE-77 | OS command injection | `POST /staff/diagnostics` |
| 14 | A05 | CWE-80 | Reflected XSS | `GET /shop?q=` |
| 15 | A05 | CWE-80 | Stored XSS | `POST /product/:slug/review` |
| 16 | A05 | CWE-89 | SQL injection | `GET /track?order=` |
| 17 | A06 Insecure Design | CWE-266 | Incorrect privilege assignment | `POST /register` |
| 18 | A06 | CWE-1021 | Clickjacking | `/account` |
| 19 | A07 Authentication Failures | CWE-287 | Improper authentication | `fdc_remember` cookie |
| 20 | A07 | CWE-308 | Single-factor auth, no lockout | `POST /backoffice/login` |
| 21 | A07 | CWE-384 | Session fixation | `GET /login?sid=` |
| 22 | A07 | CWE-640 | Weak password recovery | `POST /forgot/verify` |
| 23 | A08 Software/Data Integrity | CWE-565 | Unverified cookie data (price tampering) | `POST /checkout` |
| 24 | A08 | CWE-502 | PHP object injection (deserialization) | `POST /api/v1/wishlist/import` |
| 25 | A09 Logging Failures | CWE-532 | Sensitive data in logs | `logs/activity.log`, `GET /staff/logs` |
| 26 | A10 Exceptional Conditions | CWE-755 | Improper exception handling | `POST /bag/coupon` |

> That is 26 rows for 25 required CWEs: A08 is covered by **two** distinct integrity failures (price tampering and object injection) because PHP object injection is the signature A08 flaw for this stack and was worth including alongside the cookie-integrity issue. If you need exactly 25, treat #23 and #24 as the single A08 entry.

Flags: `FLAG{ssrf_reached_the_moulding_store}`, `FLAG{env_files_are_not_a_frame_safe}`, `FLAG{trade_catalogue_is_not_access_control}`, `FLAG{admin_dashboard_unlocked}`.

### Details

#### 1. CWE-22 Path traversal
- **OWASP:** A01 · **File:** `src/routes_account.php` (`/account/invoices/download`), `src/containment.php` (`resolve_download`)
- **Endpoint:** `GET /account/invoices/download?file=INV-FC-30411.pdf` · **Attack surface:** authenticated customer, query parameter
- **Description:** The file name is joined to the invoice directory and resolved lexically; absolute paths and `../` sequences that land on `/etc/passwd` or `/etc/hosts` are served.
- **Educational objective:** Recognise file-download parameters as traversal candidates; understand canonicalisation.
- **Validation:** Sign in as `bianca`, open `/account/invoices/download?file=../../../../../../etc/passwd` or `?file=/etc/hosts`.
- **Expected impact:** Disclosure of `/etc/passwd` and `/etc/hosts`.
- **Remediation:** Look invoices up by an order id owned by the user; never accept file paths. If paths are unavoidable, resolve and confirm the result stays within the base directory.

#### 2. CWE-352 Cross-site request forgery
- **OWASP:** A01 · **File:** `src/routes_account.php` (`account_email`), `src/security.php` (`CSRF_EXEMPT`)
- **Endpoint:** `GET|POST /account/email` · **Attack surface:** any page the signed-in victim visits
- **Description:** The email-change endpoint is exempt from CSRF checks and also accepts GET.
- **Educational objective:** Identify state-changing requests without anti-CSRF tokens; build a PoC.
- **Validation:** While signed in, load a PoC page containing `<img src="http://<host>:8080/account/email?email=attacker@evil.test">`; the account email changes. (A same-site page on another port works for the cookie to be sent; for a cross-site origin use a top-level navigation.)
- **Expected impact:** Account takeover: change the email, then trigger password reset (chains with #22).
- **Remediation:** Require a CSRF token on every state-changing request, accept only POST, set `SameSite`, re-authenticate for email changes.

#### 3. CWE-548 Directory listing
- **OWASP:** A01 · **File:** `src/routes_internal.php` (`backups_index`) · **Folder:** `storage/backups/`
- **Endpoint:** `GET /backups/` (hinted in `/robots.txt`) · **Attack surface:** unauthenticated
- **Description:** A legacy backup folder is served with an auto-generated index.
- **Educational objective:** Use robots.txt and content discovery; recognise sensitive leftovers.
- **Validation:** Browse `/backups/`; download `frameshop_2024-05-02.sql.bak` and `apache-vhost.conf.old`.
- **Expected impact:** Old MD5 hashes (crackable), a retired staff coupon, and confirmation of the loopback-only `/internal/` path (SSRF hint).
- **Remediation:** Disable directory listing, keep backups out of the web root, delete stale files.

#### 4. CWE-918 Server-side request forgery
- **OWASP:** A01 · **File:** `src/routes_shop.php` (`/custom/preview`), target in `src/routes_internal.php`
- **Endpoint:** `POST /custom/preview` (JSON `{"url": …}`, from the *Custom framing* page "Preview" button) · **Attack surface:** authenticated customer
- **Description:** The server fetches any http(s) URL with cURL and returns the body when it is not an image.
- **Educational objective:** Spot server-side fetch features; pivot to loopback-only services.
- **Validation:** Preview `http://127.0.0.1:8080/internal/supplier-sync`. Direct browser access from a remote host returns 404; only the server (loopback) sees it.
- **Expected impact:** Supplier API key and `FLAG{ssrf_reached_the_moulding_store}`; reaching `/debug/env` via loopback.
- **Remediation:** Allow-list image hosts; resolve and reject private/loopback/link-local addresses (re-check after redirects); disable redirects; never echo non-image responses.

#### 5. CWE-615 Sensitive information in source comments
- **OWASP:** A01 · **Files:** `templates/login.php` (HTML comment), `public/assets/js/app.js` (JS comment)
- **Endpoint:** `GET /login` (view source), `GET /assets/js/app.js` · **Attack surface:** unauthenticated
- **Description:** A developer TODO leaks the staff back-office credentials; a JS comment reveals the internal sync URL and the debug endpoint.
- **Educational objective:** Always review page source and client scripts.
- **Validation:** View source of `/login`: `framer / Studio2024!`. Sign in at `/backoffice/login`.
- **Expected impact:** Staff access (order data, activity log, diagnostics → chains with #13 and #25).
- **Remediation:** Strip comments in build pipelines, secret-scan repositories, never put credentials in code.

#### 6. CWE-16 Configuration weakness: permissive CORS
- **OWASP:** A02 · **File:** `public/index.php` (API CORS block)
- **Endpoint:** `GET /api/v1/me`, `/api/v1/orders` · **Attack surface:** any origin the victim visits
- **Description:** The API reflects any `Origin` header and sets `Access-Control-Allow-Credentials: true`.
- **Educational objective:** Test CORS with a custom Origin header; write a cross-origin data-theft PoC.
- **Validation:** `curl -i -H "Origin: http://evil.test" -b "fdc_sid=…" http://<host>:8080/api/v1/me` → `Access-Control-Allow-Origin: http://evil.test`.
- **Expected impact:** Theft of name, email, phone, address, preferences and order history.
- **Remediation:** Allow-list exact trusted origins; never combine a reflected origin with credentials.

#### 7. CWE-526 Exposure of environment variables
- **OWASP:** A02 · **File:** `src/routes_internal.php` (`/debug/env`)
- **Endpoint:** `GET /debug/env` (hinted in robots.txt and app.js) · **Attack surface:** unauthenticated
- **Description:** A debug route returns the whole environment.
- **Educational objective:** Discover debug endpoints; understand why env vars are not a secret store.
- **Validation:** Open `/debug/env`.
- **Expected impact:** DB password, `SECRET_KEY`, supplier and payment keys, `FLAG{env_files_are_not_a_frame_safe}`.
- **Remediation:** Remove debug routes in production; use a secrets manager; never return environment data.

#### 8. CWE-1004 Sensitive cookie without HttpOnly
- **OWASP:** A02 · **File:** `src/security.php` (`session_boot`, `httponly => false`)
- **Endpoint:** every response that sets `fdc_sid` · **Attack surface:** any XSS
- **Description:** The session cookie is readable from JavaScript.
- **Educational objective:** Inspect cookie flags; chain with XSS for session theft.
- **Validation:** In DevTools, `document.cookie` includes `fdc_sid`. Chain with stored XSS (#15).
- **Expected impact:** Session hijacking of anyone who views an XSS payload, including staff.
- **Remediation:** `HttpOnly; Secure; SameSite=Lax` on the session cookie.

#### 9. CWE-1035 Using components with known vulnerabilities
- **OWASP:** A03 · **Files:** `public/assets/vendor/jquery-1.12.4.min.js`, `templates/_layout.php`
- **Endpoint:** every page · **Attack surface:** client-side library
- **Description:** jQuery 1.12.4 is affected by CVE-2015-9251, CVE-2019-11358 and CVE-2020-11022/11023.
- **Educational objective:** Fingerprint client libraries and map versions to CVEs.
- **Validation:** Check the script URL, run `jQuery.fn.jquery` in the console, or scan with Retire.js.
- **Expected impact:** Latent XSS / prototype-pollution risk wherever jQuery processes untrusted data.
- **Remediation:** Upgrade to jQuery 3.7+ (or remove it); use SCA in CI; keep an SBOM.

#### 10. CWE-1395 Dependency on vulnerable third-party component
- **OWASP:** A03 · **Files:** `public/assets/vendor/jquery.validate-1.19.0.min.js`, `templates/_layout.php`
- **Endpoint:** every page · **Attack surface:** client-side library
- **Description:** jquery-validation 1.19.0 is affected by known prototype-pollution / ReDoS advisories (e.g. CVE-2021-21252, CVE-2022-31147) fixed in later releases.
- **Educational objective:** Inventory every bundled dependency, not just the obvious one; map transitive/companion libraries to advisories.
- **Validation:** Retire.js or `npm audit` on the vendored version string; confirm it loads on every page.
- **Expected impact:** Client-side DoS / pollution gadget availability.
- **Remediation:** Upgrade to the patched release; pin and monitor front-end dependencies.

#### 11. CWE-319 Cleartext transmission
- **OWASP:** A04 · **File:** `public/index.php` (no HSTS, plain HTTP), deployment configs
- **Endpoint:** whole site, especially `POST /login`, `POST /checkout` · **Attack surface:** network path
- **Description:** Credentials and card numbers travel over plain HTTP, with no HSTS or HTTPS redirect.
- **Educational objective:** Capture and read credentials on the wire.
- **Validation:** Capture with Wireshark on port 8080 while signing in.
- **Expected impact:** Credential and card interception by anyone on the network path.
- **Remediation:** TLS everywhere, HSTS, `Secure` cookies, redirect HTTP→HTTPS.

#### 12. CWE-328 Reversible / weak one-way hash
- **OWASP:** A04 · **File:** `src/security.php` (`hash_password`), `db/init.sql` (`MD5()`)
- **Endpoint:** storage of all passwords; exposed via #16 and #3 · **Attack surface:** database
- **Description:** Passwords are unsalted MD5.
- **Educational objective:** Crack weak hashes; understand salting and slow KDFs.
- **Validation:** Dump hashes with #16, then `hashcat -m 0 hashes.txt rockyou.txt`. `admin` → `gallery1`.
- **Expected impact:** Plaintext passwords for every account, including admin.
- **Remediation:** `password_hash()` with bcrypt/Argon2id; force resets after migration.

#### 13. CWE-77 Command injection
- **OWASP:** A05 · **Files:** `src/routes_backoffice.php` (`/staff/diagnostics`), `src/containment.php` (`build_ping`)
- **Endpoint:** `POST /staff/diagnostics` · **Attack surface:** staff account (get it via #5, #12, #17, #19)
- **Description:** The depot hostname is concatenated into a `ping` shell command run with `shell_exec`.
- **Educational objective:** Identify shell-backed features; use separators and command substitution.
- **Validation:** As `framer`, Workshop → Courier depot check, enter `127.0.0.1; id` (also `| whoami`, `&& uname`, `; pwd`, `; hostname`, `` `id` ``).
- **Expected impact:** Command execution as the web user (`www-data`); host/network reconnaissance.
- **Remediation:** Use `escapeshellarg`, an argument array with `proc_open`, strict hostname validation, or a native check — never a shell string.

#### 14. CWE-80 Reflected XSS
- **OWASP:** A05 · **Files:** `src/routes_shop.php` (`/shop`), `templates/shop.php` (unescaped term)
- **Endpoint:** `GET /shop?q=` · **Attack surface:** crafted link
- **Description:** The search term is echoed unencoded after a naive keyword blocklist.
- **Educational objective:** Find reflections; bypass a blocklist.
- **Validation:** `/shop?q=<img src=x onerror=confirm(document.domain)>`. (`<script>` and `alert` are filtered — students must find alternatives.)
- **Expected impact:** Script execution in the victim's session; cookie theft via #8.
- **Remediation:** Context-aware output encoding; a strict Content-Security-Policy; no blocklists.

#### 15. CWE-80 Stored XSS
- **OWASP:** A05 · **Files:** `src/routes_shop.php` (`/product/:slug/review`), `templates/product.php` (unescaped body)
- **Endpoint:** `POST /product/:slug/review`, rendered on the product page · **Attack surface:** any signed-in customer
- **Description:** Review text is stored and rendered raw for every visitor.
- **Educational objective:** Persistent XSS and its reach versus reflected XSS.
- **Validation:** Post a review `<svg onload=confirm(document.cookie)>` on any product; reload in another browser.
- **Expected impact:** Mass session theft of every visitor, including staff and admins.
- **Remediation:** Encode output; sanitise rich text with an allow-list (e.g. HTML Purifier); add CSP.

#### 16. CWE-89 SQL injection
- **OWASP:** A05 · **File:** `src/routes_shop.php` (`/track`, concatenated query)
- **Endpoint:** `GET /track?order=` · **Attack surface:** unauthenticated
- **Description:** The order number is concatenated into a 5-column SELECT run with `PDO::query`.
- **Educational objective:** Detect, enumerate columns, UNION-extract; use sqlmap responsibly.
- **Validation:** `/track?order=' UNION SELECT username,password_hash,email,role,id FROM users-- -`. Or `sqlmap -u "http://<host>:8080/track?order=FC-30411" --batch -D frameshop --dump`.
- **Expected impact:** Full read of the shop database (users, hashes, orders, addresses). File reads and stacked queries are blocked by DB privileges / PDO.
- **Remediation:** Prepared statements everywhere (as used elsewhere); least-privilege DB account (already applied).

#### 17. CWE-266 Incorrect privilege assignment
- **OWASP:** A06 · **Files:** `src/routes_auth.php` (`/register`), `templates/register.php` (hidden `role` field)
- **Endpoint:** `POST /register` · **Attack surface:** unauthenticated
- **Description:** The server trusts a client-supplied `role` field (mass assignment).
- **Educational objective:** Tamper with hidden fields in an intercepting proxy.
- **Validation:** Register with Burp intercepting; change `role=customer` to `role=admin`. Visit `/admin`.
- **Expected impact:** Full admin access: all customer data, roles, catalogue, `FLAG{admin_dashboard_unlocked}`.
- **Remediation:** Assign roles server-side only; allow-list accepted fields.

#### 18. CWE-1021 Improper restriction of rendered UI layers (clickjacking)
- **OWASP:** A06 · **File:** `public/index.php` (`/account` omits `X-Frame-Options`)
- **Endpoint:** `/account` (Close account, Change password, Update email) · **Attack surface:** attacker page framing the account area
- **Description:** The account area can be framed by any site (no X-Frame-Options / frame-ancestors).
- **Educational objective:** Build a clickjacking PoC with an invisible iframe.
- **Validation:** Host `<iframe src="http://<host>:8080/account#settings" style="opacity:.05;width:1200px;height:1400px">` with a decoy button over **Close my account**.
- **Expected impact:** Victim closes their own account (admin can reactivate) or submits other account actions.
- **Remediation:** `X-Frame-Options: DENY` and CSP `frame-ancestors 'none'` on every page.

#### 19. CWE-287 Improper authentication
- **OWASP:** A07 · **Files:** `src/routes_auth.php` (sets `fdc_remember`), `public/index.php` (trusts it)
- **Endpoint:** any page with a `fdc_remember` cookie · **Attack surface:** client-controlled cookie
- **Description:** "Keep me signed in" stores `base64(username|timestamp)` with no signature; the server logs in whoever it names.
- **Educational objective:** Decode and forge tokens; understand signing/HMAC.
- **Validation:** `curl -b "fdc_remember=$(printf 'admin|0' | base64)" http://<host>:8080/admin` → admin dashboard.
- **Expected impact:** Log in as any user without a password.
- **Remediation:** Random, server-stored, hashed remember-me tokens bound to the user and rotated on use.

#### 20. CWE-308 Use of single-factor authentication
- **OWASP:** A07 · **File:** `src/routes_auth.php` (`/backoffice/login`)
- **Endpoint:** `POST /backoffice/login` · **Attack surface:** unauthenticated
- **Description:** Staff/admin accounts use only a password, with no rate limit or lockout (unlike the customer login).
- **Educational objective:** Credential brute-forcing; why privileged accounts need MFA.
- **Validation:** Burp Intruder or `hydra` against the password field (keep the session cookie that matches the CSRF token). Finds `gallery1`; no 429 ever appears.
- **Expected impact:** Admin account takeover.
- **Remediation:** MFA for privileged roles; rate limiting; lockout/backoff; breached-password checks.

#### 21. CWE-384 Session fixation
- **OWASP:** A07 · **Files:** `src/security.php` (`session_boot` accepts `?sid=`), `src/routes_auth.php` (customer login does not rotate)
- **Endpoint:** `GET /login?sid=<id>` then `POST /login` · **Attack surface:** crafted "magic sign-in link"
- **Description:** The app adopts a session id from the URL and keeps it after customer sign-in.
- **Educational objective:** Understand why session ids must change at authentication.
- **Validation:** Send a victim `/login?sid=attacker-chosen-session-id-01234`; after they sign in, the attacker uses the same `fdc_sid` cookie and is logged in as them.
- **Expected impact:** Hijack the victim's authenticated session.
- **Remediation:** `session_regenerate_id(true)` on login (as the back-office login already does); never accept session ids from URLs.

#### 22. CWE-640 Weak password recovery mechanism
- **OWASP:** A07 · **File:** `src/routes_auth.php` (`/forgot`, `/forgot/verify`)
- **Endpoint:** `POST /forgot/verify` · **Attack surface:** unauthenticated
- **Description:** Reset codes are 4 digits (10,000 possibilities), valid 30 minutes, with unlimited attempts. Codes are "emailed" to the server log.
- **Educational objective:** Brute-force a short OTP; design secure recovery.
- **Validation:** Request a reset for `devon`; brute-force `/forgot/verify` with codes 0000–9999 (keep session + CSRF token). A 302 marks the right code.
- **Expected impact:** Takeover of any account, including admin.
- **Remediation:** Long random single-use tokens by link; attempt limits; short expiry; notify the owner.

#### 23. CWE-565 Reliance on cookies without integrity (price tampering)
- **OWASP:** A08 · **Files:** `src/cart.php` (`read_bag`), `src/routes_cart.php` (`/checkout` uses cookie prices)
- **Endpoint:** `fdc_bag` cookie → `POST /checkout` · **Attack surface:** authenticated customer
- **Description:** The bag — including unit prices — lives in an unsigned base64 JSON cookie, and checkout trusts those prices.
- **Educational objective:** Decode and tamper client-side state; understand integrity verification.
- **Validation:** Add a frame, base64-decode `fdc_bag`, set `"price":0.01`, re-encode, check out. The order total reflects the tampered price.
- **Expected impact:** Buying any product at any price.
- **Remediation:** Keep the cart server-side (or HMAC-sign it); always recompute prices from the database at checkout.

#### 24. CWE-502 Deserialization of untrusted data (PHP object injection)
- **OWASP:** A08 · **File:** `src/routes_api.php` (`/api/v1/wishlist/import`, `unserialize`)
- **Endpoint:** `POST /api/v1/wishlist/import` (JSON `{"token": …}`) · **Attack surface:** authenticated customer
- **Description:** The importer base64-decodes and `unserialize()`s the token before checking its type, so any class is instantiated. A `WishlistAuditor` gadget with a `__destruct` that writes `logFile`/`note` is present in the codebase.
- **Educational objective:** Understand PHP object injection and POP-gadget chains.
- **Validation:** Export a token via `GET /api/v1/wishlist/export`, observe the serialized format, then craft a `WishlistAuditor` object and import it; the type check happens only after `unserialize` has run.
- **Expected impact:** Arbitrary object instantiation; with the bundled gadget, attacker-controlled file writes.
- **Remediation:** Never `unserialize` untrusted input; use JSON; if unavoidable, pass `['allowed_classes' => false]` and verify integrity with an HMAC.

#### 25. CWE-532 Insertion of sensitive information into log files
- **OWASP:** A09 · **Files:** `src/logger.php`, `src/routes_auth.php` (logs password), `src/routes_cart.php` (logs full card)
- **Endpoint:** `logs/activity.log`, viewable at `GET /staff/logs` · **Attack surface:** staff account, server/container logs
- **Description:** Every sign-in attempt logs the plaintext password; every order logs the full card number.
- **Educational objective:** Review logs for secrets; understand log hygiene and PCI DSS.
- **Validation:** As `framer`, open Workshop → Activity log; look for `password=` entries, including the admin's.
- **Expected impact:** Credential and card disclosure to anyone with log access; staff-to-admin escalation.
- **Remediation:** Never log secrets or PANs; redact by allow-list; restrict log access; alert on auth anomalies.

#### 26. CWE-755 Improper handling of exceptional conditions
- **OWASP:** A10 · **Files:** `src/routes_cart.php` (`/bag/coupon` catch block), `templates/debug_error.php`
- **Endpoint:** `POST /bag/coupon` · **Attack surface:** any visitor with a bag
- **Description:** A non-ASCII coupon code throws, and the handler renders the stack trace plus internal context instead of a generic error.
- **Educational objective:** Fuzz inputs for unhandled exceptions; recognise information leakage in errors.
- **Validation:** Submit a coupon code containing raw non-ASCII bytes, e.g. `code=%C9%A0bad`.
- **Expected impact:** Stack trace, file paths, PHP version, PID, DB host, user and database name.
- **Remediation:** Validate input before processing; handle expected errors explicitly; return generic errors; log details server-side only.

---

## 6. Lab exercises

### Suggested student exercises
1. **Recon:** map the app, read `robots.txt`, view page source and client scripts. Find the leaked credentials, the backup folder and the debug endpoint (#3, #5, #7, #9, #10).
2. **Injection:** dump the users table from the order tracker and crack the hashes (#16, #12).
3. **Client-side:** get JavaScript running through the search box and a review despite the filter; steal a session cookie (#14, #15, #8).
4. **Access control:** read system files from the invoice download and reach an internal service from the custom-framing page (#1, #4).
5. **Authentication:** become admin four different ways (#17, #19, #20, #22) and hijack a session (#21).
6. **Business logic & integrity:** buy a frame for one cent, then craft a wishlist token to inject a PHP object (#23, #24).
7. **Back office:** from the framer account, run commands on the server and escalate to admin using what the logs reveal (#13, #25).
8. **Cross-site:** build PoC pages for CSRF, CORS data theft and clickjacking (#2, #6, #18).
9. **Errors & transport:** trigger the coupon debug dump, then capture credentials in Wireshark (#26, #11).
10. **Capstone chain:** unauthenticated → staff → admin, using no credentials from this README.

### Learning objectives
- Map each finding to OWASP Top 10 2025 and the correct CWE.
- Distinguish reflected from stored XSS and bypass naive blocklists responsibly.
- Chain low-severity findings into high-impact attack paths.
- Explain business-logic, integrity and deserialization flaws that scanners miss.
- Write clear findings with evidence, impact and remediation.
- Fix each flaw in PHP and confirm the fix.

### Verification procedure
1. Students record each finding with request/response evidence and the flag where applicable.
2. Instructor runs `php scripts/verify_logic.php` (and `verify_lab.php` on Docker/VM) on a clean instance, then compares student evidence with section 5.
3. For secure-coding courses, students patch a flaw and re-run the harness; the matching check should then fail while the rest of the app keeps working.

### Recommended tooling
Burp Suite or OWASP ZAP · browser DevTools · sqlmap · hashcat or John the Ripper · hydra or Burp Intruder · ffuf / gobuster / dirsearch · Retire.js and an SCA scanner · Wireshark · `curl` and a small local web server for PoC pages · a PHP POP-gadget reference (e.g. PHPGGC) for #24.

### Secure coding remediation guidance
Each finding in section 5 lists its fix. Project-wide checklist: prepared statements everywhere; encode all output and add a CSP; CSRF token on every state-changing route; regenerate sessions at login and set `HttpOnly; Secure; SameSite`; `password_hash()` not MD5; keep prices and roles server-side; never `unserialize` untrusted input; remove debug routes and secret comments; serve only over TLS with HSTS; restrict CORS to known origins; deny framing; never shell out with user input; keep front-end dependencies patched with SCA in CI; never log secrets; return generic errors.

---

## 7. Project layout
```
public/index.php          front controller, middleware, router dispatch
public/router.php         shim for the PHP built-in dev server
public/assets/            css, js, images, vendor (Bootstrap, icons, Chart.js, jQuery 1.12.4, jquery-validation, fonts)
src/                      config, db, security (sessions/CSRF/auth), helpers, logger, pdf, cart, containment
src/routes_*.php          shop, auth, cart, account, api, backoffice, internal
templates/                PHP view templates (storefront, account, staff/, admin/, layouts)
db/init.sql               schema, seed data, least-privilege DB user
storage/backups/          intentionally listed legacy backups
storage/invoices/         generated PDF invoices
k8s/                      namespace, secrets, MySQL, app, NetworkPolicy, kustomization
docker/                   Apache vhost config, entrypoint
scripts/                  install-vm.sh, reset-lab.sh, bootstrap.php, verify_logic.php, verify_lab.php
Dockerfile, docker-compose.yml, .env, .env.example
ATTRIBUTIONS.md           image credits
STUDENT_BRIEF.md          spoiler-free brief for students
```

Image credits: product images are original CC0 renders; three editorial photos are from Wikimedia Commons under the licences in `ATTRIBUTIONS.md`.
