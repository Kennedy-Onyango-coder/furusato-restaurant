<?php
/**
 * api/reservations.php - Furusato Restaurant Reservations API
 *
 * SECURITY HARDENED:
 *  - CSRF required for every state-changing request (POST/PUT).
 *  - Summary listing is admin-only; customers can only look up their OWN
 *    reservation with id + verification token (narrow, non-sensitive fields).
 *  - Customer edits require: reservation id + verification token + CSRF.
 *  - Strict input validation (date YYYY-MM-DD, time HH:MM, guests 1-50).
 *  - Atomic JSON persistence with advisory file locking (no lost duplicate
 *    checks, no torn reads during concurrent writes).
 *  - Enforcing per-IP rate limits for public writes (429), separate from
 *    authenticated admin operations which are never rate limited.
 *  - Notifications run only AFTER a successful save and never break the save.
 *
 * Notification + email logic is delegated to includes/whatsapp.php and
 * includes/mailer.php (centralised helpers). This file contains no secrets
 * and no hardcoded recipient phone numbers.
 */

// ============================================================
// LOAD CORE FUNCTIONS + START SECURE PUBLIC SESSION
// ============================================================
// functions.php also loads the central configuration.
require_once __DIR__ . '/../includes/functions.php';

// Public reservation requests need a secure session and CSRF token,
// but must NOT require administrator authentication.
startSecureSession(false);

// ============================================================
// CORS - restricted to the Furusato origin(s) configured in
// includes/config.php. Never wildcard together with credentials.
// OPTIONS pre-flight is answered here with HTTP 204 and exits.
// ============================================================
furusato_cors_headers();

error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

while (ob_get_level()) {
    ob_end_clean();
}
ob_start();

date_default_timezone_set('Africa/Nairobi');

require_once __DIR__ . '/../includes/mailer.php';
require_once __DIR__ . '/../includes/whatsapp.php';

// ─────────────────────────────────────────────────────────────────────────────
// Constants
// ─────────────────────────────────────────────────────────────────────────────

const RES_RATE_LIMIT_HOURLY        = 20;
const RES_RATE_LIMIT_HOURLY_WINDOW = 3600;
const RES_RATE_LIMIT_BURST         = 8;
const RES_RATE_LIMIT_BURST_WINDOW  = 120;
const RES_SOFT_VOLUME_WARN         = 15;

const RES_MAX_NAME_LENGTH  = 100;
const RES_MAX_EMAIL_LENGTH = 100;
const RES_MAX_PHONE_LENGTH = 20;
const RES_MAX_NOTES_LENGTH = 500;
const RES_GUESTS_MIN       = 1;
const RES_GUESTS_MAX       = 50;
const RES_PENDING_EDIT_TTL = 1800;

const RES_STATUSES = [
    'pending',
    'confirmed',
    'declined',
    'cancelled',
    'completed',
    'no_show'
];

// ─────────────────────────────────────────────────────────────────────────────
// JSON Response Helpers
// ─────────────────────────────────────────────────────────────────────────────

function sendJsonResponse($data, $statusCode = 200)
{
    while (ob_get_level()) {
        ob_end_clean();
    }

    http_response_code($statusCode);

    header('Content-Type: application/json; charset=utf-8');

    // Explicitly forbid caching at every layer (browser, LiteSpeed, and
    // Hostinger hCDN). Without this, the hCDN caches API responses for
    // 60s and fresh visitors receive another session's CSRF token,
    // breaking the first reservation attempt. Also vary on cookies so
    // session-bound responses can never be shared between visitors.
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
    header('Vary: Cookie');

    if ($statusCode === 429) {
        $retryAfter = (int) ($data['data']['retry_after'] ?? 60);
        header('Retry-After: ' . max(1, $retryAfter));
    }

    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function sendError($message, $statusCode = 400)
{
    $payload = [
        'success' => false,
        'error'   => $message
    ];

    // Allow the frontend to refresh its in-page CSRF token after a failure.
    if ($statusCode === 403 && isset($_SESSION['csrf_token'])) {
        $payload['csrf_token'] = $_SESSION['csrf_token'];
    }

    sendJsonResponse($payload, $statusCode);
}

// ─────────────────────────────────────────────────────────────────────────────
// Rate limiting - public reservation writes only
// ─────────────────────────────────────────────────────────────────────────────

function reservationRateLimitHit(string $ip, int $limit, int $window): array
{
    $file = __DIR__ . '/../data/rate_limits_reservations.json';
    $dir  = dirname($file);

    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }

    $key = md5($ip . ':reservation:' . $window);
    $now = time();

    $fp = @fopen($file, 'c+');

    if ($fp === false) {
        error_log(
            'Reservation rate-limit file could not be opened; request allowed.'
        );

        return [
            'allowed'      => true,
            'count'        => 0,
            'limit'        => $limit,
            'retry_after'  => 0
        ];
    }

    if (!flock($fp, LOCK_EX)) {
        fclose($fp);

        error_log(
            'Reservation rate-limit lock could not be acquired; request allowed.'
        );

        return [
            'allowed'      => true,
            'count'        => 0,
            'limit'        => $limit,
            'retry_after'  => 0
        ];
    }

    $raw = stream_get_contents($fp);

    $data = (
        is_string($raw) &&
        $raw !== ''
    )
        ? json_decode($raw, true)
        : null;

    if (!is_array($data)) {
        $data = [];
    }

    $bucket = (
        isset($data[$key]) &&
        is_array($data[$key])
    )
        ? $data[$key]
        : [];

    $bucket = array_values(
        array_filter(
            $bucket,
            function ($ts) use ($now, $window) {
                return ($now - (int) $ts) < $window;
            }
        )
    );

    $allowed = count($bucket) < $limit;

    if ($allowed) {
        $bucket[] = $now;
        $data[$key] = $bucket;

        $json = json_encode($data);

        if ($json !== false) {
            rewind($fp);
            ftruncate($fp, 0);
            fwrite($fp, $json);
            fflush($fp);
        }
    }

    $retryAfter = 0;

    if (!$allowed && count($bucket) > 0) {
        $oldest = (int) $bucket[0];

        $retryAfter = max(
            1,
            ($oldest + $window) - $now
        );
    }

    flock($fp, LOCK_UN);
    fclose($fp);

    return [
        'allowed'     => $allowed,
        'count'       => count($bucket) + 1,
        'limit'       => $limit,
        'retry_after' => $retryAfter
    ];
}

function enforcePublicReservationRateLimit(string $ip): array
{
    $hourly = reservationRateLimitHit(
        $ip,
        RES_RATE_LIMIT_HOURLY,
        RES_RATE_LIMIT_HOURLY_WINDOW
    );

    $burst = reservationRateLimitHit(
        $ip,
        RES_RATE_LIMIT_BURST,
        RES_RATE_LIMIT_BURST_WINDOW
    );

    if (!$hourly['allowed']) {
        return [
            'allowed'     => false,
            'retry_after' => $hourly['retry_after'],
            'count'       => $hourly['count'],
            'limit'       => $hourly['limit']
        ];
    }

    if (!$burst['allowed']) {
        return [
            'allowed'     => false,
            'retry_after' => $burst['retry_after'],
            'count'       => $burst['count'],
            'limit'       => $burst['limit']
        ];
    }

    return [
        'allowed'       => true,
        'count'         => $burst['count'],
        'limit'         => $burst['limit'],
        'is_high_volume' => ($hourly['count'] >= RES_SOFT_VOLUME_WARN)
    ];
}

// ─────────────────────────────────────────────────────────────────────────────
// IP Blocklist Check
// ─────────────────────────────────────────────────────────────────────────────

function isIpBlocked(string $ip): bool
{
    $blocklistFile = __DIR__ . '/../data/blocklist.json';

    if (!is_file($blocklistFile)) {
        return false;
    }

    $raw = @file_get_contents($blocklistFile);

    $blocklist = is_string($raw)
        ? json_decode($raw, true)
        : null;

    if (!is_array($blocklist)) {
        return false;
    }

    $now = time();

    foreach ($blocklist as $blocked) {
        if (
            is_array($blocked) &&
            ($blocked['ip'] ?? '') === $ip &&
            (int) ($blocked['expires'] ?? 0) > $now
        ) {
            return true;
        }
    }

    return false;
}

// ─────────────────────────────────────────────────────────────────────────────
// Atomic JSON reservation store
// ─────────────────────────────────────────────────────────────────────────────

function reservationStorePath(): string
{
    return __DIR__ . '/../data/reservations.json';
}

function readReservations(): array
{
    $file = reservationStorePath();

    if (is_file($file)) {
        $raw = @file_get_contents($file);

        if (is_string($raw) && $raw !== '') {
            $data = json_decode($raw, true);

            if (is_array($data)) {
                return $data;
            }

            error_log(
                'Reservations storage is not valid JSON; treating as empty.'
            );
        }
    }

    return [];
}

function persistReservations(string $file, array $reservations): bool
{
    $json = json_encode(
        $reservations,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
    );

    if ($json === false) {
        return false;
    }

    $dir = dirname($file);

    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }

    $tmp = $dir . '/.tmp_res_' . bin2hex(random_bytes(8)) . '.json';

    if (@file_put_contents($tmp, $json . "\n", LOCK_EX) === false) {
        @unlink($tmp);
        return false;
    }

    @chmod($tmp, 0640);

    if (!@rename($tmp, $file)) {
        @unlink($tmp);
        return false;
    }

    @chmod($file, 0640);

    return true;
}

function withReservationLock(callable $fn)
{
    $dir = __DIR__ . '/../data';

    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }

    $lockFp = @fopen(
        $dir . '/reservations.lock',
        'c'
    );

    if ($lockFp === false) {
        throw new RuntimeException(
            'Reservation lock could not be opened'
        );
    }

    try {
        if (!flock($lockFp, LOCK_EX)) {
            throw new RuntimeException(
                'Reservation lock could not be acquired'
            );
        }

        $result = $fn(readReservations());

        if (
            !empty($result['commit']) &&
            isset($result['list']) &&
            is_array($result['list'])
        ) {
            if (
                !persistReservations(
                    reservationStorePath(),
                    $result['list']
                )
            ) {
                throw new RuntimeException(
                    'Reservation save failed'
                );
            }
        }

        return $result;
    } finally {
        flock($lockFp, LOCK_UN);
        fclose($lockFp);
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// Reservation validation
// ─────────────────────────────────────────────────────────────────────────────

function validateReservationInput(array $input): array
{
    $errors = [];
    $data   = [];

    $name = trim(
        (string) (
            $input['name'] ??
            ($input['full_name'] ?? '')
        )
    );

    $name = substr(
        $name,
        0,
        RES_MAX_NAME_LENGTH
    );

    if ($name === '' || strlen($name) < 2) {
        $errors[] =
            'Full name is required (minimum 2 characters)';
    }

    $data['name'] = $name;

    $email = trim(
        (string) ($input['email'] ?? '')
    );

    $email = substr(
        $email,
        0,
        RES_MAX_EMAIL_LENGTH
    );

    if ($email !== '') {
        $email = preg_replace(
            '/[\r\n]+/',
            '',
            $email
        );

        if (!validateEmail($email)) {
            $errors[] =
                'Please enter a valid email address';
        }
    }

    $data['email'] = $email;

    $phone = trim(
        (string) (
            $input['phone'] ??
            ($input['phone_number'] ?? '')
        )
    );

    $phone = substr(
        $phone,
        0,
        RES_MAX_PHONE_LENGTH
    );

    if ($phone === '') {
        $errors[] =
            'Phone number is required';
    } elseif (!validatePhone($phone)) {
        $errors[] =
            'Please enter a valid phone number with country code (e.g., +254722488706 for Kenya)';
    }

    $data['phone'] = $phone;

    $date = trim(
        (string) ($input['date'] ?? '')
    );

    if ($date === '') {
        $errors[] = 'Date is required';
    } elseif (
        !preg_match(
            '/^\d{4}-\d{2}-\d{2}$/',
            $date
        )
    ) {
        $errors[] =
            'Date must be in YYYY-MM-DD format';
    } else {
        $parts = array_map(
            'intval',
            explode('-', $date)
        );

        if (
            !checkdate(
                $parts[1],
                $parts[2],
                $parts[0]
            ) ||
            $parts[0] < 2000 ||
            $parts[0] > 2100
        ) {
            $errors[] =
                'Please enter a valid date';
        } elseif ($date < date('Y-m-d')) {
            $errors[] =
                'Date cannot be in the past';
        }
    }

    $data['date'] = $date;

    $time = trim(
        (string) ($input['time'] ?? '')
    );

    if ($time === '') {
        $errors[] = 'Time is required';
    } elseif (
        !preg_match(
            '/^(?:[01]\d|2[0-3]):[0-5]\d$/',
            $time
        )
    ) {
        $errors[] =
            'Time must be in HH:MM 24-hour format';
    } else {
        $parts = array_map(
            'intval',
            explode(':', $time)
        );

        if (
            $parts[0] < 12 ||
            $parts[0] > 21 ||
            ($parts[0] === 21 && $parts[1] > 0)
        ) {
            $errors[] =
                'Time must be between 12:00 PM and 9:00 PM (21:00)';
        }
    }

    $data['time'] = $time;

    $guests = filter_var(
        $input['guests'] ??
        ($input['number_of_guests'] ?? ''),
        FILTER_VALIDATE_INT
    );

    if (
        $guests === false ||
        $guests < RES_GUESTS_MIN ||
        $guests > RES_GUESTS_MAX
    ) {
        $errors[] =
            'Number of guests must be between 1 and 50';
    }

    $data['guests'] = (int) $guests;

    $notes = trim(
        (string) (
            $input['special_requests'] ??
            ($input['notes'] ?? '')
        )
    );

    $notes = preg_replace(
        '/[\x00-\x08\x0B\x0C\x0E-\x1F]/',
        '',
        $notes
    );

    if (strlen($notes) > RES_MAX_NOTES_LENGTH) {
        $notes = substr(
            $notes,
            0,
            RES_MAX_NOTES_LENGTH
        );
    }

    $data['special_requests'] = $notes;

    return [
        'errors' => $errors,
        'data'   => $data
    ];
}

// ─────────────────────────────────────────────────────────────────────────────
// Duplicate reservation detection
// ─────────────────────────────────────────────────────────────────────────────

function checkDuplicateReservation(
    array $reservations,
    string $name,
    string $email,
    string $phone,
    string $date,
    string $time
): array {
    $nameNorm = strtolower(trim($name));
    $emailNorm = strtolower(trim($email));
    $phoneNorm = preg_replace(
        '/[^0-9]/',
        '',
        $phone
    );

    foreach ($reservations as $existing) {
        if (!is_array($existing)) {
            continue;
        }

        $en = strtolower(
            trim(
                (string) ($existing['name'] ?? '')
            )
        );

        $ee = strtolower(
            trim(
                (string) ($existing['email'] ?? '')
            )
        );

        $ep = preg_replace(
            '/[^0-9]/',
            '',
            (string) ($existing['phone'] ?? '')
        );

        if (
            $en === $nameNorm &&
            $ee === $emailNorm &&
            $ep === $phoneNorm &&
            ($existing['date'] ?? '') === $date &&
            ($existing['time'] ?? '') === $time
        ) {
            error_log(
                'Duplicate reservation detected for ID: ' .
                ($existing['id'] ?? '?')
            );

            return [
                'is_duplicate' => true,
                'existing_id'  => (string) (
                    $existing['id'] ?? ''
                ),
                'existing_data' => [
                    'name' => $existing['name'] ?? '',
                    'email' => $existing['email'] ?? '',
                    'phone' => $existing['phone'] ?? '',
                    'date' => $existing['date'] ?? '',
                    'time' => $existing['time'] ?? '',
                    'guests' => $existing['guests'] ?? 1,
                    'special_requests' =>
                        $existing['special_requests'] ?? ''
                ]
            ];
        }
    }

    return [
        'is_duplicate' => false
    ];
}

// ─────────────────────────────────────────────────────────────────────────────
// Reservation ID generation
// ─────────────────────────────────────────────────────────────────────────────

function generateReservationId(array $existingIds): string
{
    do {
        $id = 'RES-' .
            strtoupper(
                substr(
                    bin2hex(random_bytes(4)),
                    0,
                    8
                )
            );
    } while (isset($existingIds[$id]));

    return $id;
}

// ─────────────────────────────────────────────────────────────────────────────
// Email data
// ─────────────────────────────────────────────────────────────────────────────

function buildEmailData(array $res): array
{
    return [
        'name' => (string) ($res['name'] ?? ''),
        'email' => (string) ($res['email'] ?? ''),
        'phone' => (string) ($res['phone'] ?? ''),
        'date' => (string) ($res['date'] ?? ''),
        'time' => (string) ($res['time'] ?? ''),
        'guests' => (int) ($res['guests'] ?? 1),
        'requests' => (string) (
            $res['special_requests'] ?? ''
        ),
        'id' => (string) ($res['id'] ?? '')
    ];
}

// ─────────────────────────────────────────────────────────────────────────────
// Response + notification runner
// ─────────────────────────────────────────────────────────────────────────────

function respondAndNotify(
    array $responseData,
    array $reservationsToNotify,
    bool $isUpdate
): void {
    /*
     * Complete notifications BEFORE finishing the HTTP request.
     *
     * Previously fastcgi_finish_request() was called before the WhatsApp
     * notification loop. On some PHP-FPM environments this can terminate
     * or otherwise interfere with code that follows it.
     *
     * WhatsApp and email are therefore deliberately completed first.
     */

    while (ob_get_level()) {
        ob_end_clean();
    }

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }

    foreach ($reservationsToNotify as $reservation) {
        try {
            sendWhatsAppReservation($reservation, $isUpdate);
        } catch (Throwable $e) {
            error_log(
                'WhatsApp notification failed for ' .
                ($reservation['id'] ?? '?') .
                ': ' .
                $e->getMessage()
            );
        }

        if (!$isUpdate) {
            try {
                sendReservationEmail(buildEmailData($reservation));
            } catch (Throwable $e) {
                error_log(
                    'Email notification failed for ' .
                    ($reservation['id'] ?? '?') .
                    ': ' .
                    $e->getMessage()
                );
            }
        }
    }

    /*
     * Only send the HTTP response after notification processing has
     * completed. This keeps the request lifecycle predictable on
     * Hostinger/PHP-FPM.
     */
    http_response_code(200);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate');

    echo json_encode($responseData, JSON_UNESCAPED_UNICODE);
    exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// Main Request Handler
// ─────────────────────────────────────────────────────────────────────────────

try {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    $rawBody = (string) file_get_contents(
        'php://input'
    );

    $input = json_decode(
        $rawBody,
        true
    );

    if (!is_array($input)) {
        $trimmed = ltrim($rawBody);

        if (
            $trimmed !== '' &&
            (
                $trimmed[0] === '{' ||
                $trimmed[0] === '['
            )
        ) {
            sendError(
                'Malformed JSON body',
                400
            );
        }

        $input = [];
    }

    if (
        $method === 'POST' &&
        empty($input)
    ) {
        $input = $_POST;
    }

    $clientIP = getClientIP();

    if (isIpBlocked($clientIP)) {
        sendError(
            'Your IP has been temporarily blocked due to suspicious activity.',
            403
        );
    }

    if ($method === 'OPTIONS') {
        http_response_code(204);
        exit;
    }

    // ============================================================
    // CSRF warm-up (uncached initialization endpoint)
    // ------------------------------------------------------------
    // LiteSpeed page caching can serve contact.php from cache
    // without starting a PHP session, so the CSRF token embedded in
    // the cached HTML belongs to a foreign/previous session. The
    // first reservation POST then fails CSRF validation and the
    // customer sees an error. This lightweight read-only GET gives
    // the visitor their own session + fresh token (the response is
    // always sent no-store) so the FIRST submission succeeds.
    // ============================================================

    if (
        $method === 'GET' &&
        ($_GET['action'] ?? '') === 'csrf'
    ) {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        sendJsonResponse([
            'success'    => true,
            'csrf_token' => $_SESSION['csrf_token']
        ]);
    }

    // ============================================================
    // GET - Admin listing or verified single reservation lookup
    // ============================================================

    if ($method === 'GET') {
        if (furusato_admin_authenticated()) {
            $reservations = readReservations();

            usort(
                $reservations,
                function ($a, $b) {
                    $timeA = strtotime(
                        $a['created'] ??
                        $a['date'] ??
                        '2000-01-01'
                    );

                    $timeB = strtotime(
                        $b['created'] ??
                        $b['date'] ??
                        '2000-01-01'
                    );

                    return $timeB - $timeA;
                }
            );

            $safe = array_map(
                function ($r) {
                    unset(
                        $r['verify_token'],
                        $r['pending_edit_token'],
                        $r['pending_edit_expires'],
                        $r['ip']
                    );

                    return $r;
                },
                $reservations
            );

            sendJsonResponse([
                'reservations' => $safe
            ]);
        }

        $lookupId = trim(
            (string) ($_GET['id'] ?? '')
        );

        $lookupToken = trim(
            (string) ($_GET['token'] ?? '')
        );

        if (
            $lookupId === '' ||
            $lookupToken === ''
        ) {
            sendError(
                'Unauthorized. Please log in to manage reservations.',
                401
            );
        }

        if (
            !preg_match(
                '/^RES-[0-9A-F]{8}$/i',
                $lookupId
            )
        ) {
            sendError(
                'Reservation not found',
                404
            );
        }

        $reservations = readReservations();

        foreach ($reservations as $r) {
            if (!is_array($r)) {
                continue;
            }

            if (
                ($r['id'] ?? '') === $lookupId &&
                !empty($r['verify_token']) &&
                hash_equals(
                    (string) $r['verify_token'],
                    $lookupToken
                )
            ) {
                sendJsonResponse([
                    'reservation' => [
                        'id' => (string) $r['id'],
                        'name' => (string) (
                            $r['name'] ?? ''
                        ),
                        'date' => (string) (
                            $r['date'] ?? ''
                        ),
                        'time' => (string) (
                            $r['time'] ?? ''
                        ),
                        'guests' => (int) (
                            $r['guests'] ?? 1
                        ),
                        'status' => (string) (
                            $r['status'] ?? 'pending'
                        )
                    ]
                ]);
            }
        }

        sendError(
            'Reservation not found',
            404
        );
    }

    // ============================================================
    // PUT - Secure customer edit
    // ============================================================

    if ($method === 'PUT') {
        $reservationId = trim(
            (string) ($input['id'] ?? '')
        );

        $token = trim(
            (string) (
                $input['token'] ??
                ($input['edit_token'] ?? '')
            )
        );

        if ($reservationId === '') {
            sendError(
                'Reservation ID required',
                400
            );
        }

        if (
            !preg_match(
                '/^RES-[0-9A-F]{8}$/i',
                $reservationId
            )
        ) {
            sendError(
                'Reservation not found',
                404
            );
        }

        if ($token === '') {
            sendError(
                'A verification token is required to edit a reservation',
                403
            );
        }

        if (!verifyCsrf(false)) {
            sendError(
                'Invalid or missing security token. Please refresh the page and try again.',
                403
            );
        }

        if (!furusato_admin_authenticated()) {
            $rate = enforcePublicReservationRateLimit(
                $clientIP
            );

            if (!$rate['allowed']) {
                sendJsonResponse([
                    'success' => false,
                    'error' => 'Too many requests. Please try again later.',
                    'data' => [
                        'retry_after' =>
                            $rate['retry_after'],
                        'retry_minutes' =>
                            max(
                                1,
                                (int) ceil(
                                    $rate['retry_after'] / 60
                                )
                            )
                    ]
                ], 429);
            }
        }

        $owned = false;

        foreach (
            readReservations()
            as $r
        ) {
            if (
                !is_array($r) ||
                ($r['id'] ?? '') !== $reservationId
            ) {
                continue;
            }

            $owned =
                (
                    !empty($r['verify_token']) &&
                    hash_equals(
                        (string) $r['verify_token'],
                        $token
                    )
                ) ||
                (
                    !empty($r['pending_edit_token']) &&
                    hash_equals(
                        (string) $r['pending_edit_token'],
                        $token
                    ) &&
                    (int) (
                        $r['pending_edit_expires'] ?? 0
                    ) > time()
                );

            break;
        }

        if (!$owned) {
            sendError(
                'Invalid reservation ID or verification token',
                403
            );
        }

        $validation = validateReservationInput(
            $input
        );

        if (!empty($validation['errors'])) {
            sendError(
                implode(
                    ' ',
                    $validation['errors']
                ),
                422
            );
        }

        $updateData = $validation['data'];

        $outcome = withReservationLock(
            function (array $reservations)
            use (
                $reservationId,
                $token,
                $updateData
            ) {
                foreach (
                    $reservations
                    as $i => $r
                ) {
                    if (!is_array($r)) {
                        continue;
                    }

                    if (
                        ($r['id'] ?? '') !==
                        $reservationId
                    ) {
                        continue;
                    }

                    $now = time();

                    $validToken =
                        (
                            !empty($r['verify_token']) &&
                            hash_equals(
                                (string) $r['verify_token'],
                                $token
                            )
                        ) ||
                        (
                            !empty($r['pending_edit_token']) &&
                            hash_equals(
                                (string) $r['pending_edit_token'],
                                $token
                            ) &&
                            (int) (
                                $r['pending_edit_expires'] ?? 0
                            ) > $now
                        );

                    if (!$validToken) {
                        return [
                            'commit' => false,
                            'payload' => [
                                'status' =>
                                    'auth_failed'
                            ]
                        ];
                    }

                    $reservations[$i] =
                        array_merge(
                            $r,
                            $updateData
                        );

                    $reservations[$i]['updated'] =
                        date('c');

                    unset(
                        $reservations[$i]['pending_edit_token'],
                        $reservations[$i]['pending_edit_expires']
                    );

                    return [
                        'commit' => true,
                        'list' => $reservations,
                        'payload' => [
                            'status' => 'updated',
                            'reservation' =>
                                $reservations[$i]
                        ]
                    ];
                }

                return [
                    'commit' => false,
                    'payload' => [
                        'status' => 'not_found'
                    ]
                ];
            }
        );

        $payloadStatus =
            $outcome['payload']['status'] ??
            'error';

        if ($payloadStatus === 'updated') {
            logAudit(
                'RESERVATION_UPDATED',
                'ID: ' . $reservationId
            );

            respondAndNotify(
                [
                    'success' => true,
                    'id' => $reservationId,
                    'message' =>
                        'Reservation updated successfully!'
                ],
                [
                    $outcome['payload']['reservation']
                ],
                true
            );
        }

        if ($payloadStatus === 'auth_failed') {
            sendError(
                'Invalid verification token for this reservation',
                403
            );
        }

        sendError(
            'Reservation not found',
            404
        );
    }

    // ============================================================
    // POST - Admin operations or public reservation creation
    // ============================================================

    if ($method === 'POST') {
        $action = trim(
            (string) ($input['action'] ?? '')
        );

        // ========================================================
        // Admin operations
        // ========================================================

        if ($action !== '') {
            if (!furusato_admin_authenticated()) {
                sendError(
                    'Unauthorized. Please log in to perform this action.',
                    401
                );
            }

            if (!verifyCsrf(false)) {
                sendError(
                    'Invalid or missing security token. Please refresh the page and try again.',
                    403
                );
            }

            $reservationId = trim(
                (string) ($input['id'] ?? '')
            );

            if (
                !preg_match(
                    '/^RES-[0-9A-F]{8}$/i',
                    $reservationId
                )
            ) {
                sendError(
                    'Invalid reservation ID',
                    400
                );
            }

            // ----------------------------------------------------
            // update_status
            // ----------------------------------------------------

            if ($action === 'update_status') {
                $status = trim(
                    (string) ($input['status'] ?? '')
                );

                if (
                    !in_array(
                        $status,
                        RES_STATUSES,
                        true
                    )
                ) {
                    sendError(
                        'Invalid status value',
                        400
                    );
                }

                $outcome = withReservationLock(
                    function (array $reservations)
                    use ($reservationId, $status) {
                        foreach (
                            $reservations
                            as $i => $r
                        ) {
                            if (!is_array($r)) {
                                continue;
                            }

                            if (
                                ($r['id'] ?? '') !==
                                $reservationId
                            ) {
                                continue;
                            }

                            $reservations[$i]['status'] =
                                $status;

                            $reservations[$i]['updated'] =
                                date('c');

                            return [
                                'commit' => true,
                                'list' => $reservations,
                                'payload' => [
                                    'status' => 'ok'
                                ]
                            ];
                        }

                        return [
                            'commit' => false,
                            'payload' => [
                                'status' => 'not_found'
                            ]
                        ];
                    }
                );

                if (
                    ($outcome['payload']['status'] ?? '') ===
                    'ok'
                ) {
                    logAudit(
                        'RESERVATION_STATUS_UPDATED',
                        'ID: ' .
                        $reservationId .
                        ', Status: ' .
                        $status
                    );

                    sendJsonResponse([
                        'success' => true
                    ]);
                }

                sendError(
                    'Reservation not found',
                    404
                );
            }

            // ----------------------------------------------------
            // update_notes
            // ----------------------------------------------------

            if ($action === 'update_notes') {
                $notes = trim(
                    (string) ($input['notes'] ?? '')
                );

                $notes = preg_replace(
                    '/[\x00-\x08\x0B\x0C\x0E-\x1F]/',
                    '',
                    $notes
                );

                if (
                    strlen($notes) >
                    RES_MAX_NOTES_LENGTH
                ) {
                    $notes = substr(
                        $notes,
                        0,
                        RES_MAX_NOTES_LENGTH
                    );
                }

                $outcome = withReservationLock(
                    function (array $reservations)
                    use ($reservationId, $notes) {
                        foreach (
                            $reservations
                            as $i => $r
                        ) {
                            if (!is_array($r)) {
                                continue;
                            }

                            if (
                                ($r['id'] ?? '') !==
                                $reservationId
                            ) {
                                continue;
                            }

                            $reservations[$i]['admin_notes'] =
                                $notes;

                            $reservations[$i]['updated'] =
                                date('c');

                            return [
                                'commit' => true,
                                'list' => $reservations,
                                'payload' => [
                                    'status' => 'ok'
                                ]
                            ];
                        }

                        return [
                            'commit' => false,
                            'payload' => [
                                'status' => 'not_found'
                            ]
                        ];
                    }
                );

                if (
                    ($outcome['payload']['status'] ?? '') ===
                    'ok'
                ) {
                    logAudit(
                        'RESERVATION_NOTES_UPDATED',
                        'ID: ' .
                        $reservationId
                    );

                    sendJsonResponse([
                        'success' => true
                    ]);
                }

                sendError(
                    'Reservation not found',
                    404
                );
            }

            // ----------------------------------------------------
            // delete
            // ----------------------------------------------------

            if ($action === 'delete') {
                $outcome = withReservationLock(
                    function (array $reservations)
                    use ($reservationId) {
                        $count =
                            count($reservations);

                        $filtered =
                            array_values(
                                array_filter(
                                    $reservations,
                                    function ($r)
                                    use ($reservationId) {
                                        return
                                            !is_array($r) ||
                                            ($r['id'] ?? '') !==
                                                $reservationId;
                                    }
                                )
                            );

                        if (
                            count($filtered) ===
                            $count
                        ) {
                            return [
                                'commit' => false,
                                'payload' => [
                                    'status' =>
                                        'not_found'
                                ]
                            ];
                        }

                        return [
                            'commit' => true,
                            'list' => $filtered,
                            'payload' => [
                                'status' => 'ok'
                            ]
                        ];
                    }
                );

                if (
                    ($outcome['payload']['status'] ?? '') ===
                    'ok'
                ) {
                    logAudit(
                        'RESERVATION_DELETED',
                        'ID: ' .
                        $reservationId
                    );

                    sendJsonResponse([
                        'success' => true
                    ]);
                }

                sendError(
                    'Reservation not found',
                    404
                );
            }

            sendError(
                'Invalid action',
                400
            );
        }

        // ========================================================
        // Public reservation creation
        // ========================================================

        $isAdmin =
            furusato_admin_authenticated();

        $rate = [
            'is_high_volume' => false
        ];

        if (!$isAdmin) {
            $rate =
                enforcePublicReservationRateLimit(
                    $clientIP
                );

            if (!$rate['allowed']) {
                sendJsonResponse([
                    'success' => false,
                    'error' =>
                        'Too many requests. Please try again later.',
                    'data' => [
                        'retry_after' =>
                            $rate['retry_after'],
                        'retry_minutes' =>
                            max(
                                1,
                                (int) ceil(
                                    $rate['retry_after'] /
                                    60
                                )
                            )
                    ]
                ], 429);
            }
        }

        // CSRF is mandatory for creating a reservation.
        if (!verifyCsrf(false)) {
            sendError(
                'Invalid or missing security token. Please refresh the page and try again.',
                403
            );
        }

        // Honeypot bot trap.
        if (!empty($input['website'])) {
            sendJsonResponse([
                'success' => true,
                'id' => 'bot-' . time()
            ]);
        }

        $validation =
            validateReservationInput($input);

        if (!empty($validation['errors'])) {
            sendError(
                implode(
                    ' ',
                    $validation['errors']
                ),
                422
            );
        }

        $data = $validation['data'];

        $outcome = withReservationLock(
            function (array $reservations)
            use ($clientIP, $data) {
                $duplicateCheck =
                    checkDuplicateReservation(
                        $reservations,
                        $data['name'],
                        $data['email'],
                        $data['phone'],
                        $data['date'],
                        $data['time']
                    );

                if ($duplicateCheck['is_duplicate']) {
                    $pendingToken =
                        generateSecureToken(16);

                    foreach (
                        $reservations
                        as $i => $r
                    ) {
                        if (!is_array($r)) {
                            continue;
                        }

                        if (
                            ($r['id'] ?? '') ===
                            $duplicateCheck['existing_id']
                        ) {
                            $reservations[$i][
                                'pending_edit_token'
                            ] = $pendingToken;

                            $reservations[$i][
                                'pending_edit_expires'
                            ] =
                                time() +
                                RES_PENDING_EDIT_TTL;

                            break;
                        }
                    }

                    return [
                        'commit' => true,
                        'list' => $reservations,
                        'payload' => [
                            'status' =>
                                'duplicate',
                            'existing_id' =>
                                $duplicateCheck[
                                    'existing_id'
                                ],
                            'existing_data' =>
                                $duplicateCheck[
                                    'existing_data'
                                ],
                            'edit_token' =>
                                $pendingToken
                        ]
                    ];
                }

                $existingIds = [];

                foreach (
                    $reservations
                    as $r
                ) {
                    if (is_array($r)) {
                        $existingIds[
                            $r['id'] ?? ''
                        ] = true;
                    }
                }

                $reservation = [
                    'id' =>
                        generateReservationId(
                            $existingIds
                        ),

                    'name' =>
                        $data['name'],

                    'email' =>
                        $data['email'],

                    'phone' =>
                        $data['phone'],

                    'date' =>
                        $data['date'],

                    'time' =>
                        $data['time'],

                    'guests' =>
                        $data['guests'],

                    'special_requests' =>
                        $data['special_requests'],

                    'status' =>
                        'pending',

                    'ip' =>
                        $clientIP,

                    'created' =>
                        date('c'),

                    // Original-format data is stored.
                    // Escaping happens at the display boundary.
                    'verify_token' =>
                        generateSecureToken(32)
                ];

                $reservations[] =
                    $reservation;

                return [
                    'commit' => true,
                    'list' => $reservations,
                    'payload' => [
                        'status' => 'created',
                        'reservation' =>
                            $reservation
                    ]
                ];
            }
        );

        $payloadStatus =
            $outcome['payload']['status'] ??
            'error';

        if ($payloadStatus === 'duplicate') {
            sendJsonResponse([
                'success' => false,
                'duplicate_detected' => true,
                'existing_id' =>
                    $outcome['payload']['existing_id'],
                'existing_data' =>
                    $outcome['payload']['existing_data'],
                'edit_token' =>
                    $outcome['payload']['edit_token'],
                'message' =>
                    'A reservation with these exact details already exists. Would you like to edit it?'
            ], 409);
        }

        if ($payloadStatus === 'created') {
            $created =
                $outcome['payload']['reservation'];

            $responseData = [
                'success' => true,
                'id' => $created['id'],

                // Customer needs this token to edit later.
                'edit_token' =>
                    $created['verify_token'],

                'message' =>
                    'Reservation received! We will confirm within 24 hours.'
            ];

            if (!empty($rate['is_high_volume'])) {
                $responseData['warning'] =
                    'You have made ' .
                    $rate['count'] .
                    ' reservations in the past hour. If you need to make many reservations, please call us directly at 0722 488 706 for assistance.';
            }

            respondAndNotify(
                $responseData,
                [$created],
                false
            );
        }

        throw new RuntimeException(
            'Unexpected reservation storage outcome'
        );
    }

    sendError(
        'Invalid request method',
        405
    );

} catch (Throwable $e) {
    error_log(
        'Reservation API error: ' .
        $e->getMessage() .
        ' in ' .
        $e->getFile() .
        ' on line ' .
        $e->getLine()
    );

    sendError(
        'An error occurred. Please try again.',
        500
    );
}