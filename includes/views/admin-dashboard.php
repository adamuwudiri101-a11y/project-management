<?php
/* Administrator dashboard presentation. All metrics are read from the live database. */
$countByLabel=array_column($cards,1,0);
$tiles=[
 ['Total students',(int)($countByLabel['Students']??0),'Registered final year students','users',''],
 ['Total supervisors',(int)($countByLabel['Active supervisors']??0),'Active academic staff','user','is-blue'],
 ['Active projects',(int)($countByLabel['Active projects']??0),'Projects not yet completed','file','is-gold'],
 ['Pending academic reviews',(int)($countByLabel['Pending academic reviews']??0),'Ideas and documents awaiting review','clock','is-red'],
];
$allUsers=(int)($countByLabel['Total users']??0);
$completedProjects=(int)($countByLabel['Completed projects']??0);
$q=db()->prepare("SELECT p.id,p.title,p.status,p.updated_at,st.first_name,st.last_name,st.matric_number FROM projects p LEFT JOIN project_members pm ON pm.project_id=p.id LEFT JOIN users st ON st.id=pm.student_id WHERE p.status IN ('correction_required','supervisor_pending','idea_submitted','idea_under_review') ORDER BY p.updated_at ASC LIMIT 5");$q->execute();$urgent=$q->fetchAll();
$dist=[
 ['Not started',['idea_submitted','idea_under_review','idea_approved','idea_rejected','supervisor_pending','correction_required'],'is-grey'],
 ['In progress',['supervisor_assigned','proposal_submitted','in_progress'],'is-gold'],
 ['Final writing and evaluation',['final_submission','under_evaluation'],'is-red'],
 ['Completed and archived',['approved','completed','archived'],'is-green'],
];
$distribution=[];$distributionTotal=0;
foreach($dist as [$label,$statuses,$tone]){$marks=implode(',',array_fill(0,count($statuses),'?'));$q=db()->prepare("SELECT COUNT(*) FROM projects WHERE status IN ($marks)");$q->execute($statuses);$count=(int)$q->fetchColumn();$distribution[]=['label'=>$label,'tone'=>$tone,'count'=>$count];$distributionTotal+=$count;}
$roleCounts=[];foreach(['coordinator'=>'HODs','dean'=>'Deans','examiner'=>'Examiners'] as $roleName=>$label){$q=db()->prepare('SELECT COUNT(*) FROM users u JOIN roles r ON r.id=u.role_id WHERE r.name=? AND u.account_status="active"');$q->execute([$roleName]);$roleCounts[$label]=(int)$q->fetchColumn();}
$iconFor=function(string $action):string{$action=strtolower($action);if(str_contains($action,'approv')||str_contains($action,'complete'))return 'check';if(str_contains($action,'correct')||str_contains($action,'reject')||str_contains($action,'fail'))return 'alert';if(str_contains($action,'upload')||str_contains($action,'submit'))return 'upload';if(str_contains($action,'login')||str_contains($action,'user')||str_contains($action,'assign'))return 'user';return 'chat';};
$ago=function(string $timestamp):string{$seconds=max(0,time()-strtotime($timestamp));$value=$seconds<3600?max(1,(int)($seconds/60)):($seconds<86400?(int)($seconds/3600):(int)($seconds/86400));$unit=$seconds<3600?'min':($seconds<86400?'hour':'day');return $value.' '.$unit.($value===1?'':'s').' ago';};
$sessionName=(string)db()->query('SELECT name FROM academic_sessions WHERE is_current=1 LIMIT 1')->fetchColumn();
?>
<section class="dashboard-welcome"><div><h2>Welcome, Administrator</h2><p>System-wide supervision activity and final year project administration.</p></div><?php if($sessionName): ?><span class="welcome-session">Academic session: <?= e($sessionName) ?></span><?php endif; ?></section>
<div class="row g-3 mb-3"><?php foreach($tiles as [$label,$value,$sub,$icon,$tone]): ?>
<div class="col-sm-6 col-xl-3"><div class="stat-card stat-with-icon"><span class="stat-corner <?= e($tone) ?>"><?= ui_icon($icon,18) ?></span><div class="stat-label"><?= e($label) ?></div><div class="stat-value"><?= (int)$value ?></div><small class="stat-sub"><?= e($sub) ?></small></div></div>
<?php endforeach; ?></div>
<section class="card p-3 mb-3"><div class="dashboard-section-title"><h2>System snapshot</h2><span class="text-muted small">Current database totals</span></div><div class="row g-3">
<div class="col-6 col-xl"><div class="stat-label">All users</div><strong class="fs-5 text-ink"><?= $allUsers ?></strong></div>
<div class="col-6 col-xl"><div class="stat-label">HODs</div><strong class="fs-5 text-ink"><?= $roleCounts['HODs'] ?></strong></div>
<div class="col-6 col-xl"><div class="stat-label">Deans</div><strong class="fs-5 text-ink"><?= $roleCounts['Deans'] ?></strong></div>
<div class="col-6 col-xl"><div class="stat-label">Examiners</div><strong class="fs-5 text-ink"><?= $roleCounts['Examiners'] ?></strong></div>
<div class="col-6 col-xl"><div class="stat-label">Completed projects</div><strong class="fs-5 text-ink"><?= $completedProjects ?></strong></div>
</div></section>
<section class="card p-3 mb-3"><div class="dashboard-section-title"><h2>Quick actions</h2></div><div class="d-flex flex-wrap gap-2"><a class="btn btn-primary" href="<?= e(url('admin/users.php')) ?>">Manage users</a><a class="btn btn-outline-primary" href="<?= e(url('admin/assignments.php')) ?>">Supervisor assignments</a><a class="btn btn-outline-primary" href="<?= e(url('admin/projects.php')) ?>">Project register</a><a class="btn btn-outline-primary" href="<?= e(url('admin/settings.php')) ?>">System settings</a><a class="btn btn-outline-primary" href="<?= e(url('admin/roles-permissions.php')) ?>">Roles &amp; permissions</a></div></section>
<div class="row g-3">
  <div class="col-xl-7">
    <section class="card p-4 mb-3"><h2 class="section-heading">Projects Requiring Attention</h2>
      <?php if(!$urgent): ?><div class="empty-state">No projects need urgent attention.</div><?php endif; ?>
      <?php foreach($urgent as $p): $days=max(0,(int)((time()-strtotime($p['updated_at']))/86400));$studentName=trim(($p['first_name']??'').' '.($p['last_name']??'')); ?>
      <a class="attention-row" href="<?= e(url('admin/projects.php')) ?>"><div><strong><?= e($studentName?:$p['title']) ?></strong><small><?= e(ui_label($p['status'])) ?> · <?= $days ?> day<?= $days===1?'':'s' ?></small></div><span class="ui-pill <?= $p['status']==='correction_required'?'is-bad':'is-warn' ?>"><?= $p['status']==='correction_required'?'Correction outstanding':'Pending' ?></span></a>
      <?php endforeach; ?></section>
    <section class="card p-4"><h2 class="section-heading">Project Progression Distribution</h2>
      <?php foreach($distribution as $item): $pct=$distributionTotal?(int)round($item['count']/$distributionTotal*100):0; ?>
      <div class="dist-row"><div class="roadmap-line-top"><strong><?= e($item['label']) ?></strong><small><?= (int)$item['count'] ?> projects (<?= $pct ?>%)</small></div><div class="progress <?= e($item['tone']) ?>" role="progressbar" aria-label="<?= e($item['label']) ?>" aria-valuenow="<?= $pct ?>" aria-valuemin="0" aria-valuemax="100"><div class="progress-bar" style="width:<?= $pct ?>%"></div></div></div>
      <?php endforeach; ?>
    </section>
  </div>
  <div class="col-xl-5"><section class="card p-4"><div class="section-row mb-3"><h2 class="section-heading mb-0">Recent Activity Feed</h2><a class="view-link" href="<?= e(url('admin/audit-logs.php')) ?>">View all</a></div>
    <?php if(!$rows): ?><div class="empty-state">No system activity recorded yet.</div><?php endif; ?>
    <ul class="history-log"><?php foreach($rows as $r): ?><li><span class="history-icon"><?= ui_icon($iconFor((string)$r['action']),16) ?></span><div><strong><?= e(ui_label($r['action'])) ?></strong><p><?= e($r['description']) ?></p><small><?= e($ago((string)$r['created_at'])) ?></small></div></li><?php endforeach; ?></ul></section>
    <section class="card p-4 mt-3"><div class="dashboard-section-title"><h2>Academic deadlines</h2><span class="badge text-bg-secondary"><?= (int)$deadlineCount ?> upcoming</span></div><?php if(!$deadlineRows): ?><div class="empty-state">No deadlines in the current session window.</div><?php else: ?><div class="deadline-list"><?php foreach($deadlineRows as $d): $isPast=$d['due_at']<date('Y-m-d H:i:s');$isToday=substr($d['due_at'],0,10)===date('Y-m-d'); ?><div class="deadline-item"><span class="deadline-marker <?= $isPast?'is-past':($isToday?'is-today':'') ?>"></span><div><strong><?= e($d['title']) ?></strong><small><?= e(ucwords(str_replace('_',' ',$d['milestone_key']))) ?></small></div><time><?= e(date('M j',strtotime($d['due_at']))) ?></time></div><?php endforeach; ?></div><?php endif; ?></section>
  </div>
</div>
