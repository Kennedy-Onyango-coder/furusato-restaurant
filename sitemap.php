<?php
/**
 * sitemap.php - Dynamic XML Sitemap for Furusato Restaurant
 * GENERATES: Complete sitemap with images, lastmod dates, proper priorities
 * UPDATED: Added real lastmod dates, image sitemap, better structure
 */

// Set proper headers for XML
header('Content-Type: application/xml; charset=utf-8');

// Start XML output
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";

// Base URL
$baseUrl = "https://furusatorestaurant.com";

// Helper function to get file modification date
function getFileLastMod($filePath) {
    $fullPath = __DIR__ . $filePath;
    if (file_exists($fullPath)) {
        return date('Y-m-d', filemtime($fullPath));
    }
    return date('Y-m-d');
}

// Helper function to sanitize for URL
function sanitizeForUrl($string) {
    if (empty($string)) return 'item';
    $string = strtolower($string);
    $string = preg_replace('/[^a-z0-9-]/', '-', $string);
    $string = preg_replace('/-+/', '-', $string);
    return trim($string, '-');
}

// Get menu data for dynamic URLs
$menuFile = __DIR__ . '/data/menu.json';
$categoryUrls = [];
$imageUrls = [];

if (file_exists($menuFile)) {
    $menuData = json_decode(file_get_contents($menuFile), true);
    if ($menuData && isset($menuData['categories'])) {
        foreach ($menuData['categories'] as $category) {
            if (isset($category['visible']) && $category['visible'] !== false) {
                $slug = isset($category['slug']) ? $category['slug'] : sanitizeForUrl($category['label']);
                $categoryUrls[] = [
                    'url' => "/menu.php?category=" . urlencode($slug),
                    'priority' => '0.7',
                    'changefreq' => 'weekly',
                    'lastmod' => getFileLastMod('/data/menu.json')
                ];
            }
            
            // Collect images from items
            if (isset($category['items'])) {
                foreach ($category['items'] as $item) {
                    if (isset($item['visible']) && $item['visible'] !== false && !empty($item['image']) && !preg_match('/placeholder/i', $item['image'])) {
                        $imageUrls[] = [
                            'url' => "/menu.php",
                            'image' => $item['image']
                        ];
                    }
                }
            }
            
            // Collect images from subcategories
            if (isset($category['subcategories'])) {
                foreach ($category['subcategories'] as $subcategory) {
                    if (isset($subcategory['items'])) {
                        foreach ($subcategory['items'] as $item) {
                            if (isset($item['visible']) && $item['visible'] !== false && !empty($item['image']) && !preg_match('/placeholder/i', $item['image'])) {
                                $imageUrls[] = [
                                    'url' => "/menu.php",
                                    'image' => $item['image']
                                ];
                            }
                        }
                    }
                }
            }
        }
    }
}

// Get reservations count for sitemap priority
$reservationsFile = __DIR__ . '/data/reservations.json';
$reservationCount = 0;
if (file_exists($reservationsFile)) {
    $reservations = json_decode(file_get_contents($reservationsFile), true);
    $reservationCount = is_array($reservations) ? count($reservations) : 0;
}

// Define all static pages with dynamic lastmod dates
$pages = [
    ['url' => '/', 'priority' => '1.0', 'changefreq' => 'daily', 'lastmod' => getFileLastMod('/index.php')],
    ['url' => '/menu.php', 'priority' => '0.9', 'changefreq' => 'weekly', 'lastmod' => getFileLastMod('/menu.php')],
    ['url' => '/contact.php', 'priority' => '0.9', 'changefreq' => 'weekly', 'lastmod' => getFileLastMod('/contact.php')],
    ['url' => '/our-story.php', 'priority' => '0.8', 'changefreq' => 'monthly', 'lastmod' => getFileLastMod('/our-story.php')],
];

// Admin pages (lower priority, less frequent)
$adminPages = [
    ['url' => '/admin/dashboard.php', 'priority' => '0.3', 'changefreq' => 'never', 'lastmod' => getFileLastMod('/admin/dashboard.php')],
    ['url' => '/admin/login.php', 'priority' => '0.3', 'changefreq' => 'never', 'lastmod' => getFileLastMod('/admin/login.php')],
];

// API pages (very low priority)
$apiPages = [
    ['url' => '/api/menu.php', 'priority' => '0.2', 'changefreq' => 'always', 'lastmod' => date('Y-m-d')],
    ['url' => '/api/reservations.php', 'priority' => '0.2', 'changefreq' => 'always', 'lastmod' => date('Y-m-d')],
    ['url' => '/api/settings.php', 'priority' => '0.1', 'changefreq' => 'monthly', 'lastmod' => date('Y-m-d')],
    ['url' => '/api/auth.php', 'priority' => '0.1', 'changefreq' => 'monthly', 'lastmod' => date('Y-m-d')],
];

// Asset directories (not needed for search engines, but included for completeness)
$assetPaths = [
    '/assets/css/style.css',
    '/assets/css/animations.css',
    '/assets/js/main.js',
    '/assets/js/contact.js',
];

echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1" xmlns:xhtml="http://www.w3.org/1999/xhtml">' . "\n";

// Add main pages
foreach ($pages as $page) {
    echo '  <url>' . "\n";
    echo '    <loc>' . $baseUrl . $page['url'] . '</loc>' . "\n";
    echo '    <lastmod>' . $page['lastmod'] . '</lastmod>' . "\n";
    echo '    <changefreq>' . $page['changefreq'] . '</changefreq>' . "\n";
    echo '    <priority>' . $page['priority'] . '</priority>' . "\n";
    echo '  </url>' . "\n";
}

// Add category URLs
foreach ($categoryUrls as $catUrl) {
    echo '  <url>' . "\n";
    echo '    <loc>' . $baseUrl . $catUrl['url'] . '</loc>' . "\n";
    echo '    <lastmod>' . $catUrl['lastmod'] . '</lastmod>' . "\n";
    echo '    <changefreq>' . $catUrl['changefreq'] . '</changefreq>' . "\n";
    echo '    <priority>' . $catUrl['priority'] . '</priority>' . "\n";
    echo '  </url>' . "\n";
}

// Add admin pages (excluded from indexing via robots.txt but included for completeness)
foreach ($adminPages as $adminPage) {
    echo '  <url>' . "\n";
    echo '    <loc>' . $baseUrl . $adminPage['url'] . '</loc>' . "\n";
    echo '    <lastmod>' . $adminPage['lastmod'] . '</lastmod>' . "\n";
    echo '    <changefreq>' . $adminPage['changefreq'] . '</changefreq>' . "\n";
    echo '    <priority>' . $adminPage['priority'] . '</priority>' . "\n";
    echo '  </url>' . "\n";
}

// Add image sitemap entries for better Google Images ranking
$uniqueImages = [];
foreach ($imageUrls as $imageItem) {
    $imageUrl = $imageItem['image'];
    if (in_array($imageUrl, $uniqueImages)) continue;
    $uniqueImages[] = $imageUrl;
    
    $imageFullUrl = $baseUrl . '/' . ltrim($imageUrl, '/');
    echo '  <url>' . "\n";
    echo '    <loc>' . $baseUrl . $imageItem['url'] . '</loc>' . "\n";
    echo '    <image:image>' . "\n";
    echo '      <image:loc>' . htmlspecialchars($imageFullUrl) . '</image:loc>' . "\n";
    echo '      <image:title>Furusato Japanese Restaurant Menu Item</image:title>' . "\n";
    echo '    </image:image>' . "\n";
    echo '  </url>' . "\n";
}

echo '</urlset>';