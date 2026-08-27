<?php
/**
 * cache-control.php - Cache Control Utilities
 * 
 * SECURITY ENHANCEMENTS:
 * - No-cache headers for admin/API routes
 * - HSTS (HTTP Strict Transport Security) support
 * - Service worker cache control
 * - CDN/LiteSpeed cache purge helper
 * - Version-based asset URLs
 * - Security headers integration
 * - PHP 7.x/8.x compatible
 */

// ─────────────────────────────────────────────────────────────────────────────
// Constants
// ─────────────────────────────────────────────────────────────────────────────

const ONE_HOUR = 3600;
const ONE_DAY = 86400;
const ONE_WEEK = 604800;
const ONE_MONTH = 2592000;
const ONE_YEAR = 31536000;

// ─────────────────────────────────────────────────────────────────────────────
// Security: No-Cache Headers (For Admin and API)
// ─────────────────────────────────────────────────────────────────────────────

if (!function_exists('setNoCacheHeaders')) {

/**
 * Set aggressive no-cache headers for dynamic/secure pages
 * Use on: admin panel, API endpoints, reservation pages
 */
function setNoCacheHeaders(): void {
    // HTTP/1.1
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0, post-check=0, pre-check=0', true);
    
    // HTTP/1.0 legacy
    header('Pragma: no-cache', true);
    
    // Force expiration in the past
    header('Expires: Sat, 26 Jul 1997 05:00:00 GMT', true);
    
    // Remove any ETag or Last-Modified that might cause conditional requests
    header_remove('ETag');
    header_remove('Last-Modified');
    
    // Vary on important headers
    header('Vary: Accept-Encoding, Authorization, X-Requested-With', true);
}

}

// ─────────────────────────────────────────────────────────────────────────────
// Security: HSTS (HTTP Strict Transport Security)
// ─────────────────────────────────────────────────────────────────────────────

if (!function_exists('setHstsHeaders')) {

/**
 * Set HSTS (HTTP Strict Transport Security) headers
 * Forces browsers to use HTTPS for all future requests
 * 
 * @param int $maxAge Maximum age in seconds (default: 1 year)
 * @param bool $includeSubdomains Include all subdomains
 * @param bool $preload Opt-in for HSTS preload list
 */
function setHstsHeaders(int $maxAge = ONE_YEAR, bool $includeSubdomains = true, bool $preload = false): void {
    $hsts = "max-age={$maxAge}";
    if ($includeSubdomains) {
        $hsts .= "; includeSubDomains";
    }
    if ($preload) {
        $hsts .= "; preload";
    }
    header("Strict-Transport-Security: {$hsts}", true);
}

}

// ─────────────────────────────────────────────────────────────────────────────
// Security: Content Security Policy Helper
// ─────────────────────────────────────────────────────────────────────────────

if (!function_exists('setCspHeaders')) {

/**
 * Set Content Security Policy headers for different page types
 * 
 * @param string $type Page type: 'public', 'admin', 'api'
 */
function setCspHeaders(string $type = 'public'): void {
    switch ($type) {
        case 'admin':
            $csp = "default-src 'self'; "
                 . "script-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://fonts.googleapis.com; "
                 . "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; "
                 . "img-src 'self' data: blob: https:; "
                 . "font-src 'self' data: https://fonts.gstatic.com; "
                 . "connect-src 'self'; "
                 . "frame-src 'none'; "
                 . "base-uri 'self'; "
                 . "form-action 'self';";
            break;
            
        case 'api':
            $csp = "default-src 'none'; "
                 . "script-src 'none'; "
                 . "style-src 'none'; "
                 . "img-src 'none'; "
                 . "font-src 'none'; "
                 . "connect-src 'self';";
            break;
            
        default: // public
            $csp = "default-src 'self' 'unsafe-inline' 'unsafe-eval' data: blob:; "
                 . "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdnjs.cloudflare.com https://fonts.googleapis.com https://maps.googleapis.com https://www.google.com; "
                 . "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; "
                 . "img-src 'self' https://images.unsplash.com data: blob: https: http:; "
                 . "font-src 'self' data: https://fonts.gstatic.com; "
                 . "connect-src 'self' https://images.unsplash.com https://fonts.googleapis.com https://maps.googleapis.com; "
                 . "frame-src 'self' https://www.google.com https://maps.google.com; "
                 . "base-uri 'self'; "
                 . "form-action 'self';";
            break;
    }
    
    header("Content-Security-Policy: {$csp}", true);
}

}

// ─────────────────────────────────────────────────────────────────────────────
// Cache Headers for Different Asset Types
// ─────────────────────────────────────────────────────────────────────────────

if (!function_exists('setMenuImageCacheHeaders')) {

/**
 * Set short cache for menu images (5 minutes) so new uploads appear quickly
 */
function setMenuImageCacheHeaders(): void {
    header('Cache-Control: public, max-age=300, must-revalidate', true);
    header('Vary: Accept-Encoding, Accept', true);
}

}

if (!function_exists('setCacheableHeaders')) {

/**
 * Set cache headers for static assets (CSS, JS)
 * 
 * @param int $maxAge Cache duration in seconds
 */
function setCacheableHeaders(int $maxAge = ONE_WEEK): void {
    header('Cache-Control: public, max-age=' . $maxAge . ', must-revalidate', true);
    header('Vary: Accept-Encoding', true);
    header_remove('Pragma');
}

}

if (!function_exists('setLongTermCacheHeaders')) {

/**
 * Set long-term cache for truly static assets (logos, brand images)
 * Uses 'immutable' directive for optimal caching
 */
function setLongTermCacheHeaders(): void {
    header('Cache-Control: public, max-age=' . ONE_YEAR . ', immutable', true);
    header('Vary: Accept-Encoding', true);
    header_remove('Pragma');
    header_remove('Expires');
}

}

if (!function_exists('setPrivateCacheHeaders')) {

/**
 * Set private cache for user-specific content
 */
function setPrivateCacheHeaders(int $maxAge = ONE_HOUR): void {
    header('Cache-Control: private, max-age=' . $maxAge, true);
    header('Vary: Accept-Encoding, Cookie', true);
}

}

// ─────────────────────────────────────────────────────────────────────────────
// Combined Security Headers (All-in-One)
// ─────────────────────────────────────────────────────────────────────────────

if (!function_exists('setSecureHeaders')) {

/**
 * Set all security headers at once
 * 
 * @param string $pageType Page type: 'public', 'admin', 'api'
 */
function setSecureHeaders(string $pageType = 'public'): void {
    // Basic security headers
    header('X-Frame-Options: DENY', true);
    header('X-Content-Type-Options: nosniff', true);
    header('X-XSS-Protection: 1; mode=block', true);
    header('Referrer-Policy: strict-origin-when-cross-origin', true);
    header('Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=()', true);
    
    // HSTS (only on HTTPS)
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        setHstsHeaders();
    }
    
    // CSP based on page type
    setCspHeaders($pageType);
    
    // No-cache for admin/api, cache for public
    if ($pageType === 'admin' || $pageType === 'api') {
        setNoCacheHeaders();
    }
}

}

// ─────────────────────────────────────────────────────────────────────────────
// Cache Busting Utilities
// ─────────────────────────────────────────────────────────────────────────────

if (!function_exists('logCacheHeaders')) {

/**
 * Log cache headers for debugging (development only)
 * 
 * @param string $context Route/context where headers are applied
 */
function logCacheHeaders(string $context): void {
    $debug = getenv('DEBUG_CACHE') === '1' || (isset($_COOKIE['debug_cache']) && $_COOKIE['debug_cache'] === '1');
    if (!$debug) return;
    
    $headers = headers_list();
    $cacheHeaders = array_filter($headers, function($h) {
        return stripos($h, 'Cache-Control') !== false || 
               stripos($h, 'Pragma') !== false || 
               stripos($h, 'Expires') !== false;
    });
    
    $msg = sprintf(
        "[%s] Cache headers for %s: %s",
        date('Y-m-d H:i:s'),
        $context,
        implode(' | ', $cacheHeaders)
    );
    error_log($msg);
}

}

if (!function_exists('getCacheBustingVersion')) {

/**
 * Get cache-busting version string for assets
 * 
 * @param string $filePath Path to asset file
 * @param bool $useDevelopmentMode Force development mode (timestamp-based)
 * @return int|string Version identifier
 */
function getCacheBustingVersion(string $filePath = '', bool $useDevelopmentMode = false) {
    $isDev = $useDevelopmentMode || getenv('APP_ENV') === 'development';
    
    if ($isDev) {
        return time();
    }
    
    if (empty($filePath)) {
        return time();
    }
    
    // Handle relative paths
    if (!file_exists($filePath)) {
        $filePath = $_SERVER['DOCUMENT_ROOT'] . '/' . ltrim($filePath, '/');
    }
    
    if (!file_exists($filePath)) {
        return time();
    }
    
    return (string)filemtime($filePath);
}

}

if (!function_exists('versionedAsset')) {

/**
 * Generate versioned asset URL with cache-busting
 * 
 * @param string $path Asset path (e.g., '/assets/css/style.css')
 * @return string Versioned URL
 */
function versionedAsset(string $path): string {
    $version = getCacheBustingVersion($path);
    return $path . '?v=' . $version;
}

}

if (!function_exists('getApiCacheBustParam')) {

/**
 * Generate cache-busting parameter for API calls
 * Uses microsecond precision for unique requests
 * 
 * @return string Query parameter string
 */
function getApiCacheBustParam(): string {
    $timestamp = round(microtime(true) * 1000);
    return '_=' . $timestamp;
}

}

if (!function_exists('getMenuImageCacheBust')) {

/**
 * Get cache-busting parameter for menu images
 * Ensures newly uploaded images appear immediately
 * 
 * @param string $imagePath Path to the image
 * @return string URL with cache-busting parameter
 */
function getMenuImageCacheBust(string $imagePath): string {
    if (empty($imagePath)) {
        return '';
    }
    
    // Remove existing cache busting
    $cleanPath = strtok($imagePath, '?');
    
    $fullPath = $_SERVER['DOCUMENT_ROOT'] . $cleanPath;
    if (file_exists($fullPath)) {
        $version = filemtime($fullPath);
    } else {
        $version = time();
    }
    
    $separator = strpos($cleanPath, '?') === false ? '?' : '&';
    return $cleanPath . $separator . 'v=' . $version;
}

}

if (!function_exists('getCacheControlMetaTags')) {

/**
 * Get HTML meta tags for cache control
 * Useful for adding to HTML head sections
 * 
 * @return string HTML meta tags
 */
function getCacheControlMetaTags(): string {
    return '
    <meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate, max-age=0">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    ';
}

}

// ─────────────────────────────────────────────────────────────────────────────
// CDN / LiteSpeed Cache Purge
// ─────────────────────────────────────────────────────────────────────────────

if (!function_exists('purgeCache')) {

/**
 * Purge cache for a specific URL (LiteSpeed/CDN)
 * 
 * @param string $url URL to purge (default: current page)
 * @return bool True on success
 */
function purgeCache(string $url = ''): bool {
    if (empty($url)) {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
        $url = $protocol . ($_SERVER['HTTP_HOST'] ?? 'furusatorestaurant.com') . $_SERVER['REQUEST_URI'];
    }
    
    // LiteSpeed Cache purge
    if (function_exists('header') && !headers_sent()) {
        header('X-LiteSpeed-Purge: ' . $url);
        header('X-LiteSpeed-Purge: *');
        return true;
    }
    
    // Alternative: cURL request to purge
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PURGE');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return $httpCode === 200;
}

}

// ─────────────────────────────────────────────────────────────────────────────
// Service Worker Cache Control
// ─────────────────────────────────────────────────────────────────────────────

if (!function_exists('setServiceWorkerCacheHeaders')) {

/**
 * Set cache headers specifically for service worker files
 * Service workers need short cache to update quickly
 */
function setServiceWorkerCacheHeaders(): void {
    header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0', true);
    header('Service-Worker-Allowed: /', true);
}

}

// ─────────────────────────────────────────────────────────────────────────────
// Preload Helpers
// ─────────────────────────────────────────────────────────────────────────────

if (!function_exists('addPreloadHeaders')) {

/**
 * Add preload headers for critical assets
 * 
 * @param string $assetUrl Asset URL to preload
 * @param string $as Type of asset ('style', 'script', 'image', 'font')
 * @param string $crossorigin Cross-origin attribute ('', 'anonymous', 'use-credentials')
 */
function addPreloadHeaders(string $assetUrl, string $as, string $crossorigin = ''): void {
    $link = "<{$assetUrl}>; rel=preload; as={$as}";
    if (!empty($crossorigin)) {
        $link .= "; crossorigin={$crossorigin}";
    }
    header("Link: {$link}", false);
}

}

// ─────────────────────────────────────────────────────────────────────────────
// Conditional Cache Bypass (for admin users)
// ─────────────────────────────────────────────────────────────────────────────

if (!function_exists('shouldBypassCache')) {

/**
 * Check if current user should bypass cache (admin logged in)
 * 
 * @return bool True if cache should be bypassed
 */
function shouldBypassCache(): bool {
    if (session_status() === PHP_SESSION_NONE) {
        return false;
    }
    
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

}

if (!function_exists('setConditionalCacheHeaders')) {

/**
 * Set conditional cache headers based on user role
 * Bypass cache for admin users to see fresh content
 */
function setConditionalCacheHeaders(): void {
    if (shouldBypassCache()) {
        setNoCacheHeaders();
    } else {
        setCacheableHeaders();
    }
}

}
?>