```php
<?php
/**
 * includes/mailer.php - Furusato Restaurant Email System
 *
 * Production email notification system.
 *
 * DELIVERY:
 *  1. Uses authenticated SMTP when SMTP_HOST / SMTP_USER / SMTP_PASS
 *     are configured server-side.
 *  2. Falls back to PHP mail() if SMTP delivery fails.
 *
 * EMAILS:
 *  - Restaurant notification email
 *  - Customer reservation acknowledgement email
 *
 * DESIGN:
 *  - Professional HTML email
 *  - No gradients
 *  - No emojis
 *  - Responsive/mobile-friendly
 *  - Plain-text fallback
 *  - Furusato branded colours
 *
 * SECURITY:
 *  - No credentials are hardcoded.
 *  - Customer email is validated before being used as Reply-To.
 *  - User-provided content is escaped before entering HTML.
 */

require_once __DIR__ . '/config.php';

/**
 * True when a full SMTP configuration is present in server-side config.
 */
function furusato_smtp_configured(): bool
{
    return furusato_config('SMTP_HOST') !== null
        && furusato_config('SMTP_USER') !== null
        && furusato_config('SMTP_PASS') !== null;
}

/**
 * Minimal dependency-free SMTP client.
 *
 * Supports:
 *  - STARTTLS
 *  - Implicit SSL
 *  - AUTH LOGIN
 *
 * @return bool
 */
function furusato_smtp_send(
    string $to,
    string $subject,
    string $headers,
    string $body
): bool {
    $sock = null;
    $host = 'unknown';
    $port = 0;
    $secure = 'unknown';

    try {
        $host = (string) furusato_config('SMTP_HOST');
        $port = (int) (furusato_config('SMTP_PORT', 587) ?: 587);
        $user = (string) furusato_config('SMTP_USER');
        $pass = (string) furusato_config('SMTP_PASS');
        $secure = strtolower(
            (string) furusato_config('SMTP_SECURE', 'tls')
        );

        $from = (string) furusato_config(
            'SMTP_FROM',
            'reservations@furusatorestaurant.com'
        );

        $remote = (
            $secure === 'ssl'
                ? 'ssl://'
                : ''
        ) . $host;

        $sock = @fsockopen(
            $remote,
            $port,
            $errno,
            $errstr,
            15
        );

        if (!$sock) {
            error_log(
                "SMTP failed at connection stage to {$host}:{$port} " .
                "(secure={$secure}): {$errstr} ({$errno})"
            );

            return false;
        }

        stream_set_timeout($sock, 25);

        $read = function () use ($sock): string {
            $data = '';

            while (($line = fgets($sock, 515)) !== false) {
                $data .= $line;

                if (
                    isset($line[3]) &&
                    $line[3] === ' '
                ) {
                    break;
                }
            }

            return $data;
        };

        $writeAll = function (string $data) use ($sock): void {
            $total = strlen($data);
            $written = 0;

            while ($written < $total) {
                $n = fwrite(
                    $sock,
                    substr($data, $written)
                );

                if ($n === false || $n === 0) {
                    throw new RuntimeException(
                        'SMTP connection lost while sending data'
                    );
                }

                $written += $n;
            }
        };

        $cmd = function (string $command) use (
            $read,
            $writeAll
        ): string {
            $writeAll($command . "\r\n");
            return $read();
        };

        $expect = function (
            int $min,
            int $max,
            string $response,
            string $stage
        ): void {
            $code = (int) substr($response, 0, 3);

            if ($code < $min || $code > $max) {
                $detail = trim($response);

                if ($detail === '') {
                    $detail =
                        'no response received ' .
                        '(connection closed or timed out)';
                }

                throw new RuntimeException(
                    "SMTP failed at {$stage} stage: {$detail}"
                );
            }
        };

        $expect(
            220,
            220,
            $read(),
            'greeting'
        );

        $ehlo = $_SERVER['SERVER_NAME']
            ?? 'furusatorestaurant.com';

        $expect(
            250,
            250,
            $cmd('EHLO ' . $ehlo),
            'EHLO'
        );

        if ($secure === 'tls') {
            if (!function_exists('stream_socket_enable_crypto')) {
                throw new RuntimeException(
                    'STARTTLS requested but OpenSSL is unavailable'
                );
            }

            $expect(
                220,
                220,
                $cmd('STARTTLS'),
                'STARTTLS'
            );

            if (
                !stream_socket_enable_crypto(
                    $sock,
                    true,
                    STREAM_CRYPTO_METHOD_TLS_CLIENT
                )
            ) {
                throw new RuntimeException(
                    'TLS negotiation failed'
                );
            }

            $expect(
                250,
                250,
                $cmd('EHLO ' . $ehlo),
                'EHLO after TLS'
            );
        }

        $expect(
            300,
            399,
            $cmd('AUTH LOGIN'),
            'AUTH LOGIN'
        );

        $expect(
            300,
            399,
            $cmd(base64_encode($user)),
            'AUTH username'
        );

        $expect(
            200,
            299,
            $cmd(base64_encode($pass)),
            'AUTH password'
        );

        $expect(
            200,
            299,
            $cmd("MAIL FROM:<{$from}>"),
            'MAIL FROM'
        );

        $expect(
            200,
            299,
            $cmd("RCPT TO:<{$to}>"),
            'RCPT TO'
        );

        $expect(
            300,
            399,
            $cmd('DATA'),
            'DATA'
        );

        /*
         * Raw SMTP DATA payload.
         *
         * The To and Subject headers are included here because this is
         * direct SMTP rather than PHP mail().
         */
        $payload =
            'To: ' . $to . "\r\n"
            . 'Subject: =?UTF-8?B?'
            . base64_encode($subject)
            . "?=\r\n"
            . $headers
            . "\r\n"
            . $body;

        /*
         * Dot-stuff leading periods according to RFC 5321.
         */
        $payload =
            preg_replace(
                '/^\./m',
                '..',
                $payload
            ) . "\r\n.";

        $expect(
            200,
            299,
            $cmd($payload),
            'final message delivery'
        );

        $cmd('QUIT');

        fclose($sock);

        return true;

    } catch (Throwable $e) {
        error_log(
            sprintf(
                'SMTP send failed (host=%s port=%d secure=%s): %s',
                $host,
                $port,
                $secure,
                $e->getMessage()
            )
        );

        if (is_resource($sock)) {
            fclose($sock);
        }

        return false;
    }
}

/**
 * Deliver a fully constructed MIME message.
 *
 * SMTP is attempted first when configured.
 * PHP mail() is used as a fallback.
 */
function furusato_deliver(
    string $to,
    string $subject,
    string $headers,
    string $message
): bool {
    $smtpConfigured = furusato_smtp_configured();

    if ($smtpConfigured) {
        if (
            furusato_smtp_send(
                $to,
                $subject,
                $headers,
                $message
            )
        ) {
            error_log(
                'Email delivered via SMTP for "' .
                $subject .
                '" to ' .
                $to .
                '.'
            );

            return true;
        }

        error_log(
            'SMTP delivery failed for "' .
            $subject .
            '" - falling back to PHP mail().'
        );
    }

    $sent = @mail(
        $to,
        $subject,
        $message,
        $headers
    );

    if (!$sent) {
        error_log(
            'Complete delivery failure for "' .
            $subject .
            '" to ' .
            $to .
            ' - ' .
            (
                $smtpConfigured
                    ? 'SMTP failed and PHP mail() also returned false.'
                    : 'PHP mail() returned false.'
            )
        );
    }

    return $sent;
}

/**
 * Safely escape text for HTML email.
 */
function furusato_email_escape($value): string
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

/**
 * Normalize a single-line value.
 */
function furusato_email_clean_line($value): string
{
    $value = trim((string) $value);

    return preg_replace(
        '/[\r\n\t]+/',
        ' ',
        $value
    ) ?? $value;
}

/**
 * Resolve special requests from both the current field name and the
 * legacy field name.
 *
 * Current reservation API uses "special_requests".
 * Older code may use "requests".
 */
function furusato_email_special_requests(array $reservationData): string
{
    $value = $reservationData['special_requests']
        ?? ($reservationData['requests'] ?? '');

    return trim((string) $value);
}

/**
 * Format a reservation date for display.
 */
function furusato_email_format_date($date): string
{
    $timestamp = strtotime((string) $date);

    if ($timestamp === false) {
        return furusato_email_clean_line($date);
    }

    return date(
        'l, F j, Y',
        $timestamp
    );
}

/**
 * Format a reservation time for display.
 */
function furusato_email_format_time($time): string
{
    $timestamp = strtotime((string) $time);

    if ($timestamp === false) {
        return furusato_email_clean_line($time);
    }

    return date(
        'h:i A',
        $timestamp
    );
}

/**
 * Build a multipart/alternative MIME message.
 */
function furusato_build_multipart_message(
    string $plainText,
    string $htmlMessage,
    string &$headers
): string {
    $boundary = '=_FURUSATO_' .
        bin2hex(random_bytes(12));

    $headers .=
        'Content-Type: multipart/alternative; boundary="'
        . $boundary
        . "\"\r\n";

    $message =
        '--' . $boundary . "\r\n"
        . "Content-Type: text/plain; charset=UTF-8\r\n"
        . "Content-Transfer-Encoding: 8bit\r\n\r\n"
        . $plainText
        . "\r\n\r\n"
        . '--' . $boundary . "\r\n"
        . "Content-Type: text/html; charset=UTF-8\r\n"
        . "Content-Transfer-Encoding: 8bit\r\n\r\n"
        . $htmlMessage
        . "\r\n\r\n"
        . '--' . $boundary . "--";

    return $message;
}

/**
 * Send reservation notification to Furusato staff.
 *
 * @param array $reservationData
 * @return bool
 */
function sendReservationEmail($reservationData)
{
    if (!is_array($reservationData)) {
        error_log(
            'Reservation email failed: invalid reservation data.'
        );

        return false;
    }

    /*
     * Restaurant notification recipient.
     */
    $to = 'furusatoreservation@gmail.com';

    /*
     * Reservation values.
     */
    $name = furusato_email_clean_line(
        $reservationData['name'] ?? ''
    );

    $email = trim(
        (string) ($reservationData['email'] ?? '')
    );

    $phone = furusato_email_clean_line(
        $reservationData['phone'] ?? ''
    );

    $date = furusato_email_clean_line(
        $reservationData['date'] ?? ''
    );

    $time = furusato_email_clean_line(
        $reservationData['time'] ?? ''
    );

    $guests = (int) (
        $reservationData['guests'] ?? 0
    );

    $reservationId = furusato_email_clean_line(
        $reservationData['id'] ?? ''
    );

    $specialRequests =
        furusato_email_special_requests(
            $reservationData
        );

    /*
     * Display formats.
     */
    $displayDate =
        furusato_email_format_date($date);

    $displayTime =
        furusato_email_format_time($time);

    /*
     * Subject.
     *
     * No emoji.
     */
    $subject =
        'New Reservation — ' .
        ($name !== '' ? $name : 'Guest') .
        ' — ' .
        ($displayDate !== '' ? $displayDate : $date);

    /*
     * Sender configuration.
     */
    $fromAddress = (string) furusato_config(
        'SMTP_FROM',
        'reservations@furusatorestaurant.com'
    );

    $fromName = (string) furusato_config(
        'SMTP_FROM_NAME',
        'Furusato Japanese Restaurant'
    );

    /*
     * Headers.
     */
    $headers =
        "MIME-Version: 1.0\r\n";

    $headers .=
        'From: ' .
        furusato_email_clean_line($fromName) .
        ' <' .
        $fromAddress .
        ">\r\n";

    /*
     * Only use the customer's email as Reply-To when it is valid.
     *
     * This avoids malformed Reply-To headers when the email field is empty.
     */
    if (
        $email !== '' &&
        filter_var($email, FILTER_VALIDATE_EMAIL)
    ) {
        $headers .=
            'Reply-To: ' .
            $email .
            "\r\n";
    } else {
        $headers .=
            "Reply-To: furusatoreservation@gmail.com\r\n";
    }

    $headers .=
        'X-Mailer: Furusato Reservation System / PHP ' .
        phpversion() .
        "\r\n";

    $headers .=
        "X-Auto-Response-Suppress: All\r\n";

    /*
     * Do not attempt to forge Return-Path through a normal mail header.
     * SMTP handles the envelope sender separately.
     */
    ini_set(
        'sendmail_from',
        'bounce@furusatorestaurant.com'
    );

    /*
     * Plain-text version.
     */
    $plainText =
        "FURUSATO JAPANESE RESTAURANT\n"
        . "NEW RESERVATION\n"
        . "================================\n\n";

    $plainText .=
        'Reservation ID: ' .
        ($reservationId !== ''
            ? $reservationId
            : 'Not provided') .
        "\n";

    $plainText .=
        "Status: PENDING\n\n";

    $plainText .=
        "GUEST DETAILS\n"
        . "--------------------------------\n";

    $plainText .=
        'Name: ' .
        ($name !== '' ? $name : 'Not provided') .
        "\n";

    $plainText .=
        'Email: ' .
        ($email !== '' ? $email : 'Not provided') .
        "\n";

    $plainText .=
        'Phone: ' .
        ($phone !== '' ? $phone : 'Not provided') .
        "\n\n";

    $plainText .=
        "RESERVATION DETAILS\n"
        . "--------------------------------\n";

    $plainText .=
        'Date: ' .
        $displayDate .
        "\n";

    $plainText .=
        'Time: ' .
        $displayTime .
        " (Nairobi Time)\n";

    $plainText .=
        'Guests: ' .
        $guests .
        "\n\n";

    $plainText .=
        "SPECIAL REQUESTS\n"
        . "--------------------------------\n";

    $plainText .=
        (
            $specialRequests !== ''
                ? $specialRequests
                : 'None'
        ) .
        "\n\n";

    $plainText .=
        "ACTION REQUIRED\n"
        . "--------------------------------\n"
        . "Please review and confirm this reservation "
        . "within 24 hours.\n\n";

    $plainText .=
        "Furusato Japanese Restaurant\n"
        . "Ring Road Parklands, Westlands, Nairobi, Kenya\n"
        . "0722 488 706 / 0734 639 203\n"
        . "furusatoreservation@gmail.com\n"
        . "Open Daily: 12:00 PM - 9:00 PM\n"
        . "https://furusatorestaurant.com";

    /*
     * HTML-safe values.
     */
    $hName =
        furusato_email_escape(
            $name !== '' ? $name : 'Not provided'
        );

    $hEmail =
        furusato_email_escape(
            $email !== '' ? $email : 'Not provided'
        );

    $hPhone =
        furusato_email_escape(
            $phone !== '' ? $phone : 'Not provided'
        );

    $hDate =
        furusato_email_escape($displayDate);

    $hTime =
        furusato_email_escape($displayTime);

    $hGuests =
        furusato_email_escape((string) $guests);

    $hReservationId =
        furusato_email_escape(
            $reservationId !== ''
                ? $reservationId
                : 'Not provided'
        );

    $hSpecialRequests =
        nl2br(
            furusato_email_escape(
                $specialRequests !== ''
                    ? $specialRequests
                    : 'No special requests'
            )
        );

    /*
     * Professional HTML email.
     *
     * No gradients.
     * No emojis.
     * Uses inline-safe CSS patterns that work well in Gmail.
     */
    $htmlMessage = '<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>New Reservation - Furusato Japanese Restaurant</title>
</head>

<body style="
    margin:0;
    padding:0;
    background:#f3f1ed;
    color:#222222;
    font-family:Arial,Helvetica,sans-serif;
">

<table role="presentation" width="100%" cellspacing="0" cellpadding="0"
       border="0" style="background:#f3f1ed;">
<tr>
<td align="center" style="padding:32px 16px;">

<table role="presentation" width="100%" cellspacing="0" cellpadding="0"
       border="0"
       style="
           max-width:640px;
           background:#ffffff;
           border:1px solid #e2ddd5;
       ">

<!-- HEADER -->
<tr>
<td style="
    background:#171717;
    padding:28px 30px;
    text-align:center;
">

    <div style="
        font-size:28px;
        line-height:34px;
        font-weight:700;
        letter-spacing:2px;
        color:#ffffff;
    ">
        FURUSATO
    </div>

    <div style="
        margin-top:6px;
        font-size:12px;
        line-height:18px;
        letter-spacing:2px;
        color:#d6d0c8;
    ">
        JAPANESE RESTAURANT
    </div>

</td>
</tr>

<!-- TITLE -->
<tr>
<td style="padding:32px 34px 18px 34px;">

    <div style="
        font-size:13px;
        line-height:18px;
        font-weight:700;
        letter-spacing:1.5px;
        color:#8a1c1c;
        text-transform:uppercase;
    ">
        Reservation Notification
    </div>

    <h1 style="
        margin:8px 0 0 0;
        font-size:28px;
        line-height:36px;
        font-weight:600;
        color:#171717;
    ">
        New Reservation Received
    </h1>

    <p style="
        margin:10px 0 0 0;
        font-size:15px;
        line-height:24px;
        color:#666666;
    ">
        A new reservation request has been submitted through the Furusato website.
    </p>

</td>
</tr>

<!-- STATUS -->
<tr>
<td style="padding:0 34px 22px 34px;">

    <table role="presentation" cellspacing="0" cellpadding="0" border="0">
    <tr>
    <td style="
        background:#f5e9e7;
        border:1px solid #d8b8b3;
        padding:7px 13px;
        font-size:12px;
        line-height:16px;
        font-weight:700;
        letter-spacing:.7px;
        color:#7b1717;
    ">
        PENDING CONFIRMATION
    </td>
    </tr>
    </table>

</td>
</tr>

<!-- RESERVATION ID -->
<tr>
<td style="padding:0 34px 24px 34px;">

<table role="presentation" width="100%" cellspacing="0" cellpadding="0"
       border="0"
       style="background:#f8f7f4;border:1px solid #e4e0d9;">
<tr>
<td style="
    padding:15px 18px;
    font-size:12px;
    color:#777777;
    letter-spacing:.5px;
">
    RESERVATION ID
</td>
<td align="right" style="
    padding:15px 18px;
    font-size:14px;
    font-weight:700;
    color:#171717;
">
    ' . $hReservationId . '
</td>
</tr>
</table>

</td>
</tr>

<!-- GUEST DETAILS -->
<tr>
<td style="padding:0 34px 10px 34px;">

    <h2 style="
        margin:0;
        font-size:16px;
        line-height:24px;
        color:#171717;
        font-weight:700;
        letter-spacing:.5px;
    ">
        Guest Details
    </h2>

</td>
</tr>

<tr>
<td style="padding:0 34px 26px 34px;">

<table role="presentation" width="100%" cellspacing="0" cellpadding="0"
       border="0"
       style="border-top:1px solid #e6e2dc;">

<tr>
<td style="
    padding:13px 0;
    border-bottom:1px solid #e6e2dc;
    width:35%;
    font-size:13px;
    color:#777777;
">
    Name
</td>
<td style="
    padding:13px 0;
    border-bottom:1px solid #e6e2dc;
    font-size:14px;
    color:#222222;
    font-weight:600;
">
    ' . $hName . '
</td>
</tr>

<tr>
<td style="
    padding:13px 0;
    border-bottom:1px solid #e6e2dc;
    font-size:13px;
    color:#777777;
">
    Email
</td>
<td style="
    padding:13px 0;
    border-bottom:1px solid #e6e2dc;
    font-size:14px;
    color:#222222;
">
    ' . $hEmail . '
</td>
</tr>

<tr>
<td style="
    padding:13px 0;
    border-bottom:1px solid #e6e2dc;
    font-size:13px;
    color:#777777;
">
    Phone
</td>
<td style="
    padding:13px 0;
    border-bottom:1px solid #e6e2dc;
    font-size:14px;
    color:#222222;
">
    ' . $hPhone . '
</td>
</tr>

</table>

</td>
</tr>

<!-- RESERVATION DETAILS -->
<tr>
<td style="padding:0 34px 10px 34px;">

    <h2 style="
        margin:0;
        font-size:16px;
        line-height:24px;
        color:#171717;
        font-weight:700;
        letter-spacing:.5px;
    ">
        Reservation Details
    </h2>

</td>
</tr>

<tr>
<td style="padding:0 34px 26px 34px;">

<table role="presentation" width="100%" cellspacing="0" cellpadding="0"
       border="0"
       style="border-top:1px solid #e6e2dc;">

<tr>
<td style="
    padding:13px 0;
    border-bottom:1px solid #e6e2dc;
    width:35%;
    font-size:13px;
    color:#777777;
">
    Date
</td>
<td style="
    padding:13px 0;
    border-bottom:1px solid #e6e2dc;
    font-size:14px;
    color:#222222;
    font-weight:600;
">
    ' . $hDate . '
</td>
</tr>

<tr>
<td style="
    padding:13px 0;
    border-bottom:1px solid #e6e2dc;
    font-size:13px;
    color:#777777;
">
    Time
</td>
<td style="
    padding:13px 0;
    border-bottom:1px solid #e6e2dc;
    font-size:14px;
    color:#222222;
    font-weight:600;
">
    ' . $hTime . ' (Nairobi Time)
</td>
</tr>

<tr>
<td style="
    padding:13px 0;
    border-bottom:1px solid #e6e2dc;
    font-size:13px;
    color:#777777;
">
    Guests
</td>
<td style="
    padding:13px 0;
    border-bottom:1px solid #e6e2dc;
    font-size:14px;
    color:#222222;
    font-weight:600;
">
    ' . $hGuests . ' people
</td>
</tr>

</table>

</td>
</tr>

<!-- SPECIAL REQUESTS -->
<tr>
<td style="padding:0 34px 26px 34px;">

<table role="presentation" width="100%" cellspacing="0" cellpadding="0"
       border="0"
       style="
           background:#faf8f4;
           border-left:4px solid #8a1c1c;
       ">

<tr>
<td style="padding:18px 20px;">

    <div style="
        font-size:12px;
        line-height:18px;
        font-weight:700;
        letter-spacing:1px;
        color:#8a1c1c;
        text-transform:uppercase;
    ">
        Special Requests
    </div>

    <div style="
        margin-top:8px;
        font-size:14px;
        line-height:22px;
        color:#333333;
    ">
        ' . $hSpecialRequests . '
    </div>

</td>
</tr>

</table>

</td>
</tr>

<!-- ACTION -->
<tr>
<td style="padding:0 34px 30px 34px;">

<table role="presentation" width="100%" cellspacing="0" cellpadding="0"
       border="0"
       style="background:#f5e9e7;border:1px solid #e0c7c2;">

<tr>
<td style="padding:18px 20px;">

    <div style="
        font-size:13px;
        line-height:20px;
        font-weight:700;
        color:#7b1717;
    ">
        Action Required
    </div>

    <div style="
        margin-top:5px;
        font-size:14px;
        line-height:22px;
        color:#333333;
    ">
        Please review this reservation and contact the customer
        within 24 hours to confirm the booking.
    </div>

</td>
</tr>

</table>

</td>
</tr>

<!-- FOOTER -->
<tr>
<td style="
    background:#171717;
    padding:24px 30px;
    text-align:center;
">

    <div style="
        font-size:14px;
        line-height:22px;
        font-weight:700;
        color:#ffffff;
    ">
        Furusato Japanese Restaurant
    </div>

    <div style="
        margin-top:7px;
        font-size:12px;
        line-height:20px;
        color:#c9c5bf;
    ">
        Ring Road Parklands, Westlands, Nairobi, Kenya
    </div>

    <div style="
        margin-top:4px;
        font-size:12px;
        line-height:20px;
        color:#c9c5bf;
    ">
        0722 488 706 / 0734 639 203
    </div>

    <div style="
        margin-top:4px;
        font-size:12px;
        line-height:20px;
        color:#c9c5bf;
    ">
        furusatoreservation@gmail.com
    </div>

    <div style="
        margin-top:4px;
        font-size:12px;
        line-height:20px;
        color:#c9c5bf;
    ">
        Open Daily: 12:00 PM - 9:00 PM
    </div>

    <div style="
        margin-top:16px;
        padding-top:14px;
        border-top:1px solid #3a3a3a;
        font-size:10px;
        line-height:16px;
        color:#888888;
    ">
        Automated reservation notification from the Furusato website.
    </div>

</td>
</tr>

</table>

</td>
</tr>
</table>

</body>
</html>';

    /*
     * Build multipart email.
     */
    $message = furusato_build_multipart_message(
        $plainText,
        $htmlMessage,
        $headers
    );

    /*
     * Send restaurant notification.
     */
    $mailSent = furusato_deliver(
        $to,
        $subject,
        $headers,
        $message
    );

    /*
     * Log the result.
     */
    $logFile =
        __DIR__ . '/../logs/mail.log';

    $logDir = dirname($logFile);

    if (!is_dir($logDir)) {
        @mkdir(
            $logDir,
            0755,
            true
        );
    }

    $logEntry =
        date('Y-m-d H:i:s') .
        ' | ' .
        ($mailSent ? 'SUCCESS' : 'FAILED') .
        ' | To: ' .
        $to .
        ' | Customer: ' .
        furusato_email_clean_line($email) .
        ' | Reservation: ' .
        furusato_email_clean_line($reservationId) .
        ' | Date: ' .
        furusato_email_clean_line($date) .
        ' | IP: ' .
        ($_SERVER['REMOTE_ADDR'] ?? 'unknown') .
        "\n";

    @file_put_contents(
        $logFile,
        $logEntry,
        FILE_APPEND | LOCK_EX
    );

    /*
     * Send acknowledgement to customer only after the restaurant
     * notification has been successfully handed to the mail system.
     */
    if ($mailSent) {
        sendCustomerConfirmation(
            $reservationData
        );
    }

    /*
     * Small delay to reduce the possibility of SMTP rate limiting
     * when the two messages are sent consecutively.
     */
    usleep(500000);

    return $mailSent;
}

/**
 * Send reservation acknowledgement to the customer.
 *
 * @param array $reservationData
 * @return bool
 */
function sendCustomerConfirmation($reservationData)
{
    if (!is_array($reservationData)) {
        return false;
    }

    $to = trim(
        (string) ($reservationData['email'] ?? '')
    );

    /*
     * Email is optional from the notification perspective.
     * Never attempt to send to an invalid address.
     */
    if (
        $to === '' ||
        !filter_var($to, FILTER_VALIDATE_EMAIL)
    ) {
        error_log(
            'Customer confirmation skipped: invalid recipient email.'
        );

        return false;
    }

    $name = furusato_email_clean_line(
        $reservationData['name'] ?? ''
    );

    $reservationId = furusato_email_clean_line(
        $reservationData['id'] ?? ''
    );

    $date = furusato_email_clean_line(
        $reservationData['date'] ?? ''
    );

    $time = furusato_email_clean_line(
        $reservationData['time'] ?? ''
    );

    $guests = (int) (
        $reservationData['guests'] ?? 0
    );

    $specialRequests =
        furusato_email_special_requests(
            $reservationData
        );

    $displayDate =
        furusato_email_format_date($date);

    $displayTime =
        furusato_email_format_time($time);

    $subject =
        'Reservation Received — Furusato Japanese Restaurant';

    /*
     * Sender configuration.
     */
    $fromAddress = (string) furusato_config(
        'SMTP_FROM',
        'reservations@furusatorestaurant.com'
    );

    $fromName = (string) furusato_config(
        'SMTP_FROM_NAME',
        'Furusato Japanese Restaurant'
    );

    /*
     * Headers.
     */
    $headers =
        "MIME-Version: 1.0\r\n";

    $headers .=
        'From: ' .
        furusato_email_clean_line($fromName) .
        ' <' .
        $fromAddress .
        ">\r\n";

    $headers .=
        "Reply-To: furusatoreservation@gmail.com\r\n";

    $headers .=
        'X-Mailer: Furusato Reservation System / PHP ' .
        phpversion() .
        "\r\n";

    /*
     * Plain-text customer email.
     */
    $plainText =
        "FURUSATO JAPANESE RESTAURANT\n"
        . "RESERVATION RECEIVED\n"
        . "================================\n\n";

    $plainText .=
        'Dear ' .
        ($name !== '' ? $name : 'Guest') .
        ",\n\n";

    $plainText .=
        "Thank you for choosing Furusato Japanese Restaurant.\n\n";

    $plainText .=
        "We have received your reservation request. "
        . "Your reservation is currently pending confirmation.\n\n";

    $plainText .=
        "RESERVATION DETAILS\n"
        . "--------------------------------\n";

    $plainText .=
        'Reservation ID: ' .
        ($reservationId !== ''
            ? $reservationId
            : 'Not provided') .
        "\n";

    $plainText .=
        'Date: ' .
        $displayDate .
        "\n";

    $plainText .=
        'Time: ' .
        $displayTime .
        " (Nairobi Time)\n";

    $plainText .=
        'Guests: ' .
        $guests .
        "\n";

    if ($specialRequests !== '') {
        $plainText .=
            'Special Requests: ' .
            $specialRequests .
            "\n";
    }

    $plainText .=
        "\nWHAT HAPPENS NEXT\n"
        . "--------------------------------\n"
        . "Our team will review your request and "
        . "confirm your table within 24 hours.\n\n";

    $plainText .=
        "If we need any additional information, "
        . "a member of our team may contact you.\n\n";

    $plainText .=
        "Furusato Japanese Restaurant\n"
        . "Ring Road Parklands, Westlands, Nairobi, Kenya\n"
        . "0722 488 706 / 0734 639 203\n"
        . "furusatoreservation@gmail.com\n"
        . "Open Daily: 12:00 PM - 9:00 PM\n"
        . "https://furusatorestaurant.com";

    /*
     * HTML-safe values.
     */
    $hName =
        furusato_email_escape(
            $name !== '' ? $name : 'Guest'
        );

    $hReservationId =
        furusato_email_escape(
            $reservationId !== ''
                ? $reservationId
                : 'Not provided'
        );

    $hDate =
        furusato_email_escape($displayDate);

    $hTime =
        furusato_email_escape($displayTime);

    $hGuests =
        furusato_email_escape((string) $guests);

    $hSpecialRequests =
        nl2br(
            furusato_email_escape(
                $specialRequests !== ''
                    ? $specialRequests
                    : 'None'
            )
        );

    /*
     * Customer HTML email.
     */
    $htmlMessage = '<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Reservation Received - Furusato Japanese Restaurant</title>
</head>

<body style="
    margin:0;
    padding:0;
    background:#f3f1ed;
    color:#222222;
    font-family:Arial,Helvetica,sans-serif;
">

<table role="presentation" width="100%" cellspacing="0" cellpadding="0"
       border="0" style="background:#f3f1ed;">
<tr>
<td align="center" style="padding:32px 16px;">

<table role="presentation" width="100%" cellspacing="0" cellpadding="0"
       border="0"
       style="
           max-width:640px;
           background:#ffffff;
           border:1px solid #e2ddd5;
       ">

<!-- HEADER -->
<tr>
<td style="
    background:#171717;
    padding:30px;
    text-align:center;
">

    <div style="
        font-size:28px;
        line-height:34px;
        font-weight:700;
        letter-spacing:2px;
        color:#ffffff;
    ">
        FURUSATO
    </div>

    <div style="
        margin-top:6px;
        font-size:12px;
        line-height:18px;
        letter-spacing:2px;
        color:#d6d0c8;
    ">
        JAPANESE RESTAURANT
    </div>

</td>
</tr>

<!-- CONTENT -->
<tr>
<td style="padding:34px;">

    <div style="
        font-size:13px;
        line-height:18px;
        font-weight:700;
        letter-spacing:1.5px;
        color:#8a1c1c;
        text-transform:uppercase;
    ">
        Reservation Request
    </div>

    <h1 style="
        margin:8px 0 0 0;
        font-size:28px;
        line-height:36px;
        font-weight:600;
        color:#171717;
    ">
        Thank You, ' . $hName . '
    </h1>

    <p style="
        margin:14px 0 0 0;
        font-size:15px;
        line-height:25px;
        color:#555555;
    ">
        We have received your reservation request.
        Your table is currently pending confirmation.
    </p>

    <!-- STATUS -->
    <table role="presentation" cellspacing="0" cellpadding="0" border="0"
           style="margin-top:22px;">
    <tr>
    <td style="
        background:#f5e9e7;
        border:1px solid #d8b8b3;
        padding:8px 14px;
        font-size:12px;
        line-height:16px;
        font-weight:700;
        letter-spacing:.7px;
        color:#7b1717;
    ">
        PENDING CONFIRMATION
    </td>
    </tr>
    </table>

    <!-- RESERVATION CARD -->
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0"
           border="0"
           style="
               margin-top:28px;
               border:1px solid #e2ddd5;
               background:#faf9f7;
           ">

    <tr>
    <td colspan="2" style="
        padding:18px 20px;
        border-bottom:1px solid #e2ddd5;
        font-size:15px;
        font-weight:700;
        color:#171717;
    ">
        Reservation Details
    </td>
    </tr>

    <tr>
    <td style="
        padding:13px 20px;
        width:38%;
        font-size:13px;
        color:#777777;
        border-bottom:1px solid #e8e4de;
    ">
        Reservation ID
    </td>
    <td style="
        padding:13px 20px;
        font-size:14px;
        font-weight:700;
        color:#222222;
        border-bottom:1px solid #e8e4de;
    ">
        ' . $hReservationId . '
    </td>
    </tr>

    <tr>
    <td style="
        padding:13px 20px;
        font-size:13px;
        color:#777777;
        border-bottom:1px solid #e8e4de;
    ">
        Date
    </td>
    <td style="
        padding:13px 20px;
        font-size:14px;
        font-weight:600;
        color:#222222;
        border-bottom:1px solid #e8e4de;
    ">
        ' . $hDate . '
    </td>
    </tr>

    <tr>
    <td style="
        padding:13px 20px;
        font-size:13px;
        color:#777777;
        border-bottom:1px solid #e8e4de;
    ">
        Time
    </td>
    <td style="
        padding:13px 20px;
        font-size:14px;
        font-weight:600;
        color:#222222;
        border-bottom:1px solid #e8e4de;
    ">
        ' . $hTime . ' (Nairobi Time)
    </td>
    </tr>

    <tr>
    <td style="
        padding:13px 20px;
        font-size:13px;
        color:#777777;
    ">
        Guests
    </td>
    <td style="
        padding:13px 20px;
        font-size:14px;
        font-weight:600;
        color:#222222;
    ">
        ' . $hGuests . ' people
    </td>
    </tr>

    </table>

    <!-- NEXT STEPS -->
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0"
           border="0"
           style="
               margin-top:24px;
               background:#faf8f4;
               border-left:4px solid #8a1c1c;
           ">
    <tr>
    <td style="padding:18px 20px;">

        <div style="
            font-size:13px;
            line-height:20px;
            font-weight:700;
            color:#7b1717;
        ">
            What Happens Next
        </div>

        <div style="
            margin-top:7px;
            font-size:14px;
            line-height:23px;
            color:#444444;
        ">
            Our team will review your request and confirm
            your table within 24 hours. If we need any
            additional information, a member of our team
            may contact you.
        </div>

    </td>
    </tr>
    </table>

    <!-- SPECIAL REQUEST -->
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0"
           border="0"
           style="
               margin-top:24px;
               border-top:1px solid #e2ddd5;
           ">
    <tr>
    <td style="padding:20px 0 0 0;">

        <div style="
            font-size:12px;
            line-height:18px;
            font-weight:700;
            letter-spacing:1px;
            color:#8a1c1c;
            text-transform:uppercase;
        ">
            Special Requests
        </div>

        <div style="
            margin-top:7px;
            font-size:14px;
            line-height:22px;
            color:#555555;
        ">
            ' . $hSpecialRequests . '
        </div>

    </td>
    </tr>
    </table>

</td>
</tr>

<!-- FOOTER -->
<tr>
<td style="
    background:#171717;
    padding:25px 30px;
    text-align:center;
">

    <div style="
        font-size:14px;
        line-height:22px;
        font-weight:700;
        color:#ffffff;
    ">
        Furusato Japanese Restaurant
    </div>

    <div style="
        margin-top:7px;
        font-size:12px;
        line-height:20px;
        color:#c9c5bf;
    ">
        Ring Road Parklands, Westlands, Nairobi, Kenya
    </div>

    <div style="
        margin-top:4px;
        font-size:12px;
        line-height:20px;
        color:#c9c5bf;
    ">
        0722 488 706 / 0734 639 203
    </div>

    <div style="
        margin-top:4px;
        font-size:12px;
        line-height:20px;
        color:#c9c5bf;
    ">
        furusatoreservation@gmail.com
    </div>

    <div style="
        margin-top:4px;
        font-size:12px;
        line-height:20px;
        color:#c9c5bf;
    ">
        Open Daily: 12:00 PM - 9:00 PM
    </div>

    <div style="
        margin-top:16px;
        padding-top:14px;
        border-top:1px solid #3a3a3a;
        font-size:10px;
        line-height:16px;
        color:#888888;
    ">
        Thank you for choosing Furusato.
    </div>

</td>
</tr>

</table>

</td>
</tr>
</table>

</body>
</html>';

    $message = furusato_build_multipart_message(
        $plainText,
        $htmlMessage,
        $headers
    );

    return furusato_deliver(
        $to,
        $subject,
        $headers,
        $message
    );
}

/**
 * Helper function to test email configuration.
 *
 * This function is retained for administration/diagnostics.
 */
function sendTestEmail(
    $testEmail = 'furusatoreservation@gmail.com'
) {
    $testEmail = trim((string) $testEmail);

    if (
        !filter_var(
            $testEmail,
            FILTER_VALIDATE_EMAIL
        )
    ) {
        return false;
    }

    $subject =
        'Furusato Email System Test — ' .
        date('Y-m-d H:i:s');

    $headers =
        "MIME-Version: 1.0\r\n";

    $headers .=
        "From: reservations@furusatorestaurant.com\r\n";

    $headers .=
        "Reply-To: furusatoreservation@gmail.com\r\n";

    $headers .=
        'X-Mailer: Furusato Reservation System / PHP ' .
        phpversion() .
        "\r\n";

    $plainText =
        "FURUSATO JAPANESE RESTAURANT\n"
        . "EMAIL SYSTEM TEST\n"
        . "================================\n\n"
        . "The Furusato email system is working correctly.\n\n"
        . "Time: " .
        date('Y-m-d H:i:s T') .
        "\n"
        . "Server: " .
        ($_SERVER['SERVER_NAME']
            ?? 'unknown') .
        "\n"
        . "PHP Version: " .
        phpversion() .
        "\n";

    $htmlMessage = '<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Furusato Email System Test</title>
</head>

<body style="
    margin:0;
    padding:30px 15px;
    background:#f3f1ed;
    font-family:Arial,Helvetica,sans-serif;
    color:#222222;
">

<table role="presentation" width="100%" cellspacing="0" cellpadding="0"
       border="0">
<tr>
<td align="center">

<table role="presentation" width="100%" cellspacing="0" cellpadding="0"
       border="0"
       style="
           max-width:560px;
           background:#ffffff;
           border:1px solid #e2ddd5;
       ">

<tr>
<td style="
    background:#171717;
    padding:28px;
    text-align:center;
    color:#ffffff;
">

<div style="
    font-size:26px;
    line-height:34px;
    font-weight:700;
    letter-spacing:2px;
">
    FURUSATO
</div>

<div style="
    margin-top:5px;
    font-size:11px;
    letter-spacing:2px;
    color:#d6d0c8;
">
    JAPANESE RESTAURANT
</div>

</td>
</tr>

<tr>
<td style="padding:32px;">

<div style="
    font-size:12px;
    font-weight:700;
    letter-spacing:1px;
    color:#8a1c1c;
    text-transform:uppercase;
">
    System Test
</div>

<h1 style="
    margin:8px 0 14px 0;
    font-size:26px;
    line-height:34px;
    color:#171717;
">
    Email Delivery Successful
</h1>

<p style="
    font-size:14px;
    line-height:23px;
    color:#555555;
">
    The Furusato restaurant email system successfully
    delivered this test message.
</p>

<div style="
    margin-top:22px;
    padding:16px;
    background:#faf8f4;
    border-left:4px solid #8a1c1c;
    font-size:13px;
    line-height:22px;
    color:#444444;
">
    <strong>Time:</strong> ' . furusato_email_escape(
        date('Y-m-d H:i:s T')
    ) . '<br>
    <strong>Server:</strong> ' . furusato_email_escape(
        $_SERVER['SERVER_NAME'] ?? 'unknown'
    ) . '<br>
    <strong>PHP:</strong> ' . furusato_email_escape(
        phpversion()
    ) . '
</div>

</td>
</tr>

<tr>
<td style="
    background:#171717;
    padding:20px;
    text-align:center;
    font-size:11px;
    line-height:18px;
    color:#aaa49c;
">
    Furusato Japanese Restaurant · Nairobi
</td>
</tr>

</table>

</td>
</tr>
</table>

</body>
</html>';

    $message = furusato_build_multipart_message(
        $plainText,
        $htmlMessage,
        $headers
    );

    return furusato_deliver(
        $testEmail,
        $subject,
        $headers,
        $message
    );
}
?>
```
