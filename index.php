<?php
require_once __DIR__ . '/includes/functions.php';

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

$styleVersion    = get_asset_version('assets/css/style.css');
$animationsVersion = get_asset_version('assets/css/animations.css');
$mainJsVersion   = get_asset_version('assets/js/main.js');
$heroJsVersion   = get_asset_version('assets/js/hero.js');

// Get settings for dynamic content
$settings = getJsonData('settings');
$restaurantName = $settings['name'] ?? 'Furusato Japanese Restaurant';
$restaurantPhone = $settings['phone'] ?? '+254722488706';
// Single source of truth: Admin → Settings → WhatsApp
$restaurantWhatsapp = get_whatsapp_number();
$restaurantEmail = $settings['email'] ?? 'furusatorestaurant@gmail.com';
$restaurantAddress = $settings['address'] ?? 'Ring Road Parklands, Westlands, Nairobi, Kenya';

// Pre-filled WhatsApp messages (enquiry/communication channel — not ordering)
$waGeneralMsg = "Hello Furusato Japanese Restaurant,\n\nI have an enquiry.\n\nThank you.";
$waReservationMsg = "Hello Furusato Japanese Restaurant,\n\nI would like to make a reservation.\n\nThank you.";
$waDeliveryMsg = "Hello Furusato Japanese Restaurant,\n\nI would like to enquire about delivery.\n\nThank you.";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="description" content="Furusato Japanese Restaurant – Nairobi's premier destination for authentic Japanese cuisine since 2001. Open daily 12pm–9pm in Westlands. Sushi, sashimi, teppanyaki, and more.">
    <meta name="keywords" content="Furusato, Japanese restaurant Nairobi, authentic Japanese cuisine, sushi Nairobi, sashimi, teppanyaki, Westlands restaurant, best Japanese food Kenya">
    <meta name="author" content="Furusato Japanese Restaurant">
    <meta name="theme-color" content="#0d1b2a">
    <meta name="google-site-verification" content="Q8G11unFgqqLPmJNn1WHbsXa8SFWNmv7vh6AXVF5j30">
    <meta name="geo.region" content="KE-30">
    <meta name="geo.placename" content="Nairobi">
    <meta name="geo.position" content="-1.2624;36.8049">
    <meta name="ICBM" content="-1.2624, 36.8049">
    <link rel="canonical" href="https://furusatorestaurant.com/">
    <title>Furusato Japanese Restaurant — Taste That Carries You Home | Nairobi</title>

    <!-- Open Graph -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://furusatorestaurant.com/">
    <meta property="og:title" content="Furusato Japanese Restaurant – Authentic Japanese Cuisine in Nairobi">
    <meta property="og:description" content="Experience authentic Japanese cuisine at Furusato in Westlands, Nairobi. Open daily 12pm–9pm. Sushi, sashimi, teppanyaki since 2001.">
    <meta property="og:image" content="https://furusatorestaurant.com/assets/images/og-image.jpg">
    <meta property="og:locale" content="en_KE">
    <meta property="og:site_name" content="Furusato Japanese Restaurant">
    
    <!-- Twitter -->
    <meta property="twitter:card" content="summary_large_image">
    <meta property="twitter:url" content="https://furusatorestaurant.com/">
    <meta property="twitter:title" content="Furusato Japanese Restaurant – Nairobi">
    <meta property="twitter:description" content="Authentic Japanese cuisine in Westlands, Nairobi since 2001. Open daily 12pm–9pm.">
    <meta property="twitter:image" content="https://furusatorestaurant.com/assets/images/og-image.jpg">

    <!-- Schema Markup for Rich Snippets -->
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "Restaurant",
      "name": "<?= htmlspecialchars($restaurantName) ?>",
      "image": "https://furusatorestaurant.com/assets/images/furusato-logo.png",
      "logo": "https://furusatorestaurant.com/assets/images/furusato-logo.png",
      "description": "Authentic Japanese cuisine in Nairobi since 2001. Specializing in sushi, sashimi, teppanyaki, and traditional Japanese dishes.",
      "address": {
        "@type": "PostalAddress",
        "streetAddress": "Ring Road Parklands, Westlands",
        "addressLocality": "Nairobi",
        "addressRegion": "Nairobi County",
        "addressCountry": "KE"
      },
      "geo": {
        "@type": "GeoCoordinates",
        "latitude": "-1.2624",
        "longitude": "36.8049"
      },
      "url": "https://furusatorestaurant.com/",
      "telephone": "<?= htmlspecialchars($restaurantPhone) ?>",
      "email": "<?= htmlspecialchars($restaurantEmail) ?>",
      "openingHours": ["Mo-Su 12:00-21:00"],
      "openingHoursSpecification": [
        {
          "@type": "OpeningHoursSpecification",
          "dayOfWeek": ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday", "Sunday"],
          "opens": "12:00",
          "closes": "21:00"
        }
      ],
      "priceRange": "KES 500-5000",
      "servesCuisine": ["Japanese", "Sushi", "Korean", "Teppanyaki", "Ramen", "Tempura"],
      "acceptsReservations": "Yes",
      "reservationUrl": "https://furusatorestaurant.com/contact.php#reservation",
      "hasMenu": "https://furusatorestaurant.com/menu.php",
      "hasMap": "https://maps.google.com/?q=Ring+Road+Parklands+Westlands+Nairobi",
      "foundingDate": "2001-05-01",
      "numberOfEmployees": "50",
      "sameAs": [
        "https://www.facebook.com/FurusatoNairobi",
        "https://www.instagram.com/furusato_japanese_restaurant"
      ],
      "paymentAccepted": ["Cash", "Credit Card", "M-Pesa", "Visa", "Mastercard"]
    }
    </script>

    <!-- Breadcrumb Schema -->
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "BreadcrumbList",
      "itemListElement": [
        {
          "@type": "ListItem",
          "position": 1,
          "name": "Home",
          "item": "https://furusatorestaurant.com/"
        }
      ]
    }
    </script>

    <!-- Local Business Schema -->
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "LocalBusiness",
      "name": "<?= htmlspecialchars($restaurantName) ?>",
      "image": "https://furusatorestaurant.com/assets/images/hero/out-furusato.webp",
      "telephone": "<?= htmlspecialchars($restaurantPhone) ?>",
      "address": {
        "@type": "PostalAddress",
        "streetAddress": "Ring Road Parklands, Westlands",
        "addressLocality": "Nairobi",
        "addressCountry": "KE"
      },
      "openingHours": "Mo-Su 12:00-21:00",
      "priceRange": "KES 500-5000"
    }
    </script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="dns-prefetch" href="https://cdnjs.cloudflare.com">
    <link rel="preload" as="image" href="/assets/images/hero/out-furusato.webp" fetchpriority="high">
    
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,600&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,300&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="manifest" href="/site.webmanifest">
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" sizes="32x32" href="/assets/images/favicon/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/assets/images/favicon/favicon-16x16.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/assets/images/favicon/apple-touch-icon.png">
    <link rel="shortcut icon" href="/favicon.ico" type="image/x-icon">
    <meta name="msapplication-TileColor" content="#0d1b2a">

    <link rel="stylesheet" href="<?= asset_url('assets/css/style.css') . '?v=' . $styleVersion ?>">
    <link rel="stylesheet" href="<?= asset_url('assets/css/animations.css') . '?v=' . $animationsVersion ?>">

    <style>
        /* ============================================================
           SHARED DESIGN TOKENS — mirrors contact.php
           ============================================================ */
        :root {
            --ink:           #0e0c0a;
            --ink-80:        rgba(14,12,10,0.8);
            --ink-60:        rgba(14,12,10,0.6);
            --ink-40:        rgba(14,12,10,0.4);
            --ink-10:        rgba(14,12,10,0.07);
            --paper:         #faf8f4;
            --paper-dark:    #f0ece4;
            --gold:          #c9a03d;
            --gold-light:    #e8d08a;
            --gold-dark:     #9a7520;
            --gold-glow:     rgba(201,160,61,0.18);
            --crimson:       #b5271f;
            --navy:          #0d1b2a;
            --navy-mid:      #162336;
            --white:         #ffffff;
            --shadow-soft:   0 2px 20px rgba(14,12,10,0.06);
            --shadow-med:    0 8px 40px rgba(14,12,10,0.10);
            --shadow-hard:   0 20px 70px rgba(14,12,10,0.16);
            --gold-gradient: linear-gradient(135deg,#9a7520 0%,#c9a03d 40%,#e8d08a 60%,#c9a03d 80%,#9a7520 100%);
            --r-sm: 6px; --r-md: 14px; --r-lg: 24px; --r-xl: 40px;
        }

        *, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }
        html { scroll-behavior: smooth; }

        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--paper);
            color: var(--ink);
            overflow-x: hidden;
        }

        /* ── Utility ── */
        .container { max-width:1280px; margin:0 auto; padding:0 60px; }
        @media(max-width:1100px){.container{padding:0 40px;}}
        @media(max-width:640px){.container{padding:0 20px;}}

        .label-row {
            display:flex; align-items:center; gap:14px; margin-bottom:14px;
        }
        .label-line  { width:30px; height:1px; background:var(--gold); flex-shrink:0; }
        .label-text  {
            font-size:0.68rem; font-weight:600; letter-spacing:3px;
            text-transform:uppercase; color:var(--gold);
        }
        .section-heading {
            font-family:'Cormorant Garamond',serif;
            font-size:clamp(2.2rem,4.5vw,3.2rem);
            font-weight:400; color:var(--ink); line-height:1.1;
        }
        .section-heading em { font-style:italic; color:var(--gold-dark); }
        .section-heading-white { color:var(--white); }
        .section-heading-white em { color:var(--gold-light); }

        /* Reveal animation */
        .reveal { opacity:0; transform:translateY(28px); transition:opacity .7s ease,transform .7s ease; }
        .reveal.in-view { opacity:1; transform:translateY(0); }
        .reveal-left  { opacity:0; transform:translateX(-40px); transition:opacity .8s ease,transform .8s ease; }
        .reveal-left.in-view  { opacity:1; transform:translateX(0); }
        .reveal-right { opacity:0; transform:translateX(40px); transition:opacity .8s ease,transform .8s ease; }
        .reveal-right.in-view { opacity:1; transform:translateX(0); }
        .delay-1{transition-delay:.1s} .delay-2{transition-delay:.2s}
        .delay-3{transition-delay:.3s} .delay-4{transition-delay:.4s}
        .delay-5{transition-delay:.5s}

        /* ============================================================
           HERO — SLIDESHOW
           ============================================================ */
        .hero {
            position:relative;
            height:100vh;
            min-height:600px;
            overflow:hidden;
            background:var(--navy);
        }

        .hero-slide {
            position:absolute; inset:0;
            opacity:0; transition:opacity 1.2s ease;
            pointer-events:none;
        }
        .hero-slide.active { opacity:1; pointer-events:auto; }

        .hero-slide-bg {
            position:absolute; inset:0;
            background-size:cover; background-position:center;
            transition:transform 8s ease;
            transform:scale(1.06);
        }
        .hero-slide.active .hero-slide-bg { transform:scale(1); }

        .hero-slide-overlay {
            position:absolute; inset:0;
            background:linear-gradient(
                120deg,
                rgba(13,27,42,0.82) 0%,
                rgba(13,27,42,0.50) 55%,
                rgba(13,27,42,0.15) 100%
            );
        }

        /* Decorative brushstroke lines */
        .hero-brushlines {
            position:absolute; right:0; top:0;
            width:40%; height:100%; overflow:hidden; pointer-events:none;
        }
        .hero-brushline {
            position:absolute; top:0; width:1px; height:100%;
            background:linear-gradient(180deg,transparent,rgba(201,160,61,0.18),transparent);
        }
        .hero-brushline:nth-child(1){left:10%;height:70%;top:15%;animation:bFloat 6s ease-in-out infinite;}
        .hero-brushline:nth-child(2){left:30%;height:85%;top:5%; animation:bFloat 8s ease-in-out infinite 1s;}
        .hero-brushline:nth-child(3){left:55%;height:60%;top:20%;animation:bFloat 7s ease-in-out infinite 2s;}
        .hero-brushline:nth-child(4){left:75%;height:90%;top:0%; animation:bFloat 9s ease-in-out infinite .5s;}
        .hero-brushline:nth-child(5){left:90%;height:50%;top:30%;animation:bFloat 5s ease-in-out infinite 3s;}
        @keyframes bFloat{0%,100%{opacity:1;}50%{opacity:.4;}}

        /* Ripple rings */
        .hero-ripple {
            position:absolute; border-radius:50%;
            border:1px solid rgba(201,160,61,0.08);
            animation:hRipple 10s ease-out infinite;
            pointer-events:none;
        }
        .hero-ripple:nth-child(1){width:350px;height:350px;bottom:10%;right:8%;animation-delay:0s;}
        .hero-ripple:nth-child(2){width:560px;height:560px;bottom:0;right:2%;animation-delay:2s;animation-duration:13s;}
        .hero-ripple:nth-child(3){width:760px;height:760px;bottom:-10%;right:-5%;animation-delay:4s;animation-duration:16s;}
        @keyframes hRipple{0%{transform:scale(.8);opacity:.6;}100%{transform:scale(1.2);opacity:0;}}

        .hero-inner {
            position:relative; z-index:3;
            height:100%; display:flex; align-items:center;
        }

        .hero-content {
            max-width:660px;
            animation:hFadeUp .9s ease both;
        }
        @keyframes hFadeUp{from{opacity:0;transform:translateY(32px);}to{opacity:1;transform:translateY(0);}}

        .hero-eyebrow {
            display:inline-flex; align-items:center; gap:14px;
            margin-bottom:28px;
        }
        .hero-eyebrow-line { width:36px; height:1px; background:var(--gold); }
        .hero-eyebrow-text {
            font-size:0.7rem; font-weight:600; letter-spacing:3px;
            text-transform:uppercase; color:var(--gold);
        }

        .hero-h1 {
            font-family:'Cormorant Garamond',serif;
            font-size:clamp(3.4rem,8vw,6.8rem);
            font-weight:300; color:var(--white);
            line-height:1.0; margin-bottom:10px;
            letter-spacing:-0.02em;
        }
        .hero-h1 em {
            font-style:italic;
            background:var(--gold-gradient);
            -webkit-background-clip:text; -webkit-text-fill-color:transparent;
            background-clip:text; background-size:200% 100%;
            animation:goldShimmer 4s ease-in-out infinite 1s;
        }
        @keyframes goldShimmer{0%,100%{background-position:0% 50%;}50%{background-position:100% 50%;}}

        .hero-sub {
            font-size:clamp(.9rem,1.6vw,1.05rem);
            color:rgba(255,255,255,.65);
            font-weight:300; line-height:1.7;
            margin-bottom:44px; max-width:460px;
        }

        .hero-btns { display:flex; gap:18px; flex-wrap:wrap; }

        .btn-gold {
            display:inline-flex; align-items:center; gap:10px;
            padding:14px 36px;
            background:var(--gold-gradient);
            color:var(--navy); font-family:'DM Sans',sans-serif;
            font-size:.82rem; font-weight:700; letter-spacing:1px;
            text-transform:uppercase; border-radius:50px;
            text-decoration:none;
            transition:all .35s cubic-bezier(.23,1,.32,1);
            box-shadow:0 6px 22px rgba(201,160,61,.32);
            position:relative; overflow:hidden;
        }
        .btn-gold::after{
            content:''; position:absolute; inset:0;
            background:rgba(255,255,255,.18);
            transform:translateX(-110%); transition:transform .4s ease;
        }
        .btn-gold:hover::after{transform:translateX(110%);}
        .btn-gold:hover{transform:translateY(-4px);box-shadow:0 12px 32px rgba(201,160,61,.45);}

        .btn-ghost-white {
            display:inline-flex; align-items:center; gap:10px;
            padding:13px 32px;
            background:transparent; border:1.5px solid rgba(255,255,255,.3);
            color:rgba(255,255,255,.85); font-size:.82rem; font-weight:600;
            letter-spacing:1px; text-transform:uppercase; border-radius:50px;
            text-decoration:none;
            transition:all .3s ease; backdrop-filter:blur(8px);
        }
        .btn-ghost-white:hover{border-color:var(--gold);color:var(--gold);transform:translateY(-3px);background:rgba(201,160,61,.07);}

        /* Slide controls */
        .hero-nav {
            position:absolute; bottom:48px; left:50%;
            transform:translateX(-50%);
            z-index:5; display:flex; align-items:center; gap:24px;
        }
        .hero-arrow {
            width:44px; height:44px; border-radius:50%;
            background:rgba(255,255,255,.1); border:1px solid rgba(255,255,255,.2);
            color:white; font-size:1rem; cursor:pointer;
            display:flex; align-items:center; justify-content:center;
            transition:all .3s ease; backdrop-filter:blur(8px);
        }
        .hero-arrow:hover{background:var(--gold);border-color:var(--gold);color:var(--navy);}
        .hero-dots { display:flex; gap:8px; align-items:center; }
        .hero-dot {
            width:8px; height:8px; border-radius:50%;
            background:rgba(255,255,255,.3); border:none; cursor:pointer;
            transition:all .3s ease; padding:0;
        }
        .hero-dot.active{background:var(--gold);transform:scale(1.3);}

        /* Scroll cue */
        .scroll-cue {
            position:absolute; bottom:42px; right:60px; z-index:5;
            display:flex; flex-direction:column; align-items:center; gap:8px;
        }
        .scroll-cue-label{font-size:.6rem;letter-spacing:3px;text-transform:uppercase;color:rgba(255,255,255,.35);}
        .scroll-mouse{width:20px;height:32px;border:1.5px solid rgba(255,255,255,.2);border-radius:10px;display:flex;justify-content:center;padding-top:5px;}
        .scroll-dot{width:3px;height:7px;background:var(--gold);border-radius:3px;animation:sDot 2s ease-in-out infinite;}
        @keyframes sDot{0%,100%{transform:translateY(0);opacity:1;}50%{transform:translateY(7px);opacity:.3;}}

        /* ============================================================
           MARQUEE TICKER
           ============================================================ */
        .marquee-bar {
            background:var(--navy-mid);
            border-bottom:1px solid rgba(201,160,61,.15);
            overflow:hidden; padding:14px 0;
        }
        .marquee-track {
            display:flex; gap:0;
            animation:marqueeScroll 28s linear infinite;
            width:max-content;
        }
        .marquee-item {
            white-space:nowrap;
            font-size:.72rem; font-weight:600; letter-spacing:2px;
            text-transform:uppercase; color:rgba(255,255,255,.55);
            padding:0 40px;
        }
        .marquee-sep {
            color:var(--gold); margin:0; font-size:.9rem;
        }
        @keyframes marqueeScroll{from{transform:translateX(0);}to{transform:translateX(-50%);}}

        /* ============================================================
           PHILOSOPHY SECTION
           ============================================================ */
        .philosophy {
            padding:110px 0;
            background:var(--white);
            position:relative; overflow:hidden;
        }
        .philosophy::before {
            content:'ふるさと';
            position:absolute; right:-30px; top:50%;
            transform:translateY(-50%);
            font-family:'Cormorant Garamond',serif;
            font-size:clamp(100px,15vw,200px);
            color:rgba(201,160,61,.04); font-weight:700;
            line-height:1; pointer-events:none; user-select:none;
            letter-spacing:-0.05em;
        }
        .philosophy-grid {
            display:grid; grid-template-columns:1fr 1fr;
            gap:72px; align-items:center;
        }
        .philosophy-img-wrap {
            position:relative; border-radius:var(--r-lg); overflow:hidden;
            box-shadow:var(--shadow-hard);
        }
        .philosophy-img-wrap::before {
            content:'';
            position:absolute; inset:-1px;
            border-radius:var(--r-lg);
            background:var(--gold-gradient);
            z-index:1; opacity:.6;
            padding:2px;
            -webkit-mask:linear-gradient(#fff 0 0) content-box,linear-gradient(#fff 0 0);
            -webkit-mask-composite:destination-out;
            mask-composite:exclude;
        }
        .philosophy-img-wrap img,
        .philosophy-img-placeholder {
            width:100%; display:block;
            object-fit:cover; aspect-ratio:4/3;
            border-radius:var(--r-lg);
        }
        .philosophy-img-placeholder {
            background:linear-gradient(135deg,var(--navy) 0%,#1a3a5c 100%);
            display:flex; align-items:center; justify-content:center;
            color:rgba(255,255,255,.4);
            font-family:'Cormorant Garamond',serif; font-size:1.4rem;
            text-align:center; padding:2rem;
        }
        .philosophy-year-badge {
            position:absolute; bottom:24px; left:-20px; z-index:3;
            background:var(--gold-gradient);
            color:var(--navy); padding:14px 22px;
            border-radius:var(--r-md);
            box-shadow:0 8px 24px rgba(201,160,61,.3);
            text-align:center;
        }
        .philosophy-year-badge strong{display:block;font-size:1.5rem;font-weight:700;line-height:1;}
        .philosophy-year-badge span{font-size:.65rem;font-weight:600;letter-spacing:2px;text-transform:uppercase;}

        .philosophy-text { position:relative; }
        .philosophy-text .body-copy {
            font-size:.95rem; color:var(--ink-60);
            line-height:1.8; margin-bottom:18px;
        }
        .philosophy-text blockquote {
            font-family:'Cormorant Garamond',serif;
            font-size:1.2rem; font-style:italic;
            color:var(--ink);
            border-left:3px solid var(--gold);
            padding-left:20px;
            margin:24px 0; line-height:1.6;
        }
        .btn-ink {
            display:inline-flex; align-items:center; gap:10px;
            padding:13px 32px;
            background:var(--navy); color:var(--white);
            font-size:.82rem; font-weight:600; letter-spacing:1px;
            text-transform:uppercase; border-radius:50px;
            text-decoration:none; border:none;
            transition:all .3s ease;
        }
        .btn-ink:hover{background:var(--crimson);transform:translateY(-2px);box-shadow:0 8px 24px rgba(181,39,31,.3);}

        /* ============================================================
           STATS BAR
           ============================================================ */
        .stats-bar {
            background:var(--navy);
            border-top:1px solid rgba(201,160,61,.1);
            border-bottom:1px solid rgba(201,160,61,.1);
        }
        .stats-grid {
            display:grid; grid-template-columns:repeat(4,1fr);
        }
        .stat-cell {
            padding:36px 0; text-align:center;
            border-right:1px solid rgba(255,255,255,.07);
            transition:background .3s;
        }
        .stat-cell:last-child{border-right:none;}
        .stat-cell:hover{background:rgba(201,160,61,.04);}
        .stat-num {
            font-family:'Cormorant Garamond',serif;
            font-size:2.4rem; font-weight:600;
            color:var(--gold); line-height:1; margin-bottom:6px;
        }
        .stat-lbl {
            font-size:.68rem; font-weight:600; letter-spacing:2px;
            text-transform:uppercase; color:rgba(255,255,255,.4);
        }

        /* ============================================================
           PROMISE / FEATURES SECTION
           ============================================================ */
        .promise-section {
            padding:110px 0;
            background:linear-gradient(160deg,var(--navy) 0%,var(--navy-mid) 100%);
            position:relative; overflow:hidden;
        }
        .promise-section::before {
            content:'一期一会';
            position:absolute; left:-20px; bottom:-20px;
            font-family:'Cormorant Garamond',serif;
            font-size:clamp(80px,12vw,160px);
            color:rgba(201,160,61,.04); font-weight:700;
            line-height:1; pointer-events:none;
        }
        .promise-grid {
            display:grid; grid-template-columns:1fr 1fr;
            gap:72px; align-items:center;
        }
        .promise-img-wrap {
            border-radius:var(--r-lg); overflow:hidden;
            box-shadow:var(--shadow-hard); position:relative;
        }
        .promise-img-wrap img {
            width:100%; display:block;
            object-fit:cover; aspect-ratio:4/3;
        }
        .promise-img-wrap::after {
            content:'';
            position:absolute; inset:0;
            background:linear-gradient(to top,rgba(13,27,42,.5) 0%,transparent 50%);
        }
        .promise-text .body-copy {
            font-size:.95rem; color:rgba(255,255,255,.65);
            line-height:1.8; margin-bottom:18px;
        }
        .promise-text blockquote {
            font-family:'Cormorant Garamond',serif;
            font-size:1.2rem; font-style:italic;
            color:var(--gold-light);
            border-left:3px solid var(--crimson);
            padding-left:20px;
            margin:24px 0; line-height:1.6;
        }
        .promise-btns{display:flex;gap:16px;flex-wrap:wrap;margin-top:32px;}
        .btn-crimson {
            display:inline-flex; align-items:center; gap:10px;
            padding:14px 34px;
            background:var(--crimson); color:var(--white);
            font-size:.82rem; font-weight:700; letter-spacing:1px;
            text-transform:uppercase; border-radius:50px;
            text-decoration:none; transition:all .3s ease;
        }
        .btn-crimson:hover{background:#a01c15;transform:translateY(-3px);box-shadow:0 8px 24px rgba(181,39,31,.35);}
        .btn-outline-light {
            display:inline-flex; align-items:center; gap:10px;
            padding:13px 30px;
            background:transparent; border:1.5px solid rgba(255,255,255,.3);
            color:rgba(255,255,255,.85); font-size:.82rem; font-weight:600;
            letter-spacing:1px; text-transform:uppercase; border-radius:50px;
            text-decoration:none; transition:all .3s ease;
        }
        .btn-outline-light:hover{border-color:var(--gold-light);color:var(--gold-light);transform:translateY(-2px);}

        /* ============================================================
           POPULAR DISHES
           ============================================================ */
        .dishes-section {
            padding:110px 0;
            background:var(--paper);
            position:relative;
        }
        .dishes-section-header {
            display:flex; align-items:flex-end; justify-content:space-between;
            margin-bottom:56px; flex-wrap:wrap; gap:24px;
        }
        .dishes-grid {
            display:grid;
            grid-template-columns:repeat(auto-fill,minmax(280px,1fr));
            gap:28px;
        }
        .dish-card {
            background:var(--white);
            border-radius:var(--r-lg);
            overflow:hidden;
            border:1px solid var(--ink-10);
            transition:all .35s cubic-bezier(.23,1,.32,1);
            box-shadow:var(--shadow-soft);
        }
        .dish-card:hover{transform:translateY(-8px);box-shadow:var(--shadow-hard);border-color:rgba(201,160,61,.2);}
        .dish-img-wrap{position:relative;overflow:hidden;}
        .dish-img-wrap img{
            width:100%; aspect-ratio:4/3; object-fit:cover;
            display:block;
            transition:transform .6s ease;
        }
        .dish-card:hover .dish-img-wrap img{transform:scale(1.06);}
        .dish-badge-pill {
            position:absolute; top:14px; left:14px;
            background:var(--gold-gradient);
            color:var(--navy); font-size:.65rem; font-weight:700;
            letter-spacing:1.5px; text-transform:uppercase;
            padding:5px 12px; border-radius:50px;
        }
        .dish-body{padding:22px 24px 24px;}
        .dish-name{
            font-family:'Cormorant Garamond',serif;
            font-size:1.3rem; font-weight:600;
            color:var(--ink); margin-bottom:8px; line-height:1.2;
        }
        .dish-desc{font-size:.82rem;color:var(--ink-60);line-height:1.6;margin-bottom:16px;}
        .dish-footer{display:flex;align-items:center;justify-content:space-between;}
        .dish-price{
            font-family:'Cormorant Garamond',serif;
            font-size:1.2rem; font-weight:600; color:var(--gold-dark);
        }
        .loading-state{
            grid-column:1/-1; text-align:center; padding:60px 0;
            color:var(--ink-40); font-size:.9rem;
        }
        .spinner{
            width:36px; height:36px; border:2px solid var(--paper-dark);
            border-top-color:var(--gold);
            border-radius:50%; animation:spin .8s linear infinite;
            margin:0 auto 16px;
        }
        @keyframes spin{to{transform:rotate(360deg);}}

        /* ============================================================
           STORY TEASER
           ============================================================ */
        .story-teaser {
            position:relative; padding:120px 0;
            overflow:hidden;
        }
        .story-teaser-bg {
            position:absolute; inset:0;
            background-size:cover; background-position:center;
            filter:brightness(.25);
            transform:scale(1.04);
        }
        .story-teaser::before {
            content:''; position:absolute; inset:0; z-index:1;
            background:linear-gradient(135deg,rgba(13,27,42,.85) 0%,rgba(13,27,42,.4) 100%);
        }
        .story-teaser-inner {
            position:relative; z-index:2;
            display:grid; grid-template-columns:1fr 1fr;
            gap:64px; align-items:center;
        }
        .story-teaser-img-frame {
            border-radius:var(--r-lg); overflow:hidden;
            box-shadow:0 30px 80px rgba(0,0,0,.5);
            border:1px solid rgba(201,160,61,.2);
        }
        .story-teaser-img-frame img{width:100%;display:block;object-fit:cover;aspect-ratio:4/3;}
        .story-teaser-text .body-copy{
            font-size:.95rem; color:rgba(255,255,255,.65);
            line-height:1.8; margin-bottom:18px;
        }

        /* ============================================================
           VIDEO SECTION
           ============================================================ */
        .video-section {
            padding:110px 0;
            background:var(--white);
        }
        .video-grid {
            display:grid; grid-template-columns:1fr 1fr;
            gap:72px; align-items:center;
        }
        .video-text .body-copy{
            font-size:.95rem; color:var(--ink-60);
            line-height:1.8; margin-bottom:20px;
        }
        .video-embed-frame {
            border-radius:var(--r-lg); overflow:hidden;
            box-shadow:var(--shadow-hard);
            position:relative;
            background:var(--navy);
        }
        .video-embed-frame video{
            width:100%; display:block;
            aspect-ratio:16/9; object-fit:cover;
        }
        .btn-youtube {
            display:inline-flex; align-items:center; gap:10px;
            padding:13px 28px;
            background:#ff0000; color:white;
            font-size:.82rem; font-weight:700; letter-spacing:1px;
            text-transform:uppercase; border-radius:50px;
            text-decoration:none; transition:all .3s ease;
            margin-top:8px;
        }
        .btn-youtube:hover{background:#cc0000;transform:translateY(-2px);box-shadow:0 8px 24px rgba(255,0,0,.3);}

        /* ============================================================
           DELIVERY SECTION
           ============================================================ */
        .delivery-section {
            padding:100px 0;
            background:var(--paper-dark);
            position:relative; overflow:hidden;
        }
        .delivery-section::before {
            content:'配達';
            position:absolute; right:-10px; top:50%;
            transform:translateY(-50%);
            font-family:'Cormorant Garamond',serif;
            font-size:clamp(120px,18vw,240px);
            color:rgba(201,160,61,.05); font-weight:700;
            line-height:1; pointer-events:none;
        }
        .delivery-header{text-align:center;margin-bottom:24px;}
        .delivery-intro{
            text-align:center; max-width:680px; margin:0 auto 52px;
        }
        .delivery-intro p{
            font-size:.95rem; color:var(--ink-60); line-height:1.8;
            margin-bottom:10px;
        }
        .delivery-intro .d-contact{
            display:inline-flex; align-items:center; gap:10px;
            padding:11px 24px;
            background:rgba(201,160,61,.1); border:1px solid rgba(201,160,61,.25);
            border-radius:50px; font-size:.82rem; color:var(--ink);
            text-decoration:none; transition:all .3s;
            margin-top:10px;
        }
        .delivery-intro .d-contact i{color:#25D366;}
        .delivery-intro .d-contact:hover{background:var(--gold);color:var(--navy);}

        .delivery-cards-row {
            display:grid; grid-template-columns:repeat(2,1fr);
            gap:28px; max-width:720px; margin:0 auto;
            position:relative; z-index:1;
        }
        .delivery-app-card {
            background:var(--white);
            border-radius:var(--r-lg); padding:40px 28px;
            text-align:center;
            border:2px solid transparent;
            box-shadow:var(--shadow-soft);
            text-decoration:none;
            transition:all .35s cubic-bezier(.23,1,.32,1);
            display:flex; flex-direction:column;
            align-items:center; gap:0;
        }
        .delivery-app-card:hover{
            transform:translateY(-8px);
            box-shadow:var(--shadow-hard);
            border-color:var(--gold);
        }
        .delivery-app-card.uber{background:#000;}
        .delivery-app-card.uber:hover{border-color:rgba(255,255,255,.3);}
        .delivery-app-logo{
            height:52px; width:auto;
            max-width:160px; object-fit:contain;
            display:block; margin-bottom:20px;
        }
        .delivery-app-name{
            font-family:'Cormorant Garamond',serif;
            font-size:1.3rem; font-weight:600;
            color:var(--ink); margin-bottom:16px;
        }
        .delivery-app-card.uber .delivery-app-name{color:#fff;}
        .delivery-btn {
            display:inline-flex; align-items:center; gap:8px;
            padding:10px 24px; border-radius:50px;
            font-size:.75rem; font-weight:700; letter-spacing:1px;
            text-transform:uppercase; transition:all .3s;
        }
        .delivery-btn-dark{background:var(--navy);color:var(--white);}
        .delivery-btn-dark:hover{background:var(--crimson);}
        .delivery-btn-light{background:transparent;border:1.5px solid rgba(255,255,255,.35);color:#fff;}
        .delivery-btn-light:hover{background:#fff;color:#000;}

        .delivery-fee-note {
            display:flex; align-items:flex-start; gap:12px;
            max-width:720px; margin:28px auto 0;
            padding:16px 22px;
            background:rgba(201,160,61,.08);
            border:1px solid rgba(201,160,61,.2);
            border-radius:var(--r-md);
            font-size:.82rem; color:var(--ink-60); line-height:1.6;
            position:relative; z-index:1;
        }
        .delivery-fee-note i{color:var(--gold);flex-shrink:0;margin-top:2px;}

        /* ============================================================
           REVIEWS
           ============================================================ */
        .reviews-section {
            padding:100px 0;
            background:var(--navy);
            overflow:hidden;
        }
        .reviews-header{text-align:center;margin-bottom:52px;}
        .reviews-ticker {
            overflow:hidden;
            mask-image:linear-gradient(90deg,transparent,black 8%,black 92%,transparent);
            -webkit-mask-image:linear-gradient(90deg,transparent,black 8%,black 92%,transparent);
        }
        .reviews-track {
            display:flex; gap:24px;
            animation:reviewScroll 38s linear infinite;
            width:max-content;
        }
        .reviews-track:hover{animation-play-state:paused;}
        @keyframes reviewScroll{from{transform:translateX(0);}to{transform:translateX(-50%);}}
        .review-card {
            flex-shrink:0; width:320px;
            background:rgba(255,255,255,.05);
            border:1px solid rgba(255,255,255,.08);
            border-radius:var(--r-lg); padding:28px;
            transition:all .3s;
        }
        .review-card:hover{background:rgba(255,255,255,.08);border-color:rgba(201,160,61,.2);}
        .review-stars{color:var(--gold);font-size:1rem;letter-spacing:2px;margin-bottom:14px;}
        .review-text{
            font-size:.88rem; color:rgba(255,255,255,.7);
            line-height:1.7; margin-bottom:20px;
            font-style:italic;
        }
        .review-author{display:flex;align-items:center;gap:12px;}
        .review-avatar{
            width:38px; height:38px; border-radius:50%;
            background:var(--gold-gradient);
            display:flex; align-items:center; justify-content:center;
            font-weight:700; font-size:.9rem; color:var(--navy);
            flex-shrink:0;
        }
        .review-name{font-size:.82rem;font-weight:600;color:rgba(255,255,255,.85);}
        .review-date{font-size:.72rem;color:rgba(255,255,255,.4);}

        /* ============================================================
           INSTAGRAM TEASER
           ============================================================ */
        .insta-section{
            padding:80px 0;
            background:var(--paper);
            text-align:center;
        }
        .insta-handle {
            display:inline-flex; align-items:center; gap:12px;
            padding:14px 32px;
            background:linear-gradient(135deg,#833ab4,#fd1d1d,#fcb045);
            color:white; border-radius:50px;
            font-size:.82rem; font-weight:700; letter-spacing:1px;
            text-transform:uppercase; text-decoration:none;
            transition:all .3s ease; margin-top:24px;
            box-shadow:0 6px 20px rgba(253,29,29,.25);
        }
        .insta-handle:hover{transform:translateY(-3px);box-shadow:0 12px 32px rgba(253,29,29,.35);}

        /* ============================================================
           FOOTER
           ============================================================ */
        .footer {
            background:var(--navy);
            color:rgba(255,255,255,.7);
            padding:70px 0 0;
        }
        .footer-grid {
            display:grid; grid-template-columns:2fr 1fr 2fr;
            gap:60px; padding-bottom:60px;
            border-bottom:1px solid rgba(255,255,255,.08);
        }
        .footer-brand-name {
            font-family:'Cormorant Garamond',serif;
            font-size:2rem; font-weight:600; color:var(--gold);
            display:block; margin-bottom:14px;
        }
        .footer-tagline{
            font-size:.82rem; line-height:1.7;
            color:rgba(255,255,255,.5); margin-bottom:28px; max-width:260px;
        }
        .social-row{display:flex;gap:12px;}
        .social-link{
            width:38px; height:38px; border-radius:50%;
            background:rgba(255,255,255,.07);
            border:1px solid rgba(255,255,255,.08);
            display:flex; align-items:center; justify-content:center;
            color:rgba(255,255,255,.6); font-size:.85rem;
            text-decoration:none; transition:all .3s;
        }
        .social-link:hover{background:var(--gold);color:var(--navy);border-color:var(--gold);transform:translateY(-3px);}
        .footer-col-title{
            font-size:.7rem; font-weight:600; letter-spacing:2.5px;
            text-transform:uppercase; color:var(--gold); margin-bottom:22px;
        }
        .footer-links{list-style:none;display:flex;flex-direction:column;gap:11px;}
        .footer-links a{
            color:rgba(255,255,255,.5); text-decoration:none;
            font-size:.85rem; transition:all .2s;
        }
        .footer-links a:hover{color:rgba(255,255,255,.9);padding-left:6px;}
        .footer-contact-list{list-style:none;display:flex;flex-direction:column;gap:14px;}
        .footer-contact-item{display:flex;align-items:flex-start;gap:12px;font-size:.82rem;line-height:1.5;}
        .footer-contact-item i{color:var(--gold);width:16px;flex-shrink:0;margin-top:2px;font-size:.8rem;}
        .footer-contact-item a{color:rgba(255,255,255,.5);text-decoration:none;transition:color .2s;}
        .footer-contact-item a:hover{color:var(--gold);}
        .footer-bottom{
            display:flex; align-items:center; justify-content:space-between;
            padding:24px 0; font-size:.72rem;
            color:rgba(255,255,255,.3); flex-wrap:wrap; gap:8px;
        }

        /* WhatsApp float */
        .wa-float {
            position:fixed; bottom:32px; right:32px;
            width:56px; height:56px; border-radius:50%;
            background:#25D366; color:white; font-size:1.6rem;
            display:flex; align-items:center; justify-content:center;
            box-shadow:0 6px 22px rgba(37,211,102,.4);
            text-decoration:none; z-index:200; transition:all .3s;
        }
        .wa-float::before{
            content:''; position:absolute; inset:-4px; border-radius:50%;
            border:2px solid rgba(37,211,102,.3);
            animation:waPulse 2s ease-in-out infinite;
        }
        @keyframes waPulse{0%,100%{transform:scale(1);opacity:.5;}50%{transform:scale(1.15);opacity:0;}}
        .wa-float:hover{transform:scale(1.1) translateY(-3px);box-shadow:0 10px 30px rgba(37,211,102,.55);}

        /* ============================================================
           HERO SECTION OVERRIDE FIXES - Centering the content
           ============================================================ */
        .hero {
            position: relative;
            width: 100%;
            height: 100vh;
            min-height: 600px;
            overflow: hidden;
            background: var(--navy);
        }

        .hero .hero-inner {
            position: relative !important;
            top: auto !important;
            left: auto !important;
            z-index: 10 !important;
            height: 100% !important;
            width: 100% !important;
            display: flex !important;
            flex-direction: column !important;
            justify-content: center !important;
            align-items: center !important;
            text-align: center !important;
        }

        .hero .hero-content {
            position: relative !important;
            top: auto !important;
            left: auto !important;
            right: auto !important;
            bottom: auto !important;
            transform: none !important;
            text-align: center !important;
            width: 100% !important;
            max-width: 820px !important;
            margin: 0 auto !important;
            padding: 0 20px !important;
            z-index: 10 !important;
        }

        .hero .hero-content .hero-h1 {
            text-align: center !important;
            margin-bottom: 24px !important;
        }

        .hero .hero-content .hero-sub {
            text-align: center !important;
            margin-left: auto !important;
            margin-right: auto !important;
            max-width: 560px !important;
        }

        .hero .hero-content .hero-btns {
            justify-content: center !important;
        }

        .hero .hero-content .hero-eyebrow {
            justify-content: center !important;
            margin-bottom: 24px !important;
        }

        .hero .hero-arrow {
            z-index: 20 !important;
        }
        .hero .hero-dots {
            z-index: 20 !important;
        }
        .hero .scroll-cue {
            z-index: 20 !important;
        }

        /* Responsive fixes for hero only */
        @media (max-width: 768px) {
            .hero .hero-btns {
                flex-direction: column !important;
                align-items: center !important;
            }
            .hero .btn-gold,
            .hero .btn-ghost-white {
                min-width: 200px !important;
                justify-content: center !important;
            }
            .hero .hero-h1 {
                font-size: clamp(2.5rem, 8vw, 3.5rem) !important;
            }
        }

        /* ============================================================
           RESPONSIVE
           ============================================================ */
        @media(max-width:960px){
            .philosophy-grid,.promise-grid,.story-teaser-inner,.video-grid{grid-template-columns:1fr;}
            .story-teaser-img-frame,.promise-img-wrap{order:-1;}
            .footer-grid{grid-template-columns:1fr 1fr;gap:40px;}
            .stats-grid{grid-template-columns:repeat(2,1fr);}
            .stat-cell:nth-child(2){border-right:none;}
        }
        @media(max-width:640px){
            .philosophy,.promise-section,.dishes-section,
            .story-teaser,.video-section,.delivery-section,
            .reviews-section,.insta-section{padding:70px 0;}
            .hero-h1{font-size:clamp(2.8rem,10vw,4.5rem);}
            .hero-btns{flex-direction:column;align-items:flex-start;}
            .footer-grid{grid-template-columns:1fr;}
            .delivery-cards-row{grid-template-columns:1fr 1fr;}
            .promise-btns{flex-direction:column;}
            .scroll-cue{display:none;}
            .dishes-section-header{flex-direction:column;align-items:flex-start;}
        }
        @media(max-width:420px){
            .delivery-cards-row{grid-template-columns:1fr;}
        }
        
        /* Accessibility */
        .skip-to-content {
            position: absolute;
            top: -40px;
            left: 0;
            background: var(--gold);
            color: var(--navy);
            padding: 8px 16px;
            text-decoration: none;
            z-index: 1000;
            transition: top 0.2s;
        }
        .skip-to-content:focus {
            top: 0;
        }
    </style>
</head>
<body>

<a href="#main-content" class="skip-to-content">Skip to main content</a>

<!-- ── NAVBAR ── -->
<nav class="navbar" role="navigation" aria-label="Main navigation">
    <div class="navbar-inner">
        <a href="/" class="navbar-logo" aria-label="Furusato Home">
            <img src="/assets/images/furusato-logo.png" alt="Furusato Japanese Restaurant" width="48" height="48" class="navbar-logo-img">
            <span class="navbar-logo-text">Furusato <span>Restaurant</span></span>
        </a>
        <div class="navbar-nav">
            <a href="/" class="active">Home</a>
            <div class="nav-dropdown">
                <a href="/menu.php" class="nav-dropdown-trigger">Menu
                    <svg width="10" height="6" viewBox="0 0 10 6" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M1 1l4 4 4-4"/></svg>
                </a>
                <div class="nav-dropdown-menu" id="nav-menu-categories"></div>
            </div>
            <a href="/our-story.php">Our Story</a>
            <a href="/contact.php">Contact</a>
        </div>
        <div class="navbar-reserve">
            <a href="/contact.php#reservation" class="btn">Reserve a Table</a>
        </div>
        <button class="navbar-hamburger" aria-label="Toggle mobile menu" aria-expanded="false">
            <span></span><span></span><span></span>
        </button>
    </div>
</nav>

<div class="mobile-menu" role="dialog" aria-label="Mobile navigation">
    <a href="/" class="active">Home</a>
    <a href="/menu.php">Menu</a>
    <div class="mobile-menu-categories" id="mobile-menu-categories"></div>
    <a href="/our-story.php">Our Story</a>
    <a href="/contact.php">Contact</a>
    <a href="/contact.php#reservation" class="btn btn-primary btn-lg">Reserve a Table</a>
</div>

<!-- ── HERO ── -->
<section class="hero" aria-label="Hero" id="main-content">
    <!-- Slide 1 -->
    <div class="hero-slide active">
        <div class="hero-slide-bg" style="background-image:url('/assets/images/hero/out-furusato.webp');" fetchpriority="high"></div>
        <div class="hero-slide-overlay"></div>
    </div>
    <!-- Slide 2 -->
    <div class="hero-slide">
        <div class="hero-slide-bg" style="background-image:url('/assets/images/hero/sushi-hero.webp');"></div>
        <div class="hero-slide-overlay"></div>
    </div>
    <!-- Slide 3 -->
    <div class="hero-slide">
        <div class="hero-slide-bg" style="background-image:url('/assets/images/hero/crafted.jpg');"></div>
        <div class="hero-slide-overlay"></div>
    </div>
    <!-- Slide 4 -->
    <div class="hero-slide">
        <div class="hero-slide-bg" style="background-image:url('/assets/images/interior.webp');"></div>
        <div class="hero-slide-overlay"></div>
    </div>

    <div class="hero-brushlines" aria-hidden="true">
        <div class="hero-brushline"></div><div class="hero-brushline"></div>
        <div class="hero-brushline"></div><div class="hero-brushline"></div>
        <div class="hero-brushline"></div>
    </div>
    <div class="hero-ripple" aria-hidden="true"></div>
    <div class="hero-ripple" aria-hidden="true"></div>
    <div class="hero-ripple" aria-hidden="true"></div>

    <div class="hero-inner container">
        <div class="hero-content">
            <div class="hero-eyebrow">
                <span class="hero-eyebrow-line"></span>
                <span class="hero-eyebrow-text">Est. 1 May 2001 &nbsp;·&nbsp; Westlands, Nairobi</span>
                <span class="hero-eyebrow-line"></span>
            </div>
            <h1 class="hero-h1">
                Taste That<br>
                Carries <em>You Home</em>
            </h1>
            <p class="hero-sub">Authentic Japanese &amp; Korean cuisine crafted with passion by 31 expert chefs. Open daily 12pm – 9pm.</p>
            <div class="hero-btns">
                <a href="/menu.php" class="btn-gold"><i class="fas fa-utensils"></i> View Our Menu</a>
                <a href="/our-story.php" class="btn-ghost-white">Our Story</a>
            </div>
        </div>
    </div>

    <nav class="hero-nav" aria-label="Slide navigation">
        <button class="hero-arrow hero-arrow-prev" aria-label="Previous slide"><i class="fas fa-chevron-left"></i></button>
        <div class="hero-dots" role="tablist">
            <button class="hero-dot active" data-index="0" aria-label="Slide 1"></button>
            <button class="hero-dot" data-index="1" aria-label="Slide 2"></button>
            <button class="hero-dot" data-index="2" aria-label="Slide 3"></button>
            <button class="hero-dot" data-index="3" aria-label="Slide 4"></button>
        </div>
        <button class="hero-arrow hero-arrow-next" aria-label="Next slide"><i class="fas fa-chevron-right"></i></button>
    </nav>

    <div class="scroll-cue" aria-hidden="true">
        <div class="scroll-mouse"><div class="scroll-dot"></div></div>
        <span class="scroll-cue-label">Scroll</span>
    </div>
</section>

<!-- ── MARQUEE ── -->
<div class="marquee-bar" aria-hidden="true">
    <div class="marquee-track" id="marquee-track">
        <span class="marquee-item">Nairobi's Premier Japanese Restaurant <span class="marquee-sep">✦</span></span>
        <span class="marquee-item">Est. 2001 <span class="marquee-sep">✦</span></span>
        <span class="marquee-item">31 Expert Chefs <span class="marquee-sep">✦</span></span>
        <span class="marquee-item">100+ Dishes <span class="marquee-sep">✦</span></span>
        <span class="marquee-item">Open Daily 12pm – 9pm <span class="marquee-sep">✦</span></span>
        <span class="marquee-item">Ring Road Parklands, Westlands <span class="marquee-sep">✦</span></span>
        <span class="marquee-item">Nairobi's Premier Japanese Restaurant <span class="marquee-sep">✦</span></span>
        <span class="marquee-item">Est. 2001 <span class="marquee-sep">✦</span></span>
        <span class="marquee-item">31 Expert Chefs <span class="marquee-sep">✦</span></span>
        <span class="marquee-item">100+ Dishes <span class="marquee-sep">✦</span></span>
        <span class="marquee-item">Open Daily 12pm – 9pm <span class="marquee-sep">✦</span></span>
        <span class="marquee-item">Ring Road Parklands, Westlands <span class="marquee-sep">✦</span></span>
    </div>
</div>

<!-- ── PHILOSOPHY ── -->
<section class="philosophy">
    <div class="container">
        <div class="philosophy-grid">
            <div class="reveal-left">
                <div class="philosophy-img-wrap">
                    <?php
                    $img = '/assets/images/2001.jpeg';
                    if (file_exists($_SERVER['DOCUMENT_ROOT'] . $img)): ?>
                        <img src="/assets/images/2001.jpeg" srcset="/assets/images/2001-480.jpg 480w, /assets/images/2001-960.jpg 960w, /assets/images/2001.jpeg 1600w" sizes="(max-width: 900px) 100vw, 50vw" alt="Furusato founders, Est. 2001" loading="lazy">
                    <?php else: ?>
                        <div class="philosophy-img-placeholder">Furusato Founders<br>Est. 2001</div>
                    <?php endif; ?>
                    <div class="philosophy-year-badge">
                        <strong>2001</strong>
                        <span>Est. 1st May</span>
                    </div>
                </div>
            </div>
            <div class="philosophy-text reveal-right">
                <div class="label-row">
                    <span class="label-line"></span>
                    <span class="label-text">Our Philosophy</span>
                </div>
                <h2 class="section-heading" style="margin-bottom:24px;">
                    Memories<br><em>Made in Food</em>
                </h2>
                <p class="body-copy">On 1 May 2001, we opened Furusato near Sarit Centre — a name chosen for its meaning: <strong>"hometown."</strong> Our dream was to create dishes that transport our guests to a place of comfort and nostalgia.</p>
                <p class="body-copy">Our kitchen was led by Nairobi's first female sushi chef — a testament to our daring spirit. Today we operate with 31 chefs and 19 servers, our focus on excellence never shifting.</p>
                <blockquote>"When you eat with us, you can expect consistent flavours, familiar foods, in a comfortable setting."</blockquote>
                <a href="/our-story.php" class="btn-ink">Read Our Story <i class="fas fa-arrow-right"></i></a>
            </div>
        </div>
    </div>
</section>

<!-- ── STATS ── -->
<div class="stats-bar">
    <div class="container">
        <div class="stats-grid">
            <div class="stat-cell">
                <div class="stat-num" data-target="2001">0</div>
                <div class="stat-lbl">Founded</div>
            </div>
            <div class="stat-cell">
                <div class="stat-num" data-target="31">0</div>
                <div class="stat-lbl">Expert Chefs</div>
            </div>
            <div class="stat-cell">
                <div class="stat-num" data-target="100" data-suffix="+">0</div>
                <div class="stat-lbl">Dishes</div>
            </div>
            <div class="stat-cell">
                <div class="stat-num" data-target="24" data-suffix="+">0</div>
                <div class="stat-lbl">Years of Excellence</div>
            </div>
        </div>
    </div>
</div>

<!-- ── PROMISE ── -->
<section class="promise-section">
    <div class="container">
        <div class="promise-grid">
            <div class="promise-text reveal-left">
                <div class="label-row">
                    <span class="label-line" style="background:var(--gold);"></span>
                    <span class="label-text">Our Promise</span>
                </div>
                <h2 class="section-heading section-heading-white" style="margin-bottom:24px;">
                    Consistency &amp;<br><em>Freshness</em>
                </h2>
                <p class="body-copy">Some of the best memories revolve around good food and family. Our recipes have been crafted, adapted and perfected for over two decades — now with 31 chefs and 19 servers dedicated to your experience.</p>
                <blockquote>"We roll more than sushi — we roll out warmth, hospitality, and a seat at the table for everyone."</blockquote>
                <div class="promise-btns">
                    <a href="/contact.php#reservation" class="btn-crimson"><i class="fas fa-calendar-check"></i> Reserve a Table</a>
                    <a href="/menu.php" class="btn-outline-light">View Menu</a>
                </div>
            </div>
            <div class="reveal-right">
                <div class="promise-img-wrap">
                    <img src="/assets/images/exterior.webp" srcset="/assets/images/exterior-480.webp 480w, /assets/images/exterior-960.webp 960w, /assets/images/exterior.webp 1536w" sizes="(max-width: 900px) 100vw, 50vw" alt="Furusato Restaurant exterior" loading="lazy">
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ── POPULAR DISHES ── -->
<section class="dishes-section">
    <div class="container">
        <div class="dishes-section-header reveal">
            <div>
                <div class="label-row">
                    <span class="label-line"></span>
                    <span class="label-text">From Our Kitchen</span>
                </div>
                <h2 class="section-heading">Our Most <em>Loved</em> Dishes</h2>
            </div>
            <a href="/menu.php" class="btn-ink">View Full Menu <i class="fas fa-arrow-right"></i></a>
        </div>
        <div class="dishes-grid" id="dishes-grid">
            <div class="loading-state">
                <div class="spinner"></div>
                <p>Loading popular dishes…</p>
            </div>
        </div>
    </div>
</section>

<!-- ── STORY TEASER ── -->
<section class="story-teaser">
    <div class="story-teaser-bg" style="background-image:url('/assets/images/interior.webp');"></div>
    <div class="container">
        <div class="story-teaser-inner">
            <div class="reveal-right" style="order:2;">
                <div class="story-teaser-img-frame">
                    <img src="/assets/images/hero/crafted.jpg" alt="Furusato chefs at work" loading="lazy">
                </div>
            </div>
            <div class="reveal-left" style="order:1;">
                <div class="label-row">
                    <span class="label-line" style="background:var(--gold);"></span>
                    <span class="label-text" style="color:var(--gold);">Est. 1 May 2001</span>
                </div>
                <h2 class="section-heading section-heading-white" style="margin-bottom:24px;">
                    Where Japanese Tradition<br><em>Meets Nairobi's Heart</em>
                </h2>
                <p style="font-size:.95rem;color:rgba(255,255,255,.65);line-height:1.8;margin-bottom:28px;">What began as a small dream to bring the authentic flavours of Japan to East Africa has grown into Nairobi's most beloved Japanese dining destination.</p>
                <a href="/our-story.php" class="btn-gold">Read Our Full Story <i class="fas fa-arrow-right"></i></a>
            </div>
        </div>
    </div>
</section>

<!-- ── VIDEO ── -->
<section class="video-section">
    <div class="container">
        <div class="video-grid">
            <div class="reveal-left">
                <div class="label-row">
                    <span class="label-line"></span>
                    <span class="label-text">Watch &amp; Experience</span>
                </div>
                <h2 class="section-heading" style="margin-bottom:20px;">
                    See <em>Furusato</em><br>Come to Life
                </h2>
                <p class="video-text body-copy" style="font-size:.95rem;color:var(--ink-60);line-height:1.8;margin-bottom:20px;">Step behind the scenes and experience the warmth, craft and passion that goes into every dish we serve.</p>
                <a href="https://www.youtube.com/@furusatorestaurant" target="_blank" rel="noopener noreferrer" class="btn-youtube">
                    <i class="fab fa-youtube"></i> Watch on YouTube
                </a>
            </div>
            <div class="reveal-right">
                <div class="video-embed-frame">
                    <video id="heroVideo" preload="none" muted loop playsinline poster="/assets/images/video-poster.jpg">
                        <source src="/assets/images/video/furusato.mp4" type="video/mp4">
                    </video>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ── DELIVERY ── -->
<section class="delivery-section">
    <div class="container">
        <div class="delivery-header reveal">
            <div class="label-row" style="justify-content:center;">
                <span class="label-line"></span>
                <span class="label-text">At Your Doorstep</span>
                <span class="label-line"></span>
            </div>
            <h2 class="section-heading" style="text-align:center;">Order for <em>Delivery</em></h2>
        </div>
        <div class="delivery-intro reveal">
            <p>Furusato delivers for free for orders over <strong>Ksh 3,000</strong>. For orders under Ksh 3,000 a delivery fee applies — ranging from <strong>Ksh 300 to Ksh 500</strong> depending on distance.</p>
            <p>We are also available on the delivery apps below:</p>
            <a href="<?= htmlspecialchars(wa_link($waDeliveryMsg)) ?>" target="_blank" rel="noopener noreferrer" class="d-contact">
                <i class="fab fa-whatsapp"></i> Call or WhatsApp <strong>0722 488 706</strong> to enquire about delivery
            </a>
        </div>
        <div class="delivery-cards-row reveal">
            <a href="https://www.ubereats.com/ke/store/furusato-japanese-restaurant/FGTL3T6JQRqcydZR-3jmeg"
               target="_blank" rel="noopener noreferrer" class="delivery-app-card uber">
                <img src="/assets/images/ubereats.png" alt="Uber Eats" class="delivery-app-logo"
                     onerror="this.style.display='none'">
                <div class="delivery-app-name">Uber Eats</div>
                <span class="delivery-btn delivery-btn-light">Order Now</span>
            </a>
            <a href="https://glovoapp.com/ke/en/nairobi/furusato-japanese-restaurant-nbo/"
               target="_blank" rel="noopener noreferrer" class="delivery-app-card">
                <img src="/assets/images/glovo.png" alt="Glovo" class="delivery-app-logo"
                     onerror="this.style.display='none'">
                <div class="delivery-app-name">Glovo</div>
                <span class="delivery-btn delivery-btn-dark">Order Now</span>
            </a>
        </div>
        
    </div>
</section>

<!-- ── REVIEWS ── -->
<section class="reviews-section">
    <div class="container">
        <div class="reviews-header reveal">
            <div class="label-row" style="justify-content:center;">
                <span class="label-line" style="background:var(--gold);"></span>
                <span class="label-text">Guest Experiences</span>
                <span class="label-line" style="background:var(--gold);"></span>
            </div>
            <h2 class="section-heading section-heading-white">What Our <em>Guests</em> Say</h2>
        </div>
    </div>
    <div class="reviews-ticker">
        <div class="reviews-track" id="reviews-track"></div>
    </div>
</section>

<!-- ── INSTAGRAM ── -->
<section class="insta-section reveal">
    <div class="container">
        <div class="label-row" style="justify-content:center;">
            <span class="label-line"></span>
            <span class="label-text">Follow Along</span>
            <span class="label-line"></span>
        </div>
        <h2 class="section-heading" style="margin-bottom:8px;">We're on <em>Instagram</em></h2>
        <p style="color:var(--ink-40);font-size:.9rem;margin-bottom:4px;">Tag us in your photos and share your Furusato moments</p>
        <a href="https://www.instagram.com/furusato_japanese_restaurant" target="_blank" rel="noopener noreferrer" class="insta-handle">
            <i class="fab fa-instagram"></i> @furusato_japanese_restaurant
        </a>
    </div>
</section>

<!-- ── FOOTER ── -->
<footer class="footer">
    <div class="container">
        <div class="footer-grid">
            <div>
                <span class="footer-brand-name">Furusato</span>
                <p class="footer-tagline">Where tradition meets taste. Authentic Japanese cuisine crafted with passion in the heart of Nairobi.</p>
                <div class="social-row">
                    <a href="https://www.facebook.com/FurusatoNairobi" class="social-link" target="_blank" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
                    <a href="https://www.instagram.com/furusato_japanese_restaurant" class="social-link" target="_blank" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
                </div>
            </div>
            <div>
                <div class="footer-col-title">Quick Links</div>
                <ul class="footer-links">
                    <li><a href="/">Home</a></li>
                    <li><a href="/menu.php">Our Menu</a></li>
                    <li><a href="/our-story.php">Our Story</a></li>
                    <li><a href="/contact.php">Contact Us</a></li>
                    <li><a href="/contact.php#reservation">Reservations</a></li>
                </ul>
            </div>
            <div>
                <div class="footer-col-title">Contact Info</div>
                <ul class="footer-contact-list">
                    <li class="footer-contact-item"><i class="fas fa-map-marker-alt"></i><span>Ring Road Parklands, Westlands, Nairobi, Kenya</span></li>
                    <li class="footer-contact-item"><i class="fas fa-phone-alt"></i><a href="tel:+254722488706">0722 488 706</a></li>
                    <li class="footer-contact-item"><i class="fab fa-whatsapp"></i><a href="https://wa.me/<?= htmlspecialchars($restaurantWhatsapp) ?>" target="_blank" rel="noopener noreferrer">0734 639 203</a></li>
                    <li class="footer-contact-item"><i class="fas fa-envelope"></i><a href="mailto:furusatoreservation@gmail.com">furusatoreservation@gmail.com</a></li>
                    <li class="footer-contact-item"><i class="fas fa-clock"></i><span>Daily: 12:00 PM – 9:00 PM</span></li>
                </ul>
            </div>
        </div>
        <div class="footer-bottom">
            <span>&copy; <?= date('Y') ?> Furusato Japanese Restaurant. All prices inclusive of VAT and Levy.</span>
            <span>Authentic Japanese Cuisine · Nairobi, Kenya</span>
        </div>
    </div>
</footer>

<a href="<?= htmlspecialchars(wa_link($waReservationMsg)) ?>" class="wa-float" target="_blank" rel="noopener noreferrer" aria-label="Chat on WhatsApp">
    <i class="fab fa-whatsapp"></i>
</a>

<script src="/assets/js/main.js?v=<?= $mainJsVersion ?>"></script>
<script src="/assets/js/hero.js?v=<?= $heroJsVersion ?>"></script>
<script>
/* ── Scroll Reveal ── */
(function(){
    const obs = new IntersectionObserver((entries)=>{
        entries.forEach(e=>{
            if(e.isIntersecting){e.target.classList.add('in-view');obs.unobserve(e.target);}
        });
    },{threshold:.1});
    document.querySelectorAll('.reveal,.reveal-left,.reveal-right').forEach(el=>obs.observe(el));
})();

/* Hero Slideshow: consolidated into /assets/js/hero.js (enqueued above) */

/* ── Stats Counter ── */
(function(){
    const els = document.querySelectorAll('.stat-num');
    if(!els.length) return;
    let done = false;
    const obs = new IntersectionObserver(entries=>{
        if(entries[0].isIntersecting && !done){
            done=true;
            els.forEach(el=>{
                const target=parseInt(el.dataset.target,10);
                const suf=el.dataset.suffix||'';
                if(isNaN(target)) return;
                let n=0; const step=Math.ceil(target/80);
                const t=setInterval(()=>{
                    n+=step;
                    if(n>=target){el.textContent=target.toLocaleString()+suf;clearInterval(t);}
                    else el.textContent=n.toLocaleString()+suf;
                },20);
            });
        }
    },{threshold:.3});
    const sec=document.querySelector('.stats-bar');
    if(sec) obs.observe(sec);
})();

/* ── Popular Dishes ── */
(function(){
    const grid=document.getElementById('dishes-grid');
    if(!grid) return;
    function esc(s){if(!s) return '';return String(s).replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));}
    fetch('/api/menu.php?popular=true')
        .then(r=>{if(!r.ok) throw new Error('HTTP '+r.status); return r.json();})
        .then(data=>{
            let items=[];
            if(data.popular&&Array.isArray(data.popular)) items=data.popular;
            else if(Array.isArray(data)) items=data;
            if(!items.length){grid.innerHTML='<div class="loading-state"><p>No popular dishes found. <a href="/menu.php">Browse our full menu</a>.</p></div>';return;}
            grid.innerHTML=items.map(item=>{
                const img=item.image_url||item.image||'/assets/images/menu/placeholder.webp';
                const price=Number(item.price||0).toLocaleString();
                const desc=esc((item.description||'A delicious Japanese specialty').substring(0,100));
                return `<div class="dish-card reveal delay-1">
                    <div class="dish-img-wrap">
                        <span class="dish-badge-pill">${esc(item.badge||'Popular')}</span>
                        <img src="${esc(img)}" alt="${esc(item.name)}" loading="lazy" onerror="this.src='/assets/images/furusato-logo.png'">
                    </div>
                    <div class="dish-body">
                        <div class="dish-name">${esc(item.name)}</div>
                        <div class="dish-desc">${desc}${desc.length>=98?'…':''}</div>
                        <div class="dish-footer"><span class="dish-price">KES ${price}</span></div>
                    </div>
                </div>`;
            }).join('');
            document.querySelectorAll('.dish-card.reveal').forEach(el=>{
                new IntersectionObserver((en,o)=>{if(en[0].isIntersecting){en[0].target.classList.add('in-view');o.unobserve(en[0].target);}},{threshold:.1}).observe(el);
            });
        })
        .catch(()=>{
            grid.innerHTML='<div class="loading-state"><p>Unable to load dishes. <a href="/menu.php">Browse our full menu</a>.</p></div>';
        });
})();

/* ── Reviews ── */
(function(){
    const track=document.getElementById('reviews-track');
    if(!track) return;
    const reviews=[
        {name:"Aiko M.",rating:5,text:"The best Japanese restaurant in Nairobi. The sashimi is always incredibly fresh!",date:"March 2025"},
        {name:"James K.",rating:5,text:"Been coming here for over 10 years. The teppanyaki experience is unmatched.",date:"February 2025"},
        {name:"Sarah W.",rating:5,text:"Furusato feels like home. The staff are warm, the food is authentic.",date:"January 2025"},
        {name:"Flyer117706",rating:5,text:"My favourite place for Tepanyaki in Nairobi. Good place for groups, family and business.",date:"January 2025"},
        {name:"Kiseli M.",rating:5,text:"Wow! The hotplate experience at this Japanese restaurant was absolutely thrilling.",date:"July 2025"},
        {name:"Restlesspinay",rating:5,text:"Delicious food, cozy dining experience and a service that goes the extra mile.",date:"January 2025"},
        {name:"Shemina S.",rating:5,text:"Savour incredible Japanese flavours, from complimentary soups and salads to a diverse selection.",date:"July 2025"}
    ];
    function esc(s){return String(s||'').replace(/[&<>]/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;'}[m]));}
    const html=reviews.map(r=>`
        <div class="review-card">
            <div class="review-stars">★★★★★</div>
            <p class="review-text">"${esc(r.text)}"</p>
            <div class="review-author">
                <div class="review-avatar">${r.name.charAt(0)}</div>
                <div>
                    <div class="review-name">${esc(r.name)}</div>
                    <div class="review-date">${esc(r.date)}</div>
                </div>
            </div>
        </div>`).join('');
    track.innerHTML=html+html;
})();

/* ── Video lazy load ── */
(function(){
    const v=document.getElementById('heroVideo');
    if(!v) return;
    const obs=new IntersectionObserver(entries=>{
        if(entries[0].isIntersecting){
            v.preload='metadata'; v.load();
            v.play().catch(()=>{});
            obs.disconnect();
        }
    },{threshold:.3});
    obs.observe(v);
})();
</script>
</body>
</html>