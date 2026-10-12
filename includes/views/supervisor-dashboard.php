<?php
/* Supervisor dashboard view (presentation, read-only queries). */
$welcome = trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? '')) ?: 'Supervisor';
$sessionName = (string)db()->query('SELECT name FROM academic_sessions WHERE is_current=1 LIMIT 1')->fetchColumn();
$q = db()->prepare('SELECT DISTINCT p.id pid,p.title,p.status,st.first_name,st.last_name,st.matric_number FROM projects p JOIN project_supervisors ps ON ps.project_id=p.id AND ps.ended_at IS NULL JOIN project_members pm ON pm.project_id=p.id JOIN users st ON st.id=pm.student_id WHERE ps.supervisor_id=? ORDER BY st.last_name,st.first_name');
$q->execute([(int)$u['id']]); $supervised = $q->fetchAll();
$roster = [];
foreach ($supervised as $s) {
    $ms = ui_student_milestones((int)$s['pid'], (string)$s['status']);
    $cur = ui_current_milestone($ms);
    $lq = db()->prepare('SELECT status FROM submissions WHERE project_id=? ORDER BY submitted_at DESC LIMIT 1'); $lq->execute([(int)$s['pid']]);
    $roster[] = ['pid' => (int)$s['pid'], 'name' => trim($s['first_name'] . ' ' . $s['last_name']) ?: $s['matric_number'], 'percent' => ui_progress_percent($ms), 'stage' => $cur['label'] ?? 'Completed', 'last' => (string)$lq->fetchColumn()];
}
$attention = array_values(array_filter($roster, fn($r) => in_array($r['last'], ['submitted', 'resubmitted', 'correction_required', 'under_review'], true)));
$q = db()->prepare('SELECT s.document_type,s.status,p.id pid,st.first_name,st.last_name,v.original_filename,v.uploaded_at FROM submissions s JOIN projects p ON p.id=s.project_id JOIN project_supervisors ps ON ps.project_id=p.id AND ps.ended_at IS NULL JOIN users st ON st.id=s.student_id JOIN submission_versions v ON v.submission_id=s.id AND v.version_number=s.current_version WHERE ps.supervisor_id=? ORDER BY v.uploaded_at DESC LIMIT 5');
$q->execute([(int)$u['id']]); $feed = $q->fetchAll();
[$cStudents, $cActive, $cPending, $cCorr, $cApproved] = array_map(fn($c) => (int)$c[1], $cards);
$tiles = [
    ['Assigned students', $cStudents, 'Currently supervised', 'users', ''],
    ['Pending reviews', $cPending, 'Awaiting your review', 'clock', 'is-gold'],
    ['Corrections outstanding', $cCorr, 'Awaiting student revision', 'alert', 'is-red'],
    ['Approved projects', $cApproved, 'Final draft cleared', 'check', 'is-green'],
];
$attentionLabel = ['correction_required' => ['Correction Outstanding', 'warn'], 'submitted' => ['Needs Review', 'warn'], 'resubmitted' => ['Needs Review', 'warn'], 'under_review' => ['Under Review', 'warn']];
?>
<section class="dashboard-welcome"><div><h2>Welcome back, <?= e($welcome) ?></h2><p><?= e(($facultyLabel ?? '') ?: 'Supervisor') ?> | Supervisor Dashboard Overview &amp; Project Track logs</p></div><?php if ($sessionName): ?><span class="welcome-session">Academic Session: <?= e($sessionName) ?></span><?php endif; ?></section>
<div class="row g-3 mb-3"><?php foreach ($tiles as [$label, $value, $sub, $icon, $tone]): ?>
<div class="col-sm-6 col-xl-3"><div class="stat-card stat-with-icon"><span class="stat-corner <?= e($tone) ?>"><?= ui_icon($icon, 18) ?></span><div class="stat-label"><?= e($label) ?></div><div class="stat-value"><?= (int)$value ?></div><small class="stat-sub"><?= e($sub) ?></small></div></div>
<?php endforeach; ?></div>
<div class="row g-3">
  <div class="col-xl-7">
    <section class="card p-4 mb-3"><h2 class="section-heading">Students Requiring Attention</h2>
      <?php if (!$attention): ?><div class="empty-state">No students need attention right now.</div><?php endif; ?>
      <?php foreach (array_slice($attention, 0, 5) as $a): [$lbl, $tone] = $attentionLabel[$a['last']] ?? ['Review', 'warn']; ?>
      <a class="attention-row" href="<?= e(url('supervisor/reviews.php?project_id=' . $a['pid'])) ?>"><div><strong><?= e($a['name']) ?></strong><small><?= e($a['stage']) ?></small></div><span class="ui-pill is-<?= $a['last'] === 'correction_required' ? 'bad' : 'warn' ?>"><?= e($lbl) ?></span></a>
      <?php endforeach; ?></section>
    <section class="card p-4"><h2 class="section-heading">Recent Submissions Feed</h2>
      <?php if (!$feed): ?><div class="empty-state">Student submissions will appear here.</div><?php endif; ?>
      <?php foreach ($feed as $f): ?><div class="feed-row"><div><strong><?= e(trim($f['first_name'] . ' ' . $f['last_name'])) ?></strong><p><?= e(ui_doc_label($f['document_type'])) ?> — <?= e($f['original_filename']) ?></p><small><?= e(ui_date($f['uploaded_at'], 'M j, Y · g:i A')) ?></small></div><div class="feed-actions"><?= ui_pill($f['status']) ?><a class="view-link" href="<?= e(url('supervisor/reviews.php?project_id=' . (int)$f['pid'])) ?>">Review</a></div></div><?php endforeach; ?></section>
  </div>
  <div class="col-xl-5"><section class="card p-4"><h2 class="section-heading">Student Performance Roadmap</h2>
    <?php if (!$roster): ?><div class="empty-state">No students have been assigned to you yet.</div><?php endif; ?>
    <?php foreach ($roster as $r): ?><div class="roadmap-line"><div class="roadmap-line-top"><strong><?= e($r['name']) ?></strong><small><?= e($r['stage']) ?> (<?= (int)$r['percent'] ?>%)</small></div><div class="progress <?= $r['percent'] >= 100 ? 'is-complete' : '' ?>" role="progressbar" aria-label="<?= e($r['name']) ?> progress" aria-valuenow="<?= (int)$r['percent'] ?>" aria-valuemin="0" aria-valuemax="100"><div class="progress-bar" style="width:<?= (int)$r['percent'] ?>%"></div></div></div><?php endforeach; ?></section></div>
</div>
