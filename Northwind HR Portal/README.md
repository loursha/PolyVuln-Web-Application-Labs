# Northwind HR Portal — Intentionally Vulnerable Training Application

> **WARNING — FOR SECURITY TRAINING ONLY**
> This application contains deliberate, exploitable vulnerabilities. Never deploy it
> to production, never expose it to the internet, and only run it in an isolated lab
> (a local VM, an air-gapped network, or a disposable Docker host).

A realistic **HR management portal** built with **Python Flask + SQLite + Bootstrap 5**,
seeded with an OWASP Top 10 (2025) baseline of vulnerabilities for penetration-testing
practice, secure-coding education, and internal security-awareness programs.

**Difficulty level:** Intermediate

---

## 1. Application Overview

### Purpose
Provide an enterprise-looking HR system that testers can attack safely to learn
web application security, and that defenders can use to practice remediation.

### Features
- **Authentication** — login, logout, self-service password recovery
- **Dashboard** — headcount stats, department chart, company announcements
- **Employee Directory** — searchable org-wide directory with profile drill-down
- **Leave Management** — submit leave requests, view status
- **Document Center** — upload and download files
- **My Profile** — edit phone/bio with a live preview
- **HR Module** (`hr`/`admin`) — leave approvals, payroll overview, announcements
- **Admin Module** (`admin`) — user management (CRUD + role assignment), network
  diagnostics, and URL import/health-check tooling

### Architecture
```
Browser ── HTTP ──> Flask (app.py) ──> SQLite (hrportal.db)
                         │
                         ├── templates/  (Jinja2 + Bootstrap 5 UI)
                         ├── static/     (CSS, uploads)
                         └── logs/       (app.log — cleartext credential logging)
```
Three roles enforce a tiered access model: **employee → hr → admin**.

### Technology Stack
| Layer      | Choice                                   |
|------------|------------------------------------------|
| Backend    | Python 3, Flask 2.0.1 (pinned, old)      |
| Database   | SQLite 3                                  |
| Frontend   | Bootstrap 5.3, Bootstrap Icons, Inter    |
| Auth       | Server-side sessions (cookie)            |
| Deployment | Docker, Docker Compose, local VM script  |

---

## 2. Installation Guide

### Prerequisites
- Python 3.9+ **or** Docker + Docker Compose
- (For the ping diagnostics feature) the `ping` binary — bundled in the Docker image

### Option A — Docker (recommended)
```bash
docker compose up --build
# open http://localhost:5000
```

### Option B — Local VM / bare metal
```bash
chmod +x run.sh
./run.sh
# or manually:
python3 -m venv venv && source venv/bin/activate
pip install -r requirements.txt
python init_db.py        # build + seed the database
python app.py            # serve on http://0.0.0.0:5000
```

### Database Setup & Seed Data
`init_db.py` drops and recreates `hrportal.db` with seed users, announcements,
leave requests, and payroll records. It runs automatically on first start.

### Default Credentials
| Username     | Password    | Role     |
|--------------|-------------|----------|
| `admin`      | `Admin@123` | admin    |
| `hr.manager` | `Hr@2024`   | hr       |
| `hr.recruiter` | `Recruit1` | hr      |
| `j.doe`      | `Password1` | employee |
| `m.chan`     | `Welcome1`  | employee |
| `s.gupta`    | `Sunny123`  | employee |

---

## 3. Deployment Instructions

### Docker
```bash
docker compose up --build -d      # start detached
docker compose logs -f            # view logs
docker compose down               # stop
```

### Kubernetes (quick manifest)
Create `k8s.yaml` and apply. Minimal example (Minikube/Kind/k3s):
```yaml
apiVersion: apps/v1
kind: Deployment
metadata: { name: hr-portal }
spec:
  replicas: 1
  selector: { matchLabels: { app: hr-portal } }
  template:
    metadata: { labels: { app: hr-portal } }
    spec:
      containers:
        - name: hr-portal
          image: northwind-hr:latest    # docker build -t northwind-hr .
          ports: [ { containerPort: 5000 } ]
---
apiVersion: v1
kind: Service
metadata: { name: hr-portal }
spec:
  type: NodePort
  selector: { app: hr-portal }
  ports: [ { port: 5000, targetPort: 5000, nodePort: 30500 } ]
```
```bash
docker build -t northwind-hr .
kubectl apply -f k8s.yaml
minikube service hr-portal
```

### Local VM
Supported on Ubuntu, Debian, Rocky, CentOS, Windows (WSL). Use `run.sh` or the
manual steps above. Fully runnable offline once dependencies are cached.

---

## 4. Vulnerability Matrix

> Sinks are marked in the source with comments referencing their CWE.

| # | OWASP 2025 | CWE | Vulnerability | File | Endpoint | Attack Surface |
|---|-----------|-----|---------------|------|----------|----------------|
| 1 | A05 Injection | CWE-89 | SQL Injection | `app.py` `employees()` | `GET /employees?q=` | Post-login search box |
| 2 | A05 Injection | CWE-80 | Reflected XSS | `app.py` `leave()` / `leave.html` | `GET /leave?filter=` | Query parameter |
| 3 | A05 Injection | CWE-79 | Stored XSS | `profile()`, `leave()`, `hr_announce()` | `/profile`, `/leave`, `/hr/announce` | Bio, leave reason, announcement body |
| 4 | A05 Injection | CWE-77 | OS Command Injection | `admin_ping()` / `admin_tools.html` | `POST /admin/ping` | `host` field → shell |
| 5 | A01 Broken Access Control | CWE-22 | Path Traversal | `download()` | `GET /download?file=` | Filename param |
| 6 | A01 Broken Access Control | CWE-352 | CSRF | `hr_leave_action()`, `admin_delete_user()` | `GET /hr/leave/.../approve`, `/admin/user/<id>/delete` | State change via GET, no token |
| 7 | A01 Broken Access Control | CWE-548 | Directory Listing Exposure | `documents()` / `documents.html` | `GET /documents` | Storage listing rendered |
| 8 | A01 Broken Access Control | CWE-918 | SSRF | `admin_fetch()` | `POST /admin/fetch` | `url` field fetched server-side |
| 9 | A01 Broken Access Control | CWE-615 | Sensitive Info in Comments | `app.py`, templates | source | HTML/source comments with hints |
| 10 | A01 Broken Access Control | (IDOR) CWE-639 | Insecure Direct Object Reference | `employee_detail()` | `GET /employee/<id>` | Enumerable id exposes salary |
| 11 | A02 Security Misconfiguration | CWE-16 | Debug Mode / Config Weakness | `app.py` | app-wide | `debug=True`, Werkzeug console |
| 12 | A02 Security Misconfiguration | CWE-526 | Secrets in Env Vars | `.env`, `debug_env()` | `GET /debug/env` | Env dump incl. AWS/SMTP keys |
| 13 | A02 Security Misconfiguration | CWE-1004 | Cookie Without HttpOnly | `app.py` config | session cookie | JS-readable session |
| 14 | A03 Supply Chain | CWE-1035 / 1395 | Known-Vulnerable Components | `requirements.txt` | dependencies | Outdated pinned libs |
| 15 | A04 Cryptographic Failures | CWE-319 | Cleartext Transmission | app / docker | transport | Plain HTTP, no TLS |
| 16 | A04 Cryptographic Failures | CWE-328 | Reversible/Weak Hash | `md5()` in `app.py`/`init_db.py` | password storage | Unsalted MD5 |
| 17 | A06 Insecure Design | CWE-266 | Incorrect Privilege Assignment | `hr_required()`, `admin_set_role()` | `/hr`, `/admin/user/<id>/role` | Header backdoor `X-HR-Override`; any role settable |
| 18 | A06 Insecure Design | CWE-1021 | Missing Anti-Framing | app-wide | all pages | No `X-Frame-Options`/CSP → clickjacking |
| 19 | A07 Auth Failures | CWE-287 | Improper Authentication | `hr_required()` | `/hr` | Trusts client header for role |
| 20 | A07 Auth Failures | CWE-308 | Single-Factor Auth | `login()` | `/login` | Password only, no MFA |
| 21 | A07 Auth Failures | CWE-384 | Session Fixation | `login()` | `/login` | Session id not regenerated |
| 22 | A07 Auth Failures | CWE-640 | Weak Password Recovery | `forgot()` | `/forgot` | Cleartext answer, new pw echoed, question leaked |
| 23 | A08 Integrity Failures | (upload) CWE-434 | Unrestricted File Upload | `documents()` | `POST /documents` | No type/extension checks |
| 24 | A09 Logging Failures | CWE-532 | Sensitive Data in Logs | `login()` logging | `logs/app.log` | Credentials logged in cleartext |
| 25 | A10 Exceptional Conditions | CWE-755 | Improper Error Handling | `employees()`, `download()`, tools | multiple | Verbose errors leak SQL/stack |

### Per-Vulnerability Detail (selected)

**#1 SQL Injection (CWE-89)** — *Login is intentionally safe (parameterized).* The
injection lives post-authentication in the directory search, where `q` is concatenated
directly into the query.
- **Educational objective:** understand UNION-based extraction and why param binding matters.
- **Validation:** `GET /employees?q=x' UNION SELECT username,password,role,email,department,title,role,salary FROM users-- `
- **Expected impact:** dump all user records incl. MD5 password hashes.

**#2/#3 XSS (CWE-80 / CWE-79)** — *`<script>` is not the intended vector; use tag-based
event handlers.*
- **Reflected:** `GET /leave?filter=<img src=x onerror=alert(document.domain)>`
- **Stored (bio):** save `<svg/onload=alert(1)>` in Profile → fires on the detail/preview pages.
- **Impact:** session theft (cookie has no HttpOnly — see #13), UI redressing.

**#4 Command Injection (CWE-77)** — `POST /admin/ping` with `host=127.0.0.1; id`.
- **Impact:** arbitrary command execution as the app user.

**#5 Path Traversal (CWE-22)** — `GET /download?file=../../app.py` (or `../../../etc/passwd`).

**#8 SSRF (CWE-918)** — `POST /admin/fetch` with `url=http://169.254.169.254/latest/meta-data/`
or an internal host to reach otherwise-unreachable services.

**#17/#19 Privilege / Auth (CWE-266/287)** — send header `X-HR-Override: true` on any
request to `/hr` to reach the HR console as a plain employee.

**#22 Weak Recovery (CWE-640)** — `/forgot` reveals the security question on a wrong
answer and echoes the freshly-set password back to the browser.

---

## 5. Lab Exercises

### Suggested Student Exercises
1. **Recon & auth** — enumerate valid usernames via `/forgot` question disclosure.
2. **SQLi** — extract every password hash from `/employees`, then crack the MD5s offline.
3. **XSS chain** — store `<svg onload=...>` in your bio that exfiltrates
   `document.cookie` (works because the session cookie lacks HttpOnly).
4. **Access control** — as `j.doe`, reach the HR console using the header backdoor,
   then approve your own leave request.
5. **Privilege escalation** — combine SQLi creds + admin role assignment to gain admin,
   then achieve RCE via the ping tool.
6. **SSRF** — pivot from `/admin/fetch` to an internal-only endpoint.
7. **File attacks** — upload a `.py`/`.html` file, then read source with path traversal.
8. **Info disclosure** — pull secrets from `/debug/env` and `logs/app.log`.

### Learning Objectives
- Recognize where input crosses trust boundaries and reaches dangerous sinks.
- Practice UNION-based SQLi, DOM/event-handler XSS, SSRF, and command injection.
- Understand broken access control patterns (IDOR, client-trusted authz, CSRF).
- Appreciate the impact of weak crypto, cleartext logging, and misconfiguration.

### Verification Procedures
Each finding in the matrix includes a reproducible request. Confirm the *before* state
(vulnerable), apply the remediation below, and confirm the *after* state (blocked).

### Recommended Tooling
Burp Suite / OWASP ZAP, `sqlmap`, `curl`, browser devtools, `hashcat`/`john`,
a local netcat listener for SSRF/XSS callbacks, `nikto` for surface mapping.

### Secure-Coding Remediation Guidance
| Vuln | Fix |
|------|-----|
| SQLi | Use parameterized queries / ORM everywhere, incl. search |
| XSS | Context-aware output encoding; drop `\|safe`; add CSP |
| Command Injection | Avoid shell; use `subprocess.run([...], shell=False)` with allowlists |
| Path Traversal | `os.path.realpath` + confirm within upload dir; use `secure_filename` |
| CSRF | Anti-CSRF tokens; make state changes POST-only |
| SSRF | Allowlist destinations; block internal/link-local ranges |
| IDOR | Enforce per-object authorization checks |
| Weak Hash | Use `bcrypt`/`argon2` with per-user salt |
| Auth/Privilege | Derive role server-side only; never trust client headers |
| Session Fixation | Regenerate session id on login |
| Cookies | `HttpOnly`, `Secure`, `SameSite=Lax/Strict` |
| Upload | Validate type/extension/size; store outside webroot; random names |
| Logging | Never log secrets; redact credentials |
| Config | `debug=False` in prod; serve over TLS; keep deps patched |

---

## 6. Project Structure
```
hr-app/
├── app.py                 # Flask app (all routes + vuln sinks)
├── init_db.py             # schema + seed data
├── requirements.txt       # pinned (old) dependencies
├── .env                   # exposed secrets (training)
├── Dockerfile
├── docker-compose.yml
├── run.sh                 # local VM startup
├── _selftest.py           # smoke/vuln self-test (12 checks)
├── templates/             # Jinja2 + Bootstrap 5
│   ├── base.html  login.html  forgot.html  dashboard.html
│   ├── employees.html  employee_detail.html  leave.html
│   ├── profile.html  hr.html  admin.html  admin_tools.html  documents.html
├── static/css/style.css   # enterprise UI theme
├── static/uploads/        # uploaded documents
└── logs/                  # app.log (cleartext credential logging)
```

---
*Built for authorized security training and secure-coding education. Use responsibly.*
