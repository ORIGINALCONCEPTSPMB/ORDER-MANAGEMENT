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

try {
    $db = getDb();

    // Total orders
    $totalOrders     = (int) $db->query('SELECT COUNT(*) FROM orders')->fetchColumn();
    $pendingCount    = (int) $db->query("SELECT COUNT(*) FROM orders WHERE status = 'pending'")->fetchColumn();
    $processingCount = (int) $db->query("SELECT COUNT(*) FROM orders WHERE status = 'processing'")->fetchColumn();
    $completedCount  = (int) $db->query("SELECT COUNT(*) FROM orders WHERE status = 'completed'")->fetchColumn();
    $cancelledCount  = (int) $db->query("SELECT COUNT(*) FROM orders WHERE status = 'cancelled'")->fetchColumn();

    // Recent 10 orders
    $stmt = $db->prepare(
        'SELECT o.*, CONCAT(u.first_name, " ", u.last_name) AS creator_name
         FROM orders o
         LEFT JOIN users u ON u.id = o.created_by
         ORDER BY o.created_at DESC LIMIT 10'
    );
    $stmt->execute();
    $recentOrders = $stmt->fetchAll();
} catch (Exception $e) {
    $totalOrders = $pendingCount = $processingCount = $completedCount = $cancelledCount = 0;
    $recentOrders = [];
}

$pageTitle = 'Dashboard';
include __DIR__ . '/includes/header.php';
?>

<div class="stats-grid">
    <div class="stats-card">
        <div class="stats-card-icon blue">
            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
        </div>
        <div>
            <div class="stats-card-number"><?= $totalOrders ?></div>
            <div class="stats-card-label">Total Orders</div>
        </div>
    </div>
    <div class="stats-card">
        <div class="stats-card-icon yellow">
            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        </div>
        <div>
            <div class="stats-card-number"><?= $pendingCount ?></div>
            <div class="stats-card-label">Pending</div>
        </div>
    </div>
    <div class="stats-card">
        <div class="stats-card-icon blue">
            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
        </div>
        <div>
            <div class="stats-card-number"><?= $processingCount ?></div>
            <div class="stats-card-label">Processing</div>
        </div>
    </div>
    <div class="stats-card">
        <div class="stats-card-icon green">
            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
        </div>
        <div>
            <div class="stats-card-number"><?= $completedCount ?></div>
            <div class="stats-card-label">Completed</div>
        </div>
    </div>
    <div class="stats-card">
        <div class="stats-card-icon red">
            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </div>
        <div>
            <div class="stats-card-number"><?= $cancelledCount ?></div>
            <div class="stats-card-label">Cancelled</div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h2 class="card-title">Recent Orders</h2>
        <a href="orders/create.php" class="btn btn-primary btn-sm">+ New Order</a>
    </div>
    <div class="card-body" style="padding:0;">
        <div class="table-wrapper">
            <table class="table" id="searchable-table">
                <thead>
                    <tr>
                        <th>Order #</th>
                        <th>Customer</th>
                        <th>Status</th>
                        <th>Priority</th>
                        <th>Created By</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($recentOrders)): ?>
                    <tr><td colspan="7" class="text-center text-muted" style="padding:32px;">No orders yet. <a href="orders/create.php">Create the first one</a>.</td></tr>
                <?php else: ?>
                    <?php foreach ($recentOrders as $order): ?>
                    <tr>
                        <td><a href="orders/view.php?id=<?= $order['id'] ?>" class="fw-600"><?= htmlspecialchars($order['order_number']) ?></a></td>
                        <td><?= htmlspecialchars($order['customer_name']) ?></td>
                        <td><span class="<?= getStatusBadgeClass($order['status']) ?>"><?= ucfirst($order['status']) ?></span></td>
                        <td><span class="<?= getPriorityBadgeClass($order['priority']) ?>"><?= ucfirst($order['priority']) ?></span></td>
                        <td><?= htmlspecialchars($order['creator_name'] ?? 'N/A') ?></td>
                        <td><?= formatDate($order['created_at'], 'M j, Y') ?></td>
                        <td>
                            <a href="orders/view.php?id=<?= $order['id'] ?>" class="btn btn-secondary btn-sm">View</a>
                            <a href="orders/edit.php?id=<?= $order['id'] ?>" class="btn btn-secondary btn-sm">Edit</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php if ($totalOrders > 10): ?>
    <div style="padding:16px 22px;border-top:1px solid var(--border);">
        <a href="orders/index.php" class="btn btn-secondary btn-sm">View All Orders &rarr;</a>
    </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
