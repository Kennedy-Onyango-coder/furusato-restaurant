```php
<?php
/**
 * includes/whatsapp.php - WhatsApp Notification System
 *
 * Sends formatted reservation details to the configured WhatsApp number.
 *
 * CallMeBot transport is centralised here so reservation notifications and
 * manual test messages use exactly the same request logic.
 *
 * Notification response handling:
 *   HTTP 200 = accepted for immediate delivery
 *   HTTP 210 = accepted and queued by CallMeBot
 *   Other    = failed
 */

date_default_timezone_set('Africa/Nairobi');

require_once __DIR__ . '/config.php';

/**
 * Resolve the legacy admin-stored API key when no server-side secret exists.
 *
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
        return trim((string) (
            $wa['api_key'] ??
            ($stored['whatsapp_api_key'] ?? '')
        ));
    }

    return trim((string) ($stored['whatsapp_api_key'] ?? ''));
}

/**
 * Clean a value for use inside a WhatsApp message.
 *
 * Removes control characters while preserving normal line breaks where
 * appropriate. This prevents unexpected formatting from user-submitted data.
 *
 * @param mixed $value
 * @param bool $preserveNewLines
 * @return string
 */
function furusato_whatsapp_clean_text($value, bool $preserveNewLines = false): string
{
    $text = trim((string) $value);

    if ($preserveNewLines) {
        $text = preg_replace("/\r\n|\r|\n/", "\n", $text) ?? $text;
        $text = preg_replace("/[ \t]+/", " ", $text) ?? $text;
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;
    } else {
        $text = preg_replace('/[\r\n\t]+/', ' ', $text) ?? $text;
        $text = preg_replace('/\s{2,}/', ' ', $text) ?? $text;
    }

    return trim($text);
}

/**
 * Send a text message through CallMeBot.
 *
 * @param string $message
 * @return array{
 *     success:bool,
 *     http_code:int,
 *     response:string,
 *     error:string
 * }
 */
function furusato_callmebot_send(string $message): array
{
    $phone = trim((string) furusato_whatsapp_phone());
    $apiKey = trim((string) furusato_whatsapp_api_key());

    /*
     * Fall back to the legacy settings.json API key only when the
     * server-side configuration does not provide one.
     */
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

    $message = trim($message);

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

    /*
     * CallMeBot uses:
     *   200 = accepted normally
     *   210 = accepted and queued
     *
     * Both indicate that the request reached CallMeBot successfully.
     */
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

/**
 * Send a reservation notification to the configured WhatsApp number.
 *
 * @param mixed $reservation
 * @param bool $isUpdate
 * @return bool
 */
function sendWhatsAppReservation($reservation, $isUpdate = false)
{
    if (!is_array($reservation)) {
        error_log(
            'WhatsApp notification failed: reservation payload is not an array.'
        );

        return false;
    }

    /*
     * Read and normalize reservation data.
     */
    $reservationId = furusato_whatsapp_clean_text(
        $reservation['id'] ?? '?'
    );

    $name = furusato_whatsapp_clean_text(
        $reservation['name'] ?? ''
    );

    $phone = furusato_whatsapp_clean_text(
        $reservation['phone'] ?? ''
    );

    $email = furusato_whatsapp_clean_text(
        $reservation['email'] ?? ''
    );

    $date = furusato_whatsapp_clean_text(
        $reservation['date'] ?? ''
    );

    $time = furusato_whatsapp_clean_text(
        $reservation['time'] ?? ''
    );

    $guests = (int) ($reservation['guests'] ?? 0);

    $specialRequests = furusato_whatsapp_clean_text(
        $reservation['special_requests'] ?? '',
        true
    );

    /*
     * Use the actual submission time generated by the server.
     */
    $submissionTime = date('h:i A');
    $submissionDate = date('l, F j, Y');

    /*
     * Validate reservation date and time before formatting.
     */
    $reservationTimestamp = strtotime($time);
    $reservationDateTimestamp = strtotime($date);

    if (
        $reservationTimestamp === false ||
        $reservationDateTimestamp === false
    ) {
        error_log(
            'WhatsApp notification failed for reservation ' .
            $reservationId .
            ': invalid reservation date/time.'
        );

        return false;
    }

    $reservationTimeFormatted = date(
        'h:i A',
        $reservationTimestamp
    );

    $reservationDateFormatted = date(
        'l, F j, Y',
        $reservationDateTimestamp
    );

    /*
     * Build a clean, professional WhatsApp notification.
     *
     * Important:
     * Do NOT use "Location:" here.
     * CallMeBot testing showed that this specific label caused HTTP 403.
     * "Venue:" is intentionally used instead.
     */
    $message = (
        $isUpdate
            ? "RESERVATION UPDATED\n"
            : "NEW RESERVATION\n"
    );

    $message .= "FURUSATO JAPANESE RESTAURANT\n";
    $message .= "================================\n";

    /*
     * Reservation reference and status.
     */
    $message .= "Reservation ID: " .
        ($reservationId !== '' ? $reservationId : '?') .
        "\n";

    $message .= "Status: PENDING\n";
    $message .= "--------------------------------\n";

    /*
     * Guest details.
     */
    $message .= "GUEST DETAILS\n";

    $message .= "Name: " .
        ($name !== '' ? $name : 'Not provided') .
        "\n";

    $message .= "Phone: " .
        ($phone !== '' ? $phone : 'Not provided') .
        "\n";

    /*
     * Only show the email address when the customer supplied one.
     */
    if ($email !== '') {
        $message .= "Email: " . $email . "\n";
    }

    $message .= "--------------------------------\n";

    /*
     * Reservation details.
     */
    $message .= "RESERVATION DETAILS\n";

    $message .= "Date: " .
        $reservationDateFormatted .
        "\n";

    $message .= "Time: " .
        $reservationTimeFormatted .
        " (Nairobi Time)\n";

    $message .= "Guests: " .
        $guests .
        "\n";

    /*
     * Special requests are optional.
     */
    if ($specialRequests !== '') {
        $message .= "--------------------------------\n";
        $message .= "SPECIAL REQUEST\n";
        $message .= $specialRequests . "\n";
    }

    /*
     * Restaurant information.
     *
     * "Venue:" is deliberately used instead of "Location:"
     * because CallMeBot previously returned HTTP 403 when the
     * literal "Location:" label was included.
     */
    $message .= "================================\n";
    $message .= "VENUE\n";
    $message .= "Ring Road Parklands, Westlands, Nairobi\n";

    $message .= "--------------------------------\n";
    $message .= "CONTACT\n";
    $message .= "0722 488 706 | 0734 639 203\n";
    $message .= "Open Daily: 12:00 PM - 9:00 PM\n";

    $message .= "--------------------------------\n";
    $message .= "SUBMITTED\n";
    $message .= $submissionTime .
        " on " .
        $submissionDate .
        " (Nairobi Time)\n";

    $message .= "================================";

    /*
     * Send through the centralised CallMeBot transport.
     */
    $result = furusato_callmebot_send($message);

    /*
     * HTTP 200:
     * CallMeBot accepted the message normally.
     */
    if (
        $result['success'] &&
        $result['http_code'] === 200
    ) {
        error_log(
            'WhatsApp notification sent successfully for reservation: ' .
            $reservationId
        );

        return true;
    }

    /*
     * HTTP 210:
     * CallMeBot accepted the message but placed it in its queue.
     *
     * This is NOT treated as an immediate delivery confirmation.
     */
    if (
        $result['success'] &&
        $result['http_code'] === 210
    ) {
        error_log(
            'WhatsApp notification queued by CallMeBot for reservation: ' .
            $reservationId
        );

        return true;
    }

    /*
     * Log genuine failures for troubleshooting.
     */
    $responseForLog = $result['response'];

    if (strlen($responseForLog) > 500) {
        $responseForLog =
            substr($responseForLog, 0, 500) . '...';
    }

    error_log(
        'WhatsApp notification failed for reservation ' .
        $reservationId .
        ': HTTP ' .
        $result['http_code'] .
        ' - cURL: ' .
        (
            $result['error'] !== ''
                ? $result['error']
                : 'none'
        ) .
        ' - Response: ' .
        (
            $responseForLog !== ''
                ? $responseForLog
                : '[empty]'
        )
    );

    return false;
}

/**
 * Send a simple WhatsApp test message.
 *
 * @param string $message
 * @return array{
 *     success:bool,
 *     response:string,
 *     http_code:int,
 *     error:string
 * }
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
```
