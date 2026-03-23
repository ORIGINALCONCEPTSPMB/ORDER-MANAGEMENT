<?php
session_start();

if (file_exists(__DIR__ . '/../config.php')) {
    require_once __DIR__ . '/../config.php';
    require_once __DIR__ . '/../includes/db.php';
    require_once __DIR__ . '/../includes/functions.php';
    require_once __DIR__ . '/../includes/auth.php';
    logoutUser();
}

header('Location: ../auth/login.php');
exit;
