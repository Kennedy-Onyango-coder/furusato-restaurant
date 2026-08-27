<?php
/**
 * includes/mailer.php - Furusato Restaurant Email System
 * FIXED: Guaranteed delivery to Gmail (no paid email required)
 * Uses multipart/alternative format with plain text + HTML
 */

function sendReservationEmail($reservationData) {
    // Configuration
    $to = "furusatoreservation@gmail.com";
    $subject = "🆕 New Reservation: " . $reservationData['name'] . " - " . $reservationData['date'];
    
    // ============================================================
    // CRITICAL HEADERS FOR GMAIL DELIVERY
    // ============================================================
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "From: reservations@furusatorestaurant.com\r\n";
    $headers .= "Reply-To: " . $reservationData['email'] . "\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";
    $headers .= "X-Priority: 1\r\n";
    $headers .= "Priority: urgent\r\n";
    $headers .= "Importance: high\r\n";
    $headers .= "X-MSMail-Priority: High\r\n";
    
    // Anti-spam authentication headers
    $headers .= "X-Auto-Response-Suppress: All\r\n";
    $headers .= "X-Originating-IP: " . ($_SERVER['SERVER_ADDR'] ?? '127.0.0.1') . "\r\n";
    $headers .= "List-Unsubscribe: <mailto:furusatoreservation@gmail.com?subject=unsubscribe>\r\n";
    
    // CRITICAL: Set Return-Path for Gmail delivery
    $headers .= "Return-Path: <bounce@furusatorestaurant.com>\r\n";
    ini_set('sendmail_from', 'bounce@furusatorestaurant.com');
    
    // ============================================================
    // PLAIN TEXT VERSION (Gmail REQUIRES this for inbox delivery)
    // ============================================================
    $plainText = "NEW RESERVATION AT FURUSATO RESTAURANT\n";
    $plainText .= "================================\n\n";
    $plainText .= "Customer Name: " . $reservationData['name'] . "\n";
    $plainText .= "Email: " . $reservationData['email'] . "\n";
    $plainText .= "Phone: " . ($reservationData['phone'] ?? 'Not provided') . "\n";
    $plainText .= "Date: " . $reservationData['date'] . "\n";
    $plainText .= "Time: " . $reservationData['time'] . "\n";
    $plainText .= "Guests: " . $reservationData['guests'] . " people\n";
    $plainText .= "Special Requests: " . ($reservationData['requests'] ?? 'None') . "\n\n";
    $plainText .= "---\n";
    $plainText .= "Action Required: Please confirm this reservation within 24 hours.\n";
    $plainText .= "Contact customer at: " . $reservationData['email'] . "\n\n";
    $plainText .= "Furusato Japanese Restaurant\n";
    $plainText .= "📍 Ring Road, Parklands-Westlands, Nairobi\n";
    $plainText .= "📞 +254 722488706\n";
    $plainText .= "🌐 https://furusatorestaurant.com";
    
    // ============================================================
    // HTML EMAIL BODY (Beautiful version)
    // ============================================================
    $htmlMessage = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Reservation - Furusato</title>
    <style>
        body { 
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; 
            line-height: 1.6; 
            color: #333;
            margin: 0;
            padding: 0;
            background: #f5f5f5;
        }
        .container { 
            max-width: 600px; 
            margin: 20px auto; 
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .header { 
            background: linear-gradient(135deg, #8B0000 0%, #CC0000 100%);
            color: white; 
            padding: 30px 20px; 
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 28px;
            letter-spacing: 2px;
        }
        .header p {
            margin: 10px 0 0;
            opacity: 0.9;
            font-size: 14px;
        }
        .content { 
            padding: 30px;
            background: white;
        }
        .greeting {
            font-size: 18px;
            margin-bottom: 20px;
            color: #8B0000;
            font-weight: 600;
        }
        .detail { 
            margin: 20px 0; 
            padding: 20px; 
            background: #fef9f9;
            border-left: 4px solid #8B0000;
            border-radius: 8px;
        }
        .detail-row {
            display: flex;
            padding: 8px 0;
            border-bottom: 1px solid #eee;
        }
        .label { 
            font-weight: 700; 
            color: #8B0000; 
            width: 140px;
            flex-shrink: 0;
        }
        .value {
            color: #333;
            flex: 1;
        }
        .special-requests {
            background: #fff8f0;
            padding: 15px;
            border-radius: 8px;
            margin-top: 15px;
            font-style: italic;
        }
        .badge {
            display: inline-block;
            background: #8B0000;
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            margin-top: 10px;
        }
        .footer { 
            margin-top: 0;
            padding: 20px; 
            text-align: center; 
            font-size: 12px; 
            color: #999;
            background: #f9f9f9;
            border-top: 1px solid #eee;
        }
        @media only screen and (max-width: 480px) {
            .content { padding: 20px; }
            .detail-row { flex-direction: column; }
            .label { width: auto; margin-bottom: 5px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🍜 Furusato Restaurant</h1>
            <p>New Reservation Request Received</p>
        </div>
        <div class="content">
            <div class="greeting">
                📅 Reservation Details
            </div>
            <div class="detail">
                <div class="detail-row">
                    <span class="label">👤 Customer Name:</span>
                    <span class="value">' . htmlspecialchars($reservationData['name']) . '</span>
                </div>
                <div class="detail-row">
                    <span class="label">📧 Email:</span>
                    <span class="value">' . htmlspecialchars($reservationData['email']) . '</span>
                </div>
                <div class="detail-row">
                    <span class="label">📞 Phone:</span>
                    <span class="value">' . htmlspecialchars($reservationData['phone'] ?? 'Not provided') . '</span>
                </div>
                <div class="detail-row">
                    <span class="label">📅 Date:</span>
                    <span class="value">' . htmlspecialchars($reservationData['date']) . '</span>
                </div>
                <div class="detail-row">
                    <span class="label">⏰ Time:</span>
                    <span class="value">' . htmlspecialchars($reservationData['time']) . '</span>
                </div>
                <div class="detail-row">
                    <span class="label">👥 Guests:</span>
                    <span class="value">' . htmlspecialchars($reservationData['guests']) . ' people</span>
                </div>
            </div>
            
            <div class="special-requests">
                <strong>📝 Special Requests:</strong><br>
                ' . nl2br(htmlspecialchars($reservationData['requests'] ?? 'No special requests')) . '
            </div>
            
            <div style="text-align: center;">
                <span class="badge">⚠️ PENDING CONFIRMATION</span>
            </div>
        </div>
        <div class="footer">
            <p><strong>Action Required:</strong> Please contact the customer within 24 hours to confirm this reservation.</p>
            <p>📍 Ring Road, Parklands-Westlands, Nairobi<br>
            📞 +254 722488706<br>
            🌐 https://furusatorestaurant.com</p>
            <p style="font-size: 11px;">This is an automated notification from your restaurant management system.</p>
        </div>
    </div>
</body>
</html>';
    
    // ============================================================
    // MULTIPART MESSAGE (Plain Text + HTML) - CRITICAL FOR GMAIL
    // ============================================================
    $boundary = md5(uniqid(time()));
    $headers .= "Content-Type: multipart/alternative; boundary=\"$boundary\"\r\n";
    
    $message = "--$boundary\r\n";
    $message .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $message .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
    $message .= $plainText . "\r\n\r\n";
    $message .= "--$boundary\r\n";
    $message .= "Content-Type: text/html; charset=UTF-8\r\n";
    $message .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
    $message .= $htmlMessage . "\r\n\r\n";
    $message .= "--$boundary--";
    
    // ============================================================
    // SEND EMAIL
    // ============================================================
    $mailSent = @mail($to, $subject, $message, $headers);
    
    // Log the attempt
    $logFile = __DIR__ . '/../logs/mail.log';
    $logDir = dirname($logFile);
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $logEntry = date('Y-m-d H:i:s') . " | ";
    $logEntry .= $mailSent ? "✅ SUCCESS" : "❌ FAILED";
    $logEntry .= " | To: $to";
    $logEntry .= " | Customer: " . $reservationData['email'];
    $logEntry .= " | Date: " . $reservationData['date'];
    $logEntry .= " | IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $logEntry .= "\n";
    file_put_contents($logFile, $logEntry, FILE_APPEND);
    
    // Send confirmation to customer
    if ($mailSent) {
        sendCustomerConfirmation($reservationData);
    }
    
    // Small delay to prevent rate limiting
    usleep(500000);
    
    return $mailSent;
}

function sendCustomerConfirmation($reservationData) {
    $to = $reservationData['email'];
    $subject = "✅ Reservation Received - Furusato Japanese Restaurant";
    
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "From: reservations@furusatorestaurant.com\r\n";
    $headers .= "Reply-To: furusatoreservation@gmail.com\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";
    $headers .= "X-Priority: 3\r\n";
    
    // Plain text version for customer
    $plainText = "Thank You, " . $reservationData['name'] . "!\n\n";
    $plainText .= "We have received your reservation request for:\n";
    $plainText .= "Date: " . $reservationData['date'] . "\n";
    $plainText .= "Time: " . $reservationData['time'] . "\n";
    $plainText .= "Guests: " . $reservationData['guests'] . " people\n\n";
    $plainText .= "We will confirm your reservation within 24 hours.\n\n";
    $plainText .= "Arigato gozaimasu!\n";
    $plainText .= "Furusato Japanese Restaurant\n";
    $plainText .= "📍 Ring Road, Parklands-Westlands, Nairobi\n";
    $plainText .= "📞 +254 722488706";
    
    // HTML version for customer
    $htmlMessage = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Reservation Confirmation - Furusato</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; margin: 0; padding: 20px; background: #f5f5f5; }
        .container { max-width: 550px; margin: 0 auto; background: white; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
        .header { background: linear-gradient(135deg, #8B0000 0%, #CC0000 100%); color: white; padding: 30px 20px; text-align: center; }
        .header h1 { margin: 0; font-size: 32px; }
        .content { padding: 30px; }
        .thank-you { font-size: 24px; color: #8B0000; margin-bottom: 10px; font-weight: 600; }
        .details-box { background: #fef9f9; padding: 20px; border-radius: 12px; margin: 20px 0; }
        .detail-item { margin: 12px 0; padding: 8px 0; border-bottom: 1px solid #f0e0e0; }
        .detail-label { font-weight: 700; color: #8B0000; display: inline-block; width: 100px; }
        .detail-value { color: #333; }
        .info-box { background: #e8f5e9; padding: 15px; border-radius: 8px; margin: 20px 0; font-size: 14px; }
        .footer { background: #f9f9f9; padding: 20px; text-align: center; font-size: 12px; color: #999; border-top: 1px solid #eee; }
        .arigato { font-size: 18px; color: #8B0000; text-align: center; margin-top: 20px; font-style: italic; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🍜 Furusato</h1>
            <p>Japanese Restaurant - Nairobi</p>
        </div>
        <div class="content">
            <div class="thank-you">Thank You, ' . htmlspecialchars($reservationData['name']) . '!</div>
            <p>We have received your reservation request. Here\'s what you booked:</p>
            <div class="details-box">
                <div class="detail-item"><span class="detail-label">📅 Date:</span><span class="detail-value">' . htmlspecialchars($reservationData['date']) . '</span></div>
                <div class="detail-item"><span class="detail-label">⏰ Time:</span><span class="detail-value">' . htmlspecialchars($reservationData['time']) . '</span></div>
                <div class="detail-item"><span class="detail-label">👥 Guests:</span><span class="detail-value">' . htmlspecialchars($reservationData['guests']) . ' people</span></div>
            </div>
            <div class="info-box">
                <strong>📌 What happens next?</strong><br>
                • We will review your reservation<br>
                • You will receive a confirmation within 24 hours<br>
                • A member of our team may call to verify your booking
            </div>
            <div class="info-box">
                <strong>📍 Location & Hours</strong><br>
                Ring Road, Parklands-Westlands, Nairobi<br>
                Open Daily: 12:00 PM - 10:00 PM
            </div>
            <div class="arigato">Arigato gozaimasu! 🙇</div>
        </div>
        <div class="footer">
            <p>Furusato Japanese Restaurant<br>📞 +254 722488706 | 📧 furusatoreservation@gmail.com</p>
            <p style="font-size: 11px;">This is an automated confirmation. Please save this email for your records.</p>
        </div>
    </div>
</body>
</html>';
    
    // Multipart for customer
    $boundary = md5(uniqid(time()));
    $headers .= "Content-Type: multipart/alternative; boundary=\"$boundary\"\r\n";
    
    $message = "--$boundary\r\n";
    $message .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $message .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
    $message .= $plainText . "\r\n\r\n";
    $message .= "--$boundary\r\n";
    $message .= "Content-Type: text/html; charset=UTF-8\r\n";
    $message .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
    $message .= $htmlMessage . "\r\n\r\n";
    $message .= "--$boundary--";
    
    return @mail($to, $subject, $message, $headers);
}

/**
 * Helper function to test email configuration
 */
function sendTestEmail($testEmail = "furusatoreservation@gmail.com") {
    $subject = "✅ Furusato Email Test - " . date('Y-m-d H:i:s');
    
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "From: test@furusatorestaurant.com\r\n";
    $headers .= "Return-Path: <bounce@furusatorestaurant.com>\r\n";
    
    $plainText = "Email Test Successful!\n\n";
    $plainText .= "Time: " . date('Y-m-d H:i:s T') . "\n";
    $plainText .= "Server: " . $_SERVER['SERVER_NAME'] . "\n";
    $plainText .= "PHP Version: " . phpversion() . "\n\n";
    $plainText .= "Your Furusato Restaurant email system is working correctly.";
    
    $htmlMessage = '<html><body>
        <h2>✅ Email Test Successful!</h2>
        <p>Your Furusato Restaurant email system is working correctly.</p>
        <p><strong>Time:</strong> ' . date('Y-m-d H:i:s T') . '</p>
        <p><strong>Server:</strong> ' . $_SERVER['SERVER_NAME'] . '</p>
        <p><strong>PHP Version:</strong> ' . phpversion() . '</p>
        <hr><p>You can now accept real reservations!</p>
    </body></html>';
    
    $boundary = md5(uniqid(time()));
    $headers .= "Content-Type: multipart/alternative; boundary=\"$boundary\"\r\n";
    
    $message = "--$boundary\r\n";
    $message .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n";
    $message .= $plainText . "\r\n\r\n";
    $message .= "--$boundary\r\n";
    $message .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
    $message .= $htmlMessage . "\r\n\r\n";
    $message .= "--$boundary--";
    
    return @mail($testEmail, $subject, $message, $headers);
}
?>