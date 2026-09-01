<?php
/**
 * includes/whatsapp.php - WhatsApp Notification System
 * Sends formatted reservation details to your WhatsApp Business number
 * UPDATED: Nairobi timezone, cleaner formatting, no unnecessary emojis
 */

// Set timezone to Nairobi
date_default_timezone_set('Africa/Nairobi');

// Credentials come from server-side configuration (see includes/config.php):
// environment variables and/or the production-only, gitignored includes/.env.php,
// falling back to the admin-managed values in data/settings.json.
require_once __DIR__ . '/config.php';

function sendWhatsAppReservation($reservation, $isUpdate = false) {
    $yourWhatsAppNumber = furusato_whatsapp_phone();
    $apiKey = furusato_whatsapp_api_key();
    if ($apiKey === '') {
        $settingsFile = __DIR__ . '/../data/settings.json';
        if (is_file($settingsFile)) {
            $stored = json_decode((string) file_get_contents($settingsFile), true);
            if (is_array($stored)) {
                // Legacy admin-stored key (settings.whatsapp may be a flat string
                // or the nested admin structure; guard both without warnings).
                $wa = $stored['whatsapp'] ?? null;
                if (is_array($wa)) {
                    $apiKey = (string) ($wa['api_key'] ?? $stored['whatsapp_api_key'] ?? '');
                } else {
                    $apiKey = (string) ($stored['whatsapp_api_key'] ?? '');
                }
            }
        }
    }

    // Gracefully skip (with a log entry) when WhatsApp is not configured.
    if ($apiKey === '' || $yourWhatsAppNumber === '') {
        error_log('WhatsApp notification skipped for reservation ' . ($reservation['id'] ?? '?') . ': WhatsApp is not configured.');
        return false;
    }
    
    // Get current Nairobi time
    $submissionTime = date('h:i A');
    $submissionDate = date('l, F j, Y');
    
    // Format reservation time
    $reservationTimeFormatted = date('h:i A', strtotime($reservation['time']));
    $reservationDateFormatted = date('l, F j, Y', strtotime($reservation['date']));
    
    // Build the formatted message (clean, no unnecessary emojis). Updates are
    // flagged so staff can tell a modification from a brand new reservation.
    $message = ($isUpdate ? "RESERVATION UPDATED - Furusato\n" : "NEW RESERVATION - Furusato\n");
    $message .= "----------------------------------------\n";
    $message .= "Guest: " . $reservation['name'] . "\n";
    $message .= "Phone: " . $reservation['phone'] . "\n";
    $message .= "----------------------------------------\n";
    $message .= "Date: " . $reservationDateFormatted . "\n";
    $message .= "Time: " . $reservationTimeFormatted . " (Nairobi Time)\n";
    $message .= "Party: " . $reservation['guests'] . " people\n";
    $message .= "----------------------------------------\n";
    
    if (!empty($reservation['special_requests'])) {
        $message .= "Special Requests:\n";
        $message .= "  " . $reservation['special_requests'] . "\n";
        $message .= "----------------------------------------\n";
    }
    
    $message .= "Reservation ID: " . $reservation['id'] . "\n";
    $message .= "Submitted: " . $submissionTime . " on " . $submissionDate . " (Nairobi Time)\n";
    $message .= "----------------------------------------\n";
    $message .= "Location: Ring Road Parklands, Westlands, Nairobi\n";
    $message .= "Restaurant: 0722 488 706 | 0734 639 203\n";
    $message .= "Open Daily: 12pm - 9pm";
    
    // URL encode the message
    $encodedMessage = urlencode($message);
    
    // CallMeBot API URL
    $url = "https://api.callmebot.com/whatsapp.php?phone={$yourWhatsAppNumber}&text={$encodedMessage}&apikey={$apiKey}";
    
    // Send the request
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    // Log the result for debugging
    if ($httpCode == 200 || strpos($response, 'Message sent') !== false) {
        error_log("WhatsApp notification sent successfully for reservation: " . $reservation['id']);
        return true;
    } else {
        error_log("WhatsApp notification failed: HTTP $httpCode - $error - Response: $response");
        return false;
    }
}

// Send a simple test message
function sendWhatsAppTest($message) {
    $yourWhatsAppNumber = furusato_whatsapp_phone();
    $apiKey = furusato_whatsapp_api_key();
    if ($apiKey === '') {
        $settingsFile = __DIR__ . '/../data/settings.json';
        if (is_file($settingsFile)) {
            $stored = json_decode((string) file_get_contents($settingsFile), true);
            if (is_array($stored)) {
                $wa = $stored['whatsapp'] ?? null;
                if (is_array($wa)) {
                    $apiKey = (string) ($wa['api_key'] ?? $stored['whatsapp_api_key'] ?? '');
                } else {
                    $apiKey = (string) ($stored['whatsapp_api_key'] ?? '');
                }
            }
        }
    }
    if ($apiKey === '' || $yourWhatsAppNumber === '') {
        return ['success' => false, 'response' => 'WhatsApp is not configured'];
    }
    
    $url = "https://api.callmebot.com/whatsapp.php?phone={$yourWhatsAppNumber}&text=" . urlencode($message) . "&apikey={$apiKey}";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return ['success' => $httpCode == 200, 'response' => $response];
}
?>