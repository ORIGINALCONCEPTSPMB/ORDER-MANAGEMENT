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

$tab    = in_array($_GET['tab'] ?? '', ['archived']) ? 'archived' : 'active';
$search = trim($_GET['search'] ?? '');
$stage  = (int)($_GET['stage'] ?? 0);
$page   = max(1, (int)($_GET['page'] ?? 1));

$perPage = (int)getSetting('orders_per_page', '20');
if ($perPage < 1) $perPage = 20;

$stages = $db->query('SELECT * FROM pf_stages WHERE is_active=1 ORDER BY order_position ASC')->fetchAll();

$isArchived = $tab === 'archived' ? 1 : 0;

// Build WHERE
$where  = ['o.is_archived = ?'];
$params = [$isArchived];

if (!$isAdmin) {
    $where[]  = 'o.created_by = ?';
    $params[] = $currentUser['id'];
}

if ($search !== '') {
    $where[]  = '(o.customer_name LIKE ? OR o.business_name LIKE ? OR o.invoice_number LIKE ?)';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}

if ($stage > 0) {
    $where[]  = 'o.current_stage = ?';
    $params[] = $stage;
}

$whereStr = 'WHERE ' . implode(' AND ', $where);

// Totals for tabs
if ($isAdmin) {
    $countActive   = (int)$db->query('SELECT COUNT(*) FROM pf_orders WHERE is_archived=0')->fetchColumn();
    $countArchived = (int)$db->query('SELECT COUNT(*) FROM pf_orders WHERE is_archived=1')->fetchColumn();
} else {
    $st = $db->prepare('SELECT COUNT(*) FROM pf_orders WHERE is_archived=0 AND created_by=?');
    $st->execute([$currentUser['id']]);
    $countActive = (int)$st->fetchColumn();
    $st = $db->prepare('SELECT COUNT(*) FROM pf_orders WHERE is_archived=1 AND created_by=?');
    $st->execute([$currentUser['id']]);
    $countArchived = (int)$st->fetchColumn();
}

// Count filtered results for pagination
$countStmt = $db->prepare('SELECT COUNT(*) FROM pf_orders o LEFT JOIN pf_stages s ON o.current_stage=s.id ' . $whereStr);
$countStmt->execute($params);
$total  = (int)$countStmt->fetchColumn();
$pages  = max(1, (int)ceil($total / $perPage));
$offset = ($page - 1) * $perPage;

// Fetch orders
$orderStmt = $db->prepare(
    'SELECT o.*, s.name AS stage_name, s.color AS stage_color
     FROM pf_orders o
     LEFT JOIN pf_stages s ON o.current_stage = s.id
     ' . $whereStr . '
     ORDER BY o.created_at DESC
     LIMIT ' . $perPage . ' OFFSET ' . $offset
);
$orderStmt->execute($params);
$orders = $orderStmt->fetchAll();

$pageTitle = 'Orders';
include __DIR__ . '/../includes/header.php';
?>

<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:20px;">
    <h2 style="margin:0;">Orders</h2>
    <a href="<?= rtrim(APP_URL, '/') ?>/orders/create.php" class="btn btn-primary">+ New Order</a>
</div>

<!-- Tabs -->
<div style="display:flex;gap:0;margin-bottom:20px;border-bottom:2px solid var(--border);">
    <a href="?tab=active<?= $search ? '&search=' . urlencode($search) : '' ?><?= $stage ? '&stage=' . $stage : '' ?>"
       style="padding:8px 20px;text-decoration:none;color:<?= $tab === 'active' ? 'var(--primary)' : 'var(--text-muted)' ?>;border-bottom:2px solid <?= $tab === 'active' ? 'var(--primary)' : 'transparent' ?>;margin-bottom:-2px;font-weight:<?= $tab === 'active' ? '600' : '400' ?>;">
        Active Orders <span style="background:var(--border);border-radius:10px;padding:1px 7px;font-size:0.8em;"><?= $countActive ?></span>
    </a>
    <a href="?tab=archived<?= $search ? '&search=' . urlencode($search) : '' ?><?= $stage ? '&stage=' . $stage : '' ?>"
       style="padding:8px 20px;text-decoration:none;color:<?= $tab === 'archived' ? 'var(--primary)' : 'var(--text-muted)' ?>;border-bottom:2px solid <?= $tab === 'archived' ? 'var(--primary)' : 'transparent' ?>;margin-bottom:-2px;font-weight:<?= $tab === 'archived' ? '600' : '400' ?>;">
        Archived <span style="background:var(--border);border-radius:10px;padding:1px 7px;font-size:0.8em;"><?= $countArchived ?></span>
    </a>
</div>

<!-- Filters -->
<form method="GET" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:20px;">
    <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">
    <input type="text" name="search" class="form-control" placeholder="Search customer, business, invoice..."
           value="<?= htmlspecialchars($search) ?>" style="max-width:280px;">
    <select name="stage" class="form-control" style="max-width:200px;">
        <option value="">All Stages</option>
        <?php foreach ($stages as $s): ?>
        <option value="<?= $s['id'] ?>" <?= $stage === (int)$s['id'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($s['name']) ?>
        </option>
        <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-secondary">Filter</button>
    <?php if ($search || $stage): ?>
    <a href="?tab=<?= htmlspecialchars($tab) ?>" class="btn btn-secondary">Clear</a>
    <?php endif; ?>
</form>

<!-- Orders Table -->
<div class="card">
    <div class="card-body" style="padding:0;">
        <div class="table-wrapper">
            <table class="table">
                <thead>
                    <tr>
                        <th>Order #</th>
                        <th>Customer</th>
                        <th>Business</th>
                        <th>Invoice #</th>
                        <th>Stage</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($orders)): ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted" style="padding:32px;">
                            No orders found.
                            <?php if (!$search && !$stage && $tab === 'active'): ?>
                            <a href="<?= rtrim(APP_URL, '/') ?>/orders/create.php">Create the first one</a>.
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($orders as $order): ?>
                    <tr>
                        <td><a href="<?= rtrim(APP_URL, '/') ?>/orders/view.php?id=<?= $order['id'] ?>" class="fw-600"><?= htmlspecialchars(getOrderDisplayNumber($order)) ?></a></td>
                        <td><?= htmlspecialchars($order['customer_name']) ?></td>
                        <td><?= htmlspecialchars($order['business_name'] ?: '—') ?></td>
                        <td><?= htmlspecialchars($order['invoice_number'] ?: '—') ?></td>
                        <td>
                            <?php if ($order['stage_name']): ?>
                            <span class="stage-badge" style="background:<?= htmlspecialchars($order['stage_color']) ?>;color:#fff;padding:3px 10px;border-radius:12px;font-size:0.8em;">
                                <?= htmlspecialchars($order['stage_name']) ?>
                            </span>
                            <?php else: ?>
                            <span style="color:var(--text-muted);font-size:0.85em;">—</span>
                            <?php endif; ?>
                        </td>
                        <td><?= formatDate($order['created_at'], 'M j, Y') ?></td>
                        <td style="display:flex;gap:6px;flex-wrap:wrap;align-items:center;">
                            <a href="<?= rtrim(APP_URL, '/') ?>/orders/view.php?id=<?= $order['id'] ?>" class="btn btn-secondary btn-sm">View</a>
                            <a href="<?= rtrim(APP_URL, '/') ?>/orders/edit.php?id=<?= $order['id'] ?>" class="btn btn-secondary btn-sm">Edit</a>
                            <?php if ($order['whatsapp'] && $order['stage_name']): ?>
                            <?php
                            $msg = parseWhatsAppTemplate($order['whatsapp_template'] ?? '', $order, $order['stage_name']);
                            $waLink = getWhatsAppUrl($order['whatsapp'], $msg);
                            ?>
                            <a href="<?= htmlspecialchars($waLink) ?>" target="_blank"
                               class="btn btn-sm" style="background:#25d366;color:#fff;border-color:#25d366;padding:4px 8px;"
                               title="WhatsApp">
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                            </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if ($pages > 1): ?>
<div style="display:flex;gap:8px;margin-top:20px;justify-content:center;flex-wrap:wrap;">
    <?php for ($i = 1; $i <= $pages; $i++): ?>
    <a href="?tab=<?= htmlspecialchars($tab) ?><?= $search ? '&search=' . urlencode($search) : '' ?><?= $stage ? '&stage=' . $stage : '' ?>&page=<?= $i ?>"
       style="padding:6px 12px;border:1px solid var(--border);border-radius:4px;text-decoration:none;<?= $i === $page ? 'background:var(--primary);color:#fff;border-color:var(--primary);' : '' ?>">
        <?= $i ?>
    </a>
    <?php endfor; ?>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
