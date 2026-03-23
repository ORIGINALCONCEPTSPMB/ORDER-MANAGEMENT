<?php
/**
 * Helper functions for the Order Management System
 */

/**
 * Redirect to a URL and exit
 */
function redirect(string $url): void {
    header('Location: ' . $url);
    exit;
}

/**
 * Sanitize user input
 */
function sanitize(string $input): string {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Generate a cryptographically secure random token
 */
function generateToken(int $length = 32): string {
    return bin2hex(random_bytes($length));
}

/**
 * Generate a random 6-digit numeric code
 */
function generateCode(int $length = 6): string {
    $min = (int) str_pad('1', $length, '0');
    $max = (int) str_pad('9', $length, '9');
    return str_pad((string) random_int($min, $max), $length, '0', STR_PAD_LEFT);
}

/**
 * Generate a unique order number in the format ORD-YYYYMMDD-XXXXX
 */
function generateOrderNumber(): string {
    $date = date('Ymd');
    $random = str_pad((string) random_int(0, 99999), 5, '0', STR_PAD_LEFT);
    return 'ORD-' . $date . '-' . $random;
}

/**
 * Return a human-readable "time ago" string
 */
function timeAgo(string $datetime): string {
    $now  = time();
    $then = strtotime($datetime);
    $diff = $now - $then;

    if ($diff < 60) {
        return 'just now';
    } elseif ($diff < 3600) {
        $m = (int) floor($diff / 60);
        return $m . ' minute' . ($m !== 1 ? 's' : '') . ' ago';
    } elseif ($diff < 86400) {
        $h = (int) floor($diff / 3600);
        return $h . ' hour' . ($h !== 1 ? 's' : '') . ' ago';
    } elseif ($diff < 2592000) {
        $d = (int) floor($diff / 86400);
        return $d . ' day' . ($d !== 1 ? 's' : '') . ' ago';
    } elseif ($diff < 31536000) {
        $mo = (int) floor($diff / 2592000);
        return $mo . ' month' . ($mo !== 1 ? 's' : '') . ' ago';
    } else {
        $y = (int) floor($diff / 31536000);
        return $y . ' year' . ($y !== 1 ? 's' : '') . ' ago';
    }
}

/**
 * Format a datetime string
 */
function formatDate(string $datetime, string $format = 'M j, Y g:i A'): string {
    return date($format, strtotime($datetime));
}

/**
 * Validate an email address
 */
function isValidEmail(string $email): bool {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Truncate a string to a given length with ellipsis
 */
function truncate(string $str, int $len = 50): string {
    if (mb_strlen($str) <= $len) {
        return $str;
    }
    return mb_substr($str, 0, $len) . '...';
}

/**
 * Return a CSS badge class name for the given order status
 */
function getStatusBadgeClass(string $status): string {
    $map = [
        'pending'    => 'badge-pending',
        'processing' => 'badge-processing',
        'completed'  => 'badge-completed',
        'cancelled'  => 'badge-cancelled',
    ];
    return 'badge ' . ($map[$status] ?? 'badge-secondary');
}

/**
 * Return a CSS badge class name for the given priority
 */
function getPriorityBadgeClass(string $priority): string {
    $map = [
        'low'    => 'badge-low',
        'medium' => 'badge-medium',
        'high'   => 'badge-high',
    ];
    return 'badge ' . ($map[$priority] ?? 'badge-secondary');
}

/**
 * Set a flash message in the session
 */
function setFlash(string $type, string $message): void {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

/**
 * Get the client's IP address
 */
function getClientIp(): string {
    $keys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
    foreach ($keys as $key) {
        if (!empty($_SERVER[$key])) {
            $ip = explode(',', $_SERVER[$key])[0];
            $ip = trim($ip);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }
    return '0.0.0.0';
}

/**
 * Validate password strength: min 8 chars, 1 uppercase, 1 lowercase, 1 digit
 */
function isStrongPassword(string $password): bool {
    if (strlen($password) < 8) return false;
    if (!preg_match('/[A-Z]/', $password)) return false;
    if (!preg_match('/[a-z]/', $password)) return false;
    if (!preg_match('/[0-9]/', $password)) return false;
    return true;
}
