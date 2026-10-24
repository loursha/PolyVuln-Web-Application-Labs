const express = require('express');
const crypto = require('crypto');
const rateLimit = require('express-rate-limit');
const { q } = require('../lib/db');
const { hashPassword, sessionUser, safeNext, isEmail, flash } = require('../lib/security');
const { activity } = require('../lib/logger');
const router = express.Router();

const customerLoginLimiter = rateLimit({
  windowMs: 15 * 60 * 1000, limit: 20, standardHeaders: true, legacyHeaders: false,
  handler: (req, res) => res.status(429).render('login', { title: 'Sign in', error: 'Too many sign-in attempts. Wait 15 minutes and try again.', next: '', username: '' })
});

// ---------------- Customer sign in ----------------
router.get('/login', (req, res) => {
  if (req.session.user) return res.redirect('/account');
  res.render('login', { title: 'Sign in', error: null, next: safeNext(req.query.next) || '', username: '' });
});

router.post('/login', customerLoginLimiter, async (req, res, next) => {
  try {
    const username = String(req.body.username || '').trim().slice(0, 40);
    const password = String(req.body.password || '');
    const [u] = await q('SELECT * FROM users WHERE username = ? AND password_hash = ? AND active = 1', [username, hashPassword(password)]);
    // CWE-532: credentials written to the activity log
    activity('LOGIN', { portal: 'storefront', user: username, password, result: u ? 'success' : 'failure', ip: req.ip });
    if (!u) return res.status(401).render('login', { title: 'Sign in', error: 'That username and password don\'t match. Try again or reset your password.', next: safeNext(req.body.next) || '', username });

    // CWE-384: the pre-login session id is kept after authentication
    req.session.user = sessionUser(u);
    if (req.body.remember === 'on') {
      // CWE-287: unsigned remember-me token (base64 "username|timestamp")
      res.cookie('bloom_remember', Buffer.from(`${u.username}|${Date.now()}`).toString('base64'), { maxAge: 30 * 864e5, httpOnly: true, sameSite: 'lax' });
    }
    flash(req, 'success', `Welcome back, ${u.full_name.split(' ')[0]}.`);
    res.redirect(safeNext(req.body.next) || (u.role === 'customer' ? '/account' : `/${u.role === 'admin' ? 'admin' : 'staff'}`));
  } catch (e) { next(e); }
});

// ---------------- Back-office sign in (CWE-308 / no lockout) ----------------
router.get('/backoffice/login', (req, res) => res.render('backoffice-login', { title: 'Back office', error: null, username: '' }));

router.post('/backoffice/login', async (req, res, next) => {
  try {
    const username = String(req.body.username || '').trim().slice(0, 40);
    const password = String(req.body.password || '');
    const [u] = await q("SELECT * FROM users WHERE username = ? AND password_hash = ? AND active = 1 AND role IN ('staff','admin')", [username, hashPassword(password)]);
    activity('LOGIN', { portal: 'backoffice', user: username, password, result: u ? 'success' : 'failure', ip: req.ip });
    if (!u) return res.status(401).render('backoffice-login', { title: 'Back office', error: 'Incorrect username or password.', username });
    req.session.regenerate(err => {
      if (err) return next(err);
      req.session.user = sessionUser(u);
      res.redirect(u.role === 'admin' ? '/admin' : '/staff');
    });
  } catch (e) { next(e); }
});

router.post('/logout', (req, res) => {
  const who = req.session.user && req.session.user.username;
  req.session.destroy(() => {
    res.clearCookie('bloom.sid'); res.clearCookie('bloom_remember');
    if (who) activity('LOGOUT', { user: who });
    res.redirect('/');
  });
});

// ---------------- Registration (CWE-266) ----------------
router.get('/register', (req, res) => res.render('register', { title: 'Create an account', error: null, form: {} }));

router.post('/register', async (req, res, next) => {
  const form = { username: String(req.body.username || '').trim(), email: String(req.body.email || '').trim(), full_name: String(req.body.full_name || '').trim() };
  const fail = (error) => res.status(400).render('register', { title: 'Create an account', error, form });
  try {
    const password = String(req.body.password || '');
    if (!/^[a-zA-Z0-9_]{3,30}$/.test(form.username)) return fail('Usernames use 3–30 letters, numbers or underscores.');
    if (!isEmail(form.email)) return fail('Enter a valid email address.');
    if (form.full_name.length < 2 || form.full_name.length > 100 || /[<>]/.test(form.full_name)) return fail('Enter your full name.');
    if (password.length < 8) return fail('Use at least 8 characters for your password.');
    if (password !== req.body.confirm) return fail('The two passwords don\'t match.');
    const role = ['customer', 'staff', 'admin'].includes(req.body.role) ? req.body.role : 'customer';
    const r = await q('INSERT INTO users (username, email, password_hash, full_name, role, preferences) VALUES (?,?,?,?,?,?)',
      [form.username, form.email, hashPassword(password), form.full_name, role, JSON.stringify({ newsletter: true, sms: false, favoriteFlower: '' })]);
    activity('REGISTER', { user: form.username, role, ip: req.ip });
    const [u] = await q('SELECT * FROM users WHERE id = ?', [r.insertId]);
    req.session.regenerate(err => {
      if (err) return next(err);
      req.session.user = sessionUser(u);
      flash(req, 'success', 'Your account is ready. Welcome to Petal & Stem!');
      res.redirect('/account');
    });
  } catch (e) {
    if (e.code === 'ER_DUP_ENTRY') return fail('That username or email is already registered.');
    next(e);
  }
});

// ---------------- Password recovery (CWE-640) ----------------
router.get('/forgot', (req, res) => res.render('forgot', { title: 'Reset your password', step: 'request', username: '', error: null }));

router.post('/forgot', async (req, res, next) => {
  try {
    const id = String(req.body.identifier || '').trim().slice(0, 120);
    const [u] = await q('SELECT id, username, email FROM users WHERE (username = ? OR email = ?) AND active = 1', [id, id]);
    if (u) {
      const code = String(crypto.randomInt(0, 10000)).padStart(4, '0');
      await q('UPDATE password_resets SET used = 1 WHERE user_id = ?', [u.id]);
      await q('INSERT INTO password_resets (user_id, code, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 30 MINUTE))', [u.id, code]);
      console.log(`[mail] To: ${u.email} | Subject: Your Petal & Stem reset code | Body: Your code is ${code}`);
    }
    res.render('forgot', { title: 'Reset your password', step: 'verify', username: u ? u.username : id, error: null });
  } catch (e) { next(e); }
});

router.post('/forgot/verify', async (req, res, next) => {
  try {
    const username = String(req.body.username || '').trim().slice(0, 120);
    const code = String(req.body.code || '').trim();
    const password = String(req.body.password || '');
    const again = (error) => res.status(400).render('forgot', { title: 'Reset your password', step: 'verify', username, error });
    if (password.length < 8) return again('Use at least 8 characters for your new password.');
    const [row] = await q(`SELECT pr.id, pr.user_id FROM password_resets pr JOIN users u ON u.id = pr.user_id
      WHERE (u.username = ? OR u.email = ?) AND pr.code = ? AND pr.used = 0 AND pr.expires_at > NOW() ORDER BY pr.id DESC LIMIT 1`, [username, username, code]);
    if (!row) return again('That code is incorrect or has expired.');
    await q('UPDATE users SET password_hash = ? WHERE id = ?', [hashPassword(password), row.user_id]);
    await q('UPDATE password_resets SET used = 1 WHERE user_id = ?', [row.user_id]);
    activity('PASSWORD_RESET', { user: username, ip: req.ip });
    flash(req, 'success', 'Password updated. Sign in with your new password.');
    res.redirect('/login');
  } catch (e) { next(e); }
});

module.exports = router;
