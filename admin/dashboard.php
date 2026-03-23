<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
requireRole('admin');

$currentUser = getCurrentUser();
$db          = getDb();

$totalActive   = (int)$db->query('SELECT COUNT(*) FROM pf_orders WHERE is_archived=0')->fetchColumn();
$totalArchived = (int)$db->query('SELECT COUNT(*) FROM pf_orders WHERE is_archived=1')->fetchColumn();
$totalUsers    = (int)$db->query('SELECT COUNT(*) FROM users')->fetchColumn();
$activeUsers   = (int)$db->query('SELECT COUNT(*) FROM users WHERE is_active=1')->fetchColumn();

// Orders by stage
$stages      = $db->query('SELECT * FROM pf_stages WHERE is_active=1 ORDER BY order_position ASC')->fetchAll();
$stageCounts = [];
foreach ($stages as $s) {
    $stmt = $db->prepare('SELECT COUNT(*) FROM pf_orders WHERE current_stage=? AND is_archived=0');
    $stmt->execute([$s['id']]);
    $stageCounts[$s['id']] = (int)$stmt->fetchColumn();
}
$noStageCount = (int)$db->query('SELECT COUNT(*) FROM pf_orders WHERE current_stage IS NULL AND is_archived=0')->fetchColumn();

// Recent 10 active orders
$recentOrders = $db->query(
    'SELECT o.*, s.name AS stage_name, s.color AS stage_color
     FROM pf_orders o
     LEFT JOIN pf_stages s ON o.current_stage = s.id
     WHERE o.is_archived = 0
     ORDER BY o.created_at DESC
     LIMIT 10'
)->fetchAll();

$pageTitle = 'Admin Dashboard';
include __DIR__ . '/../includes/header.php';
?>

<div class="stats-grid">
    <div class="stats-card">
        <div class="stats-card-icon blue">
            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
        </div>
        <div>
            <div class="stats-card-number"><?= $totalActive ?></div>
            <div class="stats-card-label">Active Orders</div>
        </div>
    </div>
    <div class="stats-card">
        <div class="stats-card-icon gray">
            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 8h14M5 8a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v1a2 2 0 0 1-2 2M5 8v11a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8"/></svg>
        </div>
        <div>
            <div class="stats-card-number"><?= $totalArchived ?></div>
            <div class="stats-card-label">Archived Orders</div>
        </div>
    </div>
    <div class="stats-card">
        <div class="stats-card-icon blue">
            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
        </div>
        <div>
            <div class="stats-card-number"><?= $totalUsers ?></div>
            <div class="stats-card-label">Total Users</div>
        </div>
    </div>
    <div class="stats-card">
        <div class="stats-card-icon green">
            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
        </div>
        <div>
            <div class="stats-card-number"><?= $activeUsers ?></div>
            <div class="stats-card-label">Active Users</div>
        </div>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-bottom:24px;">
    <!-- Orders by Stage -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Active Orders by Stage</h2>
            <a href="<?= rtrim(APP_URL, '/') ?>/admin/orders.php" class="btn btn-secondary btn-sm">View All</a>
        </div>
        <div class="card-body">
            <?php if (!empty($stages)): ?>
            <?php foreach ($stages as $s): ?>
            <div style="display:flex;align-items:center;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--border);">
                <span class="stage-badge" style="background:<?= htmlspecialchars($s['color']) ?>;color:#fff;padding:3px 10px;border-radius:12px;font-size:0.8em;">
                    <?= htmlspecialchars($s['name']) ?>
                </span>
                <span class="fw-600"><?= $stageCounts[$s['id']] ?? 0 ?></span>
            </div>
            <?php endforeach; ?>
            <div style="display:flex;align-items:center;justify-content:space-between;padding:8px 0;">
                <span style="color:var(--text-muted);font-size:0.85em;">No Stage</span>
                <span class="fw-600"><?= $noStageCount ?></span>
            </div>
            <?php else: ?>
            <p style="color:var(--text-muted);">No stages configured. <a href="<?= rtrim(APP_URL, '/') ?>/admin/stages.php">Add stages</a>.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Quick Links -->
    <div class="card">
        <div class="card-header"><h2 class="card-title">Quick Actions</h2></div>
        <div class="card-body" style="display:flex;flex-direction:column;gap:12px;">
            <a href="<?= rtrim(APP_URL, '/') ?>/orders/create.php" class="btn btn-primary">+ Create New Order</a>
            <a href="<?= rtrim(APP_URL, '/') ?>/admin/stages.php" class="btn btn-secondary">Manage Stages</a>
            <a href="<?= rtrim(APP_URL, '/') ?>/admin/settings.php" class="btn btn-secondary">Settings</a>
            <a href="<?= rtrim(APP_URL, '/') ?>/admin/users.php" class="btn btn-secondary">Manage Users</a>
            <a href="<?= rtrim(APP_URL, '/') ?>/orders/track.php" target="_blank" class="btn btn-secondary">Customer Tracking Portal</a>
        </div>
    </div>
</div>

<!-- Recent Orders -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title">Recent Active Orders</h2>
        <a href="<?= rtrim(APP_URL, '/') ?>/admin/orders.php" class="btn btn-secondary btn-sm">All Orders</a>
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
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($recentOrders)): ?>
                <tr><td colspan="5" class="text-center text-muted" style="padding:24px;">No active orders yet. <a href="<?= rtrim(APP_URL, '/') ?>/orders/create.php">Create one</a>.</td></tr>
                <?php else: ?>
                <?php foreach ($recentOrders as $o): ?>
                <tr>
                    <td><a href="<?= rtrim(APP_URL, '/') ?>/orders/view.php?id=<?= $o['id'] ?>" class="fw-600">#<?= $o['id'] ?></a></td>
                    <td><?= htmlspecialchars($o['customer_name']) ?></td>
                    <td><?= htmlspecialchars($o['business_name'] ?: '—') ?></td>
                    <td>
                        <?php if ($o['stage_name']): ?>
                        <span class="stage-badge" style="background:<?= htmlspecialchars($o['stage_color']) ?>;color:#fff;padding:3px 10px;border-radius:12px;font-size:0.8em;">
                            <?= htmlspecialchars($o['stage_name']) ?>
                        </span>
                        <?php else: ?>—<?php endif; ?>
                    </td>
                    <td><?= formatDate($o['created_at'], 'M j, Y') ?></td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
