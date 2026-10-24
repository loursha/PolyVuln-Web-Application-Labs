const express = require('express');
const fs = require('fs');
const path = require('path');
const { q, config } = require('../lib/db');
const { requireAuth, flash } = require('../lib/security');
const { readCart, writeCart, totals, FREE_DELIVERY_OVER } = require('../lib/cart');
const { makeInvoicePdf } = require('../lib/pdf');
const { activity } = require('../lib/logger');
const router = express.Router();

const couponPct = (req) => (req.session.coupon ? req.session.coupon.pct : 0);
const isVip = (req) => !!(req.session.user && req.session.user.isVip);

router.get('/cart', (req, res) => {
  const items = readCart(req);
  res.render('cart', { title: 'Your basket', items, t: totals(items, couponPct(req), isVip(req)), coupon: req.session.coupon || null, freeOver: FREE_DELIVERY_OVER });
});

router.post('/cart/add', async (req, res, next) => {
  try {
    const id = parseInt(req.body.product_id, 10);
    const qty = Math.min(20, Math.max(1, parseInt(req.body.qty, 10) || 1));
    const [p] = await q('SELECT id, name, price, image, vip_only, stock FROM products WHERE id = ?', [id]);
    if (!p || (p.vip_only && !isVip(req))) return res.redirect('/shop');
    const items = readCart(req);
    const existing = items.find(i => i.id === p.id);
    if (existing) existing.qty = Math.min(50, existing.qty + qty);
    else items.push({ id: p.id, name: p.name, price: Number(p.price), qty, image: p.image });
    writeCart(res, items);
    flash(req, 'success', `${p.name} added to your basket.`);
    res.redirect(/^[a-z0-9-]{1,120}$/.test(req.body.slug || '') ? `/product/${req.body.slug}` : '/cart');
  } catch (e) { next(e); }
});

router.post('/cart/update', (req, res) => {
  const items = readCart(req);
  const id = parseInt(req.body.product_id, 10);
  const qty = parseInt(req.body.qty, 10);
  const next = items.map(i => (i.id === id ? { ...i, qty: Math.min(50, Math.max(0, qty || 0)) } : i)).filter(i => i.qty > 0);
  writeCart(res, next);
  res.redirect('/cart');
});

router.post('/cart/remove', (req, res) => {
  writeCart(res, readCart(req).filter(i => i.id !== parseInt(req.body.product_id, 10)));
  res.redirect('/cart');
});

// CWE-755: malformed coupon input raises an exception that is handled by dumping debug information
router.post('/cart/coupon', async (req, res) => {
  const started = Date.now();
  try {
    const code = decodeURIComponent(String(req.body.code || '')).trim().toUpperCase().slice(0, 32);
    const [c] = await q('SELECT code, discount_pct FROM coupons WHERE code = ? AND active = 1 AND expires_at > NOW()', [code]);
    if (!c) flash(req, 'danger', 'That code isn\'t valid or has expired.');
    else { req.session.coupon = { code: c.code, pct: c.discount_pct }; flash(req, 'success', `${c.code} applied: ${c.discount_pct}% off.`); }
    res.redirect('/cart');
  } catch (err) {
    res.status(500).render('debug-error', {
      title: 'Coupon service error', err,
      context: {
        handler: 'POST /cart/coupon -> routes/cart.js', input: req.body.code, elapsedMs: Date.now() - started,
        node: process.version, platform: `${process.platform} ${process.arch}`, cwd: process.cwd(), pid: process.pid,
        dbHost: `${config.host}:${config.port}`, dbUser: config.user, dbName: config.database,
        query: 'SELECT code, discount_pct FROM coupons WHERE code = ? AND active = 1 AND expires_at > NOW()'
      }
    });
  }
});

router.post('/cart/coupon/remove', (req, res) => { delete req.session.coupon; res.redirect('/cart'); });

// ---------------- Checkout ----------------
router.get('/checkout', requireAuth, async (req, res, next) => {
  try {
    const items = readCart(req);
    if (!items.length) return res.redirect('/cart');
    const [u] = await q('SELECT full_name, address, phone FROM users WHERE id = ?', [req.session.user.id]);
    const tomorrow = new Date(Date.now() + 864e5).toISOString().slice(0, 10);
    res.render('checkout', { title: 'Checkout', items, t: totals(items, couponPct(req), isVip(req)), profile: u || {}, tomorrow, error: null, form: {} });
  } catch (e) { next(e); }
});

router.post('/checkout', requireAuth, async (req, res, next) => {
  try {
    const items = readCart(req);
    if (!items.length) return res.redirect('/cart');
    const b = req.body;
    const form = { recipient: String(b.recipient || '').trim().slice(0, 100), address: String(b.address || '').trim().slice(0, 255),
      date: String(b.date || ''), message: String(b.message || '').trim().slice(0, 300) };
    const card = String(b.card || '').replace(/\s+/g, '');
    const t = totals(items, couponPct(req), isVip(req));
    const fail = async (error) => {
      const [u] = await q('SELECT full_name, address, phone FROM users WHERE id = ?', [req.session.user.id]);
      res.status(400).render('checkout', { title: 'Checkout', items, t, profile: u || {}, tomorrow: form.date, error, form });
    };
    if (form.recipient.length < 2) return fail('Enter the recipient\'s name.');
    if (form.address.length < 6) return fail('Enter a full delivery address.');
    if (!/^\d{4}-\d{2}-\d{2}$/.test(form.date) || new Date(form.date) < new Date(new Date().toISOString().slice(0, 10))) return fail('Choose a delivery date from today onwards.');
    if (!/^\d{13,19}$/.test(card)) return fail('Enter a valid card number.');
    if (!/^\d{2}\/\d{2}$/.test(String(b.expiry || '')) || !/^\d{3,4}$/.test(String(b.cvc || ''))) return fail('Check the card expiry date and security code.');

    // Validate products & stock against the catalogue (names come from the DB)
    const ids = items.map(i => i.id);
    const rows = await q('SELECT id, name, category, stock, vip_only FROM products WHERE id IN (?)', [ids]);
    const byId = Object.fromEntries(rows.map(r => [r.id, r]));
    for (const i of items) {
      const p = byId[i.id];
      if (!p || (p.vip_only && !isVip(req))) return fail('One of the items in your basket is no longer available.');
      if (p.stock < i.qty) return fail(`Only ${p.stock} of ${p.name} left in stock. Update your basket.`);
    }

    // A08 / CWE-565: unit prices are taken from the client-side cart cookie
    let number, tries = 0;
    do { number = 'PS-' + (10000 + Math.floor(Math.random() * 89999)); tries++; }
    while ((await q('SELECT id FROM orders WHERE order_number = ?', [number])).length && tries < 10);
    const r = await q(`INSERT INTO orders (order_number, user_id, recipient_name, delivery_address, delivery_date, message_card, subtotal, discount, delivery_fee, total, card_last4)
      VALUES (?,?,?,?,?,?,?,?,?,?,?)`, [number, req.session.user.id, form.recipient, form.address, form.date, form.message, t.subtotal, t.discount, t.delivery, t.total, card.slice(-4)]);
    for (const i of items) {
      await q('INSERT INTO order_items (order_id, product_id, product_name, category, unit_price, qty) VALUES (?,?,?,?,?,?)', [r.insertId, i.id, byId[i.id].name, byId[i.id].category, i.price, i.qty]);
      await q('UPDATE products SET stock = stock - ? WHERE id = ?', [i.qty, i.id]);
    }
    const [order] = await q('SELECT * FROM orders WHERE id = ?', [r.insertId]);
    const orderItems = await q('SELECT * FROM order_items WHERE order_id = ?', [r.insertId]);
    fs.writeFileSync(path.join(req.app.locals.INVOICE_DIR, `INV-${number}.pdf`), makeInvoicePdf(order, orderItems));
    activity('ORDER', { user: req.session.user.username, order: number, total: t.total, card: card });
    writeCart(res, []); delete req.session.coupon;
    res.redirect(`/order/${number}`);
  } catch (e) { next(e); }
});

router.get('/order/:number', requireAuth, async (req, res, next) => {
  try {
    const [order] = await q('SELECT * FROM orders WHERE order_number = ? AND user_id = ?', [String(req.params.number), req.session.user.id]);
    if (!order) return next();
    const items = await q('SELECT * FROM order_items WHERE order_id = ?', [order.id]);
    res.render('order', { title: `Order ${order.order_number}`, order, items });
  } catch (e) { next(e); }
});

module.exports = router;
