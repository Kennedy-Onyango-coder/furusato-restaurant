<?php
/**
 * Our Story Page - Furusato Japanese Restaurant
 * CONVERTED FROM HTML to PHP with full functionality
 * INTEGRATED with main style.css - removed furusato.css dependency
 * UPDATED: Working hours changed to 9pm, SEO improvements
 * UPDATED: Clean URLs (no .php extension)
 */
require_once __DIR__ . '/includes/functions.php';

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

// Get file modification times for cache busting
$styleVersion = get_asset_version('assets/css/style.css');
$animationsVersion = get_asset_version('assets/css/animations.css');
$mainJsVersion = get_asset_version('assets/js/main.js');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title>Our Story | Furusato Japanese Restaurant — Nairobi | Since 2001</title>
<meta name="description" content="Furusato opened 1 May 2001 near Sarit Centre. Nairobi's first dedicated Japanese dining experience, now with 31 chefs, 19 servers. Open daily 12pm-9pm.">

<!-- SEO Meta Tags -->
<meta name="keywords" content="furusato story, japanese restaurant nairobi history, furusato 2001, nairobi japanese dining, authentic japanese cuisine kenya, furusato since 2001">
<meta name="author" content="Furusato Japanese Restaurant">
<meta name="robots" content="index, follow">
<meta name="language" content="English">
<meta name="geo.region" content="KE-30">
<meta name="geo.placename" content="Nairobi">
<meta name="geo.position" content="-1.2624;36.8049">
<meta name="ICBM" content="-1.2624, 36.8049">

<!-- Canonical URL - Clean URL without .php -->
<link rel="canonical" href="https://furusatorestaurant.com/our-story">

<!-- Open Graph / Facebook -->
<meta property="og:type" content="website">
<meta property="og:url" content="https://furusatorestaurant.com/our-story">
<meta property="og:title" content="Our Story | Furusato Japanese Restaurant — Nairobi Since 2001">
<meta property="og:description" content="Furusato opened 1 May 2001 near Sarit Centre. Nairobi's first dedicated Japanese dining experience. Open daily 12pm-9pm.">
<meta property="og:image" content="https://furusatorestaurant.com/assets/images/og-image.jpg">
<meta property="og:site_name" content="Furusato Japanese Restaurant">

<!-- Twitter -->
<meta property="twitter:card" content="summary_large_image">
<meta property="twitter:url" content="https://furusatorestaurant.com/our-story">
<meta property="twitter:title" content="Our Story | Furusato Japanese Restaurant — Nairobi">
<meta property="twitter:description" content="Furusato opened 1 May 2001. Nairobi's first dedicated Japanese dining experience. Open daily 12pm-9pm.">
<meta property="twitter:image" content="https://furusatorestaurant.com/assets/images/og-image.jpg">

<!-- JSON-LD Schema for Restaurant -->
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "Restaurant",
  "name": "Furusato Japanese Restaurant",
  "image": "https://furusatorestaurant.com/assets/images/furusato-logo.png",
  "description": "Authentic Japanese cuisine in Nairobi since 2001. Specializing in sushi, sashimi, teppanyaki.",
  "address": {
    "@type": "PostalAddress",
    "streetAddress": "Ring Road Parklands, Westlands",
    "addressLocality": "Nairobi",
    "addressRegion": "Nairobi",
    "addressCountry": "KE"
  },
  "geo": {
    "@type": "GeoCoordinates",
    "latitude": "-1.2624",
    "longitude": "36.8049"
  },
  "url": "https://furusatorestaurant.com/",
  "telephone": "+254722488706",
  "openingHours": "Mo-Su 12:00-21:00",
  "priceRange": "KES 500-5000",
  "servesCuisine": ["Japanese", "Sushi", "Korean"],
  "acceptsReservations": "Yes",
  "foundingDate": "2001-05-01"
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

<!-- Main Stylesheet -->
<link rel="stylesheet" href="/assets/css/style.css?v=<?= $styleVersion ?>">
<link rel="stylesheet" href="/assets/css/animations.css?v=<?= $animationsVersion ?>">

<!-- Our Story Page Custom Styles -->
<style>
    /* ============================================================
       OUR STORY PAGE CUSTOM STYLES
       ============================================================ */
    
    /* Page Hero */
    .page-hero {
        position: relative;
        width: 100%;
        height: 100vh;
        min-height: 500px;
        max-height: 700px;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
    }
    
    .page-hero-bg {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-size: cover;
        background-position: center;
        background-repeat: no-repeat;
    }
    
    .page-hero-overlay {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: linear-gradient(135deg, rgba(13, 27, 42, 0.75) 0%, rgba(13, 27, 42, 0.55) 100%);
        z-index: 2;
    }
    
    .page-hero-content {
        position: relative;
        z-index: 3;
        text-align: center;
        color: #fff;
        padding: 0 24px;
        max-width: 800px;
    }
    
    .page-hero-eyebrow {
        font-family: var(--font-body);
        font-size: 0.75rem;
        font-weight: 700;
        letter-spacing: 0.2em;
        text-transform: uppercase;
        color: var(--color-gold);
        margin-bottom: 20px;
    }
    
    .page-hero-h1 {
        font-family: var(--font-heading);
        font-size: clamp(2.5rem, 6vw, 5rem);
        font-weight: 700;
        color: #fff;
        margin-bottom: 16px;
    }
    
    .page-hero-h1 em {
        color: var(--color-crimson);
        font-style: italic;
    }
    
    .page-hero-sub {
        font-size: clamp(1rem, 2vw, 1.25rem);
        color: rgba(255,255,255,0.9);
    }
    
    /* Two Column Layout */
    .two-col {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 60px;
        align-items: center;
    }
    
    .img-frame {
        border-radius: var(--border-radius-lg);
        overflow: hidden;
        box-shadow: var(--shadow-lg);
    }
    
    .img-frame img {
        width: 100%;
        height: auto;
        display: block;
    }
    
    /* Stat Bar */
    .section-sm {
        padding: 48px 0;
    }
    
    .bg-cream2 {
        background-color: #f5f2ec;
    }
    
    .stat-bar {
        display: flex;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 32px;
        text-align: center;
    }
    
    .stat-cell {
        flex: 1;
        min-width: 120px;
    }
    
    .stat-num {
        font-family: var(--font-heading);
        font-size: clamp(2rem, 4vw, 3rem);
        font-weight: 700;
        color: var(--color-crimson);
        display: block;
        line-height: 1.2;
    }
    
    .stat-num em {
        font-size: 1.8rem;
    }
    
    .stat-lbl {
        font-size: 0.8rem;
        font-weight: 600;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: var(--color-navy);
    }
    
    /* Gallery Grid */
    .gallery-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 20px;
        margin-top: 48px;
    }
    
    .gallery-item {
        position: relative;
        overflow: hidden;
        border-radius: var(--border-radius-lg);
    }
    
    .gallery-item.gal-wide {
        grid-column: span 2;
    }
    
    .gallery-item img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform var(--transition-slow);
    }
    
    .gallery-item:hover img {
        transform: scale(1.05);
    }
    
    /* Values Grid - NO EMOJIS */
    .val-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 32px;
        margin-top: 48px;
    }
    
    .val-card {
        background: #fff;
        border-radius: var(--border-radius-lg);
        padding: 32px 28px;
        text-align: center;
        box-shadow: var(--shadow-sm);
        transition: all var(--transition-base);
        border-top: 4px solid var(--color-gold);
    }
    
    .val-card:hover {
        transform: translateY(-6px);
        box-shadow: var(--shadow-lg);
    }
    
    .val-icon {
        display: block;
        margin-bottom: 20px;
    }
    
    .val-icon svg {
        width: 48px;
        height: 48px;
        color: var(--color-gold);
    }
    
    .val-title {
        font-family: var(--font-heading);
        font-size: 1.35rem;
        font-weight: 700;
        color: var(--color-navy);
        margin-bottom: 12px;
    }
    
    .val-text {
        font-size: 0.9rem;
        line-height: 1.7;
        color: var(--color-charcoal);
    }
    
    /* Delivery Bar */
    .delivery-bar {
        background: var(--color-navy);
        padding: 60px 0;
        color: #fff;
    }
    
    .delivery-section {
        text-align: center;
    }
    
    .delivery-title {
        font-family: var(--font-heading);
        font-size: 2.5rem;
        font-weight: 700;
        color: var(--color-gold);
        margin-bottom: 24px;
    }
    
    .delivery-text {
        font-size: 1rem;
        line-height: 1.7;
        max-width: 800px;
        margin: 0 auto 20px;
        color: rgba(255,255,255,0.85);
    }
    
    .delivery-subtitle {
        font-size: 0.9rem;
        color: rgba(255,255,255,0.6);
        margin-bottom: 24px;
    }
    
    .delivery-logos {
        display: flex;
        gap: 32px;
        justify-content: center;
        flex-wrap: wrap;
    }
    
    .delivery-logo-link {
        display: inline-block;
        transition: transform var(--transition-base);
    }
    
    .delivery-logo-link:hover {
        transform: translateY(-4px);
    }
    
    .logo-img {
        height: 50px;
        width: auto;
    }
    
    /* Button Outline White */
    .btn-outline-white {
        background-color: transparent;
        color: #fff;
        border-color: #fff;
    }
    
    .btn-outline-white:hover {
        background-color: #fff;
        color: var(--color-navy);
        transform: translateY(-2px);
    }
    
    /* Responsive */
    @media (max-width: 992px) {
        .two-col {
            grid-template-columns: 1fr;
            gap: 40px;
        }
        .gallery-grid {
            grid-template-columns: repeat(2, 1fr);
        }
        .val-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }
    
    @media (max-width: 768px) {
        .stat-bar {
            flex-direction: column;
            gap: 24px;
        }
        .gallery-grid {
            grid-template-columns: 1fr;
        }
        .gallery-item.gal-wide {
            grid-column: span 1;
        }
        .val-grid {
            grid-template-columns: 1fr;
        }
        .delivery-text {
            font-size: 0.9rem;
            padding: 0 16px;
        }
        .two-col {
            gap: 32px;
        }
    }
    
    @media (max-width: 480px) {
        .page-hero-h1 {
            font-size: 2.2rem;
        }
        .val-card {
            padding: 24px 20px;
        }
    }
</style>
</head>
<body>

<!-- ══════ NAVBAR ══════ -->
<nav class="navbar" role="navigation" aria-label="Main navigation">
    <div class="navbar-inner">
        <a href="/" class="navbar-logo" aria-label="Furusato Home">
            <img src="/assets/images/furusato-logo.png" alt="Furusato Japanese Restaurant" width="48" height="48" class="navbar-logo-img">
            <span class="navbar-logo-text">Furusato <span>Restaurant</span></span>
        </a>
        <div class="navbar-nav">
            <a href="/">Home</a>
            <div class="nav-dropdown">
                <a href="/menu" class="nav-dropdown-trigger">Menu
                    <svg width="10" height="6" viewBox="0 0 10 6" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M1 1l4 4 4-4"/></svg>
                </a>
                <div class="nav-dropdown-menu" id="nav-menu-categories"></div>
            </div>
            <a href="/our-story" class="active">Our Story</a>
            <a href="/contact">Contact</a>
        </div>
        <div class="navbar-reserve">
            <a href="/contact#reservation" class="btn">Reserve a Table</a>
        </div>
        <button class="navbar-hamburger" aria-label="Toggle mobile menu" aria-expanded="false">
            <span></span><span></span><span></span>
        </button>
    </div>
</nav>

<div class="mobile-menu" role="dialog" aria-label="Mobile navigation">
    <a href="/">Home</a>
    <a href="/menu">Menu</a>
    <div class="mobile-menu-categories" id="mobile-menu-categories"></div>
    <a href="/our-story" class="active">Our Story</a>
    <a href="/contact">Contact</a>
    <a href="/contact#reservation" class="btn btn-primary btn-lg">Reserve a Table</a>
</div>


<!-- ══════ PAGE HERO ══════ -->
<section class="page-hero">
  <div class="page-hero-bg" style="background-image:url('/assets/images/hero/team.jpg');"></div>
  <div class="page-hero-overlay"></div>
  <div class="page-hero-content">
    <div class="page-hero-eyebrow">Est. 1 May 2001 · Nairobi, Kenya | Open Daily 12pm-9pm</div>
    <h1 class="page-hero-h1">Our <em>Story</em></h1>
    <p class="page-hero-sub">Two decades of warmth, craft &amp; authentic flavour | Open 12pm-9pm</p>
  </div>
</section>


<!-- ══════ SECTION 1 — The Beginning ══════ -->
<section class="philosophy-section scroll-reveal" style="padding:96px 0; background:#fff;">
  <div class="container">
    <div class="two-col">

      <div style="display:flex; flex-direction:column; gap:24px;">
        <div class="section-label" style="color:var(--color-crimson);">How It All Began</div>
        <h2 class="heading" style="font-family:var(--font-heading); font-size:clamp(1.8rem,4vw,2.5rem); color:var(--color-navy);">A <em style="color:var(--color-crimson); font-style:italic;">Hometown</em><br>Born in Nairobi</h2>
        <p class="body-copy" style="color:var(--color-charcoal); line-height:1.7;">On <strong>1 May 2001</strong>, we opened Furusato near Sarit Centre — a name chosen for its meaning: <strong>"hometown."</strong> Our dream was to create dishes that would transport our guests to a place of comfort and nostalgia, like their hometown.</p>
        <p class="body-copy" style="color:var(--color-charcoal); line-height:1.7;">From the start, Furusato was a place of firsts. Our kitchen was led by <strong>Nairobi's first female sushi chef</strong> — a testament to our daring spirit. For decades, her journey, rich with experience, has inspired our recipes, each one a living story. These culinary traditions continue to evolve, shaped by customer feedback.</p>
        <blockquote style="font-family:var(--font-heading); font-size:21px; font-style:italic; color:var(--color-teal); border-left:4px solid var(--color-crimson); padding-left:20px; line-height:1.55; margin:4px 0;">"Our dream was to create dishes that transport our guests to a place of comfort — like their hometown."</blockquote>
        <div style="display:flex; gap:12px; flex-wrap:wrap;">
          <a class="btn btn-primary" href="/menu">🍣 Explore Our Menu</a>
          <a class="btn btn-outline-navy" href="/contact#reservation">Reserve a Table</a>
        </div>
      </div>

      <div>
        <div class="img-frame">
          <img src="/assets/images/2001.jpeg"
               alt="Furusato founders and early team" loading="lazy">
        </div>
      </div>

    </div>
  </div>
</section>


<!-- ══════ STAT BAR ══════ -->
<div class="section-sm bg-cream2 scroll-reveal" style="padding:48px 0; background-color:#f5f2ec;">
  <div class="container">
    <div class="stat-bar">
      <div class="stat-cell">
        <span class="stat-num">2001</span>
        <span class="stat-lbl">Year Founded</span>
      </div>
      <div class="stat-cell">
        <span class="stat-num">31</span>
        <span class="stat-lbl">Chefs on Floor</span>
      </div>
      <div class="stat-cell">
        <span class="stat-num">19</span>
        <span class="stat-lbl">Servers</span>
      </div>
      <div class="stat-cell">
        <span class="stat-num">24<em>+</em></span>
        <span class="stat-lbl">Years of Flavour</span>
      </div>
    </div>
  </div>
</div>


<!-- ══════ SECTION 2 — New Home 2007 (dark) ══════ -->
<section style="background:linear-gradient(120deg,var(--color-navy) 0%,#0d1e3d 55%,#1a0505 100%); padding:88px 0; position:relative; overflow:hidden;">
  <div style="position:absolute; inset:0; background:radial-gradient(ellipse at 28% 50%, rgba(43,108,176,0.12), transparent 58%); pointer-events:none;"></div>
  <div class="container" style="position:relative; z-index:1;">
    <div class="two-col">

      <div class="scroll-reveal">
        <div class="img-frame">
          <img src="/assets/images/hero/sushi-bar.jpg"
               alt="Furusato restaurant construction 2007" loading="lazy">
        </div>
      </div>

      <div class="scroll-reveal" style="display:flex; flex-direction:column; gap:22px;">
        <div class="section-label" style="color:var(--color-gold);">2007 — A New Chapter</div>
        <h2 class="heading" style="font-family:var(--font-heading); font-size:clamp(1.8rem,4vw,2.5rem); color:#fff;">How Furusato <em style="color:var(--color-crimson); font-style:italic;">Came to Be</em></h2>
        <p class="body-copy" style="color:rgba(255,255,255,0.85); line-height:1.7;">In 2007, we moved our restaurant to a new home — a place we meticulously designed to be an <strong style="color:#fff;">authentic piece of Japan in Nairobi</strong>. We intentionally placed the sushi bar in the centre, making it the first thing customers see upon entering.</p>
        <p class="body-copy" style="color:rgba(255,255,255,0.85); line-height:1.7;">Over the years, our recipes have evolved, adapting to Kenyan tastes. Though we've come a long way from where we started — we're now up to <strong style="color:#fff;">31 chefs, 19 servers, and 5 facilities &amp; administrative staff</strong> on a normal evening — our focus on excellence has never shifted.</p>
        <blockquote style="font-family:var(--font-heading); font-size:20px; font-style:italic; color:rgba(255,255,255,0.85); border-left:4px solid var(--color-crimson); padding-left:20px; line-height:1.55;">"When you eat with us, you can expect consistent flavours, familiar foods, in a comfortable setting."</blockquote>
      </div>

    </div>
  </div>
</section>


<!-- ══════ CINEMATIC FULL-WIDTH — Restaurant at night ══════ -->
<div style="height:420px; overflow:hidden; position:relative;">
  <img src="/assets/images/interior.png"
       alt="Furusato restaurant at night"
       style="width:100%; height:100%; object-fit:cover; display:block;">
  <div style="position:absolute; inset:0; background:linear-gradient(0deg, rgba(26,26,26,0.72) 0%, rgba(26,26,26,0.1) 55%, transparent 100%);"></div>
  <div style="position:absolute; bottom:40px; left:50%; transform:translateX(-50%); text-align:center; z-index:2; white-space:nowrap;">
    <p style="font-family:var(--font-body); font-size:11px; font-weight:700; letter-spacing:5px; text-transform:uppercase; color:rgba(255,255,255,0.75); margin-bottom:8px;">Ring Road Parklands · Westlands · Nairobi</p>
    <p style="font-family:var(--font-heading); font-size:clamp(24px,4vw,42px); font-weight:700; color:#fff; font-style:italic; letter-spacing:-0.5px;">Nairobi's Japanese home since 2001 | Open 12pm-9pm</p>
  </div>
</div>


<!-- ══════ PHOTO GALLERY ══════ -->
<section class="philosophy-section scroll-reveal" style="padding:96px 0; background:#fff;" id="gallery">
  <div class="container">
    <div class="text-center" style="text-align:center;">
      <div class="section-label" style="color:var(--color-crimson); display:inline-block; margin-bottom:16px;">Through the Years</div>
      <h2 class="heading" style="font-family:var(--font-heading); font-size:clamp(1.8rem,4vw,2.5rem); color:var(--color-navy);">Furusato <em style="color:var(--color-crimson); font-style:italic;">in Pictures</em></h2>
      <p class="body-copy" style="color:var(--color-charcoal); max-width:600px; margin:0 auto;">A glimpse into our restaurant, our kitchen and the memories we've built together over two decades</p>
    </div>

    <div class="gallery-grid">
      <div class="gallery-item gal-wide" style="height:220px;">
        <img src="/assets/images/hero/hero6.jpg" alt="Furusato 2024" loading="lazy">
      </div>
      <div class="gallery-item" style="height:220px;">
        <img src="/assets/images/hero/crafted.jpg" alt="Furusato 2014" loading="lazy">
      </div>
      <div class="gallery-item" style="height:220px;">
        <img src="/assets/images/hero/out-furusato.webp" alt="Furusato dining room" loading="lazy">
      </div>
      <div class="gallery-item" style="height:220px;">
        <img src="/assets/images/hero/crafted1.jpg" alt="Furusato food photography" loading="lazy">
      </div>
      <div class="gallery-item" style="height:220px;">
        <img src="/assets/images/hero/20251119_100750_result.webp" alt="Furusato sushi bar" loading="lazy">
      </div>
      <div class="gallery-item gal-wide" style="height:220px;">
        <img src="/assets/images/hero/20251119_095152_result.webp" alt="Furusato kitchen team" loading="lazy">
      </div>
    </div>
  </div>
</section>


<!-- ══════ VALUES CARDS — NO EMOJIS ══════ -->
<section class="philosophy-section scroll-reveal" style="padding:96px 0; background:#f5f2ec;" id="values">
  <div class="container">
    <div class="text-center" style="text-align:center;">
      <div class="section-label" style="color:var(--color-crimson); display:inline-block; margin-bottom:16px;">What We Stand For</div>
      <h2 class="heading" style="font-family:var(--font-heading); font-size:clamp(1.8rem,4vw,2.5rem); color:var(--color-navy);">Our <em style="color:var(--color-crimson); font-style:italic;">Values</em></h2>
      <p class="body-copy" style="color:var(--color-charcoal); max-width:600px; margin:0 auto;">The principles that guide every dish, every greeting and every moment at Furusato</p>
    </div>
    <div class="val-grid">
      <div class="val-card">
        <div class="val-icon">
          <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
            <circle cx="12" cy="12" r="10"/>
            <path d="M12 6v6l4 2"/>
          </svg>
        </div>
        <div class="val-title">Authentic Tradition</div>
        <div class="val-text">Rooted in Japanese culinary heritage — every recipe honours centuries-old techniques passed through generations of master chefs.</div>
      </div>
      <div class="val-card">
        <div class="val-icon">
          <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
            <path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10 10-4.5 10-10S17.5 2 12 2z"/>
            <path d="M12 8v8"/>
            <path d="M8 12h8"/>
          </svg>
        </div>
        <div class="val-title">Always Fresh</div>
        <div class="val-text">Only the freshest ingredients find their way to your plate, every single day without exception. No shortcuts, ever.</div>
      </div>
      <div class="val-card">
        <div class="val-icon">
          <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
            <circle cx="9" cy="7" r="4"/>
            <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
            <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
          </svg>
        </div>
        <div class="val-title">Community First</div>
        <div class="val-text">Rooted in Nairobi for over 20 years — our team of 31 chefs and 19 servers are the beating heart of our kitchen.</div>
      </div>
      <div class="val-card">
        <div class="val-icon">
          <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
            <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
          </svg>
        </div>
        <div class="val-title">A Seat for Everyone</div>
        <div class="val-text">We welcome every family to a warm atmosphere where precious moments are shared over beautiful, consistent food.</div>
      </div>
      <div class="val-card">
        <div class="val-icon">
          <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
            <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
            <polyline points="9 22 9 12 15 12 15 22"/>
          </svg>
        </div>
        <div class="val-title">Japanese &amp; Korean</div>
        <div class="val-text">Sushi, sashimi, teppanyaki, bibimbap — two great East Asian cuisines celebrated under one roof in Westlands.</div>
      </div>
      <div class="val-card">
        <div class="val-icon">
          <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
            <path d="M12 2L15 8.5L22 9.5L17 14L18.5 21L12 17.5L5.5 21L7 14L2 9.5L9 8.5L12 2z"/>
          </svg>
        </div>
        <div class="val-title">Nairobi Spirit</div>
        <div class="val-text">Japanese flavours, Nairobi spirit — adapting our recipes to Kenyan tastes while honouring every original tradition.</div>
      </div>
    </div>
  </div>
</section>


<!-- ══════ CTA STRIP ══════ -->
<section style="background:linear-gradient(135deg,var(--color-teal) 0%,#0f2a5c 55%,#1a0808 100%); padding:88px 0; text-align:center;">
  <div class="container">
    <div class="scroll-reveal">
      <div class="section-label" style="color:rgba(255,200,80,0.9); display:inline-block; margin-bottom:14px;">Come Experience It</div>
      <h2 class="heading" style="font-family:var(--font-heading); font-size:clamp(1.8rem,4vw,2.5rem); color:#fff; margin-bottom:16px;">Ready to Make <em style="color:var(--color-crimson); font-style:italic;">Memories</em>?</h2>
      <p class="body-copy" style="color:rgba(255,255,255,0.9); max-width:480px; margin:0 auto 36px;">Open every day from 12&nbsp;pm to 9&nbsp;pm. Walk-ins are always welcome — groups of 8 or more please reserve ahead.</p>
      <div style="display:flex; gap:14px; flex-wrap:wrap; justify-content:center;">
        <a class="btn btn-primary" href="/contact#reservation">📞 Reserve a Table</a>
        <a class="btn btn-outline-white" href="/menu">🍜 View Our Menu</a>
      </div>
    </div>
  </div>
</section>


<!-- ══════ DELIVERY BAR ══════ -->
<div class="delivery-bar">
  <div class="container">
    <div class="delivery-section">
      <h2 class="delivery-title">Delivery</h2>
      <p class="delivery-text">Furusato delivers for free for orders over 3000ksh. For orders under 3000ksh a delivery fee applies depends on distance and ranges from 300ksh to 500ksh. <strong>Please call or WhatsApp us at 0722 488 706</strong> to place an order or for additional inquiries!</p>
      <p class="delivery-subtitle">We are also available on the below delivery apps:</p>
      
      <div class="delivery-logos">
        <a class="delivery-logo-link" href="https://www.ubereats.com/ke/store/furusato-japanese-restaurant/FGTL3T6JQRqcydZR-3jmeg" target="_blank" rel="noopener" title="Order on Uber Eats">
          <img src="/assets/images/ubereats.png" alt="Uber Eats" class="logo-img" onerror="this.src='/assets/images/furusato-logo.png'">
        </a>
        <a class="delivery-logo-link" href="https://glovoapp.com/ke/en/nairobi/furusato-japanese-restaurant-nbo/" target="_blank" rel="noopener" title="Order on Glovo">
          <img src="/assets/images/glovo.png" alt="Glovo" class="logo-img" onerror="this.src='/assets/images/furusato-logo.png'">
        </a>
      </div>
    </div>
  </div>
</div>


<!-- ============================================================
   FOOTER - Unified Design (Gold Links, Contact Cards Style)
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

<!-- WhatsApp Floating Button -->
<a href="https://wa.me/254722488706?text=Hello%20Furusato!%20I'd%20like%20to%20learn%20about%20your%20story" class="whatsapp-float" target="_blank" rel="noopener noreferrer" aria-label="Chat on WhatsApp">
    <svg width="28" height="28" viewBox="0 0 24 24" fill="white">
        <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.473-.148-.673.149-.2.297-.768.967-.94 1.164-.173.199-.347.223-.644.075-.297-.149-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/>
        <path d="M12 0C5.373 0 0 5.373 0 12c0 2.625.846 5.059 2.284 7.034L.789 23.492a.468.468 0 0 0 .575.594l4.591-1.456A11.95 11.95 0 0 0 12 24c6.627 0 12-5.373 12-12S18.627 0 12 0z"/>
    </svg>
</a>

<script src="/assets/js/main.js?v=<?= $mainJsVersion ?>"></script>

<script>
(function() {
    // Scroll reveal for elements with .scroll-reveal class
    var revealElements = document.querySelectorAll('.scroll-reveal');
    if (revealElements.length && window.IntersectionObserver) {
        var observer = new IntersectionObserver(function(entries) {
            entries.forEach(function(entry) {
                if (entry.isIntersecting) {
                    entry.target.classList.add('revealed');
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.15, rootMargin: '0px 0px -40px 0px' });
        revealElements.forEach(function(el) { observer.observe(el); });
    } else if (revealElements.length) {
        revealElements.forEach(function(el) { el.classList.add('revealed'); });
    }
    
    // Mobile menu handler
    var hamburger = document.querySelector('.navbar-hamburger');
    var mobileMenu = document.querySelector('.mobile-menu');
    if (hamburger && mobileMenu) {
        hamburger.addEventListener('click', function() {
            hamburger.classList.toggle('active');
            mobileMenu.classList.toggle('open');
            document.body.style.overflow = mobileMenu.classList.contains('open') ? 'hidden' : '';
        });
        
        mobileMenu.querySelectorAll('a').forEach(function(link) {
            link.addEventListener('click', function() {
                hamburger.classList.remove('active');
                mobileMenu.classList.remove('open');
                document.body.style.overflow = '';
            });
        });
    }
    
    // Stats counter animation
    var statNumbers = document.querySelectorAll('.stat-num');
    if (statNumbers.length && window.IntersectionObserver) {
        var animated = false;
        var statsObserver = new IntersectionObserver(function(entries) {
            entries.forEach(function(entry) {
                if (entry.isIntersecting && !animated) {
                    animated = true;
                    statNumbers.forEach(function(el) {
                        var target = el.innerText;
                        var numMatch = target.match(/\d+/);
                        if (numMatch) {
                            var finalNum = parseInt(numMatch[0]);
                            var current = 0;
                            var duration = 2000;
                            var step = Math.ceil(finalNum / (duration / 16));
                            var timer = setInterval(function() {
                                current += step;
                                if (current >= finalNum) {
                                    el.innerText = finalNum + (target.includes('+') ? '+' : '');
                                    clearInterval(timer);
                                } else {
                                    el.innerText = current + (target.includes('+') ? '+' : '');
                                }
                            }, 16);
                        }
                    });
                    statsObserver.disconnect();
                }
            });
        }, { threshold: 0.3 });
        
        var statBar = document.querySelector('.stat-bar');
        if (statBar) statsObserver.observe(statBar);
    }
})();
</script>
</body>
</html>