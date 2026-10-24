const express = require('express');
const router = express.Router();

const isLoopback = (req) => ['127.0.0.1', '::1', '::ffff:127.0.0.1'].includes(req.socket.remoteAddress);

// Internal supplier sync endpoint - reachable from the server itself only (SSRF target)
router.get('/internal/inventory-sync', (req, res) => {
  if (!isLoopback(req)) return res.status(404).render('error', { title: 'Page not found', message: 'That page has wilted. Check the address or head back to the shop.' });
  res.json({
    service: 'fleurdirect-inventory-sync', version: '2.3.1', lastSync: new Date(Date.now() - 42 * 60000).toISOString(),
    supplier: { name: 'FleurDirect Wholesale', endpoint: 'https://api.fleurdirect.example/v2', apiKey: process.env.SUPPLIER_API_KEY },
    flag: 'FLAG{ssrf_reached_the_back_room}'
  });
});

// CWE-526: debug endpoint left enabled, returns the full process environment
router.get('/debug/env', (req, res) => {
  res.json({ status: 'ok', uptime: process.uptime(), node: process.version, env: process.env });
});

module.exports = router;
