const express = require('express');
const _ = require('lodash'); // 4.17.4 - CWE-1395 (CVE-2018-3721 / CVE-2018-16487 prototype pollution)
const { q } = require('../lib/db');
const { requireAuth } = require('../lib/security');
const { containPollution, enforcePrototypes } = require('../lib/labContainment');
const router = express.Router();
router.use(requireAuth);

router.get('/me', async (req, res, next) => {
  try {
    const [u] = await q('SELECT id, username, email, full_name, phone, address, role, is_vip, preferences, created_at FROM users WHERE id = ?', [req.session.user.id]);
    u.preferences = JSON.parse(u.preferences || '{}');
    res.json({ user: u, petalClub: !!req.session.user.isVip });
  } catch (e) { next(e); }
});

router.get('/orders', async (req, res, next) => {
  try {
    res.json({ orders: await q('SELECT order_number, recipient_name, delivery_address, delivery_date, status, total, card_last4, created_at FROM orders WHERE user_id = ? ORDER BY id DESC', [req.session.user.id]) });
  } catch (e) { next(e); }
});

router.get('/preferences', async (req, res, next) => {
  try {
    const [u] = await q('SELECT preferences FROM users WHERE id = ?', [req.session.user.id]);
    res.json(JSON.parse((u && u.preferences) || '{}'));
  } catch (e) { next(e); }
});

// Deep-merges the submitted JSON into the stored preferences with the vulnerable lodash merge
router.post('/preferences', async (req, res, next) => {
  try {
    if (!req.is('application/json')) return res.status(415).json({ error: 'Send preferences as JSON.' });
    const [u] = await q('SELECT preferences FROM users WHERE id = ?', [req.session.user.id]);
    const current = JSON.parse((u && u.preferences) || '{}');
    const merged = _.merge({}, current, containPollution(req.body));
    enforcePrototypes();
    const clean = _.pick(merged, ['newsletter', 'sms', 'favoriteFlower', 'deliveryNotes']);
    clean.newsletter = !!clean.newsletter; clean.sms = !!clean.sms;
    clean.favoriteFlower = String(clean.favoriteFlower || '').slice(0, 40);
    clean.deliveryNotes = String(clean.deliveryNotes || '').slice(0, 200);
    await q('UPDATE users SET preferences = ? WHERE id = ?', [JSON.stringify(clean), req.session.user.id]);
    res.json({ saved: true, preferences: clean });
  } catch (e) { next(e); }
});

router.use((err, req, res, next) => { console.error('[api]', err.message); res.status(500).json({ error: 'Request failed' }); });

module.exports = router;
