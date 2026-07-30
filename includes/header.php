<?php
/**
 * Main authenticated layout header
 * Expects $pageTitle to be set before inclusion.
 */

// Display and clear any flash message
$flash = null;
if (!empty($_SESSION['flash'])) {
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
}

$currentUser = $currentUser ?? getCurrentUser();
$currentPath = $_SERVER['PHP_SELF'] ?? '';

function isActivePage(string $path): string {
    global $currentPath;
    return (strpos($currentPath, $path) !== false) ? 'active' : '';
}

$isAdmin = $currentUser && in_array($currentUser['role'], ['admin', 'super_admin']);
$appName  = defined('APP_NAME') ? APP_NAME : 'Order Management';
$pageTitle = $pageTitle ?? 'Dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($pageTitle) ?> &mdash; <?= htmlspecialchars($appName) ?></title>
<link rel="stylesheet" href="<?= rtrim(defined('APP_URL') ? APP_URL : '', '/') ?>/assets/css/style.css">
</head>
<body>

<!-- Sidebar -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <span class="sidebar-logo"><?= htmlspecialchars($appName) ?></span>
    </div>

    <nav class="sidebar-nav">
        <ul>
            <li>
                <a href="<?= rtrim(defined('APP_URL') ? APP_URL : '', '/') ?>/index.php"
                   class="<?= isActivePage('/index.php') && !isActivePage('/orders/') && !isActivePage('/admin/') && !isActivePage('/account/') ? 'active' : '' ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                    Dashboard
                </a>
            </li>
            <li class="nav-section">Orders</li>
            <li>
                <a href="<?= rtrim(defined('APP_URL') ? APP_URL : '', '/') ?>/orders/index.php"
                   class="<?= isActivePage('/orders/index.php') ? 'active' : '' ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                    All Orders
                </a>
            </li>
            <li>
                <a href="<?= rtrim(defined('APP_URL') ? APP_URL : '', '/') ?>/orders/create.php"
                   class="<?= isActivePage('/orders/create.php') ? 'active' : '' ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    New Order
                </a>
            </li>

            <?php if ($isAdmin): ?>
            <li class="nav-section">Administration</li>
            <li>
                <a href="<?= rtrim(defined('APP_URL') ? APP_URL : '', '/') ?>/admin/dashboard.php"
                   class="<?= isActivePage('/admin/dashboard.php') ? 'active' : '' ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                    Admin Dashboard
                </a>
            </li>
            <li>
                <a href="<?= rtrim(defined('APP_URL') ? APP_URL : '', '/') ?>/admin/stages.php"
                   class="<?= isActivePage('/admin/stages.php') ? 'active' : '' ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                    Stages
                </a>
            </li>
            <li>
                <a href="<?= rtrim(defined('APP_URL') ? APP_URL : '', '/') ?>/admin/settings.php"
                   class="<?= isActivePage('/admin/settings.php') ? 'active' : '' ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                    Settings
                </a>
            </li>
            <li>
                <a href="<?= rtrim(defined('APP_URL') ? APP_URL : '', '/') ?>/admin/users.php"
                   class="<?= isActivePage('/admin/users.php') ? 'active' : '' ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    Users
                </a>
            </li>
            <li>
                <a href="<?= rtrim(defined('APP_URL') ? APP_URL : '', '/') ?>/admin/orders.php"
                   class="<?= isActivePage('/admin/orders.php') ? 'active' : '' ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
                    All Orders (Admin)
                </a>
            </li>
            <?php endif; ?>

            <li class="nav-section">Account</li>
            <li>
                <a href="<?= rtrim(defined('APP_URL') ? APP_URL : '', '/') ?>/account/settings.php"
                   class="<?= isActivePage('/account/settings.php') ? 'active' : '' ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                    Profile Settings
                </a>
            </li>
            <li>
                <a href="<?= rtrim(defined('APP_URL') ? APP_URL : '', '/') ?>/account/change-password.php"
                   class="<?= isActivePage('/account/change-password.php') ? 'active' : '' ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                    Change Password
                </a>
            </li>
            <li>
                <a href="<?= rtrim(defined('APP_URL') ? APP_URL : '', '/') ?>/auth/logout.php">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                    Logout
                </a>
            </li>
        </ul>
    </nav>

    <div class="sidebar-footer">
        <div class="sidebar-user">
            <div class="sidebar-user-avatar">
                <?= strtoupper(substr($currentUser['first_name'] ?? 'U', 0, 1)) ?>
            </div>
            <div class="sidebar-user-info">
                <div class="sidebar-user-name">
                    <?= htmlspecialchars(($currentUser['first_name'] ?? '') . ' ' . ($currentUser['last_name'] ?? '')) ?>
                </div>
                <div class="sidebar-user-role"><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $currentUser['role'] ?? ''))) ?></div>
            </div>
        </div>
    </div>
</aside>

<!-- Mobile sidebar toggle -->
<button class="sidebar-toggle" id="sidebar-toggle" aria-label="Toggle sidebar">
    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
</button>

<!-- Main content area -->
<main class="main-content" id="main-content">
    <div class="top-bar">
        <h1 class="page-title"><?= htmlspecialchars($pageTitle) ?></h1>
        <div class="top-bar-actions">
            <span class="top-bar-user">
                <?= htmlspecialchars(($currentUser['first_name'] ?? '') . ' ' . ($currentUser['last_name'] ?? '')) ?>
            </span>
        </div>
    </div>

    <?php if ($flash): ?>
    <div class="alert alert-<?= htmlspecialchars($flash['type']) ?>" role="alert">
        <?= htmlspecialchars($flash['message']) ?>
        <button class="alert-close" onclick="this.parentElement.remove()" aria-label="Close">&times;</button>
    </div>
    <?php endif; ?>

    <div class="content-body">
