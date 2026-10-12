<?php
require_once dirname(__DIR__).'/includes/auth.php';
$u=require_role('supervisor');
$err='';
if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf();
    $projectId=filter_var($_POST['project_id']??null,FILTER_VALIDATE_INT)?:0;
    if(!project_access($projectId,$u)){http_response_code(403);exit('Project not assigned to you.');}
    $ideaId=filter_var($_POST['idea_id']??null,FILTER_VALIDATE_INT)?:0;
    $decision=(string)($_POST['decision']??'');$comment=post_string('comment',5000);
    if(!in_array($decision,['approved','rejected','revision_requested'],true)||$comment==='')$err='Choose a decision and provide feedback for the student.';
    else{
        $q=db()->prepare("SELECT id,submitted_by,title,description FROM project_ideas WHERE id=? AND project_id=? AND review_status IN ('submitted','under_review','revision_requested')");$q->execute([$ideaId,$projectId]);$idea=$q->fetch();
        if(!$idea)$err='That idea does not belong to this assigned project.';
        elseif($decision==='approved'){
            $already=db()->prepare("SELECT id FROM project_ideas WHERE project_id=? AND review_status='approved' AND id<>?");$already->execute([$projectId,$ideaId]);
            if($already->fetchColumn())$err='Another idea is already selected for this project.';
        }
        if(!$err){
            try{
                db()->beginTransaction();
                db()->prepare('UPDATE project_ideas SET review_status=?,reviewed_by=?,reviewer_comment=?,reviewed_at=NOW() WHERE id=? AND project_id=?')->execute([$decision,$u['id'],$comment,$ideaId,$projectId]);
                if($decision==='approved'){
                    db()->prepare("UPDATE project_ideas SET review_status='rejected',reviewed_by=?,reviewer_comment='Another idea was selected for this project.',reviewed_at=NOW() WHERE project_id=? AND id<>? AND review_status<>'rejected'")->execute([$u['id'],$projectId,$ideaId]);
                    db()->prepare('UPDATE projects SET title=?,description=?,status="supervisor_assigned",approved_at=NOW(),registered_at=COALESCE(registered_at,NOW()) WHERE id=?')->execute([$idea['title'],$idea['description'],$projectId]);
                    db()->prepare("UPDATE milestones SET status='completed',completed_at=NOW(),updated_by=? WHERE project_id=? AND milestone_key='idea'")->execute([$u['id'],$projectId]);
                }elseif($decision==='revision_requested'){
                    db()->prepare('UPDATE projects SET status="idea_under_review" WHERE id=?')->execute([$projectId]);
                }else{
                    $remaining=db()->prepare("SELECT COUNT(*) FROM project_ideas WHERE project_id=? AND id<>? AND review_status NOT IN ('rejected')");$remaining->execute([$projectId,$ideaId]);
                    if((int)$remaining->fetchColumn()===0)db()->prepare('UPDATE projects SET status="idea_rejected" WHERE id=?')->execute([$projectId]);
                }
                db()->commit();
                notify_user((int)$idea['submitted_by'],$decision==='approved'?'Project idea selected':'Project idea review updated',$comment,$decision==='approved'?'success':'action','project',$projectId);
                audit('project_idea_'.$decision,'project',$projectId,'Supervisor decision on idea '.$ideaId);
                flash('success',$decision==='approved'?'Project idea selected. The other idea was closed and the student can proceed to proposal submission.':'Idea feedback sent to the student.');
                redirect('supervisor/projects.php');
            }catch(Throwable $error){if(db()->inTransaction())db()->rollBack();error_log($error->getMessage());$err='The idea review could not be saved.';}
        }
    }
}

$q=db()->prepare("SELECT p.*,pm.student_id,st.first_name student_first,st.last_name student_last,st.matric_number,pr.name programme,d.name department
    FROM projects p JOIN project_supervisors ps ON ps.project_id=p.id AND ps.ended_at IS NULL
    JOIN project_members pm ON pm.project_id=p.id JOIN users st ON st.id=pm.student_id
    LEFT JOIN programmes pr ON pr.id=p.programme_id LEFT JOIN departments d ON d.id=p.department_id
    WHERE ps.supervisor_id=? ORDER BY p.updated_at DESC");
$q->execute([$u['id']]);$projects=$q->fetchAll();
$pageTitle='Assigned projects and idea review';$crumbTrail=['Projects','Assigned projects'];require dirname(__DIR__).'/includes/header.php';
?>
<p class="page-intro">Review project ideas and supervise academic work only for projects assigned to you. Select no more than one idea for each project.</p>
<?php if($err): ?><div role="alert" class="alert alert-danger"><?= e($err) ?></div><?php endif; ?>
<?php if(!$projects): ?><section class="card p-4"><div class="empty-state">No projects have been assigned to you yet. Your HOD manages student and supervisor assignments.</div></section><?php endif; ?>
<?php foreach($projects as $project):
    $q=db()->prepare('SELECT * FROM project_ideas WHERE project_id=? ORDER BY id');$q->execute([$project['id']]);$ideas=$q->fetchAll();
    $selected=(bool)array_filter($ideas,fn($idea)=>$idea['review_status']==='approved');
    $q=db()->prepare("SELECT document_type,status FROM submissions WHERE project_id=?");$q->execute([$project['id']]);$submissions=[];foreach($q->fetchAll() as $submission)$submissions[$submission['document_type']]=$submission['status'];
    $stages=[['Project Idea','idea'],['Proposal','proposal'],['Chapter 1','chapter_1'],['Chapter 2','chapter_2'],['Chapter 3','chapter_3'],['Chapter 4','chapter_4'],['Chapter 5','chapter_5'],['Final Submission','final_project']];
    $blocked=false;
?>
<article class="card p-4 mb-3" id="project-<?= (int)$project['id'] ?>"><div class="d-flex flex-wrap justify-content-between gap-3 align-items-start"><div><span class="eyebrow"><?= e($project['reference_no']) ?> · <?= e($project['matric_number']?:'Matric number pending') ?></span><h2 class="h5 mb-1"><?= e(trim(($project['student_first']??'').' '.($project['student_last']??''))?:'Student profile pending') ?></h2><p class="text-muted mb-1"><?= e($project['department']??'') ?><?= !empty($project['programme'])?' · '.e($project['programme']):'' ?></p><p class="fw-semibold mb-0"><?= e($selected?$project['title']:'Project ideas awaiting selection') ?></p></div><div class="text-end"><?= status_badge($project['status']) ?><div class="mt-2"><a class="btn btn-sm btn-outline-primary" href="<?= e(url('supervisor/reviews.php?project_id='.(int)$project['id'])) ?>">Review submissions</a> <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('supervisor/meetings.php')) ?>">Meetings</a></div></div></div>
<section class="mt-4"><h3 class="h6">Academic workflow</h3><div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><?php foreach($stages as [$label,$key]): ?><th><?= e($label) ?></th><?php endforeach; ?></tr></thead><tbody><tr><?php foreach($stages as [$label,$key]):
    if($key==='idea'){$state=$selected?'Approved':($ideas?'Awaiting review':'No idea');$done=$selected;$correction=(bool)array_filter($ideas,fn($idea)=>$idea['review_status']==='revision_requested');}
    else{$status=$submissions[$key]??null;$done=$status==='approved'||$status==='final';$correction=$status==='correction_required';$state=$blocked?'Locked':($done?'Approved':($correction?'Correction required':($status?'Awaiting review':'Pending submission')));}
    if(!$done)$blocked=true;
?><td><?= $done?'<span class="text-success fw-semibold">Approved</span>':($correction?'<span class="text-danger fw-semibold">Correction required</span>':($state==='Locked'?'<span class="text-muted">Locked</span>':'<span class="text-warning-emphasis fw-semibold">'.e($state).'</span>')) ?></td><?php endforeach; ?></tr></tbody></table></div></section>
<section class="mt-4"><div class="d-flex justify-content-between align-items-center mb-2"><h3 class="h6 mb-0">Student project ideas</h3><span class="small text-muted"><?= count($ideas) ?> idea<?= count($ideas)===1?'':'s' ?></span></div>
<?php if(!$ideas): ?><div class="empty-state">No project idea has been submitted.</div><?php else: ?><div class="row g-3"><?php foreach($ideas as $index=>$idea): $isChosen=$idea['review_status']==='approved'; ?><div class="col-xl-6"><section class="card p-3 h-100 <?= $isChosen?'border-success':'' ?>"><div class="d-flex justify-content-between gap-2"><h4 class="h6">Idea <?= $index+1 ?><?= $isChosen?' · Selected':'' ?></h4><?= status_badge($idea['review_status']) ?></div><h5 class="h5 mt-1"><?= e($idea['title']) ?></h5><p><?= nl2br(e($idea['description'])) ?></p><?php if($idea['reviewer_comment']): ?><div class="alert alert-light border"><strong>Previous feedback</strong><p class="mb-0 mt-1"><?= nl2br(e($idea['reviewer_comment'])) ?></p></div><?php endif; ?>
<?php if(!$selected&&in_array($idea['review_status'],['submitted','under_review'],true)): ?><form method="post" class="border-top pt-3"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="project_id" value="<?= (int)$project['id'] ?>"><input type="hidden" name="idea_id" value="<?= (int)$idea['id'] ?>"><div class="mb-3"><label class="form-label" for="idea-feedback-<?= (int)$idea['id'] ?>">Feedback for the student</label><textarea class="form-control" id="idea-feedback-<?= (int)$idea['id'] ?>" name="comment" rows="3" maxlength="5000" required></textarea></div><div class="d-flex flex-wrap gap-2"><button class="btn btn-success" name="decision" value="approved" data-confirm="Select this idea? Any other idea for this project will be closed.">Select this idea</button><button class="btn btn-warning" name="decision" value="revision_requested">Request revision</button><button class="btn btn-outline-danger" name="decision" value="rejected" data-confirm="Reject this idea? The student will see your feedback.">Reject idea</button></div></form><?php elseif($idea['review_status']==='revision_requested'): ?><p class="small text-warning-emphasis mb-0">Waiting for the student to submit the requested revision.</p><?php elseif($isChosen): ?><p class="small text-success fw-semibold mb-0">This is the selected institutional project idea.</p><?php endif; ?></section></div><?php endforeach; ?></div><?php endif; ?></section></article>
<?php endforeach; ?>
<?php require dirname(__DIR__).'/includes/footer.php'; ?>
