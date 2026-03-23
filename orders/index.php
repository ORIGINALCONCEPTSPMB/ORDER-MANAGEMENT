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

$db = getDb();

// Filters
$filterStatus   = $_GET['status']   ?? '';
$filterPriority = $_GET['priority'] ?? '';
$search         = trim($_GET['search'] ?? '');
$sort           = in_array($_GET['sort'] ?? '', ['order_number','customer_name','status','priority','created_at']) ? $_GET['sort'] : 'created_at';
$dir            = (($_GET['dir'] ?? '') === 'asc') ? 'ASC' : 'DESC';
$page           = max(1, (int) ($_GET['page'] ?? 1));
$perPage        = 20;
$offset         = ($page - 1) * $perPage;

$where  = [];
$params = [];

if (!$isAdmin) {
    $where[]  = '(o.created_by = ? OR o.assigned_to = ?)';
    $params[] = $currentUser['id'];
    $params[] = $currentUser['id'];
}
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
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$totalCount = (int) $db->prepare("SELECT COUNT(*) FROM orders o $whereClause")
    ->execute($params) ? $db->prepare("SELECT COUNT(*) FROM orders o $whereClause")->execute($params) : 0;

$countStmt = $db->prepare("SELECT COUNT(*) FROM orders o $whereClause");
$countStmt->execute($params);
$totalCount  = (int) $countStmt->fetchColumn();
$totalPages  = max(1, (int) ceil($totalCount / $perPage));

$stmt = $db->prepare(
    "SELECT o.*,
            CONCAT(u.first_name,' ',u.last_name) AS creator_name,
            CONCAT(a.first_name,' ',a.last_name) AS assignee_name
     FROM orders o
     LEFT JOIN users u ON u.id = o.created_by
     LEFT JOIN users a ON a.id = o.assigned_to
     $whereClause
     ORDER BY o.$sort $dir
     LIMIT $perPage OFFSET $offset"
);
$stmt->execute($params);
$orders = $stmt->fetchAll();

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    if (validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $delId = (int) $_POST['delete_id'];
        // Only creator or admin can delete
        $chk = $db->prepare('SELECT created_by FROM orders WHERE id = ? LIMIT 1');
        $chk->execute([$delId]);
        $row = $chk->fetch();
        if ($row && ($isAdmin || $row['created_by'] == $currentUser['id'])) {
            $db->prepare('DELETE FROM orders WHERE id = ?')->execute([$delId]);
            setFlash('success', 'Order deleted successfully.');
        } else {
            setFlash('error', 'You do not have permission to delete this order.');
        }
    }
    redirect('index.php?' . http_build_query(['status'=>$filterStatus,'priority'=>$filterPriority,'search'=>$search,'page'=>$page]));
}

$pageTitle = 'All Orders';
include __DIR__ . '/../includes/header.php';

function buildPageUrl(int $p): string {
    global $filterStatus, $filterPriority, $search, $sort, $dir;
    return 'index.php?' . http_build_query(['status'=>$filterStatus,'priority'=>$filterPriority,'search'=>$search,'sort'=>$sort,'dir'=>$dir,'page'=>$p]);
}
?>

<div class="actions-row">
    <div></div>
    <a href="create.php" class="btn btn-primary">+ New Order</a>
</div>

<!-- Filter Bar -->
<form method="GET" action="" class="card" style="padding:16px;margin-bottom:20px;">
    <div class="filter-bar">
        <div class="form-group">
            <label class="form-label">Search</label>
            <input type="text" id="table-search" name="search" class="form-control"
                   value="<?= htmlspecialchars($search) ?>" placeholder="Order #, name, email...">
        </div>
        <div class="form-group">
            <label class="form-label">Status</label>
            <select name="status" class="form-control">
                <option value="">All Statuses</option>
                <?php foreach (['pending','processing','completed','cancelled'] as $s): ?>
                <option value="<?= $s ?>" <?= $filterStatus === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label">Priority</label>
            <select name="priority" class="form-control">
                <option value="">All Priorities</option>
                <?php foreach (['low','medium','high'] as $p): ?>
                <option value="<?= $p ?>" <?= $filterPriority === $p ? 'selected' : '' ?>><?= ucfirst($p) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group" style="display:flex;align-items:flex-end;gap:8px;">
            <button type="submit" class="btn btn-primary">Filter</button>
            <a href="index.php" class="btn btn-secondary">Clear</a>
        </div>
    </div>
</form>

<div class="card">
    <div class="card-header">
        <h2 class="card-title">Orders <span class="text-muted text-sm">(<?= $totalCount ?>)</span></h2>
    </div>
    <div class="card-body" style="padding:0;">
        <div class="table-wrapper">
            <table class="table" id="searchable-table">
                <thead>
                    <tr>
                        <th class="sortable">Order #</th>
                        <th class="sortable">Customer</th>
                        <th class="sortable">Status</th>
                        <th class="sortable">Priority</th>
                        <th>Assigned To</th>
                        <th class="sortable">Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($orders)): ?>
                    <tr><td colspan="7" class="text-center text-muted" style="padding:32px;">No orders found.</td></tr>
                <?php else: ?>
                    <?php foreach ($orders as $order): ?>
                    <tr>
                        <td><a href="view.php?id=<?= $order['id'] ?>" class="fw-600"><?= htmlspecialchars($order['order_number']) ?></a></td>
                        <td>
                            <?= htmlspecialchars($order['customer_name']) ?>
                            <?php if ($order['customer_email']): ?>
                            <br><small class="text-muted"><?= htmlspecialchars($order['customer_email']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td><span class="<?= getStatusBadgeClass($order['status']) ?>"><?= ucfirst($order['status']) ?></span></td>
                        <td><span class="<?= getPriorityBadgeClass($order['priority']) ?>"><?= ucfirst($order['priority']) ?></span></td>
                        <td><?= htmlspecialchars($order['assignee_name'] ?? 'Unassigned') ?></td>
                        <td title="<?= formatDate($order['created_at']) ?>"><?= timeAgo($order['created_at']) ?></td>
                        <td>
                            <a href="view.php?id=<?= $order['id'] ?>" class="btn btn-secondary btn-sm">View</a>
                            <?php if ($isAdmin || $order['created_by'] == $currentUser['id']): ?>
                            <a href="edit.php?id=<?= $order['id'] ?>" class="btn btn-secondary btn-sm">Edit</a>
                            <form method="POST" action="" style="display:inline;">
                                <?= csrfField() ?>
                                <input type="hidden" name="delete_id" value="<?= $order['id'] ?>">
                                <button type="submit" class="btn btn-danger btn-sm confirm-delete"
                                        data-confirm="Delete order <?= htmlspecialchars($order['order_number']) ?>? This cannot be undone.">Delete</button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php if ($totalPages > 1): ?>
    <div style="padding:16px 22px;border-top:1px solid var(--border);">
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="<?= buildPageUrl(1) ?>">&laquo;</a>
                <a href="<?= buildPageUrl($page - 1) ?>">&lsaquo;</a>
            <?php endif; ?>
            <?php for ($i = max(1, $page-2); $i <= min($totalPages, $page+2); $i++): ?>
                <?php if ($i === $page): ?>
                    <span class="current"><?= $i ?></span>
                <?php else: ?>
                    <a href="<?= buildPageUrl($i) ?>"><?= $i ?></a>
                <?php endif; ?>
            <?php endfor; ?>
            <?php if ($page < $totalPages): ?>
                <a href="<?= buildPageUrl($page + 1) ?>">&rsaquo;</a>
                <a href="<?= buildPageUrl($totalPages) ?>">&raquo;</a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
