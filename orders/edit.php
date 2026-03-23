<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
requireLogin();

$currentUser = getCurrentUser();
$isAdmin     = in_array($currentUser['role'], ['admin', 'super_admin']);
$db          = getDb();

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    setFlash('error', 'Invalid order ID.');
    redirect(rtrim(APP_URL, '/') . '/orders/index.php');
}

$stmt = $db->prepare('SELECT * FROM pf_orders WHERE id = ? LIMIT 1');
$stmt->execute([$id]);
$order = $stmt->fetch();

if (!$order) {
    setFlash('error', 'Order not found.');
    redirect(rtrim(APP_URL, '/') . '/orders/index.php');
}

if (!$isAdmin && $order['created_by'] != $currentUser['id']) {
    setFlash('error', 'You do not have permission to edit this order.');
    redirect(rtrim(APP_URL, '/') . '/orders/index.php');
}

$stages       = $db->query('SELECT * FROM pf_stages WHERE is_active=1 ORDER BY order_position ASC')->fetchAll();
$customFields = getCustomFields();
$error        = '';
$waRedirect   = null;

// Handle archive/unarchive
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) &&
    in_array($_POST['action'], ['archive', 'unarchive']) && $isAdmin) {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Invalid security token.');
    } else {
        $now = date('Y-m-d H:i:s');
        if ($_POST['action'] === 'archive') {
            $db->prepare('UPDATE pf_orders SET is_archived=1, archived_at=?, updated_at=? WHERE id=?')
               ->execute([$now, $now, $id]);
            setFlash('success', 'Order archived successfully.');
        } else {
            $db->prepare('UPDATE pf_orders SET is_archived=0, archived_at=NULL, updated_at=? WHERE id=?')
               ->execute([$now, $id]);
            setFlash('success', 'Order unarchived successfully.');
        }
    }
    redirect(rtrim(APP_URL, '/') . '/orders/view.php?id=' . $id);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['action'])) {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $customerName  = trim($_POST['customer_name']  ?? '');
        $businessName  = trim($_POST['business_name']  ?? '');
        $whatsapp      = trim($_POST['whatsapp']        ?? '');
        $invoiceNumber = trim($_POST['invoice_number']  ?? '');
        $jobDetails    = trim($_POST['job_details']     ?? '');
        $productLines  = trim($_POST['product_lines']   ?? '');
        $newStage      = !empty($_POST['current_stage']) ? (int)$_POST['current_stage'] : null;
        $sendWa        = !empty($_POST['send_whatsapp']);

        if (empty($customerName)) {
            $error = 'Customer name is required.';
        } elseif (empty($jobDetails)) {
            $error = 'Job details are required.';
        } else {
            $cfValues = [];
            foreach ($customFields as $cf) {
                $key = 'cf_' . $cf['id'];
                if ($cf['field_type'] === 'checkbox') {
                    $cfValues[$cf['id']] = isset($_POST[$key]) ? '1' : '0';
                } else {
                    $cfValues[$cf['id']] = trim($_POST[$key] ?? '');
                }
                if ($cf['is_required'] && $cf['field_type'] !== 'checkbox' && $cfValues[$cf['id']] === '') {
                    $error = htmlspecialchars($cf['field_label']) . ' is required.';
                    break;
                }
            }

            if (!$error) {
                $now      = date('Y-m-d H:i:s');
                $oldStage = $order['current_stage'] ? (int)$order['current_stage'] : null;

                $db->prepare(
                    'UPDATE pf_orders SET customer_name=?, business_name=?, whatsapp=?,
                     invoice_number=?, job_details=?, product_lines=?, current_stage=?,
                     custom_fields=?, updated_at=? WHERE id=?'
                )->execute([
                    $customerName, $businessName, $whatsapp, $invoiceNumber, $jobDetails,
                    $productLines ?: null, $newStage,
                    !empty($cfValues) ? json_encode($cfValues) : null,
                    $now, $id
                ]);

                if ($newStage !== $oldStage) {
                    $db->prepare(
                        'UPDATE pf_stage_history SET completed_at=? WHERE order_id=? AND completed_at IS NULL'
                    )->execute([$now, $id]);

                    if ($newStage) {
                        $db->prepare(
                            'INSERT INTO pf_stage_history (order_id, stage_id, entered_at) VALUES (?,?,?)'
                        )->execute([$id, $newStage, $now]);
                    }

                    if ($sendWa && $newStage && $whatsapp) {
                        // Build WA URL for redirect after save
                        $stageName = '';
                        $template  = '';
                        foreach ($stages as $s) {
                            if ((int)$s['id'] === $newStage) {
                                $stageName = $s['name'];
                                $template  = $s['whatsapp_template'];
                                break;
                            }
                        }
                        $updatedOrder = array_merge($order, [
                            'customer_name' => $customerName,
                            'business_name' => $businessName,
                            'id'            => $id,
                        ]);
                        $message    = parseWhatsAppTemplate($template, $updatedOrder, $stageName);
                        $waRedirect = getWhatsAppUrl($whatsapp, $message);
                    }
                }

                setFlash('success', 'Order updated successfully.');
                if ($waRedirect) {
                    // Store WA URL in session and redirect to view page which will open it
                    $_SESSION['wa_redirect'] = $waRedirect;
                }
                redirect(rtrim(APP_URL, '/') . '/orders/view.php?id=' . $id);
            }
        }
    }
    // Reload order after failed validation
    $stmt->execute([$id]);
    $order = $stmt->fetch();
}

$savedCf = getOrderCustomFields($order);

$pageTitle = 'Edit Order #' . $id;
include __DIR__ . '/../includes/header.php';
?>

<nav class="breadcrumb">
    <a href="<?= rtrim(APP_URL, '/') ?>/index.php">Dashboard</a>
    <span class="breadcrumb-sep">/</span>
    <a href="<?= rtrim(APP_URL, '/') ?>/orders/index.php">Orders</a>
    <span class="breadcrumb-sep">/</span>
    <a href="<?= rtrim(APP_URL, '/') ?>/orders/view.php?id=<?= $id ?>">Order #<?= $id ?></a>
    <span class="breadcrumb-sep">/</span>
    <span class="breadcrumb-current">Edit</span>
</nav>

<?php if ($error): ?>
<div class="alert alert-error"><?= htmlspecialchars($error) ?><button class="alert-close" onclick="this.parentElement.remove()">&times;</button></div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h2 class="card-title">Edit Order #<?= $id ?></h2>
        <?php if ($isAdmin): ?>
        <form method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>?id=<?= $id ?>" style="display:inline;">
            <?= csrfField() ?>
            <?php if ($order['is_archived']): ?>
            <input type="hidden" name="action" value="unarchive">
            <button type="submit" class="btn btn-secondary btn-sm" onclick="return confirm('Unarchive this order?')">Unarchive</button>
            <?php else: ?>
            <input type="hidden" name="action" value="archive">
            <button type="submit" class="btn btn-secondary btn-sm" onclick="return confirm('Archive this order?')">Archive</button>
            <?php endif; ?>
        </form>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <form method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>?id=<?= $id ?>">
            <?= csrfField() ?>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" for="customer_name">Customer Name <span class="required">*</span></label>
                    <input type="text" id="customer_name" name="customer_name" class="form-control" required
                           value="<?= htmlspecialchars($_POST['customer_name'] ?? $order['customer_name']) ?>">
                </div>
                <div class="form-group">
                    <label class="form-label" for="business_name">Business Name</label>
                    <input type="text" id="business_name" name="business_name" class="form-control"
                           value="<?= htmlspecialchars($_POST['business_name'] ?? $order['business_name']) ?>">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" for="whatsapp">WhatsApp Number</label>
                    <input type="tel" id="whatsapp" name="whatsapp" class="form-control"
                           value="<?= htmlspecialchars($_POST['whatsapp'] ?? $order['whatsapp']) ?>" placeholder="+27 82 123 4567">
                </div>
                <div class="form-group">
                    <label class="form-label" for="invoice_number">Invoice Number</label>
                    <input type="text" id="invoice_number" name="invoice_number" class="form-control"
                           value="<?= htmlspecialchars($_POST['invoice_number'] ?? $order['invoice_number']) ?>">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="job_details">Job Details <span class="required">*</span></label>
                <textarea id="job_details" name="job_details" class="form-control" required rows="4"><?= htmlspecialchars($_POST['job_details'] ?? $order['job_details']) ?></textarea>
            </div>

            <div class="form-group">
                <label class="form-label" for="product_lines">Product Lines</label>
                <textarea id="product_lines" name="product_lines" class="form-control" rows="3"
                          placeholder="One product per line"><?= htmlspecialchars($_POST['product_lines'] ?? $order['product_lines'] ?? '') ?></textarea>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" for="current_stage">Current Stage</label>
                    <select id="current_stage" name="current_stage" class="form-control">
                        <option value="">— No stage —</option>
                        <?php
                        $selStage = (int)($_POST['current_stage'] ?? $order['current_stage'] ?? 0);
                        foreach ($stages as $stage): ?>
                        <option value="<?= $stage['id'] ?>" <?= $selStage === (int)$stage['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($stage['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php if ($order['whatsapp']): ?>
                <div class="form-group" style="display:flex;align-items:flex-end;">
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;padding-bottom:8px;">
                        <input type="checkbox" name="send_whatsapp" value="1" <?= !empty($_POST['send_whatsapp']) ? 'checked' : '' ?>>
                        Send WhatsApp notification on stage change
                    </label>
                </div>
                <?php endif; ?>
            </div>

            <?php foreach ($customFields as $cf): ?>
            <div class="form-group">
                <label class="form-label" for="cf_<?= $cf['id'] ?>">
                    <?= htmlspecialchars($cf['field_label']) ?>
                    <?php if ($cf['is_required']): ?><span class="required">*</span><?php endif; ?>
                </label>
                <?php
                $cfKey = 'cf_' . $cf['id'];
                $cfVal = $_POST[$cfKey] ?? ($savedCf[$cf['id']] ?? '');
                switch ($cf['field_type']):
                    case 'textarea': ?>
                    <textarea id="cf_<?= $cf['id'] ?>" name="cf_<?= $cf['id'] ?>" class="form-control" rows="3"
                              <?= $cf['is_required'] ? 'required' : '' ?>><?= htmlspecialchars($cfVal) ?></textarea>
                    <?php break;
                    case 'checkbox': ?>
                    <input type="checkbox" id="cf_<?= $cf['id'] ?>" name="cf_<?= $cf['id'] ?>"
                           <?= $cfVal ? 'checked' : '' ?>>
                    <?php break;
                    default: ?>
                    <input type="<?= htmlspecialchars($cf['field_type']) ?>" id="cf_<?= $cf['id'] ?>"
                           name="cf_<?= $cf['id'] ?>" class="form-control"
                           value="<?= htmlspecialchars($cfVal) ?>"
                           <?= $cf['is_required'] ? 'required' : '' ?>>
                <?php endswitch; ?>
            </div>
            <?php endforeach; ?>

            <div class="d-flex gap-12">
                <button type="submit" class="btn btn-primary">Save Changes</button>
                <a href="<?= rtrim(APP_URL, '/') ?>/orders/view.php?id=<?= $id ?>" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
