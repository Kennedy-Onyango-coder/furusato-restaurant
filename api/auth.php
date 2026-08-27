<?php
/**
 * api/auth.php - Authentication API for Furusato Admin
 * FIXED: CORS headers to allow login from any device
 */

// ============================================================
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

error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

while (ob_get_level()) ob_end_clean();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

require_once __DIR__ . '/../includes/functions.php';

// ─────────────────────────────────────────────────────────────────────────────
// Constants
// ─────────────────────────────────────────────────────────────────────────────

const MAX_LOGIN_ATTEMPTS = 5;
const LOCKOUT_DURATION = 900; // 15 minutes
const RATE_LIMIT_LOGIN = 10;   // 10 attempts per hour

// ─────────────────────────────────────────────────────────────────────────────
// Helper Functions
// ─────────────────────────────────────────────────────────────────────────────

function authJsonResponse($data, $code = 200) {
    while (ob_get_level()) ob_end_clean();
    http_response_code($code);
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Credentials: true');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function sendAuthError($message, $code = 401) {
    authJsonResponse(['error' => $message], $code);
}

// Check rate limit for login attempts
function checkLoginRateLimit($ip) {
    $rateFile = __DIR__ . '/../data/login_rate_limits.json';
    $data = [];
    
    if (file_exists($rateFile)) {
        $content = file_get_contents($rateFile);
        $data = json_decode($content, true) ?: [];
    }
    
    $key = md5($ip . '_login');
    $now = time();
    $window = 3600; // 1 hour
    
    if (isset($data[$key])) {
        $data[$key] = array_filter($data[$key], function($ts) use ($now, $window) {
            return ($now - $ts) < $window;
        });
    } else {
        $data[$key] = [];
    }
    
    if (count($data[$key]) >= RATE_LIMIT_LOGIN) {
        logAudit('LOGIN_RATE_LIMIT', "IP: {$ip}");
        return false;
    }
    
    $data[$key][] = $now;
    file_put_contents($rateFile, json_encode($data), LOCK_EX);
    return true;
}

// Check if IP is blocked due to too many failed attempts
function isLoginBlocked($ip) {
    $attempts = loadAttempts();
    
    if (isset($attempts[$ip])) {
        $blockedUntil = $attempts[$ip]['blocked_until'] ?? 0;
        if ($blockedUntil > time()) {
            return true;
        }
    }
    return false;
}

// Track failed login attempt
function trackFailedLogin($email, $ip) {
    $attempts = loadAttempts();
    $now = time();
    
    if (!isset($attempts[$ip])) {
        $attempts[$ip] = [
            'failures' => [],
            'blocked_until' => 0,
            'attempt_count' => 0
        ];
    }
    
    $attempts[$ip]['failures'][] = $now;
    $attempts[$ip]['attempt_count'] = count($attempts[$ip]['failures']);
    
    $attempts[$ip]['failures'] = array_filter($attempts[$ip]['failures'], function($ts) use ($now) {
        return ($now - $ts) < 900;
    });
    
    if (count($attempts[$ip]['failures']) >= MAX_LOGIN_ATTEMPTS) {
        $attempts[$ip]['blocked_until'] = $now + LOCKOUT_DURATION;
        logAudit('IP_BLOCKED', "IP: {$ip}, Duration: " . LOCKOUT_DURATION . "s");
    }
    
    persistAttempts($attempts);
    logAudit('FAILED_LOGIN_ATTEMPT', "Email: {$email}, IP: {$ip}");
}

// Clear failed attempts on successful login
function clearFailedAttempts($ip) {
    $attempts = loadAttempts();
    if (isset($attempts[$ip])) {
        unset($attempts[$ip]);
        persistAttempts($attempts);
        logAudit('LOGIN_SUCCESS', "IP: {$ip}");
    }
}

// Validate CSRF token for login
function validateLoginCsrf() {
    $headers = getallheaders();
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $headers['X-CSRF-Token'] ?? '';
    
    if (empty($token)) {
        return false;
    }
    
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        logAudit('LOGIN_CSRF_FAILED', "IP: " . getClientIP());
        return false;
    }
    
    return true;
}

// Regenerate session after successful login
function regenerateSessionAfterLogin($email) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    session_regenerate_id(true);
    
    $_SESSION['admin_logged_in'] = true;
    $_SESSION['admin_email'] = $email;
    $_SESSION['last_activity'] = time();
    $_SESSION['session_ip'] = getClientIP();
    $_SESSION['session_ua'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $_SESSION['_last_regen'] = time();
    
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ─────────────────────────────────────────────────────────────────────────────
// Main Request Handler
// ─────────────────────────────────────────────────────────────────────────────

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$clientIP = getClientIP();

// Get CSRF token
if ($action === 'get_csrf') {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    authJsonResponse(['csrf_token' => $_SESSION['csrf_token']]);
}

// Login
if ($action === 'login') {
    if (isLoginBlocked($clientIP)) {
        sendAuthError('Too many failed attempts. Please try again later.', 429);
    }
    
    if (!checkLoginRateLimit($clientIP)) {
        sendAuthError('Too many login attempts. Please try again later.', 429);
    }
    
    if (!validateLoginCsrf()) {
        sendAuthError('Invalid security token. Please refresh the page and try again.', 403);
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $email = isset($input['email']) ? trim(substr($input['email'], 0, 100)) : '';
    $password = isset($input['password']) ? $input['password'] : '';
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        trackFailedLogin($email, $clientIP);
        sendAuthError('Invalid email format', 401);
    }
    
    $admin = getJsonData('admin');
    $storedEmail = $admin['email'] ?? '';
    $storedPassword = $admin['password'] ?? '';
    
    if (empty($storedEmail) || empty($storedPassword)) {
        sendAuthError('Admin account not configured', 500);
    }
    
    if ($email !== $storedEmail || !password_verify($password, $storedPassword)) {
        trackFailedLogin($email, $clientIP);
        sendAuthError('Invalid email or password', 401);
    }
    
    clearFailedAttempts($clientIP);
    
    if (!empty($admin['totpEnabled']) && !empty($admin['totpSecret'])) {
        if (session_status() === PHP_SESSION_NONE) session_start();
        $_SESSION['pending_login'] = true;
        $_SESSION['pending_email'] = $email;
        $_SESSION['pending_ip'] = $clientIP;
        authJsonResponse(['requireTotp' => true]);
    }
    
    regenerateSessionAfterLogin($email);
    authJsonResponse(['success' => true, 'redirect' => '/admin/dashboard.php']);
}

// Verify TOTP
if ($action === 'verify_totp') {
    if (session_status() === PHP_SESSION_NONE) session_start();
    
    if (!isset($_SESSION['pending_login']) || !isset($_SESSION['pending_email'])) {
        sendAuthError('Session expired. Please login again.', 401);
    }
    
    if (isset($_SESSION['pending_ip']) && $_SESSION['pending_ip'] !== $clientIP) {
        unset($_SESSION['pending_login']);
        unset($_SESSION['pending_email']);
        unset($_SESSION['pending_ip']);
        sendAuthError('Session validation failed. Please login again.', 401);
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $code = isset($input['totp_code']) ? preg_replace('/[^0-9]/', '', $input['totp_code']) : '';
    $backupCode = isset($input['backup_code']) ? strtoupper(trim($input['backup_code'])) : '';
    
    $admin = getJsonData('admin');
    $valid = false;
    
    if (!empty($code) && !empty($admin['totpSecret'])) {
        require_once __DIR__ . '/../includes/totp.php';
        $totp = new TOTP($admin['totpSecret']);
        $valid = $totp->verifyCode($code, 2);
    }
    
    if (!$valid && !empty($backupCode) && isset($admin['backup_codes']) && is_array($admin['backup_codes'])) {
        $valid = verifyBackupCode($backupCode, $admin['backup_codes']);
        
        if ($valid && !empty($admin['backup_codes'])) {
            foreach ($admin['backup_codes'] as $index => $hashedCode) {
                if (password_verify($backupCode, $hashedCode)) {
                    unset($admin['backup_codes'][$index]);
                    $admin['backup_codes'] = array_values($admin['backup_codes']);
                    setJsonData('admin', $admin);
                    break;
                }
            }
        }
    }
    
    if (!$valid) {
        logAudit('TOTP_VERIFY_FAILED', "IP: {$clientIP}");
        sendAuthError('Invalid authentication code', 401);
    }
    
    $email = $_SESSION['pending_email'];
    
    unset($_SESSION['pending_login']);
    unset($_SESSION['pending_email']);
    unset($_SESSION['pending_ip']);
    
    regenerateSessionAfterLogin($email);
    logAudit('TOTP_VERIFY_SUCCESS', "IP: {$clientIP}");
    authJsonResponse(['success' => true, 'redirect' => '/admin/dashboard.php']);
}

// Logout
if ($action === 'logout') {
    if (session_status() === PHP_SESSION_NONE) session_start();
    
    $email = $_SESSION['admin_email'] ?? 'Unknown';
    logAudit('LOGOUT', "Email: {$email}, IP: {$clientIP}");
    
    $_SESSION = [];
    
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    session_destroy();
    authJsonResponse(['success' => true, 'redirect' => '/admin/login.php']);
}

// Check session
if ($action === 'check_session') {
    if (session_status() === PHP_SESSION_NONE) session_start();
    
    $isLoggedIn = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
    
    if ($isLoggedIn) {
        $currentIP = getClientIP();
        $sessionIP = $_SESSION['session_ip'] ?? '';
        $currentUA = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $sessionUA = $_SESSION['session_ua'] ?? '';
        
        if (!empty($sessionIP) && $currentIP !== $sessionIP) {
            $isLoggedIn = false;
            session_destroy();
        } elseif (!empty($sessionUA) && $currentUA !== $sessionUA) {
            $isLoggedIn = false;
            session_destroy();
        } elseif (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > 1800) {
            $isLoggedIn = false;
            session_destroy();
        } else {
            $_SESSION['last_activity'] = time();
        }
    }
    
    authJsonResponse(['loggedIn' => $isLoggedIn]);
}

// Change password
if ($action === 'change_password') {
    if (session_status() === PHP_SESSION_NONE) session_start();
    
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        sendAuthError('Unauthorized', 401);
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $currentPassword = $input['current_password'] ?? '';
    $newPassword = $input['new_password'] ?? '';
    $confirmPassword = $input['confirm_password'] ?? '';
    
    if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
        sendAuthError('All password fields are required', 400);
    }
    
    if ($newPassword !== $confirmPassword) {
        sendAuthError('New passwords do not match', 400);
    }
    
    if (strlen($newPassword) < 8) {
        sendAuthError('New password must be at least 8 characters', 400);
    }
    
    $admin = getJsonData('admin');
    $storedPassword = $admin['password'] ?? '';
    
    if (!password_verify($currentPassword, $storedPassword)) {
        logAudit('PASSWORD_CHANGE_FAILED', "IP: {$clientIP}");
        sendAuthError('Current password is incorrect', 401);
    }
    
    $admin['password'] = password_hash($newPassword, PASSWORD_DEFAULT);
    setJsonData('admin', $admin);
    
    logAudit('PASSWORD_CHANGED', "IP: {$clientIP}");
    authJsonResponse(['success' => true, 'message' => 'Password changed successfully']);
}

// Setup 2FA
if ($action === 'setup_2fa') {
    if (session_status() === PHP_SESSION_NONE) session_start();
    
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        sendAuthError('Unauthorized', 401);
    }
    
    require_once __DIR__ . '/../includes/totp.php';
    
    $admin = getJsonData('admin');
    
    if (empty($admin['totpSecret'])) {
        $totp = new TOTP();
        $secret = $totp->generateSecret();
        $admin['totpSecret'] = $secret;
        $admin['totpEnabled'] = false;
        setJsonData('admin', $admin);
    } else {
        $secret = $admin['totpSecret'];
        $totp = new TOTP($secret);
    }
    
    $qrCodeUrl = $totp->getQRCodeURL($admin['email'] ?? 'admin@furusato.com', 'Furusato Restaurant');
    
    authJsonResponse([
        'success' => true,
        'secret' => $secret,
        'qrCodeUrl' => $qrCodeUrl
    ]);
}

// Enable 2FA
if ($action === 'enable_2fa') {
    if (session_status() === PHP_SESSION_NONE) session_start();
    
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        sendAuthError('Unauthorized', 401);
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $code = isset($input['code']) ? preg_replace('/[^0-9]/', '', $input['code']) : '';
    
    if (empty($code)) {
        sendAuthError('Verification code is required', 400);
    }
    
    $admin = getJsonData('admin');
    
    if (empty($admin['totpSecret'])) {
        sendAuthError('TOTP not set up. Please run setup first.', 400);
    }
    
    require_once __DIR__ . '/../includes/totp.php';
    $totp = new TOTP($admin['totpSecret']);
    
    if (!$totp->verifyCode($code, 2)) {
        sendAuthError('Invalid verification code', 401);
    }
    
    $backupCodes = generateBackupCodes(10);
    $admin['backup_codes'] = hashBackupCodes($backupCodes);
    $admin['totpEnabled'] = true;
    setJsonData('admin', $admin);
    
    logAudit('TOTP_ENABLED', "IP: {$clientIP}");
    authJsonResponse([
        'success' => true,
        'backupCodes' => $backupCodes,
        'message' => '2FA enabled successfully. Save your backup codes.'
    ]);
}

// Disable 2FA
if ($action === 'disable_2fa') {
    if (session_status() === PHP_SESSION_NONE) session_start();
    
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        sendAuthError('Unauthorized', 401);
    }
    
    $admin = getJsonData('admin');
    $admin['totpEnabled'] = false;
    $admin['backup_codes'] = [];
    setJsonData('admin', $admin);
    
    logAudit('TOTP_DISABLED', "IP: {$clientIP}");
    authJsonResponse(['success' => true, 'message' => '2FA disabled successfully']);
}

sendAuthError('Invalid action', 400);
?>