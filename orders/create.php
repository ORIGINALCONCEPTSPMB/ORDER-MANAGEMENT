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

$db    = getDb();
$error = '';

// Fetch users for assign-to dropdown (admin only)
$users = [];
if ($isAdmin) {
    $stmt = $db->prepare('SELECT id, first_name, last_name FROM users WHERE is_active = 1 ORDER BY first_name');
    $stmt->execute();
    $users = $stmt->fetchAll();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $customerName  = trim($_POST['customer_name']  ?? '');
        $customerEmail = trim($_POST['customer_email'] ?? '');
        $customerPhone = trim($_POST['customer_phone'] ?? '');
        $description   = trim($_POST['description']    ?? '');
        $status        = $_POST['status']   ?? 'pending';
        $priority      = $_POST['priority'] ?? 'medium';
        $assignedTo    = $isAdmin && !empty($_POST['assigned_to']) ? (int) $_POST['assigned_to'] : null;
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
            // Ensure order number is unique
            do {
                $orderNumber = generateOrderNumber();
                $chk = $db->prepare('SELECT id FROM orders WHERE order_number = ? LIMIT 1');
                $chk->execute([$orderNumber]);
            } while ($chk->fetch());

            $db->prepare(
                'INSERT INTO orders
                    (order_number, customer_name, customer_email, customer_phone, description,
                     status, priority, assigned_to, notes, created_by)
                 VALUES (?,?,?,?,?,?,?,?,?,?)'
            )->execute([
                $orderNumber, $customerName, $customerEmail, $customerPhone, $description,
                $status, $priority, $assignedTo, $notes, $currentUser['id']
            ]);

            $orderId = (int) $db->lastInsertId();

            // Log creation to history
            $db->prepare(
                'INSERT INTO order_history (order_id, user_id, action, new_status, note)
                 VALUES (?,?,?,?,?)'
            )->execute([$orderId, $currentUser['id'], 'Order created', $status, 'Order was created.']);

            setFlash('success', 'Order ' . $orderNumber . ' created successfully.');
            redirect('view.php?id=' . $orderId);
        }
    }
}

$pageTitle = 'Create Order';
include __DIR__ . '/../includes/header.php';
?>

<nav class="breadcrumb">
    <a href="../index.php">Dashboard</a>
    <span class="breadcrumb-sep">/</span>
    <a href="index.php">Orders</a>
    <span class="breadcrumb-sep">/</span>
    <span class="breadcrumb-current">Create</span>
</nav>

<?php if ($error): ?>
<div class="alert alert-error"><?= htmlspecialchars($error) ?><button class="alert-close" onclick="this.parentElement.remove()">&times;</button></div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h2 class="card-title">New Order</h2>
    </div>
    <div class="card-body">
        <form method="POST" action="" data-validate>
            <?= csrfField() ?>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" for="customer_name">Customer Name <span class="required">*</span></label>
                    <input type="text" id="customer_name" name="customer_name" class="form-control" required
                           value="<?= htmlspecialchars($_POST['customer_name'] ?? '') ?>" placeholder="Full name">
                </div>
                <div class="form-group">
                    <label class="form-label" for="customer_email">Customer Email</label>
                    <input type="email" id="customer_email" name="customer_email" class="form-control"
                           value="<?= htmlspecialchars($_POST['customer_email'] ?? '') ?>" placeholder="customer@example.com">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" for="customer_phone">Customer Phone</label>
                    <input type="text" id="customer_phone" name="customer_phone" class="form-control"
                           value="<?= htmlspecialchars($_POST['customer_phone'] ?? '') ?>" placeholder="+1 555 000 0000">
                </div>
                <div class="form-group">
                    <label class="form-label" for="priority">Priority</label>
                    <select id="priority" name="priority" class="form-control">
                        <?php foreach (['low','medium','high'] as $p): ?>
                        <option value="<?= $p ?>" <?= ($_POST['priority'] ?? 'medium') === $p ? 'selected' : '' ?>><?= ucfirst($p) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" for="status">Status</label>
                    <select id="status" name="status" class="form-control">
                        <?php foreach (['pending','processing','completed','cancelled'] as $s): ?>
                        <option value="<?= $s ?>" <?= ($_POST['status'] ?? 'pending') === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php if ($isAdmin): ?>
                <div class="form-group">
                    <label class="form-label" for="assigned_to">Assign To</label>
                    <select id="assigned_to" name="assigned_to" class="form-control">
                        <option value="">Unassigned</option>
                        <?php foreach ($users as $u): ?>
                        <option value="<?= $u['id'] ?>" <?= ((int)($_POST['assigned_to'] ?? 0)) === $u['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($u['first_name'] . ' ' . $u['last_name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label class="form-label" for="description">Description <span class="required">*</span></label>
                <textarea id="description" name="description" class="form-control" required rows="4"
                          maxlength="5000" placeholder="Describe the order requirements..."><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
            </div>

            <div class="form-group">
                <label class="form-label" for="notes">Internal Notes</label>
                <textarea id="notes" name="notes" class="form-control" rows="3"
                          maxlength="2000" placeholder="Any internal notes (not visible to customer)..."><?= htmlspecialchars($_POST['notes'] ?? '') ?></textarea>
            </div>

            <div class="d-flex gap-12">
                <button type="submit" class="btn btn-primary">Create Order</button>
                <a href="index.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
