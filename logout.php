<?php
define('APP_ACCESS', true);
require_once './app/config.php';

// Log activity before destroying session
if (isset($_SESSION['user_id'])) {
    logActivity(
        'logout',
        'user',
        $_SESSION['user_id'],
        'User logged out',
        $_SESSION['user_id'],
        $_SESSION['full_name'] ?? 'User'
    );
}

// Destroy session
session_unset();
session_destroy();

// Redirect to login page
header('Location: login.php?logout=success');
exit;
