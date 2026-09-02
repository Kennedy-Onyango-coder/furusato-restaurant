<?php
require_once __DIR__ . '/includes/functions.php';

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

// Start secure public session for CSRF protection - MUST be before ANY output
startSecureSession(false);
$csrfToken = $_SESSION['csrf_token'];

$styleVersion = get_asset_version('assets/css/style.css');
$animationsVersion = get_asset_version('assets/css/animations.css');
$mainJsVersion = get_asset_version('assets/js/main.js');
$contactJsVersion = get_asset_version('assets/js/contact.js');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate, max-age=0">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
    <meta name="description" content="Reserve your table at Furusato Japanese Restaurant in Nairobi. Experience authentic Japanese cuisine in an elegant setting.">
    <title>Contact & Reservations | Furusato Japanese Restaurant</title>

    <link rel="icon" type="image/png" href="/assets/images/furusato-logo.png">
    <link rel="apple-touch-icon" href="/assets/images/furusato-logo.png">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,600&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,300&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= asset_url('assets/css/style.css') . '?v=' . $styleVersion ?>">
    <link rel="stylesheet" href="<?= asset_url('assets/css/animations.css') . '?v=' . $animationsVersion ?>">

    <style>
        /* ============================================================
           CSS CUSTOM PROPERTIES
           ============================================================ */
        :root {
            --ink:         #0e0c0a;
            --ink-80:      rgba(14,12,10,0.8);
            --ink-40:      rgba(14,12,10,0.4);
            --ink-10:      rgba(14,12,10,0.07);
            --paper:       #faf8f4;
            --paper-dark:  #f0ece4;
            --gold:        #c9a03d;
            --gold-light:  #e8d08a;
            --gold-dark:   #9a7520;
            --gold-glow:   rgba(201,160,61,0.18);
            --red-accent:  #c0392b;
            --navy:        #0d1b2a;
            --navy-mid:    #162336;
            --white:       #ffffff;
            --shadow-soft: 0 2px 20px rgba(14,12,10,0.06);
            --shadow-med:  0 8px 40px rgba(14,12,10,0.10);
            --shadow-hard: 0 20px 70px rgba(14,12,10,0.16);
            --gold-gradient: linear-gradient(135deg, #9a7520 0%, #c9a03d 40%, #e8d08a 60%, #c9a03d 80%, #9a7520 100%);
            --r-sm: 6px;
            --r-md: 14px;
            --r-lg: 24px;
            --r-xl: 40px;
        }

        *, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }

        html { scroll-behavior: smooth; }

        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--paper);
            color: var(--ink);
            overflow-x: hidden;
        }

        /* Hero Section */
        .hero {
            position: relative;
            min-height: 65vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: flex-start;
            overflow: hidden;
            background: var(--navy);
        }

        .hero-canvas {
            position: absolute;
            inset: 0;
            overflow: hidden;
        }

        .hero-canvas::before {
            content: '';
            position: absolute;
            inset: 0;
            background:
                radial-gradient(ellipse 80% 60% at 70% 40%, rgba(201,160,61,0.12) 0%, transparent 60%),
                radial-gradient(ellipse 50% 70% at 20% 80%, rgba(192,57,43,0.08) 0%, transparent 50%),
                linear-gradient(160deg, #0d1b2a 0%, #162336 50%, #0a1520 100%);
        }

        .hero-inner {
            position: relative;
            z-index: 3;
            max-width: 1280px;
            margin: 0 auto;
            padding: 0 60px;
            width: 100%;
            padding-top: 120px;
            padding-bottom: 80px;
        }

        .hero-eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 14px;
            margin-bottom: 32px;
            opacity: 0;
            animation: revealUp 0.8s ease forwards 0.3s;
        }

        .eyebrow-line {
            width: 40px;
            height: 1px;
            background: var(--gold);
        }

        .eyebrow-text {
            font-size: 0.72rem;
            font-weight: 600;
            letter-spacing: 3px;
            text-transform: uppercase;
            color: var(--gold);
        }

        .hero-heading {
            font-family: 'Cormorant Garamond', serif;
            font-size: clamp(3.8rem, 9vw, 7.5rem);
            font-weight: 300;
            line-height: 1.0;
            color: var(--white);
            margin-bottom: 12px;
            opacity: 0;
            animation: revealUp 1s ease forwards 0.5s;
        }

        .hero-heading em {
            font-style: italic;
            background: var(--gold-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .hero-heading-2 {
            font-family: 'Cormorant Garamond', serif;
            font-size: clamp(3.8rem, 9vw, 7.5rem);
            font-weight: 600;
            line-height: 1.0;
            color: var(--white);
            margin-bottom: 36px;
            opacity: 0;
            animation: revealUp 1s ease forwards 0.7s;
        }

        .hero-sub {
            max-width: 480px;
            font-size: 1rem;
            font-weight: 300;
            color: rgba(255,255,255,0.65);
            line-height: 1.75;
            margin-bottom: 52px;
            opacity: 0;
            animation: revealUp 1s ease forwards 0.9s;
        }

        .hero-actions {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            opacity: 0;
            animation: revealUp 1s ease forwards 1.1s;
        }

        .cta-primary {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            padding: 16px 40px;
            background: var(--gold-gradient);
            color: var(--navy);
            font-family: 'DM Sans', sans-serif;
            font-size: 0.85rem;
            font-weight: 600;
            letter-spacing: 1px;
            text-transform: uppercase;
            border-radius: var(--r-xl);
            text-decoration: none;
            transition: all 0.4s cubic-bezier(0.23,1,0.32,1);
            box-shadow: 0 6px 24px rgba(201,160,61,0.35);
        }

        .cta-primary:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 36px rgba(201,160,61,0.45);
        }

        .cta-ghost {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            padding: 15px 36px;
            background: transparent;
            border: 1px solid rgba(255,255,255,0.25);
            color: rgba(255,255,255,0.85);
            font-size: 0.85rem;
            font-weight: 500;
            letter-spacing: 1px;
            text-transform: uppercase;
            border-radius: var(--r-xl);
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .cta-ghost:hover {
            border-color: var(--gold);
            color: var(--gold);
            transform: translateY(-3px);
            background: rgba(201,160,61,0.06);
        }

        .scroll-cue {
            position: absolute;
            bottom: 40px;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
            z-index: 3;
            opacity: 0;
            animation: revealUp 1s ease forwards 1.5s;
        }

        .scroll-cue span {
            font-size: 0.65rem;
            letter-spacing: 3px;
            text-transform: uppercase;
            color: rgba(255,255,255,0.4);
        }

        .scroll-mouse {
            width: 22px;
            height: 36px;
            border: 1.5px solid rgba(255,255,255,0.2);
            border-radius: 12px;
            display: flex;
            justify-content: center;
            padding-top: 6px;
        }

        .scroll-dot {
            width: 3px;
            height: 8px;
            background: var(--gold);
            border-radius: 3px;
            animation: scrollBounce 2s ease-in-out infinite;
        }

        @keyframes scrollBounce {
            0%, 100% { transform: translateY(0); opacity: 1; }
            50% { transform: translateY(8px); opacity: 0.3; }
        }

        @keyframes revealUp {
            from { opacity: 0; transform: translateY(30px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        /* Stats Bar */
        .stats-bar {
            background: var(--navy-mid);
            border-bottom: 1px solid rgba(201,160,61,0.15);
        }

        .stats-inner {
            max-width: 1280px;
            margin: 0 auto;
            padding: 0 60px;
            display: grid;
            grid-template-columns: repeat(4, 1fr);
        }

        .stat-item {
            padding: 28px 0;
            text-align: center;
            border-right: 1px solid rgba(255,255,255,0.07);
        }

        .stat-item:last-child { border-right: none; }

        .stat-num {
            font-family: 'Cormorant Garamond', serif;
            font-size: 2rem;
            font-weight: 600;
            color: var(--gold);
            line-height: 1;
            margin-bottom: 6px;
        }

        .stat-label {
            font-size: 0.7rem;
            font-weight: 500;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            color: rgba(255,255,255,0.45);
        }

        /* Main Body */
        .page-body {
            max-width: 1280px;
            margin: 0 auto;
            padding: 100px 60px;
        }

        .label-row {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 16px;
        }

        .label-line { width: 32px; height: 1px; background: var(--gold); }

        .label-text {
            font-size: 0.68rem;
            font-weight: 600;
            letter-spacing: 3px;
            text-transform: uppercase;
            color: var(--gold);
        }

        .section-title {
            font-family: 'Cormorant Garamond', serif;
            font-size: clamp(2.4rem, 5vw, 3.5rem);
            font-weight: 400;
            color: var(--ink);
            line-height: 1.1;
        }

        .section-title em { font-style: italic; color: var(--gold-dark); }

        /* Grid Layout */
        .content-grid {
            display: grid;
            grid-template-columns: 1fr 1.15fr;
            gap: 64px;
            align-items: start;
        }

        /* Contact Cards */
        .contact-col { display: flex; flex-direction: column; gap: 16px; }

        .contact-card {
            display: flex;
            align-items: center;
            gap: 20px;
            padding: 22px 26px;
            background: var(--white);
            border: 1px solid rgba(14,12,10,0.07);
            border-radius: var(--r-md);
            transition: all 0.35s cubic-bezier(0.23,1,0.32,1);
        }

        .contact-card:hover {
            transform: translateX(6px);
            box-shadow: var(--shadow-med);
            border-color: rgba(201,160,61,0.2);
        }

        .card-icon {
            flex-shrink: 0;
            width: 52px;
            height: 52px;
            background: var(--paper-dark);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            color: var(--gold-dark);
        }

        .contact-card:hover .card-icon {
            background: var(--gold);
            color: var(--white);
        }

        .card-body { flex: 1; }

        .card-label {
            font-size: 0.65rem;
            font-weight: 600;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: var(--ink-40);
            margin-bottom: 4px;
        }

        .card-value {
            font-size: 0.95rem;
            font-weight: 500;
            color: var(--ink);
        }

        .card-value a {
            color: inherit;
            text-decoration: none;
        }
        .card-value a:hover { color: var(--gold-dark); }

        /* Map Section */
        .map-section {
            margin-top: 10px;
            border-radius: var(--r-lg);
            overflow: hidden;
            box-shadow: var(--shadow-med);
            background: var(--white);
        }

        .map-topbar {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 16px 22px;
            background: var(--navy);
        }

        .map-dot { width: 8px; height: 8px; border-radius: 50%; }
        .map-dot-1 { background: #ff5f57; }
        .map-dot-2 { background: #febc2e; }
        .map-dot-3 { background: #28c840; }

        .map-address {
            flex: 1;
            background: rgba(255,255,255,0.07);
            border-radius: 6px;
            padding: 6px 14px;
            font-size: 0.72rem;
            color: rgba(255,255,255,0.55);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .map-address i { color: var(--gold); }

        .map-embed {
            width: 100%;
            height: 280px;
            display: block;
            border: none;
        }

        /* Delivery Section */
        .delivery-section {
            margin-top: 10px;
            background: linear-gradient(135deg, var(--navy) 0%, var(--navy-mid) 100%);
            border-radius: var(--r-lg);
            padding: 32px 28px;
        }

        .delivery-header {
            display: flex;
            align-items: flex-start;
            gap: 14px;
            margin-bottom: 24px;
        }

        .delivery-icon-wrap {
            width: 46px;
            height: 46px;
            background: rgba(201,160,61,0.15);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--gold);
            font-size: 1.1rem;
        }

        .delivery-text-block h4 {
            font-family: 'Cormorant Garamond', serif;
            font-size: 1.4rem;
            font-weight: 500;
            color: var(--white);
            margin-bottom: 4px;
        }

        .delivery-text-block p {
            font-size: 0.8rem;
            color: rgba(255,255,255,0.5);
        }

        .delivery-options {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
        }

        .delivery-option {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 8px;
            background: rgba(255,255,255,0.95);
            border-radius: var(--r-md);
            padding: 18px 16px 14px;
            text-decoration: none;
            transition: all 0.35s ease;
            border: 2px solid transparent;
            min-height: 100px;
        }

        .delivery-option:hover {
            transform: translateY(-5px);
            box-shadow: 0 16px 40px rgba(0,0,0,0.25);
            border-color: var(--gold);
        }

        .delivery-logo-img {
            width: auto;
            max-width: 100%;
            height: 44px;
            object-fit: contain;
        }

        .delivery-option-label {
            font-size: 0.65rem;
            font-weight: 600;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            color: var(--ink-40);
        }

        .delivery-info-block {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-top: 20px;
        }

        .delivery-info-row {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 10px 14px;
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(201,160,61,0.15);
            border-radius: var(--r-sm);
            font-size: 0.8rem;
            color: rgba(255,255,255,0.75);
        }

        .delivery-info-row i {
            color: var(--gold);
            margin-top: 2px;
        }

        .delivery-info-cta {
            background: rgba(201,160,61,0.1);
            border-color: rgba(201,160,61,0.3);
        }

        .delivery-info-cta i { color: #25D366; }

        .delivery-info-row a {
            color: var(--gold-light);
            text-decoration: none;
        }

        /* Form Section */
        .form-col { position: sticky; top: 100px; }

        .form-wrap {
            background: var(--white);
            border-radius: var(--r-xl);
            padding: 50px 44px;
            box-shadow: var(--shadow-hard);
            border: 1px solid rgba(14,12,10,0.06);
            position: relative;
        }

        .form-wrap::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--gold-gradient);
        }

        .form-header { margin-bottom: 36px; }

        .form-title {
            font-family: 'Cormorant Garamond', serif;
            font-size: 2.2rem;
            font-weight: 500;
            color: var(--ink);
            margin-bottom: 8px;
        }

        .form-title em { font-style: italic; color: var(--gold-dark); }

        .form-desc {
            font-size: 0.85rem;
            color: var(--ink-40);
        }

        .form-divider {
            width: 48px;
            height: 2px;
            background: var(--gold-gradient);
            margin: 16px 0 28px;
        }

        .f-group { margin-bottom: 18px; }

        .f-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
        }

        .f-label {
            display: block;
            font-size: 0.72rem;
            font-weight: 600;
            letter-spacing: 1.2px;
            text-transform: uppercase;
            color: var(--ink-80);
            margin-bottom: 8px;
        }

        .f-label .req { color: var(--gold); }

        .f-input {
            width: 100%;
            padding: 13px 16px;
            background: var(--paper);
            border: 1.5px solid rgba(14,12,10,0.1);
            border-radius: var(--r-sm);
            font-family: 'DM Sans', sans-serif;
            font-size: 0.9rem;
            color: var(--ink);
            outline: none;
            transition: all 0.3s ease;
        }

        .f-input:focus {
            border-color: var(--gold);
            background: var(--white);
            box-shadow: 0 0 0 3px rgba(201,160,61,0.1);
        }

        textarea.f-input {
            resize: vertical;
            min-height: 96px;
        }

        .f-note {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.7rem;
            color: var(--ink-40);
            margin-top: 5px;
        }

        .f-note i { color: var(--gold); }

        .f-error {
            display: block;
            font-size: 0.7rem;
            color: #e11d48;
            margin-top: 4px;
        }

        .char-counter {
            text-align: right;
            font-size: 0.68rem;
            color: var(--ink-40);
            margin-top: 4px;
        }

        .honeypot { display: none; }

        .btn-reserve {
            width: 100%;
            padding: 16px 24px;
            background: var(--gold-gradient);
            color: var(--navy);
            border: none;
            border-radius: 50px;
            font-family: 'DM Sans', sans-serif;
            font-size: 0.85rem;
            font-weight: 700;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            transition: all 0.4s ease;
            margin-top: 6px;
            box-shadow: 0 6px 20px rgba(201,160,61,0.3);
        }

        .btn-reserve:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 32px rgba(201,160,61,0.45);
        }

        .btn-reserve:disabled {
            opacity: 0.65;
            cursor: not-allowed;
        }

        .net-error {
            display: none;
            align-items: center;
            gap: 10px;
            padding: 12px 16px;
            background: #fef2f2;
            border: 1px solid #fca5a5;
            border-radius: var(--r-sm);
            color: #dc2626;
            font-size: 0.8rem;
            margin-bottom: 20px;
        }

        .form-success {
            display: none;
            text-align: center;
            padding: 24px 0;
        }

        .success-ring {
            width: 90px;
            height: 90px;
            border-radius: 50%;
            background: linear-gradient(135deg, #ecfdf5, #d1fae5);
            border: 2px solid #6ee7b7;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 28px;
            font-size: 2.2rem;
            color: #10b981;
        }

        .success-title {
            font-family: 'Cormorant Garamond', serif;
            font-size: 2rem;
            font-weight: 500;
            color: var(--ink);
            margin-bottom: 12px;
        }

        .success-body {
            font-size: 0.9rem;
            color: var(--ink-40);
            margin-bottom: 28px;
        }

        .btn-again {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 12px 28px;
            background: var(--paper-dark);
            color: var(--ink);
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 600;
            cursor: pointer;
            border: none;
        }

        .btn-again:hover { background: var(--gold); color: var(--navy); }

        /* Footer */
        .footer {
            background: var(--navy);
            color: rgba(255,255,255,0.7);
            padding: 70px 0 0;
        }

        .footer-inner {
            max-width: 1280px;
            margin: 0 auto;
            padding: 0 60px;
        }

        .footer-grid {
            display: grid;
            grid-template-columns: 2fr 1fr 2fr;
            gap: 60px;
            padding-bottom: 60px;
            border-bottom: 1px solid rgba(255,255,255,0.08);
        }

        .footer-brand-name {
            font-family: 'Cormorant Garamond', serif;
            font-size: 2rem;
            font-weight: 600;
            color: var(--gold);
            display: block;
            margin-bottom: 14px;
        }

        .footer-tagline {
            font-size: 0.82rem;
            line-height: 1.7;
            color: rgba(255,255,255,0.5);
            margin-bottom: 28px;
            max-width: 260px;
        }

        .social-row {
            display: flex;
            gap: 12px;
        }

        .social-link {
            width: 38px;
            height: 38px;
            background: rgba(255,255,255,0.07);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: rgba(255,255,255,0.6);
            font-size: 0.85rem;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .social-link:hover {
            background: var(--gold);
            color: var(--navy);
            transform: translateY(-3px);
        }

        .footer-col-title {
            font-size: 0.7rem;
            font-weight: 600;
            letter-spacing: 2.5px;
            text-transform: uppercase;
            color: var(--gold);
            margin-bottom: 24px;
        }

        .footer-links { list-style: none; display: flex; flex-direction: column; gap: 12px; }

        .footer-links a {
            color: rgba(255,255,255,0.5);
            text-decoration: none;
            font-size: 0.85rem;
            transition: all 0.2s;
        }

        .footer-links a:hover { color: rgba(255,255,255,0.9); }

        .footer-contact-list { list-style: none; display: flex; flex-direction: column; gap: 14px; }

        .footer-contact-item {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            font-size: 0.82rem;
        }

        .footer-contact-item i {
            color: var(--gold);
            width: 16px;
            margin-top: 2px;
        }

        .footer-contact-item a {
            color: rgba(255,255,255,0.5);
            text-decoration: none;
        }

        .footer-contact-item a:hover { color: var(--gold); }

        .footer-bottom {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 24px 0;
            font-size: 0.72rem;
            color: rgba(255,255,255,0.3);
            flex-wrap: wrap;
        }

        /* WhatsApp Float */
        .wa-float {
            position: fixed;
            bottom: 32px;
            right: 32px;
            width: 58px;
            height: 58px;
            background: #25D366;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.7rem;
            box-shadow: 0 6px 24px rgba(37,211,102,0.45);
            transition: all 0.3s ease;
            z-index: 200;
            text-decoration: none;
        }

        .wa-float:hover {
            transform: scale(1.1) translateY(-3px);
            box-shadow: 0 10px 32px rgba(37,211,102,0.6);
        }

        /* Rate Limit Overlay */
        .rate-limit-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.85);
            z-index: 1000;
            display: none;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(8px);
        }
        .rate-limit-modal {
            background: var(--white);
            max-width: 450px;
            width: 90%;
            padding: 40px 32px;
            border-radius: 24px;
            text-align: center;
            box-shadow: var(--shadow-hard);
            border-top: 4px solid var(--gold);
        }
        .rate-limit-modal i {
            font-size: 3.5rem;
            color: var(--gold);
            margin-bottom: 20px;
        }
        .rate-limit-modal h3 {
            font-family: 'Cormorant Garamond', serif;
            font-size: 1.8rem;
            margin-bottom: 12px;
        }
        .rate-limit-modal p {
            color: var(--ink-80);
            margin-bottom: 24px;
            font-size: 0.9rem;
        }
        .rate-limit-modal .countdown {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--gold-dark);
        }
        .rate-limit-btn {
            background: var(--gold-gradient);
            border: none;
            padding: 12px 28px;
            border-radius: 50px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 16px;
        }

        /* Reveal Animation */
        .reveal {
            opacity: 0;
            transform: translateY(24px);
            transition: opacity 0.7s ease, transform 0.7s ease;
        }
        .reveal.in-view { opacity: 1; transform: translateY(0); }
        .reveal-delay-1 { transition-delay: 0.1s; }
        .reveal-delay-2 { transition-delay: 0.2s; }
        .reveal-delay-3 { transition-delay: 0.3s; }
        .reveal-delay-4 { transition-delay: 0.4s; }

        /* Responsive */
        @media (max-width: 1100px) {
            .page-body { padding: 80px 40px; }
            .stats-inner { padding: 0 40px; }
            .footer-inner { padding: 0 40px; }
            .hero-inner { padding: 120px 40px 80px; }
        }

        @media (max-width: 900px) {
            .content-grid { grid-template-columns: 1fr; }
            .form-col { position: static; }
            .footer-grid { grid-template-columns: 1fr 1fr; gap: 40px; }
            .stats-inner { grid-template-columns: repeat(2,1fr); }
        }

        @media (max-width: 640px) {
            .page-body { padding: 60px 20px; }
            .hero-inner { padding: 120px 24px 60px; }
            .stats-inner { padding: 0 20px; }
            .footer-inner { padding: 0 24px; }
            .form-wrap { padding: 32px 24px; }
            .f-row { grid-template-columns: 1fr; }
            .footer-grid { grid-template-columns: 1fr; gap: 32px; }
            .footer-bottom { flex-direction: column; text-align: center; }
            .delivery-options { grid-template-columns: 1fr 1fr; }
        }
    </style>
</head>
<body>

<!-- NAVBAR -->
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
            <a href="/our-story">Our Story</a>
            <a href="/contact" class="active">Contact</a>
        </div>
        <div class="navbar-reserve">
            <a href="#reservation" class="btn">Reserve a Table</a>
        </div>
        <button class="navbar-hamburger" aria-label="Toggle mobile menu" aria-expanded="false">
            <span></span><span></span><span></span>
        </button>
    </div>
</nav>

<!-- Mobile Menu -->
<div class="mobile-menu" role="dialog" aria-label="Mobile navigation">
    <a href="/">Home</a>
    <a href="/menu">Menu</a>
    <div class="mobile-menu-categories" id="mobile-menu-categories"></div>
    <a href="/our-story">Our Story</a>
    <a href="/contact" class="active">Contact</a>
    <a href="#reservation" class="btn btn-primary btn-lg">Reserve a Table</a>
</div>

<!-- HERO SECTION -->
<section class="hero">
    <div class="hero-canvas"></div>
    <div class="hero-inner">
        <div class="hero-eyebrow">
            <span class="eyebrow-line"></span>
            <span class="eyebrow-text">Furusato · ふるさと · Nairobi</span>
            <span class="eyebrow-line"></span>
        </div>

        <h1 class="hero-heading"><em>Reserve</em> Your</h1>
        <h2 class="hero-heading-2">Table</h2>

        <p class="hero-sub">
            Experience the art of authentic Japanese cuisine in the heart of Nairobi. An unforgettable culinary journey awaits you at Ring Road Parklands, Westlands.
        </p>

        <div class="hero-actions">
            <a href="#reservation" class="cta-primary">
                <i class="fas fa-calendar-check"></i>
                Book a Table
            </a>
            <a href="#find-us" class="cta-ghost">
                <i class="fas fa-map-marker-alt"></i>
                Find Us
            </a>
        </div>
    </div>

    <div class="scroll-cue">
        <div class="scroll-mouse"><div class="scroll-dot"></div></div>
        <span>Scroll</span>
    </div>
</section>

<!-- STATS BAR -->
<div class="stats-bar">
    <div class="stats-inner">
        <div class="stat-item"><div class="stat-num">24+</div><div class="stat-label">Years of Excellence</div></div>
        <div class="stat-item"><div class="stat-num">120+</div><div class="stat-label">Menu Items</div></div>
        <div class="stat-item"><div class="stat-num">9PM</div><div class="stat-label">Open Daily Until</div></div>
        <div class="stat-item"><div class="stat-num">4.8★</div><div class="stat-label">Guest Rating</div></div>
    </div>
</div>

<!-- MAIN CONTENT -->
<main class="page-body">
    <div class="content-grid">

        <!-- LEFT COLUMN -->
        <div class="contact-col">
            <div class="col-header reveal">
                <div class="label-row">
                    <span class="label-line"></span>
                    <span class="label-text">Get In Touch</span>
                </div>
                <h2 class="section-title">Find &amp; <em>Contact</em> Us</h2>
            </div>

            <div class="contact-card reveal reveal-delay-1">
                <div class="card-icon"><i class="fas fa-map-marker-alt"></i></div>
                <div class="card-body">
                    <div class="card-label">Location</div>
                    <div class="card-value">Ring Road Parklands, Westlands, Nairobi, Kenya</div>
                </div>
            </div>

            <div class="contact-card reveal reveal-delay-2">
                <div class="card-icon"><i class="fas fa-clock"></i></div>
                <div class="card-body">
                    <div class="card-label">Opening Hours</div>
                    <div class="card-value">Monday – Sunday · 12:00 PM – 9:00 PM</div>
                </div>
            </div>

            <div class="contact-card reveal reveal-delay-3">
                <div class="card-icon"><i class="fas fa-phone-alt"></i></div>
                <div class="card-body">
                    <div class="card-label">Call Us</div>
                    <div class="card-value">
                        <a href="tel:0722488706">0722 488 706</a> / <a href="tel:0734639203">0734 639 203</a>
                    </div>
                </div>
            </div>

            <div class="contact-card reveal reveal-delay-4">
                <div class="card-icon"><i class="fab fa-whatsapp"></i></div>
                <div class="card-body">
                    <div class="card-label">WhatsApp</div>
                    <div class="card-value">
                        <a href="<?= htmlspecialchars(wa_link("Hello Furusato Japanese Restaurant,\n\nI would like to make a reservation.\n\nThank you.")) ?>" target="_blank" rel="noopener noreferrer">0734 639 203</a>
                    </div>
                </div>
            </div>

            <!-- PAYMENT INFORMATION (informational only — no online payment) -->
            <div class="contact-card reveal">
                <div class="card-icon"><i class="fas fa-credit-card"></i></div>
                <div class="card-body">
                    <div class="card-label">Payment</div>
                    <div class="card-value" style="font-size: 0.85rem; line-height: 1.6;">
                        For payment information and available payment methods, please contact our team on WhatsApp or speak with our restaurant staff.
                    </div>
                </div>
            </div>

            <div class="contact-card reveal">
                <div class="card-icon"><i class="fas fa-envelope"></i></div>
                <div class="card-body">
                    <div class="card-label">Email</div>
                    <div class="card-value">
                        <a href="mailto:furusatoreservation@gmail.com">furusatoreservation@gmail.com</a>
                    </div>
                </div>
            </div>

            <!-- MAP SECTION -->
            <div class="map-section reveal" id="find-us">
                <div class="map-topbar">
                    <div class="map-dot map-dot-1"></div>
                    <div class="map-dot map-dot-2"></div>
                    <div class="map-dot map-dot-3"></div>
                    <div class="map-address">
                        <i class="fas fa-location-dot"></i>
                        Ring Road Parklands, Westlands, Nairobi, Kenya
                    </div>
                </div>
                <iframe 
                    class="map-embed"
                    src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3988.698479!2d36.8025941!3d-1.2660993!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x182f172a8b6c44cd%3A0x68a27b11f63e3b2f!2sFurusato%20Japanese%20Restaurant!5e0!3m2!1sen!2ske!4v1700000000000!5m2!1sen!2ske"
                    width="100%" 
                    height="280" 
                    style="border:0; display:block;" 
                    allowfullscreen="" 
                    loading="lazy" 
                    title="Furusato Japanese Restaurant Location">
                </iframe>
                <div style="text-align: center; padding: 12px; background: white; border-top: 1px solid rgba(0,0,0,0.05);">
                    <a href="https://maps.google.com/?q=Furusato+Japanese+Restaurant,+Ring+Road+Parklands,+Westlands,+Nairobi" 
                       target="_blank" 
                       style="display: inline-flex; align-items: center; gap: 8px; color: var(--gold-dark); font-size: 0.8rem; text-decoration: none;">
                        <i class="fas fa-external-link-alt"></i> Open in Google Maps <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            </div>

            <!-- DELIVERY SECTION -->
            <div class="delivery-section reveal">
                <div class="delivery-header">
                    <div class="delivery-icon-wrap"><i class="fas fa-motorcycle"></i></div>
                    <div class="delivery-text-block">
                        <h4>Order for Delivery</h4>
                        <p>Enjoy Furusato at home. Get your favourites delivered straight to your door.</p>
                    </div>
                </div>

                <div class="delivery-options">
                    <a href="https://www.ubereats.com/ke/store/furusato-japanese-restaurant/FGTL3T6JQRqcydZR-3jmeg" target="_blank" class="delivery-option">
                        <img src="/assets/images/ubereats.png" alt="Uber Eats" class="delivery-logo-img" onerror="this.style.display='none'; this.parentNode.querySelector('.delivery-option-label').textContent='Uber Eats';">
                        <span class="delivery-option-label">Uber Eats</span>
                    </a>
                    <a href="https://glovoapp.com/ke/en/nairobi/furusato-japanese-restaurant-nbo/" target="_blank" class="delivery-option">
                        <img src="/assets/images/glovo.png" alt="Glovo" class="delivery-logo-img" onerror="this.style.display='none'; this.parentNode.querySelector('.delivery-option-label').textContent='Glovo';">
                        <span class="delivery-option-label">Glovo</span>
                    </a>
                </div>

                <div class="delivery-info-block">
                    <div class="delivery-info-row"><i class="fas fa-gift"></i><span><strong>Free delivery</strong> on orders above <strong>Ksh 3,000</strong></span></div>
                    <div class="delivery-info-row"><i class="fas fa-route"></i><span>Delivery fee of <strong>Ksh 300 – 500</strong> applies for orders under Ksh 3,000 (varies by distance)</span></div>
                    <div class="delivery-info-row delivery-info-cta"><i class="fab fa-whatsapp"></i><span>Call or WhatsApp <a href="<?= htmlspecialchars(wa_link("Hello Furusato Japanese Restaurant,\n\nI would like to enquire about delivery.\n\nThank you.")) ?>" target="_blank" rel="noopener noreferrer"><strong>0722 488 706</strong></a> to enquire about delivery</span></div>
                </div>
            </div>
        </div>

        <!-- RIGHT COLUMN - RESERVATION FORM -->
        <div class="form-col" id="reservation">
            <div class="form-wrap reveal">
                <div class="form-header">
                    <div class="label-row">
                        <span class="label-line"></span>
                        <span class="label-text">Reservations</span>
                    </div>
                    <h3 class="form-title">Make a <em>Reservation</em></h3>
                    <div class="form-divider"></div>
                    <p class="form-desc">Book your table and let us craft an unforgettable dining experience for you.</p>
                </div>

                <div id="net-error" class="net-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <span id="net-error-msg">Something went wrong. Please try again.</span>
                </div>

                <form id="reservation-form" novalidate>
                    <!-- CSRF Token Field -->
                    <input type="hidden" name="csrf_token" id="csrf_token_input" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

                    <div class="f-group">
                        <label class="f-label" for="reservation-name">Full Name <span class="req">*</span></label>
                        <input type="text" id="reservation-name" class="f-input" placeholder="Your full name" required autocomplete="name">
                        <span class="f-error" id="name-error"></span>
                    </div>

                    <div class="f-row">
                        <div class="f-group">
                            <label class="f-label" for="reservation-email">Email <span class="req">*</span></label>
                            <input type="email" id="reservation-email" class="f-input" placeholder="you@email.com" required autocomplete="email">
                            <span class="f-error" id="email-error"></span>
                        </div>
                        <div class="f-group">
                            <label class="f-label" for="reservation-phone">Phone <span class="req">*</span></label>
                            <input type="tel" id="reservation-phone" class="f-input" placeholder="0722 488 706" required autocomplete="tel">
                            <span class="f-error" id="phone-error"></span>
                        </div>
                    </div>

                    <div class="f-row">
                        <div class="f-group">
                            <label class="f-label" for="reservation-date">Date <span class="req">*</span></label>
                            <input type="date" id="reservation-date" class="f-input" required>
                            <span class="f-error" id="date-error"></span>
                        </div>
                        <div class="f-group">
                            <label class="f-label" for="reservation-time">Time <span class="req">*</span></label>
                            <input type="time" id="reservation-time" class="f-input" required>
                            <div class="f-note"><i class="fas fa-info-circle"></i> 12:00 PM – 9:00 PM</div>
                            <span class="f-error" id="time-error"></span>
                        </div>
                    </div>

                    <div class="f-group">
                        <label class="f-label" for="reservation-guests">Number of Guests <span class="req">*</span></label>
                        <select id="reservation-guests" class="f-input" required>
                            <option value="" disabled selected>Select guests</option>
                        </select>
                        <span class="f-error" id="guests-error"></span>
                    </div>

                    <div class="f-group">
                        <label class="f-label" for="reservation-requests">Special Requests</label>
                        <textarea id="reservation-requests" class="f-input" rows="4" placeholder="Dietary requirements, allergies, special occasions, seating preferences…"></textarea>
                        <div class="char-counter"><span id="char-count">0</span> / 500</div>
                        <span class="f-error" id="requests-error"></span>
                    </div>

                    <!-- Honeypot -->
                    <div class="honeypot" aria-hidden="true">
                        <input type="text" id="reservation-website" name="website" tabindex="-1" autocomplete="off">
                    </div>

                    <button type="submit" class="btn-reserve" id="reservation-submit">
                        <i class="fas fa-calendar-check"></i> Reserve My Table
                    </button>
                </form>

                <!-- Success -->
                <div id="reservation-success" class="form-success">
                    <div class="success-ring"><i class="fas fa-check"></i></div>
                    <h3 class="success-title">Reservation Received</h3>
                    <p class="success-body">
                        Thank you for choosing Furusato.<br>
                        We will confirm your table within 24 hours.<br><br>
                        Questions? Call us: <a href="tel:0722488706">0722 488 706</a>
                    </p>
                    <button class="btn-again" id="btn-new-reservation">
                        <i class="fas fa-plus"></i> Another Reservation
                    </button>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- FOOTER -->
<footer class="footer">
    <div class="footer-inner">
        <div class="footer-grid">
            <div>
                <span class="footer-brand-name">Furusato</span>
                <p class="footer-tagline">Where tradition meets taste. Authentic Japanese cuisine crafted with passion in the heart of Nairobi.</p>
                <div class="social-row">
                    <a href="https://web.facebook.com/FurusatoNairobi/" class="social-link" target="_blank"><i class="fab fa-facebook-f"></i></a>
                    <a href="https://www.instagram.com/furusato_japanese_restaurant/" class="social-link" target="_blank"><i class="fab fa-instagram"></i></a>
                </div>
            </div>

            <div>
                <div class="footer-col-title">Quick Links</div>
                <ul class="footer-links">
                    <li><a href="/">Home</a></li>
                    <li><a href="/menu">Our Menu</a></li>
                    <li><a href="/our-story">Our Story</a></li>
                    <li><a href="/contact">Contact Us</a></li>
                    <li><a href="#reservation">Reservations</a></li>
                </ul>
            </div>

            <div>
                <div class="footer-col-title">Contact Info</div>
                <ul class="footer-contact-list">
                    <li class="footer-contact-item"><i class="fas fa-map-marker-alt"></i><span>Ring Road Parklands, Westlands, Nairobi</span></li>
                    <li class="footer-contact-item"><i class="fas fa-phone-alt"></i><a href="tel:+254722488706">0722 488 706</a></li>
<li class="footer-contact-item"><i class="fab fa-whatsapp"></i><a href="https://wa.me/<?= htmlspecialchars(get_whatsapp_number()) ?>" target="_blank" rel="noopener noreferrer">0734 639 203</a></li>
                    <li class="footer-contact-item"><i class="fas fa-envelope"></i><a href="mailto:furusatoreservation@gmail.com">furusatoreservation@gmail.com</a></li>
                    <li class="footer-contact-item"><i class="fas fa-clock"></i><span>Daily: 12:00 PM – 9:00 PM</span></li>
                </ul>
            </div>
        </div>

        <div class="footer-bottom">
            <span>&copy; <?= date('Y') ?> Furusato Japanese Restaurant. All rights reserved.</span>
            <span>Authentic Japanese Cuisine · Nairobi, Kenya</span>
        </div>
    </div>
</footer>

<!-- WhatsApp Float -->
<a href="<?= htmlspecialchars(wa_link("Hello Furusato Japanese Restaurant,\n\nI would like to make a reservation.\n\nThank you.")) ?>" class="wa-float" target="_blank" rel="noopener noreferrer" aria-label="Chat on WhatsApp">
    <i class="fab fa-whatsapp"></i>
</a>

<!-- Rate Limit Overlay -->
<div id="rate-limit-overlay" class="rate-limit-overlay">
    <div class="rate-limit-modal">
        <i class="fas fa-clock"></i>
        <h3>Too Many Attempts</h3>
        <p>You've made too many reservation attempts. Please wait <span id="countdown-timer" class="countdown">5:00</span> before trying again.</p>
        <button class="rate-limit-btn" onclick="location.reload()">Refresh Page</button>
    </div>
</div>

<!-- Scripts -->
<script src="/assets/js/main.js?v=<?= $mainJsVersion ?>"></script>
<script src="/assets/js/contact.js?v=<?= $contactJsVersion ?>"></script>

<!-- Only essential non-conflicting scripts remain -->
<script>
/* Particles - only if element exists */
(function() {
    const container = document.getElementById('particles');
    if (!container) return;
    const count = window.innerWidth < 640 ? 10 : 20;
    for (let i = 0; i < count; i++) {
        const el = document.createElement('div');
        el.className = 'particle';
        el.style.left = Math.random() * 100 + '%';
        el.style.animationDuration = (Math.random() * 15 + 10) + 's';
        el.style.animationDelay = (Math.random() * 10) + 's';
        container.appendChild(el);
    }
})();

/* Scroll Reveal */
(function() {
    const obs = new IntersectionObserver((entries) => {
        entries.forEach(e => {
            if (e.isIntersecting) {
                e.target.classList.add('in-view');
                obs.unobserve(e.target);
            }
        });
    }, { threshold: 0.12 });
    document.querySelectorAll('.reveal').forEach(el => obs.observe(el));
})();

/* Populate Guests Dropdown */
(function() {
    const sel = document.getElementById('reservation-guests');
    if (!sel) return;
    for (let i = 1; i <= 20; i++) {
        const opt = document.createElement('option');
        opt.value = i;
        opt.textContent = i + (i === 1 ? ' Guest' : ' Guests');
        sel.appendChild(opt);
    }
})();

/* Character Counter */
(function() {
    const ta = document.getElementById('reservation-requests');
    const cc = document.getElementById('char-count');
    if (!ta || !cc) return;
    ta.addEventListener('input', function() {
        let len = this.value.length;
        if (len > 500) { this.value = this.value.slice(0, 500); len = 500; }
        cc.textContent = len;
        cc.style.color = len >= 490 ? '#e11d48' : '';
    });
})();

/* Smooth Scroll */
document.querySelectorAll('a[href^="#"]').forEach(a => {
    a.addEventListener('click', function(e) {
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
            e.preventDefault();
            target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    });
});

/* Default Date & Time */
(function() {
    const dateInput = document.getElementById('reservation-date');
    const timeInput = document.getElementById('reservation-time');
    if (dateInput) {
        const tomorrow = new Date();
        tomorrow.setDate(tomorrow.getDate() + 1);
        dateInput.min = tomorrow.toISOString().split('T')[0];
        dateInput.value = tomorrow.toISOString().split('T')[0];
    }
    if (timeInput) timeInput.value = '19:00';
})();
</script>
</body>
</html>