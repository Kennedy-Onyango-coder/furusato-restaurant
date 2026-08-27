<?php
class PHPMailer {
    public $Host = '';
    public $Port = 587;
    public $SMTPAuth = true;
    public $Username = '';
    public $Password = '';
    public $SMTPSecure = 'tls';
    public $From = '';
    public $FromName = '';
    public $Subject = '';
    public $Body = '';
    public $AltBody = '';
    public $isHTML = false;
    public $CharSet = 'UTF-8';
    public $ErrorInfo = '';
    public $SMTPDebug = 0;

    private $to = [];
    private $cc = [];
    private $bcc = [];
    private $ReplyTo = [];
    private $attachments = [];
    private $smtp_conn = null;
    private $last_smtp_response = '';

    public function setFrom($address, $name = '') {
        $this->From = $address;
        $this->FromName = $name;
    }

    public function addAddress($address, $name = '') {
        $this->to[] = [$address, $name];
    }

    public function addCC($address, $name = '') {
        $this->cc[] = [$address, $name];
    }

    public function addBCC($address, $name = '') {
        $this->bcc[] = [$address, $name];
    }

    public function addReplyTo($address, $name = '') {
        $this->ReplyTo[] = [$address, $name];
    }

    public function addAttachment($path, $name = '', $encoding = 'base64', $type = '', $disposition = 'attachment') {
        $this->attachments[] = ['path' => $path, 'name' => $name ?: basename($path), 'type' => $type, 'disposition' => $disposition];
    }

    public function isSMTP() {
        // Use SMTP
    }

    public function isHTML($html = true) {
        $this->isHTML = $html;
    }

    public function send() {
        $this->ErrorInfo = '';
        if (empty($this->Host)) {
            throw new Exception('SMTP host not set');
        }
        if (empty($this->to)) {
            throw new Exception('No recipients');
        }

        $this->smtp_conn = @fsockopen(
            $this->Host,
            $this->Port,
            $errno,
            $errstr,
            30
        );

        if (!$this->smtp_conn) {
            $this->ErrorInfo = "Could not connect to SMTP host {$this->Host}:{$this->Port} ({$errstr})";
            throw new Exception($this->ErrorInfo);
        }

        stream_set_timeout($this->smtp_conn, 30);

        $this->smtpRead(); // greeting

        $this->smtpSend("EHLO " . ($_SERVER['SERVER_NAME'] ?? 'localhost'));

        if ($this->SMTPSecure === 'tls') {
            $this->smtpSend("STARTTLS");
            stream_socket_enable_crypto($this->smtp_conn, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            $this->smtpSend("EHLO " . ($_SERVER['SERVER_NAME'] ?? 'localhost'));
        }

        if ($this->SMTPAuth) {
            $this->smtpSend("AUTH LOGIN");
            $this->smtpSend(base64_encode($this->Username));
            $this->smtpSend(base64_encode($this->Password));
        }

        $this->smtpSend("MAIL FROM:<{$this->From}>");

        foreach ($this->to as $recipient) {
            $this->smtpSend("RCPT TO:<{$recipient[0]}>");
        }
        foreach ($this->cc as $recipient) {
            $this->smtpSend("RCPT TO:<{$recipient[0]}>");
        }
        foreach ($this->bcc as $recipient) {
            $this->smtpSend("RCPT TO:<{$recipient[0]}>");
        }

        $this->smtpSend("DATA");

        $headers = $this->createHeaders();
        $body = $this->createBody();
        $message = $headers . "\r\n\r\n" . $body . "\r\n.";

        $this->smtpSend($message);

        $this->smtpSend("QUIT");
        fclose($this->smtp_conn);

        return true;
    }

    private function smtpSend($cmd) {
        fwrite($this->smtp_conn, $cmd . "\r\n");
        $response = $this->smtpRead();
        $code = (int)substr($response, 0, 3);
        if ($code >= 400 && $code < 500 && $cmd !== 'AUTH LOGIN') {
            // Some servers return 334 for AUTH, that's fine
        }
        if ($code >= 500) {
            throw new Exception("SMTP error: {$response}");
        }
        return $response;
    }

    private function smtpRead() {
        $response = '';
        while ($line = fgets($this->smtp_conn, 515)) {
            $response .= $line;
            if (substr($line, 3, 1) === ' ') break;
        }
        return $response;
    }

    private function createHeaders() {
        $headers = [];
        $headers[] = "From: =?{$this->CharSet}?B?" . base64_encode($this->FromName) . "?= <{$this->From}>";
        $headers[] = "Return-Path: <{$this->From}>";

        foreach ($this->to as $recipient) {
            $headers[] = "To: =?{$this->CharSet}?B?" . base64_encode($recipient[1]) . "?= <{$recipient[0]}>";
        }
        foreach ($this->cc as $recipient) {
            $headers[] = "Cc: =?{$this->CharSet}?B?" . base64_encode($recipient[1]) . "?= <{$recipient[0]}>";
        }
        foreach ($this->ReplyTo as $rt) {
            $headers[] = "Reply-To: =?{$this->CharSet}?B?" . base64_encode($rt[1]) . "?= <{$rt[0]}>";
        }

        $headers[] = "Subject: =?{$this->CharSet}?B?" . base64_encode($this->Subject) . "?=";
        $headers[] = "Date: " . date('r');
        $headers[] = "MIME-Version: 1.0";
        $headers[] = "Content-Type: text/html; charset={$this->CharSet}";
        $headers[] = "Content-Transfer-Encoding: 8bit";
        $headers[] = "X-Mailer: PHPMailer-Furusato";

        return implode("\r\n", $headers);
    }

    private function createBody() {
        if ($this->isHTML) {
            return $this->Body;
        }
        return strip_tags($this->Body);
    }
}

class Exception extends \Exception {}
