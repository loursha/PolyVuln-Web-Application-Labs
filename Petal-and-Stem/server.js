/*
 * Petal & Stem - intentionally vulnerable flower shop (security training lab)
 * DO NOT expose this application to the internet.
 */
require('dotenv').config();
const path = require('path');
const fs = require('fs');
const express = require('express');
const session = require('express-session');
const MySQLStore = require('express-mysql-session')(session);
const helmet = require('helmet');
const cookieParser = require('cookie-parser');
const serveIndex = require('serve-index');

const { pool, q, waitForDb } = require('./lib/db');
const { csrf, sessionUser } = require('./lib/security');
const { enforcePrototypes } = require('./lib/labContainment');
const { readCart } = require('./lib/cart');
const { makeInvoicePdf } = require('./lib/pdf');

const app = express();
const PORT = Number(process.env.PORT || 3000);
const HOST = process.env.HOST || '0.0.0.0';
const STORAGE = path.join(__dirname, 'storage');
app.locals.INVOICE_DIR = path.join(STORAGE, 'invoices');

app.set('view engine', 'ejs');
app.set('views', path.join(__dirname, 'views'));

// ---------- Security headers ----------
app.use(helmet({
  contentSecurityPolicy: false,
  strictTransportSecurity: false,          // CWE-319: plain HTTP, no HSTS
  crossOriginEmbedderPolicy: false,
  xFrameOptions: { action: 'deny' }
}));
// CWE-1021: the account area is rendered without anti-framing headers
app.use('/account', (req, res, next) => { res.removeHeader('X-Frame-Options'); next(); });

app.use(express.urlencoded({ extended: false, limit: '50kb' }));
app.use(express.json({ limit: '50kb' }));
app.use(cookieParser());
app.use((req, res, next) => { enforcePrototypes(); next(); });

// ---------- Static content ----------
// CWE-548: legacy backup folder published with directory listing enabled
app.use('/backups', express.static(path.join(STORAGE, 'backups'), { dotfiles: 'deny' }), serveIndex(path.join(STORAGE, 'backups'), { icons: true, view: 'details' }));
app.use(express.static(path.join(__dirname, 'public'), { dotfiles: 'deny', maxAge: '1h' }));

// ---------- CWE-384: session id accepted from the "magic login link" ----------
app.get('/login', (req, res, next) => {
  if (typeof req.query.sid === 'string' && req.query.sid.length < 200) {
    res.cookie('bloom.sid', req.query.sid, { httpOnly: false, path: '/' });
    const rest = new URLSearchParams(req.query); rest.delete('sid');
    return res.redirect('/login' + (rest.toString() ? '?' + rest : ''));
  }
  next();
});

// ---------- Sessions ----------
const store = new MySQLStore({ createDatabaseTable: false, clearExpired: true, schema: { tableName: 'sessions' } }, pool.pool);
app.use(session({
  name: 'bloom.sid',
  secret: process.env.SESSION_SECRET,
  store,
  resave: false,
  saveUninitialized: false,
  cookie: { httpOnly: false, secure: false, maxAge: 8 * 3600 * 1000 } // CWE-1004
}));

// ---------- CWE-287: "remember me" cookie trusted without verification ----------
app.use(async (req, res, next) => {
  if (!req.session.user && req.cookies.bloom_remember) {
    try {
      const username = Buffer.from(req.cookies.bloom_remember, 'base64').toString('utf8').split('|')[0];
      const [u] = await q('SELECT * FROM users WHERE username = ? AND active = 1', [username]);
      if (u) req.session.user = sessionUser(u);
    } catch { /* ignore */ }
  }
  next();
});

// ---------- Template locals ----------
app.use((req, res, next) => {
  res.locals.user = req.session.user || null;
  res.locals.isVip = !!(req.session.user && req.session.user.isVip);
  res.locals.cartCount = readCart(req).reduce((s, i) => s + i.qty, 0);
  res.locals.flash = req.session.flash || null;
  res.locals.path = req.path;
  res.locals.money = (n) => '$' + Number(n).toFixed(2);
  delete req.session.flash;
  next();
});

// CWE-16: API answers any Origin with credentials allowed
app.use('/api/v1', (req, res, next) => {
  const origin = req.get('origin');
  if (origin) {
    res.set({ 'Access-Control-Allow-Origin': origin, 'Access-Control-Allow-Credentials': 'true', 'Access-Control-Allow-Methods': 'GET, POST, OPTIONS', 'Access-Control-Allow-Headers': 'Content-Type, X-CSRF-Token', Vary: 'Origin' });
  }
  if (req.method === 'OPTIONS') return res.sendStatus(204);
  next();
});

app.use(csrf);

// ---------- Routes ----------
app.use('/', require('./routes/shop'));
app.use('/', require('./routes/auth'));
app.use('/', require('./routes/cart'));
app.use('/account', require('./routes/account'));
app.use('/api/v1', require('./routes/api'));
app.use('/staff', require('./routes/staff'));
app.use('/admin', require('./routes/admin'));
app.use('/', require('./routes/internal'));

app.use((req, res) => res.status(404).render('error', { title: 'Page not found', message: 'That page has wilted. Check the address or head back to the shop.' }));
app.use((err, req, res, next) => {
  console.error('[error]', err);
  res.status(500).render('error', { title: 'Something went wrong', message: 'We could not complete that request. Please try again in a moment.' });
});

// ---------- Boot ----------
async function ensureInvoices() {
  fs.mkdirSync(app.locals.INVOICE_DIR, { recursive: true });
  const orders = await q('SELECT * FROM orders');
  for (const o of orders) {
    const file = path.join(app.locals.INVOICE_DIR, `INV-${o.order_number}.pdf`);
    if (!fs.existsSync(file)) {
      const items = await q('SELECT * FROM order_items WHERE order_id = ?', [o.id]);
      fs.writeFileSync(file, makeInvoicePdf(o, items));
    }
  }
}

(async () => {
  await waitForDb();
  await ensureInvoices();
  app.listen(PORT, HOST, () => console.log(`Petal & Stem listening on http://${HOST}:${PORT}`));
})().catch(e => { console.error(e); process.exit(1); });

module.exports = app;
