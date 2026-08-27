<?php
/**
 * includes/whatsapp.php - WhatsApp Notification System
 * Sends formatted reservation details to your WhatsApp Business number
 * UPDATED: Nairobi timezone, cleaner formatting, no unnecessary emojis
 */

// Set timezone to Nairobi
date_default_timezone_set('Africa/Nairobi');

function sendWhatsAppReservation($reservation) {
    // Load credentials from settings.json
    $settingsFile = __DIR__ . '/../data/settings.json';
    $settings = [];
    if (file_exists($settingsFile)) {
        $settings = json_decode(file_get_contents($settingsFile), true) ?: [];
    }
    $yourWhatsAppNumber = $settings['whatsapp'] ?? '';
    $apiKey = $settings['whatsapp_api_key'] ?? '';
    
    if (empty($yourWhatsAppNumber) || empty($apiKey)) {
        error_log('WhatsApp notification skipped: not configured');
        return false;
    }
    
    // Get current Nairobi time
    $submissionTime = date('h:i A');
    $submissionDate = date('l, F j, Y');
    
    // Format reservation time
    $reservationTimeFormatted = date('h:i A', strtotime($reservation['time']));
    $reservationDateFormatted = date('l, F j, Y', strtotime($reservation['date']));
    
    // Build the formatted message (clean, no unnecessary emojis)
    $message = "NEW RESERVATION - Furusato\n";
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
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
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
    $settingsFile = __DIR__ . '/../data/settings.json';
    $settings = [];
    if (file_exists($settingsFile)) {
        $settings = json_decode(file_get_contents($settingsFile), true) ?: [];
    }
    $yourWhatsAppNumber = $settings['whatsapp'] ?? '';
    $apiKey = $settings['whatsapp_api_key'] ?? '';
    
    if (empty($yourWhatsAppNumber) || empty($apiKey)) {
        return ['success' => false, 'response' => 'WhatsApp not configured'];
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