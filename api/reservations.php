<?php
/**
 * api/reservations.php - Furusato Restaurant Reservations API
 * 
 * SIMPLIFIED DUPLICATE CHECK:
 * - Only checks for EXACT same name + email + phone + date + time on the SAME DAY
 * - ANY change (different name, email, phone, date, or time) = NEW reservation
 * - No time window restriction - works for any past/future reservations
 * - Edit existing reservation support
 */

// ============================================================
// START SESSION - Required for admin functions only
// ============================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ============================================================
// CORS Headers
// ============================================================
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');
header('Access-Control-Max-Age: 86400');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

while (ob_get_level()) ob_end_clean();
ob_start();

date_default_timezone_set('Africa/Nairobi');

// ─────────────────────────────────────────────────────────────────────────────
// Constants
// ─────────────────────────────────────────────────────────────────────────────

const RATE_LIMIT_RESERVATIONS = 20;       // Max 20 reservations per IP per hour (soft limit)
const RATE_LIMIT_WINDOW = 3600;           // 1 hour window
const MAX_NAME_LENGTH = 100;
const MAX_EMAIL_LENGTH = 100;
const MAX_PHONE_LENGTH = 20;
const MAX_NOTES_LENGTH = 500;

// ─────────────────────────────────────────────────────────────────────────────
// Helper Functions
// ─────────────────────────────────────────────────────────────────────────────

function sendJsonResponse($data, $statusCode = 200) {
    while (ob_get_level()) ob_end_clean();
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Access-Control-Allow-Origin: *');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function sendError($message, $statusCode = 400) {
    sendJsonResponse(['success' => false, 'error' => $message], $statusCode);
}

// ─────────────────────────────────────────────────────────────────────────────
// Rate Limiting - SOFT LIMIT (warns but does NOT block)
// ─────────────────────────────────────────────────────────────────────────────

function checkRateLimit($ip, $limit = RATE_LIMIT_RESERVATIONS, $window = RATE_LIMIT_WINDOW) {
    $rateFile = __DIR__ . '/../data/rate_limits_reservations.json';
    $data = [];
    
    $dataDir = __DIR__ . '/../data';
    if (!is_dir($dataDir)) {
        @mkdir($dataDir, 0755, true);
    }
    
    if (file_exists($rateFile)) {
        $content = file_get_contents($rateFile);
        $data = json_decode($content, true) ?: [];
    }
    
    $now = time();
    $key = md5($ip);
    
    if (isset($data[$key])) {
        $data[$key] = array_filter($data[$key], function($ts) use ($now, $window) {
            return ($now - $ts) < $window;
        });
        $data[$key] = array_values($data[$key]);
    } else {
        $data[$key] = [];
    }
    
    $attemptCount = count($data[$key]);
    
    // SOFT LIMIT: Just track and warn, but NEVER block
    $data[$key][] = $now;
    file_put_contents($rateFile, json_encode($data), LOCK_EX);
    
    $isHighVolume = ($attemptCount + 1) >= $limit;
    
    return [
        'allowed' => true,
        'is_high_volume' => $isHighVolume,
        'count' => $attemptCount + 1,
        'limit' => $limit
    ];
}

// ─────────────────────────────────────────────────────────────────────────────
// IP Blocklist Check
// ─────────────────────────────────────────────────────────────────────────────

function isIpBlocked($ip) {
    $blocklistFile = __DIR__ . '/../data/blocklist.json';
    if (!file_exists($blocklistFile)) return false;
    
    $blocklist = json_decode(file_get_contents($blocklistFile), true);
    if (!is_array($blocklist)) return false;
    
    foreach ($blocklist as $blocked) {
        if ($blocked['ip'] === $ip && $blocked['expires'] > time()) {
            return true;
        }
    }
    return false;
}

// ─────────────────────────────────────────────────────────────────────────────
// Admin Session Check
// ─────────────────────────────────────────────────────────────────────────────

function isAdminAuthenticated() {
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

// ─────────────────────────────────────────────────────────────────────────────
// Data Functions
// ─────────────────────────────────────────────────────────────────────────────

function getReservations() {
    $file = __DIR__ . '/../data/reservations.json';
    if (!file_exists($file)) return [];
    $content = file_get_contents($file);
    $data = json_decode($content, true);
    return is_array($data) ? $data : [];
}

function saveReservations($data) {
    $file = __DIR__ . '/../data/reservations.json';
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    return file_put_contents($file, $json, LOCK_EX) !== false;
}

function updateReservation($id, $updatedData) {
    $reservations = getReservations();
    foreach ($reservations as &$res) {
        if ($res['id'] === $id) {
            $res = array_merge($res, $updatedData);
            $res['updated'] = date('c');
            saveReservations($reservations);
            return true;
        }
    }
    return false;
}

// ─────────────────────────────────────────────────────────────────────────────
// Validation Functions
// ─────────────────────────────────────────────────────────────────────────────

function validatePhoneNumber($phone) {
    $cleaned = preg_replace('/[\s\-\(\)\.]/', '', $phone);
    
    if (!preg_match('/^[\+0-9]/', $cleaned)) {
        return false;
    }
    
    $digitsOnly = preg_replace('/[^0-9]/', '', $cleaned);
    $digitCount = strlen($digitsOnly);
    
    if ($digitCount < 8 || $digitCount > 15) {
        return false;
    }
    
    return true;
}

// ─────────────────────────────────────────────────────────────────────────────
// SIMPLIFIED DUPLICATE CHECK:
// - Checks for EXACT match: name + email + phone + date + time
// - NO time window - checks ALL existing reservations
// - ANY field different = allowed as new reservation
// - Returns the existing reservation ID if found for editing
// ─────────────────────────────────────────────────────────────────────────────

function checkDuplicateReservation($reservations, $name, $email, $phone, $date, $time) {
    $nameNormalized = strtolower(trim($name));
    $emailNormalized = strtolower(trim($email));
    $phoneNormalized = preg_replace('/[^0-9]/', '', $phone);
    
    foreach ($reservations as $existing) {
        $existingNameNormalized = strtolower(trim($existing['name'] ?? ''));
        $existingEmailNormalized = strtolower(trim($existing['email'] ?? ''));
        $existingPhoneNormalized = preg_replace('/[^0-9]/', '', $existing['phone'] ?? '');
        
        // Check for EXACT match on ALL fields
        if ($existingNameNormalized === $nameNormalized && 
            $existingEmailNormalized === $emailNormalized && 
            $existingPhoneNormalized === $phoneNormalized && 
            $existing['date'] === $date &&
            $existing['time'] === $time) {
            
            error_log("Duplicate found: Name={$name}, Email={$email}, Phone={$phone}, Date={$date}, Time={$time}");
            return [
                'is_duplicate' => true,
                'existing_id' => $existing['id'],
                'existing_data' => [
                    'name' => $existing['name'],
                    'email' => $existing['email'],
                    'phone' => $existing['phone'],
                    'date' => $existing['date'],
                    'time' => $existing['time'],
                    'guests' => $existing['guests'],
                    'special_requests' => $existing['special_requests']
                ]
            ];
        }
    }
    
    return ['is_duplicate' => false];
}

// ─────────────────────────────────────────────────────────────────────────────
// WhatsApp Notification
// ─────────────────────────────────────────────────────────────────────────────

function sendWhatsAppReservation($reservation, $isUpdate = false) {
    $settingsFile = __DIR__ . '/../data/settings.json';
    $settings = [];
    if (file_exists($settingsFile)) {
        $settings = json_decode(file_get_contents($settingsFile), true);
    }
    
    $yourWhatsAppNumber = $settings['whatsapp'] ?? '';
    $apiKey = $settings['whatsapp_api_key'] ?? '';
    if (empty($yourWhatsAppNumber) || empty($apiKey)) {
        error_log('WhatsApp notification skipped: not configured');
        return false;
    }
    
    $submissionTime = date('h:i A');
    $submissionDate = date('l, F j, Y');
    $reservationTimeFormatted = date('h:i A', strtotime($reservation['time']));
    $reservationDateFormatted = date('l, F j, Y', strtotime($reservation['date']));
    
    $type = $isUpdate ? "🔄 *RESERVATION UPDATED* 🔄" : "🆕 *FURUSATO RESERVATION* 🆕";
    
    $message = $type . "\n\n";
    $message .= "━━━━━━━━━━━━━━━━━━━━━\n";
    $message .= "👤 *Guest:* " . $reservation['name'] . "\n";
    $message .= "📞 *Phone:* " . $reservation['phone'] . "\n";
    $message .= "━━━━━━━━━━━━━━━━━━━━━\n";
    $message .= "📅 *Date:* " . $reservationDateFormatted . "\n";
    $message .= "⏰ *Time:* " . $reservationTimeFormatted . " (Nairobi Time)\n";
    $message .= "👥 *Party:* " . $reservation['guests'] . " people\n";
    $message .= "━━━━━━━━━━━━━━━━━━━━━\n";
    
    if (!empty($reservation['special_requests'])) {
        $message .= "📝 *Special Requests:*\n";
        $message .= "   " . $reservation['special_requests'] . "\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━━\n";
    }
    
    $message .= "\n🆔 *Reservation ID:* " . $reservation['id'] . "\n";
    $message .= "⏱️ *Submitted:* " . $submissionTime . " on " . $submissionDate . " (Nairobi Time)\n\n";
    $message .= "━━━━━━━━━━━━━━━━━━━━━\n";
    $message .= "📍 Ring Road Parklands, Westlands, Nairobi\n";
    $message .= "📞 0722 488 706 | 0734 639 203\n";
    $message .= "🕐 Open Daily: 12pm - 9pm";
    
    $encodedMessage = urlencode($message);
    $url = "https://api.callmebot.com/whatsapp.php?phone={$yourWhatsAppNumber}&text={$encodedMessage}&apikey={$apiKey}";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Furusato-Restaurant/1.0');
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return ($httpCode == 200 || strpos($response, 'Message sent') !== false);
}

// ─────────────────────────────────────────────────────────────────────────────
// Email Notification
// ─────────────────────────────────────────────────────────────────────────────

function sendEmail($to, $subject, $htmlBody) {
    if (empty($to) || !filter_var($to, FILTER_VALIDATE_EMAIL)) return true;
    
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: Furusato Restaurant <reservations@furusatorestaurant.com>\r\n";
    $headers .= "Reply-To: furusatoreservation@gmail.com\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";
    $encodedSubject = "=?UTF-8?B?" . base64_encode($subject) . "?=";
    
    return @mail($to, $encodedSubject, $htmlBody, $headers);
}

// ─────────────────────────────────────────────────────────────────────────────
// Generate Secure Reservation ID
// ─────────────────────────────────────────────────────────────────────────────

function generateReservationId() {
    return 'RES-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
}

// ─────────────────────────────────────────────────────────────────────────────
// Main Request Handler
// ─────────────────────────────────────────────────────────────────────────────

try {
    $method = $_SERVER['REQUEST_METHOD'];
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) $input = [];
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($input)) {
        $input = $_POST;
    }
    
    $clientIP = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $clientIP = trim($ips[0]);
    }
    
    if (isIpBlocked($clientIP)) {
        sendError('Your IP has been temporarily blocked due to suspicious activity.', 403);
    }
    
    if ($method === 'OPTIONS') {
        sendJsonResponse(['success' => true]);
    }
    
    // ============================================================
    // GET: Fetch reservations
    // ============================================================
    if ($method === 'GET') {
        $reservations = getReservations();
        usort($reservations, function($a, $b) {
            $timeA = strtotime($a['created'] ?? $a['date'] ?? '2000-01-01');
            $timeB = strtotime($b['created'] ?? $b['date'] ?? '2000-01-01');
            return $timeB - $timeA;
        });
        
        if (!isAdminAuthenticated()) {
            $publicReservations = array_map(function($r) {
                return [
                    'id' => $r['id'],
                    'name' => substr($r['name'], 0, 1) . '***',
                    'date' => $r['date'],
                    'time' => $r['time'],
                    'guests' => $r['guests'],
                    'status' => $r['status'] ?? 'pending'
                ];
            }, array_slice($reservations, 0, 10));
            sendJsonResponse(['reservations' => $publicReservations]);
        }
        
        sendJsonResponse(['reservations' => $reservations]);
    }
    
    // ============================================================
    // PUT: Update existing reservation (for edit feature)
    // ============================================================
    if ($method === 'PUT') {
        $reservationId = $input['id'] ?? '';
        if (empty($reservationId)) {
            sendError('Reservation ID required', 400);
        }
        
        $name = trim(substr($input['name'] ?? '', 0, MAX_NAME_LENGTH));
        $email = trim(substr($input['email'] ?? '', 0, MAX_EMAIL_LENGTH));
        $phone = trim(substr($input['phone'] ?? '', 0, MAX_PHONE_LENGTH));
        $date = trim($input['date'] ?? '');
        $time = trim($input['time'] ?? '');
        $guests = (int)($input['guests'] ?? 0);
        $notes = trim(substr($input['special_requests'] ?? '', 0, MAX_NOTES_LENGTH));
        
        $updateData = [
            'name' => htmlspecialchars($name, ENT_QUOTES, 'UTF-8'),
            'email' => htmlspecialchars($email, ENT_QUOTES, 'UTF-8'),
            'phone' => htmlspecialchars($phone, ENT_QUOTES, 'UTF-8'),
            'date' => $date,
            'time' => $time,
            'guests' => $guests,
            'special_requests' => htmlspecialchars($notes, ENT_QUOTES, 'UTF-8'),
            'updated' => date('c')
        ];
        
        if (updateReservation($reservationId, $updateData)) {
            if (function_exists('fastcgi_finish_request')) {
                fastcgi_finish_request();
            }
            $reservation = array_merge(['id' => $reservationId], $updateData);
            sendWhatsAppReservation($reservation, true);
            
            sendJsonResponse(['success' => true, 'message' => 'Reservation updated successfully!']);
        } else {
            sendError('Reservation not found', 404);
        }
    }
    
    // ============================================================
    // POST: New reservation with duplicate detection
    // ============================================================
    if ($method === 'POST' && empty($input['action'])) {
        
        // STEP 1: Check rate limit (soft)
        $rateLimitResult = checkRateLimit($clientIP);
        
        // STEP 2: Honeypot check (bot protection)
        if (!empty($input['website'])) {
            sendJsonResponse(['success' => true, 'id' => 'bot-' . time()]);
        }
        
        // STEP 3: Validate input data
        $name = trim(substr($input['name'] ?? $input['full_name'] ?? '', 0, MAX_NAME_LENGTH));
        $email = trim(substr($input['email'] ?? '', 0, MAX_EMAIL_LENGTH));
        $phone = trim(substr($input['phone'] ?? $input['phone_number'] ?? '', 0, MAX_PHONE_LENGTH));
        $date = trim($input['date'] ?? '');
        $time = trim($input['time'] ?? '');
        $guests = (int)($input['guests'] ?? $input['number_of_guests'] ?? 0);
        $notes = trim(substr($input['special_requests'] ?? $input['notes'] ?? '', 0, MAX_NOTES_LENGTH));
        
        $errors = [];
        
        if (strlen($name) < 2) $errors[] = 'Full name is required (minimum 2 characters)';
        if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address';
        }
        if (empty($phone)) {
            $errors[] = 'Phone number is required';
        } elseif (!validatePhoneNumber($phone)) {
            $errors[] = 'Please enter a valid phone number with country code (e.g., +254722488706 for Kenya)';
        }
        if (empty($date)) {
            $errors[] = 'Date is required';
        } else {
            $selectedDate = strtotime($date);
            $today = strtotime(date('Y-m-d'));
            if ($selectedDate < $today) $errors[] = 'Date cannot be in the past';
        }
        if (empty($time)) {
            $errors[] = 'Time is required';
        } else {
            $timeParts = explode(':', $time);
            $hours = (int)($timeParts[0] ?? 0);
            $minutes = (int)($timeParts[1] ?? 0);
            if ($hours < 12 || $hours > 21 || ($hours == 21 && $minutes > 0)) {
                $errors[] = 'Time must be between 12:00 PM and 9:00 PM';
            }
        }
        if ($guests < 1 || $guests > 50) $errors[] = 'Number of guests must be between 1 and 50';
        
        if (!empty($errors)) {
            sendError(implode(' ', $errors), 422);
        }
        
        // STEP 4: Check for duplicate - EXACT same details only
        $reservations = getReservations();
        $duplicateCheck = checkDuplicateReservation($reservations, $name, $email, $phone, $date, $time);
        
        if ($duplicateCheck['is_duplicate']) {
            // Return duplicate info for frontend to handle
            sendJsonResponse([
                'success' => false,
                'duplicate_detected' => true,
                'existing_id' => $duplicateCheck['existing_id'],
                'existing_data' => $duplicateCheck['existing_data'],
                'message' => 'A reservation with these exact details already exists. Would you like to edit it?'
            ], 409);
        }
        
        // STEP 5: Save new reservation
        $reservationId = generateReservationId();
        
        $reservation = [
            'id' => $reservationId,
            'name' => htmlspecialchars($name, ENT_QUOTES, 'UTF-8'),
            'email' => htmlspecialchars($email, ENT_QUOTES, 'UTF-8'),
            'phone' => htmlspecialchars($phone, ENT_QUOTES, 'UTF-8'),
            'date' => $date,
            'time' => $time,
            'guests' => $guests,
            'special_requests' => htmlspecialchars($notes, ENT_QUOTES, 'UTF-8'),
            'status' => 'pending',
            'ip' => $clientIP,
            'created' => date('c'),
        ];
        
        $reservations[] = $reservation;
        saveReservations($reservations);
        
        // STEP 6: Send notifications
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }
        sendWhatsAppReservation($reservation);
        
        $adminHtml = "<h2>New Reservation</h2>
        <p><strong>Name:</strong> " . htmlspecialchars($name) . "</p>
        <p><strong>Phone:</strong> " . htmlspecialchars($phone) . "</p>
        <p><strong>Email:</strong> " . htmlspecialchars($email) . "</p>
        <p><strong>Date:</strong> " . htmlspecialchars($date) . "</p>
        <p><strong>Time:</strong> " . htmlspecialchars($time) . "</p>
        <p><strong>Guests:</strong> " . $guests . "</p>
        <p><strong>Special Requests:</strong> " . nl2br(htmlspecialchars($notes)) . "</p>
        <p><strong>ID:</strong> " . $reservationId . "</p>";
        sendEmail('furusatoreservation@gmail.com', 'New Reservation: ' . $name, $adminHtml);
        
        if (!empty($email)) {
            $clientHtml = "<h2>Reservation Received!</h2>
            <p>Dear " . htmlspecialchars($name) . ",</p>
            <p>Thank you for choosing Furusato. We have received your reservation request.</p>
            <p><strong>Reference:</strong> " . $reservationId . "</p>
            <p><strong>Details:</strong><br>
            Date: " . htmlspecialchars($date) . "<br>
            Time: " . htmlspecialchars($time) . "<br>
            Guests: " . $guests . "</p>
            <p>We will confirm your table within 24 hours.</p>
            <p>Questions? Call us at 0722 488 706</p>";
            sendEmail($email, 'Reservation Confirmation - Furusato', $clientHtml);
        }
        
        // STEP 7: Return success
        $responseData = [
            'success' => true, 
            'id' => $reservationId, 
            'message' => 'Reservation received! We will confirm within 24 hours.'
        ];
        
        if ($rateLimitResult['is_high_volume']) {
            $responseData['warning'] = 'You have made ' . $rateLimitResult['count'] . ' reservations in the past hour. If you need to make many reservations, please call us directly at 0722 488 706 for assistance.';
        }
        
        sendJsonResponse($responseData);
    }
    
    // ============================================================
    // POST with action: Admin operations
    // ============================================================
    if ($method === 'POST' && isset($input['action'])) {
        if (!isAdminAuthenticated()) {
            sendError('Unauthorized. Please log in to perform this action.', 401);
        }
        
        $action = $input['action'];
        
        if ($action === 'update_status') {
            $id = $input['id'] ?? '';
            $status = $input['status'] ?? '';
            
            if (!in_array($status, ['pending', 'confirmed', 'cancelled'])) {
                sendError('Invalid status value', 400);
            }
            
            $reservations = getReservations();
            $updated = false;
            foreach ($reservations as &$r) {
                if ($r['id'] === $id) {
                    $r['status'] = $status;
                    $r['updated'] = date('c');
                    $updated = true;
                    break;
                }
            }
            if ($updated) {
                saveReservations($reservations);
                sendJsonResponse(['success' => true]);
            } else {
                sendError('Reservation not found', 404);
            }
        }
        
        if ($action === 'delete') {
            $id = $input['id'] ?? '';
            $reservations = getReservations();
            $originalCount = count($reservations);
            $reservations = array_values(array_filter($reservations, function($r) use ($id) {
                return $r['id'] !== $id;
            }));
            if (count($reservations) < $originalCount) {
                saveReservations($reservations);
                sendJsonResponse(['success' => true]);
            } else {
                sendError('Reservation not found', 404);
            }
        }
        
        sendError('Invalid action', 400);
    }
    
    sendError('Invalid request method', 405);
    
} catch (Exception $e) {
    error_log('Reservation API Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine());
    sendError('An error occurred. Please try again.', 500);
}
?>