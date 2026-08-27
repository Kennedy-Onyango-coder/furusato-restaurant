<?php
/**
 * admin/index.php — Entry point for admin panel
 * Redirects to dashboard.php
 */
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: /admin/login.php');
    exit;
}
header('Location: /admin/dashboard.php');
exit;