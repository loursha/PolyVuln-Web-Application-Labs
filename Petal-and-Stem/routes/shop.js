const express = require('express');
const { q } = require('../lib/db');
const { requireAuth, flash } = require('../lib/security');
const { xssBlocked } = require('../lib/labContainment');
const router = express.Router();

const CATEGORIES = ['Bouquets', 'Plants', 'Seasonal', 'Weddings'];

router.get('/', async (req, res, next) => {
  try {
    const featured = await q('SELECT * FROM products WHERE featured = 1 AND vip_only = 0 ORDER BY id LIMIT 6');
    const reviews = await q(`SELECT r.rating, r.body, u.full_name, p.name AS product FROM reviews r
      JOIN users u ON u.id = r.user_id JOIN products p ON p.id = r.product_id
      WHERE r.id <= 5 ORDER BY r.rating DESC, r.id LIMIT 3`);
    res.render('home', { title: 'Fresh flowers, delivered today', featured, reviews });
  } catch (e) { next(e); }
});

// CWE-80 (reflected): the search term is echoed back unencoded
router.get('/shop', async (req, res, next) => {
  try {
    const term = typeof req.query.q === 'string' ? req.query.q.slice(0, 300) : '';
    const category = CATEGORIES.includes(req.query.category) ? req.query.category : '';
    const sort = { price_asc: 'price ASC', price_desc: 'price DESC', name: 'name ASC' }[req.query.sort] || 'featured DESC, id ASC';
    const where = ['vip_only = 0']; const params = [];
    if (category) { where.push('category = ?'); params.push(category); }
    let blocked = false;
    if (term) {
      if (xssBlocked(term)) blocked = true;
      where.push('(name LIKE ? OR description LIKE ?)'); params.push(`%${term}%`, `%${term}%`);
    }
    const products = await q(`SELECT * FROM products WHERE ${where.join(' AND ')} ORDER BY ${sort}`, params);
    res.render('shop', { title: 'Shop flowers', products, term, blocked, category, categories: CATEGORIES, sort: req.query.sort || '' });
  } catch (e) { next(e); }
});

router.get('/product/:slug', async (req, res, next) => {
  try {
    const [product] = await q('SELECT * FROM products WHERE slug = ?', [String(req.params.slug)]);
    if (!product || (product.vip_only && !res.locals.isVip)) return next();
    const reviews = await q(`SELECT r.*, u.full_name, u.username FROM reviews r JOIN users u ON u.id = r.user_id
      WHERE r.product_id = ? ORDER BY r.created_at DESC`, [product.id]);
    const related = await q('SELECT * FROM products WHERE category = ? AND id <> ? AND vip_only = 0 LIMIT 3', [product.category, product.id]);
    const avg = reviews.length ? reviews.reduce((s, r) => s + r.rating, 0) / reviews.length : 0;
    res.render('product', { title: product.name, product, reviews, related, avg });
  } catch (e) { next(e); }
});

// CWE-80 (stored): review text is stored and rendered without encoding
router.post('/product/:slug/review', requireAuth, async (req, res, next) => {
  try {
    const [product] = await q('SELECT id, slug FROM products WHERE slug = ?', [String(req.params.slug)]);
    if (!product) return next();
    const rating = Math.min(5, Math.max(1, parseInt(req.body.rating, 10) || 5));
    const body = String(req.body.body || '').trim().slice(0, 1000);
    if (body.length < 3) { flash(req, 'danger', 'Write at least a few words about your flowers.'); }
    else if (xssBlocked(body)) { flash(req, 'danger', 'Your review contains content that can\'t be published. Edit it and try again.'); }
    else {
      await q('INSERT INTO reviews (product_id, user_id, rating, body) VALUES (?, ?, ?, ?)', [product.id, req.session.user.id, rating, body]);
      flash(req, 'success', 'Thanks! Your review is now live.');
    }
    res.redirect(`/product/${product.slug}#reviews`);
  } catch (e) { next(e); }
});

// CWE-89: order tracking builds SQL by string concatenation
router.get('/track', async (req, res) => {
  const ref = typeof req.query.order === 'string' ? req.query.order.trim() : '';
  let results = null, error = null;
  if (ref) {
    try {
      const sql = "SELECT order_number, status, recipient_name, delivery_date, total FROM orders WHERE order_number = '" + ref + "'";
      results = await q(sql);
    } catch (e) {
      error = 'We could not look up that order number. Check it and try again.';
    }
  }
  res.render('track', { title: 'Track your order', ref, results, error });
});

// CWE-918: custom bouquet "reference image" fetched server-side from any URL
router.get('/bouquet', (req, res) => res.render('bouquet', { title: 'Design a custom bouquet' }));

router.post('/bouquet/preview', requireAuth, async (req, res) => {
  const url = String(req.body.url || '').trim();
  let parsed;
  try { parsed = new URL(url); } catch { return res.json({ ok: false, message: 'Enter a full web address starting with http:// or https://' }); }
  if (!['http:', 'https:'].includes(parsed.protocol)) return res.json({ ok: false, message: 'Enter a full web address starting with http:// or https://' });
  try {
    const ctrl = new AbortController(); const timer = setTimeout(() => ctrl.abort(), 6000);
    const r = await fetch(parsed.href, { signal: ctrl.signal, redirect: 'follow', headers: { 'User-Agent': 'PetalStem-ImageFetcher/1.2' } });
    clearTimeout(timer);
    const type = (r.headers.get('content-type') || '').split(';')[0].trim();
    const buf = Buffer.from(await r.arrayBuffer()).subarray(0, 2 * 1024 * 1024);
    if (/^image\/(png|jpe?g|gif|webp)$/.test(type)) {
      return res.json({ ok: true, image: `data:${type};base64,${buf.toString('base64')}` });
    }
    return res.json({ ok: false, message: `That address didn't return an image (HTTP ${r.status}, ${type || 'unknown type'}).`, body: buf.toString('utf8').slice(0, 4000) });
  } catch (e) {
    return res.json({ ok: false, message: 'We couldn\'t reach that address. Check the link and try again.' });
  }
});

router.post('/bouquet/request', requireAuth, async (req, res, next) => {
  try {
    const occasion = String(req.body.occasion || 'Just because').slice(0, 60);
    const palette = String(req.body.palette || 'Florist\'s choice').slice(0, 60);
    const budget = Math.min(500, Math.max(40, parseInt(req.body.budget, 10) || 60));
    await q('INSERT INTO custom_requests (user_id, occasion, palette, budget, notes, reference_url) VALUES (?,?,?,?,?,?)',
      [req.session.user.id, occasion, palette, budget, String(req.body.notes || '').slice(0, 600), String(req.body.url || '').slice(0, 500)]);
    flash(req, 'success', 'Request sent. A florist will email you a sketch and quote within 24 hours.');
    res.redirect('/bouquet');
  } catch (e) { next(e); }
});

// Petal Club members area (VIP)
router.get('/members', requireAuth, async (req, res, next) => {
  try {
    const products = res.locals.isVip ? await q('SELECT * FROM products WHERE vip_only = 1') : [];
    res.render('members', { title: "Petal Club Reserve", products });
  } catch (e) { next(e); }
});

module.exports = router;
