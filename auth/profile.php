<?php
require_once dirname(__DIR__).'/includes/auth.php';
require_once dirname(__DIR__).'/includes/mailer.php';
$u=require_login();
$err='';
$q=db()->prepare('SELECT u.*,r.name role,f.name faculty,d.name department,p.name programme FROM users u JOIN roles r ON r.id=u.role_id LEFT JOIN departments d ON d.id=u.department_id LEFT JOIN faculties f ON f.id=COALESCE(u.faculty_id,d.faculty_id) LEFT JOIN programmes p ON p.id=u.programme_id WHERE u.id=?');
$q->execute([$u['id']]);
$profile=$q->fetch();

if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf();
    $first=post_string('first_name',80);
    $last=post_string('last_name',80);
    $email=strtolower(post_string('email',190));
    $phone=post_string('phone',30);
    if($first===''||$last===''||!filter_var($email,FILTER_VALIDATE_EMAIL)){
        $err='Enter your first name, last name, and a valid email address.';
    }else{
        $check=db()->prepare('SELECT id FROM users WHERE email=? AND id<>?');
        $check->execute([$email,$u['id']]);
        if($check->fetchColumn()){
            $err='That email address is already in use.';
        }else{
            $emailChanged=strtolower((string)$profile['email'])!==$email;
            db()->prepare('UPDATE users SET first_name=?,last_name=?,email=?,email_verified_at=IF(?,NULL,email_verified_at),phone=?,profile_complete=1 WHERE id=?')
                ->execute([$first,$last,$email,$emailChanged?1:0,$phone?:null,$u['id']]);
            foreach(['first_name'=>$first,'last_name'=>$last,'email'=>$email,'phone'=>$phone,'profile_complete'=>1] as $key=>$value) $_SESSION['user'][$key]=$value;
            if($emailChanged) $_SESSION['user']['email_verified_at']=null;
            audit('profile_updated','user',(int)$u['id'],'User updated their personal contact details');
            $needsVerification=$emailChanged||empty($profile['email_verified_at']);
            $mailSent=$needsVerification?issue_email_verification((int)$u['id'],$email,trim($first.' '.$last)):true;
            flash('success','Your profile has been updated.');
            if($needsVerification) flash($mailSent?'info':'warning',$mailSent?'A verification link was sent to your email address.':'Email verification could not be sent. The system administrator needs to configure SMTP email.');
            redirect('auth/profile.php');
        }
    }
}

$project=null;$projectSupervisor=null;
if($profile['role']==='student'){
    $q=db()->prepare('SELECT p.id,p.title,p.status,ac.name session_name FROM projects p JOIN project_members pm ON pm.project_id=p.id LEFT JOIN academic_sessions ac ON ac.id=p.academic_session_id WHERE pm.student_id=? ORDER BY p.created_at DESC LIMIT 1');
    $q->execute([$u['id']]);$project=$q->fetch()?:null;
    if($project){$q=db()->prepare('SELECT CONCAT_WS(" ",su.first_name,su.last_name) FROM project_supervisors ps JOIN users su ON su.id=ps.supervisor_id WHERE ps.project_id=? AND ps.ended_at IS NULL ORDER BY ps.assigned_at DESC LIMIT 1');$q->execute([$project['id']]);$projectSupervisor=$q->fetchColumn()?:null;}
}
$pageTitle='My Profile';$crumbTrail=['Account','Profile'];require dirname(__DIR__).'/includes/header.php';
?>
<p class="page-intro">Review your account details. Your role and academic assignment are managed by the institution and cannot be changed here.</p>
<?php if($err): ?><div class="alert alert-danger" role="alert"><?= e($err) ?></div><?php endif; ?>
<?php if(empty($profile['email_verified_at'])&&!empty($profile['email'])): ?><div class="alert alert-warning d-flex flex-wrap align-items-center justify-content-between gap-2"><span>Your email is not verified. Verify it to support account recovery.</span><form method="post" action="<?= e(url('auth/resend-verification.php')) ?>"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><button class="btn btn-sm btn-outline-primary">Resend verification email</button></form></div><?php elseif(!empty($profile['email_verified_at'])): ?><div class="alert alert-success">Email address verified.</div><?php endif; ?>
<div class="row g-3">
 <div class="col-xl-7"><form class="card p-4 h-100" method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><h2 class="h5">Personal information</h2><p class="text-muted">You can update your name and contact details.</p><div class="row g-3"><div class="col-md-6"><label class="form-label" for="first_name">First name</label><input class="form-control" id="first_name" name="first_name" maxlength="80" value="<?= e($profile['first_name']??'') ?>" required></div><div class="col-md-6"><label class="form-label" for="last_name">Last name</label><input class="form-control" id="last_name" name="last_name" maxlength="80" value="<?= e($profile['last_name']??'') ?>" required></div><div class="col-md-8"><label class="form-label" for="email">Email address</label><input class="form-control" id="email" name="email" type="email" maxlength="190" value="<?= e($profile['email']??'') ?>" required></div><div class="col-md-4"><label class="form-label" for="phone">Phone</label><input class="form-control" id="phone" name="phone" maxlength="30" value="<?= e($profile['phone']??'') ?>"></div></div><button class="btn btn-primary mt-4 align-self-start">Save changes</button></form></div>
 <div class="col-xl-5"><section class="card p-4 h-100"><h2 class="h5">Account and academic access</h2><dl class="data-list"><dt>Role</dt><dd><?= e(match($profile['role']){'coordinator'=>'Head of Department (HOD)','administrator'=>'Administrator',default=>ucfirst($profile['role'])}) ?></dd><?php if($profile['matric_number']): ?><dt>Matric number</dt><dd><?= e($profile['matric_number']) ?></dd><?php endif; ?><?php if($profile['staff_number']): ?><dt>Staff number</dt><dd><?= e($profile['staff_number']) ?></dd><?php endif; ?><?php if($profile['faculty']): ?><dt>Faculty</dt><dd><?= e($profile['faculty']) ?></dd><?php endif; ?><?php if($profile['department']): ?><dt>Department</dt><dd><?= e($profile['department']) ?></dd><?php endif; ?><?php if($profile['programme']): ?><dt>Programme</dt><dd><?= e($profile['programme']) ?></dd><?php endif; ?><dt>Account status</dt><dd><?= status_badge($profile['account_status']) ?></dd></dl><a class="btn btn-outline-primary" href="<?= e(url('auth/change-password.php')) ?>">Change password</a></section></div>
</div>
<?php if($profile['role']==='student'): ?><section class="card p-4 mt-3"><h2 class="h5">Project and supervision</h2><?php if($project): ?><div class="row g-3"><div class="col-md-6"><span class="info-label">Project title</span><strong class="d-block"><?= e($project['title']) ?></strong></div><div class="col-md-3"><span class="info-label">Supervisor</span><strong class="d-block"><?= e($projectSupervisor?:'Not assigned') ?></strong></div><div class="col-md-3"><span class="info-label">Project status</span><?= status_badge($project['status']) ?></div></div><?php else: ?><div class="empty-state">No project has been registered yet. <a href="<?= e(url('student/project.php')) ?>">Open Project Ideas</a>.</div><?php endif; ?></section><?php endif; ?>
<?php require dirname(__DIR__).'/includes/footer.php'; ?>
