<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/database.php';

function send_portal_email(string $recipient, string $recipientName, string $subject, string $htmlBody, string $textBody): bool
{
    $host = trim((string)getenv('FYP_SMTP_HOST'));
    $username = trim((string)getenv('FYP_SMTP_USER'));
    $password = (string)getenv('FYP_SMTP_PASS');
    $from = trim((string)getenv('FYP_SMTP_FROM'));
    $secure = strtolower(trim((string)(getenv('FYP_SMTP_SECURE') ?: 'tls')));
    $port = (int)(getenv('FYP_SMTP_PORT') ?: ($secure === 'ssl' ? 465 : 587));

    if ($host === '' || $username === '' || $password === '' || !filter_var($from, FILTER_VALIDATE_EMAIL)) {
        error_log('Portal email could not be sent because SMTP settings are incomplete.');
        return false;
    }

    $autoload = dirname(__DIR__) . '/vendor/autoload.php';
    if (!is_file($autoload)) {
        error_log('Portal email could not be sent because Composer dependencies are missing.');
        return false;
    }
    require_once $autoload;
    if (!class_exists(\PHPMailer\PHPMailer\PHPMailer::class)) {
        error_log('Portal email could not be sent because PHPMailer is unavailable.');
        return false;
    }

    try {
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $host;
        $mail->SMTPAuth = true;
        $mail->Username = $username;
        $mail->Password = $password;
        $mail->Port = $port;
        $mail->Timeout = 12;
        $mail->SMTPDebug = 0;
        $mail->CharSet = 'UTF-8';
        if ($secure === 'ssl') {
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($secure === 'tls') {
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        } else {
            throw new RuntimeException('SMTP encryption must be tls or ssl.');
        }
        $mail->setFrom($from, (string)getenv('FYP_SMTP_FROM_NAME') ?: APP_NAME);
        $mail->addAddress($recipient, $recipientName);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $htmlBody;
        $mail->AltBody = $textBody;
        $mail->send();
        return true;
    } catch (Throwable $error) {
        error_log('Portal email delivery failed: ' . $error->getMessage());
        return false;
    }
}

function issue_email_verification(int $userId, string $email, string $name): bool
{
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return false;
    $token = bin2hex(random_bytes(32));
    $db = db();
    $db->beginTransaction();
    try {
        $db->prepare('DELETE FROM email_verification_tokens WHERE user_id = ?')->execute([$userId]);
        $db->prepare('INSERT INTO email_verification_tokens (user_id, token_hash, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 24 HOUR))')
            ->execute([$userId, hash('sha256', $token)]);
        $db->commit();
    } catch (Throwable $error) {
        if ($db->inTransaction()) $db->rollBack();
        error_log('Email verification token could not be saved: ' . $error->getMessage());
        return false;
    }

    $link = url('auth/verify-email.php?token=' . rawurlencode($token));
    $safeName = htmlspecialchars($name ?: 'Portal user', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $safeLink = htmlspecialchars($link, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    return send_portal_email($email, $name, 'Verify your NAUB FYP Portal email',
        '<p>Hello ' . $safeName . ',</p><p>Confirm this email address for your NAUB Final Year Project Portal account.</p><p><a href="' . $safeLink . '">Verify email address</a></p><p>This link expires in 24 hours. If you did not request it, you can ignore this message.</p>',
        "Hello {$name},\n\nVerify your NAUB FYP Portal email using this link (expires in 24 hours):\n{$link}\n\nIf you did not request it, ignore this message.");
}

function issue_password_reset(int $userId, string $email, string $name): bool
{
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return false;
    $token = bin2hex(random_bytes(32));
    $db = db();
    $db->beginTransaction();
    try {
        $db->prepare('DELETE FROM password_reset_tokens WHERE user_id = ?')->execute([$userId]);
        $db->prepare('INSERT INTO password_reset_tokens (user_id, token_hash, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))')
            ->execute([$userId, hash('sha256', $token)]);
        $db->commit();
    } catch (Throwable $error) {
        if ($db->inTransaction()) $db->rollBack();
        error_log('Password reset token could not be saved: ' . $error->getMessage());
        return false;
    }

    $link = url('auth/reset-password.php?token=' . rawurlencode($token));
    $safeName = htmlspecialchars($name ?: 'Portal user', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $safeLink = htmlspecialchars($link, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    return send_portal_email($email, $name, 'Reset your NAUB FYP Portal password',
        '<p>Hello ' . $safeName . ',</p><p>A password reset was requested for your NAUB FYP Portal account.</p><p><a href="' . $safeLink . '">Choose a new password</a></p><p>This link expires in one hour. If you did not request it, ignore this message; your password will not change.</p>',
        "Hello {$name},\n\nReset your NAUB FYP Portal password using this link (expires in one hour):\n{$link}\n\nIf you did not request this, ignore this message.");
}
