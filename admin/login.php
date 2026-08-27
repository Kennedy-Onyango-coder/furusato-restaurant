<?php
/**
 * admin/login.php — Furusato Admin Panel
 * SECURED VERSION - Full 2FA with QR code setup, backup codes
 * FIXED - Working 2FA verification with proper API endpoints
 */

@ob_start();
@error_reporting(0);
@ini_set('display_errors', '0');
@ini_set('log_errors', '1');

// Session params — Strict for security
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.cookie_secure', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.gc_maxlifetime', 7200);
session_set_cookie_params([
    'lifetime' => 7200,
    'path' => '/',
    'domain' => '',
    'samesite' => 'Strict',
    'httponly' => true,
    'secure' => true,
]);
session_start();

require_once __DIR__ . '/../includes/functions.php';

$admin = getJsonData('admin');
$totpEnabled = !empty($admin['totpEnabled']);
$show2faBanner = !$totpEnabled;

if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: /admin/dashboard.php');
    exit;
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

$successMessage = '';
if (isset($_GET['success'])) {
    if ($_GET['success'] === '2fa_enabled') {
        $successMessage = 'Two-factor authentication has been set up successfully. You may now log in.';
    }
    if ($_GET['success'] === 'password_changed') {
        $successMessage = 'Password changed successfully. Please log in again.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
    <meta name="robots" content="noindex, nofollow">
    <title>Login — Furusato Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500;600;700&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js" defer></script>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #0D1B2A;
            font-family: 'Inter', sans-serif;
            color: #E0E1DD;
            overflow: hidden;
            position: relative;
        }

        body::before {
            content: '';
            position: absolute;
            inset: 0;
            background: radial-gradient(ellipse at 20% 50%, rgba(120, 90, 60, 0.08) 0%, transparent 50%),
                        radial-gradient(ellipse at 80% 20%, rgba(180, 140, 90, 0.06) 0%, transparent 40%),
                        radial-gradient(ellipse at 50% 80%, rgba(13, 27, 42, 0.5) 0%, transparent 60%);
            pointer-events: none;
        }

        body::after {
            content: '\6545\u90F7';
            position: fixed;
            bottom: -40px;
            right: -20px;
            font-family: 'Cormorant Garamond', serif;
            font-size: clamp(200px, 30vw, 400px);
            color: rgba(180, 140, 90, 0.03);
            pointer-events: none;
            line-height: 1;
            user-select: none;
        }

        .login-card {
            width: 100%;
            max-width: 450px;
            background: linear-gradient(165deg, rgba(29, 53, 71, 0.95) 0%, rgba(20, 38, 58, 0.98) 100%);
            border: 1px solid rgba(180, 140, 90, 0.15);
            border-radius: 16px;
            padding: 48px 40px 40px;
            position: relative;
            z-index: 1;
            box-shadow: 0 4px 24px rgba(0, 0, 0, 0.4), 0 0 0 1px rgba(180, 140, 90, 0.05), inset 0 1px 0 rgba(255, 255, 255, 0.03);
            backdrop-filter: blur(20px);
        }

        .brand { text-align: center; margin-bottom: 36px; }
        .brand-kanji { font-family: 'Cormorant Garamond', serif; font-size: 14px; font-weight: 400; letter-spacing: 4px; color: rgba(180, 140, 90, 0.6); text-transform: uppercase; margin-bottom: 8px; }
        .brand-name { font-family: 'Cormorant Garamond', serif; font-size: 32px; font-weight: 600; color: #C8A96E; letter-spacing: 2px; margin-bottom: 4px; }
        .brand-subtitle { font-size: 12px; font-weight: 300; color: rgba(224, 225, 221, 0.4); letter-spacing: 3px; text-transform: uppercase; }
        .divider { height: 1px; background: linear-gradient(90deg, transparent, rgba(200, 169, 110, 0.2), transparent); margin: 0 0 28px; }

        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.85);
            backdrop-filter: blur(8px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }
        .modal-overlay.active { display: flex; }
        .modal-container {
            background: linear-gradient(135deg, #1a2a3a 0%, #0d1b2a 100%);
            border: 1px solid rgba(212, 175, 122, 0.3);
            border-radius: 20px;
            max-width: 500px;
            width: 90%;
            max-height: 85vh;
            overflow-y: auto;
            padding: 30px;
            position: relative;
            animation: modalFadeIn 0.3s ease;
        }
        @keyframes modalFadeIn {
            from { opacity: 0; transform: scale(0.95); }
            to { opacity: 1; transform: scale(1); }
        }
        .modal-header { text-align: center; margin-bottom: 24px; }
        .modal-header h2 { color: #d4af7a; font-size: 24px; margin-bottom: 8px; }
        .modal-header p { color: rgba(255,255,255,0.6); font-size: 13px; }
        .qr-container {
            background: white;
            padding: 20px;
            border-radius: 16px;
            display: inline-block;
            margin: 0 auto 20px;
            text-align: center;
            width: 100%;
        }
        .qr-container img {
            width: 200px;
            height: 200px;
            display: block;
            margin: 0 auto;
        }
        .secret-key {
            background: rgba(0,0,0,0.3);
            padding: 12px;
            border-radius: 8px;
            text-align: center;
            margin-bottom: 20px;
            font-family: monospace;
            font-size: 14px;
            letter-spacing: 1px;
            word-break: break-all;
        }
        .backup-codes {
            background: rgba(212, 175, 122, 0.1);
            border: 1px solid rgba(212, 175, 122, 0.3);
            border-radius: 12px;
            padding: 16px;
            margin: 20px 0;
        }
        .backup-codes h4 { color: #d4af7a; margin-bottom: 12px; font-size: 14px; }
        .codes-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 8px;
        }
        .code-item {
            font-family: monospace;
            font-size: 14px;
            background: rgba(0,0,0,0.3);
            padding: 6px 10px;
            border-radius: 6px;
            text-align: center;
            letter-spacing: 1px;
        }
        .warning-text {
            color: #f0a500;
            font-size: 11px;
            margin-top: 10px;
            text-align: center;
        }
        .modal-buttons { display: flex; gap: 12px; margin-top: 20px; }
        .modal-buttons .btn { flex: 1; }
        .btn-outline {
            background: transparent;
            border: 1px solid rgba(212, 175, 122, 0.5);
            color: #d4af7a;
        }
        .btn-outline:hover { background: rgba(212, 175, 122, 0.1); }

        .banner-2fa {
            background: rgba(200, 169, 110, 0.1);
            border: 1px solid rgba(200, 169, 110, 0.25);
            border-radius: 10px;
            padding: 14px 16px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            flex-wrap: wrap;
        }
        .banner-2fa-text { font-size: 13px; line-height: 1.5; color: rgba(224, 225, 221, 0.75); flex: 1; }
        .banner-2fa-text strong { color: #C8A96E; }
        .btn-banner {
            width: auto;
            padding: 8px 16px;
            font-size: 12px;
            background: #C8A96E;
            color: #0D1B2A;
            border: none;
            border-radius: 8px;
            cursor: pointer;
        }

        .success-message {
            background: rgba(76, 175, 80, 0.1);
            border: 1px solid rgba(76, 175, 80, 0.25);
            border-radius: 10px;
            padding: 14px 16px;
            margin-bottom: 24px;
            font-size: 13px;
            color: rgba(165, 214, 167, 0.9);
        }

        .form-group { margin-bottom: 20px; }
        .form-label { display: block; font-size: 12px; font-weight: 500; color: rgba(224, 225, 221, 0.55); letter-spacing: 1px; text-transform: uppercase; margin-bottom: 8px; }
        .form-input {
            width: 100%;
            padding: 14px 16px;
            background: rgba(13, 27, 42, 0.6);
            border: 1px solid rgba(180, 140, 90, 0.12);
            border-radius: 10px;
            color: #E0E1DD;
            font-family: 'Inter', sans-serif;
            font-size: 14px;
            transition: border-color 0.25s, box-shadow 0.25s;
            outline: none;
        }
        .form-input:focus { border-color: rgba(200, 169, 110, 0.45); box-shadow: 0 0 0 3px rgba(200, 169, 110, 0.08); }
        .form-input.error { border-color: rgba(239, 83, 80, 0.5); box-shadow: 0 0 0 3px rgba(239, 83, 80, 0.08); }
        .totp-input { text-align: center; font-size: 24px; font-weight: 500; letter-spacing: 12px; padding-left: 28px; }

        .btn {
            width: 100%;
            padding: 14px 20px;
            border: none;
            border-radius: 10px;
            font-family: 'Inter', sans-serif;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.25s;
            letter-spacing: 0.5px;
        }
        .btn-primary { background: linear-gradient(135deg, #C8A96E 0%, #A88B4A 100%); color: #0D1B2A; box-shadow: 0 2px 12px rgba(200, 169, 110, 0.2); }
        .btn-primary:hover { background: linear-gradient(135deg, #DFC08A 0%, #C8A96E 100%); box-shadow: 0 4px 20px rgba(200, 169, 110, 0.3); transform: translateY(-1px); }
        .btn-primary:disabled { opacity: 0.5; cursor: not-allowed; }

        .error-message {
            background: rgba(239, 83, 80, 0.1);
            border: 1px solid rgba(239, 83, 80, 0.2);
            border-radius: 10px;
            padding: 12px 16px;
            margin-bottom: 20px;
            font-size: 13px;
            color: rgba(239, 154, 154, 0.95);
            display: none;
            align-items: center;
            gap: 8px;
        }
        .error-message.visible { display: flex; }

        .step { display: none; }
        .step.active { display: block; }
        .step-indicator { display: flex; align-items: center; justify-content: center; gap: 8px; margin-bottom: 24px; }
        .step-dot { width: 8px; height: 8px; border-radius: 50%; background: rgba(180, 140, 90, 0.2); transition: all 0.3s; }
        .step-dot.active { background: #C8A96E; box-shadow: 0 0 8px rgba(200, 169, 110, 0.3); }
        .step-line { width: 32px; height: 1px; background: rgba(180, 140, 90, 0.15); }
        .step-label { font-size: 11px; color: rgba(224, 225, 221, 0.35); letter-spacing: 1px; text-transform: uppercase; text-align: center; margin-bottom: 20px; }
        .spinner { display: inline-block; width: 16px; height: 16px; border: 2px solid rgba(13, 27, 42, 0.3); border-top-color: #0D1B2A; border-radius: 50%; animation: spin 0.6s linear infinite; vertical-align: middle; margin-right: 6px; }
        @keyframes spin { to { transform: rotate(360deg); } }
        .login-footer { text-align: center; margin-top: 28px; font-size: 11px; color: rgba(224, 225, 221, 0.2); letter-spacing: 1px; }

        @media (max-width: 480px) {
            .login-card { margin: 16px; padding: 36px 24px 32px; }
            .brand-name { font-size: 26px; }
            .totp-input { letter-spacing: 8px; padding-left: 20px; }
            .codes-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<div class="login-card" id="loginCard">
    <div class="brand">
        <div class="brand-kanji">故 郷</div>
        <div class="brand-name">Furusato</div>
        <div class="brand-subtitle">Admin Panel</div>
    </div>
    <div class="divider"></div>

    <?php if ($successMessage): ?>
    <div class="success-message" id="successMessage"><?= htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <?php if (!$totpEnabled && !isset($_GET['skip_2fa'])): ?>
    <div class="banner-2fa" id="banner2fa">
        <div class="banner-2fa-text">
            <strong>🔐 Enhance Security</strong><br>
            Set up two-factor authentication to protect your account.
        </div>
        <button type="button" class="btn-banner" id="setup2faBtn">Setup 2FA</button>
    </div>
    <?php endif; ?>

    <div class="error-message" id="errorMessage">
        <svg class="error-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
        <span id="errorText">Invalid credentials</span>
    </div>

    <div class="step-indicator" id="stepIndicator">
        <div class="step-dot active" id="dot1"></div>
        <div class="step-line"></div>
        <div class="step-dot" id="dot2"></div>
    </div>

    <div class="step active" id="step1">
        <div class="step-label">Sign in to your account</div>
        <form id="loginForm">
            <div class="form-group">
                <label class="form-label" for="email">Email</label>
                <input type="email" id="email" name="email" class="form-input" placeholder="admin@furusatorestaurant.com" autocomplete="email" required autofocus>
            </div>
            <div class="form-group">
                <label class="form-label" for="password">Password</label>
                <input type="password" id="password" name="password" class="form-input" placeholder="Enter your password" autocomplete="current-password" required>
            </div>
            <button type="submit" class="btn btn-primary" id="loginBtn">Sign In</button>
        </form>
    </div>

    <div class="step" id="step2">
        <div class="step-label">Enter authentication code</div>
        <form id="totpForm">
            <div class="form-group">
                <label class="form-label" for="totpCode">6-Digit Code</label>
                <input type="text" inputmode="numeric" pattern="[0-9]*" maxlength="6" id="totpCode" name="totp_code" class="form-input totp-input" placeholder="------" autocomplete="one-time-code" required autofocus>
            </div>
            <div class="form-group">
                <label class="form-label" for="backupCode">Backup Code <span style="font-weight:normal;">(optional)</span></label>
                <input type="text" id="backupCode" name="backup_code" class="form-input" placeholder="XXXX-XXXX" autocomplete="off">
            </div>
            <button type="submit" class="btn btn-primary" id="totpBtn">Verify</button>
        </form>
        <button type="button" class="btn" id="backBtn" style="background: transparent; color: rgba(224,225,221,0.4); margin-top: 8px; font-size: 12px;">Back</button>
    </div>

    <div class="login-footer">Furusato Japanese Restaurant &copy; <?= date('Y') ?></div>
</div>

<div class="modal-overlay" id="setupModal">
    <div class="modal-container">
        <div class="modal-header">
            <h2>🔐 Two-Factor Authentication</h2>
            <p>Scan the QR code with Google Authenticator or similar app</p>
        </div>
        <div id="qrContent" style="text-align: center;">
            <div class="qr-container" id="qrContainer">
                <div class="spinner"></div> Loading QR code...
            </div>
        </div>
        <div class="secret-key" id="secretContainer"></div>
        <div class="backup-codes" id="backupCodesContainer" style="display: none;">
            <h4>📋 Backup Codes (Save These!)</h4>
            <div class="codes-grid" id="codesGrid"></div>
            <p class="warning-text">⚠️ Store these codes securely. Each code can be used only once.</p>
        </div>
        <div class="form-group">
            <label class="form-label">Enter 6-digit code from authenticator</label>
            <input type="text" id="verifyCode" class="form-input" maxlength="6" placeholder="000000" style="text-align: center; font-size: 20px; letter-spacing: 4px;">
        </div>
        <div class="modal-buttons">
            <button type="button" class="btn btn-outline" id="cancelSetupBtn">Cancel</button>
            <button type="button" class="btn btn-primary" id="verifySetupBtn">Verify & Enable</button>
        </div>
    </div>
</div>

<script>
(function() {
    const loginForm = document.getElementById('loginForm');
    const totpForm = document.getElementById('totpForm');
    const step1 = document.getElementById('step1');
    const step2 = document.getElementById('step2');
    const dot1 = document.getElementById('dot1');
    const dot2 = document.getElementById('dot2');
    const emailInput = document.getElementById('email');
    const passwordInput = document.getElementById('password');
    const totpInput = document.getElementById('totpCode');
    const backupCodeInput = document.getElementById('backupCode');
    const loginBtn = document.getElementById('loginBtn');
    const totpBtn = document.getElementById('totpBtn');
    const backBtn = document.getElementById('backBtn');
    const errorEl = document.getElementById('errorMessage');
    const errorText = document.getElementById('errorText');
    const successMessage = document.getElementById('successMessage');
    const setupModal = document.getElementById('setupModal');
    const setup2faBtn = document.getElementById('setup2faBtn');
    const cancelSetupBtn = document.getElementById('cancelSetupBtn');
    const verifySetupBtn = document.getElementById('verifySetupBtn');
    const verifyCodeInput = document.getElementById('verifyCode');
    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

    let currentBackupCodes = [];

    function showError(message) {
        errorText.textContent = message;
        errorEl.classList.add('visible');
    }

    function hideError() {
        errorEl.classList.remove('visible');
        document.querySelectorAll('.form-input').forEach(el => el.classList.remove('error'));
    }

    function setLoading(btn, loading) {
        btn.disabled = loading;
        if (loading) {
            btn._originalText = btn.textContent;
            btn.innerHTML = '<span class="spinner"></span> Please wait...';
        } else {
            btn.textContent = btn._originalText || 'Submit';
        }
    }

    function showStep(stepNum) {
        step1.classList.remove('active');
        step2.classList.remove('active');
        dot1.classList.remove('active');
        dot2.classList.remove('active');
        if (stepNum === 1) {
            step1.classList.add('active');
            dot1.classList.add('active');
            emailInput.focus();
        } else {
            step2.classList.add('active');
            dot1.classList.add('active');
            dot2.classList.add('active');
            totpInput.focus();
        }
        if (typeof gsap !== 'undefined') {
            gsap.fromTo(stepNum === 1 ? step1 : step2, { opacity: 0, y: 12 }, { opacity: 1, y: 0, duration: 0.4 });
        }
    }

    totpInput.addEventListener('input', function() {
        this.value = this.value.replace(/[^0-9]/g, '').slice(0, 6);
        if (this.value.length === 6 && !totpBtn.disabled) {
            totpForm.dispatchEvent(new Event('submit', { cancelable: true }));
        }
    });

    backBtn.addEventListener('click', () => { hideError(); showStep(1); });
    emailInput.addEventListener('input', hideError);
    passwordInput.addEventListener('input', hideError);
    totpInput.addEventListener('input', hideError);

    // Helper function for API calls
    async function apiCall(url, options) {
        try {
            const response = await fetch(url, {
                ...options,
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                    ...(options.headers || {})
                }
            });
            
            const text = await response.text();
            let data;
            try {
                data = JSON.parse(text);
            } catch (e) {
                console.error('Invalid JSON response:', text);
                throw new Error('Server returned invalid response format');
            }
            
            return { status: response.status, data };
        } catch (error) {
            console.error('API call failed:', error);
            throw error;
        }
    }

    // Login
    loginForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        hideError();
        
        const email = emailInput.value.trim();
        const password = passwordInput.value;
        
        if (!email || !password) {
            showError('Email and password are required');
            if (!email) emailInput.classList.add('error');
            if (!password) passwordInput.classList.add('error');
            return;
        }
        
        setLoading(loginBtn, true);
        
        try {
            const result = await apiCall('/api/auth.php?action=login', {
                method: 'POST',
                body: JSON.stringify({ email, password })
            });
            
            setLoading(loginBtn, false);
            
            if (result.data.requireTotp) {
                showStep(2);
                return;
            }
            
            if (result.data.success && result.data.redirect) {
                window.location.href = result.data.redirect;
                return;
            }
            
            if (result.data.error) {
                showError(result.data.error);
                emailInput.classList.add('error');
                passwordInput.classList.add('error');
                return;
            }
            
            showError('Invalid credentials');
        } catch (err) {
            setLoading(loginBtn, false);
            console.error('Login error:', err);
            showError(err.message || 'Network error. Please try again.');
        }
    });

    // 2FA Verification
    totpForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        hideError();
        
        const code = totpInput.value.replace(/[^0-9]/g, '').slice(0, 6);
        const backupCode = backupCodeInput.value.trim().toUpperCase();
        
        if (code.length !== 6 && !backupCode) {
            showError('Please enter a 6-digit code or backup code.');
            totpInput.classList.add('error');
            return;
        }
        
        setLoading(totpBtn, true);
        
        const payload = { totp_code: code };
        if (backupCode) payload.backup_code = backupCode;
        
        try {
            const result = await apiCall('/api/auth.php?action=verify_totp', {
                method: 'POST',
                body: JSON.stringify(payload)
            });
            
            setLoading(totpBtn, false);
            
            if (result.data.success && result.data.redirect) {
                window.location.href = result.data.redirect;
                return;
            }
            
            if (result.data.error) {
                showError(result.data.error);
                totpInput.classList.add('error');
                totpInput.value = '';
                totpInput.focus();
                return;
            }
            
            showError('Invalid code. Please try again.');
            totpInput.value = '';
            totpInput.focus();
        } catch (err) {
            setLoading(totpBtn, false);
            console.error('Verification error:', err);
            showError(err.message || 'Verification failed. Please try again.');
        }
    });

    // 2FA Setup Modal
    if (setup2faBtn) {
        setup2faBtn.addEventListener('click', async function() {
            setupModal.classList.add('active');
            document.body.style.overflow = 'hidden';
            
            const qrContainer = document.getElementById('qrContainer');
            qrContainer.innerHTML = '<div class="spinner"></div> Loading QR code...';
            
            try {
                const result = await apiCall('/api/auth.php?action=setup_2fa', {
                    method: 'GET'
                });
                
                if (result.data.success) {
                    const qrUrl = result.data.qrCodeUrl || result.data.qr_url;
                    const secret = result.data.secret;
                    
                    // Create image with fallback
                    const img = document.createElement('img');
                    img.src = qrUrl;
                    img.alt = 'QR Code';
                    img.style.width = '200px';
                    img.style.height = '200px';
                    img.onerror = function() {
                        qrContainer.innerHTML = `
                            <div style="text-align: center;">
                                <p style="color: #C8A96E; margin-bottom: 10px;">📱 Manual Entry Required</p>
                                <p style="font-family: monospace; font-size: 16px; word-break: break-all; background: #1a1a2e; padding: 12px; border-radius: 8px;">
                                    ${secret}
                                </p>
                                <p style="font-size: 12px; margin-top: 10px;">Enter this secret in Google Authenticator manually</p>
                            </div>
                        `;
                    };
                    
                    qrContainer.innerHTML = '';
                    qrContainer.appendChild(img);
                    document.getElementById('secretContainer').innerHTML = `Secret: <strong>${secret}</strong>`;
                    
                    currentBackupCodes = result.data.backupCodes || result.data.backup_codes || [];
                    if (currentBackupCodes.length) {
                        document.getElementById('backupCodesContainer').style.display = 'block';
                        const grid = document.getElementById('codesGrid');
                        grid.innerHTML = '';
                        currentBackupCodes.forEach(code => {
                            const div = document.createElement('div');
                            div.className = 'code-item';
                            div.textContent = code;
                            grid.appendChild(div);
                        });
                    }
                } else {
                    qrContainer.innerHTML = `<p style="color:red;">Error: ${result.data.error}</p>`;
                }
            } catch (err) {
                console.error('QR load error:', err);
                qrContainer.innerHTML = `<p style="color:red;">Failed to load QR code: ${err.message}</p>`;
            }
        });
    }

    function closeModal() {
        setupModal.classList.remove('active');
        document.body.style.overflow = '';
        verifyCodeInput.value = '';
    }

    if (cancelSetupBtn) {
        cancelSetupBtn.addEventListener('click', closeModal);
    }
    
    setupModal.addEventListener('click', function(e) { if (e.target === setupModal) closeModal(); });

    if (verifySetupBtn) {
        verifySetupBtn.addEventListener('click', async function() {
            const code = verifyCodeInput.value.replace(/[^0-9]/g, '');
            
            if (code.length !== 6) {
                alert('Please enter the 6-digit code from your authenticator app.');
                return;
            }
            
            setLoading(verifySetupBtn, true);
            
            try {
                const result = await apiCall('/api/auth.php?action=enable_2fa', {
                    method: 'POST',
                    body: JSON.stringify({ code: code })
                });
                
                setLoading(verifySetupBtn, false);
                
                if (result.data.success) {
                    const backupCodes = result.data.backupCodes || currentBackupCodes;
                    if (backupCodes && backupCodes.length) {
                        alert('2FA Enabled!\n\nSave these backup codes:\n' + backupCodes.join('\n'));
                    } else {
                        alert('2FA Enabled successfully!');
                    }
                    closeModal();
                    window.location.reload();
                } else {
                    alert(result.data.error || 'Verification failed. Please try again.');
                }
            } catch (err) {
                setLoading(verifySetupBtn, false);
                console.error('2FA enable error:', err);
                alert('Error: ' + (err.message || 'Network error. Check console.'));
            }
        });
    }

    if (typeof gsap !== 'undefined') {
        gsap.fromTo('#loginCard', { opacity: 0, y: 30, scale: 0.97 }, { opacity: 1, y: 0, scale: 1, duration: 0.8, ease: 'power3.out', delay: 0.1 });
        gsap.fromTo('.brand', { opacity: 0, y: -10 }, { opacity: 1, y: 0, duration: 0.6, ease: 'power2.out', delay: 0.3 });
        if (document.getElementById('banner2fa')) {
            gsap.fromTo('#banner2fa', { opacity: 0, x: -15 }, { opacity: 1, x: 0, duration: 0.5, delay: 0.5 });
        }
        if (successMessage) {
            gsap.fromTo('#successMessage', { opacity: 0, y: -8 }, { opacity: 1, y: 0, duration: 0.5, delay: 0.5 });
            setTimeout(() => gsap.to('#successMessage', { opacity: 0, height: 0, padding: 0, marginBottom: 0, duration: 0.4 }), 5000);
        }
    }
})();
</script>
</body>
</html>