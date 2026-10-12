<?php
require_once dirname(__DIR__) . '/includes/auth.php';

$token = (string)($_POST['token'] ?? $_GET['token'] ?? '');
$validFormat = (bool)preg_match('/\A[a-f0-9]{64}\z/', $token);
$tokenRow = null;
if ($validFormat) {
    $q = db()->prepare('SELECT id, user_id FROM email_verification_tokens WHERE token_hash = ? AND used_at IS NULL AND expires_at > NOW() LIMIT 1');
    $q->execute([hash('sha256', $token)]);
    $tokenRow = $q->fetch();
}
$error = '';
$verified = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (!$tokenRow) {
        $error = 'This verification link is invalid or has expired. Request a new link after signing in.';
    } else {
        $db = db();
        $db->beginTransaction();
        try {
            $q = $db->prepare('SELECT user_id FROM email_verification_tokens WHERE id = ? AND used_at IS NULL AND expires_at > NOW() FOR UPDATE');
            $q->execute([$tokenRow['id']]);
            $userId = (int)$q->fetchColumn();
            if (!$userId) throw new RuntimeException('Verification link is no longer available.');
            $db->prepare('UPDATE users SET email_verified_at = NOW() WHERE id = ?')->execute([$userId]);
            $db->prepare('UPDATE email_verification_tokens SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL')->execute([$userId]);
            $db->commit();
            if ((int)(current_user()['id'] ?? 0) === $userId) $_SESSION['user']['email_verified_at'] = date('Y-m-d H:i:s');
            audit('email_verified', 'user', $userId, 'Email address verified');
            $verified = true;
        } catch (Throwable $exception) {
            if ($db->inTransaction()) $db->rollBack();
            $error = 'This verification link is invalid or has expired. Request a new link after signing in.';
        }
    }
}
$pageTitle = 'Verify email address';
require dirname(__DIR__) . '/includes/header.php';
?>
<div class="row justify-content-center"><div class="col-md-7 col-lg-6"><section class="card p-4">
<?php if ($verified): ?>
  <h2 class="h4">Email verified</h2><p class="text-muted">Your email address is now verified for the NAUB FYP Portal.</p>
  <a class="btn btn-primary" href="<?= e(url(current_user() ? role_home(current_user()['role']) : 'auth/login.php')) ?>">Continue</a>
<?php elseif ($error): ?>
  <h2 class="h4">Verification link unavailable</h2><div class="alert alert-warning" role="alert"><?= e($error) ?></div><a class="btn btn-outline-primary" href="<?= e(url('auth/login.php')) ?>">Return to sign in</a>
<?php elseif ($tokenRow): ?>
  <h2 class="h4">Confirm your email</h2><p class="text-muted">Confirm that this email address belongs to your portal account. The link expires in 24 hours.</p>
  <form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="token" value="<?= e($token) ?>"><button class="btn btn-primary">Verify email address</button></form>
<?php else: ?>
  <h2 class="h4">Verification link unavailable</h2><p class="text-muted">Sign in and request a fresh email verification link.</p><a class="btn btn-outline-primary" href="<?= e(url('auth/login.php')) ?>">Return to sign in</a>
<?php endif; ?>
</section></div></div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
