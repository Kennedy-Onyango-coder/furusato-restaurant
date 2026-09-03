<?php
/**
 * admin/index.php — Entry point for admin panel
 * Redirects to dashboard.php
 */
require_once __DIR__ . '/../includes/functions.php';

if (!furusato_admin_authenticated()) {
    header('Location: /admin/login.php');
    exit;
}
header('Location: /admin/dashboard.php');
exit;