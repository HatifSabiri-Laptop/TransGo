<?php
session_start();
require_once __DIR__ . '/../config/config.php';

if (isset($_SESSION['user_id'])) {
    $conn = getDBConnection();
    
    // 🔥 LOG LOGOUT
    log_activity($conn, $_SESSION['user_id'], 'logout', 'User logged out');
    
    if ($conn instanceof mysqli) {
        $conn->close();
    }
}

session_unset();
session_destroy();
header('Location: ' . SITE_URL . '/auth/login.php');
exit();
?>