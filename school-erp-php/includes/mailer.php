<?php
/**
 * Mailer Helper — PHPMailer SMTP wrapper
 * School ERP v3.0
 *
 * Config keys (from .env.php / bootstrap):
 *   SMTP_HOST, SMTP_PORT, SMTP_USER, SMTP_PASS
 *   SMTP_FROM_EMAIL, SMTP_FROM_NAME
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailerException;

class Mailer
{
    /**
     * Send an email. Returns true on success, false on failure.
     *
     * @param string $to       Recipient email
     * @param string $subject  Email subject
     * @param string $htmlBody HTML body
     * @param string $textBody Optional plain text fallback
     */
    public static function send(string $to, string $subject, string $htmlBody, string $textBody = ''): bool
    {
        // Bail silently if no SMTP host configured (local dev)
        if (!defined('SMTP_HOST') || empty(SMTP_HOST)) {
            error_log("[Mailer] SMTP_HOST not set. Would have sent to: $to | Subject: $subject");
            return false;
        }

        // PHPMailer autoload via composer
        $autoload = __DIR__ . '/../vendor/autoload.php';
        if (!file_exists($autoload)) {
            error_log("[Mailer] Composer autoload not found.");
            return false;
        }
        require_once $autoload;

        $mail = new PHPMailer(true);
        try {
            // Server settings
            $mail->isSMTP();
            $mail->Host       = SMTP_HOST;
            $mail->SMTPAuth   = true;
            $mail->Username   = defined('SMTP_USER') ? SMTP_USER : '';
            $mail->Password   = defined('SMTP_PASS') ? SMTP_PASS : '';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = defined('SMTP_PORT') ? (int)SMTP_PORT : 587;
            $mail->CharSet    = 'UTF-8';
            $mail->Timeout    = 10;

            // Recipients
            $fromEmail = defined('SMTP_FROM_EMAIL') ? SMTP_FROM_EMAIL : 'noreply@school.erp';
            $fromName  = defined('SMTP_FROM_NAME')  ? SMTP_FROM_NAME  : 'School ERP';
            $mail->setFrom($fromEmail, $fromName);
            $mail->addAddress($to);
            $mail->addReplyTo($fromEmail, $fromName);

            // Content
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $htmlBody;
            $mail->AltBody = $textBody ?: strip_tags($htmlBody);

            $mail->send();
            return true;
        } catch (MailerException $e) {
            error_log("[Mailer] Failed to send to $to: " . $mail->ErrorInfo);
            return false;
        }
    }

    /**
     * Build an HTML email with school branding.
     */
    public static function buildHtml(string $heading, string $contentHtml): string
    {
        $schoolName = defined('APP_NAME') ? APP_NAME : 'School ERP';
        $appUrl     = defined('APP_URL')  ? APP_URL  : '#';
        $year       = date('Y');

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>{$heading}</title>
  <style>
    body{margin:0;padding:0;background:#0f1117;font-family:'Segoe UI',Arial,sans-serif;color:#e2e8f0}
    .wrap{max-width:560px;margin:40px auto;background:#1a1f2e;border-radius:16px;overflow:hidden;box-shadow:0 8px 40px rgba(0,0,0,.5)}
    .header{background:linear-gradient(135deg,#3b5bdb,#7048e8);padding:32px 36px 24px;text-align:center}
    .logo{font-size:22px;font-weight:800;color:#fff;letter-spacing:-0.5px}
    .logo span{opacity:.7}
    .body{padding:36px}
    h2{margin:0 0 18px;font-size:20px;font-weight:700;color:#f8fafc}
    p{margin:0 0 14px;line-height:1.65;color:#94a3b8;font-size:15px}
    .cred-box{background:#0f1117;border:1px solid #2d3748;border-radius:12px;padding:20px 24px;margin:20px 0}
    .cred-row{display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid #1e293b;font-size:14px}
    .cred-row:last-child{border-bottom:none}
    .cred-label{color:#64748b;text-transform:uppercase;font-size:11px;letter-spacing:.5px;padding-top:2px}
    .cred-value{color:#e2e8f0;font-weight:600;font-family:monospace;font-size:15px}
    .btn{display:inline-block;margin:20px 0 8px;padding:12px 28px;background:linear-gradient(135deg,#3b5bdb,#7048e8);color:#fff;text-decoration:none;border-radius:8px;font-weight:600;font-size:14px}
    .footer{text-align:center;padding:20px 36px;color:#475569;font-size:12px;border-top:1px solid #1e293b}
    .warning{background:#2d1b14;border:1px solid #7c3a1e;border-radius:8px;padding:12px 16px;color:#f97316;font-size:13px;margin-top:14px}
  </style>
</head>
<body>
<div class="wrap">
  <div class="header">
    <div class="logo">{$schoolName} <span>Portal</span></div>
  </div>
  <div class="body">
    <h2>{$heading}</h2>
    {$contentHtml}
  </div>
  <div class="footer">&copy; {$year} {$schoolName}. All rights reserved.<br>
    <a href="{$appUrl}" style="color:#7048e8">{$appUrl}</a>
  </div>
</div>
</body>
</html>
HTML;
    }
}
