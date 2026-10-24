<?php
// First-boot bootstrap: wait for MySQL, then generate any missing invoice PDFs.
// (The schema itself is loaded by the MySQL container's init mount, or by the VM installer.)
require dirname(__DIR__) . '/src/config.php';
require BASE_DIR . '/src/db.php';
require BASE_DIR . '/src/pdf.php';
wait_for_db();
@mkdir(INVOICE_DIR, 0775, true);
try {
    $orders = q('SELECT * FROM orders');
} catch (Throwable $e) {
    fwrite(STDERR, "[bootstrap] schema not ready yet: {$e->getMessage()}\n");
    exit(0);
}
$made = 0;
foreach ($orders as $o) {
    $f = INVOICE_DIR . "/INV-{$o['order_number']}.pdf";
    if (!is_file($f)) { file_put_contents($f, make_invoice_pdf($o, q('SELECT * FROM order_items WHERE order_id = ?', [$o['id']]))); $made++; }
}
echo "[bootstrap] invoices ready (" . count(glob(INVOICE_DIR . '/*.pdf')) . " total, $made new)\n";
