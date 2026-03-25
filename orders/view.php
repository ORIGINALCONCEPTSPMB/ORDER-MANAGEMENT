<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
requireLogin();

$currentUser = getCurrentUser();
$isAdmin     = in_array($currentUser['role'], ['admin', 'super_admin']);
$db          = getDb();

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    setFlash('error', 'Invalid order ID.');
    redirect(rtrim(APP_URL, '/') . '/orders/index.php');
}

$stmt = $db->prepare(
    'SELECT o.*, s.name AS stage_name, s.color AS stage_color, s.whatsapp_template
     FROM pf_orders o
     LEFT JOIN pf_stages s ON o.current_stage = s.id
     WHERE o.id = ? LIMIT 1'
);
$stmt->execute([$id]);
$order = $stmt->fetch();

if (!$order) {
    setFlash('error', 'Order not found.');
    redirect(rtrim(APP_URL, '/') . '/orders/index.php');
}

if (!$isAdmin && $order['created_by'] != $currentUser['id']) {
    setFlash('error', 'You do not have permission to view this order.');
    redirect(rtrim(APP_URL, '/') . '/orders/index.php');
}

// Handle archive/unarchive POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isAdmin) {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Invalid security token.');
    } else {
        $action = $_POST['action'] ?? '';
        $now = date('Y-m-d H:i:s');
        if ($action === 'archive') {
            $db->prepare('UPDATE pf_orders SET is_archived=1, archived_at=?, updated_at=? WHERE id=?')
               ->execute([$now, $now, $id]);
            setFlash('success', 'Order archived.');
        } elseif ($action === 'unarchive') {
            $db->prepare('UPDATE pf_orders SET is_archived=0, archived_at=NULL, updated_at=? WHERE id=?')
               ->execute([$now, $id]);
            setFlash('success', 'Order unarchived.');
        }
    }
    redirect(rtrim(APP_URL, '/') . '/orders/view.php?id=' . $id);
}

// Load stage history
$histStmt = $db->prepare(
    'SELECT sh.*, s.name AS stage_name, s.color
     FROM pf_stage_history sh
     JOIN pf_stages s ON sh.stage_id = s.id
     WHERE sh.order_id = ?
     ORDER BY sh.entered_at DESC'
);
$histStmt->execute([$id]);
$history = $histStmt->fetchAll();

// All stages for progress bar
$allStages = $db->query('SELECT * FROM pf_stages WHERE is_active=1 ORDER BY order_position ASC')->fetchAll();

// Custom fields
$customFields = getCustomFields();
$cfValues     = getOrderCustomFields($order);

// WhatsApp
$waUrl = '';
if ($order['whatsapp'] && $order['stage_name'] && $order['whatsapp_template']) {
    $message = parseWhatsAppTemplate($order['whatsapp_template'], $order, $order['stage_name']);
    $waUrl   = getWhatsAppUrl($order['whatsapp'], $message);
}

// WA redirect from edit page
$waRedirect = '';
if (!empty($_SESSION['wa_redirect'])) {
    $waRedirect = $_SESSION['wa_redirect'];
    unset($_SESSION['wa_redirect']);
}

$pageTitle = 'Order #' . $id;
include __DIR__ . '/../includes/header.php';
?>

<?php if ($waRedirect): ?>
<script>window.open(<?= json_encode($waRedirect) ?>, '_blank');</script>
<?php endif; ?>

<nav class="breadcrumb">
    <a href="<?= rtrim(APP_URL, '/') ?>/index.php">Dashboard</a>
    <span class="breadcrumb-sep">/</span>
    <a href="<?= rtrim(APP_URL, '/') ?>/orders/index.php">Orders</a>
    <span class="breadcrumb-sep">/</span>
    <span class="breadcrumb-current">Order #<?= $id ?></span>
</nav>

<div style="display:flex;gap:16px;flex-wrap:wrap;margin-bottom:20px;">
    <a href="<?= rtrim(APP_URL, '/') ?>/orders/edit.php?id=<?= $id ?>" class="btn btn-primary">Edit Order</a>
    <?php if ($waUrl): ?>
    <a href="<?= htmlspecialchars($waUrl) ?>" target="_blank" class="btn btn-secondary" style="background:#25d366;color:#fff;border-color:#25d366;">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor" style="vertical-align:middle;margin-right:4px;"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
        Send WhatsApp
    </a>
    <?php endif; ?>
    <?php if ($isAdmin): ?>
    <form method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>?id=<?= $id ?>" style="display:inline;">
        <?= csrfField() ?>
        <?php if ($order['is_archived']): ?>
        <input type="hidden" name="action" value="unarchive">
        <button type="submit" class="btn btn-secondary" onclick="return confirm('Unarchive this order?')">Unarchive</button>
        <?php else: ?>
        <input type="hidden" name="action" value="archive">
        <button type="submit" class="btn btn-secondary" onclick="return confirm('Archive this order?')">Archive</button>
        <?php endif; ?>
    </form>
    <?php endif; ?>
</div>

<?php if ($order['is_archived']): ?>
<div class="alert alert-error" style="background:#fff3cd;border-color:#ffc107;color:#856404;">
    This order is archived.
</div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:2fr 1fr;gap:24px;flex-wrap:wrap;">
    <!-- Order Details -->
    <div>
        <div class="card" style="margin-bottom:20px;">
            <div class="card-header"><h2 class="card-title">Order Details</h2></div>
            <div class="card-body">
                <table style="width:100%;border-collapse:collapse;">
                    <tr>
                        <th style="text-align:left;padding:8px 0;color:var(--text-muted);font-weight:500;width:40%;">Customer Name</th>
                        <td style="padding:8px 0;"><?= htmlspecialchars($order['customer_name']) ?></td>
                    </tr>
                    <tr>
                        <th style="text-align:left;padding:8px 0;color:var(--text-muted);font-weight:500;">Business Name</th>
                        <td style="padding:8px 0;"><?= htmlspecialchars($order['business_name'] ?: '—') ?></td>
                    </tr>
                    <tr>
                        <th style="text-align:left;padding:8px 0;color:var(--text-muted);font-weight:500;">WhatsApp</th>
                        <td style="padding:8px 0;">
                            <?php if ($order['whatsapp']): ?>
                            <a href="https://wa.me/<?= htmlspecialchars(preg_replace('/\D/', '', $order['whatsapp'])) ?>" target="_blank">
                                <?= htmlspecialchars($order['whatsapp']) ?>
                            </a>
                            <?php else: ?>—<?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th style="text-align:left;padding:8px 0;color:var(--text-muted);font-weight:500;">Invoice #</th>
                        <td style="padding:8px 0;"><?= htmlspecialchars($order['invoice_number'] ?: '—') ?></td>
                    </tr>
                    <tr>
                        <th style="text-align:left;padding:8px 0;color:var(--text-muted);font-weight:500;">Current Stage</th>
                        <td style="padding:8px 0;">
                            <?php if ($order['stage_name']): ?>
                            <span class="stage-badge" style="background:<?= htmlspecialchars($order['stage_color']) ?>;color:#fff;padding:3px 10px;border-radius:12px;font-size:0.8em;">
                                <?= htmlspecialchars($order['stage_name']) ?>
                            </span>
                            <?php else: ?>—<?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th style="text-align:left;padding:8px 0;color:var(--text-muted);font-weight:500;">Created</th>
                        <td style="padding:8px 0;"><?= formatDate($order['created_at']) ?></td>
                    </tr>
                    <tr>
                        <th style="text-align:left;padding:8px 0;color:var(--text-muted);font-weight:500;">Updated</th>
                        <td style="padding:8px 0;"><?= formatDate($order['updated_at']) ?></td>
                    </tr>
                </table>

                <?php if ($order['job_details']): ?>
                <div style="margin-top:16px;">
                    <div style="font-weight:500;margin-bottom:6px;color:var(--text-muted);">Job Details</div>
                    <div style="white-space:pre-wrap;"><?= htmlspecialchars($order['job_details']) ?></div>
                </div>
                <?php endif; ?>

                <?php if ($order['product_lines']): ?>
                <div style="margin-top:16px;">
                    <div style="font-weight:500;margin-bottom:6px;color:var(--text-muted);">Product Lines</div>
                    <div style="white-space:pre-wrap;"><?= htmlspecialchars($order['product_lines']) ?></div>
                </div>
                <?php endif; ?>

                <?php if (!empty($customFields) && !empty($cfValues)): ?>
                <div style="margin-top:16px;">
                    <div style="font-weight:500;margin-bottom:8px;color:var(--text-muted);">Custom Fields</div>
                    <?php foreach ($customFields as $cf):
                        $val = $cfValues[$cf['id']] ?? '';
                        if ($val === '' || $val === null) continue; ?>
                    <div style="display:flex;gap:16px;padding:4px 0;">
                        <span style="color:var(--text-muted);min-width:160px;"><?= htmlspecialchars($cf['field_label']) ?></span>
                        <span><?= $cf['field_type'] === 'checkbox' ? ($val ? 'Yes' : 'No') : htmlspecialchars($val) ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Stage Progress -->
        <?php if (!empty($allStages)): ?>
        <div class="card" style="margin-bottom:20px;">
            <div class="card-header"><h2 class="card-title">Stage Progress</h2></div>
            <div class="card-body">
                <div style="display:flex;gap:4px;flex-wrap:wrap;">
                    <?php foreach ($allStages as $s):
                        $isCurrent = (int)$order['current_stage'] === (int)$s['id'];
                        $opacity   = $isCurrent ? '1' : '0.35';
                    ?>
                    <span style="background:<?= htmlspecialchars($s['color']) ?>;color:#fff;padding:4px 12px;border-radius:12px;font-size:0.8em;opacity:<?= $opacity ?>;<?= $isCurrent ? 'box-shadow:0 0 0 2px #000;' : '' ?>">
                        <?= htmlspecialchars($s['name']) ?>
                    </span>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Stage History -->
        <?php if (!empty($history)): ?>
        <div class="card">
            <div class="card-header"><h2 class="card-title">Stage History</h2></div>
            <div class="card-body" style="padding:0;">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Stage</th>
                            <th>Entered</th>
                            <th>Completed</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($history as $h): ?>
                    <tr>
                        <td>
                            <span class="stage-badge" style="background:<?= htmlspecialchars($h['color']) ?>;color:#fff;padding:3px 10px;border-radius:12px;font-size:0.8em;">
                                <?= htmlspecialchars($h['stage_name']) ?>
                            </span>
                        </td>
                        <td><?= formatDate($h['entered_at']) ?></td>
                        <td><?= $h['completed_at'] ? formatDate($h['completed_at']) : '<em style="color:var(--text-muted)">In progress</em>' ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Sidebar: QR + Quick Info -->
    <div>
        <div class="card" style="margin-bottom:20px;">
            <div class="card-header"><h2 class="card-title">QR Code</h2></div>
            <div class="card-body" style="text-align:center;">
                <div id="qr-container" style="display:inline-block;margin-bottom:10px;"></div>
                <script src="<?= rtrim(APP_URL, '/') ?>/assets/js/qrcode.min.js"></script>
                <script>
                (function(){
                    var qrUrl = <?= json_encode(getQrScanUrl($order['qr_code_hash'])) ?>;
                    new QRCode(document.getElementById('qr-container'), {
                        text: qrUrl,
                        width: 180,
                        height: 180,
                        correctLevel: QRCode.CorrectLevel.M
                    });
                })();
                </script>
                <div style="margin-top:10px;display:flex;gap:6px;align-items:center;justify-content:center;flex-wrap:wrap;">
                    <label for="label-size" style="font-size:0.8em;color:var(--text-muted);white-space:nowrap;">Label size:</label>
                    <select id="label-size" style="font-size:0.8em;padding:3px 6px;border:1px solid var(--border-color,#ddd);border-radius:6px;background:#fff;">
                        <option value="50x40">50 &times; 40 mm (small)</option>
                        <option value="62x29">62 &times; 29 mm (Brother DK)</option>
                        <option value="100x150">100 &times; 150 mm (shipping)</option>
                        <option value="a4">A4 — 210 &times; 297 mm</option>
                    </select>
                </div>
                <div style="margin-top:8px;display:flex;gap:8px;flex-wrap:wrap;justify-content:center;">
                    <a href="<?= rtrim(APP_URL, '/') ?>/orders/qr.php?hash=<?= htmlspecialchars($order['qr_code_hash']) ?>"
                       target="_blank" class="btn btn-secondary btn-sm">Open QR Page</a>
                    <button onclick="printQrLabel(<?= $id ?>, <?= json_encode($order['customer_name']) ?>, <?= json_encode($order['invoice_number'] ?: '') ?>)"
                            class="btn btn-secondary btn-sm">&#x1F5A8; Print Label</button>
                    <button onclick="downloadQrPdf(<?= $id ?>, <?= json_encode($order['customer_name']) ?>, <?= json_encode($order['invoice_number'] ?: '') ?>)"
                            class="btn btn-secondary btn-sm">&#8659; Download PDF</button>
                </div>
            </div>
        </div>
<script>
/* ── Label size definitions ────────────────────────────────────────────────── */
var LABEL_SIZES = {
    '50x40':   { w: '50mm',  h: '40mm',  qr: '28mm', margin: '2mm', label: '50×40 mm'  },
    '62x29':   { w: '62mm',  h: '29mm',  qr: '20mm', margin: '2mm', label: '62×29 mm'  },
    '100x150': { w: '100mm', h: '150mm', qr: '70mm', margin: '4mm', label: '100×150 mm'},
    'a4':      { w: '210mm', h: '297mm', qr: '120mm',margin: '10mm',label: 'A4'        }
};

/* Extract the already-rendered QR canvas as a PNG data URL (no external request). */
function getQrDataUrl() {
    var container = document.getElementById('qr-container');
    if (!container) return '';
    var canvas = container.querySelector('canvas');
    if (canvas) return canvas.toDataURL('image/png');
    var img = container.querySelector('img');
    return (img && img.src) ? img.src : '';
}

function getSelectedSize() {
    var sel = document.getElementById('label-size');
    return LABEL_SIZES[sel ? sel.value : '50x40'] || LABEL_SIZES['50x40'];
}

/* Build a self-contained HTML page for the label. */
function buildLabelHtml(orderId, customerName, invoiceNumber, qrDataUrl, size) {
    var bodyH = 'calc(' + size.h + ' - ' + size.margin + ' * 2)';
    var qrTag = qrDataUrl
        ? '<img src="' + qrDataUrl + '" style="width:' + size.qr + ';height:' + size.qr + ';display:block;" alt="QR">'
        : '<div style="width:' + size.qr + ';height:' + size.qr + ';background:#eee;display:flex;align-items:center;justify-content:center;font-size:7pt;">QR</div>';
    var info  = '<strong>Order #' + orderId + '</strong>'
              + (invoiceNumber ? '<br>Inv: ' + invoiceNumber : '')
              + '<br>' + customerName;
    return '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Label</title>'
        + '<style>'
        + '@page{size:' + size.w + ' ' + size.h + ';margin:0}'
        + 'body{margin:0;padding:' + size.margin + ';font-family:Arial,sans-serif;font-size:7pt;height:' + bodyH + ';}'
        + '.lbl{display:flex;flex-direction:column;align-items:center;height:100%;justify-content:space-between;}'
        + '.linfo{text-align:center;line-height:1.4;}'
        + '</style></head>'
        + '<body><div class="lbl"><div>' + qrTag + '</div><div class="linfo">' + info + '</div></div></body></html>';
}

/* Print using a hidden iframe — works even when popup windows are blocked. */
function printWithFrame(html) {
    var old = document.getElementById('_qr_print_frame');
    if (old) old.parentNode.removeChild(old);
    var frame = document.createElement('iframe');
    frame.id = '_qr_print_frame';
    frame.setAttribute('aria-hidden', 'true');
    frame.style.cssText = 'position:fixed;bottom:0;left:0;width:1px;height:1px;border:0;opacity:0;pointer-events:none;';
    /* Register onload BEFORE setting srcdoc so the event is never missed. */
    frame.onload = function() {
        try {
            frame.contentWindow.focus();
            frame.contentWindow.print();
        } catch(e) { console.error('Print error:', e); }
        setTimeout(function() {
            if (frame && frame.parentNode) frame.parentNode.removeChild(frame);
        }, 3000);
    };
    document.body.appendChild(frame);
    frame.srcdoc = html;
}

function printQrLabel(orderId, customerName, invoiceNumber) {
    printWithFrame(buildLabelHtml(orderId, customerName, invoiceNumber, getQrDataUrl(), getSelectedSize()));
}

function downloadQrPdf(orderId, customerName, invoiceNumber) {
    printWithFrame(buildLabelHtml(orderId, customerName, invoiceNumber, getQrDataUrl(), getSelectedSize()));
}
</script>

        <?php if ($waUrl): ?>
        <div class="card">
            <div class="card-header"><h2 class="card-title">WhatsApp Notification</h2></div>
            <div class="card-body">
                <p style="font-size:0.85em;color:var(--text-muted);margin-bottom:12px;">
                    Send the current stage update to the customer via WhatsApp.
                </p>
                <a href="<?= htmlspecialchars($waUrl) ?>" target="_blank"
                   class="btn btn-sm" style="background:#25d366;color:#fff;border-color:#25d366;width:100%;text-align:center;display:block;">
                    Open WhatsApp
                </a>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
