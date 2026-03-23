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
$stmt = $db->prepare('SELECT * FROM orders WHERE id = ? LIMIT 1');
$stmt->execute([$id]);
$order = $stmt->fetch();

if (!$order) {
    setFlash('error', 'Order not found.');
    redirect('index.php');
}

// Check edit permission
if (!$isAdmin && $order['created_by'] != $currentUser['id']) {
    setFlash('error', 'You do not have permission to edit this order.');
    redirect('index.php');
}

$users = [];
if ($isAdmin) {
    $uStmt = $db->prepare('SELECT id, first_name, last_name FROM users WHERE is_active = 1 ORDER BY first_name');
    $uStmt->execute();
    $users = $uStmt->fetchAll();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $customerName  = trim($_POST['customer_name']  ?? '');
        $customerEmail = trim($_POST['customer_email'] ?? '');
        $customerPhone = trim($_POST['customer_phone'] ?? '');
        $description   = trim($_POST['description']    ?? '');
        $status        = $_POST['status']   ?? $order['status'];
        $priority      = $_POST['priority'] ?? $order['priority'];
        $assignedTo    = $isAdmin && !empty($_POST['assigned_to']) ? (int) $_POST['assigned_to'] : ($isAdmin ? null : $order['assigned_to']);
        $notes         = trim($_POST['notes'] ?? '');

        if (empty($customerName)) {
            $error = 'Customer name is required.';
        } elseif (empty($description)) {
            $error = 'Description is required.';
        } elseif (!in_array($status, ['pending','processing','completed','cancelled'])) {
            $error = 'Invalid status.';
        } elseif (!in_array($priority, ['low','medium','high'])) {
            $error = 'Invalid priority.';
        } elseif ($customerEmail && !isValidEmail($customerEmail)) {
            $error = 'Invalid customer email address.';
        } else {
            $oldStatus = $order['status'];

            $db->prepare(
                'UPDATE orders SET customer_name=?, customer_email=?, customer_phone=?, description=?,
                 status=?, priority=?, assigned_to=?, notes=? WHERE id=?'
            )->execute([
                $customerName, $customerEmail, $customerPhone, $description,
                $status, $priority, $assignedTo, $notes, $id
            ]);

            // Log to history
            $action = 'Order updated';
            if ($oldStatus !== $status) {
                $action = 'Status changed';
            }
            $db->prepare(
                'INSERT INTO order_history (order_id, user_id, action, old_status, new_status, note)
                 VALUES (?,?,?,?,?,?)'
            )->execute([
                $id, $currentUser['id'], $action,
                $oldStatus !== $status ? $oldStatus : null,
                $oldStatus !== $status ? $status    : null,
                'Order details updated.'
            ]);

            setFlash('success', 'Order updated successfully.');
            redirect('view.php?id=' . $id);
        }
    }
}

// Use POST values on error, otherwise use DB values
$v = ($_SERVER['REQUEST_METHOD'] === 'POST' && $error) ? $_POST : $order;

$pageTitle = 'Edit Order ' . htmlspecialchars($order['order_number']);
include __DIR__ . '/../includes/header.php';
?>

<nav class="breadcrumb">
    <a href="../index.php">Dashboard</a>
    <span class="breadcrumb-sep">/</span>
    <a href="index.php">Orders</a>
    <span class="breadcrumb-sep">/</span>
    <a href="view.php?id=<?= $id ?>"><?= htmlspecialchars($order['order_number']) ?></a>
    <span class="breadcrumb-sep">/</span>
    <span class="breadcrumb-current">Edit</span>
</nav>

<?php if ($error): ?>
<div class="alert alert-error"><?= htmlspecialchars($error) ?><button class="alert-close" onclick="this.parentElement.remove()">&times;</button></div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h2 class="card-title">Edit Order: <?= htmlspecialchars($order['order_number']) ?></h2>
    </div>
    <div class="card-body">
        <form method="POST" action="" data-validate>
            <?= csrfField() ?>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" for="customer_name">Customer Name <span class="required">*</span></label>
                    <input type="text" id="customer_name" name="customer_name" class="form-control" required
                           value="<?= htmlspecialchars($v['customer_name'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label" for="customer_email">Customer Email</label>
                    <input type="email" id="customer_email" name="customer_email" class="form-control"
                           value="<?= htmlspecialchars($v['customer_email'] ?? '') ?>">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" for="customer_phone">Customer Phone</label>
                    <input type="text" id="customer_phone" name="customer_phone" class="form-control"
                           value="<?= htmlspecialchars($v['customer_phone'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label" for="priority">Priority</label>
                    <select id="priority" name="priority" class="form-control">
                        <?php foreach (['low','medium','high'] as $p): ?>
                        <option value="<?= $p ?>" <?= ($v['priority'] ?? 'medium') === $p ? 'selected' : '' ?>><?= ucfirst($p) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" for="status">Status</label>
                    <select id="status" name="status" class="form-control">
                        <?php foreach (['pending','processing','completed','cancelled'] as $s): ?>
                        <option value="<?= $s ?>" <?= ($v['status'] ?? 'pending') === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php if ($isAdmin): ?>
                <div class="form-group">
                    <label class="form-label" for="assigned_to">Assign To</label>
                    <select id="assigned_to" name="assigned_to" class="form-control">
                        <option value="">Unassigned</option>
                        <?php foreach ($users as $u): ?>
                        <option value="<?= $u['id'] ?>" <?= ((int)($v['assigned_to'] ?? 0)) === $u['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($u['first_name'] . ' ' . $u['last_name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label class="form-label" for="description">Description <span class="required">*</span></label>
                <textarea id="description" name="description" class="form-control" required rows="4" maxlength="5000"><?= htmlspecialchars($v['description'] ?? '') ?></textarea>
            </div>

            <div class="form-group">
                <label class="form-label" for="notes">Internal Notes</label>
                <textarea id="notes" name="notes" class="form-control" rows="3" maxlength="2000"><?= htmlspecialchars($v['notes'] ?? '') ?></textarea>
            </div>

            <div class="d-flex gap-12">
                <button type="submit" class="btn btn-primary">Save Changes</button>
                <a href="view.php?id=<?= $id ?>" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
