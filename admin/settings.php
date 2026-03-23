<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
requireRole('admin');

$currentUser  = getCurrentUser();
$db           = getDb();
$customFields = getCustomFields();

// Handle export backup
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'export_backup') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Invalid security token.');
        redirect(rtrim(APP_URL, '/') . '/admin/settings.php');
    }
    $backup = [
        'exported_at'   => date('c'),
        'pf_settings'   => $db->query('SELECT * FROM pf_settings')->fetchAll(),
        'pf_stages'     => $db->query('SELECT * FROM pf_stages')->fetchAll(),
        'pf_custom_fields' => $db->query('SELECT * FROM pf_custom_fields')->fetchAll(),
        'pf_orders'     => $db->query('SELECT * FROM pf_orders')->fetchAll(),
    ];
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="processflow-backup-' . date('Y-m-d') . '.json"');
    echo json_encode($backup, JSON_PRETTY_PRINT);
    exit;
}

// Handle import backup
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'import_backup') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Invalid security token.');
        redirect(rtrim(APP_URL, '/') . '/admin/settings.php?tab=backup');
    }
    $file = $_FILES['backup_file'] ?? null;
    if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
        setFlash('error', 'No file uploaded or upload error.');
    } else {
        $json = file_get_contents($file['tmp_name']);
        $data = json_decode($json, true);
        if (!is_array($data)) {
            setFlash('error', 'Invalid backup file.');
        } else {
            try {
                $db->beginTransaction();
                if (!empty($data['pf_settings'])) {
                    foreach ($data['pf_settings'] as $row) {
                        saveSetting($row['setting_key'], (string)$row['setting_value']);
                    }
                }
                if (!empty($data['pf_stages'])) {
                    foreach ($data['pf_stages'] as $row) {
                        $db->prepare(
                            'INSERT INTO pf_stages (id,name,order_position,color,whatsapp_template,is_active)
                             VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE
                             name=VALUES(name),order_position=VALUES(order_position),
                             color=VALUES(color),whatsapp_template=VALUES(whatsapp_template),
                             is_active=VALUES(is_active)'
                        )->execute([
                            $row['id'], $row['name'], $row['order_position'],
                            $row['color'], $row['whatsapp_template'], $row['is_active']
                        ]);
                    }
                }
                if (!empty($data['pf_custom_fields'])) {
                    foreach ($data['pf_custom_fields'] as $row) {
                        $db->prepare(
                            'INSERT INTO pf_custom_fields (id,field_label,field_type,is_required,field_order)
                             VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE
                             field_label=VALUES(field_label),field_type=VALUES(field_type),
                             is_required=VALUES(is_required),field_order=VALUES(field_order)'
                        )->execute([
                            $row['id'], $row['field_label'], $row['field_type'],
                            $row['is_required'], $row['field_order']
                        ]);
                    }
                }
                $db->commit();
                setFlash('success', 'Backup imported successfully.');
            } catch (Exception $e) {
                $db->rollBack();
                setFlash('error', 'Import error: ' . $e->getMessage());
            }
        }
    }
    redirect(rtrim(APP_URL, '/') . '/admin/settings.php?tab=backup');
}

// Handle save general settings
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_settings') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Invalid security token.');
    } else {
        $keys = [
            'company_name', 'company_phone', 'company_email', 'company_logo_url',
            'portal_title', 'portal_intro', 'portal_track_mode', 'orders_per_page',
            'enable_whatsapp', 'wa_default_country',
            'invoiceninja_url', 'invoiceninja_token',
        ];
        foreach ($keys as $key) {
            if ($key === 'enable_whatsapp') {
                saveSetting($key, !empty($_POST[$key]) ? '1' : '0');
            } else {
                saveSetting($key, trim($_POST[$key] ?? ''));
            }
        }
        // Order fields enabled/required JSON settings
        if (isset($_POST['order_fields'])) {
            $allFieldKeys = ['business_name','whatsapp','invoice_number','job_details','product_lines'];
            $enabled  = [];
            $required = [];
            foreach ($allFieldKeys as $fk) {
                $enabled[$fk]  = !empty($_POST['order_fields']['enabled'][$fk]);
                $required[$fk] = !empty($_POST['order_fields']['required'][$fk]);
            }
            saveSetting('order_fields_enabled', json_encode($enabled));
            saveSetting('order_fields_required', json_encode($required));
        }
        setFlash('success', 'Settings saved.');
    }
    redirect(rtrim(APP_URL, '/') . '/admin/settings.php?tab=' . htmlspecialchars($_POST['tab'] ?? 'general'));
}

// Handle custom field create
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_custom_field') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Invalid security token.');
    } else {
        $label     = trim($_POST['field_label'] ?? '');
        $type      = $_POST['field_type'] ?? 'text';
        $required  = !empty($_POST['is_required']) ? 1 : 0;
        $order     = (int)($_POST['field_order'] ?? 0);
        $validTypes = ['text','textarea','number','date','checkbox'];
        if ($label && in_array($type, $validTypes)) {
            $db->prepare(
                'INSERT INTO pf_custom_fields (field_label, field_type, is_required, field_order) VALUES (?,?,?,?)'
            )->execute([$label, $type, $required, $order]);
            setFlash('success', 'Custom field added.');
        } else {
            setFlash('error', 'Field label is required.');
        }
    }
    redirect(rtrim(APP_URL, '/') . '/admin/settings.php?tab=custom_fields');
}

// Handle custom field delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_custom_field') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Invalid security token.');
    } else {
        $cfId = (int)($_POST['field_id'] ?? 0);
        if ($cfId) {
            $db->prepare('DELETE FROM pf_custom_fields WHERE id=?')->execute([$cfId]);
            setFlash('success', 'Custom field deleted.');
        }
    }
    redirect(rtrim(APP_URL, '/') . '/admin/settings.php?tab=custom_fields');
}

$settings     = getSettings();
$customFields = getCustomFields();
$validTabs    = ['general','whatsapp','invoiceninja','backup','custom_fields','order_template','customer_portal'];
$activeTab    = in_array($_GET['tab'] ?? '', $validTabs) ? ($_GET['tab']) : 'general';

// Decode order field settings
$orderFieldsEnabled  = json_decode($settings['order_fields_enabled']  ?? '{}', true) ?: [];
$orderFieldsRequired = json_decode($settings['order_fields_required'] ?? '{}', true) ?: [];
$allOrderFields = [
    'business_name'  => 'Business Name',
    'whatsapp'       => 'WhatsApp Number',
    'invoice_number' => 'Invoice Number',
    'job_details'    => 'Job Details',
    'product_lines'  => 'Product Lines',
];

$pageTitle = 'Settings';
include __DIR__ . '/../includes/header.php';
?>

<style>
.settings-tabs { display:flex; gap:0; border-bottom:2px solid var(--border); margin-bottom:24px; flex-wrap:wrap; }
.settings-tab  { padding:8px 18px; text-decoration:none; color:var(--text-muted); border-bottom:2px solid transparent; margin-bottom:-2px; white-space:nowrap; }
.settings-tab.active { color:var(--primary); border-bottom-color:var(--primary); font-weight:600; }
</style>

<h2 style="margin-bottom:20px;">Settings</h2>

<div class="settings-tabs">
    <?php
    $tabs = [
        'general'          => 'General',
        'whatsapp'         => 'WhatsApp',
        'order_template'   => 'Order Template',
        'customer_portal'  => 'Customer Portal',
        'invoiceninja'     => 'InvoiceNinja',
        'backup'           => 'Backup',
        'custom_fields'    => 'Custom Fields',
    ];
    foreach ($tabs as $key => $label): ?>
    <a href="?tab=<?= $key ?>" class="settings-tab <?= $activeTab === $key ? 'active' : '' ?>"><?= $label ?></a>
    <?php endforeach; ?>
</div>

<?php if ($activeTab === 'general'): ?>
<div class="card">
    <div class="card-header"><h2 class="card-title">General Settings</h2></div>
    <div class="card-body">
        <form method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="save_settings">
            <input type="hidden" name="tab" value="general">

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Company Name</label>
                    <input type="text" name="company_name" class="form-control" value="<?= htmlspecialchars($settings['company_name'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Company Phone</label>
                    <input type="text" name="company_phone" class="form-control" value="<?= htmlspecialchars($settings['company_phone'] ?? '') ?>">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Company Email</label>
                <input type="email" name="company_email" class="form-control" value="<?= htmlspecialchars($settings['company_email'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label class="form-label">Company Logo URL</label>
                <input type="url" name="company_logo_url" class="form-control" value="<?= htmlspecialchars($settings['company_logo_url'] ?? '') ?>" placeholder="https://yourdomain.com/logo.png">
                <div class="form-hint">Full URL to your company logo image (shown on customer tracking portal)</div>
            </div>
            <div class="form-group">
                <label class="form-label">Orders Per Page</label>
                <input type="number" name="orders_per_page" class="form-control" value="<?= htmlspecialchars($settings['orders_per_page'] ?? '20') ?>" min="1" max="200" style="max-width:120px;">
            </div>
            <button type="submit" class="btn btn-primary">Save Settings</button>
        </form>
    </div>
</div>

<?php elseif ($activeTab === 'order_template'): ?>
<div class="card">
    <div class="card-header"><h2 class="card-title">Order Form Template</h2></div>
    <div class="card-body">
        <p style="color:var(--text-muted);font-size:0.9em;margin-bottom:20px;">
            Configure which fields appear on the New Order / Edit Order forms.
            <strong>Customer Name</strong> is always required. <strong>WhatsApp Number</strong> is the recommended minimum along with an order/invoice number.
        </p>
        <form method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="save_settings">
            <input type="hidden" name="tab" value="order_template">
            <table class="table" style="max-width:600px;">
                <thead>
                    <tr><th>Field</th><th style="text-align:center;">Enabled</th><th style="text-align:center;">Required</th></tr>
                </thead>
                <tbody>
                <?php foreach ($allOrderFields as $fk => $flabel): ?>
                <tr>
                    <td><?= htmlspecialchars($flabel) ?></td>
                    <td style="text-align:center;">
                        <input type="checkbox" name="order_fields[enabled][<?= $fk ?>]" value="1"
                               <?= !empty($orderFieldsEnabled[$fk]) ? 'checked' : '' ?>
                               id="ofe_<?= $fk ?>">
                    </td>
                    <td style="text-align:center;">
                        <input type="checkbox" name="order_fields[required][<?= $fk ?>]" value="1"
                               <?= !empty($orderFieldsRequired[$fk]) ? 'checked' : '' ?>>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <p style="font-size:0.85em;color:var(--text-muted);">Tip: The minimum recommended setup is <em>WhatsApp Number</em> enabled + required, plus at least one of <em>Invoice Number</em> or <em>Job Details</em>.</p>
            <button type="submit" class="btn btn-primary">Save Order Template</button>
        </form>
    </div>
</div>

<?php elseif ($activeTab === 'customer_portal'): ?>
<div class="card">
    <div class="card-header"><h2 class="card-title">Customer Tracking Portal</h2></div>
    <div class="card-body">
        <form method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="save_settings">
            <input type="hidden" name="tab" value="customer_portal">
            <div class="form-group">
                <label class="form-label">Portal Page Title</label>
                <input type="text" name="portal_title" class="form-control" value="<?= htmlspecialchars($settings['portal_title'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label class="form-label">Portal Intro Text</label>
                <textarea name="portal_intro" class="form-control" rows="2"><?= htmlspecialchars($settings['portal_intro'] ?? '') ?></textarea>
            </div>
            <div class="form-group">
                <label class="form-label">Order Lookup Mode</label>
                <select name="portal_track_mode" class="form-control" style="max-width:360px;">
                    <option value="order_id_only" <?= ($settings['portal_track_mode'] ?? 'order_id_only') === 'order_id_only' ? 'selected' : '' ?>>Order ID only</option>
                    <option value="invoice_number_only" <?= ($settings['portal_track_mode'] ?? '') === 'invoice_number_only' ? 'selected' : '' ?>>Invoice Number only</option>
                    <option value="order_id_wa4" <?= ($settings['portal_track_mode'] ?? '') === 'order_id_wa4' ? 'selected' : '' ?>>Order ID + last 4 digits of WhatsApp</option>
                </select>
                <div class="form-hint">Choose how customers identify their order on the tracking page.</div>
            </div>
            <button type="submit" class="btn btn-primary">Save Portal Settings</button>
        </form>
        <?php if (!empty($settings['company_logo_url'])): ?>
        <div style="margin-top:20px;padding-top:20px;border-top:1px solid var(--border);">
            <div style="font-size:0.85em;color:var(--text-muted);margin-bottom:8px;">Logo Preview:</div>
            <img src="<?= htmlspecialchars($settings['company_logo_url']) ?>" alt="Logo" style="max-height:60px;max-width:240px;">
        </div>
        <?php endif; ?>
    </div>
</div>

<?php elseif ($activeTab === 'whatsapp'): ?>
<div class="card">
    <div class="card-header"><h2 class="card-title">WhatsApp Settings</h2></div>
    <div class="card-body">
        <form method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="save_settings">
            <input type="hidden" name="tab" value="whatsapp">

            <div class="form-group">
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                    <input type="checkbox" name="enable_whatsapp" value="1" <?= !empty($settings['enable_whatsapp']) && $settings['enable_whatsapp'] === '1' ? 'checked' : '' ?>>
                    Enable WhatsApp notifications
                </label>
            </div>
            <div class="form-group">
                <label class="form-label">Default Country Code</label>
                <input type="text" name="wa_default_country" class="form-control"
                       value="<?= htmlspecialchars($settings['wa_default_country'] ?? '27') ?>"
                       placeholder="27" style="max-width:120px;">
                <div class="form-hint">Country code without + (e.g. 27 for South Africa, 1 for USA)</div>
            </div>
            <button type="submit" class="btn btn-primary">Save Settings</button>
        </form>
    </div>
</div>

<?php elseif ($activeTab === 'invoiceninja'): ?>
<div class="card">
    <div class="card-header"><h2 class="card-title">InvoiceNinja Integration</h2></div>
    <div class="card-body">
        <form method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="save_settings">
            <input type="hidden" name="tab" value="invoiceninja">

            <div class="form-group">
                <label class="form-label">InvoiceNinja URL</label>
                <input type="url" name="invoiceninja_url" class="form-control"
                       value="<?= htmlspecialchars($settings['invoiceninja_url'] ?? '') ?>"
                       placeholder="https://invoicing.yourdomain.com">
            </div>
            <div class="form-group">
                <label class="form-label">API Token</label>
                <input type="text" name="invoiceninja_token" class="form-control"
                       value="<?= htmlspecialchars($settings['invoiceninja_token'] ?? '') ?>"
                       placeholder="Your API token">
            </div>
            <div class="d-flex gap-12">
                <button type="submit" class="btn btn-primary">Save Settings</button>
                <button type="button" class="btn btn-secondary" id="test-in-btn">Test Connection</button>
            </div>
        </form>
        <div id="in-test-result" style="margin-top:12px;"></div>
    </div>
</div>
<script>
document.getElementById('test-in-btn').addEventListener('click', function() {
    var res = document.getElementById('in-test-result');
    res.textContent = 'Testing...';
    fetch('<?= rtrim(APP_URL, '/') ?>/admin/settings.php?ajax=test_in', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'csrf_token=<?= generateCsrfToken() ?>&action=test_invoiceninja'
    }).then(function(r){ return r.json(); })
      .then(function(d){
          res.style.color = d.ok ? 'green' : 'red';
          res.textContent = d.message;
      }).catch(function(){
          res.style.color = 'red';
          res.textContent = 'Connection test failed.';
      });
});
</script>

<?php elseif ($activeTab === 'backup'): ?>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;flex-wrap:wrap;">
    <div class="card">
        <div class="card-header"><h2 class="card-title">Export Backup</h2></div>
        <div class="card-body">
            <p style="color:var(--text-muted);font-size:0.9em;margin-bottom:16px;">
                Download a JSON backup of all orders, stages, custom fields and settings.
            </p>
            <form method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="export_backup">
                <button type="submit" class="btn btn-primary">Download Backup</button>
            </form>
        </div>
    </div>
    <div class="card">
        <div class="card-header"><h2 class="card-title">Import Backup</h2></div>
        <div class="card-body">
            <p style="color:var(--text-muted);font-size:0.9em;margin-bottom:16px;">
                Import settings, stages and custom fields from a backup file. Existing data will be merged.
            </p>
            <form method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" enctype="multipart/form-data">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="import_backup">
                <div class="form-group">
                    <input type="file" name="backup_file" class="form-control" accept=".json" required>
                </div>
                <button type="submit" class="btn btn-primary" onclick="return confirm('Import will merge data. Continue?')">Import</button>
            </form>
        </div>
    </div>
</div>

<?php elseif ($activeTab === 'custom_fields'): ?>
<div class="card" style="margin-bottom:24px;">
    <div class="card-header"><h2 class="card-title">Custom Fields</h2></div>
    <div class="card-body" style="padding:0;">
        <?php if (empty($customFields)): ?>
        <div style="padding:24px;text-align:center;color:var(--text-muted);">No custom fields yet.</div>
        <?php else: ?>
        <table class="table">
            <thead>
                <tr><th>Label</th><th>Type</th><th>Required</th><th>Order</th><th>Actions</th></tr>
            </thead>
            <tbody>
            <?php foreach ($customFields as $cf): ?>
            <tr>
                <td><?= htmlspecialchars($cf['field_label']) ?></td>
                <td><?= htmlspecialchars(ucfirst($cf['field_type'])) ?></td>
                <td><?= $cf['is_required'] ? 'Yes' : 'No' ?></td>
                <td><?= $cf['field_order'] ?></td>
                <td>
                    <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this field?');">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="delete_custom_field">
                        <input type="hidden" name="field_id" value="<?= $cf['id'] ?>">
                        <button type="submit" class="btn btn-sm" style="background:#e74c3c;color:#fff;border-color:#e74c3c;">Delete</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="card-header"><h2 class="card-title">Add Custom Field</h2></div>
    <div class="card-body">
        <form method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>?tab=custom_fields">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="add_custom_field">

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Field Label <span class="required">*</span></label>
                    <input type="text" name="field_label" class="form-control" required placeholder="e.g. Artwork Reference">
                </div>
                <div class="form-group">
                    <label class="form-label">Field Type</label>
                    <select name="field_type" class="form-control">
                        <option value="text">Text</option>
                        <option value="textarea">Textarea</option>
                        <option value="number">Number</option>
                        <option value="date">Date</option>
                        <option value="checkbox">Checkbox</option>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Display Order</label>
                    <input type="number" name="field_order" class="form-control" value="<?= count($customFields) + 1 ?>" min="0" style="max-width:120px;">
                </div>
                <div class="form-group" style="display:flex;align-items:flex-end;padding-bottom:8px;">
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                        <input type="checkbox" name="is_required" value="1">
                        Required field
                    </label>
                </div>
            </div>
            <button type="submit" class="btn btn-primary">Add Field</button>
        </form>
    </div>
</div>
<?php endif; ?>

<?php
// AJAX: test InvoiceNinja connection
if (isset($_GET['ajax']) && $_GET['ajax'] === 'test_in' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        echo json_encode(['ok' => false, 'message' => 'Invalid token']);
        exit;
    }
    $inUrl   = getSetting('invoiceninja_url', '');
    $inToken = getSetting('invoiceninja_token', '');
    if (!$inUrl || !$inToken) {
        echo json_encode(['ok' => false, 'message' => 'InvoiceNinja URL and token are required.']);
        exit;
    }
    $ch = curl_init(rtrim($inUrl, '/') . '/api/v1/companies');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['X-Api-Token: ' . $inToken, 'Content-Type: application/json'],
        CURLOPT_TIMEOUT        => 10,
    ]);
    $result = curl_exec($ch);
    $code   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code >= 200 && $code < 300) {
        echo json_encode(['ok' => true, 'message' => 'Connection successful! HTTP ' . $code]);
    } else {
        echo json_encode(['ok' => false, 'message' => 'Connection failed. HTTP ' . $code]);
    }
    exit;
}
?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
