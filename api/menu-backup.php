<?php
/**
 * api/menu-backup.php - Menu Backup & Restore API
 * Provides backup, restore, and export functionality for menu data
 */

require_once __DIR__ . '/../includes/functions.php';

session_start();

// Set JSON headers
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

// Check authentication
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Create backups directory if not exists
$backupDir = __DIR__ . '/../data/backups/';
if (!file_exists($backupDir)) {
    mkdir($backupDir, 0755, true);
}

$action = isset($_GET['action']) ? $_GET['action'] : '';

/**
 * Helper function to count total items in menu
 */
function countMenuItems($menu) {
    $count = 0;
    foreach ($menu['categories'] ?? [] as $cat) {
        $count += count($cat['items'] ?? []);
        foreach ($cat['subcategories'] ?? [] as $sub) {
            $count += count($sub['items'] ?? []);
        }
    }
    return $count;
}

/**
 * Helper function to format file size
 */
function formatFileSize($bytes) {
    if ($bytes >= 1048576) {
        return round($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return round($bytes / 1024, 2) . ' KB';
    }
    return $bytes . ' B';
}

/**
 * Create a new backup
 */
if ($action === 'create') {
    $menu = getJsonData('menu');
    $timestamp = date('Y-m-d_H-i-s');
    $backupFile = $backupDir . "menu_backup_{$timestamp}.json";
    
    $backupData = [
        'backup_date' => date('c'),
        'version' => '1.0',
        'total_categories' => count($menu['categories'] ?? []),
        'total_items' => countMenuItems($menu),
        'data' => $menu
    ];
    
    if (file_put_contents($backupFile, json_encode($backupData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
        echo json_encode([
            'success' => true,
            'message' => 'Backup created successfully',
            'file' => basename($backupFile),
            'timestamp' => $timestamp
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to create backup']);
    }
    exit;
}

/**
 * List all backups
 */
if ($action === 'list') {
    $backups = [];
    $files = glob($backupDir . "menu_backup_*.json");
    
    foreach ($files as $file) {
        $backupData = json_decode(file_get_contents($file), true);
        $backups[] = [
            'filename' => basename($file),
            'date' => $backupData['backup_date'] ?? date('c', filemtime($file)),
            'size' => filesize($file),
            'size_formatted' => formatFileSize(filesize($file)),
            'filemtime' => filemtime($file),
            'total_categories' => $backupData['total_categories'] ?? 0,
            'total_items' => $backupData['total_items'] ?? 0
        ];
    }
    
    // Sort by date descending
    usort($backups, function($a, $b) {
        return $b['filemtime'] - $a['filemtime'];
    });
    
    echo json_encode(['success' => true, 'backups' => $backups]);
    exit;
}

/**
 * Restore a backup
 */
if ($action === 'restore' && isset($_GET['file'])) {
    $filename = basename($_GET['file']);
    $backupFile = $backupDir . $filename;
    
    if (!file_exists($backupFile)) {
        echo json_encode(['success' => false, 'error' => 'Backup file not found']);
        exit;
    }
    
    $backupData = json_decode(file_get_contents($backupFile), true);
    
    if (!isset($backupData['data']) || !is_array($backupData['data'])) {
        echo json_encode(['success' => false, 'error' => 'Invalid backup file format']);
        exit;
    }
    
    // Create a pre-restore backup automatically
    $preRestoreBackup = $backupDir . "menu_pre_restore_" . date('Y-m-d_H-i-s') . ".json";
    $currentMenu = getJsonData('menu');
    $preRestoreData = [
        'backup_date' => date('c'),
        'version' => '1.0',
        'note' => 'Auto-backup before restore',
        'total_categories' => count($currentMenu['categories'] ?? []),
        'total_items' => countMenuItems($currentMenu),
        'data' => $currentMenu
    ];
    file_put_contents($preRestoreBackup, json_encode($preRestoreData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    // Restore
    setJsonData('menu', $backupData['data']);
    
    echo json_encode([
        'success' => true, 
        'message' => 'Menu restored successfully',
        'pre_restore_backup' => basename($preRestoreBackup)
    ]);
    exit;
}

/**
 * Download a backup
 */
if ($action === 'download' && isset($_GET['file'])) {
    $filename = basename($_GET['file']);
    $backupFile = $backupDir . $filename;
    
    if (!file_exists($backupFile)) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Backup file not found']);
        exit;
    }
    
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    readfile($backupFile);
    exit;
}

/**
 * Delete a backup
 */
if ($action === 'delete' && isset($_GET['file'])) {
    $filename = basename($_GET['file']);
    $backupFile = $backupDir . $filename;
    
    if (file_exists($backupFile) && unlink($backupFile)) {
        echo json_encode(['success' => true, 'message' => 'Backup deleted']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to delete backup']);
    }
    exit;
}

/**
 * Export menu as JSON (download current menu)
 */
if ($action === 'export') {
    $menu = getJsonData('menu');
    $exportData = [
        'export_date' => date('c'),
        'restaurant' => 'Furusato Japanese Restaurant',
        'version' => '1.0',
        'total_categories' => count($menu['categories'] ?? []),
        'total_items' => countMenuItems($menu),
        'data' => $menu
    ];
    
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="furusato_menu_export_' . date('Y-m-d') . '.json"');
    echo json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Import menu from uploaded file
 */
if ($action === 'import' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_FILES['backup_file']) || $_FILES['backup_file']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'error' => 'No file uploaded or upload error']);
        exit;
    }
    
    // Check file size (max 10MB)
    if ($_FILES['backup_file']['size'] > 10 * 1024 * 1024) {
        echo json_encode(['success' => false, 'error' => 'File too large. Maximum 10MB.']);
        exit;
    }
    
    // Check file type
    $fileType = mime_content_type($_FILES['backup_file']['tmp_name']);
    if ($fileType !== 'application/json' && pathinfo($_FILES['backup_file']['name'], PATHINFO_EXTENSION) !== 'json') {
        echo json_encode(['success' => false, 'error' => 'Invalid file type. Please upload a JSON file.']);
        exit;
    }
    
    $fileContent = file_get_contents($_FILES['backup_file']['tmp_name']);
    $importData = json_decode($fileContent, true);
    
    if (!$importData) {
        echo json_encode(['success' => false, 'error' => 'Invalid JSON file']);
        exit;
    }
    
    // Handle both export format and raw menu format
    if (isset($importData['data']) && isset($importData['data']['categories'])) {
        $menuData = $importData['data'];
    } elseif (isset($importData['categories'])) {
        $menuData = $importData;
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid menu data structure. Missing categories array.']);
        exit;
    }
    
    // Create backup before import
    $preImportBackup = $backupDir . "menu_pre_import_" . date('Y-m-d_H-i-s') . ".json";
    $currentMenu = getJsonData('menu');
    $preImportData = [
        'backup_date' => date('c'),
        'version' => '1.0',
        'note' => 'Auto-backup before import',
        'total_categories' => count($currentMenu['categories'] ?? []),
        'total_items' => countMenuItems($currentMenu),
        'data' => $currentMenu
    ];
    file_put_contents($preImportBackup, json_encode($preImportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    // Import
    setJsonData('menu', $menuData);
    
    echo json_encode([
        'success' => true,
        'message' => 'Menu imported successfully',
        'backup_created' => basename($preImportBackup)
    ]);
    exit;
}

// If no valid action
echo json_encode(['success' => false, 'error' => 'Invalid action']);
?>