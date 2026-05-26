<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
requireRole('admin');

$currentUser = getCurrentUser();
$db          = getDb();

// AJAX: fetch InvoiceNinja invoices
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'fetch_invoiceninja') {
    header('Content-Type: application/json');
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        echo json_encode(['ok' => false, 'message' => 'Invalid token']);
        exit;
    }
    $inUrl   = defined('IN_URL')   ? IN_URL   : getSetting('invoiceninja_url', '');
    $inToken = defined('IN_TOKEN') ? IN_TOKEN : getSetting('invoiceninja_token', '');
    if (!$inUrl || !$inToken) {
        echo json_encode(['ok' => false, 'message' => 'InvoiceNinja URL and token not configured.']);
        exit;
    }
    $ch = curl_init(rtrim($inUrl, '/') . '/api/v1/invoices?per_page=50');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['X-Api-Token: ' . $inToken, 'Content-Type: application/json'],
        CURLOPT_TIMEOUT        => 15,
    ]);
    $result = curl_exec($ch);
    $code   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code >= 200 && $code < 300) {
        $decoded = json_decode($result, true);
        echo json_encode(['ok' => true, 'data' => $decoded['data'] ?? []]);
    } else {
        echo json_encode(['ok' => false, 'message' => 'InvoiceNinja returned HTTP ' . $code]);
    }
    exit;
}

// Handle bulk actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['bulk_action'])) {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Invalid security token.');
        redirect(rtrim(APP_URL, '/') . '/admin/orders.php');
    }
    $ids        = array_filter(array_map('intval', $_POST['order_ids'] ?? []));
    $bulkAction = $_POST['bulk_action'];
    $now        = date('Y-m-d H:i:s');

    if (!empty($ids)) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        if ($bulkAction === 'archive') {
            $stmt = $db->prepare("UPDATE pf_orders SET is_archived=1, archived_at=?, updated_at=? WHERE id IN ($placeholders)");
            $stmt->execute(array_merge([$now, $now], $ids));
            setFlash('success', count($ids) . ' order(s) archived.');
        } elseif ($bulkAction === 'unarchive') {
            $stmt = $db->prepare("UPDATE pf_orders SET is_archived=0, archived_at=NULL, updated_at=? WHERE id IN ($placeholders)");
            $stmt->execute(array_merge([$now], $ids));
            setFlash('success', count($ids) . ' order(s) unarchived.');
        } elseif ($bulkAction === 'delete') {
            $stmt = $db->prepare("DELETE FROM pf_orders WHERE id IN ($placeholders)");
            $stmt->execute($ids);
            $db->prepare("DELETE FROM pf_stage_history WHERE order_id IN ($placeholders)")->execute($ids);
            setFlash('success', count($ids) . ' order(s) deleted.');
        } elseif ($bulkAction === 'change_stage' && !empty($_POST['bulk_stage_id'])) {
            $newStage = (int)$_POST['bulk_stage_id'];
            $stmt = $db->prepare("UPDATE pf_orders SET current_stage=?, updated_at=? WHERE id IN ($placeholders)");
            $stmt->execute(array_merge([$newStage, $now], $ids));
            foreach ($ids as $oid) {
                $db->prepare('UPDATE pf_stage_history SET completed_at=? WHERE order_id=? AND completed_at IS NULL')
                   ->execute([$now, $oid]);
                $db->prepare('INSERT INTO pf_stage_history (order_id, stage_id, entered_at) VALUES (?,?,?)')
                   ->execute([$oid, $newStage, $now]);
            }
            setFlash('success', count($ids) . ' order(s) moved to new stage.');
        }
    }
    redirect(rtrim(APP_URL, '/') . '/admin/orders.php?' . http_build_query([
        'stage'    => $_POST['filter_stage'] ?? '',
        'archived' => $_POST['filter_archived'] ?? '',
        'search'   => $_POST['filter_search'] ?? '',
    ]));
}

// Handle InvoiceNinja import
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'import_invoiceninja') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Invalid security token.');
        redirect(rtrim(APP_URL, '/') . '/admin/orders.php');
    }
    $invoices = json_decode($_POST['invoices_json'] ?? '[]', true);
    if (is_array($invoices) && !empty($invoices)) {
        $now     = date('Y-m-d H:i:s');
        $created = 0;
        foreach ($invoices as $inv) {
            $customerName = $inv['client']['name'] ?? ($inv['number'] ?? 'Unknown');
            $invoiceNum   = $inv['number'] ?? '';
            $jobDetails   = 'Imported from InvoiceNinja. Invoice: ' . $invoiceNum;
            $qrHash       = generateQrHash();
            $db->prepare(
                'INSERT INTO pf_orders (customer_name, business_name, invoice_number, job_details,
                 qr_code_hash, created_by, created_at, updated_at)
                 VALUES (?,?,?,?,?,?,?,?)'
            )->execute([
                $customerName, '', $invoiceNum, $jobDetails,
                $qrHash, $currentUser['id'], $now, $now
            ]);
            $created++;
        }
        setFlash('success', $created . ' invoice(s) imported as orders.');
    }
    redirect(rtrim(APP_URL, '/') . '/admin/orders.php');
}

// Handle CSV import
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'import_csv') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Invalid security token.');
        redirect(rtrim(APP_URL, '/') . '/admin/orders.php');
    }
    $file = $_FILES['csv_file'] ?? null;
    if ($file && $file['error'] === UPLOAD_ERR_OK) {
        $handle  = fopen($file['tmp_name'], 'r');
        $headers = fgetcsv($handle);
        $headers = array_map('trim', $headers ?? []);
        $now     = date('Y-m-d H:i:s');
        $created = 0;
        while (($row = fgetcsv($handle)) !== false) {
            $data = array_combine($headers, array_pad($row, count($headers), ''));
            $db->prepare(
                'INSERT INTO pf_orders (customer_name, business_name, whatsapp, invoice_number,
                 job_details, qr_code_hash, created_by, created_at, updated_at)
                 VALUES (?,?,?,?,?,?,?,?,?)'
            )->execute([
                $data['customer_name'] ?? '',
                $data['business_name'] ?? '',
                $data['whatsapp'] ?? '',
                $data['invoice_number'] ?? '',
                $data['job_details'] ?? '',
                generateQrHash(),
                $currentUser['id'], $now, $now
            ]);
            $created++;
        }
        fclose($handle);
        setFlash('success', $created . ' orders imported from CSV.');
    } else {
        setFlash('error', 'CSV upload failed.');
    }
    redirect(rtrim(APP_URL, '/') . '/admin/orders.php');
}

// Filters
$filterStage    = (int)($_GET['stage'] ?? 0);
$filterArchived = $_GET['archived'] ?? 'all';  // all, active, archived
$search         = trim($_GET['search'] ?? '');
$page           = max(1, (int)($_GET['page'] ?? 1));
$perPage        = 50;

$where  = [];
$params = [];

if ($filterArchived === 'active') {
    $where[] = 'o.is_archived = 0';
} elseif ($filterArchived === 'archived') {
    $where[] = 'o.is_archived = 1';
}

if ($filterStage > 0) {
    $where[]  = 'o.current_stage = ?';
    $params[] = $filterStage;
}

if ($search !== '') {
    $where[]  = '(o.customer_name LIKE ? OR o.business_name LIKE ? OR o.invoice_number LIKE ?)';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}

$whereStr = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$countStmt = $db->prepare("SELECT COUNT(*) FROM pf_orders o $whereStr");
$countStmt->execute($params);
$total  = (int)$countStmt->fetchColumn();
$pages  = max(1, (int)ceil($total / $perPage));
$offset = ($page - 1) * $perPage;

$orderStmt = $db->prepare(
    "SELECT o.*, s.name AS stage_name, s.color AS stage_color
     FROM pf_orders o
     LEFT JOIN pf_stages s ON o.current_stage = s.id
     $whereStr
     ORDER BY o.created_at DESC
     LIMIT $perPage OFFSET $offset"
);
$orderStmt->execute($params);
$orders = $orderStmt->fetchAll();

// CSV Export
if (!empty($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="orders-' . date('Y-m-d') . '.csv"');
    $f = fopen('php://output', 'w');
    fputcsv($f, ['ID', 'Customer Name', 'Business Name', 'WhatsApp', 'Invoice #',
                  'Stage', 'Archived', 'Created', 'Updated']);
    foreach ($orders as $o) {
        fputcsv($f, [
            $o['id'], $o['customer_name'], $o['business_name'], $o['whatsapp'],
            $o['invoice_number'], $o['stage_name'] ?? '', $o['is_archived'] ? 'Yes' : 'No',
            $o['created_at'], $o['updated_at'],
        ]);
    }
    fclose($f);
    exit;
}

$stages    = $db->query('SELECT * FROM pf_stages WHERE is_active=1 ORDER BY order_position ASC')->fetchAll();
$pageTitle = 'Manage Orders';
include __DIR__ . '/../includes/header.php';
?>

<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:20px;">
    <h2 style="margin:0;">All Orders</h2>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <a href="?<?= htmlspecialchars(http_build_query(array_merge($_GET, ['export' => 'csv']))) ?>" class="btn btn-secondary">Export CSV</a>
        <a href="<?= rtrim(APP_URL, '/') ?>/orders/create.php" class="btn btn-primary">+ New Order</a>
    </div>
</div>

<!-- Filters -->
<form method="GET" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:20px;align-items:flex-end;">
    <div>
        <input type="text" name="search" class="form-control" placeholder="Search customer, business, invoice..."
               value="<?= htmlspecialchars($search) ?>" style="min-width:220px;">
    </div>
    <div>
        <select name="stage" class="form-control">
            <option value="">All Stages</option>
            <?php foreach ($stages as $s): ?>
            <option value="<?= $s['id'] ?>" <?= $filterStage === (int)$s['id'] ? 'selected' : '' ?>><?= htmlspecialchars($s['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <select name="archived" class="form-control">
            <option value="all" <?= $filterArchived === 'all' ? 'selected' : '' ?>>All Orders</option>
            <option value="active" <?= $filterArchived === 'active' ? 'selected' : '' ?>>Active Only</option>
            <option value="archived" <?= $filterArchived === 'archived' ? 'selected' : '' ?>>Archived Only</option>
        </select>
    </div>
    <button type="submit" class="btn btn-secondary">Filter</button>
    <?php if ($search || $filterStage || $filterArchived !== 'all'): ?>
    <a href="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" class="btn btn-secondary">Clear</a>
    <?php endif; ?>
</form>

<!-- InvoiceNinja Import -->
<div class="card" style="margin-bottom:20px;">
    <div class="card-header" style="cursor:pointer;" onclick="document.getElementById('in-panel').style.display=document.getElementById('in-panel').style.display==='none'?'block':'none';">
        <h2 class="card-title" style="margin:0;">InvoiceNinja Import</h2>
        <span style="color:var(--text-muted);font-size:0.85em;">Click to expand</span>
    </div>
    <div id="in-panel" style="display:none;">
        <div class="card-body">
            <button type="button" class="btn btn-secondary" id="fetch-in-btn">Fetch Invoices from InvoiceNinja</button>
            <div id="in-result" style="margin-top:16px;"></div>
        </div>
    </div>
</div>

<!-- CSV Import -->
<div class="card" style="margin-bottom:20px;">
    <div class="card-header" style="cursor:pointer;" onclick="document.getElementById('csv-panel').style.display=document.getElementById('csv-panel').style.display==='none'?'block':'none';">
        <h2 class="card-title" style="margin:0;">CSV Import</h2>
        <span style="color:var(--text-muted);font-size:0.85em;">Click to expand</span>
    </div>
    <div id="csv-panel" style="display:none;">
        <div class="card-body">
            <p style="font-size:0.85em;color:var(--text-muted);">
                CSV columns: <code>customer_name, business_name, whatsapp, invoice_number, job_details</code>
            </p>
            <form method="POST" enctype="multipart/form-data">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="import_csv">
                <div style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;">
                    <input type="file" name="csv_file" class="form-control" accept=".csv" required>
                    <button type="submit" class="btn btn-primary" onclick="return confirm('Import CSV? This will create new orders.')">Import</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Orders Table -->
<form method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" id="bulk-form">
    <?= csrfField() ?>
    <input type="hidden" name="filter_stage" value="<?= $filterStage ?>">
    <input type="hidden" name="filter_archived" value="<?= htmlspecialchars($filterArchived) ?>">
    <input type="hidden" name="filter_search" value="<?= htmlspecialchars($search) ?>">

    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Orders (<?= $total ?>)</h2>
            <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                <select name="bulk_action" class="form-control" style="font-size:0.85em;">
                    <option value="">Bulk action...</option>
                    <option value="archive">Archive</option>
                    <option value="unarchive">Unarchive</option>
                    <option value="delete">Delete</option>
                    <option value="change_stage">Change Stage</option>
                </select>
                <select name="bulk_stage_id" class="form-control" style="font-size:0.85em;">
                    <option value="">Select stage...</option>
                    <?php foreach ($stages as $s): ?>
                    <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-secondary btn-sm"
                        onclick="return document.querySelectorAll('[name=order_ids]:checked').length > 0 || (alert('Select at least one order.'), false)">
                    Apply
                </button>
                <button type="button" class="btn btn-secondary btn-sm"
                        onclick="printSelectedLabels()">
                    &#x1F5A8; Print QR Labels
                </button>
            </div>
        </div>
        <div class="card-body" style="padding:0;">
            <div class="table-wrapper">
                <table class="table">
                    <thead>
                        <tr>
                            <th><input type="checkbox" id="select-all" onchange="document.querySelectorAll('[name=order_ids]').forEach(c=>c.checked=this.checked)"></th>
                            <th>Order #</th>
                            <th>Customer</th>
                            <th>Business</th>
                            <th>Invoice #</th>
                            <th>WhatsApp</th>
                            <th>Stage</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($orders)): ?>
                    <tr><td colspan="10" class="text-center text-muted" style="padding:24px;">No orders found.</td></tr>
                    <?php else: ?>
                    <?php foreach ($orders as $o): ?>
                    <tr>
                        <td><input type="checkbox" name="order_ids[]" value="<?= $o['id'] ?>"></td>
                        <td><a href="<?= rtrim(APP_URL, '/') ?>/orders/view.php?id=<?= $o['id'] ?>" class="fw-600"><?= htmlspecialchars(getOrderDisplayNumber($o)) ?></a></td>
                        <td><?= htmlspecialchars($o['customer_name']) ?></td>
                        <td><?= htmlspecialchars($o['business_name'] ?: '—') ?></td>
                        <td><?= htmlspecialchars($o['invoice_number'] ?: '—') ?></td>
                        <td style="font-size:0.85em;"><?= htmlspecialchars($o['whatsapp'] ?: '—') ?></td>
                        <td>
                            <?php if ($o['stage_name']): ?>
                            <span class="stage-badge" style="background:<?= htmlspecialchars($o['stage_color']) ?>;color:#fff;padding:3px 10px;border-radius:12px;font-size:0.8em;">
                                <?= htmlspecialchars($o['stage_name']) ?>
                            </span>
                            <?php else: ?>—<?php endif; ?>
                        </td>
                        <td>
                            <?php if ($o['is_archived']): ?>
                            <span style="background:#94a3b8;color:#fff;padding:2px 8px;border-radius:10px;font-size:0.8em;">Archived</span>
                            <?php else: ?>
                            <span style="background:#27ae60;color:#fff;padding:2px 8px;border-radius:10px;font-size:0.8em;">Active</span>
                            <?php endif; ?>
                        </td>
                        <td style="font-size:0.85em;"><?= formatDate($o['created_at'], 'M j, Y') ?></td>
                        <td style="display:flex;gap:4px;flex-wrap:wrap;">
                            <a href="<?= rtrim(APP_URL, '/') ?>/orders/view.php?id=<?= $o['id'] ?>" class="btn btn-secondary btn-sm">View</a>
                            <a href="<?= rtrim(APP_URL, '/') ?>/orders/edit.php?id=<?= $o['id'] ?>" class="btn btn-secondary btn-sm">Edit</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</form>

<?php if ($pages > 1): ?>
<div style="display:flex;gap:8px;margin-top:20px;justify-content:center;flex-wrap:wrap;">
    <?php for ($i = 1; $i <= $pages; $i++): ?>
    <a href="?<?= htmlspecialchars(http_build_query(array_merge($_GET, ['page' => $i]))) ?>"
       style="padding:6px 12px;border:1px solid var(--border);border-radius:4px;text-decoration:none;<?= $i === $page ? 'background:var(--primary);color:#fff;border-color:var(--primary);' : '' ?>">
        <?= $i ?>
    </a>
    <?php endfor; ?>
</div>
<?php endif; ?>

<script>
document.getElementById('fetch-in-btn').addEventListener('click', function() {
    var res = document.getElementById('in-result');
    res.innerHTML = '<em>Fetching invoices...</em>';
    fetch('<?= rtrim(APP_URL, '/') ?>/admin/orders.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'csrf_token=<?= generateCsrfToken() ?>&action=fetch_invoiceninja'
    }).then(function(r){ return r.json(); })
      .then(function(d){
          if (!d.ok) { res.innerHTML = '<span style="color:red;">'+d.message+'</span>'; return; }
          if (!d.data || d.data.length === 0) { res.innerHTML = 'No invoices found.'; return; }
          var html = '<form method="POST"><input type="hidden" name="action" value="import_invoiceninja"><input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>"><table class="table"><thead><tr><th><input type="checkbox" onchange="this.closest(\'table\').querySelectorAll(\'[name=inv]\').forEach(c=>c.checked=this.checked)"></th><th>Invoice #</th><th>Client</th><th>Amount</th></tr></thead><tbody>';
          var selected = [];
          d.data.forEach(function(inv) {
              html += '<tr><td><input type="checkbox" name="inv" value="'+inv.id+'" onchange="updateJson()"></td><td>'+(inv.number||'')+'</td><td>'+((inv.client&&inv.client.name)||'')+'</td><td>'+(inv.amount||'')+'</td></tr>';
              selected.push(inv);
          });
          html += '</tbody></table><input type="hidden" name="invoices_json" id="inv-json" value=""><button type="submit" class="btn btn-primary" onclick="document.getElementById(\'inv-json\').value=JSON.stringify(window._pfInv||[])">Import Selected</button></form>';
          window._pfInvAll = d.data;
          window._pfInv = [];
          res.innerHTML = html;
      }).catch(function(){ res.innerHTML = '<span style="color:red;">Request failed.</span>'; });
});
function updateJson() {
    window._pfInv = (window._pfInvAll||[]).filter(function(inv, i) {
        var cbs = document.querySelectorAll('[name=inv]');
        return cbs[i] && cbs[i].checked;
    });
}
function printSelectedLabels() {
    var checked = document.querySelectorAll('[name="order_ids[]"]:checked');
    if (!checked.length) { alert('Select at least one order first.'); return; }
    var ids = Array.from(checked).map(function(c){ return c.value; }).join(',');
    window.open('<?= rtrim(APP_URL, '/') ?>/orders/print-labels.php?ids=' + ids, '_blank');
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
