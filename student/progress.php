<?php
require_once dirname(__DIR__).'/includes/auth.php';
$u=require_role('student');
$q=db()->prepare('SELECT p.id,p.title,p.status FROM projects p JOIN project_members pm ON pm.project_id=p.id WHERE pm.student_id=? LIMIT 1');
$q->execute([$u['id']]); $p=$q->fetch(); $rows=[]; $percent=0;
if($p){
    $q=db()->prepare('SELECT * FROM milestones WHERE project_id=? ORDER BY id'); $q->execute([$p['id']]); $rows=$q->fetchAll();
    $documentFor=['proposal'=>'proposal','chapter_1'=>'chapter_1','chapter_2'=>'chapter_2','chapter_3'=>'chapter_3','chapter_4'=>'chapter_4','chapter_5'=>'chapter_5','final_submission'=>'final_project'];
    foreach($rows as &$m){
        if($m['milestone_key']==='idea'){$iq=db()->prepare("SELECT COUNT(*),SUM(review_status='approved') FROM project_ideas WHERE project_id=?");$iq->execute([$p['id']]);[$ideaCount,$approvedIdeas]=$iq->fetch(PDO::FETCH_NUM);if((int)$ideaCount>0)$m['status']=(int)$approvedIdeas>0?'completed':'in_progress';elseif(in_array($p['status'],['proposal_submitted','in_progress','final_submission','under_evaluation','approved','completed','archived'],true))$m['status']='completed';}
        if(isset($documentFor[$m['milestone_key']])){$sq=db()->prepare('SELECT status FROM submissions WHERE project_id=? AND document_type=? ORDER BY submitted_at DESC LIMIT 1');$sq->execute([$p['id'],$documentFor[$m['milestone_key']]]);$s=$sq->fetchColumn();if($s==='approved'||$s==='final')$m['status']='completed';elseif($s==='correction_required')$m['status']='correction_required';elseif($s)$m['status']='in_progress';}
        if($m['milestone_key']==='evaluation'&&in_array($p['status'],['completed','archived'],true))$m['status']='completed';
        if($m['milestone_key']==='final_approval'&&in_array($p['status'],['completed','archived'],true))$m['status']='completed';
    } unset($m);
    $done=count(array_filter($rows,fn($m)=>in_array($m['status'],['completed','waived'],true)));$percent=count($rows)?(int)round($done/count($rows)*100):0;
}
$pageTitle='Project Progress';$crumbTrail=['Progress'];require dirname(__DIR__).'/includes/header.php';
?>
<?php if(!$p): ?><section class="card p-4"><div class="empty-state">Register your project idea to start tracking progress. <a href="<?= e(url('student/project.php')) ?>">Register now</a>.</div></section>
<?php else:
 $done=count(array_filter($rows,'ui_milestone_done'));$pending=count($rows)-$done;$cur=ui_current_milestone($rows);
 $corr=count(array_filter($rows,fn($m)=>$m['status']==='correction_required'));
 $sessName=(string)db()->query('SELECT name FROM academic_sessions WHERE is_current=1 LIMIT 1')->fetchColumn();
 $docMap=['proposal'=>'proposal','chapter_1'=>'chapter_1','chapter_2'=>'chapter_2','chapter_3'=>'chapter_3','chapter_4'=>'chapter_4','chapter_5'=>'chapter_5','final_submission'=>'final_project'];
 $nq=db()->prepare('SELECT title,message,type,created_at FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 5');$nq->execute([$u['id']]);$activity=$nq->fetchAll(); ?>
<section class="dashboard-welcome"><div><h2><?= e($p['title']) ?></h2><p>Current stage: <?= e($cur['label']??'All milestones completed') ?></p></div><?php if($sessName): ?><span class="welcome-session">Session <?= e($sessName) ?></span><?php endif; ?></section>
<div class="row g-3 mb-3">
  <div class="col-sm-6 col-xl-3"><div class="stat-card"><div class="stat-label">Overall progress</div><div class="stat-value text-red"><?= (int)$percent ?>%</div><small class="stat-sub"><?= (int)$done ?> of <?= count($rows) ?> milestones</small></div></div>
  <div class="col-sm-6 col-xl-3"><div class="stat-card"><div class="stat-label">Completed stages</div><div class="stat-value text-green"><?= (int)$done ?></div><small class="stat-sub">Approved milestones</small></div></div>
  <div class="col-sm-6 col-xl-3"><div class="stat-card"><div class="stat-label">Pending stages</div><div class="stat-value"><?= (int)$pending ?></div><small class="stat-sub">Still to complete</small></div></div>
  <div class="col-sm-6 col-xl-3"><div class="stat-card"><div class="stat-label">Corrections required</div><div class="stat-value text-red"><?= (int)$corr ?></div><small class="stat-sub">Awaiting your revision</small></div></div>
</div>
<section class="card p-4 mb-3"><h2 class="section-heading">Visual Project Timeline</h2><?= ui_stepper($rows) ?></section>
<section class="card p-4 mb-3"><h2 class="section-heading">Submission &amp; Approval Dates</h2><div class="table-responsive"><table class="table table-soft"><thead><tr><th>Stage</th><th>Submitted</th><th>Approved</th><th class="text-end">Status</th></tr></thead><tbody>
<?php foreach($rows as $r): $sub=null;$apr=null; if(isset($docMap[$r['milestone_key']])){$sq=db()->prepare('SELECT s.id,s.submitted_at FROM submissions s WHERE s.project_id=? AND s.document_type=? ORDER BY s.submitted_at DESC LIMIT 1');$sq->execute([$p['id'],$docMap[$r['milestone_key']]]);$sub=$sq->fetch();if($sub){$aq=db()->prepare("SELECT MAX(r.reviewed_at) FROM submission_reviews r JOIN submission_versions v ON v.id=r.submission_version_id WHERE v.submission_id=? AND r.decision='approved'");$aq->execute([$sub['id']]);$apr=$aq->fetchColumn();}} ?>
<tr><td class="fw-bold"><?= e($r['label']) ?></td><td><?= e(ui_date($sub['submitted_at']??null)) ?></td><td><?= e(ui_date($apr?:null)) ?></td><td class="text-end"><?= ui_pill($r['status']) ?></td></tr><?php endforeach; ?></tbody></table></div></section>
<section class="card p-4"><h2 class="section-heading">Recent Project Activity</h2>
<?php if(!$activity): ?><div class="empty-state">Project activity will appear here.</div><?php endif; ?>
<div class="activity-list"><?php foreach($activity as $a): ?><div class="activity-row"><span><strong><?= e($a['title']) ?></strong> · <?= e($a['message']) ?> <span class="text-muted">· <?= e(ui_date($a['created_at'],'M j, Y · g:i A')) ?></span></span></div><?php endforeach; ?></div></section>
<?php endif; ?>
<?php require dirname(__DIR__).'/includes/footer.php'; ?>
