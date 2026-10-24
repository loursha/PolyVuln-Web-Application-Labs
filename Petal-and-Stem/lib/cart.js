// Shopping cart is kept client-side in the `bloom_cart` cookie (base64 JSON).
const IMG_RE = /^\/images\/products\/[a-z0-9-]+\.jpg$/;

function readCart(req) {
  try {
    const raw = req.cookies.bloom_cart;
    if (!raw) return [];
    const items = JSON.parse(Buffer.from(raw, 'base64').toString('utf8'));
    if (!Array.isArray(items)) return [];
    return items.slice(0, 30).filter(i =>
      Number.isInteger(i.id) && typeof i.name === 'string' && i.name.length <= 120 &&
      typeof i.price === 'number' && Number.isFinite(i.price) && i.price >= 0 &&
      Number.isInteger(i.qty) && i.qty >= 1 && i.qty <= 50
    ).map(i => ({ id: i.id, name: i.name, price: Math.round(i.price * 100) / 100, qty: i.qty, image: IMG_RE.test(i.image) ? i.image : '/images/products/reserve.jpg' }));
  } catch { return []; }
}

function writeCart(res, items) {
  res.cookie('bloom_cart', Buffer.from(JSON.stringify(items)).toString('base64'), { httpOnly: true, sameSite: 'lax', maxAge: 7 * 864e5 });
}

const DELIVERY_FEE = 6.95, FREE_DELIVERY_OVER = 75, VIP_PCT = 15;

function totals(items, couponPct, vip) {
  const subtotal = items.reduce((s, i) => s + i.price * i.qty, 0);
  const pct = Math.max(couponPct || 0, vip ? VIP_PCT : 0);
  const discount = Math.round(subtotal * pct) / 100;
  const delivery = items.length === 0 || vip || subtotal - discount >= FREE_DELIVERY_OVER ? 0 : DELIVERY_FEE;
  const total = Math.max(0, Math.round((subtotal - discount + delivery) * 100) / 100);
  return { subtotal: Math.round(subtotal * 100) / 100, pct, discount, delivery, total };
}

module.exports = { readCart, writeCart, totals, FREE_DELIVERY_OVER };
