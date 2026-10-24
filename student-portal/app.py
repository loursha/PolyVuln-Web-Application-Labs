"""
EduCore - Student Management Portal
INTENTIONALLY VULNERABLE APPLICATION - FOR SECURITY TRAINING ONLY
Do NOT deploy on public networks.

Stack: Flask + SQLite + Bootstrap 5
"""

import os
import re
import base64
import hashlib
import sqlite3
import subprocess
import urllib.request
from flask import (
    Flask, request, render_template, redirect, url_for,
    session, make_response, send_file, g, abort
)

# CWE-526: sensitive config pulled from environment and echoed in /debug
APP_SECRET = os.environ.get("APP_SECRET", "dev-secret-please-change")  # weak default
DB_ADMIN_PW = os.environ.get("DB_ADMIN_PW", "SuperSecretDbPass!23")   # sensitive env var

app = Flask(__name__)
# CWE-16 / CWE-1004: session cookie without HttpOnly/Secure; predictable secret
app.secret_key = APP_SECRET
app.config.update(
    SESSION_COOKIE_HTTPONLY=False,   # CWE-1004: JS-accessible cookie
    SESSION_COOKIE_SECURE=False,     # CWE-319: no TLS requirement
    SESSION_COOKIE_SAMESITE=None,    # aids CSRF
    DEBUG=True,                       # CWE-16: debug mode left on
)

DB_PATH = os.path.join(os.path.dirname(__file__), "school.db")
UPLOAD_DIR = os.path.join(os.path.dirname(__file__), "uploads")
os.makedirs(UPLOAD_DIR, exist_ok=True)


def get_db():
    if "db" not in g:
        g.db = sqlite3.connect(DB_PATH)
        g.db.row_factory = sqlite3.Row
    return g.db


@app.teardown_appcontext
def close_db(exc):
    db = g.pop("db", None)
    if db is not None:
        db.close()


# CWE-328: reversible/weak one-way hash (MD5, unsalted)
def weak_hash(pw):
    return hashlib.md5(pw.encode()).hexdigest()


# --- Naive XSS filter (INTENTIONALLY WEAK) ---
# Per lab spec: only blocks <script> tags and the literal "alert".
# Bypassable via event handlers, <img onerror>, <svg>, String.fromCharCode, etc.
def naive_xss_filter(value):
    if value is None:
        return value
    cleaned = re.sub(r"<\s*script[^>]*>.*?<\s*/\s*script\s*>", "", value,
                     flags=re.IGNORECASE | re.DOTALL)
    cleaned = re.sub(r"<\s*script[^>]*>", "", cleaned, flags=re.IGNORECASE)
    cleaned = re.sub(r"alert", "", cleaned, flags=re.IGNORECASE)
    return cleaned


def current_user():
    uid = session.get("uid")
    if not uid:
        return None
    db = get_db()
    return db.execute("SELECT * FROM users WHERE id=?", (uid,)).fetchone()


@app.context_processor
def inject_user():
    return {"me": current_user()}


# ---------------- Auth ----------------

@app.route("/")
def index():
    if current_user():
        return redirect(url_for("dashboard"))
    return redirect(url_for("login"))


@app.route("/login", methods=["GET", "POST"])
def login():
    # NOTE: login uses parameterized queries (NO SQL injection here, per spec)
    error = None
    if request.method == "POST":
        username = request.form.get("username", "")
        password = request.form.get("password", "")
        db = get_db()
        row = db.execute(
            "SELECT * FROM users WHERE username=? AND password_hash=?",
            (username, weak_hash(password)),
        ).fetchone()
        if row:
            # CWE-384: session fixation - session id not rotated on login
            session["uid"] = row["id"]
            session["role"] = row["role"]
            # CWE-532: credentials/sensitive data written to log file
            with open("app.log", "a") as f:
                f.write(f"LOGIN ok user={username} pw={password} role={row['role']}\n")
            return redirect(url_for("dashboard"))
        else:
            error = "Invalid credentials"
            with open("app.log", "a") as f:
                f.write(f"LOGIN fail user={username} pw={password}\n")
    return render_template("login.html", error=error)


@app.route("/register", methods=["GET", "POST"])
def register():
    # Students self-enroll. CWE-266: role is accepted from the form (client-controlled),
    # allowing privilege escalation to 'admin' via a crafted request.
    msg = None
    if request.method == "POST":
        username = request.form.get("username", "").strip()
        password = request.form.get("password", "")
        full_name = request.form.get("full_name", "")
        role = request.form.get("role", "student")  # CWE-266 hidden field abuse
        db = get_db()
        try:
            db.execute(
                "INSERT INTO users (username, password_hash, full_name, role) VALUES (?,?,?,?)",
                (username, weak_hash(password), full_name, role),
            )
            db.commit()
            return redirect(url_for("login"))
        except sqlite3.IntegrityError:
            msg = "Username already exists"
    return render_template("register.html", msg=msg)


@app.route("/logout")
def logout():
    session.clear()
    return redirect(url_for("login"))


@app.route("/forgot", methods=["GET", "POST"])
def forgot():
    # CWE-640: weak password recovery - reveals whether user exists and
    # resets password to a predictable value with no verification.
    info = None
    if request.method == "POST":
        username = request.form.get("username", "")
        db = get_db()
        row = db.execute("SELECT * FROM users WHERE username=?", (username,)).fetchone()
        if row:
            new_pw = "Reset@" + username  # predictable recovery value
            db.execute("UPDATE users SET password_hash=? WHERE username=?",
                       (weak_hash(new_pw), username))
            db.commit()
            info = f"Password for {username} has been reset to: {new_pw}"
        else:
            info = "No such user"
    return render_template("forgot.html", info=info)


# ---------------- App pages ----------------

@app.route("/dashboard")
def dashboard():
    if not current_user():
        return redirect(url_for("login"))
    db = get_db()
    total_students = db.execute("SELECT COUNT(*) c FROM students").fetchone()["c"]
    total_courses = db.execute("SELECT COUNT(*) c FROM courses").fetchone()["c"]
    total_users = db.execute("SELECT COUNT(*) c FROM users").fetchone()["c"]
    recent = db.execute("SELECT * FROM students ORDER BY id DESC LIMIT 5").fetchall()
    return render_template("dashboard.html", total_students=total_students,
                           total_courses=total_courses, total_users=total_users,
                           recent=recent)


@app.route("/students")
def students():
    if not current_user():
        return redirect(url_for("login"))
    q = request.args.get("q", "")
    db = get_db()
    # CWE-89: SQL Injection via unparameterized search (NOT on login page)
    sql = "SELECT * FROM students WHERE name LIKE '%" + q + "%' OR grade LIKE '%" + q + "%'"
    try:
        rows = db.execute(sql).fetchall()
    except Exception as e:
        # CWE-755: exceptional condition surfaced verbosely to the user
        return f"<pre>Query error: {e}\nSQL: {sql}</pre>", 500
    return render_template("students.html", rows=rows, q=q)


@app.route("/student/add", methods=["GET", "POST"])
def student_add():
    if not current_user():
        return redirect(url_for("login"))
    if request.method == "POST":
        name = request.form.get("name", "")
        grade = request.form.get("grade", "")
        # CWE-79 Stored XSS: 'bio' saved through the naive filter, rendered unescaped.
        bio = naive_xss_filter(request.form.get("bio", ""))
        db = get_db()
        db.execute("INSERT INTO students (name, grade, bio) VALUES (?,?,?)",
                   (name, grade, bio))
        db.commit()
        return redirect(url_for("students"))
    return render_template("student_add.html")


@app.route("/student/<int:sid>")
def student_view(sid):
    if not current_user():
        return redirect(url_for("login"))
    db = get_db()
    s = db.execute("SELECT * FROM students WHERE id=?", (sid,)).fetchone()
    if not s:
        abort(404)
    return render_template("student_view.html", s=s)


@app.route("/search")
def search():
    # CWE-79 Reflected XSS: query echoed through naive filter, rendered raw
    term = naive_xss_filter(request.args.get("term", ""))
    return render_template("search.html", term=term)


@app.route("/profile", methods=["GET", "POST"])
def profile():
    u = current_user()
    if not u:
        return redirect(url_for("login"))
    db = get_db()
    if request.method == "POST":
        # CWE-352: no CSRF token on a state-changing request
        full_name = naive_xss_filter(request.form.get("full_name", ""))
        db.execute("UPDATE users SET full_name=? WHERE id=?", (full_name, u["id"]))
        db.commit()
        return redirect(url_for("profile"))
    return render_template("profile.html")


@app.route("/upload", methods=["GET", "POST"])
def upload():
    if not current_user():
        return redirect(url_for("login"))
    msg = None
    if request.method == "POST":
        f = request.files.get("file")
        if f:
            # CWE-434-ish / no validation of type; path from user filename
            path = os.path.join(UPLOAD_DIR, f.filename)
            f.save(path)
            msg = f"Saved to {path}"
    return render_template("upload.html", msg=msg)


@app.route("/download")
def download():
    # CWE-22: Path Traversal - filename joined without sanitization
    name = request.args.get("file", "")
    path = os.path.join(UPLOAD_DIR, name)
    return send_file(path)


@app.route("/files")
def files():
    # CWE-548: Directory listing exposure
    listing = os.listdir(UPLOAD_DIR)
    return render_template("files.html", listing=listing)


@app.route("/fetch")
def fetch():
    # CWE-918 SSRF: fetches arbitrary user-supplied URL server-side
    url = request.args.get("url", "")
    if not url:
        return "Provide ?url="
    try:
        with urllib.request.urlopen(url, timeout=5) as r:
            return make_response(r.read())
    except Exception as e:
        return f"Fetch error: {e}", 500


@app.route("/ping")
def ping():
    # CWE-77 Command Injection: host passed to shell unsanitized
    host = request.args.get("host", "127.0.0.1")
    try:
        out = subprocess.check_output("ping -c 1 " + host, shell=True,
                                      stderr=subprocess.STDOUT, timeout=8)
        return "<pre>" + out.decode(errors="ignore") + "</pre>"
    except subprocess.CalledProcessError as e:
        return "<pre>" + e.output.decode(errors="ignore") + "</pre>"


@app.route("/debug")
def debug():
    # CWE-526 / CWE-615: environment + secrets exposed
    info = {
        "APP_SECRET": APP_SECRET,
        "DB_ADMIN_PW": DB_ADMIN_PW,
        "cookies": dict(request.cookies),
        "env_sample": {k: os.environ.get(k) for k in ("PATH", "HOME")},
    }
    return render_template("debug.html", info=info)


@app.route("/admin")
def admin():
    # CWE-287 / CWE-266: authorization decided by client-supplied cookie/role only
    if session.get("role") != "admin":
        return "Forbidden - admins only", 403
    db = get_db()
    users = db.execute("SELECT id, username, full_name, role FROM users").fetchall()
    return render_template("admin.html", users=users)


if __name__ == "__main__":
    app.run(host="0.0.0.0", port=5000)
