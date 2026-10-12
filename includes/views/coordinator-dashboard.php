<?php
/* HOD / coordinator dashboard view (presentation, read-only queries). */
$sessionName = (string)db()->query('SELECT name FROM academic_sessions WHERE is_current=1 LIMIT 1')->fetchColumn();
$deptQ = db()->prepare('SELECT name FROM departments WHERE id=?'); $deptQ->execute([$departmentId]); $deptName = (string)$deptQ->fetchColumn();
[$cStudents, $cSupervisors, $cActive, $cDone, $cUnassigned, $cAttention] = array_map(fn($c) => (int)$c[1], $cards);
$tiles = [
    ['Department students', $cStudents, 'Registered in ' . ($deptName ?: 'your department'), ''],
    ['Active projects', $cActive, 'Across ' . $cSupervisors . ' active supervisor' . ($cSupervisors === 1 ? '' : 's'), 'text-blue'],
    ['Completed projects', $cDone, 'Approved or archived', 'text-green'],
    ['Need attention', $cAttention, $cUnassigned . ' student' . ($cUnassigned === 1 ? '' : 's') . ' without supervisor', 'text-red'],
];
$groups = ['Approved / completed' => ["'approved','completed','archived'", 'is-green', 'ok'], 'Final writing stage' => ["'final_submission','under_evaluation'", 'is-red', 'bad'], 'Chapters in progress' => ["'supervisor_assigned','proposal_submitted','in_progress'", 'is-gold', 'warn'], 'Ideas and supervisor assignment' => ["'idea_submitted','idea_under_review','idea_approved','idea_rejected','supervisor_pending','correction_required'", 'is-grey', 'muted']];
$counts = []; $total = 0;
foreach ($groups as $label => [$in]) { $q = db()->prepare("SELECT COUNT(*) FROM projects WHERE department_id=? AND status IN ($in)"); $q->execute([$departmentId]); $counts[$label] = (int)$q->fetchColumn(); $total += $counts[$label]; }
$q = db()->prepare('SELECT d.title,d.due_at,d.milestone_key FROM deadlines d WHERE (d.department_id IS NULL OR d.department_id=?) ORDER BY ABS(DATEDIFF(d.due_at,NOW())) ASC LIMIT 4'); $q->execute([$departmentId]); $deadlines = $q->fetchAll();
$q = db()->prepare("SELECT COUNT(*) total,SUM(pe.status='completed') done FROM project_examiners pe JOIN projects p ON p.id=pe.project_id WHERE p.department_id=?"); $q->execute([$departmentId]); $ev = $q->fetch() ?: ['total' => 0, 'done' => 0];
$evTotal = (int)$ev['total']; $evDone = (int)$ev['done']; $evPending = $evTotal - $evDone;
?>
<section class="dashboard-welcome"><div><h2>Welcome, Project Coordinator</h2><p>Department-wide project administration, deadlines, evaluations and progress tracking<?= $deptName ? ' for ' . e($deptName) : '' ?>.</p></div><?php if ($sessionName): ?><span class="welcome-session">Session <?= e($sessionName) ?></span><?php endif; ?></section>
<div class="row g-3 mb-3"><?php foreach ($tiles as [$label, $value, $sub, $tone]): ?>
<div class="col-sm-6 col-xl-3"><div class="stat-card"><div class="stat-label"><?= e($label) ?></div><div class="stat-value <?= e($tone) ?>"><?= (int)$value ?></div><small class="stat-sub"><?= e($sub) ?></small></div></div>
<?php endforeach; ?></div>
<section class="card p-4 mb-3"><h2 class="section-heading">Project Progress Overview</h2><div class="row-table">
<?php foreach ($groups as $label => [$in, $tone, $pill]): $c = $counts[$label]; $pct = $total ? (int)round($c / $total * 100) : 0; ?>
<div class="meeting-row dept-row"><div class="dept-main"><strong><?= e($label) ?> · <?= $pct ?>%</strong><div class="progress <?= e($tone) ?>" role="progressbar" aria-label="<?= e($label) ?>" aria-valuenow="<?= $pct ?>" aria-valuemin="0" aria-valuemax="100"><div class="progress-bar" style="width:<?= $pct ?>%"></div></div></div><span class="ui-pill is-<?= e($pill) ?>"><?= (int)$c ?> project<?= $c === 1 ? '' : 's' ?></span></div>
<?php endforeach; ?></div></section>
<section class="card p-4 mb-3"><div class="section-row mb-3"><h2 class="section-heading mb-0">Upcoming Deadlines</h2><a class="view-link" href="<?= e(url('coordinator/deadlines.php')) ?>">Manage deadlines</a></div><div class="row-table">
<?php foreach ($deadlines as $d): $past = $d['due_at'] < date('Y-m-d H:i:s'); ?><div class="meeting-row"><strong><?= e($d['title']) ?> | <?= e(ui_label($d['milestone_key'])) ?> | <?= e(ui_date($d['due_at'])) ?></strong><span class="ui-pill is-<?= $past ? 'bad' : 'info' ?>"><?= $past ? 'Overdue' : 'Upcoming' ?></span></div><?php endforeach; ?>
<?php if (!$deadlines): ?><div class="empty-state m-3">No deadlines have been set for your department.</div><?php endif; ?></div></section>
<section class="card p-4"><div class="section-row mb-3"><h2 class="section-heading mb-0">Evaluation Status &amp; Recent Projects</h2><a class="view-link" href="<?= e(url('coordinator/evaluations.php')) ?>">Manage evaluations</a></div><div class="row-table">
<div class="meeting-row"><strong>Examiner assignments <?= $evTotal ?> | Completed <?= $evDone ?> | Pending <?= $evPending ?></strong></div>
<?php foreach ($rows as $r): ?><div class="meeting-row"><div><strong><?= e($r['title']) ?></strong><p><?= e($r['reference_no']) ?> · registered <?= e(ui_date($r['created_at'])) ?></p></div><?= status_badge($r['status']) ?></div><?php endforeach; ?>
<?php if (!$rows): ?><div class="empty-state m-3">No projects registered yet.</div><?php endif; ?></div></section>
