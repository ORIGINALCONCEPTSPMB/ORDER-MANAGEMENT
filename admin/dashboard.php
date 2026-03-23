<?php
session_start();

if (!file_exists(__DIR__ . '/../config.php')) {
    header('Location: ../install.php');
    exit;
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';

requireRole('admin');
$currentUser = getCurrentUser();
$db          = getDb();

$totalUsers    = (int) $db->query('SELECT COUNT(*) FROM users')->fetchColumn();
$activeUsers   = (int) $db->query('SELECT COUNT(*) FROM users WHERE is_active = 1')->fetchColumn();
$inactiveUsers = $totalUsers - $activeUsers;
$totalOrders   = (int) $db->query('SELECT COUNT(*) FROM orders')->fetchColumn();

$statusCounts = [];
foreach (['pending','processing','completed','cancelled'] as $s) {
    $stmt = $db->prepare("SELECT COUNT(*) FROM orders WHERE status = ?");
    $stmt->execute([$s]);
    $statusCounts[$s] = (int) $stmt->fetchColumn();
}

$recentUsers = $db->query(
    'SELECT id, email, first_name, last_name, role, is_active, created_at
     FROM users ORDER BY created_at DESC LIMIT 5'
)->fetchAll();

$pageTitle = 'Admin Dashboard';
include __DIR__ . '/../includes/header.php';
?>

<div class="stats-grid">
    <div class="stats-card">
        <div class="stats-card-icon blue">
            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        </div>
        <div>
            <div class="stats-card-number"><?= $totalUsers ?></div>
            <div class="stats-card-label">Total Users</div>
        </div>
    </div>
    <div class="stats-card">
        <div class="stats-card-icon green">
            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
        </div>
        <div>
            <div class="stats-card-number"><?= $activeUsers ?></div>
            <div class="stats-card-label">Active Users</div>
        </div>
    </div>
    <div class="stats-card">
        <div class="stats-card-icon gray">
            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg>
        </div>
        <div>
            <div class="stats-card-number"><?= $inactiveUsers ?></div>
            <div class="stats-card-label">Inactive Users</div>
        </div>
    </div>
    <div class="stats-card">
        <div class="stats-card-icon blue">
            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
        </div>
        <div>
            <div class="stats-card-number"><?= $totalOrders ?></div>
            <div class="stats-card-label">Total Orders</div>
        </div>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-bottom:24px;">
    <!-- Orders by Status -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Orders by Status</h2>
            <a href="orders.php" class="btn btn-secondary btn-sm">View All</a>
        </div>
        <div class="card-body">
            <?php foreach ($statusCounts as $status => $count): ?>
            <div style="display:flex;align-items:center;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--border);">
                <span class="<?= getStatusBadgeClass($status) ?>"><?= ucfirst($status) ?></span>
                <span class="fw-600"><?= $count ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Quick Links -->
    <div class="card">
        <div class="card-header"><h2 class="card-title">Quick Actions</h2></div>
        <div class="card-body" style="display:flex;flex-direction:column;gap:12px;">
            <a href="users.php" class="btn btn-secondary">Manage Users</a>
            <a href="orders.php" class="btn btn-secondary">Manage Orders</a>
            <a href="../orders/create.php" class="btn btn-primary">Create New Order</a>
            <a href="orders.php?export=csv" class="btn btn-secondary">Export Orders CSV</a>
        </div>
    </div>
</div>

<!-- Recent Users -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title">Recent Users</h2>
        <a href="users.php" class="btn btn-secondary btn-sm">All Users</a>
    </div>
    <div class="card-body" style="padding:0;">
        <div class="table-wrapper">
            <table class="table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Joined</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($recentUsers as $u): ?>
                <tr>
                    <td><?= htmlspecialchars($u['first_name'] . ' ' . $u['last_name']) ?></td>
                    <td><?= htmlspecialchars($u['email']) ?></td>
                    <td><span class="badge badge-secondary"><?= ucfirst(str_replace('_', ' ', $u['role'])) ?></span></td>
                    <td>
                        <?php if ($u['is_active']): ?>
                        <span class="badge badge-completed">Active</span>
                        <?php else: ?>
                        <span class="badge badge-cancelled">Inactive</span>
                        <?php endif; ?>
                    </td>
                    <td><?= formatDate($u['created_at'], 'M j, Y') ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
