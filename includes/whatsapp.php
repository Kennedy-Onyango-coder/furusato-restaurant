<?php
/**
 * includes/whatsapp.php - WhatsApp Notification System
 * Sends formatted reservation details to the configured WhatsApp number.
 *
 * CallMeBot transport is centralised here so reservation notifications and
 * manual test messages use exactly the same request logic.
 */

date_default_timezone_set('Africa/Nairobi');

require_once __DIR__ . '/config.php';

/**
 * Resolve the legacy admin-stored API key when no server-side secret exists.
 * Production secrets should normally come from includes/.env.php.
 *
 * @return string
 */
function furusato_whatsapp_legacy_api_key(): string
{
    $settingsFile = __DIR__ . '/../data/settings.json';

    if (!is_file($settingsFile) || !is_readable($settingsFile)) {
        return '';
    }

    $contents = @file_get_contents($settingsFile);

    if ($contents === false) {
        return '';
    }

    $stored = json_decode($contents, true);

    if (!is_array($stored)) {
        return '';
    }

    $wa = $stored['whatsapp'] ?? null;

    if (is_array($wa)) {
        return (string) (
            $wa['api_key'] ??
            ($stored['whatsapp_api_key'] ?? '')
        );
    }

    return (string) ($stored['whatsapp_api_key'] ?? '');
}

/**
 * Send a text message through CallMeBot.
 *
 * @param string $message
 * @return array{success:bool,http_code:int,response:string,error:string}
 */
function furusato_callmebot_send(string $message): array
{
    $phone = furusato_whatsapp_phone();
    $apiKey = furusato_whatsapp_api_key();

    if ($apiKey === '') {
        $apiKey = furusato_whatsapp_legacy_api_key();
    }

    if ($apiKey === '' || $phone === '') {
        return [
            'success' => false,
            'http_code' => 0,
            'response' => 'WhatsApp is not configured',
            'error' => ''
        ];
    }

    if ($message === '') {
        return [
            'success' => false,
            'http_code' => 0,
            'response' => 'WhatsApp message is empty',
            'error' => ''
        ];
    }

    $query = http_build_query(
        [
            'source' => 'php',
            'phone' => $phone,
            'text' => $message,
            'apikey' => $apiKey
        ],
        '',
        '&',
        PHP_QUERY_RFC3986
    );

    $url = 'https://api.callmebot.com/whatsapp.php?' . $query;

    $ch = curl_init($url);

    if ($ch === false) {
        return [
            'success' => false,
            'http_code' => 0,
            'response' => '',
            'error' => 'Unable to initialize cURL'
        ];
    }

    curl_setopt_array(
        $ch,
        [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_USERAGENT => 'Furusato-Restaurant/1.0 PHP WhatsApp Notification'
        ]
    );

    $response = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);

    curl_close($ch);

    $responseText = is_string($response) ? trim($response) : '';

   $success = (
    in_array($httpCode, [200, 210], true) &&
    $error === '' &&
    $responseText !== ''
);

    return [
        'success' => $success,
        'http_code' => $httpCode,
        'response' => $responseText,
        'error' => $error
    ];
}

function sendWhatsAppReservation($reservation, $isUpdate = false)
{
    if (!is_array($reservation)) {
        error_log('WhatsApp notification failed: reservation payload is not an array.');
        return false;
    }

    $reservationId = (string) ($reservation['id'] ?? '?');
    $name = (string) ($reservation['name'] ?? '');
    $phone = (string) ($reservation['phone'] ?? '');
    $date = (string) ($reservation['date'] ?? '');
    $time = (string) ($reservation['time'] ?? '');
    $guests = (int) ($reservation['guests'] ?? 0);
    $specialRequests = (string) ($reservation['special_requests'] ?? '');

    $submissionTime = date('h:i A');
    $submissionDate = date('l, F j, Y');

    $reservationTimestamp = strtotime($time);
    $reservationDateTimestamp = strtotime($date);

    if ($reservationTimestamp === false || $reservationDateTimestamp === false) {
        error_log(
            'WhatsApp notification failed for reservation ' .
            $reservationId .
            ': invalid reservation date/time.'
        );
        return false;
    }

    $reservationTimeFormatted = date('h:i A', $reservationTimestamp);
    $reservationDateFormatted = date('l, F j, Y', $reservationDateTimestamp);

    $message = (
        $isUpdate
            ? "RESERVATION UPDATED - Furusato\n"
            : "NEW RESERVATION - Furusato\n"
    );

    $message .= "----------------------------------------\n";
    $message .= "Guest: " . $name . "\n";
    $message .= "Phone: " . $phone . "\n";
    $message .= "----------------------------------------\n";
    $message .= "Date: " . $reservationDateFormatted . "\n";
    $message .= "Time: " . $reservationTimeFormatted . " (Nairobi Time)\n";
    $message .= "Party: " . $guests . " people\n";
    $message .= "----------------------------------------\n";

    if ($specialRequests !== '') {
        $message .= "Special Requests:\n";
        $message .= "  " . $specialRequests . "\n";
        $message .= "----------------------------------------\n";
    }

    $message .= "Reservation ID: " . $reservationId . "\n";
    $message .= "Submitted: " . $submissionTime . " on " . $submissionDate . " (Nairobi Time)\n";
    $message .= "----------------------------------------\n";
    $message .= "Venue: Ring Road Parklands, Westlands, Nairobi\n";
    $message .= "Restaurant: 0722 488 706 | 0734 639 203\n";
    $message .= "Open Daily: 12pm - 9pm";

    $result = furusato_callmebot_send($message);

    if ($result['success']) {
        error_log(
            'WhatsApp notification sent successfully for reservation: ' .
            $reservationId
        );
        return true;
    }

    $responseForLog = $result['response'];

    if (strlen($responseForLog) > 500) {
        $responseForLog = substr($responseForLog, 0, 500) . '...';
    }

    error_log(
        'WhatsApp notification failed for reservation ' .
        $reservationId .
        ': HTTP ' .
        $result['http_code'] .
        ' - cURL: ' .
        ($result['error'] !== '' ? $result['error'] : 'none') .
        ' - Response: ' .
        ($responseForLog !== '' ? $responseForLog : '[empty]')
    );

    return false;
}

/**
 * Send a simple WhatsApp test message.
 *
 * @param string $message
 * @return array{success:bool,response:string,http_code:int,error:string}
 */
function sendWhatsAppTest($message)
{
    $result = furusato_callmebot_send((string) $message);

    return [
        'success' => $result['success'],
        'response' => $result['response'],
        'http_code' => $result['http_code'],
        'error' => $result['error']
    ];
}
?>