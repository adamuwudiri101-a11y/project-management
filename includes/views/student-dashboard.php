<?php
/* Student dashboard view (presentation). Uses read-only queries on top of the data prepared in includes/dashboard.php. */
$uid = (int)$u['id'];
$deptName = '';
if (!empty($u['department_id'])) { $dq2 = db()->prepare('SELECT name FROM departments WHERE id=?'); $dq2->execute([(int)$u['department_id']]); $deptName = (string)$dq2->fetchColumn(); }
$milestones = []; $supervisor = null; $stats = ['total' => 0, 'pending' => 0, 'corrections' => 0]; $activeCorrections = []; $ideaRows=[];
if ($project) {
    $milestones = ui_student_milestones((int)$project['id'], (string)$project['status']);
    $iq=db()->prepare('SELECT title,review_status,reviewer_comment,submitted_at FROM project_ideas WHERE project_id=? ORDER BY id');$iq->execute([(int)$project['id']]);$ideaRows=$iq->fetchAll();
    $q = db()->prepare('SELECT CONCAT_WS(" ",su.first_name,su.last_name) name,d.name department FROM project_supervisors ps JOIN users su ON su.id=ps.supervisor_id LEFT JOIN departments d ON d.id=su.department_id WHERE ps.project_id=? AND ps.ended_at IS NULL ORDER BY ps.assigned_at DESC LIMIT 1');
    $q->execute([(int)$project['id']]); $supervisor = $q->fetch() ?: null;
    $q = db()->prepare("SELECT COUNT(*) total, SUM(status IN ('submitted','resubmitted','under_review')) pending, SUM(status='correction_required') corrections FROM submissions WHERE project_id=?");
    $q->execute([(int)$project['id']]); $stats = array_map('intval', $q->fetch() ?: $stats);
}
$q = db()->prepare("SELECT c.description,c.due_at,c.created_at,s.document_type,v.id version_id FROM corrections c JOIN submission_reviews r ON r.id=c.submission_review_id JOIN submission_versions v ON v.id=r.submission_version_id JOIN submissions s ON s.id=v.submission_id WHERE c.assigned_to=? AND c.status IN ('open','in_progress') ORDER BY c.created_at DESC LIMIT 3");
$q->execute([$uid]); $activeCorrections = $q->fetchAll();
$q = db()->prepare('SELECT title,message,type,created_at FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 4');
$q->execute([$uid]); $history = $q->fetchAll();
$deadlineQuery=db()->prepare('SELECT d.title,d.milestone_key,d.due_at FROM deadlines d JOIN academic_sessions ac ON ac.id=d.academic_session_id WHERE ac.is_current=1 AND d.due_at>=NOW() AND d.due_at<=DATE_ADD(NOW(),INTERVAL 60 DAY) AND (d.department_id IS NULL OR d.department_id=?) ORDER BY d.due_at LIMIT 6');
$deadlineQuery->execute([(int)($u['department_id']??0)]);$studentDeadlines=$deadlineQuery->fetchAll();
$doneCount = count(array_filter($milestones, 'ui_milestone_done'));
$currentStep = ui_current_milestone($milestones);
$sessionName = (string)db()->query('SELECT name FROM academic_sessions WHERE is_current=1 LIMIT 1')->fetchColumn();
?>
<section class="dashboard-welcome">
  <div>
    <h2>Welcome back, <?= e($welcomeName) ?></h2>
    <p>Matriculation Number: <strong class="gold-text"><?= e($u['matric_number'] ?? '—') ?></strong><?php if ($deptName): ?> | <?= e($deptName) ?><?php endif; ?></p>
  </div>
  <?php if ($sessionName): ?><span class="welcome-session">Session: <?= e($sessionName) ?></span><?php endif; ?>
</section>

<div class="row g-3 mb-3">
  <div class="col-sm-6 col-xl-3"><div class="info-card"><span class="info-label">Project title</span><strong class="info-title"><?= e($project['title'] ?? 'No project registered yet') ?></strong></div></div>
  <div class="col-sm-6 col-xl-3"><div class="info-card"><span class="info-label">Assigned supervisor</span>
    <?php if ($supervisor): ?><div class="person"><span class="person-avatar"><?= e(ui_initials($supervisor['name'])) ?></span><div><strong><?= e($supervisor['name']) ?></strong><small><?= e($supervisor['department'] ?: 'Academic supervisor') ?></small></div></div>
    <?php else: ?><span class="info-muted">Awaiting HOD assignment</span><?php endif; ?></div></div>
  <div class="col-sm-6 col-xl-3"><div class="info-card"><span class="info-label">Current stage</span>
    <strong class="info-stage"><span class="dot-gold"></span><?= e($currentStep['label'] ?? ($project ? 'Completed' : 'Submit a project idea')) ?></strong>
    <small class="info-muted"><?= $project ? e(ui_label($project['status'])) : 'Register your project to begin' ?></small></div></div>
  <div class="col-sm-6 col-xl-3"><div class="info-card"><span class="info-label">Overall progress</span>
    <div class="progress" role="progressbar" aria-label="Overall progress" aria-valuenow="<?= (int)$progress ?>" aria-valuemin="0" aria-valuemax="100"><div class="progress-bar" style="width:<?= (int)$progress ?>%"></div></div>
    <strong class="info-progress"><?= (int)$progress ?>% Completed</strong></div></div>
</div>

<?php if ($milestones): ?>
<section class="card p-4 mb-3"><h2 class="section-heading">Academic Project Roadmap &amp; Stepper</h2><?= ui_stepper($milestones) ?></section>
<?php endif; ?>
<?php if($ideaRows): ?><section class="card p-4 mb-3"><div class="section-row"><div><h2 class="section-heading mb-1">Project idea review</h2><p class="text-muted mb-0">Your assigned supervisor selects one idea and provides feedback here.</p></div><a class="view-link" href="<?= e(url('student/project.php?ideas=1')) ?>">Open ideas</a></div><div class="row g-3 mt-1"><?php foreach($ideaRows as $index=>$idea): ?><div class="col-md-6"><article class="border rounded p-3 h-100"><div class="d-flex justify-content-between gap-2"><strong>Idea <?= $index+1 ?> · <?= e($idea['title']) ?></strong><?= status_badge($idea['review_status']==='approved'?'selected':$idea['review_status']) ?></div><?php if($idea['reviewer_comment']): ?><p class="small text-muted mb-0 mt-2"><?= nl2br(e($idea['reviewer_comment'])) ?></p><?php else: ?><p class="small text-muted mb-0 mt-2">Awaiting supervisor feedback.</p><?php endif; ?></article></div><?php endforeach; ?></div></section><?php endif; ?>
<section class="card p-4 mb-3"><div class="section-row"><div><h2 class="section-heading mb-1">Upcoming deadlines</h2><p class="text-muted mb-0">Official dates for your current academic session.</p></div><a class="view-link" href="<?= e(url('student/submissions.php')) ?>">Open submissions</a></div><?php if(!$studentDeadlines): ?><div class="empty-state">No upcoming deadlines have been set for your department.</div><?php else: ?><div class="table-responsive mt-2"><table class="table table-sm align-middle mb-0"><thead><tr><th>Deadline</th><th>Stage</th><th>Due</th><th>Status</th></tr></thead><tbody><?php foreach($studentDeadlines as $deadline): $today=substr($deadline['due_at'],0,10)===date('Y-m-d'); ?><tr><td><?= e($deadline['title']) ?></td><td><?= e(ui_label($deadline['milestone_key'])) ?></td><td><?= e(ui_date($deadline['due_at'],'M j, Y · g:i A')) ?></td><td><?= $today?'<span class="badge text-bg-warning">Due today</span>':'<span class="badge text-bg-primary">Upcoming</span>' ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></section>

<div class="row g-3">
  <div class="col-xl-7">
    <section class="card p-4 mb-3"><h2 class="section-heading">Submission Statistics Overview</h2>
      <div class="tile-grid">
        <div class="tile"><span>Total submissions</span><strong><?= (int)$stats['total'] ?></strong></div>
        <div class="tile tile-gold"><span>Pending review</span><strong><?= (int)$stats['pending'] ?></strong></div>
        <div class="tile tile-red"><span>Corrections needed</span><strong><?= (int)$stats['corrections'] ?></strong></div>
        <div class="tile tile-green"><span>Approved stages</span><strong><?= (int)$doneCount ?></strong></div>
      </div></section>
    <section class="card p-4"><div class="section-row"><h2 class="section-heading">Active Supervisor Corrections</h2><?php if ($activeCorrections): ?><span class="soft-chip"><?= count($activeCorrections) ?> Urgent Action<?= count($activeCorrections) > 1 ? 's' : '' ?></span><?php endif; ?></div>
      <?php if (!$activeCorrections): ?><div class="empty-state">No active corrections. You are up to date.</div><?php endif; ?>
      <?php foreach ($activeCorrections as $c): ?>
      <div class="correction-row"><div><span class="correction-meta"><strong><?= e(strtoupper(ui_doc_label($c['document_type']))) ?> CORRECTION</strong> <span>• <?= e(ui_date($c['created_at'])) ?></span></span><p><?= e(mb_strimwidth((string)$c['description'], 0, 140, '…')) ?></p></div><a class="btn btn-primary btn-sm" href="<?= e(url('student/submission-details.php?version=' . (int)$c['version_id'])) ?>">View Detail</a></div>
      <?php endforeach; ?></section>
  </div>
  <div class="col-xl-5">
    <section class="card p-4 mb-3"><h2 class="section-heading">Recent Project History Log</h2>
      <?php if (!$history): ?><div class="empty-state">Activity on your project will appear here.</div><?php endif; ?>
      <ul class="history-log"><?php foreach ($history as $h): $icon = ['success' => 'check', 'warning' => 'alert', 'action' => 'upload', 'info' => 'chat'][$h['type']] ?? 'chat'; ?>
        <li class="is-<?= e($h['type']) ?>"><span class="history-icon"><?= ui_icon($icon, 16) ?></span><div><strong><?= e($h['title']) ?></strong><p><?= e($h['message']) ?></p><small><?= e(ui_date($h['created_at'], 'M j, Y · g:i A')) ?></small></div></li>
      <?php endforeach; ?></ul></section>
    <section class="card p-4"><h2 class="section-heading">Student Tools</h2>
      <div class="tool-stack">
        <a class="btn btn-outline-primary" href="<?= e(url('student/project.php?ideas=1')) ?>"><?= ui_icon('book') ?>My Project Ideas</a>
        <a class="btn btn-danger" href="<?= e(url('student/submissions.php?new=1')) ?>"><?= ui_icon('upload') ?>Submit Project Work</a>
        <a class="btn btn-outline-primary" href="<?= e(url('student/final-submission.php')) ?>"><?= ui_icon('file') ?>Final Submission</a>
        <a class="btn btn-outline-primary" href="<?= e(url('student/corrections.php')) ?>"><?= ui_icon('eye') ?>View All Correction Briefs</a>
        <a class="btn btn-outline-primary" href="<?= e(url('student/progress.php')) ?>"><?= ui_icon('book') ?>Track Project Progress</a>
        <a class="btn btn-outline-secondary" href="<?= e(url('student/profile.php')) ?>">My academic profile</a>
      </div></section>
  </div>
</div>
