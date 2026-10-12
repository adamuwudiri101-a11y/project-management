<?php
require_once dirname(__DIR__).'/includes/auth.php';
require_once dirname(__DIR__).'/includes/similarity.php';
$u=require_role('coordinator','administrator');
$isAdmin=$u['role']==='administrator';
$err='';

if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf();
    $action=(string)($_POST['action']??'');
    $pid=filter_var($_POST['project_id']??null,FILTER_VALIDATE_INT)?:0;
    $projectQuery=db()->prepare('SELECT * FROM projects WHERE id=?'.(!$isAdmin?' AND department_id=?':''));
    $projectQuery->execute(!$isAdmin?[$pid,(int)$u['department_id']]:[$pid]);
    $project=$projectQuery->fetch();
    if(!$project){$err='Project record was not found in your access scope.';}
    elseif(in_array($action,['finalize','archive'],true)&&!$isAdmin){http_response_code(403);exit('Only the system administrator can complete or archive a project.');}
    elseif($action==='assign_supervisor'){
        $supervisorId=filter_var($_POST['supervisor_id']??null,FILTER_VALIDATE_INT)?:0;
        $q=db()->prepare("SELECT id FROM users WHERE id=? AND role_id=(SELECT id FROM roles WHERE name='supervisor') AND account_status='active' AND department_id=?");
        $q->execute([$supervisorId,$project['department_id']]);
        if(!$q->fetchColumn()){$err='Select an active supervisor from the project department.';}
        else{
            try{
                db()->beginTransaction();
                db()->prepare('UPDATE project_supervisors SET ended_at=NOW() WHERE project_id=? AND ended_at IS NULL')->execute([$pid]);
                db()->prepare('INSERT INTO project_supervisors(project_id,supervisor_id,assigned_by) VALUES(?,?,?)')->execute([$pid,$supervisorId,$u['id']]);
                $members=db()->prepare('SELECT student_id FROM project_members WHERE project_id=?');$members->execute([$pid]);
                $memberIds=$members->fetchAll(PDO::FETCH_COLUMN);
                foreach($memberIds as $studentId){
                    db()->prepare('UPDATE student_supervisors SET ended_at=NOW() WHERE student_id=? AND ended_at IS NULL')->execute([$studentId]);
                    db()->prepare('INSERT INTO student_supervisors(student_id,supervisor_id,assigned_by) VALUES(?,?,?)')->execute([$studentId,$supervisorId,$u['id']]);
                }
                $ideas=db()->prepare("SELECT COUNT(*) FROM project_ideas WHERE project_id=? AND review_status='approved'");$ideas->execute([$pid]);
                $ideaApproved=(int)$ideas->fetchColumn()>0;
                $started=in_array($project['status'],['proposal_submitted','in_progress','correction_required','final_submission','under_evaluation','approved','completed','archived'],true);
                $newStatus=$started?$project['status']:($ideaApproved?'supervisor_assigned':'idea_under_review');
                db()->prepare('UPDATE projects SET status=? WHERE id=?')->execute([$newStatus,$pid]);
                db()->commit();
                foreach($memberIds as $studentId) notify_user((int)$studentId,'Supervisor assigned','A supervisor has been assigned to your project.','success','project',$pid);
                notify_user($supervisorId,'New project assigned','A project has been assigned to you for supervision and project idea review.','action','project',$pid);
                audit('supervisor_assigned','project',$pid,'Supervisor '.$supervisorId.' assigned by '.$u['role']);
                flash('success','Supervisor assignment saved. The supervisor can now review the project ideas.');
                redirect($isAdmin?'admin/projects.php':'coordinator/projects.php');
            }catch(Throwable $error){if(db()->inTransaction())db()->rollBack();error_log($error->getMessage());$err='The supervisor assignment could not be saved.';}
        }
    }elseif($action==='similarity'){
        $q=db()->prepare("SELECT id,title,description FROM projects WHERE id<>? AND department_id=? AND status IN ('supervisor_pending','supervisor_assigned','proposal_submitted','in_progress','correction_required','final_submission','under_evaluation','approved','completed','archived')");
        $q->execute([$pid,$project['department_id']]);$matches=[];
        foreach($q->fetchAll() as $other){
            $score=similarity_score($project['title'].' '.$project['description'],$other['title'].' '.$other['description']);
            db()->prepare('INSERT INTO project_similarity_checks(project_id,compared_project_id,checked_by,algorithm_version,similarity_score) VALUES(?,?,?,"token-jaccard-v1",?)')->execute([$pid,$other['id'],$u['id'],$score]);
            if($score>=20)$matches[]=$other['title'].' ('.number_format($score,1).'%)';
        }
        audit('similarity_check','project',$pid,'Compared this project with other registered projects');
        flash('info',count($matches)?'Potential matches: '.implode('; ',array_slice($matches,0,6)):'No potential matches at or above 20% were found. Similarity assists review and does not make the decision.');
        redirect($isAdmin?'admin/projects.php':'coordinator/projects.php');
    }elseif($action==='finalize'||$action==='archive'){
        $target=$action==='archive'?'archived':'completed';
        if($action==='archive'&&$project['status']!=='completed')$err='Only completed projects can be archived.';
        elseif($action==='finalize'&&$project['status']!=='under_evaluation')$err='Project must be under evaluation before final completion.';
        else{
            $q=db()->prepare("SELECT COUNT(*),SUM(status='submitted') FROM project_examiners WHERE project_id=?");$q->execute([$pid]);[$assigned,$submitted]=$q->fetch(PDO::FETCH_NUM);
            $q=db()->prepare("SELECT COUNT(*) FROM submissions WHERE project_id=? AND document_type='final_project' AND status='approved'");$q->execute([$pid]);$approvedFinal=(int)$q->fetchColumn();
            if($action==='finalize'&&((int)$assigned===0||(int)$submitted!==(int)$assigned||$approvedFinal===0))$err='Completion requires an approved final project and submitted evaluations from every assigned examiner.';
            else{
                db()->prepare('UPDATE projects SET status=?,completed_at=IF(?="completed",NOW(),completed_at),archived_at=IF(?="archived",NOW(),archived_at) WHERE id=?')->execute([$target,$target,$target,$pid]);
                if($target==='completed')db()->prepare('UPDATE milestones SET status="completed",completed_at=NOW(),updated_by=? WHERE project_id=? AND milestone_key="final_approval"')->execute([$u['id'],$pid]);
                $members=db()->prepare('SELECT student_id FROM project_members WHERE project_id=?');$members->execute([$pid]);foreach($members->fetchAll(PDO::FETCH_COLUMN) as $studentId)notify_user((int)$studentId,$target==='completed'?'Project completed':'Project archived','The system administrator updated the project record.','success','project',$pid);
                audit('project_'.$target,'project',$pid,'Administrator marked project '.$target);flash('success',$target==='completed'?'Project completion approved.':'Project archived.');redirect('admin/projects.php');
            }
        }
    }else{$err='This action is not available from project monitoring. Academic idea and submission decisions belong to the assigned supervisor.';}
}

$scopeSql=$isAdmin?'':' WHERE p.department_id=?';
$q=db()->prepare("SELECT p.*,d.name department,ac.name session_name,
    (SELECT CONCAT_WS(' ',u.first_name,u.last_name) FROM project_supervisors ps JOIN users u ON u.id=ps.supervisor_id WHERE ps.project_id=p.id AND ps.ended_at IS NULL ORDER BY ps.assigned_at DESC LIMIT 1) supervisor_name,
    (SELECT CONCAT_WS(' ',u.first_name,u.last_name) FROM project_members pm JOIN users u ON u.id=pm.student_id WHERE pm.project_id=p.id ORDER BY pm.member_role='leader' DESC LIMIT 1) student_name,
    (SELECT COUNT(*) FROM project_ideas i WHERE i.project_id=p.id) idea_count,
    (SELECT GROUP_CONCAT(DISTINCT i.review_status ORDER BY i.id SEPARATOR ', ') FROM project_ideas i WHERE i.project_id=p.id) idea_statuses
    FROM projects p JOIN departments d ON d.id=p.department_id JOIN academic_sessions ac ON ac.id=p.academic_session_id{$scopeSql} ORDER BY p.created_at DESC");
$q->execute($isAdmin?[]:[(int)$u['department_id']]);$projects=$q->fetchAll();
$q=db()->prepare("SELECT id,first_name,last_name,staff_number FROM users WHERE role_id=(SELECT id FROM roles WHERE name='supervisor') AND account_status='active'".(!$isAdmin?' AND department_id=?':'').' ORDER BY last_name,first_name');$q->execute($isAdmin?[]:[(int)$u['department_id']]);$supervisors=$q->fetchAll();
 $progressSql="SELECT m.project_id,SUM(m.status IN ('completed','waived')) completed,COUNT(*) total FROM milestones m JOIN projects p ON p.id=m.project_id".(!$isAdmin?' WHERE p.department_id=?':'').' GROUP BY m.project_id';$progressQuery=db()->prepare($progressSql);$progressQuery->execute($isAdmin?[]:[(int)$u['department_id']]);$progressMap=[];foreach($progressQuery->fetchAll() as $progressRow){$progressMap[(int)$progressRow['project_id']]=(int)$progressRow['total']?(int)round(100*(int)$progressRow['completed']/(int)$progressRow['total']):0;}
$pageTitle=$isAdmin?'Project management':'Project monitoring';$crumbTrail=$isAdmin?['Projects']:['Department','Project monitoring'];require dirname(__DIR__).'/includes/header.php';
?>
<p class="page-intro"><?= $isAdmin?'University-wide project registry, supervisor assignments, and administrative completion.':'Department project monitoring, similarity evidence, supervisor assignments, and progress. Academic decisions are made by the assigned supervisor.' ?></p>
<?php if($err): ?><div role="alert" class="alert alert-danger"><?= e($err) ?></div><?php endif; ?>
<section class="card filter-bar mb-3"><div class="filter-form"><label class="search-box"><?= ui_icon('search') ?><input type="search" class="form-control" placeholder="Search project titles or abstracts..." aria-label="Search projects" data-live-filter="#projectMatrix tbody tr"></label><div class="filter-selects"><select class="form-select" aria-label="Filter by status" data-column-filter="5" data-filter-target="#projectMatrix"><option value="">All Statuses</option><?php foreach(array_unique(array_map(fn($p)=>ui_label($p['status']),$projects)) as $lbl): ?><option><?= e($lbl) ?></option><?php endforeach; ?></select></div></div></section>
<section class="card p-4"><div class="section-row mb-3"><h2 class="section-heading mb-0"><?= $isAdmin?'Undergraduate Project Tracking Matrix':'Department Project Monitoring' ?></h2><span class="text-muted small" id="historyCount">Showing <?= count($projects) ?> of <?= count($projects) ?> entries</span></div><p class="text-muted small"><?= $isAdmin?'University-wide project registry and workflow administration.':'Monitor project ideas, similarity evidence, supervisor assignments, and progress. Academic idea and document decisions belong to the assigned supervisor.' ?></p>
<div class="table-responsive"><table class="table table-soft align-middle" id="projectMatrix"><thead><tr><th>Project title</th><th>Student</th><th>Supervisor</th><th>Department</th><th>Progress</th><th>Status / idea review</th><th class="text-end">Actions</th></tr></thead><tbody>
<?php foreach($projects as $p): $pct=$progressMap[(int)$p['id']]??0; ?><tr><td class="project-cell"><strong><?= e($p['title']) ?></strong><br><small class="text-muted"><?= e($p['reference_no']) ?> · <?= e($p['session_name']) ?></small></td><td><?= e($p['student_name']?:'Student profile pending') ?></td><td><?= e($p['supervisor_name']?:'Pending') ?></td><td><?= e($p['department']) ?></td><td class="progress-cell"><div class="progress" role="progressbar" aria-label="Project progress" aria-valuenow="<?= $pct ?>" aria-valuemin="0" aria-valuemax="100"><div class="progress-bar" style="width:<?= $pct ?>%"></div></div><small class="text-red fw-bold"><?= $pct ?>%</small></td><td><?= status_badge($p['status']) ?><br><?php if((int)$p['idea_count']): ?><small class="text-muted"><?= (int)$p['idea_count'] ?> idea<?= (int)$p['idea_count']===1?'':'s' ?> · <?= e($p['idea_statuses']) ?></small><?php else: ?><small class="text-muted">No idea record</small><?php endif; ?></td><td class="text-end"><details class="row-actions"><summary class="view-link">Manage</summary><div class="row-actions-body"><form method="post" class="d-inline"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="project_id" value="<?= (int)$p['id'] ?>"><input type="hidden" name="action" value="similarity"><button class="btn btn-sm btn-outline-primary">Similarity check</button></form><?php if(in_array($p['status'],['idea_submitted','idea_under_review','idea_rejected','supervisor_pending','supervisor_assigned'],true)): ?><form method="post" class="mt-2"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="project_id" value="<?= (int)$p['id'] ?>"><input type="hidden" name="action" value="assign_supervisor"><label class="visually-hidden" for="supervisor-<?= (int)$p['id'] ?>">Assign supervisor for <?= e($p['reference_no']) ?></label><select class="form-select form-select-sm mb-1" name="supervisor_id" id="supervisor-<?= (int)$p['id'] ?>" required><option value="">Assign or reassign supervisor</option><?php foreach($supervisors as $s): ?><option value="<?= (int)$s['id'] ?>"><?= e(trim(($s['first_name']??'').' '.($s['last_name']??'')).' · '.($s['staff_number']??'')) ?></option><?php endforeach; ?></select><button class="btn btn-sm btn-outline-primary">Save assignment</button></form><?php endif; ?><?php if($isAdmin&&$p['status']==='under_evaluation'): ?><form method="post" class="mt-2" data-confirm="Complete this project after checking the approved final document and all examiner evaluations?"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="project_id" value="<?= (int)$p['id'] ?>"><input type="hidden" name="action" value="finalize"><button class="btn btn-sm btn-success">Final approval</button></form><?php endif; ?><?php if($isAdmin&&$p['status']==='completed'): ?><form method="post" class="mt-2" data-confirm="Archive this completed project record?"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="project_id" value="<?= (int)$p['id'] ?>"><input type="hidden" name="action" value="archive"><button class="btn btn-sm btn-outline-secondary">Archive</button></form><?php endif; ?></div></details></td></tr><?php endforeach; ?>
<?php if(!$projects): ?><tr><td colspan="7"><div class="empty-state">No projects have been registered in this scope yet.</div></td></tr><?php endif; ?></tbody></table></div></section>
<?php require dirname(__DIR__).'/includes/footer.php'; ?>
