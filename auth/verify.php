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

$type  = $_GET['type'] ?? 'registration';
$type  = in_array($type, ['registration', 'login_2fa']) ? $type : 'registration';
$error = '';

$pendingUserId = $_SESSION['pending_user_id'] ?? null;
$pendingEmail  = $_SESSION['pending_email']   ?? '';

if (!$pendingUserId) {
    redirect('login.php');
}

// --- Resend code ---
if (isset($_POST['resend']) && validateCsrfToken($_POST['csrf_token'] ?? '')) {
    try {
        $db   = getDb();
        $stmt = $db->prepare('SELECT id, first_name, email FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$pendingUserId]);
        $user = $stmt->fetch();

        if ($user) {
            $code      = generateCode();
            $expiresAt = date('Y-m-d H:i:s', time() + CODE_EXPIRY);
            $db->prepare(
                'UPDATE verification_codes SET used_at = NOW() WHERE user_id = ? AND type = ? AND used_at IS NULL'
            )->execute([$user['id'], $type]);
            $db->prepare(
                'INSERT INTO verification_codes (user_id, code, type, expires_at) VALUES (?,?,?,?)'
            )->execute([$user['id'], $code, $type, $expiresAt]);
            sendVerificationCode($user['email'], $user['first_name'], $code, $type);
            setFlash('success', 'A new code has been sent to ' . $user['email'] . '.');
        }
    } catch (Exception $e) {
        // Silently fail
    }
    redirect('verify.php?type=' . $type);
}

// --- Verify code ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['resend'])) {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please try again.';
    } else {
        // Support both single input and 6 separate digit inputs
        if (!empty($_POST['code'])) {
            $code = trim($_POST['code']);
        } else {
            $digits = [];
            for ($i = 1; $i <= 6; $i++) {
                $digits[] = trim($_POST['digit_' . $i] ?? '');
            }
            $code = implode('', $digits);
        }

        if (strlen($code) !== 6 || !ctype_digit($code)) {
            $error = 'Please enter a valid 6-digit code.';
        } else {
            try {
                $db   = getDb();
                $stmt = $db->prepare(
                    'SELECT id FROM verification_codes
                     WHERE user_id = ? AND code = ? AND type = ?
                       AND expires_at > NOW() AND used_at IS NULL
                     ORDER BY id DESC LIMIT 1'
                );
                $stmt->execute([$pendingUserId, $code, $type]);
                $vc = $stmt->fetch();

                if (!$vc) {
                    $error = 'Invalid or expired code. Please try again or request a new code.';
                } else {
                    // Mark code as used
                    $db->prepare(
                        'UPDATE verification_codes SET used_at = NOW() WHERE id = ?'
                    )->execute([$vc['id']]);

                    if ($type === 'registration') {
                        // Activate user
                        $db->prepare(
                            'UPDATE users SET is_verified = 1, is_active = 1 WHERE id = ?'
                        )->execute([$pendingUserId]);

                        // Fetch user info for welcome email
                        $uStmt = $db->prepare('SELECT first_name, email FROM users WHERE id = ? LIMIT 1');
                        $uStmt->execute([$pendingUserId]);
                        $u = $uStmt->fetch();
                        if ($u) {
                            sendWelcomeEmail($u['email'], $u['first_name']);
                        }
                    }

                    unset($_SESSION['pending_user_id'], $_SESSION['pending_email']);
                    loginUser($pendingUserId);
                    setFlash('success', $type === 'registration' ? 'Account verified! Welcome!' : 'Logged in successfully.');
                    redirect('../index.php');
                }
            } catch (Exception $e) {
                $error = 'A system error occurred. Please try again.';
            }
        }
    }
}

$maskedEmail = '';
if ($pendingEmail) {
    $parts = explode('@', $pendingEmail);
    $name  = $parts[0] ?? '';
    $domain = $parts[1] ?? '';
    $masked = strlen($name) > 2 ? substr($name, 0, 2) . str_repeat('*', strlen($name) - 2) : $name;
    $maskedEmail = $masked . '@' . $domain;
}

$pageTitle = 'Enter Verification Code';
include __DIR__ . '/../includes/auth_header.php';
?>
        <h2 class="auth-title">Verification Code</h2>
        <p class="auth-subtitle">
            We sent a 6-digit code to <strong><?= htmlspecialchars($maskedEmail) ?></strong>.
            Enter it below to continue.
        </p>

        <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?><button class="alert-close" onclick="this.parentElement.remove()">&times;</button></div>
        <?php endif; ?>

        <?php
        $flash2 = $_SESSION['flash'] ?? null;
        if ($flash2) { unset($_SESSION['flash']); }
        if ($flash2 && $flash2['type'] === 'success'):
        ?>
        <div class="alert alert-success"><?= htmlspecialchars($flash2['message']) ?><button class="alert-close" onclick="this.parentElement.remove()">&times;</button></div>
        <?php endif; ?>

        <form method="POST" action="?type=<?= htmlspecialchars($type) ?>" data-validate>
            <?= csrfField() ?>

            <div class="form-group">
                <label class="form-label" for="code">6-Digit Code</label>
                <input type="text" id="code" name="code" class="form-control" required
                       maxlength="6" inputmode="numeric" pattern="[0-9]{6}"
                       placeholder="000000"
                       style="font-size:1.4rem;letter-spacing:.4rem;text-align:center;"
                       autocomplete="one-time-code">
            </div>

            <button type="submit" class="btn btn-primary w-100 btn-lg">Verify</button>
        </form>

        <div class="auth-footer" style="margin-top:16px;">
            <form method="POST" action="?type=<?= htmlspecialchars($type) ?>" style="display:inline;">
                <?= csrfField() ?>
                <input type="hidden" name="resend" value="1">
                <button type="submit" class="btn btn-secondary btn-sm">Resend Code</button>
            </form>
            &nbsp;
            <a href="login.php" style="font-size:.875rem;">Back to Login</a>
        </div>
    </div>
</div>
<script src="<?= rtrim(defined('APP_URL') ? APP_URL : '..', '/') ?>/assets/js/app.js"></script>
</body>
</html>
