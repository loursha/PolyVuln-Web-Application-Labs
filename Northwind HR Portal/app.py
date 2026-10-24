"""
============================================================================
 Northwind HR Portal  --  INTENTIONALLY VULNERABLE TRAINING APPLICATION
============================================================================
 Difficulty: Intermediate
 Stack: Python Flask + SQLite + Bootstrap 5

 !!  DO NOT DEPLOY TO PRODUCTION OR EXPOSE TO THE INTERNET  !!
 Every weakness below is deliberate. See README.md vulnerability matrix.
============================================================================
"""
import os
import sqlite3
import hashlib
import subprocess
import logging

import requests
from flask import (
    Flask, request, session, redirect, url_for, render_template,
    g, make_response, send_from_directory, abort, jsonify
)
from dotenv import load_dotenv

load_dotenv()

# --- CWE-16 / CWE-526: config weaknesses + secrets from env ---
SECRET_KEY = os.environ.get("SECRET_KEY", "hr_super_secret_key_2024_do_not_change")
DATABASE = os.environ.get("DATABASE", "hrportal.db")
UPLOAD_DIR = os.path.join(os.path.dirname(__file__), "static", "uploads")
os.makedirs(UPLOAD_DIR, exist_ok=True)

app = Flask(__name__)
app.secret_key = SECRET_KEY
# CWE-1004: session cookie without HttpOnly/Secure; predictable settings
app.config.update(
    SESSION_COOKIE_HTTPONLY=False,
    SESSION_COOKIE_SECURE=False,
    SESSION_COOKIE_SAMESITE=None,
)

# --- CWE-532: sensitive info written to log files ---
logging.basicConfig(
    filename=os.path.join(os.path.dirname(__file__), "logs", "app.log"),
    level=logging.DEBUG,
    format="%(asctime)s %(levelname)s %(message)s",
)
logger = logging.getLogger("hrportal")


# ---------------------------------------------------------------------------
# DB helpers
# ---------------------------------------------------------------------------
def get_db():
    if "db" not in g:
        g.db = sqlite3.connect(DATABASE)
        g.db.row_factory = sqlite3.Row
    return g.db


@app.teardown_appcontext
def close_db(exc):
    db = g.pop("db", None)
    if db is not None:
        db.close()


def md5(pw):
    # CWE-328: reversible/weak unsalted MD5
    return hashlib.md5(pw.encode()).hexdigest()


def current_user():
    if "user_id" not in session:
        return None
    db = get_db()
    return db.execute("SELECT * FROM users WHERE id=?", (session["user_id"],)).fetchone()


def login_required(fn):
    from functools import wraps

    @wraps(fn)
    def wrapper(*a, **k):
        if "user_id" not in session:
            return redirect(url_for("login"))
        return fn(*a, **k)

    return wrapper


# ---------------------------------------------------------------------------
# Auth
# ---------------------------------------------------------------------------
@app.route("/")
def index():
    if "user_id" in session:
        return redirect(url_for("dashboard"))
    return redirect(url_for("login"))


@app.route("/login", methods=["GET", "POST"])
def login():
    error = None
    if request.method == "POST":
        username = request.form.get("username", "")
        password = request.form.get("password", "")
        db = get_db()
        # NOTE: login uses parameterized query (SQLi is intentionally NOT here,
        # per requirement; it lives in the post-auth search feature instead).
        row = db.execute(
            "SELECT * FROM users WHERE username=? AND password=?",
            (username, md5(password)),
        ).fetchone()
        # CWE-532: logging credentials in cleartext
        logger.info("LOGIN attempt user=%s pass=%s", username, password)
        if row:
            # CWE-384: session fixation -- session id is NOT regenerated on login
            session["user_id"] = row["id"]
            session["role"] = row["role"]
            session["username"] = row["username"]
            return redirect(url_for("dashboard"))
        error = "Invalid credentials"
    return render_template("login.html", error=error)


@app.route("/logout")
def logout():
    session.clear()
    return redirect(url_for("login"))


@app.route("/forgot", methods=["GET", "POST"])
def forgot():
    """CWE-640: weak password recovery via security question, cleartext answer,
    and the new password is displayed straight back to the browser."""
    msg = None
    if request.method == "POST":
        username = request.form.get("username", "")
        answer = request.form.get("answer", "")
        newpass = request.form.get("newpass", "")
        db = get_db()
        row = db.execute("SELECT * FROM users WHERE username=?", (username,)).fetchone()
        if row and row["security_answer"].lower() == answer.strip().lower():
            db.execute("UPDATE users SET password=? WHERE username=?", (md5(newpass), username))
            db.commit()
            msg = f"Password reset successfully. Your new password is: {newpass}"
        else:
            # verbose disclosure of the question helps enumeration
            q = row["security_question"] if row else "unknown"
            msg = f"Wrong answer. Hint - security question was: {q}"
    return render_template("forgot.html", msg=msg)


# ---------------------------------------------------------------------------
# Dashboard
# ---------------------------------------------------------------------------
@app.route("/dashboard")
@login_required
def dashboard():
    db = get_db()
    user = current_user()
    announcements = db.execute(
        "SELECT * FROM announcements ORDER BY id DESC"
    ).fetchall()
    stats = {
        "employees": db.execute("SELECT COUNT(*) c FROM users").fetchone()["c"],
        "pending_leave": db.execute(
            "SELECT COUNT(*) c FROM leave_requests WHERE status='Pending'"
        ).fetchone()["c"],
        "departments": db.execute(
            "SELECT COUNT(DISTINCT department) c FROM users"
        ).fetchone()["c"],
        "announcements": db.execute("SELECT COUNT(*) c FROM announcements").fetchone()["c"],
    }
    # simple dept distribution for chart
    dept_rows = db.execute(
        "SELECT department, COUNT(*) c FROM users GROUP BY department"
    ).fetchall()
    dept_data = {r["department"]: r["c"] for r in dept_rows}
    return render_template(
        "dashboard.html", user=user, announcements=announcements,
        stats=stats, dept_data=dept_data,
    )


# ---------------------------------------------------------------------------
# Profile  (Stored XSS via bio -- rendered unescaped)
# ---------------------------------------------------------------------------
@app.route("/profile", methods=["GET", "POST"])
@login_required
def profile():
    db = get_db()
    user = current_user()
    if request.method == "POST":
        bio = request.form.get("bio", "")
        phone = request.form.get("phone", "")
        # CWE-79 Stored XSS: bio saved raw, later rendered with |safe
        db.execute("UPDATE users SET bio=?, phone=? WHERE id=?", (bio, phone, user["id"]))
        db.commit()
        return redirect(url_for("profile"))
    return render_template("profile.html", user=user)


# ---------------------------------------------------------------------------
# Employee Directory + SEARCH  (SQL Injection lives here, post-auth)
# ---------------------------------------------------------------------------
@app.route("/employees")
@login_required
def employees():
    db = get_db()
    q = request.args.get("q", "")
    rows = []
    sql = None
    if q:
        # CWE-89 SQL Injection: raw string concatenation in post-login search
        sql = (
            "SELECT id, username, full_name, email, department, title, role, salary "
            "FROM users WHERE full_name LIKE '%" + q + "%' "
            "OR department LIKE '%" + q + "%' OR title LIKE '%" + q + "%'"
        )
        try:
            rows = db.execute(sql).fetchall()
        except Exception as e:
            # CWE-755: exceptional condition leaks the failing SQL + error
            return render_template(
                "employees.html", rows=[], q=q, user=current_user(),
                error=f"Query failed: {e} ", sql=sql,
            )
    else:
        rows = db.execute(
            "SELECT id, username, full_name, email, department, title, role, salary FROM users"
        ).fetchall()
    return render_template(
        "employees.html", rows=rows, q=q, user=current_user(), sql=sql
    )


@app.route("/employee/<int:eid>")
@login_required
def employee_detail(eid):
    # CWE-639/266-style IDOR: any logged-in user can view any employee record,
    # including salary, by changing the id.
    db = get_db()
    row = db.execute("SELECT * FROM users WHERE id=?", (eid,)).fetchone()
    if not row:
        abort(404)
    return render_template("employee_detail.html", emp=row, user=current_user())


# ---------------------------------------------------------------------------
# Leave management  (Reflected XSS on search term)
# ---------------------------------------------------------------------------
@app.route("/leave", methods=["GET", "POST"])
@login_required
def leave():
    db = get_db()
    user = current_user()
    if request.method == "POST":
        # CWE-79 Stored XSS: reason rendered unescaped in approver views
        db.execute(
            "INSERT INTO leave_requests (user_id,leave_type,start_date,end_date,reason) "
            "VALUES (?,?,?,?,?)",
            (user["id"], request.form.get("leave_type"), request.form.get("start_date"),
             request.form.get("end_date"), request.form.get("reason")),
        )
        db.commit()
        return redirect(url_for("leave"))

    # CWE-80 Reflected XSS: 'filter' echoed straight into the page via |safe
    reflected = request.args.get("filter", "")
    rows = db.execute(
        """SELECT lr.*, u.full_name FROM leave_requests lr
           JOIN users u ON u.id = lr.user_id ORDER BY lr.id DESC"""
    ).fetchall()
    return render_template("leave.html", rows=rows, user=user, reflected=reflected)


# ---------------------------------------------------------------------------
# HR MODULE  (role: hr or admin)
# ---------------------------------------------------------------------------
def hr_required(fn):
    from functools import wraps

    @wraps(fn)
    def wrapper(*a, **k):
        # CWE-266 / CWE-287: role check reads the CLIENT-controlled cookie/session
        # value only; there is also a header backdoor below.
        role = session.get("role", "")
        # backdoor: trusting a request header for privilege
        if request.headers.get("X-HR-Override") == "true":
            role = "hr"
        if role not in ("hr", "admin"):
            abort(403)
        return fn(*a, **k)

    return wrapper


@app.route("/hr")
@hr_required
def hr_home():
    db = get_db()
    leave_rows = db.execute(
        """SELECT lr.*, u.full_name FROM leave_requests lr
           JOIN users u ON u.id=lr.user_id ORDER BY lr.id DESC"""
    ).fetchall()
    payroll_rows = db.execute(
        """SELECT p.*, u.full_name FROM payroll p
           JOIN users u ON u.id=p.user_id ORDER BY p.id DESC"""
    ).fetchall()
    return render_template(
        "hr.html", leave_rows=leave_rows, payroll_rows=payroll_rows, user=current_user()
    )


@app.route("/hr/leave/<int:lid>/<action>")
@hr_required
def hr_leave_action(lid, action):
    # CWE-352 CSRF: state-changing GET request, no token
    status = "Approved" if action == "approve" else "Rejected"
    db = get_db()
    db.execute("UPDATE leave_requests SET status=? WHERE id=?", (status, lid))
    db.commit()
    return redirect(url_for("hr_home"))


@app.route("/hr/announce", methods=["POST"])
@hr_required
def hr_announce():
    # CWE-79 Stored XSS: announcement body rendered unescaped on dashboard
    db = get_db()
    db.execute(
        "INSERT INTO announcements (title, body, author) VALUES (?,?,?)",
        (request.form.get("title"), request.form.get("body"),
         current_user()["full_name"]),
    )
    db.commit()
    return redirect(url_for("hr_home"))


# ---------------------------------------------------------------------------
# ADMIN MODULE  (user management + system tools)
# ---------------------------------------------------------------------------
def admin_required(fn):
    from functools import wraps

    @wraps(fn)
    def wrapper(*a, **k):
        if session.get("role") != "admin":
            abort(403)
        return fn(*a, **k)

    return wrapper


@app.route("/admin")
@admin_required
def admin_home():
    db = get_db()
    users = db.execute("SELECT * FROM users ORDER BY id").fetchall()
    return render_template("admin.html", users=users, user=current_user())


@app.route("/admin/user/add", methods=["POST"])
@admin_required
def admin_add_user():
    db = get_db()
    f = request.form
    db.execute(
        """INSERT INTO users (username,password,full_name,email,role,department,title,salary,
           security_question,security_answer)
           VALUES (?,?,?,?,?,?,?,?,?,?)""",
        (f.get("username"), md5(f.get("password", "changeme")), f.get("full_name"),
         f.get("email"), f.get("role", "employee"), f.get("department"),
         f.get("title"), f.get("salary") or 0, "Favorite color?", "blue"),
    )
    db.commit()
    return redirect(url_for("admin_home"))


@app.route("/admin/user/<int:uid>/role", methods=["POST"])
@admin_required
def admin_set_role(uid):
    # CWE-266 incorrect privilege assignment: no guardrails, any role settable
    db = get_db()
    db.execute("UPDATE users SET role=? WHERE id=?", (request.form.get("role"), uid))
    db.commit()
    return redirect(url_for("admin_home"))


@app.route("/admin/user/<int:uid>/delete")
@admin_required
def admin_delete_user(uid):
    # CWE-352 CSRF via GET again
    db = get_db()
    db.execute("DELETE FROM users WHERE id=?", (uid,))
    db.commit()
    return redirect(url_for("admin_home"))


@app.route("/admin/ping", methods=["GET", "POST"])
@admin_required
def admin_ping():
    """CWE-77 Command Injection: 'host' passed to shell unescaped."""
    output = None
    if request.method == "POST":
        host = request.form.get("host", "")
        try:
            # deliberately vulnerable: shell=True with user input
            output = subprocess.check_output(
                f"ping -c 2 {host}", shell=True, stderr=subprocess.STDOUT, timeout=10
            ).decode()
        except Exception as e:
            output = f"Error: {e}"  # CWE-755 verbose error
    return render_template("admin_tools.html", output=output, user=current_user(), mode="ping")


@app.route("/admin/fetch", methods=["GET", "POST"])
@admin_required
def admin_fetch():
    """CWE-918 SSRF: server fetches an arbitrary user-supplied URL."""
    output = None
    if request.method == "POST":
        target = request.form.get("url", "")
        try:
            r = requests.get(target, timeout=8)
            output = r.text[:4000]
        except Exception as e:
            output = f"Error: {e}"
    return render_template("admin_tools.html", output=output, user=current_user(), mode="fetch")


# ---------------------------------------------------------------------------
# Documents  (Path traversal + insecure upload + directory listing)
# ---------------------------------------------------------------------------
@app.route("/documents", methods=["GET", "POST"])
@login_required
def documents():
    db = get_db()
    user = current_user()
    if request.method == "POST":
        file = request.files.get("file")
        if file and file.filename:
            # CWE-434 insecure upload: no type/extension validation, original name kept
            path = os.path.join(UPLOAD_DIR, file.filename)
            file.save(path)
            db.execute(
                "INSERT INTO documents (user_id, filename, original_name) VALUES (?,?,?)",
                (user["id"], file.filename, file.filename),
            )
            db.commit()
        return redirect(url_for("documents"))
    docs = db.execute("SELECT * FROM documents ORDER BY id DESC").fetchall()
    # CWE-548 directory listing exposed
    listing = os.listdir(UPLOAD_DIR)
    return render_template("documents.html", docs=docs, listing=listing, user=user)


@app.route("/download")
@login_required
def download():
    # CWE-22 Path Traversal: filename joined without sanitization
    fname = request.args.get("file", "")
    full = os.path.join(UPLOAD_DIR, fname)
    try:
        with open(full, "rb") as fh:
            data = fh.read()
        resp = make_response(data)
        resp.headers["Content-Disposition"] = f"attachment; filename={os.path.basename(fname)}"
        return resp
    except Exception as e:
        return f"Error reading file: {e}", 500  # CWE-755


# ---------------------------------------------------------------------------
# Debug / info endpoints (CWE-526 env exposure, verbose)
# ---------------------------------------------------------------------------
@app.route("/debug/env")
@login_required
def debug_env():
    # CWE-526: dumps environment variables incl. secrets to any logged-in user
    return jsonify({k: v for k, v in os.environ.items()})


@app.route("/uploads/<path:fname>")
def uploads(fname):
    return send_from_directory(UPLOAD_DIR, fname)


# CWE-1021: no anti-framing headers anywhere -> clickjackable.
# CWE-319: served over plain HTTP (see README/docker).

if __name__ == "__main__":
    if not os.path.exists(DATABASE):
        import init_db
        init_db.init_db()
    # CWE-16: debug=True in a network-exposed app -> Werkzeug console / traceback
    app.run(host="0.0.0.0", port=5000, debug=True)
