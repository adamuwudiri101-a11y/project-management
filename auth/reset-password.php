<?php
require_once dirname(__DIR__) . '/includes/auth.php';

$token = (string)($_POST['token'] ?? $_GET['token'] ?? '');
$validFormat = (bool)preg_match('/\A[a-f0-9]{64}\z/', $token);
$tokenRow = null;
if ($validFormat) {
    $q = db()->prepare('SELECT id, user_id FROM password_reset_tokens WHERE token_hash = ? AND used_at IS NULL AND expires_at > NOW() LIMIT 1');
    $q->execute([hash('sha256', $token)]);
    $tokenRow = $q->fetch();
}
$error = '';
$reset = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $password = (string)($_POST['new_password'] ?? '');
    $confirmation = (string)($_POST['confirm_password'] ?? '');
    if (!$tokenRow) {
        $error = 'This password reset link is invalid or has expired. Request another link.';
    } elseif (strlen($password) < 12) {
        $error = 'Use at least 12 characters for your new password.';
    } elseif ($password !== $confirmation) {
        $error = 'The password confirmation does not match.';
    } else {
        $db = db();
        $db->beginTransaction();
        try {
            $q = $db->prepare('SELECT user_id FROM password_reset_tokens WHERE id = ? AND used_at IS NULL AND expires_at > NOW() FOR UPDATE');
            $q->execute([$tokenRow['id']]);
            $userId = (int)$q->fetchColumn();
            if (!$userId) throw new RuntimeException('Reset token is no longer available.');
            $db->prepare('UPDATE users SET password_hash = ?, must_change_password = 0 WHERE id = ?')
                ->execute([password_hash($password, PASSWORD_DEFAULT), $userId]);
            $db->prepare('UPDATE password_reset_tokens SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL')->execute([$userId]);
            $db->commit();
            audit('password_reset_completed', 'user', $userId, 'Password reset completed using a one-time link');
            $reset = true;
        } catch (Throwable $exception) {
            if ($db->inTransaction()) $db->rollBack();
            $error = 'The password could not be reset. Request a fresh link and try again.';
        }
    }
}
$pageTitle = 'Choose a new password';
$bareLayout = true;
require dirname(__DIR__) . '/includes/header.php';
?>
<h2 class="auth-title">Choose a new password</h2><p class="auth-lede">Use a strong password with at least 12 characters.</p>
<?php if ($reset): ?><div class="alert alert-success" role="status">Your password has been reset. You can now sign in.</div><a class="btn btn-primary" href="<?= e(url('auth/login.php')) ?>">Return to sign in</a>
<?php elseif ($error): ?><div class="alert alert-danger" role="alert"><?= e($error) ?></div><a class="btn btn-outline-primary" href="<?= e(url('auth/forgot-password.php')) ?>">Request a new link</a>
<?php elseif ($tokenRow): ?><form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="token" value="<?= e($token) ?>"><label class="form-label" for="new_password">New password</label><input class="form-control mb-3" id="new_password" name="new_password" type="password" minlength="12" autocomplete="new-password" required><label class="form-label" for="confirm_password">Confirm new password</label><input class="form-control mb-3" id="confirm_password" name="confirm_password" type="password" minlength="12" autocomplete="new-password" required><button class="btn btn-primary">Save new password</button></form>
<?php else: ?><div class="alert alert-warning">This reset link is invalid or has expired.</div><a class="btn btn-outline-primary" href="<?= e(url('auth/forgot-password.php')) ?>">Request a new link</a><?php endif; ?>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
