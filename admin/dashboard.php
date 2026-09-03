<?php
/**
 * admin/dashboard.php - Furusato Restaurant Administration
 *
 * Server-rendered shell with client-side section routing.
 * All statistics are computed from the real production data files
 * (data/reservations.json, data/menu.json, data/settings.json).
 * Mutations run exclusively through the hardened APIs in /api.
 */

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../logs/admin_errors.log');

@ob_start();

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/image-processor.php';
require_once __DIR__ . '/../includes/cache-control.php';
require_once __DIR__ . '/../includes/mailer.php';
require_once __DIR__ . '/../includes/whatsapp.php';

startAdminSession(true);
setNoCacheHeaders();
setSecurityHeaders();

date_default_timezone_set('Africa/Nairobi');

$csrfToken = $_SESSION['csrf_token'];
$adminEmail = (string) ($_SESSION['admin_email'] ?? '');
$adminAccount = getJsonData('admin') ?? [];
$twoFactorEnabled = !empty($adminAccount['totpEnabled']);

/* ---------------------------------------------------------------------------
 * Settings (flat keys are what the public website consumes; nested values
 * are maintained by api/settings.php). Only non-secret values are shown.
 * ------------------------------------------------------------------------- */

$settings = getJsonData('settings') ?? [];
$settingName = (string) ($settings['name'] ?? ($settings['restaurant']['name'] ?? 'Furusato Japanese Restaurant'));
$settingPhone = (string) ($settings['phone'] ?? ($settings['restaurant']['phone'] ?? ''));
$settingEmail = (string) ($settings['email'] ?? ($settings['restaurant']['email'] ?? ''));
$settingAddress = (string) ($settings['address'] ?? ($settings['restaurant']['address'] ?? ''));
$settingHours = (string) ($settings['hours'] ?? '');
$settingDays = (string) ($settings['days'] ?? '');
$settingWhatsapp = (string) ($settings['whatsapp'] ?? ($settings['whatsapp_number'] ?? ''));

$smtpConfigured = furusato_smtp_configured();
$whatsappConfigured = trim((string) furusato_whatsapp_api_key()) !== ''
    || trim((string) furusato_whatsapp_legacy_api_key()) !== '';
/* ---------------------------------------------------------------------------
 * Reservation statistics (real data only)
 * ------------------------------------------------------------------------- */

$reservationsRaw = getJsonData('reservations');
$reservations = is_array($reservationsRaw)
    ? array_values(array_filter($reservationsRaw, 'is_array'))
    : [];

$todayKey = date('Y-m-d');
$tomorrowKey = date('Y-m-d', strtotime('+1 day'));
$weekEndKey = date('Y-m-d', strtotime('+7 days'));

$statPending = 0;
$statConfirmed = 0;
$statUpcoming = 0;
$statToday = 0;

$todaysReservations = [];
$upcomingReservations = [];

foreach ($reservations as $r) {
    $status = (string) ($r['status'] ?? 'pending');
    $date = (string) ($r['date'] ?? '');

    if ($status === 'pending') $statPending++;
    if ($status === 'confirmed') $statConfirmed++;

    if ($date === $todayKey) {
        $statToday++;
        if (!in_array($status, ['cancelled', 'declined'], true)) {
            $todaysReservations[] = $r;
        }
    } elseif ($date >= $tomorrowKey && $date <= $weekEndKey && in_array($status, ['pending', 'confirmed'], true)) {
        $statUpcoming++;
        $upcomingReservations[] = $r;
    }
}

usort($todaysReservations, function ($a, $b) {
    return strcmp((string) ($a['time'] ?? ''), (string) ($b['time'] ?? ''));
});

usort($upcomingReservations, function ($a, $b) {
    $byDate = strcmp((string) ($a['date'] ?? ''), (string) ($b['date'] ?? ''));
    return $byDate !== 0 ? $byDate : strcmp((string) ($a['time'] ?? ''), (string) ($b['time'] ?? ''));
});

$upcomingReservations = array_slice($upcomingReservations, 0, 6);

/* ---------------------------------------------------------------------------
 * Menu statistics
 * ------------------------------------------------------------------------- */

$menuData = getJsonData('menu') ?? [];
$menuCategories = is_array($menuData['categories'] ?? null) ? $menuData['categories'] : [];

$totalItems = 0;
$hiddenItems = 0;

foreach ($menuCategories as $cat) {
    if (!is_array($cat)) continue;
    foreach (($cat['items'] ?? []) as $item) {
        if (!is_array($item)) continue;
        $totalItems++;
        if (($item['visible'] ?? true) === false) $hiddenItems++;
    }
    foreach (($cat['subcategories'] ?? []) as $sub) {
        if (!is_array($sub)) continue;
        foreach (($sub['items'] ?? []) as $item) {
            if (!is_array($item)) continue;
            $totalItems++;
            if (($item['visible'] ?? true) === false) $hiddenItems++;
        }
    }
}

/* ---------------------------------------------------------------------------
 * Recent audit activity (read-only tail of data/audit.log)
 * ------------------------------------------------------------------------- */

function dashboard_audit_tail(int $lines): array
{
    $path = __DIR__ . '/../data/audit.log';
    if (!is_file($path) || !is_readable($path)) {
        return [];
    }

    $all = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!is_array($all)) {
        return [];
    }

    $rows = [];
    foreach (array_slice($all, -$lines) as $line) {
        // Format: [Y-m-d H:i:s] EVENT ip=... detail=...
        if (!preg_match('/^\[([^\]]+)\]\s+([A-Z_]+)\s*(.*)$/', $line, $m)) {
            continue;
        }

        $detail = trim((string) $m[3]);
        $detail = trim((string) preg_replace('/\bip=\S+/i', '', $detail));
        $detail = trim((string) preg_replace('/\bua=\S+/i', '', $detail));

        $rows[] = [
            'time' => (string) $m[1],
            'event' => (string) $m[2],
            'detail' => $detail,
        ];
    }

    return array_reverse($rows);
}

$auditRows = dashboard_audit_tail(40);

$hour = (int) date('G');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');

/** Render one of the six supported reservation statuses as a badge. */
function dash_status_badge(string $status): string
{
    $labelMap = [
        'pending' => 'Pending',
        'confirmed' => 'Confirmed',
        'declined' => 'Declined',
        'cancelled' => 'Cancelled',
        'completed' => 'Completed',
        'no_show' => 'No-show',
    ];
    $label = $labelMap[$status] ?? ucfirst($status);
    return '<span class="badge badge-' . htmlspecialchars($status, ENT_QUOTES, 'UTF-8') . '"><span class="dot"></span>'
        . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span>';
}
/** Format HH:MM (24h) as 12-hour time with AM/PM, e.g. 18:30 -> 6:30 PM. */
function dash_fmt_time(string $time): string
{
    if (!preg_match('/^(\d{1,2}):(\d{2})$/', $time, $m)) {
        return $time !== '' ? $time : '—';
    }
    $h = (int) $m[1];
    $min = $m[2];
    $suffix = $h >= 12 ? 'PM' : 'AM';
    $h12 = $h % 12 === 0 ? 12 : $h % 12;
    return $h12 . ':' . $min . ' ' . $suffix;
}

/** Format Y-m-d as "31 May 2026". */
function dash_fmt_date(string $date): string
{
    $ts = strtotime($date);
    return $ts ? date('j M Y', $ts) : $date;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
<meta name="robots" content="noindex, nofollow">
<title>Furusato Admin — Restaurant Administration</title>
<link rel="icon" type="image/png" href="/assets/images/furusato-logo.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Cormorant+Garamond:wght@600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="/admin/assets/admin.css?v=<?= filemtime(__DIR__ . '/assets/admin.css') ?>">
</head>
<body>
<div class="admin-shell">

  <aside class="sidebar" aria-label="Admin navigation">
    <div class="sidebar-brand">
      <img src="/assets/images/furusato-logo.png" alt="Furusato logo">
      <div>
        <div class="sidebar-brand-name">FURUSATO</div>
        <div class="sidebar-brand-sub">Restaurant Administration</div>
      </div>
    </div>
    <nav class="sidebar-nav">
      <div class="nav-group">
        <div class="nav-group-label">Overview</div>
        <button type="button" class="nav-link" data-section="overview"><i class="fa-solid fa-gauge-high fa-fw" aria-hidden="true"></i><span>Overview</span></button>
      </div>
      <div class="nav-group">
        <div class="nav-group-label">Operations</div>
        <button type="button" class="nav-link" data-section="reservations"><i class="fa-solid fa-calendar-check fa-fw" aria-hidden="true"></i><span>Reservations</span><?php if ($statPending > 0): ?><span class="nav-count"><?= (int) $statPending ?></span><?php endif; ?></button>
        <button type="button" class="nav-link" data-section="menu"><i class="fa-solid fa-utensils fa-fw" aria-hidden="true"></i><span>Menu</span></button>
      </div>
      <div class="nav-group">
        <div class="nav-group-label">System</div>
        <button type="button" class="nav-link" data-section="settings"><i class="fa-solid fa-sliders fa-fw" aria-hidden="true"></i><span>Settings</span></button>
        <button type="button" class="nav-link" data-section="notifications"><i class="fa-solid fa-bell fa-fw" aria-hidden="true"></i><span>Notifications</span></button>
        <button type="button" class="nav-link" data-section="security"><i class="fa-solid fa-shield-halved fa-fw" aria-hidden="true"></i><span>Security</span></button>
        <button type="button" class="nav-link" data-section="account"><i class="fa-solid fa-user-gear fa-fw" aria-hidden="true"></i><span>Admin Account</span></button>
      </div>
    </nav>
    <div class="sidebar-foot">
      <a href="/admin/logout.php" class="nav-link" id="sign-out"><i class="fa-solid fa-arrow-right-from-bracket fa-fw" aria-hidden="true"></i><span>Sign Out</span></a>
      <div class="sidebar-user"><?= htmlspecialchars($adminEmail !== '' ? $adminEmail : 'Admin session', ENT_QUOTES, 'UTF-8') ?></div>
    </div>
  </aside>
  <div class="sidebar-backdrop" aria-hidden="true"></div>

  <main class="admin-main">
    <header class="topbar">
      <button type="button" class="topbar-toggle" aria-label="Toggle navigation" aria-expanded="false"><i class="fa-solid fa-bars" aria-hidden="true"></i></button>
      <div class="topbar-titles">
        <div class="topbar-crumb">Furusato / Overview</div>
        <div class="topbar-title">Overview</div>
      </div>
      <div class="topbar-right">
        <span class="session-pill"><span class="dot"></span> Secure session</span>
        <div class="admin-identity">
          <span class="avatar"><?= strtoupper(htmlspecialchars(mb_substr($adminEmail !== '' ? $adminEmail : 'A', 0, 1, 'UTF-8'), ENT_QUOTES, 'UTF-8')) ?></span>
          <span class="identity-email"><?= htmlspecialchars($adminEmail, ENT_QUOTES, 'UTF-8') ?></span>
        </div>
      </div>
    </header>
<div class="content">
      <section class="section active" id="section-overview">
        <div class="section-head">
          <h2><?= htmlspecialchars($greeting, ENT_QUOTES, 'UTF-8') ?></h2>
          <p class="section-desc"><?= htmlspecialchars($settingName, ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars(date('l, j F Y'), ENT_QUOTES, 'UTF-8') ?></p>
        </div>

        <?php
        $alerts = [];
        if ($statPending > 0) {
            $alerts['warning'][] = ['icon' => 'fa-clock', 'text' => $statPending . ' reservation' . ($statPending === 1 ? '' : 's') . ' awaiting confirmation.'];
        }
        if (!$smtpConfigured) {
            $alerts['danger'][] = ['icon' => 'fa-envelope', 'text' => 'SMTP email is not configured — customer confirmation emails cannot be sent.'];
        }
        if (!$whatsappConfigured) {
            $alerts['danger'][] = ['icon' => 'fa-brands fa-whatsapp', 'text' => 'WhatsApp notifications are not configured — reservation alerts are not being delivered.'];
        }
        if ($hiddenItems > 0) {
            $alerts['info'][] = ['icon' => 'fa-eye-slash', 'text' => $hiddenItems . ' menu item' . ($hiddenItems === 1 ? ' is' : 's are') . ' hidden from the public menu.'];
        }
        if (!empty($alerts)):
        ?>
        <div class="alert-stack">
          <?php foreach (['danger' => 'alert-danger', 'warning' => 'alert-warning', 'info' => ''] as $level => $cls): ?>
            <?php foreach ($alerts[$level] ?? [] as $alert): ?>
            <div class="alert <?= $cls ?>" role="status">
              <i class="fa-solid <?= htmlspecialchars($alert['icon'], ENT_QUOTES, 'UTF-8') ?> fa-fw" aria-hidden="true"></i>
              <span><?= htmlspecialchars($alert['text'], ENT_QUOTES, 'UTF-8') ?></span>
              <?php if ($level === 'warning'): ?><a href="#section-reservations" class="alert-link" data-goto="reservations">Review</a><?php endif; ?>
            </div>
            <?php endforeach; ?>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="stats-row">
          <div class="stat-card<?= $statToday > 0 ? ' attention' : '' ?>">
            <div class="stat-label"><i class="fa-solid fa-calendar-day fa-fw" aria-hidden="true"></i> Today's Reservations</div>
            <div class="stat-value"><?= (int) $statToday ?></div>
            <div class="stat-note">Excluding cancelled / declined</div>
          </div>
          <div class="stat-card<?= $statPending > 0 ? ' attention' : '' ?>">
            <div class="stat-label"><i class="fa-solid fa-hourglass-half fa-fw" aria-hidden="true"></i> Pending</div>
            <div class="stat-value"><?= (int) $statPending ?></div>
            <div class="stat-note">Awaiting confirmation</div>
          </div>
          <div class="stat-card">
            <div class="stat-label"><i class="fa-solid fa-circle-check fa-fw" aria-hidden="true"></i> Confirmed</div>
            <div class="stat-value"><?= (int) $statConfirmed ?></div>
            <div class="stat-note">All confirmed reservations</div>
          </div>
          <div class="stat-card">
            <div class="stat-label"><i class="fa-solid fa-calendar-week fa-fw" aria-hidden="true"></i> Upcoming · 7 days</div>
            <div class="stat-value"><?= (int) $statUpcoming ?></div>
            <div class="stat-note">Pending or confirmed</div>
          </div>
          <div class="stat-card">
            <div class="stat-label"><i class="fa-solid fa-utensils fa-fw" aria-hidden="true"></i> Menu Items</div>
            <div class="stat-value"><?= (int) $totalItems ?></div>
            <div class="stat-note"><?= count($menuCategories) ?> categor<?= count($menuCategories) === 1 ? 'y' : 'ies' ?></div>
          </div>
        </div>

        <div class="quick-actions">
          <button type="button" class="btn btn-primary" data-goto="reservations"><i class="fa-solid fa-calendar-check btn-icon" aria-hidden="true"></i> Manage Reservations</button>
          <button type="button" class="btn btn-outline" data-goto="menu"><i class="fa-solid fa-utensils btn-icon" aria-hidden="true"></i> Manage Menu</button>
          <button type="button" class="btn btn-outline" id="quick-add-item"><i class="fa-solid fa-plus btn-icon" aria-hidden="true"></i> Add Menu Item</button>
          <button type="button" class="btn btn-outline" data-goto="settings"><i class="fa-solid fa-sliders btn-icon" aria-hidden="true"></i> Settings</button>
          <button type="button" class="btn btn-outline" data-goto="notifications"><i class="fa-solid fa-bell btn-icon" aria-hidden="true"></i> Notifications</button>
        </div>
<div class="panel" style="margin-bottom:22px;">
          <div class="panel-head">
            <div>
              <h3>Today's Schedule</h3>
              <div class="panel-sub"><?= htmlspecialchars(date('l, j F Y'), ENT_QUOTES, 'UTF-8') ?> · chronological</div>
            </div>
            <div class="panel-actions">
              <button type="button" class="btn btn-ghost btn-sm" data-goto="reservations">View all</button>
            </div>
          </div>
          <?php if (empty($todaysReservations)): ?>
          <div class="empty-state">
            <i class="fa-regular fa-calendar fa-fw" aria-hidden="true"></i>
            <p>No reservations scheduled for today.</p>
          </div>
          <?php else: ?>
          <div style="overflow-x:auto;">
            <table class="data-table">
              <thead><tr><th>Time</th><th>Guest</th><th>Phone</th><th class="num">Guests</th><th>Status</th><th>Requests</th></tr></thead>
              <tbody>
              <?php foreach ($todaysReservations as $r): ?>
                <tr>
                  <td class="cell-strong num"><?= htmlspecialchars(dash_fmt_time((string) ($r['time'] ?? '')), ENT_QUOTES, 'UTF-8') ?></td>
                  <td class="cell-strong"><?= htmlspecialchars((string) ($r['name'] ?? 'Unknown'), ENT_QUOTES, 'UTF-8') ?></td>
                  <td class="cell-muted"><?= htmlspecialchars((string) ($r['phone'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                  <td class="num"><?= (int) ($r['guests'] ?? 1) ?></td>
                  <td><?= dash_status_badge((string) ($r['status'] ?? 'pending')) ?></td>
                  <td class="cell-muted" style="max-width:240px;"><?= htmlspecialchars(trim((string) ($r['special_requests'] ?? '')) !== '' ? (string) $r['special_requests'] : '—', ENT_QUOTES, 'UTF-8') ?></td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php endif; ?>
        </div>

        <div class="panel">
          <div class="panel-head">
            <div>
              <h3>Upcoming · Next 7 Days</h3>
              <div class="panel-sub">Pending or confirmed reservations</div>
            </div>
          </div>
          <?php if (empty($upcomingReservations)): ?>
          <div class="empty-state">
            <i class="fa-regular fa-clock fa-fw" aria-hidden="true"></i>
            <p>No upcoming reservations in the next 7 days.</p>
          </div>
          <?php else: ?>
          <div style="overflow-x:auto;">
            <table class="data-table">
              <thead><tr><th>Date</th><th>Time</th><th>Guest</th><th class="num">Guests</th><th>Status</th></tr></thead>
              <tbody>
              <?php foreach ($upcomingReservations as $r): ?>
                <tr>
                  <td class="cell-strong"><?= htmlspecialchars(dash_fmt_date((string) ($r['date'] ?? '')), ENT_QUOTES, 'UTF-8') ?></td>
                  <td class="num"><?= htmlspecialchars(dash_fmt_time((string) ($r['time'] ?? '')), ENT_QUOTES, 'UTF-8') ?></td>
                  <td class="cell-strong"><?= htmlspecialchars((string) ($r['name'] ?? 'Unknown'), ENT_QUOTES, 'UTF-8') ?></td>
                  <td class="num"><?= (int) ($r['guests'] ?? 1) ?></td>
                  <td><?= dash_status_badge((string) ($r['status'] ?? 'pending')) ?></td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php endif; ?>
        </div>
      </section>
<section class="section" id="section-reservations">
        <div class="section-head">
          <h2>Reservations</h2>
          <p class="section-desc">Manage guest bookings, update statuses and review special requests.</p>
        </div>

        <div class="toolbar">
          <div class="search-box">
            <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
            <input type="search" id="res-search" placeholder="Search name, email, phone or ID…" autocomplete="off">
          </div>
          <div class="seg-group" id="res-date-filter" role="group" aria-label="Date filter">
            <button type="button" data-date="today">Today</button>
            <button type="button" data-date="tomorrow">Tomorrow</button>
            <button type="button" data-date="week">This Week</button>
            <button type="button" data-date="all" class="active">All</button>
          </div>
          <div class="spacer"></div>
          <button type="button" class="btn btn-outline btn-sm" id="res-refresh"><i class="fa-solid fa-rotate btn-icon" aria-hidden="true"></i> Refresh</button>
        </div>

        <div class="toolbar">
          <div class="seg-group" id="res-status-filter" role="group" aria-label="Status filter">
            <button type="button" data-status="all" class="active">All</button>
            <button type="button" data-status="pending">Pending</button>
            <button type="button" data-status="confirmed">Confirmed</button>
            <button type="button" data-status="completed">Completed</button>
            <button type="button" data-status="cancelled">Cancelled</button>
            <button type="button" data-status="declined">Declined</button>
            <button type="button" data-status="no_show">No-show</button>
          </div>
        </div>

        <div id="reservations-container"><div class="loading-block"><div class="spinner"></div><p>Loading reservations…</p></div></div>
      </section>
<section class="section" id="section-menu">
        <div class="section-head">
          <h2>Menu</h2>
          <p class="section-desc">Manage categories, subcategories and menu items. Drag rows to reorder.</p>
        </div>

        <div class="toolbar">
          <div class="search-box">
            <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
            <input type="search" id="menu-search" placeholder="Search menu items…" autocomplete="off">
          </div>
          <select id="menu-category-filter" class="field" style="max-width:210px;">
            <option value="all">All categories</option>
          </select>
          <select id="menu-avail-filter" style="max-width:190px; border:1px solid var(--border-strong); border-radius:var(--radius-sm); padding:8px 11px; font-family:inherit; font-size:0.84rem; background:var(--surface); color:var(--ink);">
            <option value="all">All availability</option>
            <option value="visible">Visible only</option>
            <option value="hidden">Hidden only</option>
          </select>
          <div class="spacer"></div>
          <button type="button" class="btn btn-outline btn-sm" id="menu-refresh"><i class="fa-solid fa-rotate btn-icon" aria-hidden="true"></i> Refresh</button>
          <button type="button" class="btn btn-outline btn-sm" id="menu-backup-btn"><i class="fa-solid fa-database btn-icon" aria-hidden="true"></i> Backup</button>
          <button type="button" class="btn btn-outline btn-sm" id="add-category-btn"><i class="fa-solid fa-folder-plus btn-icon" aria-hidden="true"></i> Category</button>
          <button type="button" class="btn btn-outline btn-sm" id="add-subcategory-btn"><i class="fa-solid fa-list btn-icon" aria-hidden="true"></i> Subcategory</button>
          <button type="button" class="btn btn-primary btn-sm" id="add-item-btn"><i class="fa-solid fa-plus btn-icon" aria-hidden="true"></i> Add Item</button>
        </div>

        <div id="menu-container"><div class="loading-block"><div class="spinner"></div><p>Loading menu…</p></div></div>
      </section>
<section class="section" id="section-notifications">
        <div class="section-head">
          <h2>Notifications</h2>
          <p class="section-desc">Review delivery health for reservation notifications and send test messages.</p>
        </div>

        <div class="stats-row" style="grid-template-columns:repeat(auto-fit,minmax(230px,1fr));">
          <div class="stat-card">
            <div class="stat-label"><i class="fa-solid fa-envelope fa-fw" aria-hidden="true"></i> Email (SMTP)</div>
            <div style="margin-top:10px;">
              <?php if ($smtpConfigured): ?><span class="badge badge-confirmed"><span class="dot"></span>Configured</span><?php else: ?><span class="badge badge-cancelled"><span class="dot"></span>Not configured</span><?php endif; ?>
            </div>
            <div class="stat-note"><?= $smtpConfigured ? 'Reservation emails can be delivered.' : 'Customer confirmation emails cannot be sent.' ?></div>
          </div>
          <div class="stat-card">
            <div class="stat-label"><i class="fa-brands fa-whatsapp fa-fw" aria-hidden="true"></i> WhatsApp</div>
            <div style="margin-top:10px;">
              <?php if ($whatsappConfigured): ?><span class="badge badge-confirmed"><span class="dot"></span>Configured</span><?php else: ?><span class="badge badge-cancelled"><span class="dot"></span>Not configured</span><?php endif; ?>
            </div>
            <div class="stat-note"><?= $whatsappConfigured ? 'Reservation alerts are enabled.' : 'Reservation alerts are not being delivered.' ?></div>
          </div>
        </div>

        <div class="panel">
          <div class="panel-head">
            <div>
              <h3>Send test email</h3>
              <div class="panel-sub">Verify the reservation notification transport reaches an inbox.</div>
            </div>
          </div>
          <div class="panel-body">
            <form id="test-email-form" class="form-grid" style="max-width:460px;">
              <div class="field">
                <label for="test-email-input">Recipient email</label>
                <input type="email" id="test-email-input" name="email" placeholder="name@example.com" autocomplete="off" required>
              </div>
              <div>
                <button type="submit" class="btn btn-primary" id="test-email-btn"><span class="spinner-inline"></span><i class="fa-solid fa-paper-plane btn-icon" aria-hidden="true"></i> Send test email</button>
              </div>
            </form>
          </div>
        </div>

        <div class="panel" style="margin-top:16px;">
          <div class="panel-head">
            <div>
              <h3>Send test WhatsApp message</h3>
              <div class="panel-sub">Sends a test message using the configured CallMeBot key.</div>
            </div>
          </div>
          <div class="panel-body">
            <form id="test-whatsapp-form" class="form-grid" style="max-width:460px;">
              <div class="field">
                <label for="test-whatsapp-phone">Phone number</label>
                <input type="tel" id="test-whatsapp-phone" name="phone_number" value="<?= htmlspecialchars($settingWhatsapp, ENT_QUOTES, 'UTF-8') ?>" placeholder="+254734639203" required>
                <div class="hint">International format, e.g. +254734639203.</div>
              </div>
              <div>
                <button type="submit" class="btn btn-primary" id="test-whatsapp-btn"><span class="spinner-inline"></span><i class="fa-brands fa-whatsapp btn-icon" aria-hidden="true"></i> Send test message</button>
              </div>
            </form>
          </div>
        </div>
      </section>
<section class="section" id="section-settings">
        <div class="section-head">
          <h2>Settings</h2>
          <p class="section-desc">Restaurant identity and reservation communication. Changes take effect on the public website immediately.</p>
        </div>

        <div class="panel">
          <div class="panel-head">
            <div>
              <h3>Restaurant</h3>
              <div class="panel-sub">Shown across the public website and reservation emails.</div>
            </div>
          </div>
          <div class="panel-body">
            <form id="restaurant-settings-form" class="form-grid">
              <div class="form-grid-2">
                <div class="field">
                  <label for="set-name">Restaurant name</label>
                  <input type="text" id="set-name" name="name" value="<?= htmlspecialchars($settingName, ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="field">
                  <label for="set-phone">Phone</label>
                  <input type="tel" id="set-phone" name="phone" value="<?= htmlspecialchars($settingPhone, ENT_QUOTES, 'UTF-8') ?>">
                </div>
              </div>
              <div class="field">
                <label for="set-email">Email</label>
                <input type="email" id="set-email" name="email" value="<?= htmlspecialchars($settingEmail, ENT_QUOTES, 'UTF-8') ?>">
              </div>
              <div class="field">
                <label for="set-address">Address</label>
                <input type="text" id="set-address" name="address" value="<?= htmlspecialchars($settingAddress, ENT_QUOTES, 'UTF-8') ?>">
              </div>
              <div class="form-grid-2">
                <div class="field">
                  <label for="set-hours">Opening hours</label>
                  <input type="text" id="set-hours" name="hours" value="<?= htmlspecialchars($settingHours, ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="field">
                  <label for="set-days">Open days</label>
                  <input type="text" id="set-days" name="days" value="<?= htmlspecialchars($settingDays, ENT_QUOTES, 'UTF-8') ?>">
                </div>
              </div>
              <div>
                <button type="submit" class="btn btn-primary" id="restaurant-settings-btn"><span class="spinner-inline"></span><i class="fa-solid fa-floppy-disk btn-icon" aria-hidden="true"></i> Save restaurant settings</button>
              </div>
            </form>
          </div>
        </div>

        <div class="panel" style="margin-top:16px;">
          <div class="panel-head">
            <div>
              <h3>WhatsApp</h3>
              <div class="panel-sub">Reservation alert destination. The API key is never shown after saving.</div>
            </div>
          </div>
          <div class="panel-body">
            <form id="whatsapp-settings-form" class="form-grid">
              <div class="form-grid-2">
                <div class="field">
                  <label for="set-wa-phone">WhatsApp number</label>
                  <input type="tel" id="set-wa-phone" name="phone_number" value="<?= htmlspecialchars($settingWhatsapp, ENT_QUOTES, 'UTF-8') ?>">
                  <div class="hint">International format, e.g. +254734639203.</div>
                </div>
                <div class="field">
                  <label for="set-wa-key">CallMeBot API key</label>
                  <input type="password" id="set-wa-key" name="api_key" value="" autocomplete="new-password" placeholder="Leave blank to keep current key">
                  <div class="hint">Numeric CallMeBot key. Blank keeps the existing key.</div>
                </div>
              </div>
              <div>
                <button type="submit" class="btn btn-primary" id="whatsapp-settings-btn"><span class="spinner-inline"></span><i class="fa-solid fa-floppy-disk btn-icon" aria-hidden="true"></i> Save WhatsApp settings</button>
              </div>
            </form>
          </div>
        </div>
      </section>
<section class="section" id="section-security">
        <div class="section-head">
          <h2>Security</h2>
          <p class="section-desc">Two-factor authentication for the admin account.</p>
        </div>

        <div class="panel">
          <div class="panel-head">
            <div>
              <h3>Two-factor authentication</h3>
              <div class="panel-sub">Protect the admin account with a time-based one-time code.</div>
            </div>
          </div>
          <div class="panel-body">
            <?php if ($twoFactorEnabled): ?>
              <div class="alert" role="status" style="margin-bottom:14px;border-left-color:var(--success);">
                <i class="fa-solid fa-shield-halved fa-fw" aria-hidden="true"></i>
                <span>Two-factor authentication is currently enabled for this account.</span>
              </div>
              <button type="button" class="btn btn-danger-outline" id="disable-2fa-btn"><i class="fa-solid fa-unlock btn-icon" aria-hidden="true"></i> Disable two-factor authentication</button>
            <?php else: ?>
              <div class="alert alert-warning" role="status" style="margin-bottom:14px;">
                <i class="fa-solid fa-triangle-exclamation fa-fw" aria-hidden="true"></i>
                <span>Two-factor authentication is currently disabled.</span>
              </div>
              <button type="button" class="btn btn-primary" id="enable-2fa-btn"><i class="fa-solid fa-shield-halved btn-icon" aria-hidden="true"></i> Enable two-factor authentication</button>
            <?php endif; ?>
          </div>
        </div>
      </section>
<section class="section" id="section-account">
        <div class="section-head">
          <h2>Admin Account</h2>
          <p class="section-desc">Account identity and password.</p>
        </div>

        <div class="panel">
          <div class="panel-head">
            <div>
              <h3>Account</h3>
              <div class="panel-sub">Signed in administrator.</div>
            </div>
          </div>
          <div class="panel-body">
            <div class="field" style="max-width:460px;">
              <label for="account-email">Email</label>
              <input type="email" id="account-email" value="<?= htmlspecialchars($adminEmail, ENT_QUOTES, 'UTF-8') ?>" readonly>
              <div class="hint">The admin email is the login identity and cannot be changed from the dashboard.</div>
            </div>
          </div>
        </div>

        <div class="panel" style="margin-top:16px;">
          <div class="panel-head">
            <div>
              <h3>Change password</h3>
              <div class="panel-sub">Minimum 8 characters.</div>
            </div>
          </div>
          <div class="panel-body">
            <form id="change-password-form" class="form-grid" style="max-width:460px;">
              <div class="field">
                <label for="cp-current">Current password</label>
                <input type="password" id="cp-current" name="current_password" autocomplete="current-password" required>
              </div>
              <div class="field">
                <label for="cp-new">New password</label>
                <input type="password" id="cp-new" name="new_password" autocomplete="new-password" minlength="8" required>
              </div>
              <div class="field">
                <label for="cp-confirm">Confirm new password</label>
                <input type="password" id="cp-confirm" name="confirm_password" autocomplete="new-password" minlength="8" required>
              </div>
              <div>
                <button type="submit" class="btn btn-primary" id="change-password-btn"><span class="spinner-inline"></span><i class="fa-solid fa-key btn-icon" aria-hidden="true"></i> Change password</button>
              </div>
            </form>
          </div>
        </div>
      </section>
      </div><!-- /.content -->
    </main>
  </div><!-- /.admin-shell -->

  <!-- Confirm dialog -->
  <div class="modal-overlay" id="confirm-modal" role="dialog" aria-modal="true" aria-labelledby="confirm-title">
    <div class="modal modal-narrow">
      <div class="modal-head">
        <h3 id="confirm-title">Please confirm</h3>
      </div>
      <div class="modal-body">
        <p id="confirm-message">Are you sure?</p>
      </div>
      <div class="modal-foot">
        <button type="button" class="btn btn-outline" id="confirm-cancel">Cancel</button>
        <button type="button" class="btn btn-primary" id="confirm-ok">Confirm</button>
      </div>
    </div>
  </div>

  <!-- Reservation detail -->
  <div class="modal-overlay" id="reservation-modal" role="dialog" aria-modal="true" aria-labelledby="reservation-modal-title">
    <div class="modal modal-wide">
      <div class="modal-head">
        <h3 id="reservation-modal-title">Reservation details</h3>
        <button type="button" class="modal-close" data-close-modal aria-label="Close"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
      </div>
      <div class="modal-body" id="reservation-modal-body"></div>
      <div class="modal-foot" id="reservation-modal-foot"></div>
    </div>
  </div>

  <!-- Menu item editor -->
  <div class="modal-overlay" id="item-modal" role="dialog" aria-modal="true" aria-labelledby="item-modal-title">
    <div class="modal modal-wide">
      <div class="modal-head">
        <h3 id="item-modal-title">Menu item</h3>
        <button type="button" class="modal-close" data-close-modal aria-label="Close"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
      </div>
      <div class="modal-body" id="item-modal-body"></div>
      <div class="modal-foot" id="item-modal-foot"></div>
    </div>
  </div>

  <!-- Category / subcategory editor -->
  <div class="modal-overlay" id="category-modal" role="dialog" aria-modal="true" aria-labelledby="category-modal-title">
    <div class="modal">
      <div class="modal-head">
        <h3 id="category-modal-title">Category</h3>
        <button type="button" class="modal-close" data-close-modal aria-label="Close"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
      </div>
      <div class="modal-body" id="category-modal-body"></div>
      <div class="modal-foot" id="category-modal-foot"></div>
    </div>
  </div>

  <!-- 2FA setup -->
  <div class="modal-overlay" id="twofa-modal" role="dialog" aria-modal="true" aria-labelledby="twofa-modal-title">
    <div class="modal">
      <div class="modal-head">
        <h3 id="twofa-modal-title">Two-factor authentication</h3>
        <button type="button" class="modal-close" data-close-modal aria-label="Close"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
      </div>
      <div class="modal-body" id="twofa-modal-body"></div>
      <div class="modal-foot" id="twofa-modal-foot"></div>
    </div>
  </div>

  <div class="session-warning" id="session-warning" role="alert">
    <span>Your session expires in <strong id="session-timer">5:00</strong>.</span>
    <button type="button" class="btn btn-primary btn-sm" id="session-extend">Stay signed in</button>
  </div>

  <script src="/admin/assets/admin.js?v=<?= filemtime(__DIR__ . '/assets/admin.js') ?>"></script>
  <script src="/admin/assets/dashboard.js?v=<?= filemtime(__DIR__ . '/assets/dashboard.js') ?>"></script>
</body>
</html>
