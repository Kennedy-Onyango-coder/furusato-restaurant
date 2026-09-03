<?php
/**
 * admin/logout.php — Ends the admin session and redirects to the login page.
 */
require_once __DIR__ . '/../includes/functions.php';

// Log the logout action before the session is torn down.
if (isset($_SESSION['admin_email'])) {
    logAudit('LOGOUT', 'email=' . $_SESSION['admin_email']);
}

// Destroy session (secure cookie cleanup + audit handled centrally).
destroySession();

// Redirect to login with message
header('Location: /admin/login.php?logged_out=1');
exit;
?>