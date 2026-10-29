<?php
// Sends emails with PHPMailer (bundled in lib/PHPMailer, no Composer needed).
require_once __DIR__ . '/../lib/PHPMailer/Exception.php';
require_once __DIR__ . '/../lib/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/../lib/PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;

/** The last error from send_mail(), shown on the Settings page when a test email fails. */
$GLOBALS['mail_error'] = '';

/** Returns true when the email was sent (or saved to the log in test mode). */
function send_mail(string $toEmail, string $toName, string $subject, string $html, string $text, ?array $cfg = null): bool
{
    $cfg = $cfg ?? mail_settings();
    $GLOBALS['mail_error'] = '';

    if ($cfg['effective'] === 'log') {
        $dir = __DIR__ . '/../storage';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $entry = '<hr><p><b>' . date('Y-m-d H:i:s') . '</b> To: ' . e($toName) . ' &lt;' . e($toEmail) . '&gt;<br>'
            . 'Subject: ' . e($subject) . "</p>\n" . $html . "\n";
        return file_put_contents($dir . '/mail-log.html', $entry, FILE_APPEND | LOCK_EX) !== false;
    }

    if (!extension_loaded('openssl')) {
        $GLOBALS['mail_error'] = 'PHP OpenSSL is turned off. In C:\\xampp\\php\\php.ini remove the ; before extension=openssl, then restart Apache.';
        error_log('CareFirst mail error: ' . $GLOBALS['mail_error']);
        return false;
    }

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = $cfg['host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $cfg['user'];
        $mail->Password   = $cfg['pass'];
        $mail->SMTPSecure = $cfg['secure'] === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = $cfg['port'];
        $mail->CharSet    = 'UTF-8';
        $mail->Timeout    = 20;

        $mail->setFrom($cfg['from'], $cfg['from_name']);
        $mail->addAddress($toEmail, $toName);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $html;
        $mail->AltBody = $text;
        $mail->send();
        return true;
    } catch (\Throwable $ex) {
        $GLOBALS['mail_error'] = friendly_mail_error($mail->ErrorInfo ?: $ex->getMessage());
        error_log('CareFirst mail error: ' . $mail->ErrorInfo . ' ' . $ex->getMessage());
        return false;
    }
}

function friendly_mail_error(string $raw): string
{
    if (stripos($raw, 'authenticate') !== false || stripos($raw, '535') !== false) {
        return 'Gmail rejected the email or password. Use a 16-letter App Password (not your normal Gmail password), and check the email address. Details: ' . $raw;
    }
    if (stripos($raw, 'connect') !== false) {
        return 'Could not connect to the email server. Check the server and port, your internet connection, and that antivirus or firewall is not blocking port 587/465. Details: ' . $raw;
    }
    return $raw;
}

/** Creates a fresh confirmation link for the user and emails it. */
function send_verification_email(int $userId, string $email, string $name): bool
{
    $token = bin2hex(random_bytes(32));
    $st = db()->prepare('UPDATE users SET verify_token_hash = ?, verify_expires_at = DATE_ADD(NOW(), INTERVAL 24 HOUR) WHERE id = ?');
    $st->execute([hash('sha256', $token), $userId]);

    $link = site_url('verify.php?token=' . $token);
    $first = e(explode(' ', $name)[0]);

    $html = '<div style="font-family:Arial,sans-serif;max-width:520px;margin:auto;color:#14305e">'
        . '<h2 style="color:#3b9441;margin-bottom:4px">Welcome to CareFirst</h2>'
        . '<p>Hi ' . $first . ',</p>'
        . '<p>Thanks for creating an account on the CareFirst website. Please confirm your email address to finish signing up:</p>'
        . '<p style="margin:26px 0"><a href="' . e($link) . '" style="background:#3b9441;color:#fff;padding:13px 26px;border-radius:999px;text-decoration:none;font-weight:bold">Confirm my email</a></p>'
        . '<p style="font-size:13px;color:#4d5f78">Or copy this link into your browser:<br><a href="' . e($link) . '">' . e($link) . '</a></p>'
        . '<p style="font-size:13px;color:#4d5f78">This link expires in 24 hours. If you did not sign up, you can ignore this email.</p>'
        . '<p>CareFirst Home Nursing<br>We Care, You Recover</p></div>';

    $text = "Hi $first,\n\nPlease confirm your email to finish signing up on the CareFirst website:\n$link\n\n"
        . "This link expires in 24 hours. If you did not sign up, ignore this email.\n\nCareFirst Home Nursing";

    return send_mail($email, $name, 'Confirm your email - CareFirst', $html, $text);
}
