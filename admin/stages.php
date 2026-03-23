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
$error       = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Invalid security token.');
        redirect(rtrim(APP_URL, '/') . '/admin/stages.php');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'create_stage') {
        $name      = trim($_POST['name'] ?? '');
        $color     = trim($_POST['color'] ?? '#3498db');
        $template  = trim($_POST['whatsapp_template'] ?? '');
        $position  = (int)($_POST['order_position'] ?? 0);
        $isActive  = !empty($_POST['is_active']) ? 1 : 0;

        if ($name === '') {
            setFlash('error', 'Stage name is required.');
        } else {
            $db->prepare(
                'INSERT INTO pf_stages (name, order_position, color, whatsapp_template, is_active)
                 VALUES (?,?,?,?,?)'
            )->execute([$name, $position, $color, $template, $isActive]);
            setFlash('success', 'Stage "' . htmlspecialchars($name) . '" created.');
        }
        redirect(rtrim(APP_URL, '/') . '/admin/stages.php');

    } elseif ($action === 'update_stage') {
        $sid      = (int)($_POST['stage_id'] ?? 0);
        $name     = trim($_POST['name'] ?? '');
        $color    = trim($_POST['color'] ?? '#3498db');
        $template = trim($_POST['whatsapp_template'] ?? '');
        $position = (int)($_POST['order_position'] ?? 0);
        $isActive = !empty($_POST['is_active']) ? 1 : 0;

        if (!$sid || $name === '') {
            setFlash('error', 'Stage name is required.');
        } else {
            $db->prepare(
                'UPDATE pf_stages SET name=?, order_position=?, color=?, whatsapp_template=?, is_active=? WHERE id=?'
            )->execute([$name, $position, $color, $template, $isActive, $sid]);
            setFlash('success', 'Stage updated.');
        }
        redirect(rtrim(APP_URL, '/') . '/admin/stages.php');

    } elseif ($action === 'delete_stage') {
        $sid = (int)($_POST['stage_id'] ?? 0);
        if ($sid) {
            $countStmt = $db->prepare('SELECT COUNT(*) FROM pf_orders WHERE current_stage=?');
            $countStmt->execute([$sid]);
            $inUse = (int)$countStmt->fetchColumn();
            if ($inUse > 0) {
                setFlash('error', 'Cannot delete stage: ' . $inUse . ' order(s) are currently in this stage.');
            } else {
                $db->prepare('DELETE FROM pf_stages WHERE id=?')->execute([$sid]);
                setFlash('success', 'Stage deleted.');
            }
        }
        redirect(rtrim(APP_URL, '/') . '/admin/stages.php');

    } elseif ($action === 'reorder') {
        $positions = json_decode($_POST['positions'] ?? '[]', true);
        if (is_array($positions)) {
            $stmt = $db->prepare('UPDATE pf_stages SET order_position=? WHERE id=?');
            foreach ($positions as $item) {
                if (isset($item['id'], $item['position'])) {
                    $stmt->execute([(int)$item['position'], (int)$item['id']]);
                }
            }
        }
        echo json_encode(['ok' => true]);
        exit;
    }
}

$stages = $db->query('SELECT * FROM pf_stages ORDER BY order_position ASC, id ASC')->fetchAll();

// Get edit ID from GET
$editId    = (int)($_GET['edit'] ?? 0);
$editStage = null;
if ($editId) {
    foreach ($stages as $s) {
        if ((int)$s['id'] === $editId) { $editStage = $s; break; }
    }
}

$pageTitle = 'Workflow Stages';
include __DIR__ . '/../includes/header.php';
?>

<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:20px;">
    <h2 style="margin:0;">Workflow Stages</h2>
    <a href="#add-stage" class="btn btn-primary">+ Add Stage</a>
</div>

<!-- Stages List -->
<div class="card" style="margin-bottom:24px;">
    <div class="card-body" style="padding:0;">
        <?php if (empty($stages)): ?>
        <div style="padding:32px;text-align:center;color:var(--text-muted);">No stages yet. Add one below.</div>
        <?php else: ?>
        <table class="table">
            <thead>
                <tr>
                    <th style="width:30px;">#</th>
                    <th>Name</th>
                    <th>Color</th>
                    <th>Active</th>
                    <th>WhatsApp Template</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($stages as $s): ?>
            <tr>
                <td style="color:var(--text-muted);"><?= $s['order_position'] ?></td>
                <td>
                    <span style="display:inline-flex;align-items:center;gap:8px;">
                        <span style="width:14px;height:14px;border-radius:50%;background:<?= htmlspecialchars($s['color']) ?>;display:inline-block;"></span>
                        <strong><?= htmlspecialchars($s['name']) ?></strong>
                    </span>
                </td>
                <td style="font-family:monospace;"><?= htmlspecialchars($s['color']) ?></td>
                <td><?= $s['is_active'] ? '<span style="color:#27ae60;font-weight:600;">Yes</span>' : '<span style="color:#e74c3c;">No</span>' ?></td>
                <td style="max-width:240px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:0.85em;color:var(--text-muted);" title="<?= htmlspecialchars($s['whatsapp_template']) ?>">
                    <?= htmlspecialchars(truncate($s['whatsapp_template'], 60)) ?>
                </td>
                <td>
                    <a href="?edit=<?= $s['id'] ?>#edit-stage" class="btn btn-secondary btn-sm">Edit</a>
                    <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this stage?');">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="delete_stage">
                        <input type="hidden" name="stage_id" value="<?= $s['id'] ?>">
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

<!-- Edit Stage Form -->
<?php if ($editStage): ?>
<div class="card" id="edit-stage" style="margin-bottom:24px;">
    <div class="card-header"><h2 class="card-title">Edit Stage: <?= htmlspecialchars($editStage['name']) ?></h2></div>
    <div class="card-body">
        <form method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="update_stage">
            <input type="hidden" name="stage_id" value="<?= $editStage['id'] ?>">

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Stage Name <span class="required">*</span></label>
                    <input type="text" name="name" class="form-control" required value="<?= htmlspecialchars($editStage['name']) ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Color</label>
                    <div style="display:flex;gap:8px;align-items:center;">
                        <input type="color" name="color" value="<?= htmlspecialchars($editStage['color']) ?>" style="height:38px;padding:2px;">
                        <input type="text" name="color_text" value="<?= htmlspecialchars($editStage['color']) ?>" class="form-control" style="font-family:monospace;" readonly>
                    </div>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Position</label>
                    <input type="number" name="order_position" class="form-control" value="<?= $editStage['order_position'] ?>" min="0">
                </div>
                <div class="form-group" style="display:flex;align-items:flex-end;padding-bottom:8px;">
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                        <input type="checkbox" name="is_active" value="1" <?= $editStage['is_active'] ? 'checked' : '' ?>>
                        Active
                    </label>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">WhatsApp Template</label>
                <textarea name="whatsapp_template" class="form-control" rows="3"><?= htmlspecialchars($editStage['whatsapp_template']) ?></textarea>
                <div class="form-hint">Use: {customer_name}, {order_id}, {business_name}, {stage_name}</div>
            </div>
            <div class="d-flex gap-12">
                <button type="submit" class="btn btn-primary">Save Changes</button>
                <a href="admin/stages.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- Add Stage Form -->
<div class="card" id="add-stage">
    <div class="card-header"><h2 class="card-title">Add New Stage</h2></div>
    <div class="card-body">
        <form method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="create_stage">

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Stage Name <span class="required">*</span></label>
                    <input type="text" name="name" class="form-control" required placeholder="e.g. Quality Check">
                </div>
                <div class="form-group">
                    <label class="form-label">Color</label>
                    <input type="color" name="color" value="#3498db" style="height:38px;padding:2px;">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Position</label>
                    <input type="number" name="order_position" class="form-control" value="<?= count($stages) + 1 ?>" min="0">
                </div>
                <div class="form-group" style="display:flex;align-items:flex-end;padding-bottom:8px;">
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                        <input type="checkbox" name="is_active" value="1" checked>
                        Active
                    </label>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">WhatsApp Template</label>
                <textarea name="whatsapp_template" class="form-control" rows="3"
                          placeholder="Hi {customer_name}, your order #{order_id} is now in {stage_name}."></textarea>
                <div class="form-hint">Use: {customer_name}, {order_id}, {business_name}, {stage_name}</div>
            </div>
            <button type="submit" class="btn btn-primary">Add Stage</button>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
