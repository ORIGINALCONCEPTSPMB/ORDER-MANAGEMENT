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

$error   = '';
$success = '';

if (!empty($_SESSION['flash'])) {
    $flashData = $_SESSION['flash'];
    unset($_SESSION['flash']);
    if ($flashData['type'] === 'success') {
        $success = $flashData['message'];
    } else {
        $error = $flashData['message'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            $error = 'Email and password are required.';
        } elseif (checkLoginAttempts($email)) {
            $lockout = defined('LOCKOUT_DURATION') ? LOCKOUT_DURATION : 900;
            $error   = 'Too many failed login attempts. Please try again in ' . ceil($lockout / 60) . ' minutes.';
        } else {
            try {
                $db   = getDb();
                $stmt = $db->prepare(
                    'SELECT id, email, password_hash, first_name, last_name, is_active, is_verified
                     FROM users WHERE email = ? LIMIT 1'
                );
                $stmt->execute([$email]);
                $user = $stmt->fetch();

                if ($user && verifyPassword($password, $user['password_hash'])) {
                    if (!$user['is_active']) {
                        recordLoginAttempt($email, false);
                        $error = 'Your account has been deactivated. Please contact an administrator.';
                    } elseif (!$user['is_verified']) {
                        // Resend verification code
                        $code      = generateCode();
                        $expiresAt = date('Y-m-d H:i:s', time() + CODE_EXPIRY);
                        $db->prepare(
                            'UPDATE verification_codes SET used_at = NOW() WHERE user_id = ? AND type = ? AND used_at IS NULL'
                        )->execute([$user['id'], 'registration']);
                        $db->prepare(
                            'INSERT INTO verification_codes (user_id, code, type, expires_at) VALUES (?,?,?,?)'
                        )->execute([$user['id'], $code, 'registration', $expiresAt]);

                        sendVerificationCode($user['email'], $user['first_name'], $code, 'registration');
                        $_SESSION['pending_user_id'] = $user['id'];
                        $_SESSION['pending_email']   = $user['email'];
                        redirect('../auth/verify.php?type=registration');
                    } else {
                        // Generate 2FA code
                        $code      = generateCode();
                        $expiresAt = date('Y-m-d H:i:s', time() + CODE_EXPIRY);
                        $db->prepare(
                            'UPDATE verification_codes SET used_at = NOW() WHERE user_id = ? AND type = ? AND used_at IS NULL'
                        )->execute([$user['id'], 'login_2fa']);
                        $db->prepare(
                            'INSERT INTO verification_codes (user_id, code, type, expires_at) VALUES (?,?,?,?)'
                        )->execute([$user['id'], $code, 'login_2fa', $expiresAt]);

                        sendVerificationCode($user['email'], $user['first_name'], $code, 'login_2fa');
                        recordLoginAttempt($email, true);
                        $_SESSION['pending_user_id'] = $user['id'];
                        $_SESSION['pending_email']   = $user['email'];
                        redirect('../auth/verify.php?type=login_2fa');
                    }
                } else {
                    recordLoginAttempt($email, false);
                    $error = 'Invalid email or password.';
                }
            } catch (Exception $e) {
                $error = 'A system error occurred. Please try again later.';
            }
        }
    }
}

$pageTitle = 'Sign In';
include __DIR__ . '/../includes/auth_header.php';
?>
        <h2 class="auth-title">Welcome back</h2>
        <p class="auth-subtitle">Sign in to your account to continue.</p>

        <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?><button class="alert-close" onclick="this.parentElement.remove()">&times;</button></div>
        <?php endif; ?>
        <?php if ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?><button class="alert-close" onclick="this.parentElement.remove()">&times;</button></div>
        <?php endif; ?>

        <form method="POST" action="" data-validate>
            <?= csrfField() ?>

            <div class="form-group">
                <label class="form-label" for="email">Email Address</label>
                <input type="email" id="email" name="email" class="form-control" required
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                       placeholder="you@example.com" autocomplete="email">
            </div>

            <div class="form-group">
                <label class="form-label" for="password">Password</label>
                <div class="input-group">
                    <input type="password" id="password" name="password" class="form-control" required
                           placeholder="Your password" autocomplete="current-password">
                    <span class="input-group-append toggle-password" data-target="#password" title="Show/hide password">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                    </span>
                </div>
            </div>

            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;">
                <label style="display:flex;align-items:center;gap:6px;font-size:.875rem;cursor:pointer;">
                    <input type="checkbox" name="remember_me" value="1"> Remember me
                </label>
                <a href="forgot-password.php" style="font-size:.875rem;">Forgot password?</a>
            </div>

            <button type="submit" class="btn btn-primary w-100 btn-lg">Sign In</button>
        </form>

        <div class="auth-footer">
            Don&rsquo;t have an account? <a href="register.php">Create one</a>
        </div>
    </div><!-- /.auth-card -->
</div><!-- /.auth-container -->
<script src="<?= rtrim(defined('APP_URL') ? APP_URL : '..', '/') ?>/assets/js/app.js"></script>
</body>
</html>
