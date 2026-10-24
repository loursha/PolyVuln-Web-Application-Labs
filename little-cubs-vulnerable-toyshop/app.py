"""
================================================================================
 Little Cubs Toy Co. -- Intentionally Vulnerable Soft Toy Shop (Training App)
================================================================================

  THIS APPLICATION IS INTENTIONALLY INSECURE.
  It was generated for security education, penetration-testing practice,
  and secure-code-review training ONLY.

  DO NOT deploy this application on any public network, cloud host, or
  production environment. DO NOT reuse any code pattern from this project
  in a real product.

  See README.md for the full vulnerability matrix, lab exercises and
  remediation guidance.
================================================================================
"""

import os
import sqlite3
import hashlib
import logging
import subprocess
import pickle
import base64
import time
from datetime import datetime

from flask import (
    Flask, request, session, redirect, url_for, render_template,
    g, flash, make_response, jsonify, Response
)
import requests

# --------------------------------------------------------------------------
# App configuration
# --------------------------------------------------------------------------
BASE_DIR = os.path.dirname(os.path.abspath(__file__))
DB_PATH = os.path.join(BASE_DIR, "instance", "toyshop.db")
UPLOAD_DIR = os.path.join(BASE_DIR, "static", "uploads")
LOG_PATH = os.path.join(BASE_DIR, "logs", "app.log")

app = Flask(__name__)

# ----------------------------------------------------------------------------
# VULNERABILITY #6 - CWE-16 Configuration Weaknesses
# OWASP A02:2025 Security Misconfiguration
#
# Hardcoded weak secret key, debug mode enabled, and a permissive CORS
# header applied to every response. Any of these alone is a serious
# misconfiguration in a real deployment.
# ----------------------------------------------------------------------------
app.config["SECRET_KEY"] = "toy-shop-super-secret-2024"     # weak, hardcoded key
app.config["DEBUG"] = True                                    # Werkzeug debugger enabled
app.config["ENV"] = "development"

# ----------------------------------------------------------------------------
# VULNERABILITY #7 - CWE-526 Exposure of Sensitive Information Through
# Environmental Variables (OWASP A01:2025 Broken Access Control)
#
# These "secrets" are stashed in environment variables and later dumped
# in full by an unauthenticated debug endpoint (/debug/env below).
# ----------------------------------------------------------------------------
os.environ.setdefault("DB_ADMIN_PASSWORD", "sup3r_dup3r_db_pw!")
os.environ.setdefault("STRIPE_SECRET_KEY", "sk_test_51FAKEKEYFORLABUSEONLY000000")
os.environ.setdefault("MAIL_SMTP_PASSWORD", "smtp_mail_pw_2024")
os.environ.setdefault("INTERNAL_ADMIN_BACKDOOR", "littlecubs-master-override")

# ----------------------------------------------------------------------------
# Logging setup
# ----------------------------------------------------------------------------
os.makedirs(os.path.dirname(LOG_PATH), exist_ok=True)
logging.basicConfig(
    filename=LOG_PATH,
    level=logging.INFO,
    format="%(asctime)s [%(levelname)s] %(message)s",
)
logger = logging.getLogger("littlecubs")


# ----------------------------------------------------------------------------
# VULNERABILITY #6 continued - permissive CORS applied globally
# ----------------------------------------------------------------------------
@app.after_request
def add_permissive_headers(resp):
    # Wide-open CORS -- any origin may read authenticated API responses.
    resp.headers["Access-Control-Allow-Origin"] = "*"
    resp.headers["Access-Control-Allow-Credentials"] = "true"
    resp.headers["Access-Control-Allow-Headers"] = "*"
    resp.headers["Access-Control-Allow-Methods"] = "*"
    # NOTE: Intentionally NOT setting X-Frame-Options / frame-ancestors here.
    # See VULNERABILITY #17 (CWE-1021, clickjacking) -- checkout page can be framed.
    return resp


# --------------------------------------------------------------------------
# Database helpers
# --------------------------------------------------------------------------
def get_db():
    if "db" not in g:
        g.db = sqlite3.connect(DB_PATH)
        g.db.row_factory = sqlite3.Row
    return g.db


@app.teardown_appcontext
def close_db(exception=None):
    db = g.pop("db", None)
    if db is not None:
        db.close()


def md5_hash(password):
    # VULNERABILITY #11 - CWE-328 Reversible One-Way Hash (OWASP A04:2025)
    # MD5 is fast and unsalted -- trivially reversible via rainbow tables.
    return hashlib.md5(password.encode()).hexdigest()


# --------------------------------------------------------------------------
# VULNERABILITY #24 - CWE-532 Sensitive Information Stored in Log Files
# OWASP A09:2025 Security Logging and Alerting Failures
#
# Login attempts are logged including the RAW plaintext password and the
# resulting session token. Any user/process with log read access can
# harvest live credentials and session tokens.
# --------------------------------------------------------------------------
def log_login_attempt(username, password, success, session_token=None):
    logger.info(
        "LOGIN ATTEMPT user=%s password=%s success=%s session_token=%s ip=%s",
        username, password, success, session_token, request.remote_addr,
    )


# --------------------------------------------------------------------------
# VULNERABILITY #25 - CWE-755 Improper Handling of Exceptional Conditions
# OWASP A10:2025 Mishandling of Exceptional Conditions
#
# The global error handler returns the full exception message and a stack
# trace to the client instead of a generic message, leaking internal
# implementation details (file paths, SQL text, library versions).
# --------------------------------------------------------------------------
@app.errorhandler(Exception)
def handle_any_exception(e):
    import traceback
    tb = traceback.format_exc()
    logger.error("UNHANDLED EXCEPTION: %s", tb)
    return render_template("error.html", error=str(e), traceback=tb), 500


# --------------------------------------------------------------------------
# Template globals
# --------------------------------------------------------------------------
@app.context_processor
def inject_globals():
    return {
        "current_user": session.get("username"),
        "is_admin": session.get("is_admin", False),
        "cart_count": sum(item["qty"] for item in get_cart().values()) if get_cart() else 0,
    }


def get_cart():
    return session.get("cart", {})


# ============================================================================
# PUBLIC STOREFRONT ROUTES
# ============================================================================

@app.route("/")
def home():
    db = get_db()
    featured = db.execute(
        "SELECT * FROM products WHERE featured = 1"
    ).fetchall()
    categories = db.execute("SELECT * FROM categories").fetchall()
    return render_template("home.html", featured=featured, categories=categories)


@app.route("/shop")
def shop():
    db = get_db()
    category_id = request.args.get("category")
    if category_id:
        products = db.execute(
            "SELECT * FROM products WHERE category_id = ?", (category_id,)
        ).fetchall()
    else:
        products = db.execute("SELECT * FROM products").fetchall()
    categories = db.execute("SELECT * FROM categories").fetchall()
    return render_template("shop.html", products=products, categories=categories,
                            selected_category=category_id)


# ----------------------------------------------------------------------------
# VULNERABILITY #15 - CWE-89 SQL Injection (OWASP A05:2025 Injection)
# Endpoint: GET /product?id=<id>
#
# The product id is concatenated directly into the SQL query string rather
# than using a parameterized query. An attacker can inject SQL via the
# `id` query parameter, e.g.:
#     /product?id=0 UNION SELECT id,username,'x',1,1.0,email,'leaked',password_hash,'x.jpg',1,1 FROM users--
# to dump arbitrary table contents (such as the users table).
# ----------------------------------------------------------------------------
@app.route("/product")
def product_detail():
    product_id = request.args.get("id", "1")
    db = get_db()
    query = "SELECT * FROM products WHERE id = " + product_id  # VULNERABLE: string concat
    try:
        product = db.execute(query).fetchone()
    except sqlite3.Error as e:
        logger.error("SQL error on /product: %s | query=%s", e, query)
        raise

    if product is None:
        return render_template("error.html", error="Product not found", traceback=""), 404

    reviews = db.execute(
        "SELECT * FROM reviews WHERE product_id = ? ORDER BY created_at DESC",
        (product["id"],),
    ).fetchall()
    return render_template("product.html", product=product, reviews=reviews)


# ----------------------------------------------------------------------------
# VULNERABILITY #14 - CWE-80 Stored XSS (OWASP A05:2025 Injection)
# Endpoint: POST /product/<id>/review
#
# The review "comment" field is stored as-is and rendered in product.html
# with the `|safe` filter (see templates/product.html), so any HTML/JS an
# attacker submits in a review is executed in every future visitor's
# browser -- a classic stored XSS.
# ----------------------------------------------------------------------------
@app.route("/product/<int:product_id>/review", methods=["POST"])
def add_review(product_id):
    if "username" not in session:
        flash("Please log in to leave a review.", "warning")
        return redirect(url_for("login"))

    rating = request.form.get("rating", "5")
    comment = request.form.get("comment", "")  # VULNERABLE: not sanitized/escaped on output

    db = get_db()
    db.execute(
        "INSERT INTO reviews (product_id, username, rating, comment) VALUES (?, ?, ?, ?)",
        (product_id, session["username"], rating, comment),
    )
    db.commit()
    flash("Thanks for your review!", "success")
    return redirect(url_for("product_detail", id=product_id))


# ----------------------------------------------------------------------------
# VULNERABILITY #13 - CWE-80 Reflected XSS (OWASP A05:2025 Injection)
# Endpoint: GET /search?q=<term>
#
# The search term is reflected directly back into the results page HTML
# via `|safe` in templates/search.html without escaping, so a crafted
# query string containing a <script> payload executes in the victim's
# browser when they click a malicious search link.
# ----------------------------------------------------------------------------
@app.route("/search")
def search():
    q = request.args.get("q", "")
    db = get_db()
    results = db.execute(
        "SELECT * FROM products WHERE name LIKE ? OR description LIKE ?",
        (f"%{q}%", f"%{q}%"),
    ).fetchall()
    return render_template("search.html", query=q, results=results)  # query rendered |safe in template


# ============================================================================
# AUTHENTICATION
# ============================================================================

# ----------------------------------------------------------------------------
# VULNERABILITY #18 - CWE-287 Improper Authentication (OWASP A07:2025)
# VULNERABILITY #10 - CWE-319 Cleartext Transmission of Sensitive Information
#                       (OWASP A04:2025 Cryptographic Failures)
# VULNERABILITY #20 - CWE-384 Session Fixation (OWASP A06:2025 Insecure Design)
# Endpoint: GET/POST /login
#
# 1) Improper Authentication: a hardcoded master-override password
#    ("littlecubs-master-override", also stashed in the INTERNAL_ADMIN_BACKDOOR
#    env var) logs a user in as admin regardless of the username supplied.
# 2) Cleartext transmission: this app is intentionally run over plain HTTP
#    (see run.py) and the login form has no additional transport-layer
#    protection -- credentials travel in cleartext.
# 3) Session fixation: if the client supplies a `sid` query parameter, the
#    server adopts that value as the session id after authentication
#    instead of always issuing a fresh one, allowing an attacker who
#    tricks a victim into using an attacker-chosen session id to hijack
#    the now-authenticated session.
# ----------------------------------------------------------------------------
@app.route("/login", methods=["GET", "POST"])
def login():
    if request.method == "POST":
        username = request.form.get("username", "")
        password = request.form.get("password", "")

        db = get_db()
        user = db.execute(
            "SELECT * FROM users WHERE username = ?", (username,)
        ).fetchone()

        backdoor = os.environ.get("INTERNAL_ADMIN_BACKDOOR", "")
        success = False

        if password == backdoor and backdoor:
            # VULNERABLE: master override bypasses normal password check entirely
            user = db.execute(
                "SELECT * FROM users WHERE is_admin = 1 LIMIT 1"
            ).fetchone()
            success = True
        elif user and user["password_hash"] == md5_hash(password):
            success = True

        # VULNERABILITY #20 - session fixation: honor attacker-supplied sid
        fixed_sid = request.args.get("sid")

        session_token = fixed_sid if fixed_sid else base64.b64encode(os.urandom(16)).decode()

        log_login_attempt(username, password, success, session_token)  # VULNERABILITY #24

        if success:
            session["username"] = user["username"]
            session["user_id"] = user["id"]
            session["is_admin"] = bool(user["is_admin"])
            session["session_token"] = session_token  # not regenerated -> fixation

            resp = make_response(redirect(url_for("home")))
            # VULNERABILITY #8 - CWE-1004 cookie without HttpOnly flag
            resp.set_cookie("remember_token", session_token, httponly=False, samesite=None)
            flash(f"Welcome back, {user['username']}!", "success")
            return resp
        else:
            flash("Invalid username or password.", "danger")
            return render_template("login.html"), 401

    return render_template("login.html")


@app.route("/register", methods=["GET", "POST"])
def register():
    if request.method == "POST":
        username = request.form.get("username", "").strip()
        email = request.form.get("email", "").strip()
        password = request.form.get("password", "")

        db = get_db()
        existing = db.execute(
            "SELECT id FROM users WHERE username = ?", (username,)
        ).fetchone()
        if existing:
            flash("That username is already taken.", "danger")
            return render_template("register.html")

        # ------------------------------------------------------------------
        # VULNERABILITY #16 - CWE-266 Incorrect Privilege Assignment
        # OWASP A06:2025 Insecure Design
        #
        # The registration form includes a hidden `is_admin` field that the
        # server trusts blindly. Any client can submit is_admin=1 in the POST
        # body (e.g. via browser devtools or curl) to self-register as an
        # administrator.
        # ------------------------------------------------------------------
        is_admin_flag = request.form.get("is_admin", "0")  # VULNERABLE: client-controlled

        db.execute(
            "INSERT INTO users (username, email, password_hash, is_admin) VALUES (?, ?, ?, ?)",
            (username, email, md5_hash(password), is_admin_flag),
        )
        db.commit()
        flash("Account created! Please log in.", "success")
        return redirect(url_for("login"))

    return render_template("register.html")


@app.route("/logout")
def logout():
    session.clear()
    resp = make_response(redirect(url_for("home")))
    resp.set_cookie("remember_token", "", expires=0)
    return resp


# ----------------------------------------------------------------------------
# VULNERABILITY #21 - CWE-640 Weak Password Recovery Mechanism
# OWASP A07:2025 Authentication Failures
# Endpoints: GET/POST /forgot-password, GET /reset-password
#
# The reset token is a predictable, non-expiring value derived from the
# username and the current minute-resolution timestamp, and the token is
# reflected directly back to the requester in the HTTP response (normally
# it would only be emailed) -- so anyone who knows/guesses a username can
# obtain a valid reset token for that account without ever accessing the
# victim's inbox.
# ----------------------------------------------------------------------------
@app.route("/forgot-password", methods=["GET", "POST"])
def forgot_password():
    if request.method == "POST":
        username = request.form.get("username", "")
        db = get_db()
        user = db.execute(
            "SELECT * FROM users WHERE username = ?", (username,)
        ).fetchone()

        if user:
            # Predictable token: username + coarse timestamp, MD5'd, no expiry stored
            coarse_time = int(time.time() // 60)  # changes once a minute -> guessable window
            token = hashlib.md5(f"{username}-{coarse_time}".encode()).hexdigest()

            db.execute("UPDATE users SET reset_token = ? WHERE id = ?", (token, user["id"]))
            db.commit()

            flash(
                f"Password reset requested. (Lab mode: your reset link is "
                f"/reset-password?username={username}&token={token})",
                "info",
            )  # VULNERABLE: token disclosed directly instead of only via email
        else:
            flash("If that account exists, a reset link has been generated.", "info")

        return redirect(url_for("forgot_password"))

    return render_template("forgot_password.html")


@app.route("/reset-password", methods=["GET", "POST"])
def reset_password():
    username = request.args.get("username", "")
    token = request.args.get("token", "")

    if request.method == "POST":
        new_password = request.form.get("password", "")
        db = get_db()
        user = db.execute(
            "SELECT * FROM users WHERE username = ? AND reset_token = ?",
            (username, token),
        ).fetchone()
        if user:
            db.execute(
                "UPDATE users SET password_hash = ?, reset_token = NULL WHERE id = ?",
                (md5_hash(new_password), user["id"]),
            )
            db.commit()
            flash("Password updated. Please log in.", "success")
            return redirect(url_for("login"))
        flash("Invalid or expired reset link.", "danger")

    return render_template("reset_password.html", username=username, token=token)


# ============================================================================
# ACCOUNT / CSRF
# ============================================================================

# ----------------------------------------------------------------------------
# VULNERABILITY #2 - CWE-352 Cross-Site Request Forgery (OWASP A01:2025)
# Endpoint: POST /account/update-email
#
# This state-changing endpoint relies solely on the session cookie for
# authentication and includes no CSRF token / origin check, so a
# malicious third-party page can auto-submit a hidden form to this URL
# and silently change the victim's account email while they are logged in.
# ----------------------------------------------------------------------------
@app.route("/account", methods=["GET"])
def account():
    if "username" not in session:
        return redirect(url_for("login"))
    db = get_db()
    user = db.execute(
        "SELECT * FROM users WHERE id = ?", (session["user_id"],)
    ).fetchone()
    orders = db.execute(
        "SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC",
        (session["user_id"],),
    ).fetchall()
    return render_template("account.html", user=user, orders=orders)


@app.route("/account/update-email", methods=["POST"])
def update_email():
    if "username" not in session:
        return redirect(url_for("login"))

    new_email = request.form.get("email", "")  # VULNERABLE: no CSRF token verified
    db = get_db()
    db.execute("UPDATE users SET email = ? WHERE id = ?", (new_email, session["user_id"]))
    db.commit()
    flash("Email address updated.", "success")
    return redirect(url_for("account"))


@app.route("/account/avatar", methods=["POST"])
def upload_avatar():
    if "username" not in session:
        return redirect(url_for("login"))

    file = request.files.get("avatar")
    if file and file.filename:
        # Filename used mostly as-is (light basename only) when saving.
        filename = file.filename.replace("/", "_")
        save_path = os.path.join(UPLOAD_DIR, filename)
        os.makedirs(UPLOAD_DIR, exist_ok=True)
        file.save(save_path)

        db = get_db()
        db.execute("UPDATE users SET avatar = ? WHERE id = ?", (filename, session["user_id"]))
        db.commit()
        flash("Avatar updated!", "success")

    return redirect(url_for("account"))


# ============================================================================
# CART / CHECKOUT
# ============================================================================

@app.route("/cart")
def view_cart():
    cart = get_cart()
    db = get_db()
    items = []
    total = 0.0
    for pid, entry in cart.items():
        product = db.execute("SELECT * FROM products WHERE id = ?", (pid,)).fetchone()
        if product:
            subtotal = product["price"] * entry["qty"]
            total += subtotal
            items.append({"product": product, "qty": entry["qty"], "subtotal": subtotal})
    return render_template("cart.html", items=items, total=total)


@app.route("/cart/add/<int:product_id>", methods=["POST"])
def add_to_cart(product_id):
    cart = get_cart()
    qty = int(request.form.get("qty", 1))
    key = str(product_id)
    if key in cart:
        cart[key]["qty"] += qty
    else:
        cart[key] = {"qty": qty}
    session["cart"] = cart
    flash("Added to your cart.", "success")
    return redirect(request.referrer or url_for("shop"))


@app.route("/cart/remove/<int:product_id>", methods=["POST"])
def remove_from_cart(product_id):
    cart = get_cart()
    cart.pop(str(product_id), None)
    session["cart"] = cart
    return redirect(url_for("view_cart"))


# ----------------------------------------------------------------------------
# VULNERABILITY #23 - Software/Data Integrity Failure (OWASP A08:2025)
# CWE-502-style insecure deserialization
# Endpoint: GET /cart/restore  (reads the `saved_cart` cookie)
#
# A "save my cart for later" feature serializes the cart dict with pickle,
# base64-encodes it into a cookie, and later deserializes whatever comes
# back in that cookie with pickle.loads() -- with no signature or
# integrity check. An attacker who controls the cookie value can supply a
# malicious pickle payload that executes arbitrary code when the server
# deserializes it.
# ----------------------------------------------------------------------------
@app.route("/cart/save", methods=["POST"])
def save_cart_for_later():
    cart = get_cart()
    serialized = pickle.dumps(cart)  # VULNERABLE: insecure serialization format
    encoded = base64.b64encode(serialized).decode()
    resp = make_response(redirect(url_for("view_cart")))
    resp.set_cookie("saved_cart", encoded)
    flash("Cart saved! It'll be here next time.", "info")
    return resp


@app.route("/cart/restore")
def restore_cart():
    saved = request.cookies.get("saved_cart")
    if saved:
        try:
            raw = base64.b64decode(saved)
            restored = pickle.loads(raw)  # VULNERABLE: untrusted deserialization
            session["cart"] = restored
            flash("Your saved cart has been restored.", "success")
        except Exception as e:
            logger.error("Failed to restore cart: %s", e)
            flash("Could not restore saved cart.", "danger")
    return redirect(url_for("view_cart"))


@app.route("/checkout", methods=["GET", "POST"])
def checkout():
    if "username" not in session:
        flash("Please log in to check out.", "warning")
        return redirect(url_for("login"))

    cart = get_cart()
    if not cart:
        flash("Your cart is empty.", "info")
        return redirect(url_for("view_cart"))

    db = get_db()
    if request.method == "POST":
        address = request.form.get("address", "")
        total = 0.0
        items = []
        for pid, entry in cart.items():
            product = db.execute("SELECT * FROM products WHERE id = ?", (pid,)).fetchone()
            if product:
                total += product["price"] * entry["qty"]
                items.append((product["id"], entry["qty"], product["price"]))

        cur = db.execute(
            "INSERT INTO orders (user_id, total, status, shipping_address) VALUES (?, ?, ?, ?)",
            (session["user_id"], total, "processing", address),
        )
        order_id = cur.lastrowid
        for pid, qty, price in items:
            db.execute(
                "INSERT INTO order_items (order_id, product_id, quantity, price_each) VALUES (?, ?, ?, ?)",
                (order_id, pid, qty, price),
            )
        db.commit()
        session["cart"] = {}
        flash("Order placed! Thank you for shopping with Little Cubs Toy Co.", "success")
        return redirect(url_for("order_confirmation", order_id=order_id))

    items = []
    total = 0.0
    for pid, entry in cart.items():
        product = db.execute("SELECT * FROM products WHERE id = ?", (pid,)).fetchone()
        if product:
            subtotal = product["price"] * entry["qty"]
            total += subtotal
            items.append({"product": product, "qty": entry["qty"], "subtotal": subtotal})

    # VULNERABILITY #17 - CWE-1021 (OWASP A06:2025 Insecure Design)
    # No X-Frame-Options / frame-ancestors CSP is ever set (see
    # add_permissive_headers above), so this checkout page can be embedded
    # in an invisible <iframe> on an attacker's site for a clickjacking
    # attack that tricks the victim into submitting a real order.
    return render_template("checkout.html", items=items, total=total)


@app.route("/order/<int:order_id>/confirmation")
def order_confirmation(order_id):
    db = get_db()
    order = db.execute("SELECT * FROM orders WHERE id = ?", (order_id,)).fetchone()
    order_items = db.execute(
        """SELECT oi.*, p.name, p.image FROM order_items oi
           JOIN products p ON oi.product_id = p.id
           WHERE oi.order_id = ?""",
        (order_id,),
    ).fetchall()
    return render_template("order_confirmation.html", order=order, order_items=order_items)


# ============================================================================
# FILE DOWNLOAD / STATIC ASSET HANDLING
# ============================================================================

# ----------------------------------------------------------------------------
# VULNERABILITY #1 - CWE-22 Path Traversal (OWASP A01:2025 Broken Access Control)
# Endpoint: GET /download/invoice/<filename>
#
# The filename is joined directly onto the server's invoice directory
# without sanitizing "../" sequences, so a request such as:
#     /download/invoice/../../app.py
# or
#     /download/invoice/....//....//....//etc/passwd
# escapes the intended directory and returns arbitrary files readable by
# the application process.
# ----------------------------------------------------------------------------
@app.route("/download/invoice/<path:filename>")
def download_invoice(filename):
    if "username" not in session:
        return redirect(url_for("login"))

    invoices_dir = os.path.join(BASE_DIR, "static", "invoices")
    os.makedirs(invoices_dir, exist_ok=True)

    target_path = os.path.join(invoices_dir, filename)  # VULNERABLE: no path normalization/allowlist

    try:
        with open(target_path, "rb") as f:
            data = f.read()
    except FileNotFoundError:
        return render_template("error.html", error=f"Invoice not found: {filename}", traceback=""), 404
    except Exception as e:
        raise

    resp = make_response(data)
    resp.headers["Content-Type"] = "application/octet-stream"
    resp.headers["Content-Disposition"] = f"attachment; filename={os.path.basename(filename)}"
    return resp


# ----------------------------------------------------------------------------
# VULNERABILITY #3 - CWE-548 Exposure of Information Through Directory Listing
# OWASP A01:2025 Broken Access Control
# Endpoint: GET /uploads/
#
# This route builds and returns a raw directory listing of every file in
# static/uploads (customer-uploaded avatars, etc.) with clickable links,
# equivalent to leaving "Options +Indexes" / autoindex enabled on a web
# server -- no authentication and no allowlist of what may be browsed.
# ----------------------------------------------------------------------------
@app.route("/uploads/")
def list_uploads():
    os.makedirs(UPLOAD_DIR, exist_ok=True)
    files = os.listdir(UPLOAD_DIR)
    return render_template("directory_listing.html", files=files, dir_name="uploads")


@app.route("/uploads/<path:filename>")
def serve_upload(filename):
    path = os.path.join(UPLOAD_DIR, filename)
    if os.path.isfile(path):
        with open(path, "rb") as f:
            return Response(f.read())
    return "Not found", 404


# ============================================================================
# ADMIN PANEL
# ============================================================================

def require_admin():
    return session.get("is_admin", False)


@app.route("/admin")
def admin_dashboard():
    if not require_admin():
        flash("Admin access required.", "danger")
        return redirect(url_for("login"))
    db = get_db()
    stats = {
        "total_products": db.execute("SELECT COUNT(*) c FROM products").fetchone()["c"],
        "total_users": db.execute("SELECT COUNT(*) c FROM users").fetchone()["c"],
        "total_orders": db.execute("SELECT COUNT(*) c FROM orders").fetchone()["c"],
        "total_reviews": db.execute("SELECT COUNT(*) c FROM reviews").fetchone()["c"],
    }
    suppliers = db.execute("SELECT * FROM suppliers").fetchall()
    users = db.execute("SELECT * FROM users").fetchall()
    return render_template("admin_dashboard.html", stats=stats, suppliers=suppliers, users=users)


# ----------------------------------------------------------------------------
# VULNERABILITY #4 - CWE-918 Server-Side Request Forgery (OWASP A01:2025)
# Endpoint: POST /admin/fetch-image-preview
#
# The "import product photo from URL" admin convenience feature makes a
# server-side HTTP request to whatever URL the admin (or an attacker who
# reaches this endpoint via the access-control flaw in #16) supplies, with
# no allowlist of hosts/schemes and no block on internal/metadata
# addresses -- classic SSRF that can be used to reach internal services
# such as a cloud metadata endpoint (169.254.169.254) or other internal
# hosts not otherwise reachable from the internet.
# ----------------------------------------------------------------------------
@app.route("/admin/fetch-image-preview", methods=["GET", "POST"])
def fetch_image_preview():
    if not require_admin():
        flash("Admin access required.", "danger")
        return redirect(url_for("login"))

    preview = None
    error = None
    if request.method == "POST":
        image_url = request.form.get("image_url", "")
        try:
            # VULNERABLE: server-side fetch of an arbitrary, unvalidated URL
            r = requests.get(image_url, timeout=5)
            preview = {
                "url": image_url,
                "status_code": r.status_code,
                "content_type": r.headers.get("Content-Type", ""),
                "body_snippet": r.text[:2000],
            }
        except Exception as e:
            error = str(e)

    return render_template("admin_fetch_preview.html", preview=preview, error=error)


# ----------------------------------------------------------------------------
# VULNERABILITY #12 - CWE-77 Command Injection (OWASP A05:2025 Injection)
# Endpoint: POST /admin/ping-supplier
#
# The admin "check supplier connectivity" tool builds a shell command by
# concatenating the user-controlled hostname directly into an os.system()
# call. Shell metacharacters (e.g. `; cat /etc/passwd`, `$(whoami)`) are
# not filtered, allowing arbitrary OS command execution as the app user.
# ----------------------------------------------------------------------------
@app.route("/admin/ping-supplier", methods=["GET", "POST"])
def ping_supplier():
    if not require_admin():
        flash("Admin access required.", "danger")
        return redirect(url_for("login"))

    db = get_db()
    suppliers = db.execute("SELECT * FROM suppliers").fetchall()
    output = None
    hostname = ""
    if request.method == "POST":
        hostname = request.form.get("hostname", "")
        # VULNERABLE: unsanitized string concatenation into a shell command
        command = "ping -c 1 " + hostname
        try:
            output = subprocess.check_output(
                command, shell=True, stderr=subprocess.STDOUT, timeout=10
            ).decode(errors="replace")
        except subprocess.CalledProcessError as e:
            output = e.output.decode(errors="replace")
        except Exception as e:
            output = f"Error: {e}"

    return render_template("admin_ping.html", suppliers=suppliers, output=output, hostname=hostname)


@app.route("/admin/product/new", methods=["GET", "POST"])
def admin_new_product():
    if not require_admin():
        flash("Admin access required.", "danger")
        return redirect(url_for("login"))

    db = get_db()
    categories = db.execute("SELECT * FROM categories").fetchall()

    if request.method == "POST":
        name = request.form.get("name", "")
        slug = name.lower().replace(" ", "-")
        category_id = request.form.get("category_id")
        price = request.form.get("price", "0")
        material = request.form.get("material", "")
        age_range = request.form.get("age_range", "")
        description = request.form.get("description", "")
        image = request.form.get("image", "teddy-bear-classic.jpg")

        db.execute(
            """INSERT INTO products (name, slug, category_id, price, material, age_range, description, image)
               VALUES (?, ?, ?, ?, ?, ?, ?, ?)""",
            (name, slug, category_id, price, material, age_range, description, image),
        )
        db.commit()
        flash(f'Product "{name}" created.', "success")
        return redirect(url_for("admin_dashboard"))

    return render_template("admin_new_product.html", categories=categories)


# ----------------------------------------------------------------------------
# VULNERABILITY #7 (endpoint) - CWE-526 Env Var Exposure (OWASP A01:2025)
# Endpoint: GET /debug/env
#
# Intended as an internal diagnostics page, this endpoint has no
# authentication check at all and dumps the full process environment,
# including the fake Stripe secret key, DB admin password, SMTP password
# and the admin login backdoor value defined above.
# ----------------------------------------------------------------------------
@app.route("/debug/env")
def debug_env():
    # VULNERABLE: no auth check, dumps full environment to any visitor
    env_dump = dict(os.environ)
    return render_template("debug_env.html", env=env_dump)


@app.route("/admin/system-info")
def admin_system_info():
    # Companion "system info" panel -- also unauthenticated, reinforcing #7.
    import sys
    info = {
        "python_version": sys.version,
        "flask_debug": app.config.get("DEBUG"),
        "db_path": DB_PATH,
        "secret_key": app.config.get("SECRET_KEY"),
        "cwd": os.getcwd(),
        "environment_variables": dict(os.environ),
    }
    return render_template("debug_env.html", env=info["environment_variables"], extra=info)


# ============================================================================
# NEWSLETTER (simple additional feature)
# ============================================================================

@app.route("/newsletter/subscribe", methods=["POST"])
def newsletter_subscribe():
    email = request.form.get("email", "")
    db = get_db()
    db.execute("INSERT INTO newsletter_subscribers (email) VALUES (?)", (email,))
    db.commit()
    flash("You're subscribed! We'll send you cuddly new arrivals.", "success")
    return redirect(request.referrer or url_for("home"))


# ============================================================================
# DEMO: trigger the global exception handler (for lab verification of #25)
# ============================================================================
@app.route("/trigger-error")
def trigger_error():
    # Intentionally raises to demonstrate CWE-755 verbose error handling.
    raise RuntimeError(
        f"Demo unhandled exception triggered by {request.remote_addr} for lab verification"
    )


if __name__ == "__main__":
    if not os.path.exists(DB_PATH):
        print("Database not found -- run `python3 init_db.py` first.")
    app.run(host="0.0.0.0", port=5000, debug=True)
