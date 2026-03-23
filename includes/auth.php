<?php
/**
 * Authentication helpers for the Order Management System
 */

/**
 * Check whether the current request belongs to an authenticated, active session
 */
function isLoggedIn(): bool {
    if (empty($_SESSION['user_id']) || empty($_SESSION['session_token'])) {
        return false;
    }

    try {
        $db = getDb();
        $stmt = $db->prepare(
            'SELECT id FROM sessions
             WHERE user_id = ? AND token = ? AND expires_at > NOW()
             LIMIT 1'
        );
        $stmt->execute([$_SESSION['user_id'], $_SESSION['session_token']]);
        return (bool) $stmt->fetch();
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Require the user to be logged in; redirect to login otherwise
 */
function requireLogin(): void {
    if (!isLoggedIn()) {
        setFlash('error', 'Please log in to access that page.');
        redirect(getBaseUrl() . '/auth/login.php');
    }
}

/**
 * Require the user to have at least the given role.
 * Hierarchy: super_admin > admin > user
 */
function requireRole(string $role): void {
    requireLogin();
    $user = getCurrentUser();

    if (!$user) {
        setFlash('error', 'Session expired. Please log in again.');
        redirect(getBaseUrl() . '/auth/login.php');
    }

    $hierarchy = ['user' => 1, 'admin' => 2, 'super_admin' => 3];
    $required   = $hierarchy[$role] ?? 1;
    $actual     = $hierarchy[$user['role']] ?? 1;

    if ($actual < $required) {
        setFlash('error', 'You do not have permission to access that page.');
        redirect(getBaseUrl() . '/index.php');
    }
}

/**
 * Return the current authenticated user record from the database
 */
function getCurrentUser(): ?array {
    if (empty($_SESSION['user_id'])) {
        return null;
    }

    try {
        $db   = getDb();
        $stmt = $db->prepare(
            'SELECT id, email, role, first_name, last_name, is_active, is_verified, created_at
             FROM users WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch() ?: null;
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Create a session record in the database and populate $_SESSION
 */
function loginUser(int $userId): void {
    $token     = bin2hex(random_bytes(32));
    $expiresAt = date('Y-m-d H:i:s', time() + SESSION_LIFETIME);
    $ip        = getClientIp();
    $ua        = $_SERVER['HTTP_USER_AGENT'] ?? '';

    $db   = getDb();
    $stmt = $db->prepare(
        'INSERT INTO sessions (user_id, token, ip_address, user_agent, expires_at)
         VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->execute([$userId, $token, $ip, $ua, $expiresAt]);

    $_SESSION['user_id']       = $userId;
    $_SESSION['session_token'] = $token;

    // Regenerate session ID to prevent session fixation
    session_regenerate_id(true);
}

/**
 * Destroy the current session and remove the DB record
 */
function logoutUser(): void {
    if (!empty($_SESSION['session_token'])) {
        try {
            $db   = getDb();
            $stmt = $db->prepare('DELETE FROM sessions WHERE token = ?');
            $stmt->execute([$_SESSION['session_token']]);
        } catch (Exception $e) {
            // Ignore DB errors on logout
        }
    }

    unset($_SESSION['user_id'], $_SESSION['session_token']);
    session_destroy();
}

/**
 * Hash a plaintext password
 */
function hashPassword(string $password): string {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
}

/**
 * Verify a plaintext password against a stored hash
 */
function verifyPassword(string $password, string $hash): bool {
    return password_verify($password, $hash);
}

/**
 * Check whether an email address is currently locked out due to too many failed attempts.
 * Returns true if locked out.
 */
function checkLoginAttempts(string $email): bool {
    $key = 'login_attempts';
    if (empty($_SESSION[$key][$email])) {
        return false;
    }

    $data      = $_SESSION[$key][$email];
    $attempts  = $data['count'] ?? 0;
    $lastTime  = $data['last_attempt'] ?? 0;
    $maxAttempts = defined('MAX_LOGIN_ATTEMPTS') ? MAX_LOGIN_ATTEMPTS : 5;
    $lockout     = defined('LOCKOUT_DURATION')   ? LOCKOUT_DURATION   : 900;

    if ($attempts >= $maxAttempts) {
        if ((time() - $lastTime) < $lockout) {
            return true;
        }
        // Lockout period expired – reset
        unset($_SESSION[$key][$email]);
    }

    return false;
}

/**
 * Record a login attempt for the given email address
 */
function recordLoginAttempt(string $email, bool $success): void {
    $key = 'login_attempts';

    if ($success) {
        unset($_SESSION[$key][$email]);
        return;
    }

    if (empty($_SESSION[$key][$email])) {
        $_SESSION[$key][$email] = ['count' => 0, 'last_attempt' => 0];
    }

    $_SESSION[$key][$email]['count']++;
    $_SESSION[$key][$email]['last_attempt'] = time();
}

/**
 * Derive the application base URL for redirects.
 * Prefers the APP_URL constant when defined.
 */
function getBaseUrl(): string {
    if (defined('APP_URL')) {
        return rtrim(APP_URL, '/');
    }
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host;
}
