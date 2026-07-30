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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $firstName       = trim($_POST['first_name'] ?? '');
        $lastName        = trim($_POST['last_name'] ?? '');
        $email           = trim($_POST['email'] ?? '');
        $password        = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (empty($firstName) || empty($lastName) || empty($email) || empty($password)) {
            $error = 'All fields are required.';
        } elseif (!isValidEmail($email)) {
            $error = 'Please enter a valid email address.';
        } elseif (!isStrongPassword($password)) {
            $error = 'Password must be at least 8 characters and include an uppercase letter, a lowercase letter, and a number.';
        } elseif ($password !== $confirmPassword) {
            $error = 'Passwords do not match.';
        } else {
            try {
                $db = getDb();

                // Check duplicate email
                $stmt = $db->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
                $stmt->execute([$email]);
                if ($stmt->fetch()) {
                    $error = 'That email address is already registered.';
                } else {
                    $hash = hashPassword($password);
                    $db->prepare(
                        'INSERT INTO users (email, password_hash, first_name, last_name, is_active, is_verified)
                         VALUES (?, ?, ?, ?, 1, 0)'
                    )->execute([$email, $hash, $firstName, $lastName]);

                    $userId    = (int) $db->lastInsertId();
                    $code      = generateCode();
                    $expiresAt = date('Y-m-d H:i:s', time() + CODE_EXPIRY);

                    $db->prepare(
                        'INSERT INTO verification_codes (user_id, code, type, expires_at) VALUES (?,?,?,?)'
                    )->execute([$userId, $code, 'registration', $expiresAt]);

                    sendVerificationCode($email, $firstName, $code, 'registration');

                    $_SESSION['pending_user_id'] = $userId;
                    $_SESSION['pending_email']   = $email;

                    redirect('../auth/verify.php?type=registration');
                }
            } catch (Exception $e) {
                $error = 'A system error occurred. Please try again later.';
            }
        }
    }
}

$pageTitle = 'Create Account';
include __DIR__ . '/../includes/auth_header.php';
?>
        <h2 class="auth-title">Create your account</h2>
        <p class="auth-subtitle">Fill in the details below to get started.</p>

        <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?><button class="alert-close" onclick="this.parentElement.remove()">&times;</button></div>
        <?php endif; ?>

        <form method="POST" action="" data-validate>
            <?= csrfField() ?>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" for="first_name">First Name <span class="required">*</span></label>
                    <input type="text" id="first_name" name="first_name" class="form-control" required
                           value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>" placeholder="Jane">
                </div>
                <div class="form-group">
                    <label class="form-label" for="last_name">Last Name <span class="required">*</span></label>
                    <input type="text" id="last_name" name="last_name" class="form-control" required
                           value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>" placeholder="Doe">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="email">Email Address <span class="required">*</span></label>
                <input type="email" id="email" name="email" class="form-control" required
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" placeholder="you@example.com">
            </div>

            <div class="form-group">
                <label class="form-label" for="password">Password <span class="required">*</span></label>
                <div class="input-group">
                    <input type="password" id="password" name="password" class="form-control password-match-1" required
                           placeholder="Min 8 chars, 1 uppercase, 1 number" autocomplete="new-password">
                    <span class="input-group-append toggle-password" data-target="#password">
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
                       placeholder="Repeat your password" autocomplete="new-password">
            </div>

            <button type="submit" class="btn btn-primary w-100 btn-lg">Create Account</button>
        </form>

        <div class="auth-footer">
            Already have an account? <a href="login.php">Sign in</a>
        </div>
    </div>
</div>
<script src="<?= rtrim(defined('APP_URL') ? APP_URL : '..', '/') ?>/assets/js/app.js"></script>
</body>
</html>
