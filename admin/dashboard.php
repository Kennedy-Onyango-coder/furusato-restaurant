<?php
/**
 * admin/dashboard.php - Furusato Admin Dashboard
 * FULLY FUNCTIONAL VERSION
 * - All CRUD operations working
 * - Drag & drop reordering
 * - Menu item management
 * - Reservations management
 * - Settings management
 * - CSRF protection
 * - Session timeout warning
 */

// Log errors instead of suppressing them
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../logs/admin_errors.log');

@ob_start();

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/image-processor.php';
require_once __DIR__ . '/../includes/cache-control.php';

// Start session with security headers
startAdminSession(true);
setNoCacheHeaders();
setSecurityHeaders();

$csrfToken = $_SESSION['csrf_token'];
$admin = getJsonData('admin') ?? [];

$menu = getJsonData('menu');
$categories = [];
if ($menu && isset($menu['categories'])) {
    foreach ($menu['categories'] as $cat) {
        $categories[] = [
            'id'     => $cat['id'],
            'label'  => $cat['label'],
            'slug'   => $cat['slug'] ?? '',
            'icon'   => $cat['icon'] ?? 'ðŸ“‹',
            'visible'=> $cat['visible'] ?? true,
            'order'  => $cat['order'] ?? 999,
            'subcats'=> $cat['subcategories'] ?? []
        ];
    }
    usort($categories, function($a, $b) {
        return ($a['order'] ?? 999) - ($b['order'] ?? 999);
    });
}

$settings = getJsonData('settings');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
  <title>Furusato Admin â€” Dashboard</title>
  <link rel="icon" type="image/png" href="/assets/images/furusato-logo.png">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Cormorant+Garamond:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  
  <style>
    /* Session timeout modal */
    .session-modal {
      position: fixed;
      bottom: 20px;
      right: 20px;
      background: #0d1b2a;
      color: white;
      padding: 16px 24px;
      border-radius: 12px;
      box-shadow: 0 4px 20px rgba(0,0,0,0.2);
      z-index: 1000;
      display: none;
      align-items: center;
      gap: 16px;
      font-size: 0.9rem;
      border-left: 4px solid #d4af7a;
    }
    .session-modal button {
      background: #d4af7a;
      border: none;
      padding: 6px 16px;
      border-radius: 6px;
      cursor: pointer;
      font-weight: 600;
      color: #0d1b2a;
    }
    .session-modal button:hover { background: #e8c99b; }
    
    .btn-loading {
      opacity: 0.7;
      pointer-events: none;
      position: relative;
    }
    .btn-loading::after {
      content: '';
      position: absolute;
      width: 16px;
      height: 16px;
      top: 50%;
      right: 12px;
      margin-top: -8px;
      border: 2px solid white;
      border-top-color: transparent;
      border-radius: 50%;
      animation: spin 0.6s linear infinite;
    }
    @keyframes spin { to { transform: rotate(360deg); } }
    
    * { margin: 0; padding: 0; box-sizing: border-box; }
    :root {
      --primary: #0d1b2a; --primary-light: #1b2e4a; --primary-dark: #07131f;
      --accent: #d4af7a; --accent-light: #e8c99b; --accent-dark: #b8944f;
      --crimson: #c0392b; --success: #10b981; --warning: #f59e0b; --danger: #ef4444;
      --gray-50: #f9fafb; --gray-100: #f3f4f6; --gray-200: #e5e7eb; --gray-300: #d1d5db;
      --gray-400: #9ca3af; --gray-500: #6b7280; --gray-600: #4b5563; --gray-700: #374151;
      --gray-800: #1f2937; --bg: #f7f5f0; --card: #ffffff;
      --sidebar-width: 280px; --header-height: 70px;
      --shadow-sm: 0 1px 2px 0 rgba(0,0,0,0.05);
      --shadow: 0 1px 3px 0 rgba(0,0,0,0.1);
      --shadow-md: 0 4px 6px -1px rgba(0,0,0,0.1);
      --shadow-lg: 0 10px 15px -3px rgba(0,0,0,0.1);
      --radius-sm: 0.5rem; --radius: 0.75rem; --radius-lg: 1rem; --radius-xl: 1.5rem;
      --transition: all 0.2s ease;
    }
    body { font-family: 'Inter', sans-serif; background: var(--bg); color: var(--gray-800); line-height: 1.5; overflow-x: hidden; }
    .admin-container { display: flex; min-height: 100vh; }
    .sidebar { width: var(--sidebar-width); background: linear-gradient(180deg, var(--primary) 0%, var(--primary-dark) 100%); position: fixed; left: 0; top: 0; bottom: 0; display: flex; flex-direction: column; z-index: 100; transition: var(--transition); }
    .sidebar-header { padding: 28px 24px; border-bottom: 1px solid rgba(255,255,255,0.08); margin-bottom: 24px; }
    .logo { display: flex; align-items: center; gap: 12px; }
    .logo-icon { width: 44px; height: 44px; background: linear-gradient(135deg, var(--accent), var(--accent-dark)); border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.3rem; font-weight: 800; color: var(--primary); box-shadow: 0 4px 12px rgba(0,0,0,0.2); }
    .logo-text { font-family: 'Cormorant Garamond', serif; font-size: 1.4rem; font-weight: 700; color: white; }
    .logo-text span { color: var(--accent); }
    .logo-sub { font-size: 0.6rem; color: rgba(255,255,255,0.4); letter-spacing: 0.1em; text-transform: uppercase; margin-top: 2px; }
    .sidebar-nav { flex: 1; padding: 0 16px; }
    .nav-item { margin-bottom: 6px; }
    .nav-link { display: flex; align-items: center; gap: 12px; padding: 12px 16px; border-radius: 12px; color: rgba(255,255,255,0.65); text-decoration: none; font-size: 0.9rem; font-weight: 500; transition: var(--transition); }
    .nav-link:hover { background: rgba(255,255,255,0.08); color: white; }
    .nav-link.active { background: linear-gradient(135deg, var(--accent), var(--accent-dark)); color: var(--primary); box-shadow: 0 4px 12px rgba(212,175,122,0.3); }
    .sidebar-footer { padding: 20px 24px; border-top: 1px solid rgba(255,255,255,0.08); margin-top: auto; }
    .logout-btn { display: flex; align-items: center; gap: 12px; padding: 10px 16px; border-radius: 10px; color: rgba(255,255,255,0.5); text-decoration: none; font-size: 0.85rem; transition: var(--transition); }
    .logout-btn:hover { background: rgba(239,68,68,0.15); color: var(--danger); }
    .main-content { flex: 1; margin-left: var(--sidebar-width); min-height: 100vh; }
    .top-header { background: var(--card); border-bottom: 1px solid var(--gray-200); padding: 0 32px; height: var(--header-height); display: flex; align-items: center; justify-content: space-between; position: sticky; top: 0; z-index: 99; backdrop-filter: blur(10px); background: rgba(255,255,255,0.95); }
    .page-title { font-family: 'Cormorant Garamond', serif; font-size: 1.6rem; font-weight: 700; color: var(--primary); }
    .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; padding: 28px 32px 0 32px; }
    .stat-card { background: var(--card); border-radius: var(--radius-lg); padding: 20px 24px; box-shadow: var(--shadow); border: 1px solid var(--gray-200); transition: var(--transition); position: relative; overflow: hidden; }
    .stat-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px; background: linear-gradient(90deg, var(--accent), var(--crimson)); }
    .stat-card:hover { transform: translateY(-2px); box-shadow: var(--shadow-lg); }
    .stat-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
    .stat-header span { font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: var(--gray-500); }
    .stat-header i { font-size: 1.5rem; color: var(--accent); opacity: 0.7; }
    .stat-value { font-family: 'Cormorant Garamond', serif; font-size: 2.5rem; font-weight: 700; color: var(--primary); line-height: 1; }
    .toolbar { background: var(--card); border-radius: var(--radius-lg); margin: 24px 32px; padding: 16px 24px; display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 16px; box-shadow: var(--shadow); border: 1px solid var(--gray-200); }
    .search-wrapper { position: relative; flex: 1; max-width: 360px; }
    .search-wrapper i { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: var(--gray-400); font-size: 1rem; }
    .search-wrapper input { width: 100%; padding: 10px 16px 10px 42px; border: 1px solid var(--gray-200); border-radius: 40px; font-size: 0.85rem; font-family: 'Inter', sans-serif; outline: none; transition: var(--transition); background: var(--gray-50); }
    .search-wrapper input:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(212,175,122,0.1); background: white; }
    .btn-group { display: flex; gap: 10px; flex-wrap: wrap; }
    .btn { display: inline-flex; align-items: center; gap: 8px; padding: 8px 18px; font-size: 0.8rem; font-weight: 600; border-radius: 40px; border: 1px solid transparent; cursor: pointer; transition: var(--transition); background: transparent; }
    .btn i { font-size: 0.9rem; }
    .btn-primary { background: linear-gradient(135deg, var(--primary), var(--primary-dark)); color: white; border-color: var(--primary); }
    .btn-primary:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(13,27,42,0.3); }
    .btn-gold { background: linear-gradient(135deg, var(--accent), var(--accent-dark)); color: var(--primary); border-color: var(--accent); }
    .btn-gold:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(212,175,122,0.4); }
    .btn-outline { border-color: var(--gray-300); color: var(--gray-700); background: white; }
    .btn-outline:hover { border-color: var(--accent); background: rgba(212,175,122,0.05); }
    .btn-sm { padding: 6px 14px; font-size: 0.75rem; }
    .action-btn { display: inline-flex; align-items: center; gap: 6px; padding: 6px 14px; font-size: 0.75rem; font-weight: 500; border-radius: 6px; border: 1px solid var(--gray-300); cursor: pointer; background: white; color: var(--gray-700); text-decoration: none; }
    .action-btn:hover { background: var(--accent); border-color: var(--accent); color: var(--primary); transform: translateY(-1px); }
    .action-btn-danger { color: var(--danger); }
    .action-btn-danger:hover { background: var(--danger); border-color: var(--danger); color: white; }
    .drag-over { border-top: 2px solid var(--accent) !important; background: rgba(212,175,122,0.05); }
    .dragging { opacity: 0.4; }
    .drag-handle { cursor: grab; }
    .drag-handle:active { cursor: grabbing; }
    .category-container { padding: 0 32px 32px 32px; }
    .category-card { background: var(--card); border-radius: var(--radius-xl); margin-bottom: 28px; box-shadow: var(--shadow); border: 1px solid var(--gray-200); overflow: hidden; transition: var(--transition); }
    .category-card:hover { box-shadow: var(--shadow-lg); }
    .category-header { display: flex; align-items: center; gap: 14px; padding: 18px 24px; background: linear-gradient(135deg, var(--gray-50), white); border-bottom: 1px solid var(--gray-200); cursor: move; }
    .category-icon { font-size: 1.5rem; }
    .category-name { font-family: 'Cormorant Garamond', serif; font-size: 1.2rem; font-weight: 700; color: var(--primary); }
    .category-badge { font-size: 0.7rem; padding: 4px 12px; background: rgba(212,175,122,0.15); border-radius: 30px; color: var(--accent-dark); }
    .category-actions { margin-left: auto; display: flex; gap: 8px; }
    .subcategories-list { padding: 16px 24px; background: var(--gray-50); border-bottom: 1px solid var(--gray-200); }
    .subcategory-item { display: flex; align-items: center; gap: 12px; padding: 10px 12px; background: white; border-radius: var(--radius); margin-bottom: 8px; border: 1px solid var(--gray-200); transition: var(--transition); cursor: move; }
    .subcategory-item:hover { border-color: var(--accent); box-shadow: var(--shadow-sm); }
    .subcategory-icon { font-size: 1rem; }
    .subcategory-name { flex: 1; font-size: 0.85rem; font-weight: 500; }
    .subcategory-badge { font-size: 0.65rem; padding: 2px 8px; background: rgba(212,175,122,0.12); border-radius: 20px; color: var(--accent-dark); }
    .subcategory-actions { display: flex; gap: 6px; margin-left: auto; }
    .items-table-wrapper { overflow-x: auto; }
    .items-table { width: 100%; border-collapse: collapse; }
    .items-table th { text-align: left; padding: 14px 16px; background: var(--gray-50); color: var(--gray-600); font-weight: 600; font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.05em; border-bottom: 1px solid var(--gray-200); }
    .items-table td { padding: 14px 16px; border-bottom: 1px solid var(--gray-100); vertical-align: middle; }
    .items-table tr:hover td { background: rgba(212,175,122,0.03); }
    .item-image { width: 48px; height: 48px; border-radius: var(--radius); object-fit: cover; background: var(--gray-100); }
    .item-name-cell { font-weight: 600; color: var(--primary); }
    .item-desc-cell { font-size: 0.7rem; color: var(--gray-500); margin-top: 4px; }
    .item-price-cell { font-weight: 700; color: var(--crimson); white-space: nowrap; }
    .badge-popular { display: inline-block; padding: 3px 10px; font-size: 0.6rem; font-weight: 700; background: linear-gradient(135deg, var(--accent), var(--accent-dark)); color: var(--primary); border-radius: 20px; margin-left: 8px; }
    .action-btns { display: flex; gap: 8px; justify-content: flex-end; }
    .settings-wrapper { padding: 32px; max-width: 1200px; margin: 0 auto; }
    .settings-card { background: var(--card); border-radius: 24px; margin-bottom: 32px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); border: 1px solid var(--gray-200); overflow: hidden; transition: all 0.3s ease; }
    .settings-card:hover { box-shadow: 0 8px 30px rgba(0,0,0,0.12); transform: translateY(-2px); }
    .settings-card-header { display: flex; align-items: center; gap: 20px; padding: 28px 32px; background: linear-gradient(135deg, var(--gray-50) 0%, white 100%); border-bottom: 1px solid var(--gray-200); }
    .settings-card-icon { width: 56px; height: 56px; background: linear-gradient(135deg, var(--accent), var(--accent-dark)); border-radius: 18px; display: flex; align-items: center; justify-content: center; font-size: 1.8rem; color: var(--primary); box-shadow: 0 4px 12px rgba(212,175,122,0.3); }
    .settings-card-title h3 { font-family: 'Cormorant Garamond', serif; font-size: 1.6rem; font-weight: 700; color: var(--primary); margin: 0 0 4px 0; }
    .settings-card-title p { font-size: 0.85rem; color: var(--gray-500); margin: 0; }
    .settings-card-body { padding: 32px; }
    .settings-form { max-width: 100%; }
    .form-group-modern { margin-bottom: 28px; }
    .form-row-modern { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 0; }
    .form-label-modern { display: flex; align-items: center; gap: 8px; font-size: 0.85rem; font-weight: 600; color: var(--gray-700); margin-bottom: 10px; }
    .form-label-modern i { color: var(--accent); font-size: 0.9rem; width: 20px; }
    .form-input-modern, .form-textarea-modern { width: 100%; padding: 12px 16px; font-size: 0.9rem; font-family: 'Inter', sans-serif; border: 2px solid var(--gray-200); border-radius: 12px; background: var(--gray-50); transition: all 0.2s ease; outline: none; color: var(--gray-800); }
    .form-input-modern:focus, .form-textarea-modern:focus { border-color: var(--accent); background: white; box-shadow: 0 0 0 4px rgba(212,175,122,0.1); }
    .info-box { background: linear-gradient(135deg, rgba(212,175,122,0.08), rgba(212,175,122,0.03)); border-left: 3px solid var(--accent); padding: 12px 16px; border-radius: 10px; margin-top: 12px; font-size: 0.75rem; color: var(--gray-600); display: flex; align-items: flex-start; gap: 10px; }
    .btn-modern { display: inline-flex; align-items: center; gap: 10px; padding: 12px 28px; font-size: 0.85rem; font-weight: 600; border-radius: 40px; border: none; cursor: pointer; transition: all 0.2s ease; font-family: 'Inter', sans-serif; }
    .btn-primary-modern { background: linear-gradient(135deg, var(--primary), var(--primary-dark)); color: white; box-shadow: 0 2px 8px rgba(13,27,42,0.2); }
    .btn-primary-modern:hover { transform: translateY(-1px); box-shadow: 0 6px 16px rgba(13,27,42,0.3); }
    .btn-outline-modern { background: white; color: var(--primary); border: 2px solid var(--gray-200); }
    .btn-outline-modern:hover { border-color: var(--accent); background: rgba(212,175,122,0.05); transform: translateY(-1px); }
    .form-actions { margin-top: 32px; padding-top: 8px; }
    .form-actions-group { display: flex; gap: 16px; margin-top: 32px; padding-top: 8px; flex-wrap: wrap; }
    .reservations-container-modern { display: flex; flex-direction: column; gap: 20px; padding: 0 32px 32px 32px; }
    .reservation-card-modern { background: var(--card); border-radius: var(--radius-lg); border: 1px solid var(--gray-200); overflow: hidden; transition: var(--transition); box-shadow: var(--shadow-sm); }
    .reservation-card-modern:hover { box-shadow: var(--shadow-md); border-color: var(--accent); }
    .reservation-header-modern { display: flex; justify-content: space-between; align-items: center; padding: 20px 24px; background: linear-gradient(135deg, var(--gray-50), white); border-bottom: 1px solid var(--gray-200); flex-wrap: wrap; gap: 12px; }
    .reservation-guest-modern { display: flex; align-items: center; gap: 14px; }
    .reservation-avatar-modern { width: 48px; height: 48px; background: linear-gradient(135deg, var(--accent), var(--accent-dark)); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: var(--primary); font-weight: 700; font-size: 1.1rem; }
    .reservation-info-modern h4 { font-size: 1rem; font-weight: 600; color: var(--primary); margin-bottom: 4px; }
    .reservation-info-modern p { font-size: 0.75rem; color: var(--gray-500); }
    .reservation-status-modern { padding: 5px 14px; border-radius: 30px; font-size: 0.7rem; font-weight: 600; letter-spacing: 0.5px; }
    .status-pending-modern { background: rgba(245,158,11,0.12); color: var(--warning); }
    .status-confirmed-modern { background: rgba(16,185,129,0.12); color: var(--success); }
    .status-cancelled-modern { background: rgba(239,68,68,0.12); color: var(--danger); }
    .reservation-details-modern { display: flex; flex-wrap: wrap; gap: 32px; padding: 20px 24px; background: white; border-bottom: 1px solid var(--gray-100); }
    .reservation-detail-modern { display: flex; align-items: center; gap: 10px; font-size: 0.85rem; color: var(--gray-700); }
    .reservation-detail-modern i { width: 20px; color: var(--accent); font-size: 0.9rem; }
    .reservation-detail-modern strong { font-weight: 600; color: var(--gray-800); }
    .reservation-requests-modern { padding: 16px 24px; background: var(--gray-50); border-bottom: 1px solid var(--gray-200); }
    .reservation-requests-modern .label { font-size: 0.7rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: var(--gray-500); margin-bottom: 6px; }
    .reservation-requests-modern .text { font-size: 0.85rem; color: var(--gray-700); line-height: 1.5; }
    .reservation-actions-modern { display: flex; gap: 12px; padding: 16px 24px; background: white; }
    .reservation-id-modern { font-size: 0.7rem; color: var(--gray-400); font-family: monospace; margin-left: auto; }
    .modal { position: fixed; inset: 0; background: rgba(13,27,42,0.7); backdrop-filter: blur(8px); display: none; align-items: center; justify-content: center; z-index: 1000; }
    .modal.active { display: flex; animation: fadeIn 0.2s ease; }
    @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
    .modal-content { background: var(--card); border-radius: var(--radius-xl); width: 90%; max-width: 560px; max-height: 85vh; overflow-y: auto; box-shadow: var(--shadow-lg); animation: slideUp 0.3s ease; display: flex; flex-direction: column; }
    @keyframes slideUp { from { transform: translateY(20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
    .modal-header { padding: 20px 24px; border-bottom: 1px solid var(--gray-200); display: flex; justify-content: space-between; align-items: center; flex-shrink: 0; }
    .modal-header h3 { font-family: 'Cormorant Garamond', serif; font-size: 1.3rem; font-weight: 700; color: var(--primary); }
    .modal-close { width: 32px; height: 32px; border-radius: 8px; background: transparent; border: none; font-size: 1.2rem; cursor: pointer; color: var(--gray-400); transition: var(--transition); }
    .modal-close:hover { background: var(--gray-100); color: var(--danger); }
    .modal-body { padding: 24px; flex: 1; overflow-y: auto; }
    .modal-footer { padding: 16px 24px; border-top: 1px solid var(--gray-200); display: flex; justify-content: flex-end; gap: 12px; flex-shrink: 0; }
    .form-group { margin-bottom: 20px; }
    .form-label { display: block; font-size: 0.75rem; font-weight: 600; margin-bottom: 6px; color: var(--gray-700); }
    .form-input, .form-select, .form-textarea { width: 100%; padding: 10px 14px; border: 1px solid var(--gray-200); border-radius: var(--radius); font-size: 0.85rem; font-family: 'Inter', sans-serif; transition: var(--transition); outline: none; background: var(--gray-50); }
    .form-input:focus, .form-select:focus, .form-textarea:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(212,175,122,0.1); background: white; }
    .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
    .popular-toggle { display: flex; align-items: center; gap: 12px; padding: 12px; background: var(--gray-50); border-radius: var(--radius); border: 1px solid var(--gray-200); }
    .popular-toggle input { width: 20px; height: 20px; accent-color: var(--accent); cursor: pointer; }
    .upload-zone { border: 2px dashed var(--gray-200); border-radius: var(--radius); padding: 24px; text-align: center; cursor: pointer; transition: var(--transition); }
    .upload-zone:hover { border-color: var(--accent); background: rgba(212,175,122,0.03); }
    .toast-container { position: fixed; bottom: 24px; right: 24px; z-index: 1100; display: flex; flex-direction: column; gap: 10px; }
    .toast { padding: 12px 20px; background: var(--primary); color: white; border-radius: var(--radius); font-size: 0.85rem; box-shadow: var(--shadow-lg); animation: slideInRight 0.3s ease; display: flex; align-items: center; gap: 10px; }
    .toast.success { background: var(--success); }
    .toast.error { background: var(--danger); }
    @keyframes slideInRight { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
    .empty-state { text-align: center; padding: 60px 20px; color: var(--gray-400); }
    .empty-state i { font-size: 3rem; margin-bottom: 16px; }
    .loading-spinner { display: flex; justify-content: center; align-items: center; padding: 40px; flex-direction: column; gap: 12px; }
    .spinner { width: 40px; height: 40px; border: 3px solid var(--gray-200); border-top-color: var(--accent); border-radius: 50%; animation: spin 0.8s linear infinite; }
    @keyframes spin { to { transform: rotate(360deg); } }
    @media (max-width: 1024px) { .stats-grid { grid-template-columns: repeat(2,1fr); gap: 16px; padding: 20px 20px 0 20px; } .toolbar { margin: 20px; flex-direction: column; align-items: stretch; } .search-wrapper { max-width: 100%; } .category-container { padding: 0 20px 20px 20px; } }
    @media (max-width: 768px) { .sidebar { transform: translateX(-100%); } .sidebar.open { transform: translateX(0); } .main-content { margin-left: 0; } .stats-grid { grid-template-columns: 1fr; } .form-row { grid-template-columns: 1fr; } .action-btns { flex-direction: column; gap: 4px; } .settings-wrapper { padding: 20px; } .settings-card-header { flex-direction: column; text-align: center; } .settings-card-body { padding: 24px; } .form-row-modern { grid-template-columns: 1fr; gap: 20px; } .form-actions-group { flex-direction: column; } .btn-modern { width: 100%; justify-content: center; } }
  </style>
</head>
<body>
<div class="admin-container">
  <aside class="sidebar">
    <div class="sidebar-header">
      <div class="logo"><div class="logo-icon">F</div><div><div class="logo-text">Furusato<span>.</span></div><div class="logo-sub">Admin Console</div></div></div>
    </div>
    <nav class="sidebar-nav">
      <div class="nav-item"><a href="#" class="nav-link active" data-section="menu"><i class="fas fa-utensils"></i><span>Menu Management</span></a></div>
      <div class="nav-item"><a href="#" class="nav-link" data-section="reservations"><i class="fas fa-calendar-alt"></i><span>Reservations</span></a></div>
      <div class="nav-item"><a href="#" class="nav-link" data-section="settings"><i class="fas fa-cog"></i><span>Admin Settings</span></a></div>
    </nav>
    <div class="sidebar-footer"><a href="#" class="logout-btn" id="logout-btn"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a></div>
  </aside>
  
  <main class="main-content">
    <div class="top-header"><h1 class="page-title" id="main-title">Menu Management</h1></div>
    
    <div class="stats-grid" id="stats-container">
      <div class="stat-card"><div class="stat-header"><span>Total Categories</span><i class="fas fa-tags"></i></div><div class="stat-value" id="stat-categories">â€”</div></div>
      <div class="stat-card"><div class="stat-header"><span>Subcategories</span><i class="fas fa-folder-open"></i></div><div class="stat-value" id="stat-subcategories">â€”</div></div>
      <div class="stat-card"><div class="stat-header"><span>Menu Items</span><i class="fas fa-hamburger"></i></div><div class="stat-value" id="stat-items">â€”</div></div>
      <div class="stat-card"><div class="stat-header"><span>Popular Dishes</span><i class="fas fa-star"></i></div><div class="stat-value" id="stat-popular">â€”</div></div>
    </div>
    
    <div class="toolbar" id="menu-toolbar">
      <div class="search-wrapper"><i class="fas fa-search"></i><input type="text" id="search-input" placeholder="Search menu items..."></div>
      <div class="btn-group">
        <button class="btn btn-outline btn-sm" onclick="refreshMenuData()"><i class="fas fa-sync-alt"></i> Refresh</button>
        <button class="btn btn-outline btn-sm" onclick="showBackupModal()"><i class="fas fa-database"></i> Backup</button>
        <button class="btn btn-outline btn-sm" onclick="showCategoryModal()"><i class="fas fa-plus"></i> Category</button>
        <button class="btn btn-outline btn-sm" onclick="showSubcategoryModal()"><i class="fas fa-plus-circle"></i> Subcategory</button>
        <button class="btn btn-gold btn-sm" onclick="showItemModal()"><i class="fas fa-plus"></i> Add Item</button>
      </div>
    </div>
    
    <div class="toolbar" id="reservations-toolbar" style="display: none;">
      <div class="search-wrapper"><i class="fas fa-search"></i><input type="text" id="reservation-search" placeholder="Search by name, phone, or email..."></div>
      <div class="btn-group">
        <button class="btn btn-outline btn-sm" onclick="refreshReservations()"><i class="fas fa-sync-alt"></i> Refresh</button>
        <button class="btn btn-outline btn-sm" onclick="debugReservationsAPI()"><i class="fas fa-bug"></i> Debug API</button>
        <button class="btn btn-outline btn-sm filter-status" data-status="all">All</button>
        <button class="btn btn-outline btn-sm filter-status" data-status="pending">Pending</button>
        <button class="btn btn-outline btn-sm filter-status" data-status="confirmed">Confirmed</button>
        <button class="btn btn-outline btn-sm filter-status" data-status="cancelled">Cancelled</button>
      </div>
    </div>
    
    <div class="toolbar" id="settings-toolbar" style="display: none;"><div class="btn-group"><button class="btn btn-outline btn-sm" onclick="refreshSettings()"><i class="fas fa-sync-alt"></i> Refresh</button></div></div>
    
    <div class="category-container" id="menu-container"><div class="loading-spinner"><div class="spinner"></div><p>Loading menu...</p></div></div>
    <div id="reservations-container" style="display: none;"></div>
    
    <div id="settings-container" style="display: none;">
      <div class="settings-wrapper">
        <div class="settings-card"><div class="settings-card-header"><div class="settings-card-icon"><i class="fas fa-lock"></i></div><div class="settings-card-title"><h3>Change Password</h3><p>Update your admin account password</p></div></div>
        <div class="settings-card-body"><form id="password-form" class="settings-form"><div class="form-group-modern"><label class="form-label-modern"><i class="fas fa-key"></i> Current Password</label><input type="password" id="current-password" class="form-input-modern" placeholder="Enter current password" required></div>
        <div class="form-row-modern"><div class="form-group-modern"><label class="form-label-modern"><i class="fas fa-lock"></i> New Password</label><input type="password" id="new-password" class="form-input-modern" placeholder="Min. 8 characters" required minlength="8"><small class="form-hint-modern">Minimum 8 characters for security</small></div>
        <div class="form-group-modern"><label class="form-label-modern"><i class="fas fa-check-circle"></i> Confirm New Password</label><input type="password" id="confirm-password" class="form-input-modern" placeholder="Confirm new password" required></div></div>
        <div class="form-actions"><button type="submit" class="btn-modern btn-primary-modern"><i class="fas fa-save"></i> Update Password</button></div></form></div></div>

        <div class="settings-card"><div class="settings-card-header"><div class="settings-card-icon"><i class="fas fa-store"></i></div><div class="settings-card-title"><h3>Restaurant Settings</h3><p>Manage your restaurant business information</p></div></div>
        <div class="settings-card-body"><form id="restaurant-settings-form" class="settings-form"><div class="form-group-modern"><label class="form-label-modern"><i class="fas fa-utensils"></i> Restaurant Name</label><input type="text" id="restaurant-name" class="form-input-modern" value="Furusato Japanese Restaurant" placeholder="Restaurant name"></div>
        <div class="form-row-modern"><div class="form-group-modern"><label class="form-label-modern"><i class="fas fa-phone"></i> Phone Number</label><input type="tel" id="restaurant-phone" class="form-input-modern" value="+254 722 488 706" placeholder="+254 XXX XXX XXX"></div>
        <div class="form-group-modern"><label class="form-label-modern"><i class="fab fa-whatsapp"></i> WhatsApp Number</label><input type="tel" id="whatsapp-number" class="form-input-modern" value="+254 734 639 203" placeholder="+254 XXX XXX XXX"></div></div>
        <div class="form-group-modern"><label class="form-label-modern"><i class="fas fa-envelope"></i> Email Address</label><input type="email" id="restaurant-email" class="form-input-modern" value="info@furusatorestaurant.com" placeholder="contact@restaurant.com"></div>
        <div class="form-group-modern"><label class="form-label-modern"><i class="fas fa-map-marker-alt"></i> Address</label><textarea id="restaurant-address" class="form-textarea-modern" rows="3">Ring Road Parklands, Westlands, Nairobi, Kenya</textarea></div>
        <div class="form-row-modern"><div class="form-group-modern"><label class="form-label-modern"><i class="fas fa-clock"></i> Opening Hours</label><input type="text" id="restaurant-hours" class="form-input-modern" value="12:00 PM - 9:00 PM" placeholder="e.g., 10:00 AM - 10:00 PM"></div>
        <div class="form-group-modern"><label class="form-label-modern"><i class="fas fa-calendar-week"></i> Days Open</label><input type="text" id="restaurant-days" class="form-input-modern" value="Monday - Sunday" placeholder="e.g., Monday - Friday"></div></div>
        <div class="form-actions"><button type="submit" class="btn-modern btn-primary-modern"><i class="fas fa-save"></i> Save Restaurant Settings</button></div></form></div></div>

        <div class="settings-card"><div class="settings-card-header"><div class="settings-card-icon"><i class="fab fa-whatsapp"></i></div><div class="settings-card-title"><h3>WhatsApp Integration</h3><p>Configure WhatsApp notifications for reservations</p></div></div>
        <div class="settings-card-body"><form id="whatsapp-settings-form" class="settings-form"><div class="form-group-modern"><label class="form-label-modern"><i class="fas fa-key"></i> CallMeBot API Key</label><input type="text" id="whatsapp-api-key" class="form-input-modern" value="" placeholder="Enter your CallMeBot API key"><div class="info-box"><i class="fas fa-info-circle"></i> Get your API key by sending "I allow callmebot to send me messages" to +34 666 66 74 26 on WhatsApp</div></div>
        <div class="form-actions-group"><button type="button" class="btn-modern btn-outline-modern" onclick="testWhatsApp()"><i class="fab fa-whatsapp"></i> Send Test Message</button><button type="submit" class="btn-modern btn-primary-modern"><i class="fas fa-save"></i> Save WhatsApp Settings</button></div></form></div></div>
      </div>
    </div>
  </main>
</div>

<div id="session-warning" class="session-modal"><span>âš ï¸ Your session will expire in <span id="session-timer">5:00</span></span><button onclick="extendSession()">Stay Logged In</button></div>

<!-- Modals -->
<div class="modal" id="item-modal"><div class="modal-content"><div class="modal-header"><h3 id="item-modal-title">Add Menu Item</h3><button class="modal-close" onclick="closeModal('item-modal')"><i class="fas fa-times"></i></button></div>
<form id="item-form" enctype="multipart/form-data"><div class="modal-body"><input type="hidden" id="item-id" name="id"><input type="hidden" id="item-action" name="action" value="add_item"><input type="hidden" id="existing-image" name="existing_image">
<div class="form-row"><div class="form-group"><label class="form-label">Category *</label><select id="item-category" name="category_id" class="form-select" required><option value="">Select category</option><?php foreach ($categories as $cat): ?><option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['label']) ?></option><?php endforeach; ?></select></div>
<div class="form-group" id="subcategory-group" style="display:none;"><label class="form-label">Subcategory</label><select id="item-subcategory" name="subcategory_id" class="form-select"><option value="">None</option></select></div></div>
<div class="form-group"><label class="form-label">Item Name *</label><input type="text" id="item-name" name="name" class="form-input" required></div>
<div class="form-group"><label class="form-label">Description</label><textarea id="item-description" name="description" class="form-textarea" rows="3"></textarea></div>
<div class="form-row"><div class="form-group"><label class="form-label">Current Price (KES) *</label><input type="number" id="item-price" name="price" class="form-input" required min="0" step="50"></div>
<div class="form-group"><label class="form-label">Original Price (KES)</label><input type="number" id="item-original-price" name="original_price" class="form-input" min="0" step="50" placeholder="Leave empty if no discount"></div></div>
<div class="popular-toggle"><input type="checkbox" id="item-popular" name="is_popular" value="1"><label for="item-popular">ðŸŒŸ Mark as Popular Dish</label><input type="hidden" id="item-badge" name="badge" value=""></div>
<div class="form-group"><label class="form-label">Item Image</label><div class="upload-zone" id="upload-zone"><i class="fas fa-cloud-upload-alt" style="font-size: 1.5rem; color: var(--gray-400);"></i><p style="margin-top: 8px; font-size: 0.8rem; color: var(--gray-500);">Click or drag to upload image</p></div><input type="file" id="item-image" name="image" accept="image/*" style="display:none;"><div class="image-preview" id="image-preview" style="display:none; margin-top: 12px;"><img id="preview-img" src="" alt="Preview" style="width: 60px; height: 60px; border-radius: var(--radius); object-fit: cover;"><button type="button" class="btn btn-outline btn-sm" onclick="clearImage()">Remove</button></div></div></div>
<div class="modal-footer"><button type="button" class="btn btn-outline" onclick="closeModal('item-modal')">Cancel</button><button type="submit" class="btn btn-primary">Save Item</button></div></form></div></div>

<div class="modal" id="category-modal"><div class="modal-content"><div class="modal-header"><h3 id="category-modal-title">Add Category</h3><button class="modal-close" onclick="closeModal('category-modal')"><i class="fas fa-times"></i></button></div>
<form id="category-form"><div class="modal-body"><input type="hidden" id="category-id" name="category_id"><div class="form-group"><label class="form-label">Category Name *</label><input type="text" id="category-name" name="label" class="form-input" required></div><div class="form-group"><label class="form-label">Japanese Name</label><input type="text" id="category-jp" name="labelJp" class="form-input"></div>
<div class="form-row"><div class="form-group"><label class="form-label">Icon (Emoji)</label><input type="text" id="category-icon" name="icon" class="form-input" placeholder="ðŸ£"></div><div class="form-group"><label class="form-label">Visible</label><select id="category-visible" name="visible" class="form-select"><option value="true">Yes</option><option value="false">No</option></select></div></div></div>
<div class="modal-footer"><button type="button" class="btn btn-outline" onclick="closeModal('category-modal')">Cancel</button><button type="submit" class="btn btn-primary">Save Category</button></div></form></div></div>

<div class="modal" id="subcategory-modal"><div class="modal-content"><div class="modal-header"><h3 id="subcategory-modal-title">Add Subcategory</h3><button class="modal-close" onclick="closeModal('subcategory-modal')"><i class="fas fa-times"></i></button></div>
<form id="subcategory-form"><div class="modal-body"><input type="hidden" id="subcategory-id" name="subcategory_id"><div class="form-group"><label class="form-label">Parent Category *</label><select id="subcategory-parent" name="parent_id" class="form-select" required><option value="">Select category</option><?php foreach ($categories as $cat): ?><option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['label']) ?></option><?php endforeach; ?></select></div>
<div class="form-group"><label class="form-label">Subcategory Name *</label><input type="text" id="subcategory-label" name="label" class="form-input" required></div><div class="form-group"><label class="form-label">Japanese Name</label><input type="text" id="subcategory-jp" name="labelJp" class="form-input"></div>
<div class="form-row"><div class="form-group"><label class="form-label">Icon (Emoji)</label><input type="text" id="subcategory-icon" name="icon" class="form-input" placeholder="ðŸ¥—"></div><div class="form-group"><label class="form-label">Visible</label><select id="subcategory-visible" name="visible" class="form-select"><option value="true">Yes</option><option value="false">No</option></select></div></div></div>
<div class="modal-footer"><button type="button" class="btn btn-outline" onclick="closeModal('subcategory-modal')">Cancel</button><button type="submit" class="btn btn-primary">Save Subcategory</button></div></form></div></div>

<div class="modal" id="delete-modal"><div class="modal-content" style="max-width: 400px;"><div class="modal-header"><h3>Confirm Delete</h3><button class="modal-close" onclick="closeModal('delete-modal')"><i class="fas fa-times"></i></button></div>
<div class="modal-body"><p id="delete-message">Are you sure you want to delete this item?</p><input type="hidden" id="delete-id"><input type="hidden" id="delete-type"></div>
<div class="modal-footer"><button class="btn btn-outline" onclick="closeModal('delete-modal')">Cancel</button><button class="btn btn-danger" id="confirm-delete">Delete</button></div></div></div>

<div class="modal" id="move-modal"><div class="modal-content" style="max-width: 500px;"><div class="modal-header"><h3><i class="fas fa-arrows-alt"></i> Move Menu Item</h3><button class="modal-close" onclick="closeModal('move-modal')"><i class="fas fa-times"></i></button></div>
<div class="modal-body"><p style="margin-bottom: 16px;">Moving: <strong id="move-item-name"></strong></p><input type="hidden" id="move-item-id"><input type="hidden" id="move-current-cat"><input type="hidden" id="move-current-sub">
<div class="form-group"><label class="form-label">Target Category</label><select id="move-target-cat" class="form-select" required><option value="">Select category...</option></select></div>
<div class="form-group" id="move-subcat-group" style="display:none;"><label class="form-label">Target Subcategory</label><select id="move-target-subcat" class="form-select"><option value="">(None - move to main category)</option></select></div>
<p class="form-hint" style="margin-top: 12px; color: var(--danger);"><i class="fas fa-exclamation-triangle"></i> Item will be moved to the new location.</p></div>
<div class="modal-footer"><button class="btn btn-outline" onclick="closeModal('move-modal')">Cancel</button><button class="btn btn-primary" id="confirm-move"><i class="fas fa-check"></i> Move Item</button></div></div></div>

<div class="modal" id="backup-modal"><div class="modal-content" style="max-width: 600px;"><div class="modal-header"><h3>Backup & Restore</h3><button class="modal-close" onclick="closeModal('backup-modal')"><i class="fas fa-times"></i></button></div>
<div class="modal-body"><div style="margin-bottom: 24px;"><h4>Create Backup</h4><button class="btn btn-primary" onclick="createBackup()"><i class="fas fa-database"></i> Create New Backup</button></div>
<div style="margin-bottom: 24px;"><h4>Export Menu</h4><button class="btn btn-outline" onclick="exportMenu()"><i class="fas fa-download"></i> Download Menu JSON</button></div>
<div style="margin-bottom: 24px;"><h4>Import Menu</h4><input type="file" id="import-file" accept=".json" style="display:none;" onchange="importMenu(this)"><button class="btn btn-outline" onclick="document.getElementById('import-file').click()"><i class="fas fa-upload"></i> Import JSON File</button><p class="form-hint">âš ï¸ Import will overwrite current menu. A backup will be created automatically.</p></div>
<div><h4>Available Backups</h4><div id="backups-list" style="max-height: 300px; overflow-y: auto;"></div></div></div>
<div class="modal-footer"><button class="btn btn-outline" onclick="closeModal('backup-modal')">Close</button></div></div></div>

<div class="toast-container" id="toast-container"></div>

<script>
// CSRF Token
const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
let currentReservations = [];
let currentFilter = 'all';
let dragSrc = null;

// DOM Elements
const menuContainer = document.getElementById('menu-container');
const reservationsContainer = document.getElementById('reservations-container');
const settingsContainer = document.getElementById('settings-container');
const statsContainer = document.getElementById('stats-container');
const menuToolbar = document.getElementById('menu-toolbar');
const reservationsToolbar = document.getElementById('reservations-toolbar');
const settingsToolbar = document.getElementById('settings-toolbar');
const mainTitle = document.getElementById('main-title');

// Session timeout tracking
let sessionTimeout, sessionWarningTimeout;
const SESSION_LIFETIME = 30 * 60 * 1000;
const WARNING_BEFORE = 5 * 60 * 1000;

function resetSessionTimer() {
    clearTimeout(sessionTimeout);
    clearTimeout(sessionWarningTimeout);
    const warningEl = document.getElementById('session-warning');
    if (warningEl) warningEl.style.display = 'none';
    sessionWarningTimeout = setTimeout(showSessionWarning, SESSION_LIFETIME - WARNING_BEFORE);
    sessionTimeout = setTimeout(logoutDueToInactivity, SESSION_LIFETIME);
}

function showSessionWarning() {
    const warningEl = document.getElementById('session-warning');
    if (!warningEl) return;
    warningEl.style.display = 'flex';
    let timeLeft = 300;
    const timerEl = document.getElementById('session-timer');
    const countdown = setInterval(function() {
        const minutes = Math.floor(timeLeft / 60);
        const seconds = timeLeft % 60;
        if (timerEl) timerEl.textContent = minutes + ':' + seconds.toString().padStart(2, '0');
        timeLeft--;
        if (timeLeft < 0) clearInterval(countdown);
    }, 1000);
}

function extendSession() {
    fetch('/api/auth.php?action=check_session', { method: 'GET', headers: { 'X-CSRF-Token': csrfToken } })
        .then(function() { resetSessionTimer(); const warningEl = document.getElementById('session-warning'); if (warningEl) warningEl.style.display = 'none'; showToast('Session extended', 'success'); })
        .catch(function() { window.location.href = '/admin/login.php'; });
}

function logoutDueToInactivity() { window.location.href = '/admin/login.php?timeout=1'; }

function escapeHtml(str) { if (str === null || str === undefined) return ''; return String(str).replace(/[&<>]/g, function(m) { if (m === '&') return '&amp;'; if (m === '<') return '&lt;'; if (m === '>') return '&gt;'; return m; }); }
function safeString(value, defaultValue) { if (defaultValue === undefined) defaultValue = ''; if (value === null || value === undefined) return defaultValue; return String(value); }

function showToast(message, type) { if (type === undefined) type = 'success'; const container = document.getElementById('toast-container'); if (!container) return; const toast = document.createElement('div'); toast.className = 'toast ' + type; toast.innerHTML = '<i class="fas ' + (type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle') + '"></i> ' + message; container.appendChild(toast); setTimeout(function() { toast.remove(); }, 3000); }

function closeModal(modalId) { document.getElementById(modalId).classList.remove('active'); }
function openModal(modalId) { document.getElementById(modalId).classList.add('active'); }

// ============================================================
// NAVIGATION
// ============================================================

function switchToSection(section) {
    if (menuContainer) menuContainer.style.display = 'none';
    if (reservationsContainer) reservationsContainer.style.display = 'none';
    if (settingsContainer) settingsContainer.style.display = 'none';
    if (statsContainer) statsContainer.style.display = 'none';
    if (menuToolbar) menuToolbar.style.display = 'none';
    if (reservationsToolbar) reservationsToolbar.style.display = 'none';
    if (settingsToolbar) settingsToolbar.style.display = 'none';
    
    if (section === 'menu') {
        if (menuContainer) menuContainer.style.display = 'block';
        if (statsContainer) statsContainer.style.display = 'grid';
        if (menuToolbar) menuToolbar.style.display = 'flex';
        if (mainTitle) mainTitle.textContent = 'Menu Management';
        loadMenu();
        loadStats();
    } else if (section === 'reservations') {
        if (reservationsContainer) reservationsContainer.style.display = 'block';
        if (reservationsToolbar) reservationsToolbar.style.display = 'flex';
        if (mainTitle) mainTitle.textContent = 'Reservations';
        loadReservations();
    } else if (section === 'settings') {
        if (settingsContainer) settingsContainer.style.display = 'block';
        if (settingsToolbar) settingsToolbar.style.display = 'flex';
        if (mainTitle) mainTitle.textContent = 'Admin Settings';
    }
}

document.querySelectorAll('.nav-link').forEach(function(link) {
    link.addEventListener('click', function(e) {
        e.preventDefault();
        document.querySelectorAll('.nav-link').forEach(function(l) { l.classList.remove('active'); });
        this.classList.add('active');
        var section = this.getAttribute('data-section');
        if (section) switchToSection(section);
    });
});

// ============================================================
// LOAD MENU
// ============================================================

async function loadMenu() {
    if (!menuContainer) return;
    menuContainer.innerHTML = '<div class="loading-spinner"><div class="spinner"></div><p>Loading menu...</p></div>';
    try {
        const response = await fetch('/api/menu.php?v=' + Date.now(), { method: 'GET', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' });
        if (!response.ok) throw new Error('HTTP ' + response.status);
        const data = await response.json();
        if (data.categories && Array.isArray(data.categories)) {
            renderMenu(data.categories);
            updateStatsFromMenu(data.categories);
        } else {
            menuContainer.innerHTML = '<div class="empty-state"><i class="fas fa-utensils"></i><p>No categories found. Click "+ Category" to get started.</p></div>';
        }
    } catch (error) {
        console.error('Menu error:', error);
        menuContainer.innerHTML = '<div class="empty-state"><i class="fas fa-exclamation-triangle"></i><p>Failed to load menu: ' + error.message + '</p><button class="btn btn-outline btn-sm" onclick="loadMenu()" style="margin-top:16px;">Retry</button></div>';
    }
}

function updateStatsFromMenu(categories) {
    if (!categories) return;
    let totalCategories = categories.length, totalSubcategories = 0, totalItems = 0, totalPopular = 0;
    for (let c = 0; c < categories.length; c++) {
        const cat = categories[c];
        totalSubcategories += (cat.subcategories || []).length;
        totalItems += (cat.items || []).length;
        for (let s = 0; s < (cat.subcategories || []).length; s++) totalItems += (cat.subcategories[s].items || []).length;
        for (let i = 0; i < (cat.items || []).length; i++) if (cat.items[i].badge && cat.items[i].badge.toLowerCase() === 'popular') totalPopular++;
        for (let s = 0; s < (cat.subcategories || []).length; s++) {
            const sub = cat.subcategories[s];
            for (let i = 0; i < (sub.items || []).length; i++) if (sub.items[i].badge && sub.items[i].badge.toLowerCase() === 'popular') totalPopular++;
        }
    }
    const sc = document.getElementById('stat-categories'), ss = document.getElementById('stat-subcategories'), si = document.getElementById('stat-items'), sp = document.getElementById('stat-popular');
    if (sc) sc.textContent = totalCategories;
    if (ss) ss.textContent = totalSubcategories;
    if (si) si.textContent = totalItems;
    if (sp) sp.textContent = totalPopular;
}

async function loadStats() {
    try {
        const formData = new FormData();
        formData.append('action', 'get_stats');
        const response = await fetch('/api/menu.php', { method: 'POST', headers: { 'X-CSRF-Token': csrfToken }, body: formData });
        const data = await response.json();
        if (data.success) {
            const sc = document.getElementById('stat-categories'), ss = document.getElementById('stat-subcategories'), si = document.getElementById('stat-items'), sp = document.getElementById('stat-popular');
            if (sc) sc.textContent = data.stats.categories;
            if (ss) ss.textContent = data.stats.subcategories;
            if (si) si.textContent = data.stats.total_items;
            if (sp) sp.textContent = data.stats.popular_items;
        }
    } catch (error) { console.error('Stats error:', error); }
}

// ============================================================
// RENDER MENU
// ============================================================

function renderMenu(categories) {
    if (!categories || categories.length === 0) { if (menuContainer) menuContainer.innerHTML = '<div class="empty-state"><i class="fas fa-utensils"></i><p>No categories found. Click "+ Category" to get started.</p></div>'; return; }
    let html = '';
    for (let c = 0; c < categories.length; c++) {
        const cat = categories[c], items = cat.items || [], subcats = cat.subcategories || [];
        let totalSubItems = 0; for (let s = 0; s < subcats.length; s++) totalSubItems += (subcats[s].items || []).length;
        html += '<div class="category-card" data-category-id="' + cat.id + '"><div class="category-header" draggable="true" data-cat-id="' + cat.id + '"><i class="fas fa-grip-vertical drag-handle" style="cursor: grab; color: #9ca3af; margin-right: 8px;"></i><span class="category-icon">' + (cat.icon || 'ðŸ“‹') + '</span><span class="category-name">' + escapeHtml(cat.label) + '</span><span class="category-badge">' + (items.length + totalSubItems) + ' items</span><div class="category-actions"><button class="action-btn" onclick="editCategory(\'' + cat.id + '\')"><i class="fas fa-edit"></i> Edit</button><button class="action-btn action-btn-danger" onclick="deleteCategory(\'' + cat.id + '\')"><i class="fas fa-trash-alt"></i> Delete</button></div></div>';
        if (subcats.length > 0) {
            html += '<div class="subcategories-list" data-cat-id="' + cat.id + '">';
            for (let s = 0; s < subcats.length; s++) {
                const sub = subcats[s];
                html += '<div class="subcategory-item" draggable="true" data-sub-id="' + sub.id + '"><i class="fas fa-grip-vertical drag-handle" style="cursor: grab; color: #9ca3af; margin-right: 8px;"></i><span class="subcategory-icon">' + (sub.icon || 'ðŸ“') + '</span><span class="subcategory-name">' + escapeHtml(sub.label) + '</span><span class="subcategory-badge">' + ((sub.items || []).length) + ' items</span><div class="subcategory-actions"><button class="action-btn action-btn-sm" onclick="editSubcategory(\'' + sub.id + '\')"><i class="fas fa-edit"></i> Edit</button><button class="action-btn action-btn-sm action-btn-danger" onclick="deleteSubcategory(\'' + sub.id + '\')"><i class="fas fa-trash-alt"></i> Delete</button></div></div>';
            }
            html += '</div>';
        }
        html += '<div class="items-table-wrapper"><table class="items-table"><thead><th style="width:40px;"></th><th>Image</th><th>Item Name</th><th>Price</th><th>Actions</th></thead><tbody id="tbody-' + cat.id + '">';
        for (let i = 0; i < items.length; i++) html += renderItemRow(items[i], cat.id);
        for (let s = 0; s < subcats.length; s++) { const sub = subcats[s]; for (let i = 0; i < (sub.items || []).length; i++) html += renderItemRow(sub.items[i], cat.id); }
        html += '</tbody></table></div></div>';
    }
    if (menuContainer) menuContainer.innerHTML = html;
    setTimeout(function() { initDragAndDrop(); }, 100);
}

function renderItemRow(item, categoryId) {
    const imgSrc = item.image_url || item.image || '', hasImage = imgSrc && imgSrc.trim() !== '';
    const isPopular = item.badge && String(item.badge).toLowerCase() === 'popular', popularBadge = isPopular ? '<span class="badge-popular"><i class="fas fa-star"></i> Popular</span>' : '';
    let priceDisplay = '';
    if (item.original_price && item.original_price > item.price) {
        priceDisplay = '<span style="text-decoration: line-through; color: #999; margin-right: 8px;">Ksh ' + Number(item.original_price).toLocaleString() + '</span> <span style="color: #c0392b; font-weight: 700;">Ksh ' + Number(item.price).toLocaleString() + '</span>';
    } else {
        priceDisplay = '<span style="color: #c0392b; font-weight: 700;">Ksh ' + Number(item.price).toLocaleString() + '</span>';
    }
    const escapedName = escapeHtml(item.name).replace(/'/g, "\\'");
    return '<tr data-item-id="' + item.id + '" draggable="true"><td style="width:40px;"><i class="fas fa-grip-vertical drag-handle" style="cursor: grab; color: #9ca3af;"></i></td><td style="width:60px;">' + (hasImage ? '<img src="' + escapeHtml(imgSrc) + '" class="item-image" onerror="this.src=\'/assets/images/menu/placeholder.webp\'">' : '<div class="item-image" style="background:#f3f4f6; display:flex;align-items:center;justify-content:center;"><i class="fas fa-utensils" style="color:#9ca3af;"></i></div>') + '</td><td><div class="item-name-cell">' + escapeHtml(item.name) + ' ' + popularBadge + '</div><div class="item-desc-cell">' + escapeHtml(String(item.description || '').substring(0, 80)) + (String(item.description || '').length > 80 ? '...' : '') + '</div></td><td class="item-price-cell">' + priceDisplay + '</td><td class="action-btns"><button class="action-btn" onclick="editItem(\'' + item.id + '\')"><i class="fas fa-edit"></i> Edit</button><button class="action-btn" onclick="showMoveModal(\'' + item.id + '\', \'' + escapedName + '\', \'' + categoryId + '\', \'' + (item.subcategory_id || '') + '\')"><i class="fas fa-arrows-alt"></i> Move</button><button class="action-btn action-btn-danger" onclick="deleteItem(\'' + item.id + '\')"><i class="fas fa-trash-alt"></i> Delete</button></div></td></tr>';
}

// ============================================================
// DRAG AND DROP
// ============================================================

function initDragAndDrop() {
    document.querySelectorAll('.category-header[draggable="true"]').forEach(function(header) {
        header.addEventListener('dragstart', handleCategoryDragStart);
        header.addEventListener('dragend', handleDragEnd);
        header.addEventListener('dragover', handleDragOver);
        header.addEventListener('dragleave', handleDragLeave);
        header.addEventListener('drop', handleCategoryDrop);
    });
    document.querySelectorAll('.subcategory-item[draggable="true"]').forEach(function(item) {
        item.addEventListener('dragstart', handleSubDragStart);
        item.addEventListener('dragend', handleDragEnd);
        item.addEventListener('dragover', handleDragOver);
        item.addEventListener('dragleave', handleDragLeave);
        item.addEventListener('drop', handleSubDrop);
    });
    document.querySelectorAll('.items-table tbody tr[draggable="true"]').forEach(function(row) {
        row.addEventListener('dragstart', handleItemDragStart);
        row.addEventListener('dragend', handleDragEnd);
        row.addEventListener('dragover', handleDragOver);
        row.addEventListener('dragleave', handleDragLeave);
        row.addEventListener('drop', handleItemDrop);
    });
}

function handleCategoryDragStart(e) { dragSrc = this; this.closest('.category-card').classList.add('dragging'); e.dataTransfer.effectAllowed = 'move'; e.dataTransfer.setData('text/plain', this.closest('.category-card').dataset.categoryId); }
function handleSubDragStart(e) { dragSrc = this; this.classList.add('dragging'); e.dataTransfer.effectAllowed = 'move'; e.dataTransfer.setData('text/plain', this.dataset.subId); }
function handleItemDragStart(e) { dragSrc = this; this.classList.add('dragging'); e.dataTransfer.effectAllowed = 'move'; e.dataTransfer.setData('text/plain', this.dataset.itemId); }
function handleDragEnd() { if (dragSrc) { dragSrc.classList.remove('dragging'); if (dragSrc.closest) { var catCard = dragSrc.closest('.category-card'); if (catCard) catCard.classList.remove('dragging'); } } document.querySelectorAll('.drag-over').forEach(function(el) { el.classList.remove('drag-over'); }); dragSrc = null; }
function handleDragOver(e) { e.preventDefault(); if (this !== dragSrc && this.parentNode === (dragSrc ? dragSrc.parentNode : null)) this.classList.add('drag-over'); }
function handleDragLeave() { this.classList.remove('drag-over'); }
function handleCategoryDrop(e) { e.preventDefault(); this.classList.remove('drag-over'); if (!dragSrc || dragSrc === this) return; var container = document.getElementById('menu-container'); var cards = Array.from(container.querySelectorAll('.category-card')); var fromIndex = cards.indexOf(dragSrc.closest('.category-card')); var toIndex = cards.indexOf(this.closest('.category-card')); if (fromIndex < toIndex) this.parentNode.insertBefore(dragSrc.closest('.category-card'), this.closest('.category-card').nextSibling); else this.parentNode.insertBefore(dragSrc.closest('.category-card'), this.closest('.category-card')); saveCategoryOrder(); }
function handleSubDrop(e) { e.preventDefault(); this.classList.remove('drag-over'); if (!dragSrc || dragSrc === this) return; var parentList = dragSrc.parentNode; var items = Array.from(parentList.querySelectorAll('.subcategory-item')); var fromIndex = items.indexOf(dragSrc); var toIndex = items.indexOf(this); if (fromIndex < toIndex) this.parentNode.insertBefore(dragSrc, this.nextSibling); else this.parentNode.insertBefore(dragSrc, this); saveSubcategoryOrder(parentList.dataset.catId); }
function handleItemDrop(e) { e.preventDefault(); this.classList.remove('drag-over'); if (!dragSrc || dragSrc === this) return; var tbody = dragSrc.parentNode; var rows = Array.from(tbody.querySelectorAll('tr[draggable="true"]')); var fromIndex = rows.indexOf(dragSrc); var toIndex = rows.indexOf(this); if (fromIndex < toIndex) this.parentNode.insertBefore(dragSrc, this.nextSibling); else this.parentNode.insertBefore(dragSrc, this); saveItemOrder(tbody.closest('.category-card').dataset.categoryId, tbody); }

async function saveCategoryOrder() { var categoryIds = []; document.querySelectorAll('.category-card').forEach(function(card) { var catId = card.dataset.categoryId; if (catId) categoryIds.push(catId); }); try { await fetch('/api/menu.php', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken }, body: JSON.stringify({ action: 'reorder_categories', category_ids: categoryIds }) }); showToast('Categories reordered', 'success'); } catch(e) { console.error(e); } }
async function saveSubcategoryOrder(catId) { var subIds = []; var parentList = document.querySelector('.subcategories-list[data-cat-id="' + catId + '"]'); if (parentList) { parentList.querySelectorAll('.subcategory-item').forEach(function(item) { var subId = item.dataset.subId; if (subId) subIds.push(subId); }); try { await fetch('/api/menu.php', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken }, body: JSON.stringify({ action: 'reorder_subcategories', category_id: catId, subcategory_ids: subIds }) }); showToast('Subcategories reordered', 'success'); } catch(e) { console.error(e); } } }
async function saveItemOrder(categoryId, tbody) { var itemIds = []; tbody.querySelectorAll('tr[draggable="true"]').forEach(function(row) { var itemId = row.dataset.itemId; if (itemId) itemIds.push(itemId); }); try { await fetch('/api/menu.php', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken }, body: JSON.stringify({ action: 'reorder_items', category_id: categoryId, item_ids: itemIds }) }); showToast('Items reordered', 'success'); } catch(e) { console.error(e); } }

// ============================================================
// MODAL FUNCTIONS - CRUD OPERATIONS
// ============================================================

window.showCategoryModal = function() { document.getElementById('category-modal-title').textContent = 'Add Category'; document.getElementById('category-form').reset(); document.getElementById('category-id').value = ''; openModal('category-modal'); };
window.showSubcategoryModal = function() { document.getElementById('subcategory-modal-title').textContent = 'Add Subcategory'; document.getElementById('subcategory-form').reset(); document.getElementById('subcategory-id').value = ''; openModal('subcategory-modal'); };
window.showItemModal = function() { document.getElementById('item-modal-title').textContent = 'Add Menu Item'; document.getElementById('item-form').reset(); document.getElementById('item-id').value = ''; document.getElementById('item-action').value = 'add_item'; document.getElementById('image-preview').style.display = 'none'; document.getElementById('item-original-price').value = ''; document.getElementById('item-popular').checked = false; document.getElementById('item-badge').value = ''; openModal('item-modal'); };
window.showBackupModal = function() { openModal('backup-modal'); loadBackupsList(); };
window.refreshMenuData = function() { loadMenu(); loadStats(); showToast('Menu refreshed', 'success'); };
window.refreshSettings = function() { showToast('Settings refreshed', 'success'); };

// Category Form Submit
document.getElementById('category-form')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    const action = document.getElementById('category-id').value ? 'edit_category' : 'create_category';
    formData.append('action', action);
    try {
        const response = await fetch('/api/menu.php', { method: 'POST', headers: { 'X-CSRF-Token': csrfToken }, body: formData });
        const data = await response.json();
        if (data.success) { closeModal('category-modal'); loadMenu(); loadStats(); showToast('Category saved'); }
        else showToast(data.error || 'Failed', 'error');
    } catch (error) { showToast('Network error', 'error'); }
});

// Subcategory Form Submit
document.getElementById('subcategory-form')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    const action = document.getElementById('subcategory-id').value ? 'edit_subcategory' : 'create_subcategory';
    formData.append('action', action);
    try {
        const response = await fetch('/api/menu.php', { method: 'POST', headers: { 'X-CSRF-Token': csrfToken }, body: formData });
        const data = await response.json();
        if (data.success) { closeModal('subcategory-modal'); loadMenu(); loadStats(); showToast('Subcategory saved'); }
        else showToast(data.error || 'Failed', 'error');
    } catch (error) { showToast('Network error', 'error'); }
});

// Item Form Submit
document.getElementById('item-form')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    const isPopular = document.getElementById('item-popular').checked;
    document.getElementById('item-badge').value = isPopular ? 'Popular' : '';
    const formData = new FormData(this);
    const action = document.getElementById('item-id').value ? 'update_item' : 'add_item';
    formData.append('action', action);
    try {
        const response = await fetch('/api/menu.php', { method: 'POST', headers: { 'X-CSRF-Token': csrfToken }, body: formData });
        const data = await response.json();
        if (data.success) { closeModal('item-modal'); loadMenu(); loadStats(); showToast('Item saved'); }
        else showToast(data.error || 'Failed', 'error');
    } catch (error) { showToast('Network error', 'error'); }
});

// Delete Functions
window.deleteItem = function(id) { document.getElementById('delete-id').value = id; document.getElementById('delete-type').value = 'item'; document.getElementById('delete-message').textContent = 'Delete this menu item?'; openModal('delete-modal'); };
window.deleteCategory = function(id) { document.getElementById('delete-id').value = id; document.getElementById('delete-type').value = 'category'; document.getElementById('delete-message').textContent = 'Delete this category? Items will be moved.'; openModal('delete-modal'); };
window.deleteSubcategory = function(id) { document.getElementById('delete-id').value = id; document.getElementById('delete-type').value = 'subcategory'; document.getElementById('delete-message').textContent = 'Delete this subcategory? Items will be moved.'; openModal('delete-modal'); };

document.getElementById('confirm-delete')?.addEventListener('click', async function() {
    const id = document.getElementById('delete-id').value;
    const type = document.getElementById('delete-type').value;
    const formData = new FormData();
    if (type === 'item') { formData.append('action', 'delete_item'); formData.append('id', id); }
    else if (type === 'category') { formData.append('action', 'delete_category'); formData.append('category_id', id); }
    else if (type === 'subcategory') { formData.append('action', 'delete_subcategory'); formData.append('subcategory_id', id); }
    try {
        const response = await fetch('/api/menu.php', { method: 'POST', headers: { 'X-CSRF-Token': csrfToken }, body: formData });
        const data = await response.json();
        if (data.success) { closeModal('delete-modal'); loadMenu(); loadStats(); showToast('Deleted'); }
        else showToast(data.error || 'Delete failed', 'error');
    } catch (error) { showToast('Network error', 'error'); }
});

// Edit Functions
window.editCategory = async function(id) {
    try {
        const response = await fetch('/api/menu.php?v=' + Date.now());
        const data = await response.json();
        const category = data.categories.find(function(c) { return c.id === id; });
        if (category) {
            document.getElementById('category-modal-title').textContent = 'Edit Category';
            document.getElementById('category-id').value = category.id;
            document.getElementById('category-name').value = category.label;
            document.getElementById('category-jp').value = category.labelJp || '';
            document.getElementById('category-icon').value = category.icon || '';
            document.getElementById('category-visible').value = category.visible !== false ? 'true' : 'false';
            openModal('category-modal');
        }
    } catch (error) { showToast('Failed to load category', 'error'); }
};

window.editSubcategory = async function(id) {
    try {
        const response = await fetch('/api/menu.php?v=' + Date.now());
        const data = await response.json();
        for (let c = 0; c < data.categories.length; c++) {
            const sub = data.categories[c].subcategories?.find(function(s) { return s.id === id; });
            if (sub) {
                document.getElementById('subcategory-modal-title').textContent = 'Edit Subcategory';
                document.getElementById('subcategory-id').value = sub.id;
                document.getElementById('subcategory-parent').value = data.categories[c].id;
                document.getElementById('subcategory-label').value = sub.label;
                document.getElementById('subcategory-jp').value = sub.labelJp || '';
                document.getElementById('subcategory-icon').value = sub.icon || '';
                document.getElementById('subcategory-visible').value = sub.visible !== false ? 'true' : 'false';
                openModal('subcategory-modal');
                break;
            }
        }
    } catch (error) { showToast('Failed to load subcategory', 'error'); }
};

window.editItem = async function(id) {
    try {
        const response = await fetch('/api/menu.php?v=' + Date.now());
        const data = await response.json();
        for (let c = 0; c < data.categories.length; c++) {
            const cat = data.categories[c];
            for (let i = 0; i < (cat.items || []).length; i++) {
                const item = cat.items[i];
                if (item.id === id) {
                    populateEditForm(item, cat.id);
                    return;
                }
            }
            for (let s = 0; s < (cat.subcategories || []).length; s++) {
                const sub = cat.subcategories[s];
                for (let i = 0; i < (sub.items || []).length; i++) {
                    const item = sub.items[i];
                    if (item.id === id) {
                        populateEditForm(item, cat.id, sub.id);
                        return;
                    }
                }
            }
        }
    } catch (error) { showToast('Failed to load item', 'error'); }
};

function populateEditForm(item, categoryId, subcategoryId) {
    document.getElementById('item-modal-title').textContent = 'Edit Menu Item';
    document.getElementById('item-id').value = item.id;
    document.getElementById('item-action').value = 'update_item';
    document.getElementById('item-category').value = categoryId;
    document.getElementById('item-name').value = item.name;
    document.getElementById('item-description').value = item.description || '';
    document.getElementById('item-price').value = item.price;
    document.getElementById('item-original-price').value = item.original_price || '';
    document.getElementById('existing-image').value = item.image || '';
    const isPopular = item.badge && String(item.badge).toLowerCase() === 'popular';
    document.getElementById('item-popular').checked = isPopular;
    document.getElementById('item-badge').value = isPopular ? 'Popular' : '';
    if (subcategoryId) {
        setTimeout(function() {
            document.getElementById('item-subcategory').value = subcategoryId;
        }, 100);
    }
    const event = new Event('change');
    document.getElementById('item-category').dispatchEvent(event);
    setTimeout(function() { openModal('item-modal'); }, 100);
}

document.getElementById('item-category')?.addEventListener('change', async function() {
    const catId = this.value;
    const subcatGroup = document.getElementById('subcategory-group');
    const subcatSelect = document.getElementById('item-subcategory');
    if (!catId) { subcatGroup.style.display = 'none'; return; }
    try {
        const response = await fetch('/api/menu.php?v=' + Date.now());
        const data = await response.json();
        const category = data.categories.find(function(c) { return c.id === catId; });
        if (category && category.subcategories && category.subcategories.length > 0) {
            subcatSelect.innerHTML = '<option value="">None</option>';
            for (let s = 0; s < category.subcategories.length; s++) {
                subcatSelect.innerHTML += '<option value="' + category.subcategories[s].id + '">' + escapeHtml(category.subcategories[s].label) + '</option>';
            }
            subcatGroup.style.display = 'block';
        } else { subcatGroup.style.display = 'none'; }
    } catch (error) { console.error('Error loading subcategories:', error); }
});

// Image Upload
document.getElementById('upload-zone')?.addEventListener('click', function() { document.getElementById('item-image').click(); });
document.getElementById('item-image')?.addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(ev) { document.getElementById('preview-img').src = ev.target.result; document.getElementById('image-preview').style.display = 'flex'; };
        reader.readAsDataURL(file);
    }
});
window.clearImage = function() { document.getElementById('item-image').value = ''; document.getElementById('image-preview').style.display = 'none'; document.getElementById('preview-img').src = ''; };

// Search
document.getElementById('search-input')?.addEventListener('input', function(e) {
    const term = e.target.value.toLowerCase();
    document.querySelectorAll('.items-table tbody tr').forEach(function(row) {
        row.style.display = row.textContent.toLowerCase().includes(term) ? '' : 'none';
    });
});

// ============================================================
// MOVE ITEM
// ============================================================

window.showMoveModal = function(itemId, itemName, currentCatId, currentSubId) {
    document.getElementById('move-item-id').value = itemId;
    document.getElementById('move-item-name').textContent = itemName;
    document.getElementById('move-current-cat').value = currentCatId;
    document.getElementById('move-current-sub').value = currentSubId || '';
    const catSelect = document.getElementById('move-target-cat');
    catSelect.innerHTML = '<option value="">Select category...</option>';
    fetch('/api/menu.php?v=' + Date.now()).then(function(res) { return res.json(); }).then(function(data) {
        for (let i = 0; i < data.categories.length; i++) {
            const cat = data.categories[i];
            const opt = document.createElement('option');
            opt.value = cat.id;
            opt.textContent = cat.label;
            if (cat.id === currentCatId) opt.selected = true;
            catSelect.appendChild(opt);
        }
        populateMoveSubcategories(catSelect.value);
    });
    openModal('move-modal');
};

function populateMoveSubcategories(catId) {
    const subcatGroup = document.getElementById('move-subcat-group'), subcatSelect = document.getElementById('move-target-subcat');
    if (!catId) { if (subcatGroup) subcatGroup.style.display = 'none'; return; }
    fetch('/api/menu.php?v=' + Date.now()).then(function(res) { return res.json(); }).then(function(data) {
        let category = null;
        for (let i = 0; i < data.categories.length; i++) { if (data.categories[i].id === catId) { category = data.categories[i]; break; } }
        if (category && category.subcategories && category.subcategories.length > 0) {
            if (subcatSelect) { subcatSelect.innerHTML = '<option value="">(None - move to main category)</option>'; for (let s = 0; s < category.subcategories.length; s++) { const opt = document.createElement('option'); opt.value = category.subcategories[s].id; opt.textContent = category.subcategories[s].label; subcatSelect.appendChild(opt); } }
            if (subcatGroup) subcatGroup.style.display = 'block';
        } else { if (subcatGroup) subcatGroup.style.display = 'none'; }
    });
}

document.getElementById('move-target-cat')?.addEventListener('change', function() { populateMoveSubcategories(this.value); });
document.getElementById('confirm-move')?.addEventListener('click', async function() {
    const itemId = document.getElementById('move-item-id').value, targetCat = document.getElementById('move-target-cat').value, targetSub = document.getElementById('move-target-subcat').value;
    if (!targetCat) { showToast('Please select a target category', 'error'); return; }
    try {
        const response = await fetch('/api/menu.php', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken }, body: JSON.stringify({ action: 'move_item', item_id: itemId, target_category_id: targetCat, target_subcategory_id: targetSub || null }) });
        const data = await response.json();
        if (data.success) { closeModal('move-modal'); loadMenu(); loadStats(); showToast('Item moved successfully', 'success'); }
        else { showToast(data.error || 'Move failed', 'error'); }
    } catch (error) { showToast('Network error', 'error'); }
});

// ============================================================
// RESERVATIONS
// ============================================================

async function loadReservations() {
    if (!reservationsContainer) return;
    reservationsContainer.innerHTML = '<div class="loading-spinner"><div class="spinner"></div><p>Loading reservations...</p></div>';
    try {
        const response = await fetch('/api/reservations.php', { method: 'GET', credentials: 'same-origin', headers: { 'Accept': 'application/json', 'Cache-Control': 'no-cache' } });
        if (!response.ok) throw new Error('HTTP ' + response.status);
        const data = await response.json();
        let reservations = data.reservations || (Array.isArray(data) ? data : []);
        currentReservations = reservations;
        if (currentReservations.length === 0) { reservationsContainer.innerHTML = '<div class="empty-state"><i class="fas fa-calendar-alt"></i><p>No reservations found</p></div>'; return; }
        renderReservations(currentReservations);
    } catch(error) { console.error('Reservations error:', error); reservationsContainer.innerHTML = '<div class="empty-state"><i class="fas fa-exclamation-triangle"></i><p>Failed to load reservations: ' + error.message + '</p></div>'; }
}

function renderReservations(reservations) {
    if (!reservations || reservations.length === 0) { if (reservationsContainer) reservationsContainer.innerHTML = '<div class="empty-state"><i class="fas fa-calendar-alt"></i><p>No reservations found</p></div>'; return; }
    let html = '<div class="reservations-container-modern">';
    for (let r = 0; r < reservations.length; r++) {
        const res = reservations[r];
        const statusClass = safeString(res.status) === 'confirmed' ? 'status-confirmed-modern' : (safeString(res.status) === 'cancelled' ? 'status-cancelled-modern' : 'status-pending-modern');
        const statusText = safeString(res.status) ? safeString(res.status).charAt(0).toUpperCase() + safeString(res.status).slice(1) : 'Pending';
        const guestName = safeString(res.name, 'Unknown'), guestEmail = safeString(res.email, 'No email'), guestPhone = safeString(res.phone, 'N/A'), guestTime = safeString(res.time, 'N/A'), guestCount = safeString(res.guests || res.party || '1'), reservationId = safeString(res.id), specialRequests = safeString(res.special_requests || res.notes, ''), guestInitial = guestName !== 'Unknown' ? guestName.charAt(0).toUpperCase() : '?';
        let formattedDate = safeString(res.date);
        try { const dateObj = new Date(res.date); if (!isNaN(dateObj.getTime())) { formattedDate = dateObj.toLocaleDateString('en-KE', { weekday: 'short', year: 'numeric', month: 'short', day: 'numeric' }); } } catch(e) { }
        html += '<div class="reservation-card-modern" data-reservation-id="' + escapeHtml(reservationId) + '"><div class="reservation-header-modern"><div class="reservation-guest-modern"><div class="reservation-avatar-modern">' + escapeHtml(guestInitial) + '</div><div class="reservation-info-modern"><h4>' + escapeHtml(guestName) + '</h4><p><i class="fas fa-envelope"></i> ' + escapeHtml(guestEmail) + '</p></div></div><div class="reservation-status-modern ' + statusClass + '">' + escapeHtml(statusText) + '</div></div><div class="reservation-details-modern"><div class="reservation-detail-modern"><i class="fas fa-phone"></i><span><strong>Phone:</strong> ' + escapeHtml(guestPhone) + '</span></div><div class="reservation-detail-modern"><i class="fas fa-calendar"></i><span><strong>Date:</strong> ' + escapeHtml(formattedDate) + '</span></div><div class="reservation-detail-modern"><i class="fas fa-clock"></i><span><strong>Time:</strong> ' + escapeHtml(guestTime) + '</span></div><div class="reservation-detail-modern"><i class="fas fa-users"></i><span><strong>Guests:</strong> ' + escapeHtml(guestCount) + '</span></div><div class="reservation-id-modern"><i class="fas fa-hashtag"></i> ' + escapeHtml(reservationId) + '</div></div>';
        if (specialRequests) { html += '<div class="reservation-requests-modern"><div class="label"><i class="fas fa-pen"></i> Special Requests</div><div class="text">' + escapeHtml(specialRequests) + '</div></div>'; }
        html += '<div class="reservation-actions-modern"><select class="form-select" style="width: auto; padding: 8px 14px; font-size: 0.75rem;" onchange="updateReservationStatus(\'' + escapeHtml(reservationId) + '\', this.value)"><option value="pending" ' + (safeString(res.status) === 'pending' ? 'selected' : '') + '>â³ Pending</option><option value="confirmed" ' + (safeString(res.status) === 'confirmed' ? 'selected' : '') + '>âœ… Confirmed</option><option value="cancelled" ' + (safeString(res.status) === 'cancelled' ? 'selected' : '') + '>âŒ Cancelled</option></select><button class="action-btn action-btn-danger action-btn-sm" onclick="deleteReservation(\'' + escapeHtml(reservationId) + '\')"><i class="fas fa-trash-alt"></i> Delete</button></div></div>';
    }
    html += '</div>';
    if (reservationsContainer) reservationsContainer.innerHTML = html;
}

window.refreshReservations = function() { loadReservations(); showToast('Reservations refreshed', 'success'); };
window.debugReservationsAPI = async function() { try { const response = await fetch('/api/reservations.php'); const text = await response.text(); console.log('API Response:', text); alert('API Response received. Check console for details.'); } catch(e) { alert('Error: ' + e.message); } };
window.updateReservationStatus = async function(id, status) {
    try {
        const response = await fetch('/api/reservations.php', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken }, body: JSON.stringify({ action: 'update_status', id: id, status: status }) });
        const data = await response.json();
        if (data.success) { showToast('Reservation ' + status, 'success'); loadReservations(); }
        else { showToast(data.error || 'Update failed', 'error'); }
    } catch (error) { showToast('Network error', 'error'); }
};
window.deleteReservation = async function(id) {
    if (!confirm('Delete this reservation?')) return;
    try {
        const response = await fetch('/api/reservations.php', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken }, body: JSON.stringify({ action: 'delete', id: id }) });
        const data = await response.json();
        if (data.success) { showToast('Reservation deleted', 'success'); loadReservations(); }
        else { showToast(data.error || 'Delete failed', 'error'); }
    } catch (error) { showToast('Network error', 'error'); }
};

document.getElementById('reservation-search')?.addEventListener('input', function(e) {
    const term = e.target.value.toLowerCase();
    const filtered = currentReservations.filter(function(res) { return safeString(res.name).toLowerCase().includes(term) || safeString(res.phone).toLowerCase().includes(term) || safeString(res.email).toLowerCase().includes(term); });
    renderReservations(filtered);
});
document.querySelectorAll('.filter-status').forEach(function(btn) {
    btn.addEventListener('click', function() {
        currentFilter = this.dataset.status;
        if (currentFilter === 'all') renderReservations(currentReservations);
        else renderReservations(currentReservations.filter(function(res) { return safeString(res.status) === currentFilter; }));
    });
});

// ============================================================
// SETTINGS FORM HANDLERS
// ============================================================

document.getElementById('password-form')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    const current = document.getElementById('current-password').value, newPass = document.getElementById('new-password').value, confirm = document.getElementById('confirm-password').value;
    if (newPass !== confirm) { showToast('Passwords do not match', 'error'); return; }
    if (newPass.length < 8) { showToast('Password must be at least 8 characters', 'error'); return; }
    try {
        const response = await fetch('/api/auth.php?action=change_password', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken }, body: JSON.stringify({ current_password: current, new_password: newPass, confirm_password: confirm }) });
        const data = await response.json();
        if (data.success) { showToast('Password updated', 'success'); document.getElementById('password-form').reset(); }
        else { showToast(data.error || 'Update failed', 'error'); }
    } catch(error) { showToast('Network error', 'error'); }
});

document.getElementById('restaurant-settings-form')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    const settings = { name: document.getElementById('restaurant-name').value, phone: document.getElementById('restaurant-phone').value, email: document.getElementById('restaurant-email').value, address: document.getElementById('restaurant-address').value, hours: document.getElementById('restaurant-hours')?.value || '', days: document.getElementById('restaurant-days')?.value || '', whatsapp: document.getElementById('whatsapp-number').value };
    try {
        const response = await fetch('/api/settings.php', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken }, body: JSON.stringify({ action: 'update_restaurant', settings: settings }) });
        const data = await response.json();
        if (data.success) showToast('Restaurant settings saved', 'success');
        else showToast(data.error || 'Save failed', 'error');
    } catch(error) { showToast('Network error', 'error'); }
});

document.getElementById('whatsapp-settings-form')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    const settings = { api_key: document.getElementById('whatsapp-api-key').value, phone_number: document.getElementById('whatsapp-number').value };
    try {
        const response = await fetch('/api/settings.php', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken }, body: JSON.stringify({ action: 'update_whatsapp', settings: settings }) });
        const data = await response.json();
        if (data.success) showToast('WhatsApp settings saved', 'success');
        else showToast(data.error || 'Save failed', 'error');
    } catch(error) { showToast('Network error', 'error'); }
});

window.testWhatsApp = async function() {
    const apiKey = document.getElementById('whatsapp-api-key').value, phoneNumber = document.getElementById('whatsapp-number').value;
    if (!apiKey || !phoneNumber) { showToast('Please save WhatsApp settings first', 'error'); return; }
    try {
        const response = await fetch('/api/settings.php', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken }, body: JSON.stringify({ action: 'test_whatsapp', api_key: apiKey, phone_number: phoneNumber }) });
        const data = await response.json();
        if (data.success) showToast('Test message sent!', 'success');
        else showToast(data.error || 'Test failed', 'error');
    } catch(error) { showToast('Network error', 'error'); }
};

// ============================================================
// BACKUP FUNCTIONS
// ============================================================

async function loadBackupsList() {
    try {
        const response = await fetch('/api/menu-backup.php?action=list');
        const data = await response.json();
        const container = document.getElementById('backups-list');
        if (data.success && data.backups && data.backups.length) {
            let html = '<div style="display: flex; flex-direction: column; gap: 10px;">';
            for (let i = 0; i < data.backups.length; i++) {
                const backup = data.backups[i];
                html += '<div style="display: flex; justify-content: space-between; align-items: center; padding: 10px; background: var(--gray-50); border-radius: 8px;"><div><div style="font-weight: 600;">' + escapeHtml(backup.filename) + '</div><div style="font-size: 0.7rem; color: var(--gray-500);">' + new Date(backup.filemtime * 1000).toLocaleString() + '</div></div><div style="display: flex; gap: 8px;"><button class="action-btn action-btn-sm" onclick="downloadBackup(\'' + backup.filename + '\')"><i class="fas fa-download"></i> Download</button><button class="action-btn action-btn-sm" onclick="restoreBackup(\'' + backup.filename + '\')"><i class="fas fa-undo-alt"></i> Restore</button><button class="action-btn action-btn-sm action-btn-danger" onclick="deleteBackup(\'' + backup.filename + '\')"><i class="fas fa-trash-alt"></i> Delete</button></div></div>';
            }
            html += '</div>';
            container.innerHTML = html;
        } else { container.innerHTML = '<div class="empty-state"><p>No backups found</p></div>'; }
    } catch (error) { document.getElementById('backups-list').innerHTML = '<div class="empty-state"><p>Failed to load backups</p></div>'; }
}

async function createBackup() { try { const response = await fetch('/api/menu-backup.php?action=create'); const data = await response.json(); if (data.success) { showToast('Backup created', 'success'); loadBackupsList(); } else { showToast(data.error || 'Backup failed', 'error'); } } catch (error) { showToast('Network error', 'error'); } }
async function restoreBackup(filename) { if (confirm('Restore "' + filename + '"?')) { try { const response = await fetch('/api/menu-backup.php?action=restore&file=' + encodeURIComponent(filename)); const data = await response.json(); if (data.success) { showToast('Menu restored', 'success'); loadMenu(); loadStats(); closeModal('backup-modal'); } else { showToast(data.error || 'Restore failed', 'error'); } } catch (error) { showToast('Network error', 'error'); } } }
async function deleteBackup(filename) { if (confirm('Delete "' + filename + '"?')) { try { const response = await fetch('/api/menu-backup.php?action=delete&file=' + encodeURIComponent(filename)); const data = await response.json(); if (data.success) { showToast('Backup deleted', 'success'); loadBackupsList(); } else { showToast(data.error || 'Delete failed', 'error'); } } catch (error) { showToast('Network error', 'error'); } } }
function downloadBackup(filename) { window.location.href = '/api/menu-backup.php?action=download&file=' + encodeURIComponent(filename); }
function exportMenu() { window.location.href = '/api/menu-backup.php?action=export'; }
async function importMenu(input) { const file = input.files[0]; if (!file) return; if (confirm('Import "' + file.name + '"?')) { const formData = new FormData(); formData.append('backup_file', file); try { const response = await fetch('/api/menu-backup.php?action=import', { method: 'POST', body: formData }); const data = await response.json(); if (data.success) { showToast('Menu imported', 'success'); loadMenu(); loadStats(); closeModal('backup-modal'); } else { showToast(data.error || 'Import failed', 'error'); } } catch (error) { showToast('Network error', 'error'); } } input.value = ''; }

// ============================================================
// INITIALIZATION
// ============================================================

document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM loaded, initializing dashboard...');
    resetSessionTimer();
    if (menuContainer) menuContainer.style.display = 'block';
    if (reservationsContainer) reservationsContainer.style.display = 'none';
    if (settingsContainer) settingsContainer.style.display = 'none';
    if (statsContainer) statsContainer.style.display = 'grid';
    if (menuToolbar) menuToolbar.style.display = 'flex';
    if (reservationsToolbar) reservationsToolbar.style.display = 'none';
    if (settingsToolbar) settingsToolbar.style.display = 'none';
    if (mainTitle) mainTitle.textContent = 'Menu Management';
    loadMenu();
    loadStats();
    const activities = ['click', 'keypress', 'mousemove', 'touchstart'];
    for (let i = 0; i < activities.length; i++) { document.addEventListener(activities[i], resetSessionTimer); }
});
</script>
</body>
</html>