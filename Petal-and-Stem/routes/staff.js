const express = require('express');
const { execFile, exec } = require('child_process');
const { q } = require('../lib/db');
const { requireRole, flash } = require('../lib/security');
const { buildPing } = require('../lib/labContainment');
const { tail, activity } = require('../lib/logger');
const router = express.Router();
router.use(requireRole('staff', 'admin'));

const STATUSES = ['received', 'arranging', 'out_for_delivery', 'delivered', 'cancelled'];

router.get('/', async (req, res, next) => {
  try {
    const orders = await q(`SELECT o.*, u.username FROM orders o JOIN users u ON u.id = o.user_id
      WHERE o.status IN ('received','arranging','out_for_delivery') ORDER BY o.delivery_date, o.id`);
    const counts = await q('SELECT status, COUNT(*) n FROM orders GROUP BY status');
    const requests = await q('SELECT c.*, u.full_name FROM custom_requests c JOIN users u ON u.id = c.user_id ORDER BY c.id DESC LIMIT 10');
    const lowStock = await q('SELECT name, stock FROM products WHERE stock < 10 ORDER BY stock');
    res.render('staff/index', { title: 'Studio queue', orders, counts: Object.fromEntries(counts.map(c => [c.status, c.n])), requests, lowStock, STATUSES });
  } catch (e) { next(e); }
});

router.post('/orders/:id/status', async (req, res, next) => {
  try {
    if (!STATUSES.includes(req.body.status)) return res.redirect('/staff');
    await q('UPDATE orders SET status = ? WHERE id = ?', [req.body.status, parseInt(req.params.id, 10)]);
    flash(req, 'success', 'Order status updated.');
    res.redirect('/staff');
  } catch (e) { next(e); }
});

// CWE-532 viewer: shows the raw activity log
router.get('/logs', (req, res) => res.render('staff/logs', { title: 'Activity log', lines: tail(250) }));

// CWE-77: delivery hub connectivity check
router.get('/diagnostics', (req, res) => res.render('staff/diagnostics', { title: 'Delivery hub diagnostics', host: '127.0.0.1', output: null, error: null }));

router.post('/diagnostics', (req, res) => {
  const host = String(req.body.host || '');
  const plan = buildPing(host);
  const render = (output, error) => res.render('staff/diagnostics', { title: 'Delivery hub diagnostics', host, output, error });
  if (!plan) return render(null, 'Enter a valid hostname or IP address, for example hub-north.petalstem.local or 10.0.4.12.');
  activity('DIAGNOSTICS', { user: req.session.user.username, target: host });
  const done = (err, stdout, stderr) => render(`${stdout || ''}${stderr || ''}`.trim() || (err ? `Command exited with code ${err.code}` : 'No output'), null);
  if (plan.shell) exec(plan.command, { timeout: 12000, shell: '/bin/sh', maxBuffer: 256 * 1024 }, done);
  else execFile('ping', plan.args, { timeout: 12000, maxBuffer: 256 * 1024 }, done);
});

module.exports = router;
