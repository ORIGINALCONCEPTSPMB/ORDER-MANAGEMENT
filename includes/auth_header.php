<?php
/**
 * Auth layout header — used on login, register, verify, forgot/reset password pages.
 * Expects $pageTitle to be set before inclusion.
 */
$appName   = defined('APP_NAME') ? APP_NAME : 'Order Management System';
$pageTitle = $pageTitle ?? $appName;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($pageTitle) ?> &mdash; <?= htmlspecialchars($appName) ?></title>
<link rel="stylesheet" href="<?= rtrim(defined('APP_URL') ? APP_URL : '..', '/') ?>/assets/css/style.css">
</head>
<body class="auth-body">
<div class="auth-container">
    <div class="auth-card">
        <div class="auth-logo">
            <svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
        </div>
        <h1 class="auth-app-name"><?= htmlspecialchars($appName) ?></h1>
