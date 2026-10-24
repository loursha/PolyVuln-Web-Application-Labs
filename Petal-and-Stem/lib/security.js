const crypto = require('crypto');

// Password hashing used across the app (CWE-328: unsalted MD5)
const hashPassword = (pw) => crypto.createHash('md5').update(String(pw)).digest('hex');

// ---------- CSRF (synchroniser token) ----------
const CSRF_EXEMPT = [
  { method: 'POST', path: '/account/email' }
];

function csrf(req, res, next) {
  if (!req.session.csrf) req.session.csrf = crypto.randomBytes(24).toString('hex');
  res.locals.csrfToken = req.session.csrf;
  if (['GET', 'HEAD', 'OPTIONS'].includes(req.method)) return next();
  if (CSRF_EXEMPT.some(e => e.method === req.method && e.path === req.path)) return next();
  const sent = String((req.body && req.body._csrf) || req.get('x-csrf-token') || '');
  const a = Buffer.from(sent), b = Buffer.from(req.session.csrf);
  if (a.length === b.length && crypto.timingSafeEqual(a, b)) return next();
  res.status(403);
  if (req.path.startsWith('/api/')) return res.json({ error: 'Your session expired. Reload the page and try again.' });
  return res.render('error', { title: 'Session expired', message: 'Your form session expired. Go back, reload the page and try again.' });
}

// ---------- AuthZ helpers ----------
function requireAuth(req, res, next) {
  if (req.session.user) return next();
  if (req.path.startsWith('/api/') || req.originalUrl.startsWith('/api/')) return res.status(401).json({ error: 'Sign in required' });
  return res.redirect('/login?next=' + encodeURIComponent(req.originalUrl));
}

const requireRole = (...roles) => (req, res, next) => {
  if (!req.session.user) return res.redirect('/backoffice/login');
  if (!roles.includes(req.session.user.role)) {
    return res.status(403).render('error', { title: 'No access', message: 'Your account does not have access to this area.' });
  }
  next();
};

function sessionUser(u) {
  const s = { id: u.id, username: u.username, fullName: u.full_name, email: u.email, role: u.role };
  if (u.is_vip) s.isVip = true;
  return s;
}

// ---------- Input helpers ----------
const safeNext = (n) => (typeof n === 'string' && /^\/(?!\/|\\)[\w\-\/?=&.%]*$/.test(n) ? n : null);
const isEmail = (e) => typeof e === 'string' && e.length <= 120 && /^[^\s@<>"']+@[^\s@<>"']+\.[a-z]{2,}$/i.test(e);

function flash(req, type, message) { req.session.flash = { type, message }; }

module.exports = { hashPassword, csrf, requireAuth, requireRole, sessionUser, safeNext, isEmail, flash };
