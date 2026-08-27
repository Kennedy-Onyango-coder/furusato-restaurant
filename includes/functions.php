<?php
/**
 * Core Architecture Utility Engine - ULTRA SECURED VERSION
 * Furusato Restaurant Admin Dashboard
 * 
 * SECURITY ENHANCEMENTS v3.0:
 * - PHP 7.x/8.x compatibility (fixed str_starts_with)
 * - File upload validation with size/type limits
 * - Path traversal prevention in all file operations
 * - Session fixation protection
 * - HSTS headers
 * - JSON file permission hardening
 * - Input validation enhancements
 * - Rate limiting with IP whitelisting option
 */

// Hostinger shared hosting: ensure session files write to a reliable path
if (session_status() === PHP_SESSION_NONE) {
    $sp = ini_get('session.save_path');
    if (empty($sp) || !is_writable($sp)) {
        session_save_path(sys_get_temp_dir());
    }
}

// Central configuration & secret loader (reads env + optional includes/.env.php).
require_once __DIR__ . '/config.php';

// ─────────────────────────────────────────────────────────────────────────────
// Constants
// ─────────────────────────────────────────────────────────────────────────────

const JSON_FILES = [
    'menu', 'reservations', 'settings', 'specials', 'hero',
    'admin', 'attempts', 'rate_limits', 'audit', 'images',
];

const SESSION_TIMEOUT = 1800;           // 30 minutes
const SESSION_TIMEOUT_ADMIN = 1800;      // 30 minutes for admin

const BF_MAX_ATTEMPTS = 5;               // failed logins before lockout
const BF_WINDOW       = 900;             // sliding-window: 15 minutes
const BF_LOCKOUT      = 900;             // lockout duration: 15 minutes
const BF_EXPONENTIAL_BACKOFF = true;     // Increase lockout time after repeated attempts

const RL_MAX_PER_HOUR = 60;              // max requests/IP/type/hour
const RL_WINDOW       = 3600;            // 1 hour

const MAX_UPLOAD_SIZE = 5242880;         // 5MB max file upload
const ALLOWED_IMAGE_TYPES = [
    'image/jpeg', 'image/png', 'image/webp', 'image/gif'
];

const TRUSTED_PROXIES = [
    '127.0.0.1',
    '::1',
];

// ─────────────────────────────────────────────────────────────────────────────
// PHP 7.x/8.x Compatibility Helpers
// ─────────────────────────────────────────────────────────────────────────────

if (!function_exists('str_starts_with')) {
    function str_starts_with($haystack, $needle) {
        return (string)$needle !== '' && strncmp($haystack, $needle, strlen($needle)) === 0;
    }
}

if (!function_exists('str_contains')) {
    function str_contains($haystack, $needle) {
        return $needle !== '' && strpos($haystack, $needle) !== false;
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// SECURITY: Session Hardening with IP and User-Agent Binding
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Start a secure admin session with IP and User-Agent binding
 * 
 * @param bool $requireLogin Whether to enforce login check
 * @return bool True if session is valid, false otherwise
 */
function startSecureSession(bool $requireLogin = true): bool
{
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (int)($_SERVER['SERVER_PORT'] ?? 80) === 443
        || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])
            && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https');

    // Set secure session parameters
    ini_set('session.cookie_samesite', 'Strict');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.gc_maxlifetime', (string)SESSION_TIMEOUT);
    ini_set('session.cookie_secure', $isHttps ? '1' : '0');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');

    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => SESSION_TIMEOUT,
            'path'     => '/',
            'domain'   => '',
            'secure'   => $isHttps,
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        session_start();
    }

    // Ensure CSRF token exists
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    if (!$requireLogin) {
        return true;
    }

    // Check if logged in
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        return false;
    }

    // SECURITY: Validate IP address hasn't changed
    $currentIP = getClientIP();
    $sessionIP = $_SESSION['session_ip'] ?? '';
    if (!empty($sessionIP) && $currentIP !== $sessionIP) {
        destroySession();
        logAudit('SESSION_IP_MISMATCH', "Expected: {$sessionIP}, Got: {$currentIP}");
        return false;
    }

    // SECURITY: Validate User-Agent hasn't changed
    $currentUA = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $sessionUA = $_SESSION['session_ua'] ?? '';
    if (!empty($sessionUA) && $currentUA !== $sessionUA) {
        destroySession();
        logAudit('SESSION_UA_MISMATCH', "Expected: {$sessionUA}, Got: {$currentUA}");
        return false;
    }

    // Check session timeout
    if (time() - ($_SESSION['last_activity'] ?? 0) > SESSION_TIMEOUT) {
        destroySession();
        return false;
    }

    $_SESSION['last_activity'] = time();

    // Regenerate session ID periodically (every 5 minutes)
    if (!isset($_SESSION['_last_regen']) || time() - $_SESSION['_last_regen'] > 300) {
        session_regenerate_id(true);
        $_SESSION['_last_regen'] = time();
    }

    return true;
}

/**
 * Regenerate session ID on login to prevent fixation
 */
function regenerateSessionOnLogin(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    session_regenerate_id(true);
    $_SESSION['session_ip'] = getClientIP();
    $_SESSION['session_ua'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $_SESSION['_last_regen'] = time();
}

/**
 * Legacy function for backward compatibility
 */
function startAdminSession(bool $requireLogin = true): void
{
    if (!startSecureSession($requireLogin) && $requireLogin) {
        header('Location: /admin/login.php');
        exit;
    }
}

/**
 * Destroy session completely
 */
function destroySession(): void
{
    $_SESSION = [];
    
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    session_destroy();
}

// ─────────────────────────────────────────────────────────────────────────────
// SECURITY: CSRF Token Rotation
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Generate a new CSRF token and rotate the old one
 * 
 * @param bool $rotate If true, invalidate old token
 * @return string New CSRF token
 */
function generateCsrfToken(bool $rotate = true): string
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if ($rotate) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    } elseif (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token with one-time use capability
 * 
 * @param bool $consume Whether to consume the token after verification
 * @return bool True if valid
 */
function verifyCsrf(bool $consume = true): bool
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    
    if (empty($token) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        logAudit('CSRF_VERIFY_FAILED', "Token provided: " . substr($token, 0, 20));
        return false;
    }
    
    if ($consume) {
        // Rotate token after use to prevent replay attacks
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    
    return true;
}

/**
 * Verify CSRF and return JSON error if invalid
 */
function verifyCsrfOrFail(): void
{
    if (!verifyCsrf()) {
        jsonResponse(['error' => 'Invalid or expired CSRF token'], 403);
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// SECURITY: Enhanced Brute Force Protection
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Load login attempts from JSON file
 */
function loadAttempts(): array
{
    $data = getJsonData('attempts');
    return is_array($data) ? $data : [];
}

/**
 * Persist login attempts to JSON file
 */
function persistAttempts(array $attempts): void
{
    setJsonData('attempts', $attempts);
}

/**
 * Record failed login attempt with exponential backoff
 */
function recordFailedLogin(): void
{
    $attempts = loadAttempts();
    $ip = getClientIP();
    $now = time();
    
    // Get existing attempts
    $ipAttempts = $attempts[$ip] ?? [
        'failures' => [],
        'blocked_until' => 0,
        'attempt_count' => 0,
        'first_attempt' => $now
    ];
    
    // Add new failure
    $ipAttempts['failures'][] = $now;
    $ipAttempts['attempt_count'] = count($ipAttempts['failures']);
    
    // Exponential backoff: longer lockout for repeated offenses
    if (BF_EXPONENTIAL_BACKOFF && $ipAttempts['attempt_count'] >= BF_MAX_ATTEMPTS) {
        $offenseCount = floor($ipAttempts['attempt_count'] / BF_MAX_ATTEMPTS);
        $lockoutDuration = BF_LOCKOUT * min($offenseCount, 10);
        $ipAttempts['blocked_until'] = $now + $lockoutDuration;
    }
    
    $attempts[$ip] = $ipAttempts;
    persistAttempts($attempts);
    logAudit('FAILED_LOGIN', "IP: {$ip}, Attempt count: {$ipAttempts['attempt_count']}");
}

/**
 * Clear failed logins for current IP
 */
function clearFailedLogins(): void
{
    $attempts = loadAttempts();
    $ip = getClientIP();
    if (isset($attempts[$ip])) {
        unset($attempts[$ip]);
        persistAttempts($attempts);
        logAudit('LOGIN_SUCCESS', "IP: {$ip} - Attempts cleared");
    }
}

/**
 * Check if IP is blocked due to brute force attempts
 * 
 * @return string|false Error message if blocked, false otherwise
 */
function checkBruteForce(): string|false
{
    $attempts = loadAttempts();
    $ip = getClientIP();
    $now = time();

    if (isset($attempts[$ip])) {
        $attempt = $attempts[$ip];

        // Check hard lockout
        if (!empty($attempt['blocked_until']) && $attempt['blocked_until'] > $now) {
            $remaining = (int)ceil(($attempt['blocked_until'] - $now) / 60);
            $minutes = $remaining === 1 ? 'minute' : 'minutes';
            return "Too many failed login attempts. Try again in {$remaining} {$minutes}.";
        }

        // Count failures inside the sliding window
        $recent = array_filter(
            $attempt['failures'] ?? [],
            fn($ts) => ($now - $ts) < BF_WINDOW
        );

        if (count($recent) >= BF_MAX_ATTEMPTS) {
            $lockoutDuration = BF_EXPONENTIAL_BACKOFF && isset($attempt['attempt_count']) 
                ? BF_LOCKOUT * min(floor($attempt['attempt_count'] / BF_MAX_ATTEMPTS), 10)
                : BF_LOCKOUT;
            
            $attempts[$ip]['blocked_until'] = $now + $lockoutDuration;
            $attempts[$ip]['failures'] = array_values($recent);
            persistAttempts($attempts);
            
            $remaining = (int)ceil($lockoutDuration / 60);
            $minutes = $remaining === 1 ? 'minute' : 'minutes';
            logAudit('IP_BLOCKED', "IP: {$ip}, Duration: {$lockoutDuration}s");
            return "Too many failed login attempts. Try again in {$remaining} {$minutes}.";
        }
    }
    
    return false;
}

// ─────────────────────────────────────────────────────────────────────────────
// SECURITY: Security Headers Helper
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Set comprehensive security headers
 */
function setSecurityHeaders(): void
{
    // Prevent clickjacking
    header('X-Frame-Options: DENY');
    
    // Enable XSS protection
    header('X-XSS-Protection: 1; mode=block');
    
    // Prevent MIME type sniffing
    header('X-Content-Type-Options: nosniff');
    
    // Referrer policy
    header('Referrer-Policy: strict-origin-when-cross-origin');
    
    // Permissions policy
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()');
    
    // HSTS (HTTP Strict Transport Security) - 1 year
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
}

// ─────────────────────────────────────────────────────────────────────────────
// FILE UPLOAD SECURITY
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Validate uploaded file for security
 * 
 * @param array $file $_FILES array element
 * @return array ['valid' => bool, 'error' => string, 'mime' => string]
 */
function validateUploadedFile(array $file): array
{
    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds server size limit',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds form size limit',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'File upload stopped by extension'
        ];
        return ['valid' => false, 'error' => $errors[$file['error']] ?? 'Unknown upload error', 'mime' => ''];
    }
    
    // Check file size
    if ($file['size'] > MAX_UPLOAD_SIZE) {
        return ['valid' => false, 'error' => 'File too large. Maximum size is ' . (MAX_UPLOAD_SIZE / 1048576) . 'MB', 'mime' => ''];
    }
    
    // Get real MIME type (not just from browser)
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    // Validate MIME type
    if (!in_array($mimeType, ALLOWED_IMAGE_TYPES, true)) {
        return ['valid' => false, 'error' => 'Invalid file type. Only JPEG, PNG, WebP, and GIF are allowed.', 'mime' => $mimeType];
    }
    
    // Check for double extensions (e.g., .jpg.php)
    $safeName = basename($file['name']);
    if (preg_match('/\.(php|phtml|php3|php4|php5|phps|exe|sh|bat|cmd)\.[a-z]+$/i', $safeName)) {
        return ['valid' => false, 'error' => 'Suspicious file name detected', 'mime' => $mimeType];
    }
    
    return ['valid' => true, 'error' => '', 'mime' => $mimeType];
}

// ─────────────────────────────────────────────────────────────────────────────
// 2FA / TOTP Helpers
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Get the admin's TOTP secret
 */
function getAdminSecret(): string
{
    $admin = getJsonData('admin');
    return $admin['totpSecret'] ?? $admin['totp_secret'] ?? '';
}

/**
 * Generate backup codes for 2FA recovery
 * 
 * @param int $count Number of backup codes to generate
 * @return array Array of backup codes
 */
function generateBackupCodes(int $count = 10): array
{
    $codes = [];
    for ($i = 0; $i < $count; $i++) {
        $codes[] = strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
    }
    return $codes;
}

/**
 * Generate backup codes for user (alias for compatibility)
 */
function generateBackupCodesForUser(int $count = 10): array
{
    return generateBackupCodes($count);
}

/**
 * Hash backup codes for storage
 * 
 * @param array $codes Plain text backup codes
 * @return array Hashed backup codes
 */
function hashBackupCodes(array $codes): array
{
    return array_map(function($code) {
        return password_hash($code, PASSWORD_BCRYPT);
    }, $codes);
}

/**
 * Hash backup codes array (alias for compatibility)
 */
function hashBackupCodesArray(array $codes): array
{
    return hashBackupCodes($codes);
}

/**
 * Verify a backup code
 * 
 * @param string $code User-provided backup code
 * @param array $hashedCodes Stored hashed backup codes
 * @return bool True if valid
 */
function verifyBackupCode(string $code, array $hashedCodes): bool
{
    foreach ($hashedCodes as $hashed) {
        if (password_verify(strtoupper(trim($code)), $hashed)) {
            return true;
        }
    }
    return false;
}

/**
 * Verify backup code input (alias for compatibility)
 */
function verifyBackupCodeInput(string $code, array $hashedCodes): bool
{
    return verifyBackupCode($code, $hashedCodes);
}

/**
 * Check authentication rate limit
 */
function checkAuthRateLimit(string $type): bool
{
    return checkRateLimit($type, 10);
}

// ─────────────────────────────────────────────────────────────────────────────
// SECURITY: Enhanced Rate Limiting
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Check rate limit with sliding window
 * 
 * @param string $type Rate limit type (e.g., 'login', 'api', 'reservation')
 * @param int $maxPerHour Maximum requests per hour
 * @return bool True if within limit
 */
function checkRateLimit(string $type, int $maxPerHour = RL_MAX_PER_HOUR): bool
{
    $data = readJsonFile(__DIR__ . '/../data/rate_limits.json') ?: [];
    $now = time();
    $ip = getClientIP();
    $key = md5($ip . '_' . $type);

    // Clean old entries
    if (isset($data[$key])) {
        $data[$key] = array_filter(
            $data[$key] ?? [],
            fn($ts) => ($now - $ts) < RL_WINDOW
        );
    } else {
        $data[$key] = [];
    }

    if (count($data[$key]) >= $maxPerHour) {
        logAudit('RATE_LIMIT_EXCEEDED', "type={$type}, ip={$ip}");
        return false;
    }

    $data[$key][] = $now;
    $data[$key] = array_slice($data[$key], -$maxPerHour);
    
    setJsonData('rate_limits', $data);
    return true;
}

// ─────────────────────────────────────────────────────────────────────────────
// WHATSAPP CONFIGURATION (single source of truth)
// The WhatsApp number is managed via Admin → Settings → WhatsApp and stored
// in data/settings.json. Never hardcode the number in pages or scripts.
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Get the restaurant's WhatsApp number (digits only, international format).
 * Supports both legacy flat setting ("whatsapp": "2547...") and the nested
 * admin structure ("whatsapp": {"phone_number": "+2547..."}).
 */
function get_whatsapp_number(): string
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    $settings = readJsonFile(__DIR__ . '/../data/settings.json') ?: [];
    $number   = '';

    if (!empty($settings['whatsapp'])) {
        $number = is_array($settings['whatsapp'])
            ? ($settings['whatsapp']['phone_number'] ?? '')
            : (string) $settings['whatsapp'];
    }
    if ($number === '' && !empty($settings['whatsapp_number'])) {
        $number = (string) $settings['whatsapp_number'];
    }

    $number = preg_replace('/[^0-9]/', '', $number);
    $cached = ($number !== '') ? $number : '254734639203'; // safe fallback
    return $cached;
}

/**
 * Build a wa.me link with a pre-filled message.
 */
function wa_link(string $message): string
{
    return 'https://wa.me/' . get_whatsapp_number() . '?text=' . rawurlencode($message);
}

/**
 * Pre-filled WhatsApp enquiry message for a single menu item.
 * WhatsApp is an ENQUIRY channel — not an ordering/checkout channel.
 */
function menu_enquiry_message(string $itemName, $price = null): string
{
    $msg  = "Hello Furusato Japanese Restaurant,\n\n";
    $msg .= "I would like to enquire about the following menu item:\n\n";
    $msg .= $itemName;
    if ($price !== null && $price !== '' && is_numeric($price)) {
        $msg .= "\nPrice shown on menu: KES " . number_format((float) $price);
    }
    $msg .= "\n\nIs this currently available?\n\nThank you.";
    return $msg;
}

/**
 * Pre-filled WhatsApp enquiry message for multiple menu items (My Enquiry).
 */
function menu_enquiry_message_multi(array $itemNames): string
{
    $msg  = "Hello Furusato Japanese Restaurant,\n\n";
    $msg .= "I would like to enquire about the following menu items:\n\n";
    foreach ($itemNames as $name) {
        $msg .= '- ' . $name . "\n";
    }
    $msg .= "\nCould you please confirm availability and provide any relevant information?\n\nThank you.";
    return $msg;
}

// ─────────────────────────────────────────────────────────────────────────────
// CACHE BUSTING HELPER
// ─────────────────────────────────────────────────────────────────────────────

function base_url(): string
{
    static $base = null;
    if ($base !== null) {
        return $base;
    }

    // Resolve the app's base path so links work whether the site is hosted at
    // the domain root (-> '') or inside a sub-folder (-> '/furusato' etc.).
    $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
    $dir    = str_replace('\\', '/', dirname($script));
    $base   = ($dir === '/' || $dir === '.' || $dir === '') ? '' : rtrim($dir, '/');
    return $base;
}

function get_asset_version($path) {
    $fullPath = $_SERVER['DOCUMENT_ROOT'] . '/' . ltrim($path, '/');
    if (file_exists($fullPath)) {
        return filemtime($fullPath);
    }
    return time();
}

function asset_url($path) {
    return base_url() . '/' . ltrim($path, '/');
}

function addImageCacheBust($imagePath) {
    if (empty($imagePath)) return '';
    if (preg_match('/^https?:\/\//i', $imagePath)) return $imagePath;
    
    $imagePath = strtok($imagePath, '?');
    $fullPath = $_SERVER['DOCUMENT_ROOT'] . $imagePath;
    $version = file_exists($fullPath) ? filemtime($fullPath) : time();
    
    return $imagePath . '?v=' . $version;
}

// ─────────────────────────────────────────────────────────────────────────────
// Low-level I/O with Path Traversal Protection
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Validate a path is within allowed directory
 * 
 * @param string $path Path to validate
 * @param string $allowedDir Allowed base directory
 * @return bool
 */
function isPathSafe(string $path, string $allowedDir): bool
{
    $realPath = realpath($path);
    $realAllowed = realpath($allowedDir);
    
    if ($realPath === false || $realAllowed === false) {
        return false;
    }
    
    // PHP 7.x compatible check
    return strpos($realPath, $realAllowed) === 0;
}

function readJsonFile(string $path): ?array
{
    if (!is_file($path) || !is_readable($path)) {
        return null;
    }
    
    $realPath = realpath($path);
    $dataDir = realpath(__DIR__ . '/../data/');
    
    // PHP 7.x compatible check (using strpos instead of str_starts_with)
    if (!$realPath || !$dataDir || strpos($realPath, $dataDir) !== 0) {
        logAudit('PATH_TRAVERSAL_ATTEMPT', "Path: {$path}");
        return null;
    }
    
    $fp = @fopen($path, 'rb');
    if ($fp === false) return null;
    
    if (!flock($fp, LOCK_SH)) {
        fclose($fp);
        return null;
    }
    
    $raw = stream_get_contents($fp);
    flock($fp, LOCK_UN);
    fclose($fp);

    $decoded = json_decode($raw ?: 'null', true);
    return is_array($decoded) ? $decoded : null;
}

function writeJsonFile(string $path, array $data): void
{
    $dir = dirname($path);
    $realDir = realpath($dir);
    $dataDir = realpath(__DIR__ . '/../data/');
    
    // Path traversal protection
    if (!$realDir || !$dataDir || strpos($realDir, $dataDir) !== 0) {
        logAudit('PATH_TRAVERSAL_ATTEMPT', "Path: {$path}");
        throw new RuntimeException('Invalid file path');
    }
    
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        throw new RuntimeException('JSON encode error: ' . json_last_error_msg());
    }

    $tmp = $dir . '/.tmp_' . bin2hex(random_bytes(8)) . '.json';

    $bytes = @file_put_contents($tmp, $json . "\n", LOCK_EX);
    if ($bytes === false) {
        throw new RuntimeException('Failed to write temporary file');
    }

    @chmod($tmp, 0640);
    
    if (!@rename($tmp, $path)) {
        @unlink($tmp);
        throw new RuntimeException('Failed to rename temporary file');
    }
    
    // Set secure permissions on the written file
    @chmod($path, 0640);
}

// ─────────────────────────────────────────────────────────────────────────────
// JSON store façade
// ─────────────────────────────────────────────────────────────────────────────

function getJsonData(string $file)
{
    $allowed = JSON_FILES;
    $file = basename($file);
    if (!in_array($file, $allowed, true)) {
        logAudit('FORBIDDEN_FILE_ACCESS', "File: {$file}");
        jsonResponse(['error' => 'Forbidden file access'], 403);
    }
    $path = __DIR__ . '/../data/' . $file . '.json';
    $real = realpath($path);
    $dataDir = realpath(__DIR__ . '/../data/');
    
    // PHP 7.x compatible check
    if (!$real || !$dataDir || strpos($real, $dataDir) !== 0) {
        jsonResponse(['error' => 'Forbidden file path'], 403);
    }
    $data = readJsonFile($real);
    return $data ?: [];
}

function setJsonData(string $file, $data): void
{
    $allowed = JSON_FILES;
    $file = basename($file);
    if (!in_array($file, $allowed, true)) {
        logAudit('FORBIDDEN_FILE_WRITE', "File: {$file}");
        jsonResponse(['error' => 'Forbidden file access'], 403);
    }
    $path = __DIR__ . '/../data/' . $file . '.json';
    try {
        writeJsonFile($path, $data);
    } catch (RuntimeException $e) {
        logAudit('FILE_WRITE_ERROR', $e->getMessage());
        jsonResponse(['error' => 'File write error: ' . $e->getMessage()], 500);
    }
}

function jsonResponse($data, int $code = 200): void
{
    ob_clean();
    http_response_code($code);
    header('Content-Type: application/json');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// IP retrieval with proxy support
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Get client IP address with proxy support
 * 
 * @return string
 */
function getClientIP(): string
{
    $remote = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    // Check for proxies only if remote is trusted
    if (in_array($remote, TRUSTED_PROXIES, true) && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $ip = trim($ips[0]);
        // Validate IP format
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            return $ip;
        }
    }
    
    // Also check CloudFlare header
    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        $ip = $_SERVER['HTTP_CF_CONNECTING_IP'];
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            return $ip;
        }
    }

    return $remote;
}

// ─────────────────────────────────────────────────────────────────────────────
// Audit logger
// ─────────────────────────────────────────────────────────────────────────────

function logAudit(string $event, string $detail = ''): void
{
    $ts = date('Y-m-d H:i:s');
    $ip = getClientIP();
    $safeDet = $detail !== '' ? sanitize($detail) : '';
    $userAgent = sanitize(substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 200));
    $requestUri = sanitize(substr($_SERVER['REQUEST_URI'] ?? '', 0, 500));

    $logPath = __DIR__ . '/../data/audit.log';
    $line = sprintf("[%s] %s ip=%s ua=%s uri=%s", $ts, $event, $ip, $userAgent, $requestUri);
    if ($safeDet !== '') {
        $line .= ' detail=' . $safeDet;
    }
    @file_put_contents($logPath, $line . "\n", FILE_APPEND | LOCK_EX);

    $jsonPath = __DIR__ . '/../data/audit.json';
    $audit = readJsonFile($jsonPath);
    $audit = is_array($audit) && isset($audit['logs']) ? $audit : ['logs' => []];
    $audit['logs'][] = [
        'timestamp' => $ts,
        'event' => $event,
        'ip' => $ip,
        'user_agent' => $userAgent,
        'uri' => $requestUri,
        'detail' => $safeDet,
    ];
    if (count($audit['logs']) > 10000) {
        $audit['logs'] = array_slice($audit['logs'], -10000);
    }
    writeJsonFile($jsonPath, $audit);
}

// ─────────────────────────────────────────────────────────────────────────────
// Input helpers with enhanced validation
// ─────────────────────────────────────────────────────────────────────────────

function sanitize($input): array|string
{
    if (is_array($input)) {
        return array_map(fn($v) => sanitize($v), $input);
    }
    // Convert null to empty string
    if ($input === null) {
        return '';
    }
    return htmlspecialchars(strip_tags(trim((string)$input)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function validatePrice($val): bool
{
    return is_numeric($val) && (float)$val >= 0 && (float)$val <= 999999;
}

function validateEmail(string $email): bool
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate phone number - International format support
 * Examples: +254722488706, 0722488706, 254722488706, +1-555-123-4567
 * 
 * @param string $phone Phone number to validate
 * @return bool True if valid
 */
function validatePhone(string $phone): bool
{
    // Remove spaces, hyphens, parentheses, dots
    $cleaned = preg_replace('/[\s\-\(\)\.]/', '', $phone);
    
    // Must start with + or digit
    if (!preg_match('/^[\+0-9]/', $cleaned)) {
        return false;
    }
    
    // Extract only digits for length check
    $digitsOnly = preg_replace('/[^0-9]/', '', $cleaned);
    $digitCount = strlen($digitsOnly);
    
    // International standard: 8-15 digits
    if ($digitCount < 8 || $digitCount > 15) {
        return false;
    }
    
    return true;
}

function validateDate(string $date): bool
{
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1 && strtotime($date) !== false;
}

function validateTime(string $time): bool
{
    if (!preg_match('/^\d{2}:\d{2}$/', $time)) return false;
    [$h, $m] = array_map('intval', explode(':', $time));
    return $h >= 12 && $h <= 22 && $m >= 0 && $m < 60 && ($h < 22 || $m === 0);
}

/**
 * Validate UUID format
 * 
 * @param string $uuid UUID to validate
 * @return bool
 */
function validateUuid(string $uuid): bool
{
    return preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/i', $uuid) === 1;
}

/**
 * Generate a secure random token
 * 
 * @param int $length Length of token in bytes (output will be 2x length in hex)
 * @return string
 */
function generateSecureToken(int $length = 32): string
{
    return bin2hex(random_bytes($length));
}

// ─────────────────────────────────────────────────────────────────────────────
// Menu item helpers
// ─────────────────────────────────────────────────────────────────────────────

function countMenuItems(array $menu): int
{
    $count = 0;
    foreach ($menu['categories'] as $cat) {
        $count += count($cat['items'] ?? []);
        foreach ($cat['subcategories'] ?? [] as $sub) {
            $count += count($sub['items'] ?? []);
        }
    }
    return $count;
}

function countCategories(array $menu): int
{
    return count($menu['categories'] ?? []);
}

function findItemById(array $menu, string $id): ?array
{
    foreach ($menu['categories'] as $cat) {
        foreach ($cat['items'] ?? [] as $item) {
            if ($item['id'] === $id) return $item;
        }
        foreach ($cat['subcategories'] ?? [] as $sub) {
            foreach ($sub['items'] ?? [] as $item) {
                if ($item['id'] === $id) return $item;
            }
        }
    }
    return null;
}

function updateItemInMenu(array &$menu, string $id, array $updates): bool
{
    $allowedFields = ['name', 'price', 'description', 'image', 'badge', 'visible', 'order'];
    
    foreach ($menu['categories'] as &$cat) {
        foreach ($cat['items'] ?? [] as &$item) {
            if ($item['id'] === $id) {
                foreach ($updates as $k => $v) {
                    if (in_array($k, $allowedFields, true)) {
                        $item[$k] = $v;
                    }
                }
                return true;
            }
        }
        unset($item);
        foreach ($cat['subcategories'] ?? [] as &$sub) {
            foreach ($sub['items'] ?? [] as &$item) {
                if ($item['id'] === $id) {
                    foreach ($updates as $k => $v) {
                        if (in_array($k, $allowedFields, true)) {
                            $item[$k] = $v;
                        }
                    }
                    return true;
                }
            }
            unset($item);
        }
        unset($sub);
    }
    unset($cat);
    return false;
}

function deleteItemFromMenu(array &$menu, string $id): bool
{
    foreach ($menu['categories'] as &$cat) {
        foreach ($cat['items'] ?? [] as $k => $item) {
            if ($item['id'] === $id) {
                array_splice($cat['items'], $k, 1);
                return true;
            }
        }
        foreach ($cat['subcategories'] ?? [] as &$sub) {
            foreach ($sub['items'] ?? [] as $k => $item) {
                if ($item['id'] === $id) {
                    array_splice($sub['items'], $k, 1);
                    return true;
                }
            }
        }
    }
    unset($cat);
    return false;
}

function getImageUrl($path) {
    if (empty($path)) {
        return '/assets/images/menu/placeholder.webp';
    }
    
    if (preg_match('/^https?:\/\//i', $path)) {
        return $path;
    }
    
    $cleanPath = ltrim($path, '/');
    $fullPath = $_SERVER['DOCUMENT_ROOT'] . '/' . $cleanPath;
    
    if (file_exists($fullPath)) {
        $version = filemtime($fullPath);
    } else {
        $version = time();
    }
    
    return '/' . $cleanPath . '?v=' . $version;
}

// ─────────────────────────────────────────────────────────────────────────────
// Additional Security Helpers
// ─────────────────────────────────────────────────────────────────────────────

function sanitizeFilename(string $filename): string
{
    // Remove path traversal attempts
    $filename = basename($filename);
    // Remove anything not alphanumeric, dot, dash, underscore
    $filename = preg_replace('/[^a-zA-Z0-9_.-]/', '', $filename);
    // Prevent double extensions that could hide malicious files
    if (preg_match('/\.(php|phtml|php3|php4|php5|phps|exe|sh|bat|cmd)\./i', $filename)) {
        return '';
    }
    // Limit length
    return substr($filename, 0, 100);
}

function isAjaxRequest(): bool
{
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

/**
 * Set no-cache headers for admin panel
 */
function setNoCacheHeaders(): void
{
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Cache-Control: post-check=0, pre-check=0', false);
    header('Pragma: no-cache');
    header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
}

/**
 * Check if request is from a trusted IP (optional whitelist)
 * 
 * @param array $whitelist Array of allowed IPs/CIDR ranges
 * @return bool
 */
function isTrustedIp(array $whitelist = []): bool
{
    if (empty($whitelist)) {
        return true; // No whitelist means all IPs allowed
    }
    
    $ip = getClientIP();
    foreach ($whitelist as $allowed) {
        // Check exact match
        if ($ip === $allowed) {
            return true;
        }
        // Check CIDR range (simple implementation)
        if (strpos($allowed, '/') !== false) {
            list($net, $mask) = explode('/', $allowed);
            $ipLong = ip2long($ip);
            $netLong = ip2long($net);
            $maskLong = ~((1 << (32 - $mask)) - 1);
            if (($ipLong & $maskLong) === ($netLong & $maskLong)) {
                return true;
            }
        }
    }
    return false;
}
?>