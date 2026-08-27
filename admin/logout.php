<?php
// admin/logout.php - Nice logout page with redirect
session_start();

// Log the logout action
if (isset($_SESSION['admin_email'])) {
    require_once __DIR__ . '/../includes/functions.php';
    logAudit('LOGOUT', 'email=' . $_SESSION['admin_email']);
}

// Destroy session
session_unset();
session_destroy();

// Redirect to login with message
header('Location: /admin/login.php?logged_out=1');
exit;
?>