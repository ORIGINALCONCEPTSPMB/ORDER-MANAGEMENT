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

$stages       = $db->query('SELECT * FROM pf_stages WHERE is_active=1 ORDER BY order_position ASC')->fetchAll();
$customFields = getCustomFields();

// Load order field visibility/required settings
$orderFieldsEnabled  = json_decode(getSetting('order_fields_enabled',  '{}'), true) ?: [];
$orderFieldsRequired = json_decode(getSetting('order_fields_required', '{}'), true) ?: [];
// Defaults: all enabled
$fieldEnabled  = function(string $k) use ($orderFieldsEnabled)  { return !isset($orderFieldsEnabled[$k])  || $orderFieldsEnabled[$k]; };
$fieldRequired = function(string $k) use ($orderFieldsRequired) { return !empty($orderFieldsRequired[$k]); };

$error = '';
$post  = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $post = $_POST;
        $customerName  = trim($post['customer_name']  ?? '');
        $businessName  = trim($post['business_name']  ?? '');
        $whatsapp      = trim($post['whatsapp']        ?? '');
        $invoiceNumber = trim($post['invoice_number']  ?? '');
        $jobDetails    = trim($post['job_details']     ?? '');
        $productLines  = trim($post['product_lines']   ?? '');
        $currentStage  = !empty($post['current_stage']) ? (int)$post['current_stage'] : null;

        if (empty($customerName)) {
            $error = 'Customer name is required.';
        } elseif ($fieldRequired('whatsapp') && empty($whatsapp)) {
            $error = 'WhatsApp number is required.';
        } elseif ($fieldRequired('invoice_number') && empty($invoiceNumber)) {
            $error = 'Invoice number is required.';
        } elseif ($fieldRequired('job_details') && empty($jobDetails)) {
            $error = 'Job details are required.';
        } else {
            // Collect custom field values
            $cfValues = [];
            foreach ($customFields as $cf) {
                $key = 'cf_' . $cf['id'];
                if ($cf['field_type'] === 'checkbox') {
                    $cfValues[$cf['id']] = isset($post[$key]) ? '1' : '0';
                } else {
                    $cfValues[$cf['id']] = trim($post[$key] ?? '');
                }
                if ($cf['is_required'] && $cf['field_type'] !== 'checkbox' && $cfValues[$cf['id']] === '') {
                    $error = htmlspecialchars($cf['field_label']) . ' is required.';
                    break;
                }
            }

            if (!$error) {
                $qrHash = generateQrHash();
                $now    = date('Y-m-d H:i:s');

                $stmt = $db->prepare(
                    'INSERT INTO pf_orders
                        (customer_name, business_name, whatsapp, invoice_number, job_details,
                         product_lines, current_stage, qr_code_hash, custom_fields,
                         created_by, created_at, updated_at)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?,?)'
                );
                $stmt->execute([
                    $customerName, $businessName, $whatsapp, $invoiceNumber, $jobDetails,
                    $productLines ?: null, $currentStage, $qrHash,
                    !empty($cfValues) ? json_encode($cfValues) : null,
                    $currentUser['id'], $now, $now
                ]);
                $orderId = (int)$db->lastInsertId();

                if ($currentStage) {
                    $db->prepare(
                        'INSERT INTO pf_stage_history (order_id, stage_id, entered_at) VALUES (?,?,?)'
                    )->execute([$orderId, $currentStage, $now]);
                }

                setFlash('success', 'Order #' . $orderId . ' created successfully.');
                redirect(rtrim(APP_URL, '/') . '/orders/view.php?id=' . $orderId);
            }
        }
    }
}

$pageTitle = 'New Order';
include __DIR__ . '/../includes/header.php';
?>

<nav class="breadcrumb">
    <a href="<?= rtrim(APP_URL, '/') ?>/index.php">Dashboard</a>
    <span class="breadcrumb-sep">/</span>
    <a href="<?= rtrim(APP_URL, '/') ?>/orders/index.php">Orders</a>
    <span class="breadcrumb-sep">/</span>
    <span class="breadcrumb-current">New Order</span>
</nav>

<?php if ($error): ?>
<div class="alert alert-error"><?= htmlspecialchars($error) ?><button class="alert-close" onclick="this.parentElement.remove()">&times;</button></div>
<?php endif; ?>

<div class="card">
    <div class="card-header"><h2 class="card-title">New Order</h2></div>
    <div class="card-body">
        <form method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>">
            <?= csrfField() ?>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" for="customer_name">Customer Name <span class="required">*</span></label>
                    <input type="text" id="customer_name" name="customer_name" class="form-control" required
                           value="<?= htmlspecialchars($post['customer_name'] ?? '') ?>" placeholder="Full name">
                </div>
                <?php if ($fieldEnabled('business_name')): ?>
                <div class="form-group">
                    <label class="form-label" for="business_name">Business Name<?= $fieldRequired('business_name') ? ' <span class="required">*</span>' : '' ?></label>
                    <input type="text" id="business_name" name="business_name" class="form-control"
                           <?= $fieldRequired('business_name') ? 'required' : '' ?>
                           value="<?= htmlspecialchars($post['business_name'] ?? '') ?>" placeholder="Business or trading name">
                </div>
                <?php endif; ?>
            </div>

            <div class="form-row">
                <?php if ($fieldEnabled('whatsapp')): ?>
                <div class="form-group">
                    <label class="form-label" for="whatsapp">WhatsApp Number<?= $fieldRequired('whatsapp') ? ' <span class="required">*</span>' : '' ?></label>
                    <input type="tel" id="whatsapp" name="whatsapp" class="form-control"
                           <?= $fieldRequired('whatsapp') ? 'required' : '' ?>
                           value="<?= htmlspecialchars($post['whatsapp'] ?? '') ?>" placeholder="+27 82 123 4567">
                </div>
                <?php endif; ?>
                <?php if ($fieldEnabled('invoice_number')): ?>
                <div class="form-group">
                    <label class="form-label" for="invoice_number">Invoice Number<?= $fieldRequired('invoice_number') ? ' <span class="required">*</span>' : '' ?></label>
                    <input type="text" id="invoice_number" name="invoice_number" class="form-control"
                           <?= $fieldRequired('invoice_number') ? 'required' : '' ?>
                           value="<?= htmlspecialchars($post['invoice_number'] ?? '') ?>" placeholder="INV-0001">
                </div>
                <?php endif; ?>
            </div>

            <?php if ($fieldEnabled('job_details')): ?>
            <div class="form-group">
                <label class="form-label" for="job_details">Job Details<?= $fieldRequired('job_details') ? ' <span class="required">*</span>' : '' ?></label>
                <textarea id="job_details" name="job_details" class="form-control"
                          <?= $fieldRequired('job_details') ? 'required' : '' ?>
                          rows="4" placeholder="Describe the job requirements..."><?= htmlspecialchars($post['job_details'] ?? '') ?></textarea>
            </div>
            <?php endif; ?>

            <?php if ($fieldEnabled('product_lines')): ?>
            <div class="form-group">
                <label class="form-label" for="product_lines">Product Lines<?= $fieldRequired('product_lines') ? ' <span class="required">*</span>' : '' ?></label>
                <textarea id="product_lines" name="product_lines" class="form-control"
                          <?= $fieldRequired('product_lines') ? 'required' : '' ?>
                          rows="3" placeholder="One product per line"><?= htmlspecialchars($post['product_lines'] ?? '') ?></textarea>
            </div>
            <?php endif; ?>

            <div class="form-group">
                <label class="form-label" for="current_stage">Current Stage</label>
                <select id="current_stage" name="current_stage" class="form-control">
                    <option value="">— No stage —</option>
                    <?php foreach ($stages as $stage): ?>
                    <option value="<?= $stage['id'] ?>"
                        <?= ((int)($post['current_stage'] ?? 0)) === (int)$stage['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($stage['name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <?php foreach ($customFields as $cf): ?>
            <div class="form-group">
                <label class="form-label" for="cf_<?= $cf['id'] ?>">
                    <?= htmlspecialchars($cf['field_label']) ?>
                    <?php if ($cf['is_required']): ?><span class="required">*</span><?php endif; ?>
                </label>
                <?php
                $cfKey = 'cf_' . $cf['id'];
                $cfVal = $post[$cfKey] ?? '';
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
                <button type="submit" class="btn btn-primary">Create Order</button>
                <a href="<?= rtrim(APP_URL, '/') ?>/orders/index.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
