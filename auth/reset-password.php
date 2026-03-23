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

if (isLoggedIn()) {
    redirect('../index.php');
}

$token   = trim($_GET['token'] ?? '');
$error   = '';
$success = false;
$validToken = null;

if (empty($token)) {
    setFlash('error', 'Invalid or missing reset token.');
    redirect('login.php');
}

try {
    $db   = getDb();
    $stmt = $db->prepare(
        'SELECT pr.id, pr.user_id, u.email, u.first_name
         FROM password_resets pr
         JOIN users u ON u.id = pr.user_id
         WHERE pr.token = ? AND pr.expires_at > NOW() AND pr.used_at IS NULL
         LIMIT 1'
    );
    $stmt->execute([$token]);
    $validToken = $stmt->fetch();
} catch (Exception $e) {
    $error = 'A system error occurred. Please try again.';
}

if (!$validToken && !$error) {
    setFlash('error', 'This password reset link is invalid or has expired. Please request a new one.');
    redirect('forgot-password.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $validToken) {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $newPassword     = $_POST['new_password']     ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (empty($newPassword)) {
            $error = 'Password is required.';
        } elseif (!isStrongPassword($newPassword)) {
            $error = 'Password must be at least 8 characters and include an uppercase letter, a lowercase letter, and a number.';
        } elseif ($newPassword !== $confirmPassword) {
            $error = 'Passwords do not match.';
        } else {
            try {
                $hash = hashPassword($newPassword);
                $db->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
                   ->execute([$hash, $validToken['user_id']]);
                $db->prepare('UPDATE password_resets SET used_at = NOW() WHERE id = ?')
                   ->execute([$validToken['id']]);
                // Invalidate all active sessions for this user
                $db->prepare('DELETE FROM sessions WHERE user_id = ?')
                   ->execute([$validToken['user_id']]);

                setFlash('success', 'Your password has been reset successfully. Please sign in with your new password.');
                redirect('login.php');
            } catch (Exception $e) {
                $error = 'A system error occurred. Please try again.';
            }
        }
    }
}

$pageTitle = 'Reset Password';
include __DIR__ . '/../includes/auth_header.php';
?>
        <h2 class="auth-title">Set new password</h2>
        <p class="auth-subtitle">Choose a strong new password for your account.</p>

        <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?><button class="alert-close" onclick="this.parentElement.remove()">&times;</button></div>
        <?php endif; ?>

        <form method="POST" action="?token=<?= htmlspecialchars(urlencode($token)) ?>" data-validate>
            <?= csrfField() ?>

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
                <label class="form-label" for="confirm_password">Confirm Password <span class="required">*</span></label>
                <input type="password" id="confirm_password" name="confirm_password" class="form-control password-match-2" required
                       placeholder="Repeat your new password" autocomplete="new-password">
            </div>

            <button type="submit" class="btn btn-primary w-100 btn-lg">Reset Password</button>
        </form>

        <div class="auth-footer">
            <a href="login.php">&larr; Back to Sign In</a>
        </div>
    </div>
</div>
<script src="<?= rtrim(defined('APP_URL') ? APP_URL : '..', '/') ?>/assets/js/app.js"></script>
</body>
</html>
