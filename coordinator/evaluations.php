<?php
require_once dirname(__DIR__).'/includes/auth.php';
$u=require_role('coordinator');
$departmentId=(int)$u['department_id'];
$db=db();$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf();
    try{
        if(($_POST['action']??'')==='assign'){
            $projectId=filter_input(INPUT_POST,'project_id',FILTER_VALIDATE_INT)?:0;
            $examinerId=filter_input(INPUT_POST,'examiner_id',FILTER_VALIDATE_INT)?:0;
            $q=$db->prepare('SELECT id,title,status FROM projects WHERE id=? AND department_id=?');$q->execute([$projectId,$departmentId]);$project=$q->fetch();
            if(!$project)throw new DomainException('Select a project in your department.');
            if(!in_array($project['status'],['final_submission','under_evaluation'],true))throw new DomainException('Examiner assignment opens after the supervisor approves the final project.');
            $q=$db->prepare('SELECT COUNT(*) FROM submissions WHERE project_id=? AND document_type="final_project" AND status="approved"');$q->execute([$projectId]);
            if(!(int)$q->fetchColumn())throw new DomainException('The final project must be approved before examiner assignment.');
            $q=$db->prepare('SELECT u.id FROM users u JOIN examiners e ON e.user_id=u.id WHERE u.id=? AND u.department_id=? AND u.account_status="active" AND e.is_available=1');$q->execute([$examinerId,$departmentId]);
            if(!$q->fetchColumn())throw new DomainException('Select an active examiner from your department.');
            $db->prepare('INSERT INTO project_examiners(project_id,examiner_id,assigned_by,evaluation_due_at) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE assigned_by=VALUES(assigned_by),evaluation_due_at=VALUES(evaluation_due_at),status="assigned"')->execute([$projectId,$examinerId,$u['id'],($_POST['due_at']??'')?:null]);
            $db->prepare('UPDATE projects SET status="under_evaluation" WHERE id=? AND department_id=?')->execute([$projectId,$departmentId]);
            notify_user($examinerId,'Project assigned for evaluation','A project in your department has been assigned to you for evaluation.','action','project',$projectId);
            audit('examiner_assigned','project',$projectId,'HOD assigned a department examiner');
            flash('success','Examiner assigned.');redirect('coordinator/evaluations.php');
        }
        if(($_POST['action']??'')==='criterion'){
            $name=post_string('name',120);$description=post_string('description',500);$max=(float)($_POST['max_score']??0);$weight=(float)($_POST['weight']??0);$role=$_POST['evaluator_role']??'examiner';$session=filter_input(INPUT_POST,'academic_session_id',FILTER_VALIDATE_INT)?:null;
            if($name===''||$max<=0||$weight<=0||!in_array($role,['examiner','supervisor'],true))throw new DomainException('Enter a criterion name, positive maximum and weight, and valid evaluator.');
            $db->prepare('INSERT INTO evaluation_criteria(academic_session_id,department_id,name,description,max_score,weight,evaluator_role) VALUES(?,?,?,?,?,?,?)')->execute([$session,$departmentId,$name,$description?:null,$max,$weight,$role]);
            audit('evaluation_criterion_created','evaluation_criterion',(int)$db->lastInsertId(),'HOD created department evaluation criterion');
            flash('success','Evaluation criterion added for your department.');redirect('coordinator/evaluations.php');
        }
        throw new DomainException('Unknown action.');
    }catch(DomainException $e){$error=$e->getMessage();}catch(Throwable $e){error_log($e->getMessage());$error='The evaluation update could not be saved.';}
}
$q=$db->prepare('SELECT id,title,status FROM projects WHERE department_id=? AND status<>"archived" ORDER BY title');$q->execute([$departmentId]);$projects=$q->fetchAll();
$q=$db->prepare('SELECT u.id,CONCAT(u.first_name," ",u.last_name) name FROM users u JOIN examiners e ON e.user_id=u.id WHERE u.department_id=? AND u.account_status="active" AND e.is_available=1 ORDER BY u.last_name,u.first_name');$q->execute([$departmentId]);$examiners=$q->fetchAll();
$q=$db->prepare('SELECT pe.project_id,pe.examiner_id,pe.assigned_at,pe.evaluation_due_at,pe.status,p.title,CONCAT(u.first_name," ",u.last_name) examiner FROM project_examiners pe JOIN projects p ON p.id=pe.project_id JOIN users u ON u.id=pe.examiner_id WHERE p.department_id=? ORDER BY pe.assigned_at DESC');$q->execute([$departmentId]);$assignments=$q->fetchAll();
$criteria=$db->prepare('SELECT c.*,s.name session_name FROM evaluation_criteria c LEFT JOIN academic_sessions s ON s.id=c.academic_session_id WHERE c.department_id=? ORDER BY c.evaluator_role,c.sort_order,c.id');$criteria->execute([$departmentId]);$criteria=$criteria->fetchAll();
$sessions=$db->query('SELECT id,name FROM academic_sessions ORDER BY starts_on DESC')->fetchAll();$pageTitle='Evaluation Management';$crumbTrail=['Evaluations'];require dirname(__DIR__).'/includes/header.php';
?>
<?php if($error): ?><div role="alert" class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
<?php $nPending=count(array_filter($assignments,fn($a)=>$a['status']!=='completed'));$nDone=count($assignments)-$nPending; ?>
<div class="row g-3 mb-3"><div class="col-md-4"><div class="stat-card"><div class="stat-label">Examiner assignments</div><div class="stat-value"><?= count($assignments) ?></div><small class="stat-sub">In your department</small></div></div><div class="col-md-4"><div class="stat-card"><div class="stat-label">Pending evaluations</div><div class="stat-value text-gold"><?= (int)$nPending ?></div><small class="stat-sub">Awaiting examiner results</small></div></div><div class="col-md-4"><div class="stat-card"><div class="stat-label">Criteria configured</div><div class="stat-value text-blue"><?= count($criteria) ?></div><small class="stat-sub">Department scoring rubric</small></div></div></div>
<div class="row g-3">
 <div class="col-lg-6"><form method="post" class="card p-4 h-100"><h2 class="section-heading">Assign examiner</h2><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="assign"><label class="form-label">Department project</label><select class="form-select mb-3" name="project_id" required><option value="">Choose project</option><?php foreach($projects as $p): ?><option value="<?= (int)$p['id'] ?>"><?= e($p['title']) ?> — <?= e(ucwords(str_replace('_',' ',$p['status']))) ?></option><?php endforeach; ?></select><label class="form-label">Examiner from this department</label><select class="form-select mb-3" name="examiner_id" required><option value="">Choose examiner</option><?php foreach($examiners as $x): ?><option value="<?= (int)$x['id'] ?>"><?= e($x['name']) ?></option><?php endforeach; ?></select><label class="form-label">Evaluation deadline (optional)</label><input class="form-control mb-3" type="datetime-local" name="due_at"><button class="btn btn-primary">Assign examiner</button></form></div>
 <div class="col-lg-6"><form method="post" class="card p-4 h-100"><h2 class="section-heading">Add evaluation criterion</h2><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="criterion"><label class="form-label">Criterion name</label><input class="form-control mb-2" name="name" maxlength="120" required><label class="form-label">Description</label><textarea class="form-control mb-2" name="description" maxlength="500"></textarea><div class="row"><div class="col"><label class="form-label">Maximum score</label><input class="form-control" type="number" min="0.01" step="0.01" name="max_score" required></div><div class="col"><label class="form-label">Weight</label><input class="form-control" type="number" min="0.001" step="0.001" name="weight" value="1" required></div></div><div class="row mt-2"><div class="col"><label class="form-label">Evaluator</label><select class="form-select" name="evaluator_role"><option value="examiner">Examiner</option><option value="supervisor">Supervisor</option></select></div><div class="col"><label class="form-label">Academic session</label><select class="form-select" name="academic_session_id"><option value="">All sessions</option><?php foreach($sessions as $s): ?><option value="<?= (int)$s['id'] ?>"><?= e($s['name']) ?></option><?php endforeach; ?></select></div></div><button class="btn btn-outline-primary mt-3">Add criterion</button></form></div>
</div>
<section class="card mt-4"><div class="card-body"><h2 class="section-heading">Examiner assignments</h2><div class="table-responsive"><table class="table table-soft"><thead><tr><th>Project</th><th>Examiner</th><th>Assigned</th><th>Due</th><th>Status</th></tr></thead><tbody><?php foreach($assignments as $a): ?><tr><td><?= e($a['title']) ?></td><td><?= e($a['examiner']) ?></td><td class="text-muted"><?= e(ui_date($a['assigned_at'])) ?></td><td class="text-muted"><?= e(ui_date($a['evaluation_due_at']??null)) ?></td><td><?= status_badge($a['status']) ?></td></tr><?php endforeach; ?><?php if(!$assignments): ?><tr><td colspan="5"><div class="empty-state">No examiner assignments yet.</div></td></tr><?php endif; ?></tbody></table></div></div></section>
<section class="card mt-4"><div class="card-body"><h2 class="section-heading">Department criteria</h2><div class="table-responsive"><table class="table table-soft"><thead><tr><th>Criterion</th><th>Evaluator</th><th>Maximum</th><th>Weight</th><th>Session</th></tr></thead><tbody><?php foreach($criteria as $c): ?><tr><td><?= e($c['name']) ?></td><td><?= e(ucfirst($c['evaluator_role'])) ?></td><td><?= e((string)$c['max_score']) ?></td><td><?= e((string)$c['weight']) ?></td><td><?= e($c['session_name']??'All sessions') ?></td></tr><?php endforeach; ?><?php if(!$criteria): ?><tr><td colspan="5"><div class="empty-state">No department criteria configured.</div></td></tr><?php endif; ?></tbody></table></div></div></section>
<?php require dirname(__DIR__).'/includes/footer.php'; ?>
