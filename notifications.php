<?php
require_once __DIR__.'/includes/auth.php';
$u=require_login();
if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf();
    if(($_POST['action']??'')==='all')db()->prepare('UPDATE notifications SET read_at=NOW() WHERE user_id=? AND read_at IS NULL')->execute([$u['id']]);
    else{$id=filter_var($_POST['id']??null,FILTER_VALIDATE_INT)?:0;db()->prepare('UPDATE notifications SET read_at=NOW() WHERE id=? AND user_id=?')->execute([$id,$u['id']]);}
    flash('success','Notification status updated.');redirect('notifications.php');
}

function notification_destination(array $notification,array $user): ?array
{
    $type=(string)($notification['entity_type']??'');$id=(int)($notification['entity_id']??0);if(!$id)return null;
    if($type==='project'){
        if(!project_access($id,$user))return null;
        return match($user['role']){
            'student'=>['student/project.php','Open project'],
            'supervisor'=>['supervisor/projects.php#project-'.$id,'Open assigned project'],
            'coordinator'=>['coordinator/projects.php','Open project monitoring'],
            'administrator'=>['admin/projects.php','Open project record'],
            'examiner'=>['examiner/projects.php','Open assigned project'],
            default=>null,
        };
    }
    if($type==='submission'){
        $q=db()->prepare('SELECT project_id,student_id FROM submissions WHERE id=?');$q->execute([$id]);$submission=$q->fetch();
        if(!$submission||!project_access((int)$submission['project_id'],$user))return null;
        $versionQ=db()->prepare('SELECT id FROM submission_versions WHERE submission_id=? ORDER BY version_number DESC LIMIT 1');$versionQ->execute([$id]);$versionId=(int)$versionQ->fetchColumn();
        return match($user['role']){
            'student'=>$versionId?['student/submission-details.php?version='.$versionId,'View submission']:null,
            'supervisor'=>['supervisor/reviews.php?project_id='.(int)$submission['project_id'],'Review submission'],
            'coordinator'=>['coordinator/projects.php','Open project monitoring'],
            'administrator'=>['admin/submissions.php','View submissions'],
            'examiner'=>['examiner/projects.php','Open assigned project'],
            default=>null,
        };
    }
    if($type==='similarity_check'&&$user['role']==='coordinator')return ['coordinator/similarity.php','Review similarity assessment'];
    if($type==='user')return match($user['role']){
        'administrator'=>['admin/users.php','Open user management'],
        'coordinator'=>['coordinator/students.php','Open department people'],
        'supervisor'=>['supervisor/students.php','Open assigned students'],
        'student'=>$id===(int)$user['id']?['student/profile.php','Open my profile']:null,
        default=>null,
    };
    return null;
}

$q=db()->prepare('SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC,id DESC LIMIT 100');$q->execute([$u['id']]);$rows=$q->fetchAll();
$unreadCount=count(array_filter($rows,fn($n)=>empty($n['read_at'])));
$pageTitle='Notifications';$crumbTrail=['Notifications'];require __DIR__.'/includes/header.php';
?>
<div class="section-row mb-3"><div><p class="page-intro mb-1">Project, supervision, and evaluation updates for your account.</p><span class="small text-muted"><?= $unreadCount ?> unread · <?= count($rows) ?> recent notification<?= count($rows)===1?'':'s' ?></span></div><?php if($unreadCount): ?><form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="all"><button class="btn btn-sm btn-outline-primary">Mark all as read</button></form><?php endif; ?></div>
<div class="mb-3"><label class="form-label" for="notificationSearch">Search notifications</label><input class="form-control" id="notificationSearch" type="search" placeholder="Search updates" data-live-filter="#notificationList [data-filter-text]"></div>
<section class="card p-3" id="notificationList"><?php if(!$rows): ?><div class="empty-state m-2">You’re all caught up. Project updates will appear here when something needs your attention.</div><?php endif; ?>
<?php foreach($rows as $r): $destination=notification_destination($r,$u); ?><article class="notice-row <?= $r['read_at']?'is-read':'is-unread' ?>" data-filter-text="<?= e(mb_strtolower($r['title'].' '.$r['message'].' '.$r['type'])) ?>"><span class="notice-icon is-<?= e($r['type']) ?>"><?= ui_icon(['success'=>'check','warning'=>'alert','action'=>'upload','info'=>'bell'][$r['type']]??'bell',18) ?></span><div class="notice-body"><div class="d-flex flex-wrap gap-2 align-items-center"><strong><?= e($r['title']) ?></strong><?= $r['read_at']?'<span class="badge text-bg-light">Read</span>':'<span class="badge text-bg-primary">Unread</span>' ?><span class="badge text-bg-secondary"><?= e(ucfirst($r['type'])) ?></span></div><p><?= e($r['message']) ?></p><small><?= e(ui_date($r['created_at'],'M j, Y · g:i A')) ?></small><?php if($destination): ?><div class="mt-2"><a class="btn btn-sm btn-outline-primary" href="<?= e(url($destination[0])) ?>"><?= e($destination[1]) ?></a></div><?php endif; ?></div><?php if(!$r['read_at']): ?><form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn btn-sm btn-outline-secondary">Mark as read</button></form><?php endif; ?></article><?php endforeach; ?></section>
<?php require __DIR__.'/includes/footer.php'; ?>
