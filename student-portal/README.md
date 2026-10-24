# EduCore — Vulnerable Student Management Portal

> ⚠️ **INTENTIONALLY VULNERABLE.** For security training, pentest labs, and secure-coding
> education only. **Never deploy on a public network or with real data.** Run in an
> isolated VM/container.

A neat, Bootstrap-based Student Management Portal (Flask + SQLite) seeded with the
OWASP Top 10 (2025) baseline of intentional vulnerabilities.

---

## Application Overview

- **Purpose:** Hands-on lab for teaching web-app vulnerability discovery and exploitation.
- **Features:** Student enrollment/self-registration, student directory + search, profiles,
  file upload/download, admin user management, dashboard with stats.
- **Architecture:** Monolithic Flask app, server-rendered Jinja2 templates, SQLite storage.
- **Tech stack:** Python 3 / Flask, SQLite, Bootstrap 5, vanilla JS.

### Lab-specific design choices (per request)
- **Login page is NOT SQL-injectable** — it uses parameterized queries. SQL injection
  lives on the **student search** (`/students?q=`) instead.
- **XSS filter blocks `<script>` tags and the literal `alert`** — but is trivially
  bypassable (`<img onerror>`, `<svg onload>`, `confirm()`, etc.). This teaches
  filter-evasion rather than "no filter at all."
- **Students self-enroll** via `/register`.

---

## Installation Guide

### Prerequisites
- Python 3.10+ (or Docker)

### Local run
```bash
pip install -r requirements.txt
python3 init_db.py      # creates & seeds school.db
python3 app.py          # http://127.0.0.1:5000
```
or simply: `./run.sh`

### Docker
```bash
docker compose up --build
# http://127.0.0.1:5000
```

### Default accounts
| Username | Password      | Role    |
|----------|---------------|---------|
| admin    | Admin@123     | admin   |
| teacher  | Teacher@123   | teacher |
| student  | Student@123   | student |

---

## Self-Test

Verify every vulnerability is live:
```bash
python3 app.py          # in one terminal
python3 selftest.py     # in another  -> expects 13/13 passed
```

---

## Vulnerability Matrix

| # | OWASP (2025) | CWE | Vulnerability | File | Endpoint | Attack Surface |
|---|--------------|-----|---------------|------|----------|----------------|
| 1 | A05 Injection | CWE-89 | SQL Injection | `app.py` `students()` | `GET /students?q=` | Search box (string-concatenated SQL) |
| 2 | A05 Injection | CWE-79 | Stored XSS | `app.py` `student_add()` / `student_view.html` | `POST /student/add` → `/student/<id>` | Student bio, `|safe` render |
| 3 | A05 Injection | CWE-79 | Reflected XSS | `app.py` `search()` / `search.html` | `GET /search?term=` | Search term echoed via `|safe` |
| 4 | A05 Injection | CWE-77 | Command Injection | `app.py` `ping()` | `GET /ping?host=` | Host passed to `shell=True` |
| 5 | A01 Access Control | CWE-22 | Path Traversal | `app.py` `download()` | `GET /download?file=` | Filename joined without sanitization |
| 6 | A01 Access Control | CWE-548 | Directory Listing | `app.py` `files()` | `GET /files` | Lists upload dir contents |
| 7 | A01 Access Control | CWE-918 | SSRF | `app.py` `fetch()` | `GET /fetch?url=` | Server fetches arbitrary URL |
| 8 | A01 Access Control | CWE-352 | CSRF | `app.py` `profile()` | `POST /profile` | State change, no token/SameSite |
| 9 | A01 Access Control | CWE-615 | Sensitive info in comments | `app.py`, templates | source | Hint comments in code |
| 10 | A02 Misconfig | CWE-526 | Env/secret exposure | `app.py` `debug()` | `GET /debug` | Dumps `APP_SECRET`, `DB_ADMIN_PW` |
| 11 | A02 Misconfig | CWE-16 | Debug mode / weak config | `app.py` config | app-wide | `DEBUG=True`, weak secret default |
| 12 | A02 Misconfig | CWE-1004 | Cookie w/o HttpOnly | `app.py` config | session cookie | `SESSION_COOKIE_HTTPONLY=False` |
| 13 | A03 Supply Chain | CWE-1035/1395 | Vulnerable component pin | `requirements.txt` / README | dependencies | Use of outdated libs (see note) |
| 14 | A04 Crypto | CWE-319 | Cleartext transmission | `app.py` config | HTTP only | No TLS, `SECURE=False` |
| 15 | A04 Crypto | CWE-328 | Reversible/weak hash | `app.py` `weak_hash()` | password storage | Unsalted MD5 |
| 16 | A06 Insecure Design | CWE-266 | Privilege escalation | `app.py` `register()` | `POST /register` | Client-supplied `role` field |
| 17 | A06 Insecure Design | CWE-1021 | UI redressing | `app.py` | headers | No X-Frame-Options / CSP |
| 18 | A07 Auth | CWE-287/308 | Improper / single-factor auth | `app.py` `admin()` | `GET /admin` | Role trusted from session only |
| 19 | A07 Auth | CWE-384 | Session fixation | `app.py` `login()` | `POST /login` | Session id not rotated |
| 20 | A07 Auth | CWE-640 | Weak password recovery | `app.py` `forgot()` | `POST /forgot` | Predictable reset, user enum |
| 21 | A08 Integrity | — | Unvalidated file upload | `app.py` `upload()` | `POST /upload` | No type/content validation |
| 22 | A09 Logging | CWE-532 | Secrets in logs | `app.py` `login()` | `app.log` | Passwords written to log |
| 23 | A10 Exceptions | CWE-755 | Verbose error handling | `app.py` `students()` | `GET /students?q='` | SQL error + query leaked to user |

### Descriptions, objectives & validation (selected)

**SQL Injection (CWE-89)** — *Objective:* recognize string-concatenated SQL.
*Validate:* `GET /students?q=x' UNION SELECT id,username,password_hash,role FROM users-- `
→ dumps user hashes. *Impact:* full DB read, credential theft.

**Stored XSS with filter bypass (CWE-79)** — *Objective:* defeat a naive blocklist.
*Validate:* enroll a student with bio `<img src=x onerror=confirm(1)>`; view the profile.
`<script>`/`alert` are stripped, event-handler payload executes. *Impact:* session theft
(cookie is non-HttpOnly), defacement.

**Privilege escalation (CWE-266)** — *Objective:* never trust client-set roles.
*Validate:* POST to `/register` with `role=admin`, log in, reach `/admin`.
*Impact:* full admin takeover from an anonymous enrollment form.

**Command Injection (CWE-77)** — *Validate:* `GET /ping?host=127.0.0.1;id` → runs `id`.
*Impact:* remote code execution.

**Path Traversal (CWE-22)** — *Validate:* `GET /download?file=../app.py` → source disclosure.

**SSRF (CWE-918)** — *Validate:* `GET /fetch?url=http://169.254.169.254/...` (cloud metadata).

**Weak recovery (CWE-640)** — *Validate:* `/forgot` with a known username resets to
`Reset@<username>` and confirms user existence.

---

## Lab Exercises

1. **Recon & discovery** — map endpoints; identify which inputs reach SQL, shell, or the DOM.
2. **SQLi** — extract all password hashes from `/students`; then crack the MD5s.
3. **XSS filter evasion** — craft a stored payload that steals `document.cookie` given the
   non-HttpOnly session cookie.
4. **Auth abuse** — self-register as `admin`; explain the root cause and the fix.
5. **RCE chain** — use `/ping` command injection to read `/debug` secrets, then pivot.
6. **File & SSRF** — combine `/upload`, `/download` traversal, and `/fetch` SSRF.

### Recommended tooling
Burp Suite / OWASP ZAP, sqlmap, hashcat/john, curl, browser devtools.

### Secure-coding remediation guidance
- Use **parameterized queries** everywhere (already correct on login — mirror it in search).
- Replace blocklist filtering with **context-aware output encoding** (Jinja autoescape;
  drop `|safe`).
- **Server-side authorization**: assign roles server-side; never accept `role` from forms.
- Never build shell commands from input; use `subprocess` arg lists, no `shell=True`.
- Canonicalize + allowlist file paths; validate upload type/size; disable directory listing.
- Restrict/allowlist SSRF targets; block link-local ranges.
- Use **bcrypt/argon2** with salts; enforce HTTPS; set `HttpOnly`, `Secure`, `SameSite`.
- Disable debug in production; add CSRF tokens; add generic error pages; scrub logs.
- Pin and patch dependencies; run SCA (e.g. `pip-audit`).
