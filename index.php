<?php
require_once __DIR__ . '/includes/auth.php';
if (current_user()) redirect(role_home(current_user()['role']));
$pageTitle = 'Project management and supervision'; require __DIR__ . '/includes/header.php';
?><div class="row justify-content-center py-5"><div class="col-lg-8"><div class="card p-4 p-lg-5"><p class="text-uppercase text-primary fw-semibold mb-2">Nigerian Army University, Biu</p><h1 class="display-6 fw-bold">Final Year Project Management and Supervision System</h1><p class="lead page-intro">A central workspace for project registration, supervision, submissions, evaluation, and academic records.</p><a class="btn btn-primary align-self-start" href="<?= e(url('auth/login.php')) ?>">Sign in to continue</a></div></div></div><?php require __DIR__ . '/includes/footer.php'; ?>
