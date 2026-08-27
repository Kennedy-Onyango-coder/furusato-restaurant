<?php
/**
 * api/settings.php - Enhanced Settings API for Furusato Admin Dashboard
 * SECURITY HARDENED VERSION WITH CORS
 * - CORS headers for global access
 * - CSRF validation
 * - Rate limiting
 * - Session validation with IP binding
 * - Audit logging
 * - Input validation and sanitization
 */

/// ============================================================
// CORS Headers - Allow from ANY device worldwide
// ============================================================
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token, X-Requested-With');
header('Access-Control-Max-Age: 86400');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

ini_set('display_errors', 0);
ini_set('log_errors', 1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../includes/functions.php';

// ─────────────────────────────────────────────────────────────────────────────
// Constants
// ─────────────────────────────────────────────────────────────────────────────

const MAX_NAME_LENGTH = 100;
const MAX_PHONE_LENGTH = 20;
const MAX_EMAIL_LENGTH = 100;
const MAX_ADDRESS_LENGTH = 500;
const MAX_API_KEY_LENGTH = 50;

// Secure directory permissions
const DIR_PERMISSIONS = 0750;
const FILE_PERMISSIONS = 0640;

// ─────────────────────────────────────────────────────────────────────────────
// Data Functions
// ─────────────────────────────────────────────────────────────────────────────

function getSettingsFile() {
    $dataDir = __DIR__ . '/../data';
    if (!is_dir($dataDir)) {
        mkdir($dataDir, DIR_PERMISSIONS, true);
        chmod($dataDir, DIR_PERMISSIONS);
    }
    return $dataDir . '/settings.json';
}

function getSettings() {
    $settingsFile = getSettingsFile();
    if (!file_exists($settingsFile)) {
        $defaults = [
            'restaurant' => [
                'name' => 'Furusato Japanese Restaurant',
                'phone' => '+254 722 488 706',
                'email' => 'furusatoreservation@gmail.com',
                'address' => 'Ring Road Parklands, Westlands, Nairobi, Kenya'
            ],
            'whatsapp' => [
                'api_key' => '3219514',
                'phone_number' => '+254734639203'
            ],
            'last_updated' => date('c')
        ];
        file_put_contents($settingsFile, json_encode($defaults, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
        chmod($settingsFile, FILE_PERMISSIONS);
        return $defaults;
    }
    
    $data = json_decode(file_get_contents($settingsFile), true);
    
    // Ensure structure exists for backward compatibility
    if (!isset($data['restaurant'])) {
        $data['restaurant'] = [
            'name' => 'Furusato Japanese Restaurant',
            'phone' => '+254 722 488 706',
            'email' => 'info@furusatorestaurant.com',
            'address' => 'Nairobi, Kenya'
        ];
    }
    if (!isset($data['whatsapp'])) {
        $data['whatsapp'] = ['api_key' => '3219514', 'phone_number' => '+254734639203'];
    }
    
    return $data;
}

function saveSettings($data) {
    $settingsFile = getSettingsFile();
    $data['last_updated'] = date('c');
    file_put_contents($settingsFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
    chmod($settingsFile, FILE_PERMISSIONS);
}

// ─────────────────────────────────────────────────────────────────────────────
// Validation Functions
// ─────────────────────────────────────────────────────────────────────────────

function validatePhoneNumber($phone) {
    // Remove spaces, hyphens, parentheses
    $clean = preg_replace('/[\s\-\(\)]/', '', $phone);
    // Must start with + or digit, 8-15 digits total
    return preg_match('/^[\+]?[0-9]{7,15}$/', $clean) === 1;
}

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function sanitizeSetting($value, $maxLength = 255) {
    if ($value === null) return '';
    $cleaned = trim(htmlspecialchars(strip_tags($value), ENT_QUOTES, 'UTF-8'));
    return substr($cleaned, 0, $maxLength);
}

// ─────────────────────────────────────────────────────────────────────────────
// Admin Session Validation (with IP binding)
// ─────────────────────────────────────────────────────────────────────────────

function validateAdminSession() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Check if logged in
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        return false;
    }
    
    // Validate IP address hasn't changed
    $currentIP = getClientIP();
    $sessionIP = $_SESSION['session_ip'] ?? '';
    if (!empty($sessionIP) && $currentIP !== $sessionIP) {
        logAudit('SETTINGS_IP_MISMATCH', "Expected: {$sessionIP}, Got: {$currentIP}");
        return false;
    }
    
    // Validate User-Agent hasn't changed
    $currentUA = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $sessionUA = $_SESSION['session_ua'] ?? '';
    if (!empty($sessionUA) && $currentUA !== $sessionUA) {
        logAudit('SETTINGS_UA_MISMATCH', "Expected: {$sessionUA}");
        return false;
    }
    
    // Check session timeout (30 minutes)
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > 1800) {
        return false;
    }
    
    $_SESSION['last_activity'] = time();
    return true;
}

// ─────────────────────────────────────────────────────────────────────────────
// CSRF Validation
// ─────────────────────────────────────────────────────────────────────────────

function validateSettingsCsrf() {
    $headers = getallheaders();
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $headers['X-CSRF-Token'] ?? '';
    
    if (empty($token)) {
        logAudit('SETTINGS_CSRF_MISSING', "IP: " . getClientIP());
        return false;
    }
    
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        logAudit('SETTINGS_CSRF_FAILED', "IP: " . getClientIP());
        return false;
    }
    
    return true;
}

// ─────────────────────────────────────────────────────────────────────────────
// Rate Limiting
// ─────────────────────────────────────────────────────────────────────────────

function checkSettingsRateLimit() {
    $ip = getClientIP();
    $rateFile = __DIR__ . '/../data/rate_limits_settings.json';
    $limit = 60; // 60 requests per hour (increased for better UX)
    $window = 3600; // 1 hour
    
    $data = [];
    if (file_exists($rateFile)) {
        $content = file_get_contents($rateFile);
        $data = json_decode($content, true) ?: [];
    }
    
    $key = md5($ip . '_settings');
    $now = time();
    
    if (isset($data[$key])) {
        $data[$key] = array_filter($data[$key], function($ts) use ($now, $window) {
            return ($now - $ts) < $window;
        });
    } else {
        $data[$key] = [];
    }
    
    if (count($data[$key]) >= $limit) {
        logAudit('SETTINGS_RATE_LIMIT', "IP: {$ip}");
        return false;
    }
    
    $data[$key][] = $now;
    file_put_contents($rateFile, json_encode($data), LOCK_EX);
    return true;
}

// ─────────────────────────────────────────────────────────────────────────────
// WhatsApp Test Function
// ─────────────────────────────────────────────────────────────────────────────

function testWhatsAppMessage($apiKey, $phoneNumber) {
    $message = urlencode("✅ Test message from Furusato Admin Dashboard! Your WhatsApp integration is working correctly.");
    $url = "https://api.callmebot.com/whatsapp.php?phone=" . urlencode($phoneNumber) . "&text=" . $message . "&apikey=" . urlencode($apiKey);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Set to false for API compatibility
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Furusato-Restaurant/1.0');
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($httpCode === 200 || strpos($response, 'Message sent') !== false) {
        return ['success' => true, 'message' => 'Test message sent successfully!'];
    }
    
    return ['success' => false, 'error' => 'Failed to send test message. Please check your API key and phone number.'];
}

// ─────────────────────────────────────────────────────────────────────────────
// Helper function to send JSON response with CORS
// ─────────────────────────────────────────────────────────────────────────────

function sendSettingsResponse($data, $code = 200) {
    http_response_code($code);
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Credentials: true');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// Main Request Handler
// ─────────────────────────────────────────────────────────────────────────────

$method = $_SERVER['REQUEST_METHOD'];
$clientIP = getClientIP();

// ============================================================
// GET settings (public - safe data only, no auth required)
// ============================================================
if ($method === 'GET') {
    // Light rate limiting for GET requests
    if (!checkSettingsRateLimit()) {
        sendSettingsResponse(['success' => false, 'error' => 'Too many requests. Please try again later.'], 429);
    }
    
    $settings = getSettings();
    
    // Return only safe public data (hide API key)
    sendSettingsResponse([
        'success' => true,
        'restaurant' => $settings['restaurant'],
        'whatsapp' => [
            'phone_number' => $settings['whatsapp']['phone_number'] ?? '',
            'configured' => !empty($settings['whatsapp']['api_key'])
        ]
    ]);
}

// ============================================================
// POST requests require admin authentication
// ============================================================
if ($method === 'POST') {
    // Rate limiting
    if (!checkSettingsRateLimit()) {
        sendSettingsResponse(['success' => false, 'error' => 'Too many requests. Please try again later.'], 429);
    }
    
    // Admin session validation
    if (!validateAdminSession()) {
        sendSettingsResponse(['success' => false, 'error' => 'Unauthorized. Please log in again.'], 401);
    }
    
    // CSRF validation
    if (!validateSettingsCsrf()) {
        sendSettingsResponse(['success' => false, 'error' => 'Invalid security token. Please refresh the page and try again.'], 403);
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        $input = [];
    }
    
    $action = $input['action'] ?? '';
    
    // ============================================================
    // Update Restaurant Settings
    // ============================================================
    if ($action === 'update_restaurant') {
        $settings = getSettings();
        $changes = [];
        
        if (isset($input['settings']) && is_array($input['settings'])) {
            $restaurantSettings = $input['settings'];
            
            if (isset($restaurantSettings['name'])) {
                $settings['restaurant']['name'] = sanitizeSetting($restaurantSettings['name'], MAX_NAME_LENGTH);
                $changes[] = 'name';
            }
            
            if (isset($restaurantSettings['phone'])) {
                $phone = sanitizeSetting($restaurantSettings['phone'], MAX_PHONE_LENGTH);
                if (!validatePhoneNumber($phone)) {
                    sendSettingsResponse(['success' => false, 'error' => 'Invalid phone number format. Use international format (e.g., +254722488706)'], 400);
                }
                $settings['restaurant']['phone'] = $phone;
                $changes[] = 'phone';
            }
            
            if (isset($restaurantSettings['email'])) {
                $email = sanitizeSetting($restaurantSettings['email'], MAX_EMAIL_LENGTH);
                if (!validateEmail($email)) {
                    sendSettingsResponse(['success' => false, 'error' => 'Invalid email address'], 400);
                }
                $settings['restaurant']['email'] = $email;
                $changes[] = 'email';
            }
            
            if (isset($restaurantSettings['address'])) {
                $settings['restaurant']['address'] = sanitizeSetting($restaurantSettings['address'], MAX_ADDRESS_LENGTH);
                $changes[] = 'address';
            }
        }
        
        saveSettings($settings);
        logAudit('SETTINGS_UPDATED', "Fields: " . implode(', ', $changes) . ", IP: {$clientIP}");
        sendSettingsResponse(['success' => true, 'message' => 'Restaurant settings updated']);
    }
    
    // ============================================================
    // Update WhatsApp Settings
    // ============================================================
    if ($action === 'update_whatsapp') {
        $settings = getSettings();
        $changes = [];
        
        if (isset($input['settings']) && is_array($input['settings'])) {
            $whatsappSettings = $input['settings'];
            
            if (isset($whatsappSettings['api_key'])) {
                $apiKey = sanitizeSetting($whatsappSettings['api_key'], MAX_API_KEY_LENGTH);
                if (!empty($apiKey) && !preg_match('/^[0-9]+$/', $apiKey)) {
                    sendSettingsResponse(['success' => false, 'error' => 'Invalid API key format. Must contain only numbers.'], 400);
                }
                $settings['whatsapp']['api_key'] = $apiKey;
                $changes[] = 'api_key';
            }
            
            if (isset($whatsappSettings['phone_number'])) {
                $phoneNumber = sanitizeSetting($whatsappSettings['phone_number'], MAX_PHONE_LENGTH);
                if (!validatePhoneNumber($phoneNumber)) {
                    sendSettingsResponse(['success' => false, 'error' => 'Invalid phone number format. Use international format (e.g., +254734639203)'], 400);
                }
                $settings['whatsapp']['phone_number'] = $phoneNumber;
                $changes[] = 'phone_number';
            }
        }
        
        saveSettings($settings);
        logAudit('WHATSAPP_SETTINGS_UPDATED', "Fields: " . implode(', ', $changes) . ", IP: {$clientIP}");
        sendSettingsResponse(['success' => true, 'message' => 'WhatsApp settings updated']);
    }
    
    // ============================================================
    // Test WhatsApp
    // ============================================================
    if ($action === 'test_whatsapp') {
        $apiKey = sanitizeSetting($input['api_key'] ?? '', MAX_API_KEY_LENGTH);
        $phoneNumber = sanitizeSetting($input['phone_number'] ?? '', MAX_PHONE_LENGTH);
        
        if (empty($apiKey) || empty($phoneNumber)) {
            sendSettingsResponse(['success' => false, 'error' => 'API key and phone number are required'], 400);
        }
        
        if (!validatePhoneNumber($phoneNumber)) {
            sendSettingsResponse(['success' => false, 'error' => 'Invalid phone number format. Use international format (e.g., +254734639203)'], 400);
        }
        
        $result = testWhatsAppMessage($apiKey, $phoneNumber);
        
        if ($result['success']) {
            logAudit('WHATSAPP_TEST_SUCCESS', "Phone: {$phoneNumber}, IP: {$clientIP}");
        } else {
            logAudit('WHATSAPP_TEST_FAILED', "Phone: {$phoneNumber}, Error: {$result['error']}, IP: {$clientIP}");
        }
        
        sendSettingsResponse($result);
    }
    
    // ============================================================
    // Legacy support for simple key-value updates
    // ============================================================
    $settings = getSettings();
    $allowed = ['name', 'email', 'phone', 'whatsapp', 'address', 'hours'];
    $updated = false;
    $changes = [];
    
    foreach ($input as $key => $value) {
        if (in_array($key, $allowed)) {
            $sanitized = sanitizeSetting($value, $key === 'address' ? MAX_ADDRESS_LENGTH : MAX_NAME_LENGTH);
            
            if ($key === 'whatsapp') {
                if (!validatePhoneNumber($sanitized)) {
                    sendSettingsResponse(['success' => false, 'error' => 'Invalid WhatsApp number format'], 400);
                }
                $settings['whatsapp']['phone_number'] = $sanitized;
                $changes[] = 'whatsapp';
            } elseif ($key === 'email') {
                if (!validateEmail($sanitized)) {
                    sendSettingsResponse(['success' => false, 'error' => 'Invalid email address'], 400);
                }
                $settings['restaurant'][$key] = $sanitized;
                $changes[] = $key;
            } elseif ($key === 'phone') {
                if (!validatePhoneNumber($sanitized)) {
                    sendSettingsResponse(['success' => false, 'error' => 'Invalid phone number format'], 400);
                }
                $settings['restaurant'][$key] = $sanitized;
                $changes[] = $key;
            } else {
                $settings['restaurant'][$key] = $sanitized;
                $changes[] = $key;
            }
            $updated = true;
        }
    }
    
    if ($updated) {
        saveSettings($settings);
        logAudit('SETTINGS_UPDATED_LEGACY', "Fields: " . implode(', ', $changes) . ", IP: {$clientIP}");
        sendSettingsResponse(['success' => true, 'message' => 'Settings updated']);
    } else {
        sendSettingsResponse(['success' => false, 'error' => 'No valid settings to update'], 400);
    }
}

// ============================================================
// Method not allowed
// ============================================================
sendSettingsResponse(['success' => false, 'error' => 'Method not allowed'], 405);
?>