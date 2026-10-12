<?php
require_once dirname(__DIR__).'/includes/auth.php';
require_role('administrator');
$pageTitle='Roles & Permissions';$crumbTrail=['Admin','Settings','Roles & Permissions'];$crumbTrail=['System settings','Roles & permissions'];require dirname(__DIR__).'/includes/header.php';
$roles=['administrator'=>'Admin','dean'=>'Dean','coordinator'=>'HOD','supervisor'=>'Supervisor','examiner'=>'Examiner','student'=>'Student'];
$rows=[
 ['section'=>'Account access'],
 ['View own profile','✓','✓','✓','✓','✓','✓'],
 ['Edit own name and contact details','✓','✓','✓','✓','✓','✓'],
 ['Change own password','✓','✓','✓','✓','✓','✓'],
 ['Reset another account password','✓','—','—','—','—','—'],
 ['Manage all system users','✓','—','—','—','—','—'],
 ['Register students and create supervisors','✓','—','Department','—','—','—'],
 ['View faculty records','All faculties','Assigned faculty','Own faculty record','—','—','Own profile'],
 ['View department records','All departments','Faculty departments','Own department','Assigned projects','Assigned projects','Own records'],
 ['Assign or reassign supervisors','✓','—','Own department','—','—','—'],
 ['Manage deadlines','—','—','Own department','—','—','—'],
 ['Assign examiners and configure evaluation criteria','—','—','Own department','—','—','—'],
 ['Run similarity checks','System-wide','—','Own department','—','—','—'],
 ['Record similarity assessment','—','—','Own department','—','—','—'],
 ['section'=>'Project lifecycle'],
 ['Submit project ideas or documents','—','—','—','—','—','Own project'],
 ['Select or request revision on project ideas','—','—','—','Assigned projects','—','—'],
 ['Review proposal and chapters','—','—','—','Assigned projects','—','—'],
 ['Request corrections or approve submissions','—','—','—','Assigned projects','—','—'],
 ['Manage supervision meetings','—','—','—','Assigned students','—','View own'],
 ['Submit supervisor evaluation','—','—','—','Assigned projects','—','—'],
 ['Evaluate assigned projects','—','—','—','—','Assigned projects','—'],
 ['Approve project completion and archive','System-wide','—','—','—','—','—'],
 ['section'=>'System administration'],
 ['Configure portal and academic sessions','✓','—','—','—','—','—'],
 ['View preloaded faculties, departments, programmes','✓','—','—','—','—','—'],
 ['View audit logs','✓','—','—','—','—','—'],
 ['View personal notifications','✓','✓','✓','✓','✓','✓'],
];
?>
<p class="page-intro">This reference describes the application’s current role boundaries. It is read-only; the server enforces access on every protected page and action.</p>
<nav class="d-flex flex-wrap gap-2 mb-3" aria-label="Settings sections"><a class="btn btn-sm btn-outline-secondary" href="<?= e(url('admin/settings.php')) ?>">General &amp; academic settings</a><span class="btn btn-sm btn-primary" aria-current="page">Roles &amp; permissions</span></nav>
<div class="alert alert-info" role="note"><strong>Scope matters.</strong> Dean access is limited to the assigned faculty; HOD access is limited to the assigned department; supervisor and examiner access is limited to assigned projects; students can access their own records.</div>
<section class="card p-3 p-lg-4"><div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3"><div><h2 class="section-heading mb-1">Permission reference</h2><p class="text-muted small mb-0">“Assigned” means the user has a current project/student assignment in the database.</p></div><span class="badge text-bg-secondary">6 roles</span></div><div class="table-responsive"><table class="table table-hover align-middle permission-matrix"><thead><tr><th scope="col">Task</th><?php foreach($roles as $role): ?><th scope="col"><?= e($role) ?></th><?php endforeach; ?></tr></thead><tbody><?php foreach($rows as $row): if(isset($row['section'])): ?><tr class="table-light"><th colspan="7" scope="rowgroup"><?= e($row['section']) ?></th></tr><?php else: ?><tr><th scope="row"><?= e($row[0]) ?></th><?php foreach(array_slice($row,1) as $value): ?><td><?= $value==='✓'?'<span class="text-success fw-bold">Allowed</span>':($value==='—'?'<span class="text-muted">—</span>':e($value)) ?></td><?php endforeach; ?></tr><?php endif; endforeach; ?></tbody></table></div></section>
<?php require dirname(__DIR__).'/includes/footer.php'; ?>
