<?php
/**
 * Menu Page - Furusato Japanese Restaurant
 * FIXED: Working search, discount pricing, proper includes
 * UPDATED: SEO improvements, Schema markup, working hours 9pm
 * UPDATED: Clean URLs (no .php extension)
 */
require_once __DIR__ . '/includes/functions.php';

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

$activeCategory = $_GET['category'] ?? null;
$styleVersion = get_asset_version('assets/css/style.css');
$menuCssVersion = get_asset_version('assets/css/menu.css');
$animationsVersion = get_asset_version('assets/css/animations.css');
$mainJsVersion = get_asset_version('assets/js/main.js');
$menuJsVersion = get_asset_version('assets/js/menu.js');

// Load menu data
$menuData = getJsonData('menu');
$categories = $menuData['categories'] ?? [];

// Sort categories by order
usort($categories, function($a, $b) {
    return ($a['order'] ?? 999) - ($b['order'] ?? 999);
});

// Get current page URL for canonical (without .php)
$currentUrl = 'https://' . $_SERVER['HTTP_HOST'] . str_replace('.php', '', $_SERVER['REQUEST_URI']);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate, max-age=0">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="description" content="Explore the full Furusato Japanese Restaurant menu - sushi, sashimi, tempura, noodles, teppanyaki, Korean dishes and more. All prices inclusive of VAT and Levy. Open daily 12pm-9pm.">
    <meta name="keywords" content="Furusato menu, Japanese restaurant menu Nairobi, sushi menu, sashimi menu, teppanyaki menu, authentic Japanese food Kenya">
    <meta name="author" content="Furusato Japanese Restaurant">
    <meta name="geo.region" content="KE-30">
    <meta name="geo.placename" content="Nairobi">
    <meta name="geo.position" content="-1.2624;36.8049">
    <meta name="ICBM" content="-1.2624, 36.8049">
    
    <!-- Canonical URL - Clean URL without .php -->
    <link rel="canonical" href="https://furusatorestaurant.com/menu">
    
    <title>Our Menu | Furusato Japanese Restaurant Nairobi | Sushi, Sashimi & Teppanyaki | Open 12pm-9pm</title>

    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://furusatorestaurant.com/menu">
    <meta property="og:title" content="Our Menu | Furusato Japanese Restaurant Nairobi">
    <meta property="og:description" content="Explore authentic Japanese cuisine - sushi, sashimi, tempura, noodles, teppanyaki, Korean dishes. Open daily 12pm-9pm.">
    <meta property="og:image" content="https://furusatorestaurant.com/assets/images/og-image.jpg">
    <meta property="og:site_name" content="Furusato Japanese Restaurant">

    <!-- Twitter -->
    <meta property="twitter:card" content="summary_large_image">
    <meta property="twitter:url" content="https://furusatorestaurant.com/menu">
    <meta property="twitter:title" content="Our Menu | Furusato Japanese Restaurant Nairobi">
    <meta property="twitter:description" content="Explore authentic Japanese cuisine - sushi, sashimi, tempura, noodles, teppanyaki. Open 12pm-9pm.">
    <meta property="twitter:image" content="https://furusatorestaurant.com/assets/images/og-image.jpg">

    <!-- JSON-LD Schema for Menu -->
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "Menu",
      "name": "Furusato Main Menu",
      "description": "Authentic Japanese cuisine menu featuring sushi, sashimi, tempura, noodles, teppanyaki and Korean dishes.",
      "hasMenuSection": [
        {
          "@type": "MenuSection",
          "name": "Sashimi",
          "description": "Fresh, authentic Japanese sashimi"
        },
        {
          "@type": "MenuSection",
          "name": "Sushi",
          "description": "Traditional and modern sushi rolls"
        },
        {
          "@type": "MenuSection",
          "name": "Teppanyaki",
          "description": "Hot plate grilled specialties"
        },
        {
          "@type": "MenuSection",
          "name": "Tempura",
          "description": "Light and crispy tempura dishes"
        },
        {
          "@type": "MenuSection",
          "name": "Noodles",
          "description": "Traditional Japanese noodle dishes"
        },
        {
          "@type": "MenuSection",
          "name": "Korean Specialties",
          "description": "Authentic Korean dishes"
        }
      ],
      "hasMenuItem": [
        {
          "@type": "MenuItem",
          "name": "Sashimi & Sushi Platter for 2",
          "description": "Assorted sashimi and sushi platter"
        },
        {
          "@type": "MenuItem",
          "name": "Teppanyaki Set Furusato",
          "description": "Complete teppanyaki experience"
        }
      ]
    }
    </script>

    <!-- Preconnect for performance -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="dns-prefetch" href="https://cdnjs.cloudflare.com">

    <!-- Favicon -->
    <link rel="icon" type="image/png" sizes="32x32" href="/assets/images/favicon/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/assets/images/favicon/favicon-16x16.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/assets/images/favicon/apple-touch-icon.png">
    <link rel="manifest" href="/site.webmanifest">

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500;600;700&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Stylesheets with cache busting -->
    <link rel="stylesheet" href="<?= asset_url('assets/css/style.css') . '?v=' . $styleVersion ?>">
    <link rel="stylesheet" href="<?= asset_url('assets/css/menu.css') . '?v=' . $menuCssVersion ?>">
    <link rel="stylesheet" href="<?= asset_url('assets/css/animations.css') . '?v=' . $animationsVersion ?>">
    
    <style>
        /* Menu Hero Button */
        .menu-hero__btn {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            margin-top: 28px;
            padding: 14px 36px;
            font-family: "Inter", sans-serif;
            font-size: 0.9375rem;
            font-weight: 600;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            color: #f9f6f0;
            background: transparent;
            border: 2px solid #f9f6f0;
            border-radius: 9999px;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        .menu-hero__btn:hover {
            background: #f9f6f0;
            color: #0d1b2a;
            border-color: #f9f6f0;
            transform: translateY(-2px);
        }
        .menu-hero__btn svg {
            width: 20px;
            height: 20px;
            fill: none;
            stroke: currentColor;
            stroke-width: 2;
        }
        
        /* Search Section */
        .menu-search-section {
            padding: 20px 0;
            background: #f9f6f0;
        }
        .menu-search {
            max-width: 600px;
            margin: 0 auto;
            padding: 0 20px;
        }
        .menu-search__input-wrap {
            position: relative;
        }
        .menu-search__icon {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #d4af7a;
        }
        .menu-search__input {
            width: 100%;
            padding: 14px 20px 14px 48px;
            border: 2px solid rgba(212, 175, 122, 0.3);
            border-radius: 50px;
            font-size: 1rem;
            font-family: "Inter", sans-serif;
            outline: none;
            transition: all 0.3s ease;
        }
        .menu-search__input:focus {
            border-color: #d4af7a;
            box-shadow: 0 0 0 3px rgba(212, 175, 122, 0.2);
        }
        .menu-search__clear {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            font-size: 1.2rem;
            cursor: pointer;
            color: #999;
            display: none;
        }
        .menu-search__clear.visible {
            display: block;
        }
        
        /* Search Results */
        .no-results {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 16px;
            margin: 40px 0;
        }
        .no-results-icon {
            font-size: 4rem;
            margin-bottom: 16px;
        }
        .no-results h3 {
            font-family: "Cormorant Garamond", serif;
            font-size: 1.5rem;
            margin-bottom: 8px;
        }
        .no-results p {
            color: #666;
        }
        
        /* Hidden items during search */
        .menu-item-card.search-hidden {
            display: none !important;
        }
        .menu-category.search-hidden {
            display: none !important;
        }
        .menu-subcategory.search-hidden {
            display: none !important;
        }
        
        /* Discount Price Styling */
        .menu-item-card__price {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        .current-price {
            font-size: 1.2rem;
            font-weight: 800;
            color: #c0392b;
        }
        .original-price {
            font-size: 0.9rem;
            font-weight: 400;
            color: #999;
            text-decoration: line-through;
        }
        .discount-badge {
            background: #c0392b;
            color: white;
            font-size: 0.7rem;
            padding: 2px 8px;
            border-radius: 20px;
            font-weight: 600;
        }
        
        /* ============================================
           FOOTER FIXES - Gold Links Instead of Blue
           ============================================ */
        .footer a {
            color: #d4af37 !important;
            text-decoration: none;
            transition: color 0.3s ease;
        }
        .footer a:hover {
            color: #ffd700 !important;
            text-decoration: underline;
        }
        .footer-contact a {
            color: #d4af37 !important;
        }
        .footer-links a {
            color: #d4af37 !important;
        }
        .footer-bottom a {
            color: #d4af37 !important;
        }
        .footer-contact .icon {
            color: #d4af37;
            margin-right: 10px;
        }
        .footer-contact .icon svg {
            stroke: #d4af37;
        }
        .footer ul li a {
            color: #d4af37 !important;
        }
        .footer ul li a:hover {
            color: #ffd700 !important;
        }
        
        /* WhatsApp button */
        .whatsapp-float {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background: #25D366;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            transition: all 0.3s ease;
        }
        .whatsapp-float:hover {
            transform: scale(1.1);
            background: #20b859;
        }
        .whatsapp-float svg {
            width: 32px;
            height: 32px;
        }
        .footer-social a {
            color: #d4af37 !important;
        }
        .footer-social a:hover {
            color: #ffd700 !important;
        }
        .mobile-menu a {
            color: white;
            text-decoration: none;
        }
        .mobile-menu a:hover {
            color: #d4af37;
        }
        .navbar-nav a.active {
            color: #d4af37;
        }

        /* ============================================
           MY ENQUIRY BAR & PANEL (not a shopping cart)
           ============================================ */
        .menu-item-card__price-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            flex-wrap: wrap;
        }
        .menu-item-card__enquire-add {
            background: transparent;
            border: 1px solid rgba(212, 175, 122, 0.6);
            color: #8a6d3b;
            font-family: "Inter", sans-serif;
            font-size: 0.72rem;
            font-weight: 600;
            padding: 6px 10px;
            border-radius: 20px;
            cursor: pointer;
            transition: all 0.25s ease;
            white-space: nowrap;
        }
        .menu-item-card__enquire-add:hover {
            background: #d4af7a;
            color: #fff;
            border-color: #d4af7a;
        }
        .menu-item-card__enquire-add.added {
            background: #25D366;
            border-color: #25D366;
            color: #fff;
        }

        .enquiry-bar {
            position: fixed;
            bottom: 0; left: 0; right: 0;
            background: #0d1b2a;
            color: #f9f6f0;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            padding: 12px 20px;
            z-index: 1200;
            box-shadow: 0 -4px 18px rgba(0,0,0,0.25);
            flex-wrap: wrap;
        }
        .enquiry-bar__info { display: flex; align-items: center; gap: 10px; }
        .enquiry-bar__title { font-weight: 700; font-size: 0.9rem; letter-spacing: 0.04em; text-transform: uppercase; }
        .enquiry-bar__count {
            background: #25D366; color: #fff;
            border-radius: 50%; min-width: 24px; height: 24px;
            display: inline-flex; align-items: center; justify-content: center;
            font-size: 0.78rem; font-weight: 700;
        }
        .enquiry-bar__actions { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
        .enquiry-bar__btn {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 10px 18px; border-radius: 9999px;
            font-family: "Inter", sans-serif; font-size: 0.82rem; font-weight: 600;
            cursor: pointer; text-decoration: none; transition: all 0.25s ease;
            border: none;
        }
        .enquiry-bar__btn--ghost { background: transparent; color: #f9f6f0; border: 1px solid rgba(255,255,255,0.35); }
        .enquiry-bar__btn--ghost:hover { border-color: #fff; }
        .enquiry-bar__btn--primary { background: #25D366; color: #fff; }
        .enquiry-bar__btn--primary:hover { background: #20b859; }

        .enquiry-panel {
            position: fixed;
            bottom: 80px; right: 20px;
            width: min(380px, calc(100vw - 40px));
            max-height: 70vh;
            overflow-y: auto;
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            padding: 20px;
            z-index: 1300;
        }
        .enquiry-panel__header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px; }
        .enquiry-panel__header h3 { font-family: "Cormorant Garamond", serif; font-size: 1.3rem; margin: 0; }
        .enquiry-panel__close { background: none; border: none; font-size: 1.6rem; cursor: pointer; color: #666; line-height: 1; }
        .enquiry-panel__note { font-size: 0.8rem; color: #666; margin-bottom: 12px; line-height: 1.5; }
        .enquiry-panel__list { list-style: none; margin: 0 0 12px; padding: 0; }
        .enquiry-panel__list li {
            display: flex; align-items: center; justify-content: space-between;
            gap: 10px; padding: 8px 0; border-bottom: 1px solid #eee;
            font-size: 0.88rem;
        }
        .enquiry-panel__list li .enquiry-item-price { color: #888; font-size: 0.78rem; white-space: nowrap; }
        .enquiry-panel__list li button {
            background: none; border: none; color: #c0392b;
            cursor: pointer; font-size: 1rem; padding: 2px 6px;
        }
        .enquiry-panel__disclaimer {
            font-size: 0.75rem; color: #666; line-height: 1.55;
            background: #fdf6ec; border-left: 3px solid #d4af7a;
            padding: 10px 12px; border-radius: 6px; margin-bottom: 14px;
        }
        .enquiry-panel__send {
            display: inline-flex; align-items: center; justify-content: center; gap: 8px;
            width: 100%; padding: 13px; border-radius: 9999px;
            background: #25D366; color: #fff !important;
            font-weight: 700; font-size: 0.9rem; text-decoration: none;
            transition: background 0.25s ease;
        }
        .enquiry-panel__send:hover { background: #20b859; }
        .enquiry-empty { text-align: center; color: #999; font-size: 0.85rem; padding: 16px 0; }

        @media (max-width: 640px) {
            .enquiry-bar { padding: 10px 14px; }
            .enquiry-panel { bottom: 76px; right: 12px; left: 12px; width: auto; }
        }
    </style>
</head>
<body>

<!-- NAVBAR -->
<header class="navbar" role="banner">
    <div class="navbar-inner">
        <a href="/" class="navbar-logo">
            <img src="/assets/images/furusato-logo.png" alt="Furusato" width="48" height="48" class="navbar-logo-img">
            <span class="navbar-logo-text">Furusato <span>Restaurant</span></span>
        </a>
        <nav class="navbar-nav">
            <a href="/">Home</a>
            <div class="nav-dropdown">
                <a href="/menu" class="nav-dropdown-trigger active">Menu <svg width="10" height="6" viewBox="0 0 10 6" fill="none" stroke="currentColor"><path d="M1 1l4 4 4-4"/></svg></a>
                <div class="nav-dropdown-menu" id="nav-menu-categories"></div>
            </div>
            <a href="/our-story">Our Story</a>
            <a href="/contact">Contact</a>
        </nav>
        <div class="navbar-reserve">
            <a href="/contact#reservation" class="btn">Reserve</a>
        </div>
        <button class="navbar-hamburger" aria-label="Toggle menu"><span></span><span></span><span></span></button>
    </div>
</header>

<div class="mobile-menu">
    <a href="/">Home</a>
    <a href="/menu" class="active">Menu</a>
    <div class="mobile-menu-categories" id="mobile-menu-categories"></div>
    <a href="/our-story">Our Story</a>
    <a href="/contact">Contact</a>
    <a href="/contact#reservation" class="btn btn-primary">Reserve a Table</a>
</div>

<!-- MENU HERO -->
<section class="menu-hero">
    <div class="menu-hero__bg" style="background-image: url('/assets/images/hero/sushi-hero.webp')"></div>
    <div class="menu-hero__overlay"></div>
    <div class="menu-hero__content">
        <h1 class="menu-hero__title">Our Menu</h1>
        <p class="menu-hero__subtitle">All prices inclusive of VAT and Levy | Open Daily 12pm-9pm</p>
        <a href="/assets/docs/furusato-menu.pdf" target="_blank" class="menu-hero__btn" rel="noopener noreferrer">
            <svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
            Download Menu PDF
        </a>
    </div>
</section>

<!-- CATEGORY NAV -->
<nav class="menu-category-nav">
    <div class="menu-category-nav__inner" id="menu-category-nav-inner">
        <?php foreach ($categories as $category): ?>
            <?php if ($category['visible'] !== false): ?>
                <button class="menu-category-nav__link" data-category-id="<?= htmlspecialchars($category['slug'] ?? 'cat-' . $category['id']) ?>">
                    <span><?= htmlspecialchars($category['icon'] ?? '🍽️') ?> <?= htmlspecialchars($category['label'] ?? 'Category') ?></span>
                </button>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
</nav>

<!-- SEARCH -->
<section class="menu-search-section">
    <div class="menu-search">
        <div class="menu-search__input-wrap">
            <span class="menu-search__icon"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg></span>
            <input type="search" class="menu-search__input" id="menu-search-input" placeholder="Search our menu... (e.g., Sushi, Sashimi, Tempura)" autocomplete="off">
            <button class="menu-search__clear" id="menu-search-clear">&times;</button>
        </div>
    </div>
</section>

<!-- MENU CONTENT -->
<main id="menu-content" class="container" role="main">
    <?php foreach ($categories as $category): ?>
        <?php if ($category['visible'] !== false): ?>
            <div class="menu-category" id="category-<?= htmlspecialchars($category['slug'] ?? 'cat-' . $category['id']) ?>" data-category-name="<?= strtolower(htmlspecialchars($category['label'] ?? '')) ?>">
                <div class="menu-category__header">
                    <h2 class="menu-category__name"><?= htmlspecialchars($category['label'] ?? 'Category') ?></h2>
                    <?php if (!empty($category['labelJp'])): ?>
                        <div class="menu-category__subtitle"><?= htmlspecialchars($category['labelJp']) ?></div>
                    <?php endif; ?>
                </div>
                
                <!-- Subcategories -->
                <?php if (!empty($category['subcategories'])): ?>
                    <?php foreach ($category['subcategories'] as $subcategory): ?>
                        <?php if ($subcategory['visible'] !== false): ?>
                            <div class="menu-subcategory" data-subcategory-name="<?= strtolower(htmlspecialchars($subcategory['label'] ?? '')) ?>">
                                <h3 class="menu-subcategory__name"><?= htmlspecialchars($subcategory['label'] ?? '') ?></h3>
                                <?php if (!empty($subcategory['labelJp'])): ?>
                                    <div class="menu-subcategory__subtitle"><?= htmlspecialchars($subcategory['labelJp']) ?></div>
                                <?php endif; ?>
                                <div class="menu-items-grid">
                                    <?php foreach ($subcategory['items'] ?? [] as $item): ?>
                                        <?php if ($item['visible'] !== false): ?>
                                            <div class="menu-item-card" data-item-name="<?= strtolower(htmlspecialchars($item['name'])) ?>" data-item-desc="<?= strtolower(htmlspecialchars($item['description'] ?? '')) ?>">
                                                <div class="menu-item-card__image-wrap">
                                                    <?php 
                                                    $imageUrl = !empty($item['image']) ? getImageUrl($item['image']) : '/assets/images/menu/placeholder.webp';
                                                    ?>
                                                    <img src="<?= htmlspecialchars($imageUrl) ?>" alt="<?= htmlspecialchars($item['name']) ?>" class="menu-item-card__image" loading="lazy" onerror="this.src='/assets/images/menu/placeholder.webp'">
                                                    <?php if (!empty($item['badge'])): ?>
                                                        <span class="menu-item-card__badge"><?= htmlspecialchars($item['badge']) ?></span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="menu-item-card__content">
                                                    <h4 class="menu-item-card__name"><?= htmlspecialchars($item['name']) ?></h4>
                                                    <p class="menu-item-card__description"><?= nl2br(htmlspecialchars($item['description'] ?? '')) ?></p>
                                                    <div class="menu-item-card__price-row">
                                                        <div class="menu-item-card__price">
                                                            <?php if (!empty($item['original_price']) && $item['original_price'] > $item['price']): ?>
                                                                <span class="original-price">Ksh <?= number_format($item['original_price']) ?></span>
                                                                <span class="current-price">Ksh <?= number_format($item['price']) ?></span>
                                                                <span class="discount-badge">Save <?= number_format($item['original_price'] - $item['price']) ?></span>
                                                            <?php else: ?>
                                                                <span class="current-price">Ksh <?= number_format($item['price']) ?></span>
                                                            <?php endif; ?>
                                                        </div>
                                                        <a href="<?= htmlspecialchars(wa_link(menu_enquiry_message($item['name'], $item['price'] ?? null))) ?>" class="menu-item-card__whatsapp" target="_blank" rel="noopener noreferrer">
                                                            <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor">
                                                                <path d="M12.04 2c-5.46 0-9.91 4.45-9.91 9.91 0 1.75.46 3.45 1.32 4.95L2.05 22l5.25-1.38c1.45.79 3.08 1.21 4.74 1.21 5.46 0 9.91-4.45 9.91-9.91 0-5.46-4.45-9.91-9.91-9.91zm0 18.23c-1.5 0-2.96-.4-4.23-1.16l-.3-.18-3.12.82.83-3.04-.2-.31c-.84-1.36-1.28-2.93-1.28-4.53 0-4.56 3.71-8.28 8.28-8.28 4.56 0 8.28 3.71 8.28 8.28.01 4.56-3.71 8.28-8.27 8.28zm4.53-6.2c-.25-.12-1.47-.73-1.7-.81-.23-.08-.39-.12-.56.12-.17.24-.66.81-.81.98-.15.17-.29.19-.54.06-.25-.12-1.05-.39-2-1.23-.74-.66-1.23-1.47-1.38-1.72-.15-.25-.02-.38.11-.51.11-.11.25-.29.38-.44.13-.15.17-.25.26-.42.09-.17.04-.31-.02-.43-.06-.12-.56-1.35-.77-1.85-.2-.48-.41-.41-.56-.42-.14-.01-.3-.01-.46-.01-.16 0-.42.06-.64.31-.22.25-.84.82-.84 2 0 1.18.86 2.32.98 2.48.12.16 1.69 2.58 4.09 3.62.57.25 1.02.39 1.37.5.57.18 1.09.15 1.5.09.46-.07 1.41-.58 1.61-1.13.2-.55.2-1.02.14-1.12-.06-.1-.22-.16-.47-.28z"/>
                                                            </svg>
                                                            Enquire on WhatsApp
                                                        </a>
                                                        <button type="button" class="menu-item-card__enquire-add" data-enquiry-name="<?= htmlspecialchars($item['name']) ?>" data-enquiry-price="<?= htmlspecialchars((string)($item['price'] ?? '')) ?>" aria-label="Add <?= htmlspecialchars($item['name']) ?> to My Enquiry">+ My Enquiry</button>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
                
                <!-- Main Category Items (not in subcategories) -->
                <?php if (!empty($category['items'])): ?>
                    <div class="menu-items-grid">
                        <?php foreach ($category['items'] as $item): ?>
                            <?php if ($item['visible'] !== false): ?>
                                <div class="menu-item-card" data-item-name="<?= strtolower(htmlspecialchars($item['name'])) ?>" data-item-desc="<?= strtolower(htmlspecialchars($item['description'] ?? '')) ?>">
                                    <div class="menu-item-card__image-wrap">
                                        <?php 
                                        $imageUrl = !empty($item['image']) ? getImageUrl($item['image']) : '/assets/images/menu/placeholder.webp';
                                        ?>
                                        <img src="<?= htmlspecialchars($imageUrl) ?>" alt="<?= htmlspecialchars($item['name']) ?>" class="menu-item-card__image" loading="lazy" onerror="this.src='/assets/images/menu/placeholder.webp'">
                                        <?php if (!empty($item['badge'])): ?>
                                            <span class="menu-item-card__badge"><?= htmlspecialchars($item['badge']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="menu-item-card__content">
                                        <h4 class="menu-item-card__name"><?= htmlspecialchars($item['name']) ?></h4>
                                        <p class="menu-item-card__description"><?= nl2br(htmlspecialchars($item['description'] ?? '')) ?></p>
                                        <div class="menu-item-card__price-row">
                                            <div class="menu-item-card__price">
                                                <?php if (!empty($item['original_price']) && $item['original_price'] > $item['price']): ?>
                                                    <span class="original-price">Ksh <?= number_format($item['original_price']) ?></span>
                                                    <span class="current-price">Ksh <?= number_format($item['price']) ?></span>
                                                    <span class="discount-badge">Save <?= number_format($item['original_price'] - $item['price']) ?></span>
                                                <?php else: ?>
                                                    <span class="current-price">Ksh <?= number_format($item['price']) ?></span>
                                                <?php endif; ?>
                                            </div>
                                            <a href="<?= htmlspecialchars(wa_link(menu_enquiry_message($item['name'], $item['price'] ?? null))) ?>" class="menu-item-card__whatsapp" target="_blank" rel="noopener noreferrer">
                                                <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor">
                                                    <path d="M12.04 2c-5.46 0-9.91 4.45-9.91 9.91 0 1.75.46 3.45 1.32 4.95L2.05 22l5.25-1.38c1.45.79 3.08 1.21 4.74 1.21 5.46 0 9.91-4.45 9.91-9.91 0-5.46-4.45-9.91-9.91-9.91zm0 18.23c-1.5 0-2.96-.4-4.23-1.16l-.3-.18-3.12.82.83-3.04-.2-.31c-.84-1.36-1.28-2.93-1.28-4.53 0-4.56 3.71-8.28 8.28-8.28 4.56 0 8.28 3.71 8.28 8.28.01 4.56-3.71 8.28-8.27 8.28zm4.53-6.2c-.25-.12-1.47-.73-1.7-.81-.23-.08-.39-.12-.56.12-.17.24-.66.81-.81.98-.15.17-.29.19-.54.06-.25-.12-1.05-.39-2-1.23-.74-.66-1.23-1.47-1.38-1.72-.15-.25-.02-.38.11-.51.11-.11.25-.29.38-.44.13-.15.17-.25.26-.42.09-.17.04-.31-.02-.43-.06-.12-.56-1.35-.77-1.85-.2-.48-.41-.41-.56-.42-.14-.01-.3-.01-.46-.01-.16 0-.42.06-.64.31-.22.25-.84.82-.84 2 0 1.18.86 2.32.98 2.48.12.16 1.69 2.58 4.09 3.62.57.25 1.02.39 1.37.5.57.18 1.09.15 1.5.09.46-.07 1.41-.58 1.61-1.13.2-.55.2-1.02.14-1.12-.06-.1-.22-.16-.47-.28z"/>
                                                </svg>
                                                Enquire on WhatsApp
                                            </a>
                                            <button type="button" class="menu-item-card__enquire-add" data-enquiry-name="<?= htmlspecialchars($item['name']) ?>" data-enquiry-price="<?= htmlspecialchars((string)($item['price'] ?? '')) ?>" aria-label="Add <?= htmlspecialchars($item['name']) ?> to My Enquiry">+ My Enquiry</button>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endforeach; ?>
    
    <!-- No Results Template -->
    <div id="no-results" class="no-results" style="display: none;">
        <div class="no-results-icon">🔍</div>
        <h3>No items found</h3>
        <p>Try searching for something else or browse our categories above.</p>
    </div>
</main>
<div id="menu-download"></div>

<!-- ============================================================
   FOOTER - Unified Design (UPDATED HOURS + CLEAN URLs)
   ============================================================ -->
<footer class="footer">
    <div class="container">
        <div class="footer-top">
            <div class="footer-brand">
                <div class="footer-logo">
                    <span class="footer-logo-text">Furu<span>sato</span></span>
                </div>
                <p class="footer-tagline">Where tradition meets taste. Authentic Japanese cuisine crafted with passion in the heart of Nairobi.</p>
                <div class="footer-social">
                    <a href="https://www.facebook.com/FurusatoNairobi" target="_blank" rel="noopener noreferrer" aria-label="Facebook">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                        </svg>
                    </a>
                    <a href="https://www.instagram.com/furusato_japanese_restaurant" target="_blank" rel="noopener noreferrer" aria-label="Instagram">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069z"/>
                        </svg>
                    </a>
                </div>
            </div>

            <div class="footer-links">
                <h4>Quick Links</h4>
                <ul>
                    <li><a href="/">Home</a></li>
                    <li><a href="/menu">Our Menu</a></li>
                    <li><a href="/our-story">Our Story</a></li>
                    <li><a href="/contact">Contact Us</a></li>
                    <li><a href="/contact#reservation">Reservations</a></li>
                </ul>
            </div>

            <div class="footer-contact">
                <h4>Contact Us</h4>
                <ul>
                    <li>
                        <span class="icon">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>
                                <circle cx="12" cy="10" r="3"/>
                            </svg>
                        </span>
                        <span>Ring Road Parklands, Westlands, Nairobi, Kenya</span>
                    </li>
                    <li>
                        <span class="icon">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>
                            </svg>
                        </span>
                        <a href="tel:+254722488706">0722 488 706</a> / <a href="tel:+254734639203">0734 639 203</a>
                    </li>
                    <li>
                        <span class="icon">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                                <polyline points="22,6 12,13 2,6"/>
                            </svg>
                        </span>
                        <a href="mailto:furusatoreservation@gmail.com">furusatoreservation@gmail.com</a>
                    </li>
                    <li>
                        <span class="icon">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                <circle cx="12" cy="12" r="10"/>
                                <polyline points="12 6 12 12 16 14"/>
                            </svg>
                        </span>
                        <span>Open Daily: 12:00 PM - 9:00 PM</span>
                    </li>
                </ul>
            </div>
        </div>

        <div class="footer-bottom">
            <span>&copy; <?= date('Y') ?> Furusato Japanese Restaurant. All prices inclusive of VAT and Levy.</span>
            <span>Designed with care in Nairobi</span>
        </div>
    </div>
</footer>

<!-- MY ENQUIRY BAR (temporary list — NOT a shopping cart) -->
<div id="enquiry-bar" class="enquiry-bar" style="display:none;" role="region" aria-label="My Enquiry">
    <div class="enquiry-bar__info">
        <span class="enquiry-bar__title">My Enquiry</span>
        <span class="enquiry-bar__count" id="enquiry-count">0</span>
    </div>
    <div class="enquiry-bar__actions">
        <button type="button" id="enquiry-view-btn" class="enquiry-bar__btn enquiry-bar__btn--ghost">View List</button>
        <a href="#" id="enquiry-send-link" class="enquiry-bar__btn enquiry-bar__btn--primary" target="_blank" rel="noopener noreferrer">
            <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M12.04 2c-5.46 0-9.91 4.45-9.91 9.91 0 1.75.46 3.45 1.32 4.95L2.05 22l5.25-1.38c1.45.79 3.08 1.21 4.74 1.21 5.46 0 9.91-4.45 9.91-9.91 0-5.46-4.45-9.91-9.91-9.91zm0 18.23c-1.5 0-2.96-.4-4.23-1.16l-.3-.18-3.12.82.83-3.04-.2-.31c-.84-1.36-1.28-2.93-1.28-4.53 0-4.56 3.71-8.28 8.28-8.28 4.56 0 8.28 3.71 8.28 8.28.01 4.56-3.71 8.28-8.27 8.28z"/></svg>
            Enquire on WhatsApp
        </a>
        <button type="button" id="enquiry-clear-btn" class="enquiry-bar__btn enquiry-bar__btn--ghost" aria-label="Clear enquiry list">&times;</button>
    </div>
</div>

<!-- MY ENQUIRY PANEL -->
<div id="enquiry-panel" class="enquiry-panel" style="display:none;" role="dialog" aria-label="My Enquiry list">
    <div class="enquiry-panel__header">
        <h3>My Enquiry</h3>
        <button type="button" id="enquiry-close-btn" class="enquiry-panel__close" aria-label="Close">&times;</button>
    </div>
    <p class="enquiry-panel__note">This is a temporary list of menu items you would like to ask us about. It is not an order and nothing is reserved or charged.</p>
    <ul id="enquiry-items" class="enquiry-panel__list"></ul>
    <p class="enquiry-panel__disclaimer"><strong>Please note:</strong> This enquiry does not constitute a confirmed order or reservation. Our team will respond on WhatsApp to confirm availability and guide you on the next steps.</p>
    <a href="#" id="enquiry-panel-send" class="enquiry-panel__send" target="_blank" rel="noopener noreferrer">
        <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor"><path d="M12.04 2c-5.46 0-9.91 4.45-9.91 9.91 0 1.75.46 3.45 1.32 4.95L2.05 22l5.25-1.38c1.45.79 3.08 1.21 4.74 1.21 5.46 0 9.91-4.45 9.91-9.91 0-5.46-4.45-9.91-9.91-9.91zm0 18.23c-1.5 0-2.96-.4-4.23-1.16l-.3-.18-3.12.82.83-3.04-.2-.31c-.84-1.36-1.28-2.93-1.28-4.53 0-4.56 3.71-8.28 8.28-8.28 4.56 0 8.28 3.71 8.28 8.28.01 4.56-3.71 8.28-8.27 8.28z"/></svg>
        Enquire on WhatsApp
    </a>
</div>

<!-- WhatsApp Floating Button -->
<a href="<?= htmlspecialchars(wa_link("Hello Furusato Japanese Restaurant,\n\nI would like to make a reservation.\n\nThank you.")) ?>" class="whatsapp-float" target="_blank" rel="noopener noreferrer" aria-label="Chat on WhatsApp">
    <svg width="28" height="28" viewBox="0 0 24 24" fill="white">
        <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.473-.148-.673.149-.2.297-.768.967-.94 1.164-.173.199-.347.223-.644.075-.297-.149-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.864 9.864 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.88 11.88 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
    </svg>
</a>

<script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/ScrollTrigger.min.js"></script>
<script src="<?= asset_url('assets/js/main.js') . '?v=' . $mainJsVersion ?>"></script>
<script src="<?= asset_url('assets/js/menu.js') . '?v=' . $menuJsVersion ?>"></script>

<script>
    // Pass active category to menu.js
    window.MENU_INIT_CATEGORY = <?= json_encode($activeCategory) ?>;
    
    // Update footer year dynamically (null-safe)
    var footerYearEl = document.getElementById('footer-year');
    if (footerYearEl) footerYearEl.textContent = new Date().getFullYear();
    
    // SEARCH FUNCTIONALITY
    (function() {
        const searchInput = document.getElementById('menu-search-input');
        const searchClear = document.getElementById('menu-search-clear');
        const noResultsDiv = document.getElementById('no-results');
        const menuContent = document.getElementById('menu-content');
        let searchTimeout = null;
        
        function performSearch() {
            const searchTerm = searchInput.value.trim().toLowerCase();
            
            if (searchTerm === '') {
                document.querySelectorAll('.menu-item-card').forEach(el => el.classList.remove('search-hidden'));
                document.querySelectorAll('.menu-category').forEach(el => el.classList.remove('search-hidden'));
                document.querySelectorAll('.menu-subcategory').forEach(el => el.classList.remove('search-hidden'));
                noResultsDiv.style.display = 'none';
                if (searchClear) searchClear.classList.remove('visible');
                return;
            }
            
            if (searchClear) searchClear.classList.add('visible');
            
            let hasVisibleItems = false;
            
            document.querySelectorAll('.menu-category').forEach(category => {
                const categoryName = category.dataset.categoryName || '';
                let categoryHasVisible = false;
                
                category.querySelectorAll('.menu-subcategory').forEach(subcategory => {
                    const subcategoryName = subcategory.dataset.subcategoryName || '';
                    let subcategoryHasVisible = false;
                    
                    subcategory.querySelectorAll('.menu-item-card').forEach(item => {
                        const itemName = item.dataset.itemName || '';
                        const itemDesc = item.dataset.itemDesc || '';
                        
                        const matches = itemName.includes(searchTerm) || 
                                       itemDesc.includes(searchTerm) || 
                                       categoryName.includes(searchTerm) ||
                                       subcategoryName.includes(searchTerm);
                        
                        if (matches) {
                            item.classList.remove('search-hidden');
                            subcategoryHasVisible = true;
                            categoryHasVisible = true;
                            hasVisibleItems = true;
                        } else {
                            item.classList.add('search-hidden');
                        }
                    });
                    
                    if (subcategoryHasVisible) {
                        subcategory.classList.remove('search-hidden');
                    } else {
                        subcategory.classList.add('search-hidden');
                    }
                });
                
                category.querySelectorAll('.menu-items-grid:not(.menu-subcategory .menu-items-grid) .menu-item-card').forEach(item => {
                    const itemName = item.dataset.itemName || '';
                    const itemDesc = item.dataset.itemDesc || '';
                    
                    const matches = itemName.includes(searchTerm) || 
                                   itemDesc.includes(searchTerm) || 
                                   categoryName.includes(searchTerm);
                    
                    if (matches) {
                        item.classList.remove('search-hidden');
                        categoryHasVisible = true;
                        hasVisibleItems = true;
                    } else {
                        item.classList.add('search-hidden');
                    }
                });
                
                if (categoryHasVisible) {
                    category.classList.remove('search-hidden');
                } else {
                    category.classList.add('search-hidden');
                }
            });
            
            if (hasVisibleItems) {
                noResultsDiv.style.display = 'none';
            } else {
                noResultsDiv.style.display = 'block';
            }
        }
        
        if (searchInput) {
            searchInput.addEventListener('input', function() {
                if (searchTimeout) clearTimeout(searchTimeout);
                searchTimeout = setTimeout(performSearch, 300);
            });
        }
        
        if (searchClear) {
            searchClear.addEventListener('click', function() {
                if (searchInput) {
                    searchInput.value = '';
                    performSearch();
                    searchInput.focus();
                }
            });
        }
    })();

    // MY ENQUIRY — temporary list of items to ask about (NOT a cart/order)
    (function() {
        var STORAGE_KEY = 'furusato_my_enquiry';
        var bar      = document.getElementById('enquiry-bar');
        var panel    = document.getElementById('enquiry-panel');
        var countEl  = document.getElementById('enquiry-count');
        var listEl   = document.getElementById('enquiry-items');
        var sendLink = document.getElementById('enquiry-send-link');
        var panelSend= document.getElementById('enquiry-panel-send');
        if (!bar || !panel) return;

        function load() {
            try { return JSON.parse(localStorage.getItem(STORAGE_KEY)) || []; }
            catch (e) { return []; }
        }
        function save(items) {
            try { localStorage.setItem(STORAGE_KEY, JSON.stringify(items)); } catch (e) {}
        }

        // WhatsApp number comes from Admin Settings via the settings API
        var waNumber = '<?= htmlspecialchars(get_whatsapp_number()) ?>';

        function buildMessage(items) {
            var msg = "Hello Furusato Japanese Restaurant,\n\nI would like to enquire about the following menu items:\n\n";
            items.forEach(function(it) { msg += "- " + it.name + "\n"; });
            msg += "\nCould you please confirm availability and provide any relevant information?\n\nThank you.";
            return msg;
        }

        function render() {
            var items = load();
            countEl.textContent = items.length;
            bar.style.display = items.length > 0 ? 'flex' : 'none';

            listEl.innerHTML = '';
            if (items.length === 0) {
                var li = document.createElement('li');
                li.className = 'enquiry-empty';
                li.style.borderBottom = 'none';
                li.textContent = 'Your enquiry list is empty. Tap "+ My Enquiry" on any menu item.';
                listEl.appendChild(li);
            } else {
                items.forEach(function(it, idx) {
                    var li = document.createElement('li');
                    var nameSpan = document.createElement('span');
                    nameSpan.textContent = it.name;
                    li.appendChild(nameSpan);
                    if (it.price) {
                        var priceSpan = document.createElement('span');
                        priceSpan.className = 'enquiry-item-price';
                        priceSpan.textContent = 'KES ' + Number(it.price).toLocaleString();
                        li.appendChild(priceSpan);
                    }
                    var rm = document.createElement('button');
                    rm.type = 'button';
                    rm.setAttribute('aria-label', 'Remove ' + it.name);
                    rm.innerHTML = '&times;';
                    rm.addEventListener('click', function() {
                        var cur = load();
                        cur.splice(idx, 1);
                        save(cur);
                        syncButtons();
                        render();
                    });
                    li.appendChild(rm);
                    listEl.appendChild(li);
                });
            }

            var href = items.length > 0
                ? 'https://wa.me/' + waNumber + '?text=' + encodeURIComponent(buildMessage(items))
                : '#';
            sendLink.setAttribute('href', href);
            panelSend.setAttribute('href', href);
            sendLink.style.opacity = items.length > 0 ? '1' : '0.5';
            panelSend.style.opacity = items.length > 0 ? '1' : '0.5';
        }

        function syncButtons() {
            var names = {};
            load().forEach(function(it) { names[it.name] = true; });
            document.querySelectorAll('.menu-item-card__enquire-add').forEach(function(btn) {
                var n = btn.getAttribute('data-enquiry-name') || '';
                if (names[n]) {
                    btn.classList.add('added');
                    btn.textContent = '\u2713 In My Enquiry';
                } else {
                    btn.classList.remove('added');
                    btn.textContent = '+ My Enquiry';
                }
            });
        }

        document.querySelectorAll('.menu-item-card__enquire-add').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var name  = btn.getAttribute('data-enquiry-name') || '';
                var price = btn.getAttribute('data-enquiry-price') || '';
                var items = load();
                var exists = items.some(function(it) { return it.name === name; });
                if (exists) {
                    items = items.filter(function(it) { return it.name !== name; });
                } else {
                    items.push({ name: name, price: price });
                }
                save(items);
                syncButtons();
                render();
            });
        });

        document.getElementById('enquiry-view-btn').addEventListener('click', function() {
            panel.style.display = panel.style.display === 'none' ? 'block' : 'none';
        });
        document.getElementById('enquiry-close-btn').addEventListener('click', function() {
            panel.style.display = 'none';
        });
        document.getElementById('enquiry-clear-btn').addEventListener('click', function() {
            save([]);
            syncButtons();
            render();
            panel.style.display = 'none';
        });

        syncButtons();
        render();
    })();
</script>

</body>
</html>