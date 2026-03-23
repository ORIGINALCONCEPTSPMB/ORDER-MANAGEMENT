<?php
session_start();

if (!file_exists(__DIR__ . '/config.php')) {
    header('Location: install.php');
    exit;
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';

requireLogin();
$currentUser = getCurrentUser();
$isAdmin     = in_array($currentUser['role'], ['admin', 'super_admin']);
$db          = getDb();

$totalActive   = (int)$db->query('SELECT COUNT(*) FROM pf_orders WHERE is_archived=0')->fetchColumn();
$myOrdersCount = 0;
if (!$isAdmin) {
    $st = $db->prepare('SELECT COUNT(*) FROM pf_orders WHERE is_archived=0 AND created_by=?');
    $st->execute([$currentUser['id']]);
    $myOrdersCount = (int)$st->fetchColumn();
} else {
    $myOrdersCount = $totalActive;
}

$stages      = $db->query('SELECT * FROM pf_stages WHERE is_active=1 ORDER BY order_position ASC')->fetchAll();
$stageCounts = [];
foreach ($stages as $s) {
    $stmt = $db->prepare('SELECT COUNT(*) FROM pf_orders WHERE current_stage=? AND is_archived=0' . ($isAdmin ? '' : ' AND created_by=' . (int)$currentUser['id']));
    $stmt->execute([$s['id']]);
    $stageCounts[$s['id']] = (int)$stmt->fetchColumn();
}

// Recent orders
if ($isAdmin) {
    $recentOrders = $db->query(
        'SELECT o.*, s.name AS stage_name, s.color AS stage_color
         FROM pf_orders o
         LEFT JOIN pf_stages s ON o.current_stage = s.id
         WHERE o.is_archived = 0
         ORDER BY o.created_at DESC LIMIT 10'
    )->fetchAll();
} else {
    $stmt = $db->prepare(
        'SELECT o.*, s.name AS stage_name, s.color AS stage_color
         FROM pf_orders o
         LEFT JOIN pf_stages s ON o.current_stage = s.id
         WHERE o.is_archived = 0 AND o.created_by = ?
         ORDER BY o.created_at DESC LIMIT 10'
    );
    $stmt->execute([$currentUser['id']]);
    $recentOrders = $stmt->fetchAll();
}

$pageTitle = 'Dashboard';
include __DIR__ . '/includes/header.php';
?>

<div class="stats-grid">
    <div class="stats-card">
        <div class="stats-card-icon blue">
            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
        </div>
        <div>
            <div class="stats-card-number"><?= $isAdmin ? $totalActive : $myOrdersCount ?></div>
            <div class="stats-card-label"><?= $isAdmin ? 'Total Active Orders' : 'My Active Orders' ?></div>
        </div>
    </div>
    <?php foreach ($stages as $s): ?>
    <div class="stats-card">
        <div class="stats-card-icon" style="background:<?= htmlspecialchars($s['color']) ?>20;color:<?= htmlspecialchars($s['color']) ?>;">
            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
        </div>
        <div>
            <div class="stats-card-number"><?= $stageCounts[$s['id']] ?? 0 ?></div>
            <div class="stats-card-label">
                <span class="stage-badge" style="background:<?= htmlspecialchars($s['color']) ?>;color:#fff;padding:2px 8px;border-radius:10px;font-size:0.75em;">
                    <?= htmlspecialchars($s['name']) ?>
                </span>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="card">
    <div class="card-header">
        <h2 class="card-title">Recent Orders</h2>
        <div style="display:flex;gap:8px;">
            <a href="<?= rtrim(APP_URL, '/') ?>/orders/index.php" class="btn btn-secondary btn-sm">View All</a>
            <a href="<?= rtrim(APP_URL, '/') ?>/orders/create.php" class="btn btn-primary btn-sm">+ New Order</a>
        </div>
    </div>
    <div class="card-body" style="padding:0;">
        <div class="table-wrapper">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Customer</th>
                        <th>Business</th>
                        <th>Stage</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($recentOrders)): ?>
                <tr><td colspan="6" class="text-center text-muted" style="padding:32px;">
                    No orders yet. <a href="<?= rtrim(APP_URL, '/') ?>/orders/create.php">Create the first one</a>.
                </td></tr>
                <?php else: ?>
                <?php foreach ($recentOrders as $order): ?>
                <tr>
                    <td><a href="<?= rtrim(APP_URL, '/') ?>/orders/view.php?id=<?= $order['id'] ?>" class="fw-600">#<?= $order['id'] ?></a></td>
                    <td><?= htmlspecialchars($order['customer_name']) ?></td>
                    <td><?= htmlspecialchars($order['business_name'] ?: '—') ?></td>
                    <td>
                        <?php if ($order['stage_name']): ?>
                        <span class="stage-badge" style="background:<?= htmlspecialchars($order['stage_color']) ?>;color:#fff;padding:3px 10px;border-radius:12px;font-size:0.8em;">
                            <?= htmlspecialchars($order['stage_name']) ?>
                        </span>
                        <?php else: ?>—<?php endif; ?>
                    </td>
                    <td><?= formatDate($order['created_at'], 'M j, Y') ?></td>
                    <td style="display:flex;gap:6px;align-items:center;flex-wrap:wrap;">
                        <a href="<?= rtrim(APP_URL, '/') ?>/orders/view.php?id=<?= $order['id'] ?>" class="btn btn-secondary btn-sm">View</a>
                        <?php if ($order['whatsapp'] && $order['stage_name']): ?>
                        <?php
                        $msg   = parseWhatsAppTemplate($order['whatsapp_template'] ?? '', $order, $order['stage_name']);
                        $waUrl = getWhatsAppUrl($order['whatsapp'], $msg);
                        ?>
                        <a href="<?= htmlspecialchars($waUrl) ?>" target="_blank"
                           class="btn btn-sm" style="background:#25d366;color:#fff;border-color:#25d366;padding:4px 8px;"
                           title="WhatsApp <?= htmlspecialchars($order['customer_name']) ?>">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                        </a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
