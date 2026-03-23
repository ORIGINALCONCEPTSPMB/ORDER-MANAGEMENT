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

requireLogin();
$currentUser = getCurrentUser();
$isAdmin     = in_array($currentUser['role'], ['admin', 'super_admin']);

$id = (int) ($_GET['id'] ?? 0);
if (!$id) {
    setFlash('error', 'Invalid order ID.');
    redirect('index.php');
}

$db = getDb();
$stmt = $db->prepare(
    'SELECT o.*,
            CONCAT(c.first_name," ",c.last_name) AS creator_name,
            CONCAT(a.first_name," ",a.last_name) AS assignee_name
     FROM orders o
     LEFT JOIN users c ON c.id = o.created_by
     LEFT JOIN users a ON a.id = o.assigned_to
     WHERE o.id = ? LIMIT 1'
);
$stmt->execute([$id]);
$order = $stmt->fetch();

if (!$order) {
    setFlash('error', 'Order not found.');
    redirect('index.php');
}

// Non-admins can only view orders they created or are assigned to
if (!$isAdmin && $order['created_by'] != $currentUser['id'] && $order['assigned_to'] != $currentUser['id']) {
    setFlash('error', 'You do not have permission to view this order.');
    redirect('index.php');
}

// Fetch history
$histStmt = $db->prepare(
    'SELECT oh.*, CONCAT(u.first_name," ",u.last_name) AS user_name
     FROM order_history oh
     LEFT JOIN users u ON u.id = oh.user_id
     WHERE oh.order_id = ?
     ORDER BY oh.created_at DESC'
);
$histStmt->execute([$id]);
$history = $histStmt->fetchAll();

// Handle admin status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isAdmin && isset($_POST['new_status'])) {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Invalid security token.');
    } else {
        $newStatus = $_POST['new_status'];
        if (in_array($newStatus, ['pending','processing','completed','cancelled'])) {
            $oldStatus = $order['status'];
            $note      = trim($_POST['status_note'] ?? '');
            $db->prepare('UPDATE orders SET status = ? WHERE id = ?')->execute([$newStatus, $id]);
            $db->prepare(
                'INSERT INTO order_history (order_id, user_id, action, old_status, new_status, note)
                 VALUES (?,?,?,?,?,?)'
            )->execute([$id, $currentUser['id'], 'Status changed', $oldStatus, $newStatus, $note ?: null]);
            setFlash('success', 'Order status updated to ' . ucfirst($newStatus) . '.');
        }
    }
    redirect('view.php?id=' . $id);
}

$pageTitle = 'Order ' . htmlspecialchars($order['order_number']);
include __DIR__ . '/../includes/header.php';
?>

<nav class="breadcrumb">
    <a href="../index.php">Dashboard</a>
    <span class="breadcrumb-sep">/</span>
    <a href="index.php">Orders</a>
    <span class="breadcrumb-sep">/</span>
    <span class="breadcrumb-current"><?= htmlspecialchars($order['order_number']) ?></span>
</nav>

<div class="actions-row">
    <div>
        <span class="<?= getStatusBadgeClass($order['status']) ?>" style="font-size:.9rem;padding:5px 12px;"><?= ucfirst($order['status']) ?></span>
        <span class="<?= getPriorityBadgeClass($order['priority']) ?>" style="font-size:.9rem;padding:5px 12px;"><?= ucfirst($order['priority']) ?> Priority</span>
    </div>
    <div class="d-flex gap-8">
        <?php if ($isAdmin || $order['created_by'] == $currentUser['id']): ?>
        <a href="edit.php?id=<?= $order['id'] ?>" class="btn btn-secondary">Edit Order</a>
        <?php endif; ?>
        <a href="index.php" class="btn btn-secondary">Back to List</a>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 340px;gap:24px;align-items:start;">
    <div>
        <!-- Order Details -->
        <div class="card mb-24">
            <div class="card-header">
                <h2 class="card-title"><?= htmlspecialchars($order['order_number']) ?></h2>
            </div>
            <div class="card-body">
                <div class="detail-grid">
                    <div class="detail-item">
                        <label>Customer Name</label>
                        <div class="detail-value"><?= htmlspecialchars($order['customer_name']) ?></div>
                    </div>
                    <div class="detail-item">
                        <label>Customer Email</label>
                        <div class="detail-value"><?= $order['customer_email'] ? htmlspecialchars($order['customer_email']) : '<span class="text-muted">—</span>' ?></div>
                    </div>
                    <div class="detail-item">
                        <label>Customer Phone</label>
                        <div class="detail-value"><?= $order['customer_phone'] ? htmlspecialchars($order['customer_phone']) : '<span class="text-muted">—</span>' ?></div>
                    </div>
                    <div class="detail-item">
                        <label>Assigned To</label>
                        <div class="detail-value"><?= htmlspecialchars($order['assignee_name'] ?? 'Unassigned') ?></div>
                    </div>
                    <div class="detail-item">
                        <label>Created By</label>
                        <div class="detail-value"><?= htmlspecialchars($order['creator_name'] ?? 'N/A') ?></div>
                    </div>
                    <div class="detail-item">
                        <label>Created At</label>
                        <div class="detail-value"><?= formatDate($order['created_at']) ?></div>
                    </div>
                    <div class="detail-item">
                        <label>Last Updated</label>
                        <div class="detail-value"><?= formatDate($order['updated_at']) ?></div>
                    </div>
                </div>

                <?php if ($order['description']): ?>
                <div style="margin-top:20px;">
                    <label style="font-size:.78rem;font-weight:600;text-transform:uppercase;letter-spacing:.05em;color:var(--text-muted);">Description</label>
                    <div style="margin-top:6px;line-height:1.7;color:var(--text);"><?= nl2br(htmlspecialchars($order['description'])) ?></div>
                </div>
                <?php endif; ?>

                <?php if ($order['notes']): ?>
                <div style="margin-top:20px;padding:14px;background:var(--warning-light);border-radius:6px;border:1px solid #fed7aa;">
                    <label style="font-size:.78rem;font-weight:600;text-transform:uppercase;letter-spacing:.05em;color:#92400e;">Internal Notes</label>
                    <div style="margin-top:6px;line-height:1.7;color:#78350f;"><?= nl2br(htmlspecialchars($order['notes'])) ?></div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- History Timeline -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Activity History</h2>
            </div>
            <div class="card-body">
                <?php if (empty($history)): ?>
                <p class="text-muted text-sm">No activity recorded yet.</p>
                <?php else: ?>
                <div class="timeline">
                    <?php foreach ($history as $h): ?>
                    <div class="timeline-item">
                        <div class="timeline-dot"></div>
                        <div class="timeline-meta">
                            <?= htmlspecialchars($h['user_name'] ?? 'System') ?> &mdash; <?= timeAgo($h['created_at']) ?>
                        </div>
                        <div class="timeline-content fw-600"><?= htmlspecialchars($h['action']) ?></div>
                        <?php if ($h['old_status'] && $h['new_status']): ?>
                        <div class="text-sm text-muted mt-4">
                            <span class="<?= getStatusBadgeClass($h['old_status']) ?>"><?= ucfirst($h['old_status']) ?></span>
                            &rarr;
                            <span class="<?= getStatusBadgeClass($h['new_status']) ?>"><?= ucfirst($h['new_status']) ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if ($h['note']): ?>
                        <div class="text-sm text-muted mt-4"><?= htmlspecialchars($h['note']) ?></div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Sidebar panel -->
    <div>
        <?php if ($isAdmin): ?>
        <div class="card">
            <div class="card-header"><h3 class="card-title">Update Status</h3></div>
            <div class="card-body">
                <form method="POST" action="">
                    <?= csrfField() ?>
                    <div class="form-group">
                        <label class="form-label">New Status</label>
                        <select name="new_status" class="form-control">
                            <?php foreach (['pending','processing','completed','cancelled'] as $s): ?>
                            <option value="<?= $s ?>" <?= $order['status'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Note (optional)</label>
                        <textarea name="status_note" class="form-control" rows="2" placeholder="Reason for status change..."></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Update Status</button>
                </form>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
