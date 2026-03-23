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
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName  = trim($_POST['last_name']  ?? '');
        $email     = trim($_POST['email']      ?? '');

        if (empty($firstName) || empty($lastName) || empty($email)) {
            $error = 'All fields are required.';
        } elseif (!isValidEmail($email)) {
            $error = 'Please enter a valid email address.';
        } else {
            // Check email is not taken by another user
            $chk = $db->prepare('SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1');
            $chk->execute([$email, $currentUser['id']]);
            if ($chk->fetch()) {
                $error = 'That email address is already in use by another account.';
            } else {
                $db->prepare(
                    'UPDATE users SET first_name=?, last_name=?, email=? WHERE id=?'
                )->execute([$firstName, $lastName, $email, $currentUser['id']]);

                // Refresh current user
                $currentUser = getCurrentUser();
                $success = 'Profile updated successfully.';
            }
        }
    }
}

$pageTitle = 'Profile Settings';
include __DIR__ . '/../includes/header.php';
?>

<nav class="breadcrumb">
    <a href="../index.php">Dashboard</a>
    <span class="breadcrumb-sep">/</span>
    <span class="breadcrumb-current">Profile Settings</span>
</nav>

<?php if ($error): ?>
<div class="alert alert-error"><?= htmlspecialchars($error) ?><button class="alert-close" onclick="this.parentElement.remove()">&times;</button></div>
<?php endif; ?>
<?php if ($success): ?>
<div class="alert alert-success"><?= htmlspecialchars($success) ?><button class="alert-close" onclick="this.parentElement.remove()">&times;</button></div>
<?php endif; ?>

<div class="card" style="max-width:600px;">
    <div class="card-header">
        <h2 class="card-title">Profile Settings</h2>
    </div>
    <div class="card-body">
        <form method="POST" action="" data-validate>
            <?= csrfField() ?>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" for="first_name">First Name <span class="required">*</span></label>
                    <input type="text" id="first_name" name="first_name" class="form-control" required
                           value="<?= htmlspecialchars($_POST['first_name'] ?? $currentUser['first_name']) ?>">
                </div>
                <div class="form-group">
                    <label class="form-label" for="last_name">Last Name <span class="required">*</span></label>
                    <input type="text" id="last_name" name="last_name" class="form-control" required
                           value="<?= htmlspecialchars($_POST['last_name'] ?? $currentUser['last_name']) ?>">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="email">Email Address <span class="required">*</span></label>
                <input type="email" id="email" name="email" class="form-control" required
                       value="<?= htmlspecialchars($_POST['email'] ?? $currentUser['email']) ?>">
            </div>

            <div class="form-group">
                <label class="form-label">Role</label>
                <input type="text" class="form-control" value="<?= htmlspecialchars(ucfirst(str_replace('_', ' ', $currentUser['role']))) ?>" disabled>
                <div class="form-hint">Your role cannot be changed here. Contact an administrator.</div>
            </div>

            <div class="form-group">
                <label class="form-label">Member Since</label>
                <input type="text" class="form-control" value="<?= formatDate($currentUser['created_at'], 'F j, Y') ?>" disabled>
            </div>

            <div class="d-flex gap-12">
                <button type="submit" class="btn btn-primary">Save Changes</button>
                <a href="../index.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
