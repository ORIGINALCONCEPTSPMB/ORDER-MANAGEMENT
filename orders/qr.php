<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

$hash = trim($_GET['hash'] ?? '');
// The QR hash (32 hex chars = 128-bit entropy) serves as the access token.
// Validate it looks like a valid hex hash before querying.
if (!preg_match('/^[0-9a-f]{32}$/i', $hash)) {
    $hash = '';
}
$db = getDb();

$stmt = $db->prepare(
    'SELECT o.*, s.name AS stage_name, s.color AS stage_color, s.whatsapp_template
     FROM pf_orders o
     LEFT JOIN pf_stages s ON o.current_stage = s.id
     WHERE o.qr_code_hash = ? LIMIT 1'
);
$stmt->execute([$hash]);
$order = $stmt->fetch();

$allStages = $db->query('SELECT * FROM pf_stages WHERE is_active=1 ORDER BY order_position ASC')->fetchAll();
$error     = '';
$success   = '';

// Handle stage update POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $order) {
    $newStage = !empty($_POST['new_stage']) ? (int)$_POST['new_stage'] : null;
    if ($newStage) {
        $now = date('Y-m-d H:i:s');
        $db->prepare(
            'UPDATE pf_orders SET current_stage=?, updated_at=? WHERE id=?'
        )->execute([$newStage, $now, $order['id']]);
        $db->prepare(
            'UPDATE pf_stage_history SET completed_at=? WHERE order_id=? AND completed_at IS NULL'
        )->execute([$now, $order['id']]);
        $db->prepare(
            'INSERT INTO pf_stage_history (order_id, stage_id, entered_at) VALUES (?,?,?)'
        )->execute([$order['id'], $newStage, $now]);

        header('Location: ' . rtrim(APP_URL, '/') . '/orders/qr.php?hash=' . urlencode($hash) . '&updated=1');
        exit;
    }
}

// Reload after update
if (!empty($_GET['updated'])) {
    $stmt->execute([$hash]);
    $order = $stmt->fetch();
    $success = 'Stage updated successfully.';
}

$appName   = defined('APP_NAME') ? APP_NAME : 'Order Management';
$pageTitle = $order ? 'Order #' . $order['id'] : 'Order Not Found';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($pageTitle) ?> &mdash; <?= htmlspecialchars($appName) ?></title>
<link rel="stylesheet" href="<?= rtrim(APP_URL, '/') ?>/assets/css/style.css">
<style>
.qr-page { max-width: 640px; margin: 0 auto; padding: 24px 16px; }
.qr-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 24px; margin-bottom: 20px; }
.qr-card h2 { margin: 0 0 16px; font-size: 1.1rem; }
.progress-bar { display: flex; gap: 4px; flex-wrap: wrap; margin: 12px 0; }
.stage-step { flex: 1; min-width: 80px; text-align: center; padding: 6px 4px; border-radius: 6px; font-size: 0.75em; font-weight: 600; color: #fff; opacity: 0.35; }
.stage-step.current { opacity: 1; box-shadow: 0 0 0 2px #000; }
.timeline-item { display: flex; gap: 12px; padding: 10px 0; border-bottom: 1px solid #e2e8f0; }
.timeline-item:last-child { border-bottom: none; }
.wa-btn { display: block; text-align: center; background: #25d366; color: #fff; border: none; border-radius: 8px; padding: 12px; text-decoration: none; font-weight: 600; font-size: 1rem; margin-top: 8px; }
</style>
</head>
<body style="background:#f5f7fa;min-height:100vh;">
<div class="qr-page">
    <div style="text-align:center;padding:20px 0 10px;">
        <div style="font-weight:700;font-size:1.3rem;"><?= htmlspecialchars($appName) ?></div>
    </div>

    <?php if (!$order): ?>
    <div class="qr-card" style="text-align:center;">
        <div style="font-size:3rem;margin-bottom:12px;">🔍</div>
        <h2>Order Not Found</h2>
        <p style="color:#666;">The QR code you scanned is invalid or has been removed.</p>
    </div>
    <?php else: ?>

    <?php if ($success): ?>
    <div style="background:#d1fae5;border:1px solid #6ee7b7;border-radius:8px;padding:12px 16px;margin-bottom:16px;color:#065f46;">
        <?= htmlspecialchars($success) ?>
    </div>
    <?php endif; ?>

    <!-- Order Info -->
    <div class="qr-card">
        <h2>Order #<?= $order['id'] ?></h2>
        <table style="width:100%;border-collapse:collapse;font-size:0.9em;">
            <tr>
                <th style="text-align:left;padding:4px 0;color:#666;font-weight:500;width:45%;">Customer</th>
                <td><?= htmlspecialchars($order['customer_name']) ?></td>
            </tr>
            <?php if ($order['business_name']): ?>
            <tr>
                <th style="text-align:left;padding:4px 0;color:#666;font-weight:500;">Business</th>
                <td><?= htmlspecialchars($order['business_name']) ?></td>
            </tr>
            <?php endif; ?>
            <?php if ($order['invoice_number']): ?>
            <tr>
                <th style="text-align:left;padding:4px 0;color:#666;font-weight:500;">Invoice #</th>
                <td><?= htmlspecialchars($order['invoice_number']) ?></td>
            </tr>
            <?php endif; ?>
            <tr>
                <th style="text-align:left;padding:4px 0;color:#666;font-weight:500;">Current Stage</th>
                <td>
                    <?php if ($order['stage_name']): ?>
                    <span class="stage-badge" style="background:<?= htmlspecialchars($order['stage_color']) ?>;color:#fff;padding:3px 10px;border-radius:12px;font-size:0.8em;">
                        <?= htmlspecialchars($order['stage_name']) ?>
                    </span>
                    <?php else: ?>—<?php endif; ?>
                </td>
            </tr>
        </table>

        <?php if ($order['job_details']): ?>
        <div style="margin-top:14px;">
            <div style="font-weight:500;color:#666;margin-bottom:4px;font-size:0.85em;">Job Details</div>
            <div style="font-size:0.9em;white-space:pre-wrap;"><?= htmlspecialchars($order['job_details']) ?></div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Stage Progress -->
    <?php if (!empty($allStages)): ?>
    <div class="qr-card">
        <h2>Progress</h2>
        <div class="progress-bar">
            <?php foreach ($allStages as $s):
                $isCurrent = (int)$order['current_stage'] === (int)$s['id'];
            ?>
            <div class="stage-step <?= $isCurrent ? 'current' : '' ?>" style="background:<?= htmlspecialchars($s['color']) ?>;">
                <?= htmlspecialchars($s['name']) ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Update Stage Form (for staff) -->
    <?php if (!empty($allStages)): ?>
    <div class="qr-card">
        <h2>Update Stage</h2>
        <form method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>?hash=<?= urlencode($hash) ?>">
            <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;">
                <select name="new_stage" class="form-control" style="flex:1;min-width:160px;">
                    <option value="">— Select stage —</option>
                    <?php foreach ($allStages as $s): ?>
                    <option value="<?= $s['id'] ?>" <?= (int)$order['current_stage'] === (int)$s['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($s['name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-primary">Update</button>
            </div>
        </form>
    </div>
    <?php endif; ?>

    <!-- WhatsApp Button -->
    <?php if ($order['whatsapp'] && $order['stage_name'] && $order['whatsapp_template']): ?>
    <?php
    $message = parseWhatsAppTemplate($order['whatsapp_template'], $order, $order['stage_name']);
    $waUrl   = getWhatsAppUrl($order['whatsapp'], $message);
    ?>
    <div class="qr-card">
        <h2>Notify Customer</h2>
        <a href="<?= htmlspecialchars($waUrl) ?>" target="_blank" class="wa-btn">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="currentColor" style="vertical-align:middle;margin-right:6px;"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
            Send WhatsApp to <?= htmlspecialchars($order['customer_name']) ?>
        </a>
    </div>
    <?php endif; ?>

    <!-- Stage History -->
    <?php
    $histStmt = $db->prepare(
        'SELECT sh.*, s.name AS stage_name, s.color
         FROM pf_stage_history sh
         JOIN pf_stages s ON sh.stage_id = s.id
         WHERE sh.order_id = ?
         ORDER BY sh.entered_at DESC'
    );
    $histStmt->execute([$order['id']]);
    $history = $histStmt->fetchAll();
    ?>
    <?php if (!empty($history)): ?>
    <div class="qr-card">
        <h2>Stage History</h2>
        <?php foreach ($history as $h): ?>
        <div class="timeline-item">
            <span class="stage-badge" style="background:<?= htmlspecialchars($h['color']) ?>;color:#fff;padding:3px 10px;border-radius:12px;font-size:0.8em;white-space:nowrap;">
                <?= htmlspecialchars($h['stage_name']) ?>
            </span>
            <div style="font-size:0.85em;color:#666;">
                <div><?= formatDate($h['entered_at']) ?></div>
                <?php if ($h['completed_at']): ?>
                <div>Completed: <?= formatDate($h['completed_at']) ?></div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php endif; ?>
</div>
</body>
</html>
