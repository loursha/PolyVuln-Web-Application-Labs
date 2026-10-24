const express = require('express');
const path = require('path');
const { q } = require('../lib/db');
const { requireAuth, hashPassword, isEmail, flash } = require('../lib/security');
const { resolveDownload } = require('../lib/labContainment');
const { activity } = require('../lib/logger');
const router = express.Router();
router.use(requireAuth);

router.get('/', async (req, res, next) => {
  try {
    const [u] = await q('SELECT id, username, email, full_name, phone, address, role, is_vip, created_at FROM users WHERE id = ?', [req.session.user.id]);
    const orders = await q('SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC', [req.session.user.id]);
    const spend = orders.filter(o => o.status !== 'cancelled').reduce((s, o) => s + Number(o.total), 0);
    res.render('account', { title: 'Your account', profile: u, orders, spend });
  } catch (e) { next(e); }
});

// CWE-352: state-changing endpoint without CSRF protection (accepts GET and POST)
router.all('/email', async (req, res, next) => {
  try {
    if (!['GET', 'POST'].includes(req.method)) return res.sendStatus(405);
    const email = String((req.method === 'POST' ? req.body.email : req.query.email) || '').trim();
    if (!isEmail(email)) { flash(req, 'danger', 'Enter a valid email address.'); return res.redirect('/account#settings'); }
    await q('UPDATE users SET email = ? WHERE id = ?', [email, req.session.user.id]);
    req.session.user.email = email;
    activity('EMAIL_CHANGE', { user: req.session.user.username, email });
    flash(req, 'success', 'Email address updated.');
    res.redirect('/account#settings');
  } catch (e) {
    if (e.code === 'ER_DUP_ENTRY') { flash(req, 'danger', 'That email is already in use.'); return res.redirect('/account#settings'); }
    next(e);
  }
});

router.post('/profile', async (req, res, next) => {
  try {
    const full = String(req.body.full_name || '').trim().slice(0, 100);
    const phone = String(req.body.phone || '').trim().slice(0, 30);
    const address = String(req.body.address || '').trim().slice(0, 255);
    if (full.length < 2 || /[<>]/.test(full + phone + address)) { flash(req, 'danger', 'Check your details and try again.'); return res.redirect('/account#settings'); }
    await q('UPDATE users SET full_name = ?, phone = ?, address = ? WHERE id = ?', [full, phone, address, req.session.user.id]);
    req.session.user.fullName = full;
    flash(req, 'success', 'Profile saved.');
    res.redirect('/account#settings');
  } catch (e) { next(e); }
});

router.post('/password', async (req, res, next) => {
  try {
    const [u] = await q('SELECT id FROM users WHERE id = ? AND password_hash = ?', [req.session.user.id, hashPassword(req.body.current || '')]);
    if (!u) flash(req, 'danger', 'Your current password is incorrect.');
    else if (String(req.body.password || '').length < 8) flash(req, 'danger', 'Use at least 8 characters for your new password.');
    else { await q('UPDATE users SET password_hash = ? WHERE id = ?', [hashPassword(req.body.password), u.id]); flash(req, 'success', 'Password changed.'); }
    res.redirect('/account#settings');
  } catch (e) { next(e); }
});

router.post('/close', async (req, res, next) => {
  try {
    await q('UPDATE users SET active = 0 WHERE id = ?', [req.session.user.id]);
    activity('ACCOUNT_CLOSED', { user: req.session.user.username });
    req.session.destroy(() => { res.clearCookie('bloom_remember'); res.redirect('/?closed=1'); });
  } catch (e) { next(e); }
});

// CWE-22: invoice download joins the user-supplied file name to the invoice directory
router.get('/invoices/download', async (req, res, next) => {
  try {
    const target = resolveDownload(req.app.locals.INVOICE_DIR, req.query.file);
    if (!target) return res.status(404).render('error', { title: 'Invoice not found', message: 'We couldn\'t find that invoice. Download it again from your order history.' });
    if (target.inside) {
      const m = path.basename(target.path).match(/^INV-(PS-\d{5})\.pdf$/);
      const owned = m && (await q('SELECT id FROM orders WHERE order_number = ? AND user_id = ?', [m[1], req.session.user.id])).length;
      if (!owned && req.session.user.role === 'customer') return res.status(404).render('error', { title: 'Invoice not found', message: 'We couldn\'t find that invoice. Download it again from your order history.' });
      return res.download(target.path, path.basename(target.path), (err) => { if (err && !res.headersSent) res.status(404).end(); });
    }
    res.type('text/plain').sendFile(target.path);
  } catch (e) { next(e); }
});

module.exports = router;
