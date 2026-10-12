<?php
require_once dirname(__DIR__) . '/includes/auth.php';
if (current_user()) redirect(role_home(current_user()['role']));
$error='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    verify_csrf(); $login=trim((string)($_POST['login']??'')); $password=(string)($_POST['password']??'');
    $q=db()->prepare('SELECT u.*,r.name role FROM users u JOIN roles r ON r.id=u.role_id WHERE LOWER(u.email)=LOWER(?) OR UPPER(u.matric_number)=UPPER(?) OR UPPER(u.staff_number)=UPPER(?) LIMIT 1'); $q->execute([$login,$login,$login]); $u=$q->fetch();
    if ($u && $u['account_status']==='active' && password_verify($password,$u['password_hash'])) {
        session_regenerate_id(true); unset($u['password_hash']); $_SESSION['user']=$u; $_SESSION['last_activity']=time();
        db()->prepare('UPDATE users SET last_login_at=NOW() WHERE id=?')->execute([$u['id']]); audit('login','user',(int)$u['id'],'User signed in'); redirect(role_home($u['role']));
    }
    $error='The email or password was not recognized, or the account is inactive.';
}
$pageTitle='Sign in'; require dirname(__DIR__).'/includes/header.php';
?><div class="row justify-content-center"><div class="col-md-6 col-lg-5"><div class="card p-4"><h2 class="h4">NAUB FYP Portal</h2><p class="auth-lede">Final Year Project Management &amp; Supervision</p><?php if($error): ?><div role="alert" class="alert alert-danger"><?= e($error) ?></div><?php endif; ?><form method="post" autocomplete="on"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><label class="form-label" for="login">Matric number, staff number, or email</label><input class="form-control mb-3" id="login" name="login" type="text" maxlength="190" required autocomplete="username"><label class="form-label" for="password">Password</label><input class="form-control mb-3" id="password" name="password" type="password" required autocomplete="current-password"><button class="btn btn-primary w-100">Access Portal</button></form><p class="small mt-3 mb-0"><a href="<?= e(url('auth/forgot-password.php')) ?>">Forgot your password?</a></p></div></div></div><?php require dirname(__DIR__).'/includes/footer.php'; ?>
