const express = require('express');
const { q } = require('../lib/db');
const { requireRole, flash } = require('../lib/security');
const { resetPollution } = require('../lib/labContainment');
const { activity } = require('../lib/logger');
const router = express.Router();
router.use(requireRole('admin'));

router.get('/', async (req, res, next) => {
  try {
    const [kpi] = await q(`SELECT COUNT(*) orders, COALESCE(SUM(total),0) revenue, COALESCE(AVG(total),0) aov,
      SUM(status IN ('received','arranging','out_for_delivery')) open_orders FROM orders WHERE status <> 'cancelled'`);
    const [cust] = await q("SELECT COUNT(*) n FROM users WHERE role = 'customer'");
    const daily = await q(`SELECT DATE(created_at) d, SUM(total) revenue, COUNT(*) n FROM orders
      WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 13 DAY) AND status <> 'cancelled' GROUP BY DATE(created_at) ORDER BY d`);
    const byCat = await q(`SELECT oi.category, SUM(oi.unit_price * oi.qty) revenue FROM order_items oi
      JOIN orders o ON o.id = oi.order_id WHERE o.status <> 'cancelled' GROUP BY oi.category ORDER BY revenue DESC`);
    const recent = await q('SELECT o.*, u.username FROM orders o JOIN users u ON u.id = o.user_id ORDER BY o.created_at DESC LIMIT 8');
    const top = await q(`SELECT product_name, SUM(qty) units FROM order_items GROUP BY product_name ORDER BY units DESC LIMIT 5`);
    const days = [];
    for (let i = 13; i >= 0; i--) {
      const d = new Date(Date.now() - i * 864e5).toISOString().slice(0, 10);
      const row = daily.find(r => r.d === d);
      days.push({ d, revenue: row ? Number(row.revenue) : 0 });
    }
    res.render('admin/index', { title: 'Admin dashboard', kpi, customers: cust.n, days, byCat, recent, top });
  } catch (e) { next(e); }
});

router.get('/users', async (req, res, next) => {
  try {
    const users = await q('SELECT id, username, email, full_name, role, is_vip, active, created_at FROM users ORDER BY id');
    res.render('admin/users', { title: 'Customers & staff', users });
  } catch (e) { next(e); }
});

router.post('/users/:id', async (req, res, next) => {
  try {
    const id = parseInt(req.params.id, 10);
    if (id === req.session.user.id) { flash(req, 'danger', 'You can\'t change your own account here.'); return res.redirect('/admin/users'); }
    if (req.body.action === 'toggle-active') await q('UPDATE users SET active = 1 - active WHERE id = ?', [id]);
    if (req.body.action === 'toggle-vip') await q('UPDATE users SET is_vip = 1 - is_vip WHERE id = ?', [id]);
    if (['customer', 'staff', 'admin'].includes(req.body.role)) await q('UPDATE users SET role = ? WHERE id = ?', [req.body.role, id]);
    activity('ADMIN_USER_UPDATE', { admin: req.session.user.username, target: id, action: req.body.action || req.body.role });
    flash(req, 'success', 'Account updated.');
    res.redirect('/admin/users');
  } catch (e) { next(e); }
});

router.get('/products', async (req, res, next) => {
  try { res.render('admin/products', { title: 'Catalogue', products: await q('SELECT * FROM products ORDER BY category, name') }); }
  catch (e) { next(e); }
});

router.post('/products/:id', async (req, res, next) => {
  try {
    const price = Number(req.body.price), stock = parseInt(req.body.stock, 10);
    if (!(price > 0 && price < 10000) || !(stock >= 0 && stock < 100000)) { flash(req, 'danger', 'Enter a valid price and stock level.'); return res.redirect('/admin/products'); }
    await q('UPDATE products SET price = ?, stock = ?, featured = ? WHERE id = ?', [price, stock, req.body.featured === 'on' ? 1 : 0, parseInt(req.params.id, 10)]);
    flash(req, 'success', 'Product saved.');
    res.redirect('/admin/products');
  } catch (e) { next(e); }
});

// Lab helper: clears a polluted Object.prototype without restarting the container
router.post('/lab/reset', (req, res) => { resetPollution(); flash(req, 'success', 'Lab state reset: shared runtime state cleared.'); res.redirect('/admin'); });

module.exports = router;
