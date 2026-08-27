<?php
/**
 * api/menu.php - Furusato Menu API
 * SECURITY HARDENED VERSION WITH GLOBAL CORS
 * - CORS headers for worldwide access
 * - CSRF protection
 * - Rate limiting
 * - File upload security
 * - Input sanitization
 * - Audit logging
 * - Admin session validation
 */

// ============================================================
// CORS Headers - Allow from ANY device worldwide
// ============================================================
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token, X-Requested-With');
header('Access-Control-Max-Age: 86400');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Enable error logging
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');
error_reporting(E_ALL);

// Kill all output buffers
while (ob_get_level()) ob_end_clean();

// Set JSON header
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

// Load functions
require_once __DIR__ . '/../includes/functions.php';

// ─────────────────────────────────────────────────────────────────────────────
// Constants
// ─────────────────────────────────────────────────────────────────────────────

const MAX_UPLOAD_SIZE = 5242880;  // 5MB
const ALLOWED_IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
const MAX_NAME_LENGTH = 100;
const MAX_DESCRIPTION_LENGTH = 1000;
const RATE_LIMIT_MENU_API = 120;  // 120 requests per hour

// ─────────────────────────────────────────────────────────────────────────────
// Helper Functions
// ─────────────────────────────────────────────────────────────────────────────

function sendJson($data, $code = 200) {
    http_response_code($code);
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Credentials: true');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

function sendError($message, $code = 400) {
    sendJson(['success' => false, 'error' => $message], $code);
}

// Check admin session with timeout
function checkAdminSession() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Check if logged in
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        logAudit('UNAUTHORIZED_MENU_API', "IP: " . getClientIP());
        return false;
    }
    
    // Check session timeout
    $timeout = 1800; // 30 minutes
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout) {
        session_destroy();
        return false;
    }
    
    $_SESSION['last_activity'] = time();
    return true;
}

// Rate limiting for API
function checkMenuApiRateLimit() {
    $ip = getClientIP();
    $rateFile = __DIR__ . '/../data/rate_limits_menu.json';
    
    $data = [];
    if (file_exists($rateFile)) {
        $content = file_get_contents($rateFile);
        $data = json_decode($content, true) ?: [];
    }
    
    $key = md5($ip . '_menu_api');
    $now = time();
    
    // Clean old entries (older than 1 hour)
    if (isset($data[$key])) {
        $data[$key] = array_filter($data[$key], function($ts) use ($now) {
            return ($now - $ts) < 3600;
        });
    } else {
        $data[$key] = [];
    }
    
    if (count($data[$key]) >= RATE_LIMIT_MENU_API) {
        logAudit('MENU_API_RATE_LIMIT', "IP: {$ip}");
        return false;
    }
    
    $data[$key][] = $now;
    file_put_contents($rateFile, json_encode($data), LOCK_EX);
    return true;
}

// Validate CSRF token
function validateMenuCsrf() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    $headers = getallheaders();
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $headers['X-CSRF-Token'] ?? '';
    
    if (empty($token) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        logAudit('MENU_CSRF_FAILED', "IP: " . getClientIP());
        return false;
    }
    
    return true;
}

// Secure file upload
function secureUploadMenuItemImage($file) {
    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds server size limit',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds form size limit',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
        ];
        return ['success' => false, 'error' => $errors[$file['error']] ?? 'Unknown upload error'];
    }
    
    // Check file size
    if ($file['size'] > MAX_UPLOAD_SIZE) {
        return ['success' => false, 'error' => 'File too large. Maximum size is 5MB'];
    }
    
    // Validate MIME type using finfo (not just extension)
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mimeType, ALLOWED_IMAGE_TYPES, true)) {
        return ['success' => false, 'error' => 'Invalid file type. Allowed: JPEG, PNG, WEBP, GIF'];
    }
    
    // Check for malicious content
    $content = file_get_contents($file['tmp_name']);
    if (strpos($content, '<?php') !== false || strpos($content, '<%') !== false) {
        return ['success' => false, 'error' => 'Security check failed: suspicious content detected'];
    }
    
    // Generate secure filename
    $extension = match($mimeType) {
        'image/jpeg' => '.jpg',
        'image/png' => '.png',
        'image/webp' => '.webp',
        'image/gif' => '.gif',
        default => '.jpg'
    };
    
    $filename = bin2hex(random_bytes(16)) . $extension;
    
    $targetDir = __DIR__ . '/../assets/images/menu/';
    
    if (!file_exists($targetDir)) {
        if (!mkdir($targetDir, 0750, true)) {
            return ['success' => false, 'error' => 'Failed to create upload directory'];
        }
        chmod($targetDir, 0750);
    }
    
    if (!is_writable($targetDir)) {
        return ['success' => false, 'error' => 'Upload directory is not writable'];
    }
    
    $targetPath = $targetDir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        chmod($targetPath, 0640);
        return ['success' => true, 'path' => 'assets/images/menu/' . $filename];
    }
    
    return ['success' => false, 'error' => 'Failed to save file'];
}

// Sanitize input data
function sanitizeMenuItem($data) {
    return [
        'name' => substr(trim(htmlspecialchars($data['name'] ?? '', ENT_QUOTES, 'UTF-8')), 0, MAX_NAME_LENGTH),
        'description' => substr(trim(htmlspecialchars($data['description'] ?? '', ENT_QUOTES, 'UTF-8')), 0, MAX_DESCRIPTION_LENGTH),
        'price' => isset($data['price']) ? max(0, floatval($data['price'])) : 0,
        'original_price' => isset($data['original_price']) ? max(0, floatval($data['original_price'])) : null,
        'badge' => isset($data['badge']) ? substr(trim(htmlspecialchars($data['badge'], ENT_QUOTES, 'UTF-8')), 0, 50) : null,
    ];
}

// Generate slug
function generateSlug($string) {
    $string = strtolower(trim($string));
    $string = preg_replace('/[^a-z0-9-]/', '-', $string);
    $string = preg_replace('/-+/', '-', $string);
    return trim($string, '-');
}

// ─────────────────────────────────────────────────────────────────────────────
// Main Request Handler
// ─────────────────────────────────────────────────────────────────────────────

try {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    
    // ============================================================
    // GET REQUEST (Public - No auth required)
    // ============================================================
    if ($method === 'GET') {
        // Rate limiting for GET requests
        if (!checkMenuApiRateLimit()) {
            sendError('Too many requests. Please try again later.', 429);
        }
        
        $menu = getJsonData('menu');
        
        if (!is_array($menu)) {
            $menu = ['categories' => []];
        }
        if (!isset($menu['categories'])) {
            $menu['categories'] = [];
        }
        
        // Sort categories by order
        usort($menu['categories'], function($a, $b) {
            $orderA = $a['order'] ?? 999;
            $orderB = $b['order'] ?? 999;
            return $orderA - $orderB;
        });
        
        // Sort subcategories and items by order
        foreach ($menu['categories'] as &$category) {
            if (isset($category['subcategories'])) {
                usort($category['subcategories'], function($a, $b) {
                    $orderA = $a['order'] ?? 999;
                    $orderB = $b['order'] ?? 999;
                    return $orderA - $orderB;
                });
            }
            if (isset($category['items'])) {
                usort($category['items'], function($a, $b) {
                    $orderA = $a['order'] ?? 999;
                    $orderB = $b['order'] ?? 999;
                    return $orderA - $orderB;
                });
            }
            if (isset($category['subcategories'])) {
                foreach ($category['subcategories'] as &$subcategory) {
                    if (isset($subcategory['items'])) {
                        usort($subcategory['items'], function($a, $b) {
                            $orderA = $a['order'] ?? 999;
                            $orderB = $b['order'] ?? 999;
                            return $orderA - $orderB;
                        });
                    }
                }
            }
        }
        
        // Check if popular items requested
        $isPopular = isset($_GET['popular']) && ($_GET['popular'] === 'true' || $_GET['popular'] === '1');
        
        if ($isPopular) {
            $allItemsWithPopularBadge = [];
            
            foreach ($menu['categories'] as $category) {
                if (isset($category['items']) && is_array($category['items'])) {
                    foreach ($category['items'] as $item) {
                        if (!empty($item['badge']) && strtolower(trim($item['badge'])) === 'popular' && (!isset($item['visible']) || $item['visible'] !== false)) {
                            $item['image_url'] = !empty($item['image']) ? getImageUrl($item['image']) : '/assets/images/menu/placeholder.webp';
                            $allItemsWithPopularBadge[] = $item;
                        }
                    }
                }
                
                if (isset($category['subcategories']) && is_array($category['subcategories'])) {
                    foreach ($category['subcategories'] as $subcategory) {
                        if (isset($subcategory['items']) && is_array($subcategory['items'])) {
                            foreach ($subcategory['items'] as $item) {
                                if (!empty($item['badge']) && strtolower(trim($item['badge'])) === 'popular' && (!isset($item['visible']) || $item['visible'] !== false)) {
                                    $item['image_url'] = !empty($item['image']) ? getImageUrl($item['image']) : '/assets/images/menu/placeholder.webp';
                                    $allItemsWithPopularBadge[] = $item;
                                }
                            }
                        }
                    }
                }
            }
            
            $popularItems = array_slice($allItemsWithPopularBadge, 0, 6);
            sendJson(['popular' => $popularItems, 'count' => count($popularItems)]);
        }
        
        // Add image URLs for all items
        foreach ($menu['categories'] as &$category) {
            if (isset($category['items']) && is_array($category['items'])) {
                foreach ($category['items'] as &$item) {
                    $item['image_url'] = !empty($item['image']) ? getImageUrl($item['image']) : '/assets/images/menu/placeholder.webp';
                }
            }
            if (isset($category['subcategories']) && is_array($category['subcategories'])) {
                foreach ($category['subcategories'] as &$subcategory) {
                    if (isset($subcategory['items']) && is_array($subcategory['items'])) {
                        foreach ($subcategory['items'] as &$item) {
                            $item['image_url'] = !empty($item['image']) ? getImageUrl($item['image']) : '/assets/images/menu/placeholder.webp';
                        }
                    }
                }
            }
        }
        
        sendJson($menu);
    }
    
    // ============================================================
    // POST REQUEST (Admin only)
    // ============================================================
    if ($method === 'POST') {
        // Admin authentication
        if (!checkAdminSession()) {
            sendError('Unauthorized. Please log in.', 401);
        }
        
        // Rate limiting
        if (!checkMenuApiRateLimit()) {
            sendError('Too many requests. Please try again later.', 429);
        }
        
        // CSRF validation
        if (!validateMenuCsrf()) {
            sendError('Invalid security token. Please refresh the page and try again.', 403);
        }
        
        // Get action
        $action = isset($_POST['action']) ? $_POST['action'] : '';
        $inputJSON = file_get_contents('php://input');
        $input = json_decode($inputJSON, true);
        
        if (empty($action) && isset($input['action'])) {
            $action = $input['action'];
        }
        
        // Log admin action
        logAudit('MENU_API_ACTION', "Action: {$action}, IP: " . getClientIP());
        
        // ============================================================
        // UPDATE ITEM
        // ============================================================
        if ($action === 'update_item' || $action === 'edit') {
            $itemId = isset($_POST['id']) ? sanitize($_POST['id']) : '';
            $categoryId = isset($_POST['category_id']) ? sanitize($_POST['category_id']) : '';
            $subcategoryId = isset($_POST['subcategory_id']) ? sanitize($_POST['subcategory_id']) : '';
            $existingImage = isset($_POST['existing_image']) ? sanitize($_POST['existing_image']) : '';
            
            $sanitized = sanitizeMenuItem($_POST);
            $name = $sanitized['name'];
            $description = $sanitized['description'];
            $price = $sanitized['price'];
            $badge = $sanitized['badge'];
            $originalPrice = $sanitized['original_price'];
            
            if (empty($itemId) || empty($categoryId) || empty($name)) {
                sendError('Missing required fields', 400);
            }
            
            $menu = getJsonData('menu');
            if (!$menu || !is_array($menu)) {
                sendError('Failed to load menu data', 500);
            }
            
            $imagePath = $existingImage;
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $uploadResult = secureUploadMenuItemImage($_FILES['image']);
                if ($uploadResult['success']) {
                    $imagePath = $uploadResult['path'];
                    logAudit('MENU_IMAGE_UPLOAD', "Item: {$itemId}, File: {$imagePath}");
                } else {
                    sendError('Image upload failed: ' . $uploadResult['error'], 400);
                }
            }
            
            $updated = false;
            
            foreach ($menu['categories'] as &$category) {
                if (isset($category['items']) && is_array($category['items'])) {
                    foreach ($category['items'] as &$item) {
                        if ($item['id'] === $itemId) {
                            $item['name'] = $name;
                            $item['description'] = $description;
                            $item['price'] = $price;
                            if ($badge) $item['badge'] = $badge;
                            if ($imagePath) $item['image'] = $imagePath;
                            if ($originalPrice && $originalPrice > $price) {
                                $item['original_price'] = $originalPrice;
                            } else {
                                unset($item['original_price']);
                            }
                            $updated = true;
                            break 2;
                        }
                    }
                }
                if (isset($category['subcategories']) && is_array($category['subcategories'])) {
                    foreach ($category['subcategories'] as &$subcategory) {
                        if (isset($subcategory['items']) && is_array($subcategory['items'])) {
                            foreach ($subcategory['items'] as &$item) {
                                if ($item['id'] === $itemId) {
                                    $item['name'] = $name;
                                    $item['description'] = $description;
                                    $item['price'] = $price;
                                    if ($badge) $item['badge'] = $badge;
                                    if ($imagePath) $item['image'] = $imagePath;
                                    if ($originalPrice && $originalPrice > $price) {
                                        $item['original_price'] = $originalPrice;
                                    } else {
                                        unset($item['original_price']);
                                    }
                                    $updated = true;
                                    break 3;
                                }
                            }
                        }
                    }
                }
            }
            
            if ($updated) {
                $menu['lastUpdated'] = date('c');
                setJsonData('menu', $menu);
                logAudit('MENU_ITEM_UPDATED', "Item ID: {$itemId}");
                sendJson(['success' => true, 'message' => 'Item updated successfully']);
            } else {
                sendError('Item not found', 404);
            }
        }
        
        // ============================================================
        // ADD NEW ITEM
        // ============================================================
        if ($action === 'add_item' || $action === 'add') {
            $categoryId = isset($_POST['category_id']) ? sanitize($_POST['category_id']) : '';
            $subcategoryId = isset($_POST['subcategory_id']) ? sanitize($_POST['subcategory_id']) : '';
            
            $sanitized = sanitizeMenuItem($_POST);
            $name = $sanitized['name'];
            $description = $sanitized['description'];
            $price = $sanitized['price'];
            $badge = $sanitized['badge'];
            $originalPrice = $sanitized['original_price'];
            
            if (empty($categoryId) || empty($name)) {
                sendError('Missing required fields', 400);
            }
            
            $menu = getJsonData('menu');
            if (!$menu || !is_array($menu)) {
                sendError('Failed to load menu data', 500);
            }
            
            $imagePath = '';
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $uploadResult = secureUploadMenuItemImage($_FILES['image']);
                if ($uploadResult['success']) {
                    $imagePath = $uploadResult['path'];
                }
            }
            
            $newItemId = 'item-' . bin2hex(random_bytes(8));
            
            $newItem = [
                'id' => $newItemId,
                'name' => $name,
                'description' => $description,
                'price' => $price,
                'image' => $imagePath ?: '',
                'badge' => $badge ?: null,
                'visible' => true,
                'order' => 999,
                'created' => date('c')
            ];
            
            if ($originalPrice && $originalPrice > $price) {
                $newItem['original_price'] = $originalPrice;
            }
            
            $added = false;
            foreach ($menu['categories'] as &$category) {
                if ($category['id'] === $categoryId) {
                    if ($subcategoryId) {
                        foreach ($category['subcategories'] as &$subcategory) {
                            if ($subcategory['id'] === $subcategoryId) {
                                if (!isset($subcategory['items'])) $subcategory['items'] = [];
                                $subcategory['items'][] = $newItem;
                                $added = true;
                                break 2;
                            }
                        }
                    } else {
                        if (!isset($category['items'])) $category['items'] = [];
                        $category['items'][] = $newItem;
                        $added = true;
                        break;
                    }
                }
            }
            
            if ($added) {
                $menu['lastUpdated'] = date('c');
                setJsonData('menu', $menu);
                logAudit('MENU_ITEM_ADDED', "Item: {$name}, Category: {$categoryId}");
                sendJson(['success' => true, 'message' => 'Item added successfully', 'id' => $newItemId]);
            } else {
                sendError('Category not found', 404);
            }
        }
        
        // ============================================================
        // DELETE ITEM
        // ============================================================
        if (($action === 'delete' || $action === 'delete_item') && isset($_POST['id'])) {
            $itemId = sanitize($_POST['id']);
            
            $menu = getJsonData('menu');
            if (!$menu || !is_array($menu)) {
                sendError('Failed to load menu data', 500);
            }
            
            $deleted = false;
            
            foreach ($menu['categories'] as &$cat) {
                if (isset($cat['items'])) {
                    foreach ($cat['items'] as $idx => $item) {
                        if ($item['id'] === $itemId) {
                            array_splice($cat['items'], $idx, 1);
                            $deleted = true;
                            break 2;
                        }
                    }
                }
                if (isset($cat['subcategories'])) {
                    foreach ($cat['subcategories'] as &$sub) {
                        if (isset($sub['items'])) {
                            foreach ($sub['items'] as $idx => $item) {
                                if ($item['id'] === $itemId) {
                                    array_splice($sub['items'], $idx, 1);
                                    $deleted = true;
                                    break 3;
                                }
                            }
                        }
                    }
                }
            }
            
            if ($deleted) {
                $menu['lastUpdated'] = date('c');
                setJsonData('menu', $menu);
                logAudit('MENU_ITEM_DELETED', "Item ID: {$itemId}");
                sendJson(['success' => true, 'message' => 'Item deleted successfully']);
            } else {
                sendError('Item not found', 404);
            }
        }
        
        // ============================================================
        // CREATE CATEGORY
        // ============================================================
        if ($action === 'create_category') {
            $label = isset($_POST['label']) ? substr(trim(htmlspecialchars($_POST['label'], ENT_QUOTES, 'UTF-8')), 0, 50) : '';
            $labelJp = isset($_POST['labelJp']) ? substr(trim(htmlspecialchars($_POST['labelJp'], ENT_QUOTES, 'UTF-8')), 0, 50) : '';
            $icon = isset($_POST['icon']) ? substr(trim(htmlspecialchars($_POST['icon'], ENT_QUOTES, 'UTF-8')), 0, 10) : '📋';
            $visible = isset($_POST['visible']) ? filter_var($_POST['visible'], FILTER_VALIDATE_BOOLEAN) : true;
            
            if (empty($label)) {
                sendError('Category label is required', 400);
            }
            
            $menu = getJsonData('menu');
            if (!$menu || !is_array($menu)) {
                sendError('Failed to load menu data', 500);
            }
            
            $categoryId = 'cat-' . bin2hex(random_bytes(8));
            $slug = generateSlug($label);
            
            $maxOrder = 0;
            foreach ($menu['categories'] as $cat) {
                $order = $cat['order'] ?? 0;
                if ($order > $maxOrder) $maxOrder = $order;
            }
            
            $newCategory = [
                'id' => $categoryId,
                'slug' => $slug,
                'label' => $label,
                'labelJp' => $labelJp,
                'icon' => $icon,
                'visible' => $visible,
                'order' => $maxOrder + 1,
                'subcategories' => [],
                'items' => [],
                'created' => date('c')
            ];
            
            $menu['categories'][] = $newCategory;
            $menu['lastUpdated'] = date('c');
            setJsonData('menu', $menu);
            logAudit('MENU_CATEGORY_CREATED', "Category: {$label}");
            sendJson(['success' => true, 'message' => 'Category created successfully', 'category' => $newCategory]);
        }
        
        // ============================================================
        // EDIT CATEGORY
        // ============================================================
        if ($action === 'edit_category') {
            $categoryId = isset($_POST['category_id']) ? sanitize($_POST['category_id']) : '';
            $label = isset($_POST['label']) ? substr(trim(htmlspecialchars($_POST['label'], ENT_QUOTES, 'UTF-8')), 0, 50) : '';
            $labelJp = isset($_POST['labelJp']) ? substr(trim(htmlspecialchars($_POST['labelJp'], ENT_QUOTES, 'UTF-8')), 0, 50) : '';
            $icon = isset($_POST['icon']) ? substr(trim(htmlspecialchars($_POST['icon'], ENT_QUOTES, 'UTF-8')), 0, 10) : '';
            $visible = isset($_POST['visible']) ? filter_var($_POST['visible'], FILTER_VALIDATE_BOOLEAN) : true;
            
            if (empty($categoryId) || empty($label)) {
                sendError('Category ID and label are required', 400);
            }
            
            $menu = getJsonData('menu');
            if (!$menu || !is_array($menu)) {
                sendError('Failed to load menu data', 500);
            }
            
            $found = false;
            foreach ($menu['categories'] as &$category) {
                if ($category['id'] === $categoryId) {
                    $category['label'] = $label;
                    if ($labelJp) $category['labelJp'] = $labelJp;
                    if ($icon) $category['icon'] = $icon;
                    $category['visible'] = $visible;
                    $category['slug'] = generateSlug($label);
                    $found = true;
                    break;
                }
            }
            
            if ($found) {
                $menu['lastUpdated'] = date('c');
                setJsonData('menu', $menu);
                logAudit('MENU_CATEGORY_UPDATED', "Category ID: {$categoryId}");
                sendJson(['success' => true, 'message' => 'Category updated successfully']);
            } else {
                sendError('Category not found', 404);
            }
        }
        
        // ============================================================
        // DELETE CATEGORY
        // ============================================================
        if ($action === 'delete_category') {
            $categoryId = isset($_POST['category_id']) ? sanitize($_POST['category_id']) : '';
            
            if (empty($categoryId)) {
                sendError('Category ID is required', 400);
            }
            
            $menu = getJsonData('menu');
            if (!$menu || !is_array($menu)) {
                sendError('Failed to load menu data', 500);
            }
            
            $categoryIndex = -1;
            foreach ($menu['categories'] as $idx => $category) {
                if ($category['id'] === $categoryId) {
                    $categoryIndex = $idx;
                    break;
                }
            }
            
            if ($categoryIndex === -1) {
                sendError('Category not found', 404);
            }
            
            array_splice($menu['categories'], $categoryIndex, 1);
            
            // Reorder remaining categories
            foreach ($menu['categories'] as $idx => &$category) {
                $category['order'] = $idx + 1;
            }
            
            $menu['lastUpdated'] = date('c');
            setJsonData('menu', $menu);
            logAudit('MENU_CATEGORY_DELETED', "Category ID: {$categoryId}");
            sendJson(['success' => true, 'message' => 'Category deleted successfully']);
        }
        
        // ============================================================
        // CREATE SUBCATEGORY
        // ============================================================
        if ($action === 'create_subcategory') {
            $parentId = isset($_POST['parent_id']) ? sanitize($_POST['parent_id']) : '';
            $label = isset($_POST['label']) ? substr(trim(htmlspecialchars($_POST['label'], ENT_QUOTES, 'UTF-8')), 0, 50) : '';
            $labelJp = isset($_POST['labelJp']) ? substr(trim(htmlspecialchars($_POST['labelJp'], ENT_QUOTES, 'UTF-8')), 0, 50) : '';
            $icon = isset($_POST['icon']) ? substr(trim(htmlspecialchars($_POST['icon'], ENT_QUOTES, 'UTF-8')), 0, 10) : '';
            
            if (empty($parentId) || empty($label)) {
                sendError('Parent category and subcategory label are required', 400);
            }
            
            $menu = getJsonData('menu');
            if (!$menu || !is_array($menu)) {
                sendError('Failed to load menu data', 500);
            }
            
            $subcategoryId = 'sub-' . bin2hex(random_bytes(8));
            
            $maxOrder = 0;
            foreach ($menu['categories'] as $cat) {
                if ($cat['id'] === $parentId) {
                    foreach ($cat['subcategories'] ?? [] as $sub) {
                        $order = $sub['order'] ?? 0;
                        if ($order > $maxOrder) $maxOrder = $order;
                    }
                    break;
                }
            }
            
            $newSubcategory = [
                'id' => $subcategoryId,
                'label' => $label,
                'labelJp' => $labelJp,
                'icon' => $icon ?: '📁',
                'visible' => true,
                'order' => $maxOrder + 1,
                'items' => [],
                'created' => date('c')
            ];
            
            $created = false;
            foreach ($menu['categories'] as &$category) {
                if ($category['id'] === $parentId) {
                    if (!isset($category['subcategories'])) {
                        $category['subcategories'] = [];
                    }
                    $category['subcategories'][] = $newSubcategory;
                    $created = true;
                    break;
                }
            }
            
            if ($created) {
                $menu['lastUpdated'] = date('c');
                setJsonData('menu', $menu);
                logAudit('MENU_SUBCATEGORY_CREATED', "Subcategory: {$label}, Parent: {$parentId}");
                sendJson(['success' => true, 'message' => 'Subcategory created successfully']);
            } else {
                sendError('Parent category not found', 404);
            }
        }
        
        // ============================================================
        // EDIT SUBCATEGORY
        // ============================================================
        if ($action === 'edit_subcategory') {
            $subcategoryId = isset($_POST['subcategory_id']) ? sanitize($_POST['subcategory_id']) : '';
            $label = isset($_POST['label']) ? substr(trim(htmlspecialchars($_POST['label'], ENT_QUOTES, 'UTF-8')), 0, 50) : '';
            $labelJp = isset($_POST['labelJp']) ? substr(trim(htmlspecialchars($_POST['labelJp'], ENT_QUOTES, 'UTF-8')), 0, 50) : '';
            $icon = isset($_POST['icon']) ? substr(trim(htmlspecialchars($_POST['icon'], ENT_QUOTES, 'UTF-8')), 0, 10) : '';
            $visible = isset($_POST['visible']) ? filter_var($_POST['visible'], FILTER_VALIDATE_BOOLEAN) : true;
            
            if (empty($subcategoryId) || empty($label)) {
                sendError('Subcategory ID and label are required', 400);
            }
            
            $menu = getJsonData('menu');
            if (!$menu || !is_array($menu)) {
                sendError('Failed to load menu data', 500);
            }
            
            $found = false;
            foreach ($menu['categories'] as &$category) {
                foreach ($category['subcategories'] as &$subcategory) {
                    if ($subcategory['id'] === $subcategoryId) {
                        $subcategory['label'] = $label;
                        if ($labelJp) $subcategory['labelJp'] = $labelJp;
                        if ($icon) $subcategory['icon'] = $icon;
                        $subcategory['visible'] = $visible;
                        $found = true;
                        break 2;
                    }
                }
            }
            
            if ($found) {
                $menu['lastUpdated'] = date('c');
                setJsonData('menu', $menu);
                logAudit('MENU_SUBCATEGORY_UPDATED', "Subcategory ID: {$subcategoryId}");
                sendJson(['success' => true, 'message' => 'Subcategory updated successfully']);
            } else {
                sendError('Subcategory not found', 404);
            }
        }
        
        // ============================================================
        // DELETE SUBCATEGORY
        // ============================================================
        if ($action === 'delete_subcategory') {
            $subcategoryId = isset($_POST['subcategory_id']) ? sanitize($_POST['subcategory_id']) : '';
            
            if (empty($subcategoryId)) {
                sendError('Subcategory ID is required', 400);
            }
            
            $menu = getJsonData('menu');
            if (!$menu || !is_array($menu)) {
                sendError('Failed to load menu data', 500);
            }
            
            $found = false;
            foreach ($menu['categories'] as &$category) {
                foreach ($category['subcategories'] as $idx => $subcategory) {
                    if ($subcategory['id'] === $subcategoryId) {
                        array_splice($category['subcategories'], $idx, 1);
                        $found = true;
                        break 2;
                    }
                }
            }
            
            if ($found) {
                $menu['lastUpdated'] = date('c');
                setJsonData('menu', $menu);
                logAudit('MENU_SUBCATEGORY_DELETED', "Subcategory ID: {$subcategoryId}");
                sendJson(['success' => true, 'message' => 'Subcategory deleted successfully']);
            } else {
                sendError('Subcategory not found', 404);
            }
        }
        
        // ============================================================
        // REORDER CATEGORIES
        // ============================================================
        if ($action === 'reorder_categories') {
            $categoryIds = isset($input['category_ids']) ? $input['category_ids'] : [];
            
            if (empty($categoryIds)) {
                sendError('Category IDs are required', 400);
            }
            
            $menu = getJsonData('menu');
            if (!$menu || !is_array($menu)) {
                sendError('Failed to load menu data', 500);
            }
            
            $reorderedCategories = [];
            foreach ($categoryIds as $index => $categoryId) {
                foreach ($menu['categories'] as $category) {
                    if ($category['id'] === $categoryId) {
                        $category['order'] = $index + 1;
                        $reorderedCategories[] = $category;
                        break;
                    }
                }
            }
            
            foreach ($menu['categories'] as $category) {
                if (!in_array($category, $reorderedCategories)) {
                    $category['order'] = count($reorderedCategories) + 1;
                    $reorderedCategories[] = $category;
                }
            }
            
            $menu['categories'] = $reorderedCategories;
            $menu['lastUpdated'] = date('c');
            setJsonData('menu', $menu);
            logAudit('MENU_REORDER_CATEGORIES', "Order: " . implode(',', $categoryIds));
            sendJson(['success' => true, 'message' => 'Categories reordered successfully']);
        }
        
        // ============================================================
        // REORDER SUBCATEGORIES
        // ============================================================
        if ($action === 'reorder_subcategories') {
            $categoryId = isset($input['category_id']) ? sanitize($input['category_id']) : '';
            $subcategoryIds = isset($input['subcategory_ids']) ? $input['subcategory_ids'] : [];
            
            if (empty($categoryId) || empty($subcategoryIds)) {
                sendError('Category ID and subcategory IDs are required', 400);
            }
            
            $menu = getJsonData('menu');
            if (!$menu || !is_array($menu)) {
                sendError('Failed to load menu data', 500);
            }
            
            $found = false;
            foreach ($menu['categories'] as &$category) {
                if ($category['id'] === $categoryId) {
                    $reorderedSubcategories = [];
                    foreach ($subcategoryIds as $index => $subId) {
                        foreach ($category['subcategories'] as $sub) {
                            if ($sub['id'] === $subId) {
                                $sub['order'] = $index + 1;
                                $reorderedSubcategories[] = $sub;
                                break;
                            }
                        }
                    }
                    $category['subcategories'] = $reorderedSubcategories;
                    $found = true;
                    break;
                }
            }
            
            if ($found) {
                $menu['lastUpdated'] = date('c');
                setJsonData('menu', $menu);
                logAudit('MENU_REORDER_SUBCATEGORIES', "Category: {$categoryId}");
                sendJson(['success' => true, 'message' => 'Subcategories reordered successfully']);
            } else {
                sendError('Category not found', 404);
            }
        }
        
        // ============================================================
        // REORDER ITEMS
        // ============================================================
        if ($action === 'reorder_items') {
            $categoryId = isset($input['category_id']) ? sanitize($input['category_id']) : '';
            $subcategoryId = isset($input['subcategory_id']) ? sanitize($input['subcategory_id']) : '';
            $itemIds = isset($input['item_ids']) ? $input['item_ids'] : [];
            
            if (empty($itemIds)) {
                sendError('Item IDs are required', 400);
            }
            
            $menu = getJsonData('menu');
            if (!$menu || !is_array($menu)) {
                sendError('Failed to load menu data', 500);
            }
            
            $found = false;
            
            if ($subcategoryId) {
                foreach ($menu['categories'] as &$category) {
                    foreach ($category['subcategories'] as &$subcategory) {
                        if ($subcategory['id'] === $subcategoryId) {
                            $reorderedItems = [];
                            foreach ($itemIds as $index => $itemId) {
                                foreach ($subcategory['items'] as $item) {
                                    if ($item['id'] === $itemId) {
                                        $item['order'] = $index + 1;
                                        $reorderedItems[] = $item;
                                        break;
                                    }
                                }
                            }
                            $subcategory['items'] = $reorderedItems;
                            $found = true;
                            break 2;
                        }
                    }
                }
            } elseif ($categoryId) {
                foreach ($menu['categories'] as &$category) {
                    if ($category['id'] === $categoryId) {
                        $reorderedItems = [];
                        foreach ($itemIds as $index => $itemId) {
                            foreach ($category['items'] as $item) {
                                if ($item['id'] === $itemId) {
                                    $item['order'] = $index + 1;
                                    $reorderedItems[] = $item;
                                    break;
                                }
                            }
                        }
                        $category['items'] = $reorderedItems;
                        $found = true;
                        break;
                    }
                }
            }
            
            if ($found) {
                $menu['lastUpdated'] = date('c');
                setJsonData('menu', $menu);
                logAudit('MENU_REORDER_ITEMS', "Category: {$categoryId}, Subcategory: {$subcategoryId}");
                sendJson(['success' => true, 'message' => 'Items reordered successfully']);
            } else {
                sendError('Location not found', 404);
            }
        }
        
        // ============================================================
        // MOVE MENU ITEM
        // ============================================================
        if ($action === 'move_item') {
            $itemId = isset($input['item_id']) ? sanitize($input['item_id']) : '';
            $targetCategoryId = isset($input['target_category_id']) ? sanitize($input['target_category_id']) : '';
            $targetSubcategoryId = isset($input['target_subcategory_id']) ? sanitize($input['target_subcategory_id']) : '';
            
            if (empty($itemId) || empty($targetCategoryId)) {
                sendError('Item ID and target category are required', 400);
            }
            
            $menu = getJsonData('menu');
            if (!$menu || !is_array($menu)) {
                sendError('Failed to load menu data', 500);
            }
            
            // Find and remove item
            $itemToMove = null;
            
            foreach ($menu['categories'] as $catIdx => &$category) {
                if (isset($category['items']) && is_array($category['items'])) {
                    foreach ($category['items'] as $itemIdx => $item) {
                        if ($item['id'] === $itemId) {
                            $itemToMove = $item;
                            array_splice($category['items'], $itemIdx, 1);
                            break 2;
                        }
                    }
                }
                if (isset($category['subcategories']) && is_array($category['subcategories'])) {
                    foreach ($category['subcategories'] as $subIdx => &$subcategory) {
                        if (isset($subcategory['items']) && is_array($subcategory['items'])) {
                            foreach ($subcategory['items'] as $itemIdx => $item) {
                                if ($item['id'] === $itemId) {
                                    $itemToMove = $item;
                                    array_splice($subcategory['items'], $itemIdx, 1);
                                    break 3;
                                }
                            }
                        }
                    }
                }
            }
            
            if (!$itemToMove) {
                sendError('Item not found', 404);
            }
            
            // Add to target location
            $added = false;
            foreach ($menu['categories'] as &$category) {
                if ($category['id'] === $targetCategoryId) {
                    if (!empty($targetSubcategoryId)) {
                        foreach ($category['subcategories'] as &$subcategory) {
                            if ($subcategory['id'] === $targetSubcategoryId) {
                                if (!isset($subcategory['items'])) $subcategory['items'] = [];
                                $subcategory['items'][] = $itemToMove;
                                $added = true;
                                break 2;
                            }
                        }
                        if (!$added) {
                            sendError('Target subcategory not found', 404);
                        }
                    } else {
                        if (!isset($category['items'])) $category['items'] = [];
                        $category['items'][] = $itemToMove;
                        $added = true;
                        break;
                    }
                }
            }
            
            if ($added) {
                $menu['lastUpdated'] = date('c');
                setJsonData('menu', $menu);
                logAudit('MENU_ITEM_MOVED', "Item: {$itemId}, Target Category: {$targetCategoryId}");
                sendJson(['success' => true, 'message' => 'Item moved successfully']);
            } else {
                sendError('Target category not found', 404);
            }
        }
        
        // ============================================================
        // GET STATS
        // ============================================================
        if ($action === 'get_stats') {
            $menu = getJsonData('menu');
            
            $totalCategories = count($menu['categories'] ?? []);
            $totalSubcategories = 0;
            $totalItems = 0;
            $totalPopularItems = 0;
            
            foreach ($menu['categories'] ?? [] as $category) {
                $totalItems += count($category['items'] ?? []);
                $totalSubcategories += count($category['subcategories'] ?? []);
                
                foreach ($category['items'] ?? [] as $item) {
                    if (!empty($item['badge']) && strtolower(trim($item['badge'])) === 'popular') {
                        $totalPopularItems++;
                    }
                }
                
                foreach ($category['subcategories'] ?? [] as $subcategory) {
                    $totalItems += count($subcategory['items'] ?? []);
                    foreach ($subcategory['items'] ?? [] as $item) {
                        if (!empty($item['badge']) && strtolower(trim($item['badge'])) === 'popular') {
                            $totalPopularItems++;
                        }
                    }
                }
            }
            
            sendJson([
                'success' => true,
                'stats' => [
                    'categories' => $totalCategories,
                    'subcategories' => $totalSubcategories,
                    'total_items' => $totalItems,
                    'popular_items' => $totalPopularItems,
                    'last_updated' => $menu['lastUpdated'] ?? null
                ]
            ]);
        }
        
        sendError('Invalid action: ' . $action, 400);
    }
    
    sendError('Method not allowed', 405);
    
} catch (Exception $e) {
    error_log('Menu API Fatal Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    sendError('Server error occurred. Please try again later.', 500);
}
?>