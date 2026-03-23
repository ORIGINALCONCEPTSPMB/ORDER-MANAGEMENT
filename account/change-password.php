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
$db          = getDb();
$error       = '';
$success     = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword     = $_POST['new_password']     ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        // Fetch current hash
        $stmt = $db->prepare('SELECT password_hash FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$currentUser['id']]);
        $row = $stmt->fetch();

        if (!$row || !verifyPassword($currentPassword, $row['password_hash'])) {
            $error = 'Current password is incorrect.';
        } elseif (!isStrongPassword($newPassword)) {
            $error = 'New password must be at least 8 characters and include an uppercase letter, a lowercase letter, and a number.';
        } elseif ($newPassword !== $confirmPassword) {
            $error = 'New passwords do not match.';
        } else {
            $hash = hashPassword($newPassword);
            $db->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
               ->execute([$hash, $currentUser['id']]);
            $success = 'Password changed successfully.';
        }
    }
}

$pageTitle = 'Change Password';
include __DIR__ . '/../includes/header.php';
?>

<nav class="breadcrumb">
    <a href="../index.php">Dashboard</a>
    <span class="breadcrumb-sep">/</span>
    <span class="breadcrumb-current">Change Password</span>
</nav>

<?php if ($error): ?>
<div class="alert alert-error"><?= htmlspecialchars($error) ?><button class="alert-close" onclick="this.parentElement.remove()">&times;</button></div>
<?php endif; ?>
<?php if ($success): ?>
<div class="alert alert-success"><?= htmlspecialchars($success) ?><button class="alert-close" onclick="this.parentElement.remove()">&times;</button></div>
<?php endif; ?>

<div class="card" style="max-width:480px;">
    <div class="card-header">
        <h2 class="card-title">Change Password</h2>
    </div>
    <div class="card-body">
        <form method="POST" action="" data-validate>
            <?= csrfField() ?>

            <div class="form-group">
                <label class="form-label" for="current_password">Current Password <span class="required">*</span></label>
                <div class="input-group">
                    <input type="password" id="current_password" name="current_password" class="form-control" required autocomplete="current-password">
                    <span class="input-group-append toggle-password" data-target="#current_password">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                    </span>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="new_password">New Password <span class="required">*</span></label>
                <div class="input-group">
                    <input type="password" id="new_password" name="new_password" class="form-control password-match-1" required
                           placeholder="Min 8 chars, 1 uppercase, 1 number" autocomplete="new-password">
                    <span class="input-group-append toggle-password" data-target="#new_password">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                    </span>
                </div>
                <div class="password-strength-wrap mt-4">
                    <div class="password-strength-bar"><div class="password-strength-fill"></div></div>
                    <div class="password-strength-text"></div>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="confirm_password">Confirm New Password <span class="required">*</span></label>
                <input type="password" id="confirm_password" name="confirm_password" class="form-control password-match-2" required autocomplete="new-password">
            </div>

            <div class="d-flex gap-12">
                <button type="submit" class="btn btn-primary">Update Password</button>
                <a href="../index.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
