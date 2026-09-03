<?php
/**
 * api/hero.php - Hero slideshow API for Furusato Restaurant
 * FIXED: Removed concatenated menu.php code, proper JSON responses
 */

@ob_start();
@error_reporting(0);
@ini_set('display_errors', '0');

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");
header('Content-Type: application/json');

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/image-processor.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// GET: Public read - no auth required
if ($method === 'GET') {
    $hero = getJsonData('hero');
    if (!isset($hero['slides']) || !is_array($hero['slides'])) {
        $hero['slides'] = [];
    }
    jsonResponse($hero);
}

// All write operations require authentication
if (!startSecureSession(true)) {
    jsonResponse(['error' => 'Unauthorized - Please login first'], 401);
}

// Verify CSRF token. Accept the token from the header or the POST body,
// mirroring how the rest of the admin APIs read it.
$csrfIn = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? '');
if (!isset($_SESSION['csrf_token']) || !is_string($csrfIn) || !hash_equals($_SESSION['csrf_token'], $csrfIn)) {
    logAudit('HERO_CSRF_FAILED', "IP: " . getClientIP());
    jsonResponse(['error' => 'Invalid security token'], 403);
}

$hero = getJsonData('hero');
if (!isset($hero['slides'])) $hero['slides'] = [];

if ($method === 'POST') {
    $input = $_POST;
    $action = sanitize($input['action'] ?? '');
    
    // Reorder slides
    if ($action === 'reorder' && !empty($input['order'])) {
        $order = json_decode($input['order'], true) ?: [];
        if (!is_array($order)) {
            jsonResponse(['error' => 'Invalid order payload'], 400);
        }
        $slides = $hero['slides'] ?? [];
        $newSlides = [];
        foreach ($order as $slideId) {
            foreach ($slides as $slide) {
                if ($slide['id'] === $slideId) {
                    $newSlides[] = $slide;
                    break;
                }
            }
        }
        foreach ($slides as $slide) {
            if (!in_array($slide['id'], $order)) {
                $newSlides[] = $slide;
            }
        }
        $hero['slides'] = $newSlides;
        setJsonData('hero', $hero);
        logAudit('HERO_REORDERED');
        jsonResponse(['success' => true]);
    }
    
    // Toggle slide enabled/disabled
    if ($action === 'toggle' && !empty($input['slideId'])) {
        $slideId = sanitize($input['slideId']);
        $enabled = !empty($input['enabled']);
        $found = false;
        foreach ($hero['slides'] as &$slide) {
            if ($slide['id'] === $slideId) {
                $slide['enabled'] = $enabled;
                $found = true;
                break;
            }
        }
        if (!$found) {
            jsonResponse(['error' => 'Slide not found'], 404);
        }
        setJsonData('hero', $hero);
        logAudit('HERO_TOGGLED', "slide={$slideId} enabled=" . ($enabled ? '1' : '0'));
        jsonResponse(['success' => true]);
    }
    
    // Save/Update slide
    if ($action === 'save' && !empty($input['slideId'])) {
        $slideId = sanitize($input['slideId']);
        $found = false;
        foreach ($hero['slides'] as &$slide) {
            if ($slide['id'] === $slideId) {
                $allowed = ['headline', 'subtext', 'buttonText', 'buttonLink', 'enabled', 'order'];
                foreach ($allowed as $field) {
                    if (isset($input[$field])) {
                        $slide[$field] = sanitize($input[$field]);
                    }
                }
                // Handle image upload
                if (!empty($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                    $converted = convertToWebP($_FILES['image']['tmp_name'], $slideId, 'hero', 82);
                    if ($converted['success']) {
                        if (!empty($slide['image'])) {
                            deleteLocalImage($slide['image']);
                        }
                        $slide['image'] = $converted['path'];
                    }
                }
                $found = true;
                break;
            }
        }
        if (!$found) {
            jsonResponse(['error' => 'Slide not found'], 404);
        }
        setJsonData('hero', $hero);
        logAudit('HERO_SAVED', "slide={$slideId}");
        jsonResponse(['success' => true]);
    }
    
    // Delete slide
    if ($action === 'delete' && !empty($input['slideId'])) {
        $slideId = sanitize($input['slideId']);
        $found = false;
        foreach ($hero['slides'] as $i => $slide) {
            if ($slide['id'] === $slideId) {
                if (!empty($slide['image'])) {
                    deleteLocalImage($slide['image']);
                }
                array_splice($hero['slides'], $i, 1);
                $found = true;
                break;
            }
        }
        if (!$found) {
            jsonResponse(['error' => 'Slide not found'], 404);
        }
        setJsonData('hero', $hero);
        logAudit('HERO_DELETED', "slide={$slideId}");
        jsonResponse(['success' => true]);
    }
    
    jsonResponse(['error' => 'Invalid action'], 400);
}

if ($method === 'PATCH') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (isset($input['slides']) && is_array($input['slides'])) {
        /*
         * Hardened bulk update: every slide is rebuilt through an explicit
         * field whitelist so arbitrary client-supplied keys (or nested
         * structures) can never enter data/hero.json and reach the
         * public website.
         */
        $cleanSlides = [];
        $allowedText = ['id', 'headline', 'subtext', 'buttonText', 'buttonLink', 'image'];
        foreach ($input['slides'] as $slide) {
            if (!is_array($slide) || empty($slide['id']) || !is_string($slide['id'])) {
                continue;
            }
            $clean = [];
            foreach ($allowedText as $field) {
                if (isset($slide[$field]) && is_scalar($slide[$field])) {
                    $clean[$field] = sanitize((string) $slide[$field]);
                }
            }
            $clean['enabled'] = !empty($slide['enabled']);
            $clean['order'] = isset($slide['order']) ? max(0, (int) $slide['order']) : 999;
            $cleanSlides[] = $clean;
        }

        if (empty($cleanSlides)) {
            jsonResponse(['error' => 'Invalid payload: no valid slides'], 400);
        }

        usort($cleanSlides, function ($a, $b) {
            return ($a['order'] ?? 999) <=> ($b['order'] ?? 999);
        });

        $hero['slides'] = $cleanSlides;
        setJsonData('hero', $hero);
        logAudit('HERO_UPDATED', "IP: " . getClientIP());
        jsonResponse(['success' => true]);
    }
    jsonResponse(['error' => 'Invalid payload'], 400);
}

jsonResponse(['error' => 'Method not allowed'], 405);