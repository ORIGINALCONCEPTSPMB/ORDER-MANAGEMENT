<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

$db          = getDb();
$portalTitle = getSetting('portal_title', 'Track Your Order');
$portalIntro = getSetting('portal_intro', 'Enter your order ID and the last 4 digits of your WhatsApp number to track your order.');
$pageTitle   = $portalTitle;

$order    = null;
$history  = [];
$allStages = [];
$error    = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $orderId  = (int)($_POST['order_id'] ?? 0);
    $wa4      = trim($_POST['wa_last4'] ?? '');

    if (!$orderId || strlen($wa4) !== 4) {
        $error = 'Please enter a valid Order ID and 4-digit WhatsApp number.';
    } else {
        $stmt = $db->prepare(
            'SELECT o.*, s.name AS stage_name, s.color AS stage_color
             FROM pf_orders o
             LEFT JOIN pf_stages s ON o.current_stage = s.id
             WHERE o.id = ? LIMIT 1'
        );
        $stmt->execute([$orderId]);
        $found = $stmt->fetch();

        if (!$found) {
            $error = 'Order not found or details do not match.';
        } else {
            // Validate last 4 digits of whatsapp
            $storedDigits = preg_replace('/\D/', '', $found['whatsapp'] ?? '');
            $last4        = substr($storedDigits, -4);
            if ($last4 === '' || $last4 !== $wa4) {
                $error = 'Order not found or details do not match.';
            } else {
                $order = $found;

                $allStages = $db->query('SELECT * FROM pf_stages WHERE is_active=1 ORDER BY order_position ASC')->fetchAll();

                $histStmt = $db->prepare(
                    'SELECT sh.*, s.name AS stage_name, s.color
                     FROM pf_stage_history sh
                     JOIN pf_stages s ON sh.stage_id = s.id
                     WHERE sh.order_id = ?
                     ORDER BY sh.entered_at DESC'
                );
                $histStmt->execute([$orderId]);
                $history = $histStmt->fetchAll();
            }
        }
    }
}

include __DIR__ . '/../includes/auth_header.php';
?>

<?php if ($error): ?>
<div style="background:#fee2e2;border:1px solid #fca5a5;border-radius:8px;padding:12px 16px;margin-bottom:16px;color:#991b1b;">
    <?= htmlspecialchars($error) ?>
</div>
<?php endif; ?>

<?php if (!$order): ?>
<p style="color:#666;font-size:0.95em;margin-bottom:20px;"><?= htmlspecialchars($portalIntro) ?></p>
<form method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>">
    <div class="form-group">
        <label class="form-label" for="order_id">Order ID</label>
        <input type="number" id="order_id" name="order_id" class="form-control" required
               value="<?= htmlspecialchars($_POST['order_id'] ?? '') ?>" placeholder="e.g. 42" min="1">
    </div>
    <div class="form-group">
        <label class="form-label" for="wa_last4">Last 4 digits of your WhatsApp number</label>
        <input type="text" id="wa_last4" name="wa_last4" class="form-control" required
               value="<?= htmlspecialchars($_POST['wa_last4'] ?? '') ?>" placeholder="e.g. 4567"
               maxlength="4" pattern="\d{4}">
    </div>
    <button type="submit" class="btn btn-primary" style="width:100%;">Track Order</button>
</form>
<?php else: ?>
<!-- Order Results -->
<div style="margin-bottom:20px;">
    <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px;">
        <h2 style="margin:0;font-size:1.2rem;">Order #<?= $order['id'] ?></h2>
        <?php if ($order['stage_name']): ?>
        <span class="stage-badge" style="background:<?= htmlspecialchars($order['stage_color']) ?>;color:#fff;padding:4px 12px;border-radius:12px;font-size:0.85em;">
            <?= htmlspecialchars($order['stage_name']) ?>
        </span>
        <?php endif; ?>
    </div>

    <table style="width:100%;border-collapse:collapse;font-size:0.9em;margin-bottom:16px;">
        <tr>
            <th style="text-align:left;padding:5px 0;color:#666;font-weight:500;width:45%;">Customer</th>
            <td><?= htmlspecialchars($order['customer_name']) ?></td>
        </tr>
        <?php if ($order['business_name']): ?>
        <tr>
            <th style="text-align:left;padding:5px 0;color:#666;font-weight:500;">Business</th>
            <td><?= htmlspecialchars($order['business_name']) ?></td>
        </tr>
        <?php endif; ?>
        <?php if ($order['invoice_number']): ?>
        <tr>
            <th style="text-align:left;padding:5px 0;color:#666;font-weight:500;">Invoice #</th>
            <td><?= htmlspecialchars($order['invoice_number']) ?></td>
        </tr>
        <?php endif; ?>
    </table>

    <?php if (!empty($allStages)): ?>
    <div style="margin-bottom:16px;">
        <div style="font-weight:500;margin-bottom:8px;font-size:0.85em;color:#666;">Stage Progress</div>
        <div style="display:flex;gap:4px;flex-wrap:wrap;">
            <?php foreach ($allStages as $s):
                $isCurrent = (int)$order['current_stage'] === (int)$s['id'];
            ?>
            <span style="background:<?= htmlspecialchars($s['color']) ?>;color:#fff;padding:4px 10px;border-radius:10px;font-size:0.75em;font-weight:600;opacity:<?= $isCurrent ? '1' : '0.35' ?>;<?= $isCurrent ? 'box-shadow:0 0 0 2px #000;' : '' ?>">
                <?= htmlspecialchars($s['name']) ?>
            </span>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($history)): ?>
    <div>
        <div style="font-weight:500;margin-bottom:8px;font-size:0.85em;color:#666;">History</div>
        <?php foreach ($history as $h): ?>
        <div style="display:flex;gap:10px;padding:8px 0;border-bottom:1px solid #e2e8f0;font-size:0.85em;">
            <span style="background:<?= htmlspecialchars($h['color']) ?>;color:#fff;padding:2px 8px;border-radius:10px;white-space:nowrap;font-size:0.8em;">
                <?= htmlspecialchars($h['stage_name']) ?>
            </span>
            <span style="color:#666;"><?= formatDate($h['entered_at'], 'M j, Y g:i A') ?></span>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<a href="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" class="btn btn-secondary" style="width:100%;text-align:center;display:block;">Track Another Order</a>
<?php endif; ?>

</div><!-- /.auth-card -->
</div><!-- /.auth-container -->
</body>
</html>
