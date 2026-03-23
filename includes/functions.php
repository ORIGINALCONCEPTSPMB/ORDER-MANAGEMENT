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
    $min = (int) pow(10, $length - 1);
    $max = (int) pow(10, $length) - 1;
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

/**
 * Fetch all settings from pf_settings table into associative array
 */
function getSettings(): array {
    $db = getDb();
    $stmt = $db->query('SELECT setting_key, setting_value FROM pf_settings');
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $settings = [];
    foreach ($rows as $row) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    return $settings;
}

/**
 * Get a single setting value
 */
function getSetting(string $key, string $default = ''): string {
    $db = getDb();
    $stmt = $db->prepare('SELECT setting_value FROM pf_settings WHERE setting_key = ?');
    $stmt->execute([$key]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? (string)$row['setting_value'] : $default;
}

/**
 * Save a single setting value
 */
function saveSetting(string $key, string $value): void {
    $db = getDb();
    $stmt = $db->prepare('INSERT INTO pf_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?');
    $stmt->execute([$key, $value, $value]);
}

/**
 * Generate a unique QR code hash for an order
 */
function generateQrHash(): string {
    return bin2hex(random_bytes(16));
}

/**
 * Get the QR scan URL for an order hash (used in QR code content).
 */
function getQrScanUrl(string $hash): string {
    return rtrim(APP_URL, '/') . '/orders/qr.php?hash=' . rawurlencode($hash);
}

/**
 * Get QR code image URL for a hash.
 * Uses Google Charts API by default. Note: the QR scan URL is sent to Google's
 * servers for image generation. If privacy is a concern, install a server-side
 * QR library (e.g. endroid/qr-code via Composer) and override this function.
 * @deprecated Use getQrScanUrl() + client-side qrcode.js instead
 */
function getQrImageUrl(string $hash, int $size = 150): string {
    $url = rtrim(APP_URL, '/') . '/orders/qr.php?hash=' . rawurlencode($hash);
    return 'https://chart.googleapis.com/chart?cht=qr&chs=' . $size . 'x' . $size . '&chl=' . urlencode($url);
}

/**
 * Format a WhatsApp wa.me URL with phone and message
 */
function getWhatsAppUrl(string $phone, string $message): string {
    $phone = preg_replace('/\D/', '', $phone);
    return 'https://wa.me/' . $phone . '?text=' . rawurlencode($message);
}

/**
 * Replace template placeholders with order data
 */
function parseWhatsAppTemplate(string $template, array $order, string $stageName = ''): string {
    $replacements = [
        '{customer_name}' => $order['customer_name'] ?? '',
        '{order_id}'      => $order['id'] ?? '',
        '{business_name}' => $order['business_name'] ?? '',
        '{stage_name}'    => $stageName,
    ];
    return str_replace(array_keys($replacements), array_values($replacements), $template);
}

/**
 * Get all custom fields ordered by field_order
 */
function getCustomFields(): array {
    $db = getDb();
    $stmt = $db->query('SELECT * FROM pf_custom_fields ORDER BY field_order ASC, id ASC');
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Decode JSON custom_fields from an order row into an array
 */
function getOrderCustomFields(array $order): array {
    if (empty($order['custom_fields'])) {
        return [];
    }
    $decoded = json_decode($order['custom_fields'], true);
    return is_array($decoded) ? $decoded : [];
}
