<?php
/**
 * Print multiple QR labels (50mm x 40mm each) for selected orders.
 * Called from admin/orders.php with ?ids=1,2,3
 */
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$db = getDb();
$rawIds = $_GET['ids'] ?? '';
$ids = array_filter(array_map('intval', explode(',', $rawIds)));

if (empty($ids)) {
    die('No order IDs specified.');
}

$placeholders = implode(',', array_fill(0, count($ids), '?'));
$stmt = $db->prepare(
    "SELECT id, customer_name, invoice_number, qr_code_hash FROM pf_orders WHERE id IN ($placeholders) ORDER BY id ASC"
);
$stmt->execute($ids);
$orders = $stmt->fetchAll();

$appName     = defined('APP_NAME') ? APP_NAME : 'Order Management';
$companyName = getSetting('company_name', $appName);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>QR Labels &mdash; <?= htmlspecialchars($appName) ?></title>
<script src="<?= rtrim(APP_URL, '/') ?>/assets/js/qrcode.min.js"></script>
<!-- Updated dynamically by applySize() when the user changes label size. -->
<style id="page-size-style">@media print { @page { size: 50mm 40mm; margin: 1mm; } }</style>
<style>
:root { --lw: 50mm; --lh: 40mm; --lqr: 28mm; }
* { box-sizing: border-box; }
body { margin: 0; padding: 8mm; font-family: Arial, sans-serif; background: #f5f5f5; }
.controls { text-align: center; padding: 12px; background: #fff; border-bottom: 1px solid #ccc; margin-bottom: 12px; display: flex; flex-wrap: wrap; align-items: center; justify-content: center; gap: 8px; }
.controls button { background: #3a86ff; color: #fff; border: none; padding: 8px 24px; border-radius: 6px; cursor: pointer; font-size: 15px; }
.controls button.outline { background: #fff; color: #3a86ff; border: 2px solid #3a86ff; }
.controls label { font-size: 0.9em; color: #555; }
.controls select { font-size: 0.9em; padding: 5px 10px; border: 1px solid #ccc; border-radius: 6px; }
.labels-grid { display: flex; flex-wrap: wrap; gap: 4mm; justify-content: flex-start; }
.label {
    width: var(--lw);
    height: var(--lh);
    background: #fff;
    border: 1px solid #ccc;
    border-radius: 2mm;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: space-between;
    padding: 2mm;
    page-break-inside: avoid;
    overflow: hidden;
}
.label .qr-wrap { width: var(--lqr); height: var(--lqr); flex-shrink: 0; }
.label .qr-wrap canvas, .label .qr-wrap img { width: var(--lqr) !important; height: var(--lqr) !important; }
.label .linfo { text-align: center; font-size: 6.5pt; line-height: 1.3; width: 100%; }
.label .linfo strong { font-size: 7.5pt; }
@media print {
    .controls { display: none; }
    body { background: #fff; padding: 0; }
    .labels-grid { gap: 2mm; }
}
</style>
</head>
<body>
<div class="controls no-print">
    <label for="bulk-size">Label size:</label>
    <select id="bulk-size" onchange="applySize(this.value)">
        <option value="50x40">50 &times; 40 mm (small)</option>
        <option value="62x29">62 &times; 29 mm (Brother DK)</option>
        <option value="100x150">100 &times; 150 mm (shipping)</option>
        <option value="a4">A4 — 210 &times; 297 mm</option>
    </select>
    <button onclick="window.print()">&#x1F5A8; Print All Labels</button>
    <button class="outline" onclick="window.close()">Close</button>
    <span style="color:#666;font-size:0.9em;"><?= count($orders) ?> label(s)</span>
</div>

<div class="labels-grid" id="labels-grid">
<?php foreach ($orders as $order): ?>
    <div class="label">
        <div class="qr-wrap" id="qr-<?= $order['id'] ?>"></div>
        <div class="linfo">
            <strong>Order <?= htmlspecialchars(getOrderDisplayNumber($order)) ?></strong>
            <br><?= htmlspecialchars($order['customer_name']) ?>
        </div>
    </div>
<?php endforeach; ?>
</div>

<script>
(function(){
    var SIZES = {
        '50x40':   { w: '50mm',  h: '40mm',  qr: '28mm' },
        '62x29':   { w: '62mm',  h: '29mm',  qr: '20mm' },
        '100x150': { w: '100mm', h: '150mm', qr: '80mm' },
        'a4':      { w: '210mm', h: '297mm', qr: '130mm'}
    };

    window.applySize = function(key) {
        var s = SIZES[key] || SIZES['50x40'];
        var root = document.documentElement;
        root.style.setProperty('--lw',  s.w);
        root.style.setProperty('--lh',  s.h);
        root.style.setProperty('--lqr', s.qr);
        /* Update the @page size for print — CSS variables are not supported
           inside @page rules, so we inject a fresh <style> element. */
        document.getElementById('page-size-style').textContent =
            '@media print { @page { size: ' + s.w + ' ' + s.h + '; margin: 1mm; } }';
    };

    var orders = <?= json_encode(array_map(function($o) {
        return [
            'id'      => $o['id'],
            'scanUrl' => getQrScanUrl($o['qr_code_hash']),
        ];
    }, $orders)) ?>;

    orders.forEach(function(o) {
        var el = document.getElementById('qr-' + o.id);
        if (el) {
            new QRCode(el, {
                text: o.scanUrl,
                width: 106,
                height: 106,
                correctLevel: QRCode.CorrectLevel.M
            });
        }
    });

    // Auto-print after QR codes render
    window.addEventListener('load', function() {
        setTimeout(function() {
            if (window.location.search.indexOf('autoprint=1') !== -1) {
                window.print();
            }
        }, 800);
    });
}());
</script>
</body>
</html>
