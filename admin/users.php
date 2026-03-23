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
require_once __DIR__ . '/../includes/email.php';

requireRole('admin');
$currentUser = getCurrentUser();
$db          = getDb();
$isSuperAdmin = $currentUser['role'] === 'super_admin';
$error = '';

// --- Handle POST actions ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Invalid security token.');
        redirect('users.php');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'add_user') {
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName  = trim($_POST['last_name']  ?? '');
        $email     = trim($_POST['email']      ?? '');
        $password  = $_POST['password'] ?? '';
        $role      = $_POST['role']     ?? 'user';

        if (!in_array($role, ['user','admin','super_admin'])) $role = 'user';
        // Only super_admin can create super_admin accounts
        if ($role === 'super_admin' && !$isSuperAdmin) $role = 'admin';

        if (!$firstName || !$lastName || !$email || !$password) {
            setFlash('error', 'All fields are required to add a user.');
        } elseif (!isValidEmail($email)) {
            setFlash('error', 'Invalid email address.');
        } elseif (!isStrongPassword($password)) {
            setFlash('error', 'Password must be at least 8 chars with uppercase, lowercase, and number.');
        } else {
            $chk = $db->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
            $chk->execute([$email]);
            if ($chk->fetch()) {
                setFlash('error', 'Email address is already registered.');
            } else {
                $hash = hashPassword($password);
                $db->prepare(
                    'INSERT INTO users (email,password_hash,first_name,last_name,role,is_active,is_verified)
                     VALUES (?,?,?,?,?,1,1)'
                )->execute([$email, $hash, $firstName, $lastName, $role]);
                sendWelcomeEmail($email, $firstName);
                setFlash('success', 'User ' . $firstName . ' ' . $lastName . ' created successfully.');
            }
        }
        redirect('users.php');
    }

    if ($action === 'edit_user') {
        $userId = (int) ($_POST['user_id'] ?? 0);
        $role   = $_POST['role'] ?? 'user';
        if (!in_array($role, ['user','admin','super_admin'])) $role = 'user';

        // Protect super_admin from demotion by non-super_admin
        $targetStmt = $db->prepare('SELECT role FROM users WHERE id = ? LIMIT 1');
        $targetStmt->execute([$userId]);
        $target = $targetStmt->fetch();

        if ($target && $target['role'] === 'super_admin' && !$isSuperAdmin) {
            setFlash('error', 'Only a super admin can modify a super admin account.');
        } elseif ($role === 'super_admin' && !$isSuperAdmin) {
            setFlash('error', 'Only a super admin can assign the super admin role.');
        } else {
            $db->prepare('UPDATE users SET role=? WHERE id=?')->execute([$role, $userId]);
            setFlash('success', 'User role updated.');
        }
        redirect('users.php');
    }

    if ($action === 'toggle_active') {
        $userId = (int) ($_POST['user_id'] ?? 0);
        $targetStmt = $db->prepare('SELECT role, is_active FROM users WHERE id = ? LIMIT 1');
        $targetStmt->execute([$userId]);
        $target = $targetStmt->fetch();

        if ($target && $target['role'] === 'super_admin') {
            setFlash('error', 'Cannot deactivate the super admin account.');
        } elseif ($userId === $currentUser['id']) {
            setFlash('error', 'You cannot deactivate your own account.');
        } else {
            $newStatus = $target['is_active'] ? 0 : 1;
            $db->prepare('UPDATE users SET is_active=? WHERE id=?')->execute([$newStatus, $userId]);
            setFlash('success', 'User ' . ($newStatus ? 'activated' : 'deactivated') . ' successfully.');
        }
        redirect('users.php');
    }

    if ($action === 'reset_password') {
        $userId = (int) ($_POST['user_id'] ?? 0);
        $targetStmt = $db->prepare('SELECT email, first_name, role FROM users WHERE id = ? LIMIT 1');
        $targetStmt->execute([$userId]);
        $target = $targetStmt->fetch();

        if ($target && $target['role'] === 'super_admin' && !$isSuperAdmin) {
            setFlash('error', 'Only a super admin can reset a super admin password.');
        } elseif ($target) {
            $tempPassword = ucfirst(substr(bin2hex(random_bytes(4)), 0, 6)) . random_int(100,999) . '!';
            $hash = hashPassword($tempPassword);
            $db->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([$hash, $userId]);

            // Send email with temp password
            $html = "<p>Hi {$target['first_name']},</p>
                     <p>Your password has been reset by an administrator. Your temporary password is:</p>
                     <p style='font-size:1.2rem;font-weight:bold;letter-spacing:.1em;color:#2563eb;'>{$tempPassword}</p>
                     <p>Please log in and change this password immediately.</p>";
            sendEmail($target['email'], 'Your password has been reset', $html);
            setFlash('success', 'Password reset. Temporary password sent to ' . $target['email'] . '.');
        }
        redirect('users.php');
    }
}

// Fetch all users
$users = $db->query(
    'SELECT id, email, first_name, last_name, role, is_active, is_verified, created_at FROM users ORDER BY created_at DESC'
)->fetchAll();

$pageTitle = 'Manage Users';
include __DIR__ . '/../includes/header.php';
?>

<div class="actions-row">
    <h2 class="card-title">All Users (<?= count($users) ?>)</h2>
    <button class="btn btn-primary" data-modal="add-user-modal">+ Add User</button>
</div>

<div class="card">
    <div class="card-body" style="padding:0;">
        <div class="table-wrapper">
            <table class="table" id="searchable-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Verified</th>
                        <th>Joined</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($users as $u): ?>
                <tr>
                    <td><?= htmlspecialchars($u['first_name'] . ' ' . $u['last_name']) ?></td>
                    <td><?= htmlspecialchars($u['email']) ?></td>
                    <td>
                        <?php if ($u['id'] === $currentUser['id'] || ($u['role'] === 'super_admin' && !$isSuperAdmin)): ?>
                            <span class="badge badge-secondary"><?= ucfirst(str_replace('_', ' ', $u['role'])) ?></span>
                        <?php else: ?>
                        <form method="POST" action="" style="display:inline;">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="edit_user">
                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                            <select name="role" class="form-control" style="width:auto;display:inline;padding:4px 8px;"
                                    onchange="this.form.submit()">
                                <option value="user"        <?= $u['role']==='user'        ? 'selected':'' ?>>User</option>
                                <option value="admin"       <?= $u['role']==='admin'       ? 'selected':'' ?>>Admin</option>
                                <?php if ($isSuperAdmin): ?>
                                <option value="super_admin" <?= $u['role']==='super_admin' ? 'selected':'' ?>>Super Admin</option>
                                <?php endif; ?>
                            </select>
                        </form>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($u['is_active']): ?>
                        <span class="badge badge-completed">Active</span>
                        <?php else: ?>
                        <span class="badge badge-cancelled">Inactive</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($u['is_verified']): ?>
                        <span class="badge badge-completed">Yes</span>
                        <?php else: ?>
                        <span class="badge badge-pending">No</span>
                        <?php endif; ?>
                    </td>
                    <td><?= formatDate($u['created_at'], 'M j, Y') ?></td>
                    <td>
                        <?php if ($u['id'] !== $currentUser['id'] && !($u['role']==='super_admin' && !$isSuperAdmin)): ?>
                        <form method="POST" action="" style="display:inline;">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="toggle_active">
                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                            <button type="submit" class="btn btn-<?= $u['is_active'] ? 'warning' : 'success' ?> btn-sm">
                                <?= $u['is_active'] ? 'Deactivate' : 'Activate' ?>
                            </button>
                        </form>
                        <form method="POST" action="" style="display:inline;">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="reset_password">
                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                            <button type="submit" class="btn btn-secondary btn-sm confirm-delete"
                                    data-confirm="Reset password for <?= htmlspecialchars($u['email']) ?>? A temp password will be sent by email.">
                                Reset PW
                            </button>
                        </form>
                        <?php else: ?>
                        <span class="text-muted text-sm">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add User Modal -->
<div class="modal-backdrop" id="add-user-modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Add New User</h3>
            <button class="modal-close-btn" type="button">&times;</button>
        </div>
        <form method="POST" action="" data-validate>
            <?= csrfField() ?>
            <input type="hidden" name="action" value="add_user">
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="m_first_name">First Name <span class="required">*</span></label>
                        <input type="text" id="m_first_name" name="first_name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="m_last_name">Last Name <span class="required">*</span></label>
                        <input type="text" id="m_last_name" name="last_name" class="form-control" required>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label" for="m_email">Email <span class="required">*</span></label>
                    <input type="email" id="m_email" name="email" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="m_password">Password <span class="required">*</span></label>
                    <input type="password" id="m_password" name="password" class="form-control" required
                           placeholder="Min 8 chars, 1 uppercase, 1 number">
                </div>
                <div class="form-group">
                    <label class="form-label" for="m_role">Role</label>
                    <select id="m_role" name="role" class="form-control">
                        <option value="user">User</option>
                        <option value="admin">Admin</option>
                        <?php if ($isSuperAdmin): ?>
                        <option value="super_admin">Super Admin</option>
                        <?php endif; ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary modal-close-btn">Cancel</button>
                <button type="submit" class="btn btn-primary">Create User</button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
