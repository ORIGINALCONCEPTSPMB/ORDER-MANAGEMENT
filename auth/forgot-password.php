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

if (isLoggedIn()) {
    redirect('../index.php');
}

$success = false;
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $email = trim($_POST['email'] ?? '');

        if (empty($email) || !isValidEmail($email)) {
            $error = 'Please enter a valid email address.';
        } else {
            try {
                $db   = getDb();
                $stmt = $db->prepare(
                    'SELECT id, first_name FROM users WHERE email = ? AND is_active = 1 LIMIT 1'
                );
                $stmt->execute([$email]);
                $user = $stmt->fetch();

                if ($user) {
                    $token     = generateToken(32);
                    $expiresAt = date('Y-m-d H:i:s', time() + RESET_TOKEN_EXPIRY);

                    // Invalidate any previous tokens
                    $db->prepare(
                        'UPDATE password_resets SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL'
                    )->execute([$user['id']]);

                    $db->prepare(
                        'INSERT INTO password_resets (user_id, token, expires_at) VALUES (?,?,?)'
                    )->execute([$user['id'], $token, $expiresAt]);

                    $resetUrl = rtrim(APP_URL, '/') . '/auth/reset-password.php?token=' . urlencode($token);
                    sendPasswordResetEmail($email, $user['first_name'], $resetUrl);
                }

                // Always show the same message to avoid user enumeration
                $success = true;
            } catch (Exception $e) {
                $error = 'A system error occurred. Please try again later.';
            }
        }
    }
}

$pageTitle = 'Forgot Password';
include __DIR__ . '/../includes/auth_header.php';
?>
        <h2 class="auth-title">Forgot your password?</h2>
        <p class="auth-subtitle">Enter your email address and we'll send you a reset link.</p>

        <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?><button class="alert-close" onclick="this.parentElement.remove()">&times;</button></div>
        <?php endif; ?>

        <?php if ($success): ?>
        <div class="alert alert-success">
            If that email address is registered, you will receive a password reset link shortly.
        </div>
        <?php else: ?>
        <form method="POST" action="" data-validate>
            <?= csrfField() ?>

            <div class="form-group">
                <label class="form-label" for="email">Email Address</label>
                <input type="email" id="email" name="email" class="form-control" required
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                       placeholder="you@example.com" autocomplete="email">
            </div>

            <button type="submit" class="btn btn-primary w-100 btn-lg">Send Reset Link</button>
        </form>
        <?php endif; ?>

        <div class="auth-footer">
            <a href="login.php">&larr; Back to Sign In</a>
        </div>
    </div>
</div>
<script src="<?= rtrim(defined('APP_URL') ? APP_URL : '..', '/') ?>/assets/js/app.js"></script>
</body>
</html>
