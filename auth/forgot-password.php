<?php
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/mailer.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = strtolower(post_string('email', 190));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Enter the email address registered to your account.';
    } else {
        $q = db()->prepare("SELECT u.id, u.email, u.first_name, u.last_name
            FROM users u JOIN roles r ON r.id = u.role_id
            WHERE LOWER(u.email) = LOWER(?) AND u.account_status = 'active' LIMIT 1");
        $q->execute([$email]);
        $account = $q->fetch();
        if ($account) {
            $throttle = db()->prepare('SELECT MAX(created_at) FROM password_reset_tokens WHERE user_id = ?');
            $throttle->execute([$account['id']]);
            $lastRequest = $throttle->fetchColumn();
            if (!$lastRequest || strtotime((string)$lastRequest) < time() - 60) {
                issue_password_reset((int)$account['id'], $account['email'], trim(($account['first_name'] ?? '') . ' ' . ($account['last_name'] ?? '')));
                audit('password_reset_requested', 'user', (int)$account['id'], 'Password reset requested by account holder');
            }
        }
        flash('success', 'If an active account uses that email address, a password reset link will arrive shortly. Check your inbox, including spam.');
        redirect('auth/forgot-password.php?sent=1');
    }
}
$pageTitle = 'Password assistance';
require dirname(__DIR__) . '/includes/header.php';
?>
<div class="row justify-content-center"><div class="col-md-6 col-lg-5"><section class="card p-4" aria-labelledby="password-assistance-title">
  <h2 class="h4" id="password-assistance-title">Reset your password</h2>
  <p class="text-muted">Enter the email address registered to your portal account. We’ll send a one-time link if it matches an active account.</p>
  <?php if ($error): ?><div class="alert alert-danger" role="alert"><?= e($error) ?></div><?php endif; ?>
  <form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><label class="form-label" for="email">Registered email address</label><input class="form-control mb-3" id="email" name="email" type="email" maxlength="190" autocomplete="email" required><button class="btn btn-primary w-100">Send reset link</button></form>
  <a class="btn btn-link mt-2" href="<?= e(url('auth/login.php')) ?>">Return to sign in</a>
</section></div></div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
