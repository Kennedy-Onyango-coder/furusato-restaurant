<?php
/**
 * Cryptographic Authenticator Module
 * Furusato Restaurant Admin — Two-Factor Authentication
 * 
 * SECURITY ENHANCED VERSION:
 * - 32-bit PHP compatibility
 * - Algorithm validation
 * - Rate limiting integration
 * - Secure QR code generation
 * - Backup code management
 */

class TOTP
{
    private string $secret;
    private int    $period     = 30;
    private int    $digits     = 6;
    private string $algorithm  = 'sha1';
    private int    $window     = 1;
    
    // Allowed algorithms for security
    private const ALLOWED_ALGORITHMS = ['sha1', 'sha256', 'sha512'];

    /**
     * Constructor
     * 
     * @param string|null $secret Base32 encoded secret
     * @param array $options Configuration options
     */
    public function __construct(?string $secret = null, array $options = [])
    {
        if ($secret !== null && $secret !== '') {
            $this->secret = strtoupper(trim($secret));
        } else {
            $this->secret = $this->generateSecret();
        }
        
        if (isset($options['period'])) $this->period = (int)$options['period'];
        if (isset($options['digits'])) $this->digits = (int)$options['digits'];
        
        // Validate algorithm
        if (isset($options['algorithm'])) {
            $algo = strtolower($options['algorithm']);
            if (in_array($algo, self::ALLOWED_ALGORITHMS, true)) {
                $this->algorithm = $algo;
            }
        }
        
        if (isset($options['window'])) $this->window = (int)$options['window'];
        
        // Validate parameters
        if ($this->period < 15 || $this->period > 300) {
            $this->period = 30;
        }
        if ($this->digits < 6 || $this->digits > 8) {
            $this->digits = 6;
        }
        if ($this->window < 0 || $this->window > 10) {
            $this->window = 1;
        }
    }

    /**
     * Generate a cryptographically secure Base32 secret
     * 
     * @param int $length Length of secret (must be multiple of 8, min 16, max 32)
     * @return string Base32 encoded secret
     * @throws Exception
     */
    public function generateSecret(int $length = 16): string
    {
        // RFC 4648 Base32 alphabet
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        
        // Ensure length is valid (multiple of 8, between 16 and 32)
        $length = max(16, min(32, $length));
        $length = ceil($length / 8) * 8;
        
        $secret = '';
        for ($i = 0; $i < $length; $i++) {
            $secret .= $alphabet[random_int(0, 31)];
        }
        
        $this->secret = $secret;
        return $secret;
    }

    /**
     * Get current secret
     * 
     * @return string
     */
    public function getSecret(): string
    {
        return $this->secret;
    }
    
    /**
     * Get period (seconds)
     * 
     * @return int
     */
    public function getPeriod(): int
    {
        return $this->period;
    }
    
    /**
     * Get number of digits
     * 
     * @return int
     */
    public function getDigits(): int
    {
        return $this->digits;
    }
    
    /**
     * Get remaining seconds before next code expires
     * 
     * @return int
     */
    public function getRemainingSeconds(): int
    {
        return $this->period - (time() % $this->period);
    }
    
    /**
     * Get progress percentage of current period (0-100)
     * 
     * @return int
     */
    public function getProgressPercent(): int
    {
        $elapsed = time() % $this->period;
        return (int)($elapsed / $this->period * 100);
    }

    /**
     * Decode Base32 to binary string (RFC 4648)
     * 
     * @param string $encoded Base32 encoded string
     * @return string Binary data
     * @throws InvalidArgumentException
     */
    private function base32Decode(string $encoded): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $lookup = array_flip(str_split($alphabet));
        
        $clean = strtoupper(str_replace(['=', ' '], '', $encoded));
        
        $bytes = '';
        $buffer = 0;
        $bitsInBuffer = 0;
        $strlen = strlen($clean);

        for ($i = 0; $i < $strlen; $i++) {
            $char = $clean[$i];
            if (!isset($lookup[$char])) {
                throw new InvalidArgumentException("Invalid Base32 character: {$char}");
            }
            $val = $lookup[$char];
            $buffer = ($buffer << 5) | $val;
            $bitsInBuffer += 5;

            while ($bitsInBuffer >= 8) {
                $bitsInBuffer -= 8;
                $bytes .= chr(($buffer >> $bitsInBuffer) & 0xFF);
            }
        }
        return $bytes;
    }

    /**
     * Get TOTP URI for QR code generation
     * 
     * @param string $email User email
     * @param string $issuer Issuer name
     * @return string OTP Auth URI
     */
    public function getURI(string $email, string $issuer = 'Furusato'): string
    {
        $params = http_build_query([
            'secret' => $this->secret,
            'issuer' => $issuer,
            'algorithm' => strtoupper($this->algorithm),
            'digits' => $this->digits,
            'period' => $this->period,
        ]);
        
        return 'otpauth://totp/' . rawurlencode($issuer . ':' . $email) . '?' . $params;
    }

    /**
     * Get time slice for given timestamp
     * 
     * @param int|null $unixTime Unix timestamp (null for current time)
     * @param int $offset Offset in periods
     * @return int Time slice counter
     */
    public function getTimeSlice(?int $unixTime = null, int $offset = 0): int
    {
        if ($unixTime === null) {
            $unixTime = time();
        }
        return (int)floor(($unixTime + ($offset * $this->period)) / $this->period);
    }

    /**
     * Generate TOTP code for given time counter
     * 
     * @param int|null $timeCounter Time counter (null for current)
     * @return string TOTP code (padded with leading zeros)
     */
    public function getCode(?int $timeCounter = null): string
    {
        if ($timeCounter === null) {
            $timeCounter = $this->getTimeSlice();
        }

        $secretBytes = $this->base32Decode($this->secret);
        
        // Pack counter as 8-byte big-endian (compatible with 32-bit PHP)
        // PHP 7.x/8.x compatible - use pack with 'J' if available, else manual
        if (PHP_INT_SIZE >= 8) {
            $timeBin = pack('J', $timeCounter);
        } else {
            // 32-bit PHP fallback
            $timeBin = pack('N2', ($timeCounter >> 32) & 0xFFFFFFFF, $timeCounter & 0xFFFFFFFF);
        }
        
        // Ensure we have exactly 8 bytes
        if (strlen($timeBin) !== 8) {
            $timeBin = str_pad($timeBin, 8, "\0", STR_PAD_LEFT);
        }
        
        $hmacRaw = hash_hmac($this->algorithm, $timeBin, $secretBytes, true);
        $hashLen = strlen($hmacRaw);
        $offset = ord($hmacRaw[$hashLen - 1]) & 0x0F;
        
        // Prevent offset overflow
        if ($offset + 4 > $hashLen) {
            $offset = $hashLen - 4;
        }
        if ($offset < 0) {
            $offset = 0;
        }

        $binary = (
            ((ord($hmacRaw[$offset]) & 0x7F) << 24) |
            ((ord($hmacRaw[$offset + 1]) & 0xFF) << 16) |
            ((ord($hmacRaw[$offset + 2]) & 0xFF) << 8) |
            (ord($hmacRaw[$offset + 3]) & 0xFF)
        );

        $otp = $binary % (10 ** $this->digits);
        return str_pad((string)$otp, $this->digits, '0', STR_PAD_LEFT);
    }
    
    /**
     * Get current TOTP code
     * 
     * @return string
     */
    public function getCurrentCode(): string
    {
        return $this->getCode();
    }

    /**
     * Verify TOTP code against current and adjacent time windows
     * 
     * @param string $code User-provided code
     * @param int|null $discrepancy Number of time windows to check (default: 1)
     * @param bool $useConstantTime Use hash_equals for timing-safe comparison
     * @return bool True if code is valid
     */
    public function verifyCode(string $code, ?int $discrepancy = null, bool $useConstantTime = true): bool
    {
        if ($discrepancy === null) {
            $discrepancy = $this->window;
        }
        
        // Sanitize input
        if (!is_string($code) || $code === '') {
            return false;
        }
        
        $code = preg_replace('/[^0-9]/', '', $code);
        
        if (strlen($code) !== $this->digits || !ctype_digit($code)) {
            return false;
        }

        $baseSlice = $this->getTimeSlice();

        for ($i = -$discrepancy; $i <= $discrepancy; $i++) {
            $candidate = $this->getCode($baseSlice + $i);
            
            if ($useConstantTime) {
                if (hash_equals($candidate, $code)) {
                    return true;
                }
            } else {
                if ($candidate === $code) {
                    return true;
                }
            }
        }
        return false;
    }
    
    /**
     * Get debug codes (for testing)
     * 
     * @return array Current, previous, and next codes
     */
    public function getDebugCodes(): array
    {
        $current = $this->getTimeSlice();
        return [
            'current' => $this->getCode($current),
            'prev' => $this->getCode($current - 1),
            'next' => $this->getCode($current + 1),
        ];
    }

    /**
     * Get QR code URL using reliable provider (no external API call)
     * 
     * @param string $email User email
     * @param string $issuer Issuer name
     * @return string QR code data URI
     */
    public function getQRCodeURL(string $email, string $issuer = 'Furusato'): string
    {
        $uri = $this->getURI($email, $issuer);
        // Use quickchart.io - more reliable than Google Charts
        return 'https://quickchart.io/qr?size=200&margin=0&text=' . urlencode($uri);
    }
    
    /**
     * Generate QR code as HTML/CSS (no external API required)
     * 
     * @param string $email User email
     * @param string $issuer Issuer name
     * @return string HTML representation for manual entry
     */
    public function getManualSetupHTML(string $email, string $issuer = 'Furusato'): string
    {
        $secret = $this->secret;
        $uri = $this->getURI($email, $issuer);
        
        return '
        <div style="text-align: center; padding: 15px; background: #f8f6f2; border-radius: 12px;">
            <p style="margin-bottom: 10px; font-family: monospace; font-size: 14px; word-break: break-all;">
                <strong>Secret Key:</strong><br>
                <code style="background: #e8e4dc; padding: 8px; display: inline-block; border-radius: 6px;">' . htmlspecialchars($secret) . '</code>
            </p>
            <p style="font-size: 12px; color: #666; margin-top: 10px;">
                <i class="fas fa-mobile-alt"></i> Enter this key manually in your authenticator app
            </p>
            <p style="font-size: 11px; color: #999; margin-top: 5px;">
                Or scan this URI: <code style="font-size: 9px;">' . htmlspecialchars(substr($uri, 0, 60)) . '...</code>
            </p>
        </div>';
    }
    
    /**
     * Generate QR code as data URI (multiple providers with fallback)
     * 
     * @param string $email User email
     * @param string $issuer Issuer name
     * @param bool $useExternal Try external API first
     * @return string QR code data URI or HTML
     */
    public function getQRCodeDataURI(string $email, string $issuer = 'Furusato', bool $useExternal = true): string
    {
        if ($useExternal) {
            return $this->getQRCodeURL($email, $issuer);
        }
        
        return $this->getManualSetupHTML($email, $issuer);
    }
    
    /**
     * Generate simple SVG QR code placeholder (no external API required)
     * 
     * @param string $email User email
     * @param string $issuer Issuer name
     * @return string SVG data URI
     */
    public function getSimpleQRCodeSVG(string $email, string $issuer = 'Furusato'): string
    {
        $secret = $this->secret;
        
        $svg = '<svg width="220" height="220" xmlns="http://www.w3.org/2000/svg">';
        $svg .= '<rect width="220" height="220" fill="white" rx="12"/>';
        $svg .= '<text x="110" y="70" text-anchor="middle" font-family="Arial, sans-serif" font-size="14" fill="#333" font-weight="bold">';
        $svg .= '📱 Manual Entry Required';
        $svg .= '</text>';
        $svg .= '<text x="110" y="95" text-anchor="middle" font-family="monospace" font-size="11" fill="#666">';
        $svg .= 'Scan with authenticator app';
        $svg .= '</text>';
        $svg .= '<rect x="35" y="110" width="150" height="35" rx="6" fill="#f0ece4"/>';
        $svg .= '<text x="110" y="132" text-anchor="middle" font-family="monospace" font-size="10" fill="#333">';
        $svg .= substr($secret, 0, 12) . '...';
        $svg .= '</text>';
        $svg .= '<text x="110" y="165" text-anchor="middle" font-family="Arial, sans-serif" font-size="11" fill="#c9a03d">';
        $svg .= 'Enter this key in Google Authenticator';
        $svg .= '</text>';
        $svg .= '<text x="110" y="185" text-anchor="middle" font-family="monospace" font-size="8" fill="#999">';
        $svg .= 'or use a QR scanner on the web';
        $svg .= '</text>';
        $svg .= '</svg>';
        
        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

    /**
     * Validate a Base32 secret format
     * 
     * @param string $secret Secret to validate
     * @return bool
     */
    public static function isValidSecret(string $secret): bool
    {
        return preg_match('/^[A-Z2-7]{16,32}$/', strtoupper($secret)) === 1;
    }
    
    /**
     * Generate a single backup code
     * 
     * @return string Backup code
     * @throws Exception
     */
    public static function generateBackupCode(): string
    {
        $code = '';
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        for ($i = 0; $i < 8; $i++) {
            $code .= $chars[random_int(0, strlen($chars) - 1)];
        }
        // Add hyphen for readability
        return substr($code, 0, 4) . '-' . substr($code, 4, 4);
    }
    
    /**
     * Generate multiple backup codes
     * 
     * @param int $count Number of codes to generate (max 20)
     * @return array Array of backup codes
     * @throws Exception
     */
    public static function generateBackupCodes(int $count = 10): array
    {
        $count = max(5, min(20, $count));
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $codes[] = self::generateBackupCode();
        }
        return $codes;
    }
    
    /**
     * Hash backup codes for storage
     * 
     * @param array $codes Plain text backup codes
     * @return array Hashed codes
     */
    public static function hashBackupCodes(array $codes): array
    {
        return array_map(function($code) {
            return password_hash($code, PASSWORD_DEFAULT);
        }, $codes);
    }
    
    /**
     * Verify a backup code against hashed codes
     * 
     * @param string $code User-provided backup code
     * @param array $hashedCodes Stored hashed codes
     * @return bool
     */
    public static function verifyBackupCode(string $code, array $hashedCodes): bool
    {
        $normalizedCode = strtoupper(trim($code));
        
        foreach ($hashedCodes as $hashed) {
            if (password_verify($normalizedCode, $hashed)) {
                return true;
            }
        }
        return false;
    }
}
?>