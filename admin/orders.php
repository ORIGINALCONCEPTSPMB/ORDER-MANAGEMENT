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

// --- CSV Export ---
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $orders = $db->query(
        "SELECT o.order_number, o.customer_name, o.customer_email, o.customer_phone,
                o.status, o.priority, o.description, o.notes,
                CONCAT(c.first_name,' ',c.last_name) AS created_by,
                CONCAT(a.first_name,' ',a.last_name) AS assigned_to,
                o.created_at, o.updated_at
         FROM orders o
         LEFT JOIN users c ON c.id = o.created_by
         LEFT JOIN users a ON a.id = o.assigned_to
         ORDER BY o.created_at DESC"
    )->fetchAll();

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="orders-' . date('Ymd-His') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Order #','Customer','Email','Phone','Status','Priority','Description','Notes','Created By','Assigned To','Created At','Updated At']);
    foreach ($orders as $row) {
        fputcsv($out, array_values($row));
    }
    fclose($out);
    exit;
}

// --- Bulk status update ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'])) {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Invalid security token.');
        redirect('orders.php');
    }
    $ids       = array_map('intval', $_POST['order_ids'] ?? []);
    $newStatus = $_POST['bulk_status'] ?? '';
    if ($ids && in_array($newStatus, ['pending','processing','completed','cancelled'])) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $db->prepare("UPDATE orders SET status=? WHERE id IN ($placeholders)")
           ->execute(array_merge([$newStatus], $ids));

        foreach ($ids as $oid) {
            $db->prepare(
                'INSERT INTO order_history (order_id, user_id, action, new_status, note) VALUES (?,?,?,?,?)'
            )->execute([$oid, $currentUser['id'], 'Bulk status update', $newStatus, 'Bulk updated by admin.']);
        }
        setFlash('success', count($ids) . ' orders updated to ' . ucfirst($newStatus) . '.');
    }
    redirect('orders.php');
}

// --- Delete order ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Invalid security token.');
        redirect('orders.php');
    }
    $db->prepare('DELETE FROM orders WHERE id=?')->execute([(int)$_POST['delete_id']]);
    setFlash('success', 'Order deleted.');
    redirect('orders.php');
}

// --- Assign user ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_order_id'])) {
    if (validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $oid        = (int) $_POST['assign_order_id'];
        $assignedTo = !empty($_POST['assign_to']) ? (int)$_POST['assign_to'] : null;
        $db->prepare('UPDATE orders SET assigned_to=? WHERE id=?')->execute([$assignedTo, $oid]);
        $db->prepare(
            'INSERT INTO order_history (order_id, user_id, action, note) VALUES (?,?,?,?)'
        )->execute([$oid, $currentUser['id'], 'Assignment changed', 'Admin updated assignment.']);
        setFlash('success', 'Order assignment updated.');
    }
    redirect('orders.php');
}

// Filters
$filterStatus   = $_GET['status']   ?? '';
$filterPriority = $_GET['priority'] ?? '';
$search         = trim($_GET['search'] ?? '');
$page           = max(1, (int)($_GET['page'] ?? 1));
$perPage        = 25;
$offset         = ($page - 1) * $perPage;

$where  = [];
$params = [];
if ($filterStatus && in_array($filterStatus, ['pending','processing','completed','cancelled'])) {
    $where[]  = 'o.status = ?';
    $params[] = $filterStatus;
}
if ($filterPriority && in_array($filterPriority, ['low','medium','high'])) {
    $where[]  = 'o.priority = ?';
    $params[] = $filterPriority;
}
if ($search !== '') {
    $where[]  = '(o.order_number LIKE ? OR o.customer_name LIKE ? OR o.customer_email LIKE ?)';
    $like     = '%' . $search . '%';
    $params[] = $like; $params[] = $like; $params[] = $like;
}

$whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$countStmt   = $db->prepare("SELECT COUNT(*) FROM orders o $whereClause");
$countStmt->execute($params);
$totalCount  = (int) $countStmt->fetchColumn();
$totalPages  = max(1, (int)ceil($totalCount / $perPage));

$stmt = $db->prepare(
    "SELECT o.*,
            CONCAT(c.first_name,' ',c.last_name) AS creator_name,
            CONCAT(a.first_name,' ',a.last_name) AS assignee_name
     FROM orders o
     LEFT JOIN users c ON c.id = o.created_by
     LEFT JOIN users a ON a.id = o.assigned_to
     $whereClause
     ORDER BY o.created_at DESC
     LIMIT $perPage OFFSET $offset"
);
$stmt->execute($params);
$orders = $stmt->fetchAll();

$allUsers = $db->query('SELECT id, first_name, last_name FROM users WHERE is_active=1 ORDER BY first_name')->fetchAll();

$pageTitle = 'Manage Orders';
include __DIR__ . '/../includes/header.php';

function adminBuildUrl(int $p): string {
    global $filterStatus, $filterPriority, $search;
    return 'orders.php?' . http_build_query(['status'=>$filterStatus,'priority'=>$filterPriority,'search'=>$search,'page'=>$p]);
}
?>

<div class="actions-row">
    <h2 class="card-title">All Orders (<?= $totalCount ?>)</h2>
    <div class="d-flex gap-8">
        <a href="orders.php?export=csv&status=<?= urlencode($filterStatus) ?>&priority=<?= urlencode($filterPriority) ?>&search=<?= urlencode($search) ?>" class="btn btn-secondary">Export CSV</a>
        <a href="../orders/create.php" class="btn btn-primary">+ New Order</a>
    </div>
</div>

<!-- Filters -->
<form method="GET" action="" class="card" style="padding:16px;margin-bottom:20px;">
    <div class="filter-bar">
        <div class="form-group">
            <label class="form-label">Search</label>
            <input type="text" name="search" class="form-control" value="<?= htmlspecialchars($search) ?>" placeholder="Order #, name, email...">
        </div>
        <div class="form-group">
            <label class="form-label">Status</label>
            <select name="status" class="form-control">
                <option value="">All</option>
                <?php foreach (['pending','processing','completed','cancelled'] as $s): ?>
                <option value="<?= $s ?>" <?= $filterStatus===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label">Priority</label>
            <select name="priority" class="form-control">
                <option value="">All</option>
                <?php foreach (['low','medium','high'] as $p): ?>
                <option value="<?= $p ?>" <?= $filterPriority===$p?'selected':'' ?>><?= ucfirst($p) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group" style="display:flex;align-items:flex-end;gap:8px;">
            <button type="submit" class="btn btn-primary">Filter</button>
            <a href="orders.php" class="btn btn-secondary">Clear</a>
        </div>
    </div>
</form>

<!-- Bulk action form -->
<form method="POST" action="" id="bulk-form">
    <?= csrfField() ?>
    <div class="card">
        <div style="padding:12px 16px;border-bottom:1px solid var(--border);display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
            <select name="bulk_status" class="form-control" style="width:auto;">
                <option value="">Set Status...</option>
                <?php foreach (['pending','processing','completed','cancelled'] as $s): ?>
                <option value="<?= $s ?>"><?= ucfirst($s) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" name="bulk_action" value="1" class="btn btn-secondary btn-sm">Apply to Selected</button>
        </div>
        <div class="table-wrapper">
            <table class="table" id="searchable-table">
                <thead>
                    <tr>
                        <th><input type="checkbox" id="select-all"></th>
                        <th>Order #</th>
                        <th>Customer</th>
                        <th>Status</th>
                        <th>Priority</th>
                        <th>Assigned To</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($orders)): ?>
                    <tr><td colspan="8" class="text-center text-muted" style="padding:32px;">No orders found.</td></tr>
                <?php else: ?>
                    <?php foreach ($orders as $order): ?>
                    <tr>
                        <td><input type="checkbox" class="row-checkbox" name="order_ids[]" value="<?= $order['id'] ?>"></td>
                        <td><a href="../orders/view.php?id=<?= $order['id'] ?>" class="fw-600"><?= htmlspecialchars($order['order_number']) ?></a></td>
                        <td>
                            <?= htmlspecialchars($order['customer_name']) ?>
                            <?php if ($order['customer_email']): ?>
                            <br><small class="text-muted"><?= htmlspecialchars($order['customer_email']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td><span class="<?= getStatusBadgeClass($order['status']) ?>"><?= ucfirst($order['status']) ?></span></td>
                        <td><span class="<?= getPriorityBadgeClass($order['priority']) ?>"><?= ucfirst($order['priority']) ?></span></td>
                        <td>
                            <!-- Assign to -->
                            <form method="POST" action="" style="display:inline;">
                                <?= csrfField() ?>
                                <input type="hidden" name="assign_order_id" value="<?= $order['id'] ?>">
                                <select name="assign_to" class="form-control" style="width:auto;padding:4px 8px;" onchange="this.form.submit()">
                                    <option value="">Unassigned</option>
                                    <?php foreach ($allUsers as $u): ?>
                                    <option value="<?= $u['id'] ?>" <?= $order['assigned_to'] == $u['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($u['first_name'] . ' ' . $u['last_name']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </form>
                        </td>
                        <td title="<?= formatDate($order['created_at']) ?>"><?= timeAgo($order['created_at']) ?></td>
                        <td>
                            <a href="../orders/view.php?id=<?= $order['id'] ?>" class="btn btn-secondary btn-sm">View</a>
                            <a href="../orders/edit.php?id=<?= $order['id'] ?>" class="btn btn-secondary btn-sm">Edit</a>
                            <form method="POST" action="" style="display:inline;">
                                <?= csrfField() ?>
                                <input type="hidden" name="delete_id" value="<?= $order['id'] ?>">
                                <button type="submit" class="btn btn-danger btn-sm confirm-delete"
                                        data-confirm="Delete order <?= htmlspecialchars($order['order_number']) ?>?">Delete</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</form>

<?php if ($totalPages > 1): ?>
<div class="pagination">
    <?php if ($page > 1): ?>
        <a href="<?= adminBuildUrl(1) ?>">&laquo;</a>
        <a href="<?= adminBuildUrl($page-1) ?>">&lsaquo;</a>
    <?php endif; ?>
    <?php for ($i = max(1,$page-2); $i <= min($totalPages,$page+2); $i++): ?>
        <?php if ($i===$page): ?>
            <span class="current"><?= $i ?></span>
        <?php else: ?>
            <a href="<?= adminBuildUrl($i) ?>"><?= $i ?></a>
        <?php endif; ?>
    <?php endfor; ?>
    <?php if ($page < $totalPages): ?>
        <a href="<?= adminBuildUrl($page+1) ?>">&rsaquo;</a>
        <a href="<?= adminBuildUrl($totalPages) ?>">&raquo;</a>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
