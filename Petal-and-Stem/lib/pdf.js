// Minimal dependency-free PDF writer for invoices (Helvetica, single page).
function esc(s) { return String(s).replace(/[^\x20-\x7e]/g, '?').replace(/([()\\])/g, '\\$1'); }

function makeInvoicePdf(order, items) {
  const lines = [];
  const text = (x, y, size, str, bold) => lines.push(`BT /${bold ? 'F2' : 'F1'} ${size} Tf ${x} ${y} Td (${esc(str)}) Tj ET`);
  text(50, 780, 22, 'Petal & Stem', true);
  text(50, 762, 10, '12 Greenhouse Row, Portland, OR  |  hello@petalstem.local');
  text(400, 780, 14, 'INVOICE', true);
  text(400, 762, 10, `No. INV-${order.order_number}`);
  text(400, 748, 10, `Date: ${String(order.created_at).slice(0, 10)}`);
  text(50, 715, 11, 'Deliver to', true);
  text(50, 700, 10, order.recipient_name);
  text(50, 686, 10, order.delivery_address);
  text(50, 672, 10, `Delivery date: ${String(order.delivery_date).slice(0, 10)}`);
  let y = 630;
  text(50, y, 10, 'Item', true); text(360, y, 10, 'Qty', true); text(420, y, 10, 'Price', true); text(490, y, 10, 'Total', true);
  lines.push(`50 ${y - 6} m 545 ${y - 6} l S`);
  y -= 24;
  for (const it of items) {
    text(50, y, 10, it.product_name); text(365, y, 10, it.qty);
    text(420, y, 10, '$' + Number(it.unit_price).toFixed(2)); text(490, y, 10, '$' + (it.unit_price * it.qty).toFixed(2));
    y -= 18;
  }
  lines.push(`50 ${y + 6} m 545 ${y + 6} l S`);
  y -= 14;
  for (const [label, val] of [['Subtotal', order.subtotal], ['Discount', -order.discount], ['Delivery', order.delivery_fee]]) {
    text(400, y, 10, label); text(490, y, 10, '$' + Number(val).toFixed(2)); y -= 16;
  }
  text(400, y, 12, 'Total', true); text(490, y, 12, '$' + Number(order.total).toFixed(2), true);
  text(50, 90, 9, `Paid by card ending ${order.card_last4}. Thank you for choosing Petal & Stem.`);
  const stream = lines.join('\n');
  const objs = [
    '<< /Type /Catalog /Pages 2 0 R >>',
    '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
    '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents 4 0 R /Resources << /Font << /F1 5 0 R /F2 6 0 R >> >> >>',
    `<< /Length ${Buffer.byteLength(stream)} >>\nstream\n${stream}\nendstream`,
    '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
    '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>'
  ];
  let out = '%PDF-1.4\n'; const offs = [];
  objs.forEach((o, i) => { offs.push(Buffer.byteLength(out)); out += `${i + 1} 0 obj\n${o}\nendobj\n`; });
  const xref = Buffer.byteLength(out);
  out += `xref\n0 ${objs.length + 1}\n0000000000 65535 f \n` + offs.map(o => String(o).padStart(10, '0') + ' 00000 n \n').join('');
  out += `trailer\n<< /Size ${objs.length + 1} /Root 1 0 R >>\nstartxref\n${xref}\n%%EOF\n`;
  return Buffer.from(out, 'binary');
}
module.exports = { makeInvoicePdf };
