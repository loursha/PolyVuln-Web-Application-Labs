# PolyVuln Bank

> **Intentionally vulnerable web application for security training.**
> Built for penetration-testing labs, secure-coding workshops, CTFs and red-team practice.
> **Never deploy this on a public or production network. Authorized, isolated lab use only.**

PolyVuln Bank is a realistic retail-banking portal — checking and savings accounts, card
management, transfers, payees, card top-ups, statements and a staff operations console —
with **25 deliberately planted vulnerabilities** woven into otherwise plausible banking
business logic. The application is fully functional: balances move, statements generate,
charts render, cards freeze.

© PolyVuln 2026.

---

## 1. Application overview

### Purpose
A single, self-contained target that exercises most of the OWASP Top 10 (2025 draft categories)
inside one coherent banking workflow, so learners practise *finding and chaining* flaws in a
system that behaves like a real product rather than a checklist of isolated demos.

### Features
- Public marketing site with product pages and live-looking rate strip.
- Customer online banking: dashboard (balance-history and spending charts), transaction
  history with search and filters, send money, saved payees, debit/credit cards (freeze,
  limits), add money by card, e-statements, document library, support messaging, profile
  and security.
- Staff **Operations console**: network overview, customer administration, service desk,
  and a gateway-health diagnostics tool.
- Seeded with 9 users, 12 accounts and ~380 realistic transactions (salary, rent, merchants,
  interest, peer transfers), plus sample bank PDFs and KYC documents.

### Architecture
```
                 ┌─────────────┐        ┌──────────────┐
  browser ─────► │   web (PHP   │ ─────► │  db (MySQL)  │
   :8080/:30080  │  + Apache)   │        └──────────────┘
                 │              │ ─────► ┌──────────────────────┐
                 └─────────────┘        │ core-ledger (internal │
                        ▲               │ PHP service :8081)    │
                        │               └──────────────────────┘
                 backend network is isolated from the internet
```
The `core-ledger` service is reachable only from the application network — it is the
intended target of the SSRF chain.

### Technology stack
| Layer     | Choice                                            |
|-----------|---------------------------------------------------|
| Backend   | PHP 8.2 (PDO, no framework)                        |
| Database  | MySQL 8.0 (MariaDB works for local runs)          |
| Frontend  | Bootstrap 4.1.1, jQuery 3.3.1, Popper 1.14.3, Chart.js 4, Font Awesome 5 (served from CDN) |
| Web server| Apache 2.4 + mod_php                              |
| Runtime   | Docker Compose · Kubernetes · bare-metal VM       |

---

## 2. Installation guide

### Prerequisites
- **Docker** route: Docker Engine 24+ and Docker Compose v2.
- **VM** route: Ubuntu 22.04+/Debian 12 with sudo.
- **Kubernetes** route: Minikube, kind, k3s or any cluster, plus `kubectl`.

### Option A — Docker Compose (recommended)
```bash
cd polyvuln-bank
docker compose up -d --build
# wait ~20s for the database to initialise and seed
```
Open **http://localhost:8080/**. The compose file binds the web port to `127.0.0.1`
only, and puts the database and ledger on an `internal` network with no route to the
internet.

Reset to a clean state at any time:
```bash
docker compose down -v && docker compose up -d --build
```

### Option B — Bare-metal / VM
```bash
cd polyvuln-bank
./scripts/install_ubuntu.sh      # installs Apache, PHP, MariaDB; loads schema + seed
# then, in a second terminal, start the internal ledger service:
php -S 127.0.0.1:8081 core-ledger/router.php
```
Open **http://localhost:8080/**. Reload seed data with `./scripts/reset_db.sh`.

### Option C — Kubernetes
```bash
# build and load the web image into your cluster first, e.g. for minikube:
eval $(minikube docker-env) && docker build -t polyvuln/bank-web:4.12.3 .
kubectl apply -f k8s/namespace.yaml
kubectl apply -f k8s/secret.yaml -f k8s/configmap.yaml
kubectl apply -f k8s/db.yaml -f k8s/ledger.yaml -f k8s/web.yaml
# load schema + seed into the db pod:
kubectl -n polyvuln-bank exec -i deploy/pvb-db -- \
  sh -c 'mysql -uroot -p"$MYSQL_ROOT_PASSWORD"' < db/init/01_schema.sql
kubectl -n polyvuln-bank exec -i deploy/pvb-db -- \
  sh -c 'mysql -uroot -p"$MYSQL_ROOT_PASSWORD"' < db/init/02_seed.sql
minikube service pvb-web -n polyvuln-bank --url      # or http://<node>:30080
```

### Database setup, seed data & default accounts
The schema (`db/init/01_schema.sql`) and seed (`db/init/02_seed.sql`) load automatically
on first container start. The application connects as the least-privileged `pvb_app`
user (DML only: SELECT/INSERT/UPDATE/DELETE — no DDL, no FILE).

**Sample customer logins** (all customers listed in the operations console):

| Username   | Password           | Notes                                          |
|------------|--------------------|------------------------------------------------|
| `dcarter`  | `Carter@1987`      | 2 accounts (checking + savings), cards         |
| `praman`   | `Priya#2024`       | High balance, house-deposit savings            |
| `salvarez` | `password123`      | Weak password on purpose                       |
| `hlee`     | `Welcome1`         | Weak password on purpose                       |
| `qa.tester`| `Qa!Regress#2026`  | Extra low-balance account for testing           |

There is **one staff account** (`ops.admin`). Its password is a random secret generated
at seed time and **never written to source, seed, config or docs** — obtaining staff
access is itself one of the exercises (see VULN-16). Reaching the operations console is
meant to be done through the application, not with a known password.

---

## 3. Deployment instructions summary

| Mode       | Start command                                   | URL                         |
|------------|-------------------------------------------------|-----------------------------|
| Docker     | `docker compose up -d --build`                  | http://localhost:8080/      |
| VM         | `./scripts/install_ubuntu.sh`                   | http://localhost:8080/      |
| Kubernetes | `kubectl apply -f k8s/` (+ image build & seed)  | http://\<node\>:30080/      |

---

## 4. Vulnerability matrix

25 vulnerabilities. OWASP categories follow the 2025 draft list. **A09 (Security Logging &
Alerting Failures) is intentionally excluded** per the lab brief.

Legend for difficulty: **B**eginner · **I**ntermediate · **A**dvanced.

---

### A01:2025 — Broken Access Control

**VULN-01 · CWE-89 · Blind SQL injection (authenticated transaction search)** — *A*
- **File:** `src/transactions.php`
- **Endpoint:** `GET /transactions.php?q=<term>` (login required)
- **Attack surface:** The `q` search term is concatenated directly into the `WHERE` clause
  (`t.counterparty_name LIKE '%<q>%'`) of the count, sum and list queries. Every other filter
  on the page (account, direction, dates) is parameterised — only `q` is injectable.
- **Description:** The transaction search box is used by **every** authenticated customer on a
  page they all have (Transactions). The page never reflects row data from the injected query
  back to the attacker — it only shows a result **count** ("N transactions" vs "No transactions
  match") — so this is a **blind** (boolean- and time-based) injection. Because the term sits
  inside a `LIKE '%...%'`, the trailing `%'` must be commented out with `-- -`. It is **not**
  present on the login form.
- **Educational objective:** Practise blind SQLi discovery and data exfiltration where no row
  data is reflected; use the result-count and response-time as boolean/time oracles; handle a
  `LIKE`-wrapped sink.
- **Verification:**
  1. Log in as any customer (e.g. `dcarter` / `Carter@1987`) and open **Transactions**.
  2. Baseline: search `Netflix` → a non-zero count.
  3. Boolean oracle (count flips between "N transactions" and "No transactions match"):
     - TRUE: `?q=zzz%' OR (SELECT SUBSTRING(username,1,1) FROM users WHERE id=1)='o'-- -` → all rows.
     - FALSE: `?q=zzz%' OR (SELECT SUBSTRING(username,1,1) FROM users WHERE id=1)='x'-- -` → 0 rows.
  4. Time oracle: `?q=zzz%' OR IF((SELECT COUNT(*) FROM users)>5,SLEEP(3),0)-- -` delays the
     response; the `>999` variant returns instantly.
  5. Point `sqlmap` at it with an authenticated cookie:
     `sqlmap -u "http://localhost:8080/transactions.php?q=Netflix" --cookie="PVSESSID=<sid>" -p q --technique=B,T --dbms=mysql`.
- **Impact:** Full read of the `users`/`accounts` tables, including the admin row and password
  hashes, one boolean at a time (feeds VULN-13).

**VULN-02 · CWE-639 (IDOR) · Insecure Direct Object Reference on receipts** — *B*
- **File:** `src/receipt.php`
- **Endpoint:** `GET /receipt.php?id=<txn_id>`
- **Attack surface:** `id` is used to fetch a transaction with **no ownership check**.
- **Description:** Any logged-in customer can read any other customer's transaction receipt —
  counterparty names, account numbers, memos and balances — by incrementing `id`.
- **Objective:** Recognise missing object-level authorization.
- **Verification:** As any customer (e.g. `dcarter`), browse `/receipt.php?id=1`, `?id=2`, …
  to read other customers' transactions.
- **Impact:** Cross-customer financial data disclosure.

**VULN-03 · CWE-352 · CSRF on money movement and profile changes** — *I*
- **File:** `src/transfer.php`, `src/profile.php`
- **Endpoint:** `GET|POST /transfer.php`, `GET|POST /profile.php?action=contact`
- **Attack surface:** State-changing actions accept `$_REQUEST` (so GET works) and carry **no
  CSRF token**.
- **Description:** A transfer can be triggered by a crafted link / auto-submitting page, e.g.
  `/transfer.php?from_account=12&to_account=<attacker>&amount=500&currency=USD`. The same
  applies to changing the victim's email/phone (which feeds password recovery — see VULN-19).
- **Objective:** Demonstrate CSRF against funds transfer and account-takeover prep.
- **Verification:** While logged in, load an `<img src="/transfer.php?...">` or a link; the
  transfer posts with the victim's session.
- **Impact:** Unauthorized transfers; contact-detail hijack enabling takeover.

**VULN-04 · CWE-918 · SSRF via "profile photo from URL"** — *A*
- **File:** `src/profile.php` (`action=avatar_url`)
- **Endpoint:** `POST /profile.php` with `avatar_url=<url>`
- **Attack surface:** Server-side `curl` fetches an arbitrary user-supplied URL; the response
  body is echoed back in the error panel when it is not an image.
- **Description:** "Use a photo from a link" fetches internal resources. Pointing it at the
  internal ledger returns its configuration, including service tokens and the SWIFT gateway
  password, straight into the page.
- **Objective:** Exploit SSRF to reach an internal-only service and read secrets; explore
  cloud-metadata endpoints in cloud deployments.
- **Verification:** Profile → **Use a photo from a link** →
  `http://core-ledger:8081/api/v1/config` (Docker/K8s) or `http://127.0.0.1:8081/api/v1/config`
  (VM). The JSON (with `ledger_service_token`, `swift_gateway_password`, `polypay_signing_key`)
  renders in the "Response received" box.
- **Impact:** Internal service compromise; credential theft; pivot.

**VULN-05 · CWE-548 · Directory listing / exposure of KYC files** — *B*
- **File:** Apache config `docker/apache/000-default.conf` (`Options Indexes`), `src/uploads/kyc/`
- **Endpoint:** `GET /uploads/kyc/`
- **Attack surface:** Autoindex is on and the KYC folder is web-reachable with predictable names.
- **Description:** Browsing `/uploads/kyc/` lists sample driver's licences, proof-of-address
  scans and `kyc_review_queue.csv` (names + SSN last-4 + review status).
- **Objective:** Find sensitive files through directory listing and predictable paths.
- **Verification (Apache only):** Open `http://localhost:8080/uploads/kyc/`. Even without
  listing, files are directly fetchable, e.g. `/uploads/kyc/kyc_review_queue.csv`.
- **Impact:** Bulk PII / identity-document disclosure.

**VULN-06 · CWE-615 · Sensitive information in source-code comments** — *B*
- **File:** `src/login.php` (HTML comment near the end of the sign-in page)
- **Endpoint:** `GET /login.php` → *View source*
- **Attack surface:** A developer "smoke test" comment left in shipped HTML.
- **Description:** The comment leaks a regression test account (`qa.tester` / its password)
  and the internal ledger host plus its `lgr_...` service token.
- **Objective:** Always read client-side source; treat comments as leakage.
- **Verification:** `curl -s http://localhost:8080/login.php | grep -A3 "smoke tests"`.
- **Impact:** Working low-priv credentials and internal infrastructure hints (feeds VULN-01/04).

---

### A02:2025 — Security Misconfiguration

**VULN-07 · CWE-16 · `phpinfo()` and verbose server banners exposed** — *B*
- **File:** `src/phpinfo.php`; `docker/apache/security.conf` (`ServerTokens Full`, `TraceEnable On`)
- **Endpoint:** `GET /phpinfo.php`
- **Attack surface:** A diagnostic script and full server signatures shipped to production.
- **Description:** `phpinfo.php` reveals PHP version, loaded modules, paths, and environment;
  Apache advertises full version and allows `TRACE`.
- **Objective:** Fingerprinting and misconfiguration discovery.
- **Verification:** Open `/phpinfo.php`; `curl -I /` shows a verbose `Server:` header.
- **Impact:** Recon that sharpens other attacks.

**VULN-08 · CWE-526 · Exposure of secrets via environment variables** — *I*
- **File:** `phpinfo.php` output + `core-ledger/router.php` (`/api/v1/config` echoes `getenv`)
- **Endpoint:** `GET /phpinfo.php` (Environment section), internal `/api/v1/config`
- **Attack surface:** App/service secrets are passed as env vars and surfaced by diagnostics.
- **Description:** `GATEWAY_SIGNING_KEY`, DB credentials and ledger tokens appear in the
  `phpinfo` Environment table and in the ledger config endpoint reachable via SSRF.
- **Objective:** Understand how env-var secrets leak through diagnostics and SSRF.
- **Verification:** Scan the `phpinfo.php` *Environment* / *PHP Variables* sections; combine
  with VULN-04 to read the ledger config.
- **Impact:** Disclosure of signing keys and DB credentials.

**VULN-09 · CWE-1004 · Session/profile cookie without HttpOnly (or Secure)** — *I*
- **File:** `src/includes/config.php` (cookie ini), `src/includes/auth.php` (`pv_auth` cookie)
- **Endpoint:** any authenticated page (`Set-Cookie`)
- **Attack surface:** `PVSESSID` and the `pv_auth` profile cookie are set without `HttpOnly`,
  `Secure` or `SameSite`.
- **Description:** Cookies are readable from JavaScript, so any XSS (VULN-12/13) can steal the
  session and the `pv_auth` token.
- **Objective:** Connect cookie flags to XSS-driven session theft.
- **Verification:** `curl -i` the login response and inspect `Set-Cookie`; run `document.cookie`
  in the console.
- **Impact:** Session hijacking; amplifies XSS.

---

### A03:2025 — Software Supply Chain Failures

**VULN-10 · CWE-1035 · Using components with known vulnerabilities** — *B*
- **File:** `src/includes/assets_head.php`, `src/includes/assets_foot.php`
- **Endpoint:** every page (pinned CDN includes)
- **Attack surface:** jQuery **3.3.1** and Popper **1.14.3** are known-vulnerable releases.
- **Description:** These exact pinned versions carry published advisories (e.g. jQuery
  prototype-pollution / selector issues). A dependency scan flags them immediately.
- **Objective:** Practise SCA; map a front-end dependency to a CVE.
- **Verification:** `grep -r "jquery-3.3.1\|popper.js@1.14.3" src/includes/`; run `retire.js`
  against the running site.
- **Impact:** Client-side exploitation primitives; audit finding.

**VULN-11 · CWE-1395 · Dependency on vulnerable third-party component (Bootstrap 4.1.1)** — *B*
- **File:** `src/includes/assets_head.php` / `assets_foot.php`
- **Endpoint:** every page
- **Attack surface:** Bootstrap **4.1.1** predates fixes for data-attribute XSS sinks
  (tooltip/popover `data-template`).
- **Description:** Combined with stored user data rendered into tooltip/popover-capable markup,
  the outdated framework widens the XSS surface.
- **Objective:** Recognise transitive/framework risk beyond "my own code".
- **Verification:** Version string in page source; SCA report.
- **Impact:** Additional XSS avenues; audit finding.

---

### A04:2025 — Cryptographic Failures

**VULN-12 (crypto) · CWE-319 · Cleartext transmission + over-permissive CORS** — *I*
- **File:** `docker-compose.yml` / Apache vhost (HTTP only); `src/api/me.php` (CORS)
- **Endpoint:** entire site over `http://`; `GET /api/me.php`
- **Attack surface:** No TLS; `/api/me.php` reflects the request `Origin` and sets
  `Access-Control-Allow-Credentials: true`.
- **Description:** Credentials and session cookies traverse plaintext HTTP, and the profile API
  can be read cross-origin by **any** site because the origin is reflected with credentials.
- **Objective:** Show why `ACAO: <reflected>` + credentials ≈ no same-origin protection, and
  the risk of cleartext.
- **Verification:**
  `curl -s -i -H "Origin: https://evil.example" -b PVSESSID=<sid> http://localhost:8080/api/me.php | grep -i access-control`
  → the evil origin is allowed with credentials.
- **Impact:** Sniffable sessions; cross-origin theft of profile + account data.

**VULN-13 (crypto) · CWE-328 · Reversible / weak password hashing (unsalted MD5)** — *I*
- **File:** `src/includes/auth.php`, `src/login.php`, `src/register.php`, `src/profile.php`
  (all use `md5()`); `db/init/02_seed.sql`
- **Endpoint:** authentication and password-change flows; DB `users.password`
- **Attack surface:** Passwords stored as **unsalted MD5**.
- **Description:** Once the `users` table is read (via VULN-01), customer hashes fall quickly to
  rainbow tables / wordlists. (The admin hash resists this because its preimage is a long random
  token, reinforcing *why* length matters.)
- **Objective:** Contrast fast unsalted hashes with modern KDFs; crack weak customer hashes.
- **Verification:** `SELECT username,password FROM users`; crack with `hashcat -m 0`. E.g.
  `md5("Carter@1987") = ecc5c913a301c5d2eafd91360af16af7`.
- **Impact:** Mass credential recovery after any DB read.

---

### A05:2025 — Injection

**VULN-14 · CWE-77 · OS command injection (allow-listed utilities)** — *A*
- **File:** `src/admin/diagnostics.php`
- **Endpoint:** `POST /admin/diagnostics.php` (staff only — reach it via VULN-16)
- **Attack surface:** The `target` host is spliced into a shell `ping` command. A partial
  allow-list permits chaining **only** `id`, `uname`, `pwd`, `whoami`.
- **Description:** Operators can append a "supported node-inspection utility" after the probe.
  Separators (`;`, `|`, `&&`) are honoured, but the second command must be one of the four
  allow-listed binaries with only `-flag` arguments; metacharacters like `` ` ``, `$()`, quotes
  and other binaries (`cat`, `ls`, …) are rejected.
- **Objective:** Exploit command injection against a realistic (imperfect) allow-list; reason
  about why allow-listing commands but not fully sanitising is still dangerous.
- **Verification:** After gaining staff access, in **Gateway health** submit
  `127.0.0.1; id`, `127.0.0.1 | whoami`, `127.0.0.1 && uname -a`, `127.0.0.1;pwd`. Each runs and
  returns output; `127.0.0.1;cat /etc/passwd` and `` 127.0.0.1;`id` `` are blocked.
- **Impact:** Command execution as the web user; host reconnaissance.

**VULN-15 · CWE-80 · Reflected XSS (filter-bypassable)** — *I*
- **File:** `src/transactions.php` (search results heading), filter in
  `src/includes/functions.php::pv_clean()`
- **Endpoint:** `GET /transactions.php?q=<payload>`
- **Attack surface:** The `q` term is echoed into the results heading after a filter that strips
  only `<script>` tags and the word `alert`.
- **Description:** Because the filter blocks just `<script>` and `alert`, event-handler and
  non-script vectors execute, e.g. `<img src=x onerror=confirm(document.cookie)>` or
  `<svg onload=prompt(1)>`.
- **Objective:** Defeat a naive blacklist; use `on*` handlers and `confirm`/`prompt`.
- **Verification:** `/transactions.php?q=<img src=x onerror=confirm(document.cookie)>` fires in
  the victim's browser; `<script>`/`alert` variants are stripped.
- **Impact:** Session theft (with VULN-09), request forgery in the victim's context.

**VULN-16 · CWE-80 · Stored XSS in transfer memo** — *A*
- **File:** write: `src/transfer.php` (`remarks` via `pv_clean`); render: `src/dashboard.php`,
  `src/transactions.php`, `src/receipt.php`
- **Endpoint:** `POST /transfer.php` (memo) → rendered on the **recipient's** pages
- **Attack surface:** The memo passes through the same weak `pv_clean` filter and is later echoed
  without encoding on the counterparty's dashboard, list and receipt.
- **Description:** An attacker sends a tiny transfer to a victim with a memo payload; it executes
  when the victim views their activity. Chainable to account takeover via VULN-09 → VULN-03.
- **Objective:** Stored XSS across a trust boundary (attacker-controlled input rendered to a
  different user); build an attack chain.
- **Verification:** As any customer (the attacker), send `$1` to `dcarter` with memo
  `<img src=x onerror=confirm(document.cookie)>`; log in as `dcarter` and open the dashboard —
  the payload fires.
- **Impact:** Takeover of any customer who receives a payment; worm-like spread.

---

### A06:2025 — Insecure Design

**VULN-17 · CWE-266 · Privilege escalation via mass assignment on registration** — *A*
- **File:** `src/register.php`
- **Endpoint:** `POST /register.php`
- **Attack surface:** Registration copies a fixed field list, but `role` is included in that list
  and defaults to `customer` only when absent — a submitted `role=admin` is honoured. The field is
  **not** shown in the form, so it must be discovered by guessing/parameter fuzzing.
- **Description:** Adding `role=admin` to the signup POST creates a staff account directly. This is
  the "guess an undisplayed parameter" exercise.
- **Objective:** Discover hidden parameters; understand mass-assignment / over-posting.
- **Verification:**
  `curl -d "product=Checking&full_name=Eve&email=eve@lab.test&username=evelab&password=Password1&password_confirm=Password1&terms=1&role=admin" http://localhost:8080/register.php`
  then sign in as `evelab` → operations console. (Appears in **Operations → Customers** with role *Admin*.)
- **Impact:** Self-service administrator access.

**VULN-18 · CWE-1021 · Clickjacking (no framebusting / frame-ancestors)** — *B*
- **File:** app-wide (no `X-Frame-Options` / CSP `frame-ancestors` anywhere)
- **Endpoint:** all pages, notably `/transfer.php`, `/cards.php`
- **Attack surface:** Pages can be framed by any origin.
- **Description:** A malicious site can frame transfer/card actions transparently and trick a
  logged-in user into clicking them (UI redress), complementing the CSRF in VULN-03.
- **Objective:** Demonstrate clickjacking and proper frame-control headers.
- **Verification:** Load a page in `<iframe src="http://localhost:8080/transfer.php">` from
  another origin — it renders; no anti-framing header is present.
- **Impact:** Tricked privileged actions.

---

### A07:2025 — Authentication Failures

**VULN-19 · CWE-287 · Authentication bypass & privilege escalation via forgeable cookie** — *A*
- **File:** `src/includes/auth.php` (the `pv_auth` "session continuity" block)
- **Endpoint:** any page (cookie is trusted on every request)
- **Attack surface:** `pv_auth` is a **base64-encoded JSON** profile (`uid`, `role`) with no
  signature or integrity check; the server rebuilds the session from it, trusting the `role`.
- **Description:** Forging `pv_auth = base64({"uid":1,"user":"ops.admin","role":"admin"})` yields a
  full admin session **without any password** — which is how staff access is intended to be obtained
  (the real admin password is never disclosed). Any customer can also set `role":"admin"` for their
  own uid; the console authorizes on the forged role.
- **Objective:** Spot client-side trust of an unsigned token; achieve authN bypass + privilege
  escalation.
- **Verification:**
  `curl -b "pv_auth=$(printf '{"uid":1,"user":"ops.admin","role":"admin","ts":1}' | base64 -w0)" http://localhost:8080/admin/`
  returns the operations console (HTTP 200).
- **Impact:** Complete administrative takeover.

**VULN-20 · CWE-308 · Single-factor authentication only** — *B*
- **File:** `src/login.php`, `src/profile.php` (security section)
- **Endpoint:** `POST /login.php`
- **Attack surface:** Password is the sole factor; no MFA/step-up for sign-in or for money
  movement.
- **Description:** Any credential compromise (phishing, VULN-13, VULN-06) grants full access and
  the ability to move funds with no second factor.
- **Objective:** Discuss MFA and transaction step-up as compensating controls.
- **Verification:** Sign in and transfer funds with username+password alone; the profile security
  page shows "Sign-in method: Username and password" with no MFA option.
- **Impact:** Low bar from credential theft to funds movement.

**VULN-21 · CWE-384 · Session fixation (session id accepted from the URL)** — *A*
- **File:** `src/includes/config.php` (`session.use_only_cookies=0`, `use_strict_mode=0`),
  `src/includes/auth.php` (session not regenerated on login)
- **Endpoint:** `GET /login.php?PVSESSID=<fixed>` then `POST /login.php`
- **Attack surface:** PHP accepts the session id from the query string and the id is **not
  regenerated** at login.
- **Description:** An attacker fixes a victim's session id via a crafted link; after the victim
  authenticates, the attacker's known id is now an authenticated session.
- **Objective:** Exploit fixation; learn `session_regenerate_id()` and cookie-only sessions.
- **Verification:** Seed `?PVSESSID=attackerfixed123`, have the victim log in on that link, then
  reuse the same id: `curl -b "PVSESSID=attackerfixed123" /api/me.php` returns the victim.
- **Impact:** Session hijacking without stealing a cookie.

**VULN-22 · CWE-640 · Weak password-recovery mechanism** — *I*
- **File:** `src/forgot.php`
- **Endpoint:** `POST /forgot.php`
- **Attack surface:** Recovery relies on a **4-digit** code (10,000 possibilities), not rate
  limited, with the target username supplied by the attacker and no session binding.
- **Description:** The reset code is brute-forceable; combined with the CSRF-changeable phone/email
  (VULN-03), recovery becomes a takeover path.
- **Objective:** Evaluate recovery design: entropy, rate limiting, binding, enumeration.
- **Verification:** Request a reset for a victim username, then brute-force `0000`–`9999` against
  the verify step until a new password is accepted.
- **Impact:** Account takeover via recovery.

---

### A08:2025 — Software or Data Integrity Failures

**VULN-23 · CWE-345 · Unverified payment-callback (data integrity)** — *A*
- **File:** `src/api/topup_callback.php`
- **Endpoint:** `POST /api/topup_callback.php`
- **Attack surface:** The acquirer "notification" credits an account from client-controlled JSON;
  the HMAC signature is verified **only if present** (`if (isset($payload['signature']))`), and the
  amount is taken from the callback, not the original intent.
- **Description:** Replaying/forging a callback **without** a `signature` field, with
  `status:"captured"` and an inflated `amount`, credits real balance — an integrity failure in a
  trust-boundary message.
- **Objective:** Show why optional/again-trusted signatures break integrity; verify server-side
  amount against the stored intent.
- **Verification:** Create a top-up intent, then
  `curl -H 'Content-Type: application/json' -d '{"intent":"<ref>","account":"<num>","amount":"20000","status":"captured"}' /api/topup_callback.php`
  → `{"result":"credited"}` and the balance jumps. (A *wrong* signature is correctly rejected — only
  omitting it bypasses the check.)
- **Impact:** Arbitrary balance inflation / financial fraud.

---

### A10:2025 — Mishandling of Exceptional Conditions

**VULN-24 · CWE-755 · Improper handling of exceptional conditions ("fail-open" transfer)** — *A*
- **File:** `src/transfer.php` (the `try/catch` around `fx_rate()`), `src/includes/fx.php`
- **Endpoint:** `POST /transfer.php` with an unsupported `currency`
- **Attack surface:** `fx_rate()` throws for unknown currencies; the `catch` block **proceeds with
  the transfer anyway** ("settle in the end-of-day batch") and skips the balance check, while leaking
  a verbose exception message (class, file, line).
- **Description:** Sending with `currency=XYZ` forces the exception path: the debit posts even when it
  exceeds the available balance, and the response discloses internal paths.
- **Objective:** Analyse fail-open error handling and information leakage via exceptions.
- **Verification:** Transfer `amount=99999`, `currency=XYZ` from a low-balance account → "Money sent",
  balances change, and a "Settlement deferred (… in /…/fx.php:9)" notice is shown.
- **Impact:** Overdraft/negative-balance abuse; internal path disclosure.

**VULN-25 · CWE-209/755 · Verbose error disclosure via malformed input** — *I*
- **File:** `src/transactions.php` (injected `q` sink) and the exception text surfaced by VULN-24
- **Endpoint:** `GET /transactions.php?q=` with broken SQL meta-characters; `/transfer.php`
  exception notice
- **Attack surface:** Exceptional inputs are caught in ways that still reveal internal behaviour and
  file paths rather than failing cleanly.
- **Description:** Malformed injection probes and the FX exception path expose server internals
  (absolute paths, class names), confirming injectable sinks and aiding exploitation.
- **Objective:** Use error-condition handling as an oracle; argue for uniform, opaque error responses.
- **Verification:** Compare the page's behaviour on `?q=zzz%' AND 1=1-- -` vs a malformed
  `?q=zzz%' AND (1-- -` (which errors to a 500) to confirm the injectable sink; read the
  file/line in the transfer "Settlement deferred" notice.
- **Impact:** Information leakage that accelerates VULN-01 and VULN-24.

---

### Coverage summary

| OWASP 2025 category | VULN IDs |
|---|---|
| A01 Broken Access Control | 01, 02, 03, 04, 05, 06 |
| A02 Security Misconfiguration | 07, 08, 09 |
| A03 Software Supply Chain Failures | 10, 11 |
| A04 Cryptographic Failures | 12, 13 |
| A05 Injection | 14, 15, 16 |
| A06 Insecure Design | 17, 18 |
| A07 Authentication Failures | 19, 20, 21, 22 |
| A08 Software/Data Integrity Failures | 23 |
| A09 Security Logging & Alerting Failures | *excluded by design* |
| A10 Mishandling of Exceptional Conditions | 24, 25 |

---

## 5. Lab exercises

### Suggested student path (easy → hard)
1. **Recon (B):** read `/login.php` source (VULN-06), hit `/phpinfo.php` (VULN-07/08), list
   `/uploads/kyc/` (VULN-05), run an SCA/`retire.js` scan (VULN-10/11).
2. **Access control (B→I):** walk `receipt.php?id=` (VULN-02); test cookie flags and CORS
   (VULN-09/12).
3. **Injection (I→A):** reflected then stored XSS past the blacklist (VULN-15/16); blind SQLi from
   the Transactions search box (VULN-01) and dump + crack customer hashes (VULN-13).
4. **Authentication (A):** forge `pv_auth` to reach staff (VULN-19); then OS command injection in
   Gateway Health (VULN-14). Explore fixation (VULN-21) and 4-digit recovery (VULN-22).
5. **Business logic (A):** mass-assign `role=admin` at signup (VULN-17); break the top-up callback
   (VULN-23); force the fail-open transfer (VULN-24/25).
6. **Chaining (A):** SSRF → ledger secrets (VULN-04/08); stored-XSS → cookie theft (VULN-16+09) →
   CSRF transfer (VULN-03); CSRF email change (VULN-03) → recovery takeover (VULN-22).

### Learning objectives
- Perform object-level and function-level access-control testing.
- Exploit blind SQLi and stored/reflected XSS, including blacklist bypass.
- Reason about authentication token integrity, session lifecycle and MFA.
- Identify insecure design (mass assignment, unsigned callbacks, fail-open logic).
- Build multi-step chains that cross trust boundaries.

### Verification procedures
Each VULN above lists concrete reproduction steps. Re-seed between runs
(`docker compose down -v && up`, or `./scripts/reset_db.sh`) to restore balances and remove
test artifacts (e.g. the `evelab` admin, inflated balances).

### Recommended tooling
Burp Suite / OWASP ZAP (proxying, Repeater, Intruder), `sqlmap` (point at the
Transactions `q` parameter with an authenticated cookie), `hashcat`/`john` (`-m 0` MD5), `retire.js`/OWASP
Dependency-Check (SCA), `curl`/`httpie`, and the browser devtools console.

### Secure-coding remediation guidance
| Theme | Fix |
|---|---|
| SQL | Parameterised queries / prepared statements everywhere (the app already uses PDO elsewhere — the lookup endpoint is the deliberate exception). |
| XSS | Context-aware output encoding (`htmlspecialchars`) on **all** sinks; a CSP; never a keyword blacklist. |
| Access control | Server-side ownership checks on every object (`WHERE user_id = :me`); deny-by-default function gates. |
| AuthN/session | Signed, server-side sessions; `session_regenerate_id()` on login; cookie-only sessions; `HttpOnly`+`Secure`+`SameSite`; MFA and transaction step-up. |
| Tokens/integrity | Mandatory signature verification; validate amounts against stored intents; never trust client-supplied role/amount. |
| Crypto/transport | TLS everywhere; `argon2id`/`bcrypt` with per-user salts; strict, non-reflective CORS. |
| SSRF | Allow-list destinations, resolve+pin IPs, block link-local/internal ranges, disable redirects. |
| Config | Remove `phpinfo`, disable autoindex and `TRACE`, trim server banners, keep secrets out of env-exposed diagnostics, patch dependencies. |
| Errors | Catch exceptions without failing open; return uniform, opaque messages; log server-side only. |
| CSRF/clickjacking | Per-request CSRF tokens on state changes; `X-Frame-Options: DENY` / CSP `frame-ancestors 'none'`. |

---

## 6. Safety & legal

This software is deliberately insecure. Run it **only** in an isolated lab you control
(the provided Docker/K8s setups keep the datastore off the public internet and bind the
web port to loopback). Do not expose it to the internet, store real personal or financial
data in it, or use these techniques against systems you are not explicitly authorized to
test. All names, documents, cards and identities are fictitious and synthetic.

© PolyVuln 2026.
