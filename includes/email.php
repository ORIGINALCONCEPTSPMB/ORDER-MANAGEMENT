<?php
/**
 * Email sending via SMTP with PHP mail() fallback.
 *
 * Authentication note: SimpleSMTP uses AUTH LOGIN only, which is supported
 * by the majority of commercial SMTP providers (Gmail, Mailgun, SendGrid, etc.).
 * If your server requires AUTH PLAIN or XOAUTH2, use a full-featured library
 * such as PHPMailer or SwiftMailer instead.
 */

class SimpleSMTP {
    private string $host;
    private int    $port;
    private string $username;
    private string $password;
    private string $encryption;

    /** @var resource|false */
    private $socket = false;

    public function __construct(
        string $host,
        int    $port,
        string $username,
        string $password,
        string $encryption = 'tls'
    ) {
        $this->host       = $host;
        $this->port       = $port;
        $this->username   = $username;
        $this->password   = $password;
        $this->encryption = strtolower($encryption);
    }

    /** Send a command and read the server response */
    private function cmd(string $command): string {
        fwrite($this->socket, $command . "\r\n");
        return $this->read();
    }

    /** Read a full multi-line SMTP response */
    private function read(): string {
        $response = '';
        while ($line = fgets($this->socket, 515)) {
            $response .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        return $response;
    }

    /** Return the numeric code from an SMTP response line */
    private function code(string $response): int {
        return (int) substr(trim($response), 0, 3);
    }

    /**
     * Send an email message.
     *
     * @param string $from      Sender address
     * @param string $fromName  Sender display name
     * @param string $to        Recipient address
     * @param string $subject   Email subject
     * @param string $htmlBody  HTML message body
     * @param string $textBody  Plain-text fallback body
     * @return bool
     */
    public function sendMail(
        string $from,
        string $fromName,
        string $to,
        string $subject,
        string $htmlBody,
        string $textBody = ''
    ): bool {
        try {
            // --- Connect ---
            $address = ($this->encryption === 'ssl')
                ? 'ssl://' . $this->host
                : $this->host;

            $this->socket = fsockopen($address, $this->port, $errno, $errstr, 10);
            if (!$this->socket) {
                return false;
            }
            stream_set_timeout($this->socket, 10);

            $greeting = $this->read();
            if ($this->code($greeting) !== 220) {
                return false;
            }

            // --- EHLO ---
            $ehlo = $this->cmd('EHLO ' . (gethostname() ?: 'localhost'));
            if ($this->code($ehlo) !== 250) {
                return false;
            }

            // --- STARTTLS ---
            if ($this->encryption === 'tls') {
                $starttls = $this->cmd('STARTTLS');
                if ($this->code($starttls) !== 220) {
                    return false;
                }
                if (!stream_socket_enable_crypto(
                    $this->socket,
                    true,
                    STREAM_CRYPTO_METHOD_TLS_CLIENT
                )) {
                    return false;
                }
                // Re-issue EHLO after TLS upgrade
                $this->cmd('EHLO ' . (gethostname() ?: 'localhost'));
            }

            // --- AUTH LOGIN ---
            $auth = $this->cmd('AUTH LOGIN');
            if ($this->code($auth) !== 334) {
                return false;
            }
            $u = $this->cmd(base64_encode($this->username));
            if ($this->code($u) !== 334) {
                return false;
            }
            $p = $this->cmd(base64_encode($this->password));
            if ($this->code($p) !== 235) {
                return false;
            }

            // --- MAIL FROM ---
            $mf = $this->cmd('MAIL FROM:<' . $from . '>');
            if ($this->code($mf) !== 250) {
                return false;
            }

            // --- RCPT TO ---
            $rt = $this->cmd('RCPT TO:<' . $to . '>');
            if ($this->code($rt) !== 250) {
                return false;
            }

            // --- DATA ---
            $data = $this->cmd('DATA');
            if ($this->code($data) !== 354) {
                return false;
            }

            $boundary = '----=_Part_' . bin2hex(random_bytes(16));
            $headers  = "From: =?UTF-8?B?" . base64_encode($fromName) . "?= <{$from}>\r\n";
            $headers .= "To: {$to}\r\n";
            $headers .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
            $headers .= "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n";
            $headers .= "Date: " . date('r') . "\r\n";

            $body  = "--{$boundary}\r\n";
            $body .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n";
            $body .= ($textBody ?: strip_tags($htmlBody)) . "\r\n";
            $body .= "--{$boundary}\r\n";
            $body .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
            $body .= $htmlBody . "\r\n";
            $body .= "--{$boundary}--\r\n";

            fwrite($this->socket, $headers . "\r\n" . $body . "\r\n.\r\n");
            $sent = $this->read();

            $this->cmd('QUIT');
            fclose($this->socket);

            return $this->code($sent) === 250;
        } catch (Exception $e) {
            if ($this->socket) {
                fclose($this->socket);
            }
            return false;
        }
    }
}

// ---------------------------------------------------------------------------

/**
 * Send an email, preferring SMTP when configured.
 */
function sendEmail(string $to, string $subject, string $htmlBody, string $textBody = ''): bool {
    $mailHost = defined('MAIL_HOST') ? MAIL_HOST : '';

    if ($mailHost && $mailHost !== '') {
        $smtp = new SimpleSMTP(
            MAIL_HOST,
            (int) MAIL_PORT,
            MAIL_USERNAME,
            MAIL_PASSWORD,
            MAIL_ENCRYPTION
        );
        $result = $smtp->sendMail(
            MAIL_FROM_EMAIL,
            MAIL_FROM_NAME,
            $to,
            $subject,
            $htmlBody,
            $textBody
        );
        if ($result) {
            return true;
        }
        // Fall through to PHP mail() on SMTP failure
    }

    // Fallback: PHP mail()
    $fromName  = defined('MAIL_FROM_NAME')  ? MAIL_FROM_NAME  : (defined('APP_NAME') ? APP_NAME : 'Order Management');
    $fromEmail = defined('MAIL_FROM_EMAIL') ? MAIL_FROM_EMAIL : 'noreply@localhost';
    $headers   = "MIME-Version: 1.0\r\n";
    $headers  .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers  .= "From: {$fromName} <{$fromEmail}>\r\n";

    return mail($to, $subject, $htmlBody, $headers);
}

// ---------------------------------------------------------------------------

/**
 * Send a verification / 2FA code email.
 */
function sendVerificationCode(string $email, string $name, string $code, string $type): bool {
    $appName = defined('APP_NAME') ? APP_NAME : 'Order Management System';

    switch ($type) {
        case 'registration':
            $subject = 'Verify your email address';
            $heading = 'Welcome! Please verify your email';
            $message = 'Thank you for registering. Use the code below to verify your email address.';
            break;
        case 'login_2fa':
            $subject = 'Your login verification code';
            $heading = 'Two-Factor Authentication Code';
            $message = 'Use the code below to complete your login. It expires in 10 minutes.';
            break;
        case 'password_reset':
            $subject = 'Your password reset code';
            $heading = 'Password Reset Code';
            $message = 'Use the code below to reset your password. It expires in 10 minutes.';
            break;
        default:
            $subject = 'Your verification code';
            $heading = 'Verification Code';
            $message = 'Use the code below to verify your action.';
    }

    $html = buildEmailTemplate($appName, $heading, "
        <p>Hi {$name},</p>
        <p>{$message}</p>
        <div style='text-align:center;margin:30px 0;'>
            <span style='font-size:36px;font-weight:bold;letter-spacing:10px;color:#2563eb;background:#eff6ff;padding:15px 25px;border-radius:8px;display:inline-block;'>{$code}</span>
        </div>
        <p>This code expires in 10 minutes. If you did not request this, please ignore this email.</p>
    ");

    return sendEmail($email, $subject, $html);
}

/**
 * Send a password reset link email.
 */
function sendPasswordResetEmail(string $email, string $name, string $resetUrl): bool {
    $appName = defined('APP_NAME') ? APP_NAME : 'Order Management System';
    $subject = 'Reset your password';

    $html = buildEmailTemplate($appName, 'Reset Your Password', "
        <p>Hi {$name},</p>
        <p>We received a request to reset your password. Click the button below to choose a new password.</p>
        <div style='text-align:center;margin:30px 0;'>
            <a href='{$resetUrl}' style='background:#2563eb;color:#fff;padding:14px 28px;border-radius:6px;text-decoration:none;font-weight:600;display:inline-block;'>Reset Password</a>
        </div>
        <p>Or copy this link into your browser:</p>
        <p style='word-break:break-all;color:#64748b;font-size:13px;'>{$resetUrl}</p>
        <p>This link expires in 1 hour. If you did not request a password reset, you can safely ignore this email.</p>
    ");

    return sendEmail($email, $subject, $html);
}

/**
 * Send a welcome email after a user's account is verified.
 */
function sendWelcomeEmail(string $email, string $name): bool {
    $appName = defined('APP_NAME') ? APP_NAME : 'Order Management System';
    $appUrl  = defined('APP_URL')  ? APP_URL  : '';
    $subject = "Welcome to {$appName}!";

    $html = buildEmailTemplate($appName, "Welcome to {$appName}!", "
        <p>Hi {$name},</p>
        <p>Your account has been verified and you are now all set to use <strong>{$appName}</strong>.</p>
        " . ($appUrl ? "<div style='text-align:center;margin:30px 0;'><a href='{$appUrl}' style='background:#16a34a;color:#fff;padding:14px 28px;border-radius:6px;text-decoration:none;font-weight:600;display:inline-block;'>Go to Dashboard</a></div>" : '') . "
        <p>If you have any questions, please contact your administrator.</p>
    ");

    return sendEmail($email, $subject, $html);
}

// ---------------------------------------------------------------------------

/**
 * Shared HTML email wrapper template.
 */
function buildEmailTemplate(string $appName, string $heading, string $content): string {
    return <<<HTML
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>{$heading}</title>
</head>
<body style="margin:0;padding:0;background:#f8fafc;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f8fafc;padding:40px 0;">
  <tr><td align="center">
    <table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;">
      <tr>
        <td style="background:#1e293b;padding:24px 32px;border-radius:8px 8px 0 0;text-align:center;">
          <span style="color:#fff;font-size:20px;font-weight:700;">{$appName}</span>
        </td>
      </tr>
      <tr>
        <td style="background:#fff;padding:32px;border:1px solid #e2e8f0;border-top:none;border-radius:0 0 8px 8px;">
          <h2 style="margin:0 0 20px;color:#1e293b;font-size:22px;">{$heading}</h2>
          <div style="color:#475569;font-size:15px;line-height:1.6;">
            {$content}
          </div>
        </td>
      </tr>
      <tr>
        <td style="padding:20px;text-align:center;color:#94a3b8;font-size:12px;">
          &copy; {$appName}. All rights reserved.
        </td>
      </tr>
    </table>
  </td></tr>
</table>
</body>
</html>
HTML;
}
