<?php
/**
 * includes/config.php - Central configuration loader for Furusato Restaurant.
 *
 * SECRET MANAGEMENT:
 *  - No secrets are hardcoded here. Secrets are resolved, in order, from:
 *      1. PHP environment variables (set in Hostinger account or via <IfModule mod_env>) e.g. WHATSAPP_API_KEY
 *      2. A production-only file at includes/.env.php  (GITIGNORED, NEVER committed)
 *      3. Safe defaults defined below (never a real secret)
 *
 *  - includes/.env.php is created ONCE on the server and is EXCLUDED from the
 *    deployment pipeline, so a code deploy can never overwrite your secrets.
 *
 * Compatibility: PHP 7.x / 8.x, Hostinger LiteSpeed & PHP-FPM (uses getenv()).
 */

if (function_exists('furusato_config')) {
    return; // already loaded
}

/**
 * Read an individual environment variable (empty => treated as unset).
 */
function furusato_env($key, $default = null)
{
    $val = getenv($key);
    if ($val === false || $val === '') {
        return $default;
    }
    return $val;
}

/**
 * Load and cache the merged configuration map.
 *
 * @param bool $reload Force re-read (useful in tests).
 * @return array
 */
function furusato_merged_config($reload = false)
{
    static $cfg = null;
    if ($cfg !== null && !$reload) {
        return $cfg;
    }

    // Keys we understand.
    $keys = [
        'APP_ENV', 'APP_URL', 'CORS_ALLOWED_ORIGINS',
        'WHATSAPP_API_KEY', 'WHATSAPP_PHONE',
        'SMTP_HOST', 'SMTP_PORT', 'SMTP_USER', 'SMTP_PASS', 'SMTP_FROM', 'SMTP_FROM_NAME',
        'FURUSATO_SECRET_KEY', 'SESSION_HANDLER',
    ];

    $cfg = [];
    foreach ($keys as $k) {
        $cfg[$k] = furusato_env($k);
    }

    // Optional production-only, gitignored, excluded-from-deploy config.
    $envFile = __DIR__ . '/.env.php';
    if (is_file($envFile)) {
        $loaded = require $envFile;
        if (is_array($loaded)) {
            foreach ($cfg as $k => $_) {
                if (isset($loaded[$k]) && $loaded[$k] !== '') {
                    $cfg[$k] = $loaded[$k];
                }
            }
            // Accept aliases with/without prefix applied by the env file author.
            foreach (['WHATSAPP_API_KEY', 'WHATSAPP_PHONE', 'SMTP_HOST', 'SMTP_PORT', 'SMTP_USER', 'SMTP_PASS', 'SMTP_FROM', 'APP_ENV', 'APP_URL'] as $k) {
                if (isset($loaded[$k]) && $loaded[$k] !== '') {
                    $cfg[$k] = $loaded[$k];
                }
            }
        }
    }

    return $cfg;
}

/**
 * Fetch a single configuration value.
 */
function furusato_config($key, $default = null)
{
    $cfg = furusato_merged_config();
    $val = $cfg[$key] ?? null;
    return ($val === null || $val === '') ? $default : $val;
}

/**
 * Fetch the WhatsApp CallMeBot / api key (compromised legacy key is never used).
 * Returns the value from environment/config only. Empty when not configured —
 * callers must handle sending being unavailable gracefully.
 */
function furusato_whatsapp_api_key(): string
{
    return (string) furusato_config('WHATSAPP_API_KEY', '');
}

/**
 * Fetch the WhatsApp phone number (digits only, international) from config,
 * falling back to the admin-managed production settings (phone is not secret).
 * Reads data/settings.json directly so config.php has no load-order dependency.
 */
function furusato_whatsapp_phone(): string
{
    $phone = (string) furusato_config('WHATSAPP_PHONE', '');
    if ($phone === '') {
        $settingsFile = dirname(__DIR__) . '/data/settings.json';
        if (is_file($settingsFile)) {
            $decoded = json_decode((string) file_get_contents($settingsFile), true);
            if (is_array($decoded)) {
                $wa = $decoded['whatsapp'] ?? null;
                if (is_array($wa)) {
                    $phone = (string) ($wa['phone_number'] ?? '');
                } elseif (is_string($wa)) {
                    $phone = $wa; // legacy flat format
                } else {
                    $phone = (string) ($decoded['whatsapp_number'] ?? '');
                }
            }
        }
    }
    return preg_replace('/[^0-9]/', '', $phone);
}

/**
 * The canonical public origin (no trailing slash).
 */
function furusato_app_url(): string
{
    return rtrim((string) furusato_config('APP_URL', 'https://furusatorestaurant.com'), '/');
}

/**
 * Emit safe CORS headers.
 *  - Never emits Access-Control-Allow-Origin: * together with credentials.
 *  - Reflects ONLY origins that are explicitly allowed (default: the site origin).
 *  - Handles preflight OPTIONS.
 */
function furusato_cors_headers(): void
{
    $allowedRaw = (string) furusato_config('CORS_ALLOWED_ORIGINS', 'https://furusatorestaurant.com');
    $allowed    = [];
    foreach (preg_split('/[\s,]+/', $allowedRaw) as $o) {
        $o = rtrim(trim($o), '/');
        if ($o !== '') {
            $allowed[] = $o;
        }
    }
    // Always permit same-origin APP_URL requests too.
    $appUrl = rtrim(furusato_app_url(), '/');
    if (!in_array($appUrl, $allowed, true)) {
        $allowed[] = $appUrl;
    }

    $origin   = '';
    $hasInput = !empty($_SERVER['HTTP_ORIGIN']);
    if ($hasInput) {
        $origin = rtrim(trim($_SERVER['HTTP_ORIGIN']), '/');
    }

    header('Vary: Origin, Access-Control-Request-Headers, Access-Control-Request-Method');

    if ($hasInput && in_array($origin, $allowed, true)) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Access-Control-Allow-Credentials: true');
    } elseif ($hasInput) {
        // Disallowed origin: don't fetch, but don't leak a wildcard.
        header('Access-Control-Allow-Origin: ' . $appUrl);
    }

    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token, X-Requested-With, Authorization');
    header('Access-Control-Max-Age: 86400');

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}

/**
 * Dev helper: true when APP_ENV is development.
 */
function furusato_is_dev(): bool
{
    return strtolower((string) furusato_config('APP_ENV', 'production')) === 'development';
}