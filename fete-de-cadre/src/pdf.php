<?php
declare(strict_types=1);

function pdf_esc(string $s): string {
    $s = preg_replace('/[^\x20-\x7e]/', '?', $s);
    return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $s);
}

function make_invoice_pdf(array $order, array $items): string {
    $ops = [];
    $text = function (int $x, int $y, int $size, string $s, bool $bold = false) use (&$ops) {
        $f = $bold ? 'F2' : 'F1';
        $ops[] = "BT /$f $size Tf $x $y Td (" . pdf_esc($s) . ") Tj ET";
    };
    $text(50, 790, 22, 'Fete de Cadre', true);
    $text(50, 772, 10, '7 Atelier Mews, Providence, RI  |  hello@fetedecadre.local');
    $text(400, 790, 14, 'INVOICE', true);
    $text(400, 772, 10, 'No. INV-' . $order['order_number']);
    $text(400, 758, 10, 'Date: ' . substr((string) $order['created_at'], 0, 10));
    $text(50, 720, 11, 'Ship to', true);
    $text(50, 705, 10, $order['ship_name']);
    $text(50, 691, 10, $order['ship_address']);
    $text(50, 677, 10, 'Method: ' . $order['shipping_method']);
    $y = 635;
    foreach ([[50, 'Item'], [300, 'Size'], [370, 'Qty'], [430, 'Price'], [500, 'Total']] as [$x, $h]) $text($x, $y, 10, $h, true);
    $ops[] = "50 " . ($y - 6) . " m 545 " . ($y - 6) . " l S";
    $y -= 24;
    foreach ($items as $it) {
        $text(50, $y, 10, $it['product_name']);
        $text(300, $y, 10, $it['size']);
        $text(375, $y, 10, (string) $it['qty']);
        $text(430, $y, 10, '$' . number_format((float) $it['unit_price'], 2));
        $text(500, $y, 10, '$' . number_format((float) $it['unit_price'] * (int) $it['qty'], 2));
        $y -= 18;
    }
    $ops[] = "50 " . ($y + 6) . " m 545 " . ($y + 6) . " l S";
    $y -= 14;
    foreach ([['Subtotal', $order['subtotal']], ['Discount', -1 * (float) $order['discount']], ['Shipping', $order['shipping_fee']]] as [$label, $val]) {
        $text(400, $y, 10, $label);
        $text(500, $y, 10, '$' . number_format((float) $val, 2));
        $y -= 16;
    }
    $text(400, $y, 12, 'Total', true);
    $text(500, $y, 12, '$' . number_format((float) $order['total'], 2), true);
    $text(50, 90, 9, 'Paid by card ending ' . $order['card_last4'] . '. Returns accepted within 30 days.');

    $stream = implode("\n", $ops);
    $objs = [
        '<< /Type /Catalog /Pages 2 0 R >>',
        '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
        '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents 4 0 R /Resources << /Font << /F1 5 0 R /F2 6 0 R >> >> >>',
        '<< /Length ' . strlen($stream) . " >>\nstream\n$stream\nendstream",
        '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>',
    ];
    $out = "%PDF-1.4\n";
    $offs = [];
    foreach ($objs as $i => $o) {
        $offs[] = strlen($out);
        $out .= ($i + 1) . " 0 obj\n$o\nendobj\n";
    }
    $xref = strlen($out);
    $out .= "xref\n0 " . (count($objs) + 1) . "\n0000000000 65535 f \n";
    foreach ($offs as $o) $out .= sprintf("%010d 00000 n \n", $o);
    $out .= "trailer\n<< /Size " . (count($objs) + 1) . " /Root 1 0 R >>\nstartxref\n$xref\n%%EOF\n";
    return $out;
}
