<?php
/**
 * includes/config.php
 * Central configuration loader for Furusato Japanese Restaurant.
 *
 * SECRET MANAGEMENT
 * -----------------
 * Secrets are resolved in this order:
 *
 * 1. PHP environment variables
 * 2. Production-only includes/.env.php
 *    - Gitignored
 *    - Never committed to GitHub
 *    - Excluded from deployment
 * 3. Safe non-secret defaults
 *
 * IMPORTANT:
 * - Never put real API keys, SMTP passwords, or other secrets in this file.
 * - includes/.env.php must exist only on the production server.
 * - This file is safe to commit to GitHub.
 *
 * Compatibility:
 * - PHP 7.x / 8.x
 * - Hostinger LiteSpeed
 * - PHP-FPM
 */

if (function_exists('furusato_config')) {
    return;
}

/**
 * Read an environment variable.
 *
 * Empty strings and unavailable variables are treated as unset.
 *
 * @param string $key
 * @param mixed $default
 * @return mixed
 */
function furusato_env($key, $default = null)
{
    $value = getenv($key);

    if ($value === false || $value === '') {
        return $default;
    }

    return $value;
}

/**
 * Load and cache the merged Furusato configuration.
 *
 * Environment variables are loaded first.
 * The optional production-only .env.php file can override them.
 *
 * @param bool $reload Force configuration reload.
 * @return array
 */
function furusato_merged_config($reload = false)
{
    static $config = null;

    if ($config !== null && !$reload) {
        return $config;
    }

    /**
     * Only configuration keys explicitly listed here are accepted.
     *
     * This prevents arbitrary values from a .env.php file from
     * becoming application configuration.
     */
    $keys = [
        'APP_ENV',
        'APP_URL',
        'CORS_ALLOWED_ORIGINS',

        'WHATSAPP_API_KEY',
        'WHATSAPP_PHONE',

        'SMTP_HOST',
        'SMTP_PORT',
        'SMTP_USER',
        'SMTP_PASS',
        'SMTP_FROM',
        'SMTP_FROM_NAME',

        'FURUSATO_SECRET_KEY',
        'SESSION_HANDLER',
    ];

    $config = [];

    /**
     * Step 1:
     * Read supported values from the server environment.
     */
    foreach ($keys as $key) {
        $config[$key] = furusato_env($key);
    }

    /**
     * Step 2:
     * Load the optional production-only configuration file.
     *
     * This file must return an associative array.
     *
     * Example:
     *
     * return [
     *     'APP_ENV' => 'production',
     *     'APP_URL' => 'https://furusatorestaurant.com',
     *     'WHATSAPP_API_KEY' => '...',
     * ];
     */
    $envFile = __DIR__ . '/.env.php';

    if (is_file($envFile) && is_readable($envFile)) {
        $loaded = require $envFile;

        if (is_array($loaded)) {
            foreach ($keys as $key) {
                if (
                    array_key_exists($key, $loaded) &&
                    $loaded[$key] !== null &&
                    $loaded[$key] !== ''
                ) {
                    $config[$key] = $loaded[$key];
                }
            }
        }
    }

    return $config;
}

/**
 * Fetch a single configuration value.
 *
 * @param string $key
 * @param mixed $default
 * @return mixed
 */
function furusato_config($key, $default = null)
{
    $config = furusato_merged_config();

    if (!array_key_exists($key, $config)) {
        return $default;
    }

    $value = $config[$key];

    return ($value === null || $value === '')
        ? $default
        : $value;
}

/**
 * Get the WhatsApp API key.
 *
 * IMPORTANT:
 * This function never contains a production key.
 *
 * @return string
 */
function furusato_whatsapp_api_key(): string
{
    return (string) furusato_config('WHATSAPP_API_KEY', '');
}

/**
 * Get the restaurant WhatsApp phone number.
 *
 * Priority:
 *
 * 1. WHATSAPP_PHONE environment/.env.php
 * 2. data/settings.json
 *
 * The phone number is not considered a secret and may be managed
 * from the admin Settings → WhatsApp section.
 *
 * Supports:
 *
 * Legacy:
 * "whatsapp": "254712345678"
 *
 * Current:
 * "whatsapp": {
 *     "phone_number": "+254712345678"
 * }
 *
 * @return string Digits-only international phone number.
 */
function furusato_whatsapp_phone(): string
{
    $phone = (string) furusato_config('WHATSAPP_PHONE', '');

    if ($phone === '') {
        $settingsFile = dirname(__DIR__) . '/data/settings.json';

        if (is_file($settingsFile) && is_readable($settingsFile)) {
            $contents = @file_get_contents($settingsFile);

            if ($contents !== false) {
                $settings = json_decode($contents, true);

                if (is_array($settings)) {
                    $whatsapp = isset($settings['whatsapp'])
                        ? $settings['whatsapp']
                        : null;

                    if (is_array($whatsapp)) {
                        $phone = isset($whatsapp['phone_number'])
                            ? (string) $whatsapp['phone_number']
                            : '';
                    } elseif (is_string($whatsapp)) {
                        $phone = $whatsapp;
                    }

                    /**
                     * Legacy alternative:
                     * "whatsapp_number": "254712345678"
                     */
                    if ($phone === '' && isset($settings['whatsapp_number'])) {
                        $phone = (string) $settings['whatsapp_number'];
                    }
                }
            }
        }
    }

    return preg_replace('/[^0-9]/', '', $phone);
}

/**
 * Get the canonical public application URL.
 *
 * @return string
 */
function furusato_app_url(): string
{
    return rtrim(
        (string) furusato_config(
            'APP_URL',
            'https://furusatorestaurant.com'
        ),
        '/'
    );
}

/**
 * Emit secure CORS headers.
 *
 * Security rules:
 *
 * - Never use wildcard (*) with credentials.
 * - Only explicitly permitted origins are allowed.
 * - The canonical APP_URL is always allowed.
 * - Handles OPTIONS preflight requests.
 *
 * @return void
 */
function furusato_cors_headers(): void
{
    $allowedRaw = (string) furusato_config(
        'CORS_ALLOWED_ORIGINS',
        'https://furusatorestaurant.com'
    );

    $allowed = [];

    /**
     * Accept either comma-separated or whitespace-separated origins.
     */
    $origins = preg_split('/[\s,]+/', $allowedRaw);

    if (is_array($origins)) {
        foreach ($origins as $origin) {
            $origin = rtrim(trim($origin), '/');

            if ($origin !== '') {
                $allowed[] = $origin;
            }
        }
    }

    /**
     * Always permit requests originating from the canonical site.
     */
    $appUrl = rtrim(furusato_app_url(), '/');

    if (!in_array($appUrl, $allowed, true)) {
        $allowed[] = $appUrl;
    }

    $hasOrigin = !empty($_SERVER['HTTP_ORIGIN']);
    $origin = '';

    if ($hasOrigin) {
        $origin = rtrim(
            trim((string) $_SERVER['HTTP_ORIGIN']),
            '/'
        );
    }

    /**
     * CORS responses vary by Origin and preflight headers.
     */
    header(
        'Vary: Origin, Access-Control-Request-Headers, Access-Control-Request-Method'
    );

    /**
     * Reflect only explicitly allowed origins.
     */
    if ($hasOrigin && in_array($origin, $allowed, true)) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Access-Control-Allow-Credentials: true');
    } elseif ($hasOrigin) {
        /**
         * Do not return wildcard access.
         *
         * Returning the canonical application origin does not grant
         * the requesting origin access because browsers enforce CORS.
         */
        header('Access-Control-Allow-Origin: ' . $appUrl);
    }

    header(
        'Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS'
    );

    header(
        'Access-Control-Allow-Headers: Content-Type, X-CSRF-Token, X-Requested-With, Authorization'
    );

    header('Access-Control-Max-Age: 86400');

    /**
     * Handle CORS preflight.
     */
    if (
        isset($_SERVER['REQUEST_METHOD']) &&
        $_SERVER['REQUEST_METHOD'] === 'OPTIONS'
    ) {
        http_response_code(204);
        exit;
    }
}

/**
 * Determine whether the application is running in development mode.
 *
 * @return bool
 */
function furusato_is_dev(): bool
{
    return strtolower(
        (string) furusato_config('APP_ENV', 'production')
    ) === 'development';
}
