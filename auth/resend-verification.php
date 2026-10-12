<?php
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/mailer.php';
$u = require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('Method not allowed.'); }
verify_csrf();
$q = db()->prepare('SELECT email, email_verified_at, first_name, last_name FROM users WHERE id = ?');
$q->execute([$u['id']]);
$account = $q->fetch();
if (!$account || !filter_var($account['email'] ?? '', FILTER_VALIDATE_EMAIL)) {
    flash('warning', 'Add a valid email address to your profile before requesting verification.');
} elseif (!empty($account['email_verified_at'])) {
    flash('info', 'Your email address is already verified.');
} elseif (issue_email_verification((int)$u['id'], $account['email'], trim(($account['first_name'] ?? '') . ' ' . ($account['last_name'] ?? '')))) {
    flash('success', 'A verification link has been sent to your email address.');
} else {
    flash('warning', 'The verification email could not be sent. Ask the system administrator to check the mail settings.');
}
redirect(role_home($u['role']));
