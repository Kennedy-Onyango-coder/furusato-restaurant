<?php
/**
 * image-processor.php - Secure Image Processing Utilities
 * 
 * SECURITY ENHANCEMENTS:
 * - File size limits (5MB max)
 * - MIME type validation via magic bytes
 * - Cryptographically secure filenames (random_bytes)
 * - Dimension limits to prevent DoS (max 2000x2000)
 * - Secure directory permissions (0750)
 * - Memory limit checks
 * - Path traversal prevention
 * - Basic malware pattern detection
 */

// ─────────────────────────────────────────────────────────────────────────────
// Constants
// ─────────────────────────────────────────────────────────────────────────────

const MAX_IMAGE_SIZE = 5242880;        // 5MB max upload
const MAX_IMAGE_WIDTH = 2000;          // Max width in pixels
const MAX_IMAGE_HEIGHT = 2000;         // Max height in pixels
const ALLOWED_MIME_TYPES = [
    'image/jpeg', 'image/png', 'image/webp', 'image/gif'
];

// Directory permissions (secure)
const DIR_PERMISSIONS = 0750;           // Owner: rwx, Group: r-x, Others: ---
const FILE_PERMISSIONS = 0640;          // Owner: rw-, Group: r--, Others: ---

// ─────────────────────────────────────────────────────────────────────────────
// Security: Validate image magic bytes (not just extension)
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Validate image file using magic bytes (more secure than getimagesize alone)
 * 
 * @param string $filePath Path to uploaded file
 * @return string|false MIME type if valid, false otherwise
 */
function validateImageMagicBytes(string $filePath)
{
    if (!file_exists($filePath) || !is_readable($filePath)) {
        return false;
    }
    
    $handle = fopen($filePath, 'rb');
    if (!$handle) return false;
    
    $bytes = fread($handle, 12);
    fclose($handle);
    
    // JPEG: FF D8 FF
    if (substr($bytes, 0, 3) === "\xFF\xD8\xFF") {
        return 'image/jpeg';
    }
    
    // PNG: 89 50 4E 47
    if (substr($bytes, 0, 4) === "\x89PNG") {
        return 'image/png';
    }
    
    // GIF: 47 49 46
    if (substr($bytes, 0, 3) === "GIF") {
        return 'image/gif';
    }
    
    // WebP: RIFF ... WEBP
    if (substr($bytes, 0, 4) === "RIFF" && substr($bytes, 8, 4) === "WEBP") {
        return 'image/webp';
    }
    
    return false;
}

/**
 * Check for malicious patterns in image (basic malware detection)
 * 
 * @param string $filePath Path to uploaded file
 * @return bool True if suspicious patterns found
 */
function hasMaliciousContent(string $filePath): bool
{
    if (!file_exists($filePath) || !is_readable($filePath)) {
        return true; // Assume malicious if can't read
    }
    
    $content = file_get_contents($filePath);
    if ($content === false) return true;
    
    // Check for PHP tags (malicious code injection)
    $suspiciousPatterns = [
        '<?php', '<%', '<script language="php">',
        'eval(', 'base64_decode(', 'system(', 'exec(', 'passthru(',
        'shell_exec(', 'popen(', 'proc_open(', '`', // backtick execution
        '$_GET', '$_POST', '$_REQUEST', '$_COOKIE', '$_SESSION',
        'file_get_contents', 'fopen', 'fwrite', 'file_put_contents'
    ];
    
    $lowerContent = strtolower($content);
    foreach ($suspiciousPatterns as $pattern) {
        if (strpos($lowerContent, strtolower($pattern)) !== false) {
            return true;
        }
    }
    
    return false;
}

// ─────────────────────────────────────────────────────────────────────────────
// Main conversion function with enhanced security
// ─────────────────────────────────────────────────────────────────────────────

if (!function_exists('convertToWebP')) {

/**
 * Convert uploaded image to WebP with security hardening
 * 
 * @param string $tmpPath Temporary uploaded file path
 * @param string $itemName Name of item for filename
 * @param string $folderType Subfolder: 'menu', 'hero', 'gallery'
 * @param int $quality WebP quality (0-100, default 85)
 * @return array ['success' => bool, 'path' => string, 'error' => string]
 */
function convertToWebP($tmpPath, $itemName, $folderType = 'menu', $quality = 85) {
    // ============================================================
    // VALIDATION LAYER 1: File existence and size
    // ============================================================
    if (!file_exists($tmpPath) || !is_readable($tmpPath)) {
        return ['success' => false, 'error' => 'Temporary file not readable'];
    }
    
    // Check file size (prevent DoS)
    $fileSize = filesize($tmpPath);
    if ($fileSize === false || $fileSize > MAX_IMAGE_SIZE) {
        return ['success' => false, 'error' => 'File too large. Maximum size is ' . (MAX_IMAGE_SIZE / 1048576) . 'MB'];
    }
    
    // ============================================================
    // VALIDATION LAYER 2: Magic bytes (prevent fake extensions)
    // ============================================================
    $detectedMime = validateImageMagicBytes($tmpPath);
    if (!$detectedMime) {
        return ['success' => false, 'error' => 'Invalid image file: unrecognized format'];
    }
    
    if (!in_array($detectedMime, ALLOWED_MIME_TYPES, true)) {
        return ['success' => false, 'error' => 'Unsupported image type. Use JPEG, PNG, WebP, or GIF.'];
    }
    
    // ============================================================
    // VALIDATION LAYER 3: Malware detection
    // ============================================================
    if (hasMaliciousContent($tmpPath)) {
        return ['success' => false, 'error' => 'Security check failed: suspicious content detected'];
    }
    
    // ============================================================
    // VALIDATION LAYER 4: Image dimensions and memory
    // ============================================================
    $info = getimagesize($tmpPath);
    if (!$info) {
        return ['success' => false, 'error' => 'Failed to read image dimensions'];
    }
    
    $width = $info[0];
    $height = $info[1];
    $mime = $info['mime'] ?? $detectedMime;
    
    // Check dimension limits (prevent DoS from huge images)
    if ($width > MAX_IMAGE_WIDTH || $height > MAX_IMAGE_HEIGHT) {
        return ['success' => false, 'error' => 'Image dimensions too large. Max ' . MAX_IMAGE_WIDTH . 'x' . MAX_IMAGE_HEIGHT];
    }
    
    // Estimate memory needed (width * height * 4 bytes) + overhead
    $estimatedMemory = ($width * $height * 4) + (1024 * 1024);
    $memoryLimit = ini_get('memory_limit');
    $memoryLimitBytes = convertToBytes($memoryLimit);
    if ($estimatedMemory > $memoryLimitBytes && $memoryLimitBytes > 0) {
        return ['success' => false, 'error' => 'Image is too large to process. Please use a smaller image.'];
    }
    
    // ============================================================
    // Create image resource based on MIME type
    // ============================================================
    $image = null;
    switch ($mime) {
        case 'image/jpeg':
        case 'image/jpg':
            $image = imagecreatefromjpeg($tmpPath);
            break;
        case 'image/png':
            $image = imagecreatefrompng($tmpPath);
            if ($image) {
                imagepalettetotruecolor($image);
                imagealphablending($image, true);
                imagesavealpha($image, true);
            }
            break;
        case 'image/webp':
            $image = imagecreatefromwebp($tmpPath);
            break;
        case 'image/gif':
            $image = imagecreatefromgif($tmpPath);
            break;
        default:
            return ['success' => false, 'error' => "Unsupported MIME type: {$mime}"];
    }

    if (!$image) {
        return ['success' => false, 'error' => 'Failed to create image resource. File may be corrupted.'];
    }

    // ============================================================
    // Resize if dimensions exceed maximum (optimization)
    // ============================================================
    $currentWidth = imagesx($image);
    $currentHeight = imagesy($image);
    $newWidth = $currentWidth;
    $newHeight = $currentHeight;
    
    if ($currentWidth > MAX_IMAGE_WIDTH || $currentHeight > MAX_IMAGE_HEIGHT) {
        $ratio = min(MAX_IMAGE_WIDTH / $currentWidth, MAX_IMAGE_HEIGHT / $currentHeight);
        $newWidth = (int)($currentWidth * $ratio);
        $newHeight = (int)($currentHeight * $ratio);
        
        $resizedImage = imagecreatetruecolor($newWidth, $newHeight);
        
        // Preserve transparency for PNG/WebP
        if ($mime === 'image/png' || $mime === 'image/webp') {
            imagealphablending($resizedImage, false);
            imagesavealpha($resizedImage, true);
            $transparent = imagecolorallocatealpha($resizedImage, 255, 255, 255, 127);
            imagefilledrectangle($resizedImage, 0, 0, $newWidth, $newHeight, $transparent);
        }
        
        imagecopyresampled($resizedImage, $image, 0, 0, 0, 0, $newWidth, $newHeight, $currentWidth, $currentHeight);
        imagedestroy($image);
        $image = $resizedImage;
    }

    // ============================================================
    // Generate secure filename (cryptographically random)
    // ============================================================
    $cleanName = strtolower(trim($itemName));
    $cleanName = preg_replace('/[\s_]+/', '-', $cleanName);
    $cleanName = preg_replace('/[^a-z0-9-]/', '', $cleanName);
    $cleanName = trim($cleanName, '-');
    if ($cleanName === '') {
        $cleanName = 'asset';
    }
    $cleanName = substr($cleanName, 0, 30); // Limit length
    
    // Use random_bytes instead of uniqid() for security
    $randomId = bin2hex(random_bytes(8)); // 16 characters of entropy
    $filename = $cleanName . '_' . $randomId . '.webp';

    // ============================================================
    // Directory creation with secure permissions
    // ============================================================
    $storageBase = __DIR__ . '/../assets/images';
    $targetFolder = $storageBase . '/' . $folderType;

    if (!is_dir($targetFolder)) {
        if (!@mkdir($targetFolder, DIR_PERMISSIONS, true)) {
            imagedestroy($image);
            return ['success' => false, 'error' => 'Failed to create image directory'];
        }
        // Ensure parent directories have correct permissions
        @chmod($targetFolder, DIR_PERMISSIONS);
    }

    // Verify directory is writable
    if (!is_writable($targetFolder)) {
        imagedestroy($image);
        return ['success' => false, 'error' => 'Image directory is not writable'];
    }

    // ============================================================
    // Save WebP file
    // ============================================================
    $destinationPath = $targetFolder . '/' . $filename;

    // Validate quality parameter
    $quality = max(1, min(100, (int)$quality));
    
    if (!imagewebp($image, $destinationPath, $quality)) {
        imagedestroy($image);
        return ['success' => false, 'error' => 'Failed to save WebP file'];
    }

    imagedestroy($image);
    
    // Verify file was created
    if (!file_exists($destinationPath)) {
        return ['success' => false, 'error' => 'WebP file was not created'];
    }
    
    // Set secure file permissions
    @chmod($destinationPath, FILE_PERMISSIONS);
    
    $relativePath = '/assets/images/' . $folderType . '/' . $filename;
    $finalSize = filesize($destinationPath);

    return [
        'success'    => true,
        'path'       => $relativePath,
        'filename'   => $filename,
        'size'       => $finalSize,
        'width'      => $newWidth,
        'height'     => $newHeight
    ];
}

}

// ─────────────────────────────────────────────────────────────────────────────
// Helper: Convert memory limit string to bytes
// ─────────────────────────────────────────────────────────────────────────────

function convertToBytes($memoryLimit) {
    if ($memoryLimit === '-1') return PHP_INT_MAX;
    
    $last = strtolower(substr($memoryLimit, -1));
    $value = (int)$memoryLimit;
    
    switch ($last) {
        case 'g': $value *= 1024;
        case 'm': $value *= 1024;
        case 'k': $value *= 1024;
    }
    
    return $value;
}

// ─────────────────────────────────────────────────────────────────────────────
// Delete image with path traversal protection
// ─────────────────────────────────────────────────────────────────────────────

if (!function_exists('deleteLocalImage')) {

function deleteLocalImage($relativePath) {
    if (empty($relativePath)) {
        return ['success' => false, 'error' => 'No image path provided'];
    }
    
    // Remove cache busting parameters
    $relativePath = strtok($relativePath, '?');
    
    // Path traversal prevention
    if (strpos($relativePath, '..') !== false) {
        return ['success' => false, 'error' => 'Invalid image path: traversal detected'];
    }
    
    // Only allow deleting from assets/images
    if (!preg_match('#^/assets/images/(menu|hero|gallery)/#', $relativePath)) {
        return ['success' => false, 'error' => 'Invalid image path: not in allowed directory'];
    }
    
    $fullPath = realpath(__DIR__ . '/..' . $relativePath);
    $allowedBase = realpath(__DIR__ . '/../assets/images');
    
    if (!$fullPath || !$allowedBase || strpos($fullPath, $allowedBase) !== 0) {
        return ['success' => false, 'error' => 'Invalid image path: outside allowed directory'];
    }
    
    if (file_exists($fullPath)) {
        if (!@unlink($fullPath)) {
            return ['success' => false, 'error' => 'Failed to delete image file (permission denied)'];
        }
    }
    
    return ['success' => true];
}

}

// ─────────────────────────────────────────────────────────────────────────────
// Get image info with validation
// ─────────────────────────────────────────────────────────────────────────────

if (!function_exists('getImageInfo')) {

function getImageInfo($imagePath) {
    if (empty($imagePath)) {
        return null;
    }
    
    // Remove cache busting
    $imagePath = strtok($imagePath, '?');
    
    // Security: only allow paths within assets/images
    if (strpos($imagePath, '..') !== false) {
        return null;
    }
    
    $fullPath = realpath(__DIR__ . '/..' . $imagePath);
    $allowedBase = realpath(__DIR__ . '/../assets/images');
    
    if (!$fullPath || !$allowedBase || strpos($fullPath, $allowedBase) !== 0) {
        return null;
    }
    
    if (!file_exists($fullPath)) {
        return null;
    }
    
    $info = getimagesize($fullPath);
    if (!$info) {
        return null;
    }
    
    return [
        'width' => $info[0],
        'height' => $info[1],
        'mime' => $info['mime'],
        'size' => filesize($fullPath)
    ];
}

}

// ─────────────────────────────────────────────────────────────────────────────
// Ensure placeholder image exists (secure)
// ─────────────────────────────────────────────────────────────────────────────

if (!function_exists('ensurePlaceholderImage')) {

function ensurePlaceholderImage() {
    $placeholderPath = __DIR__ . '/../assets/images/menu/placeholder.webp';
    
    if (file_exists($placeholderPath)) {
        // Verify existing placeholder is not malicious
        if (hasMaliciousContent($placeholderPath)) {
            @unlink($placeholderPath);
        } else {
            return true;
        }
    }
    
    $dir = dirname($placeholderPath);
    if (!is_dir($dir)) {
        if (!@mkdir($dir, DIR_PERMISSIONS, true)) {
            return false;
        }
        @chmod($dir, DIR_PERMISSIONS);
    }
    
    // Create a simple placeholder image
    $width = 400;
    $height = 300;
    $image = imagecreatetruecolor($width, $height);
    
    if (!$image) {
        return false;
    }
    
    $bgColor = imagecolorallocate($image, 13, 27, 42); // Navy
    imagefilledrectangle($image, 0, 0, $width, $height, $bgColor);
    
    $textColor = imagecolorallocate($image, 212, 175, 122); // Gold
    
    $text = 'No Image';
    $fontSize = 5;
    $textWidth = imagefontwidth($fontSize) * strlen($text);
    $textHeight = imagefontheight($fontSize);
    $x = ($width - $textWidth) / 2;
    $y = ($height - $textHeight) / 2;
    
    imagestring($image, $fontSize, (int)$x, (int)$y, $text, $textColor);
    
    $result = imagewebp($image, $placeholderPath, 85);
    imagedestroy($image);
    
    if ($result) {
        @chmod($placeholderPath, FILE_PERMISSIONS);
    }
    
    return $result;
}

}

// Run this on file load
ensurePlaceholderImage();
?>