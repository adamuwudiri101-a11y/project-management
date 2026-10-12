<?php
require_once dirname(__DIR__) . '/includes/auth.php';
$u = require_role('supervisor');
$projectId = filter_input(INPUT_GET, 'project_id', FILTER_VALIDATE_INT) ?: 0;
if (!project_access($projectId, $u)) {
    http_response_code(403);
    exit('Project not assigned to you.');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $versionId = (int)($_POST['version_id'] ?? 0);
    $decision = (string)($_POST['decision'] ?? '');
    $comment = post_string('comment', 5000);
    $correctionDetail = post_string('correction_detail', 5000);
    $allowedDecisions = ['commented', 'correction_required', 'approved', 'rejected'];

    // A decision must apply to the current version and an awaiting-review state.
    // This prevents an old version from changing the status of a newer submission.
    $q = db()->prepare("SELECT v.id, s.id submission_id, s.student_id, s.project_id, s.document_type
        FROM submission_versions v JOIN submissions s ON s.id = v.submission_id
        WHERE v.id = ? AND s.project_id = ? AND v.version_number = s.current_version
          AND s.status IN ('submitted', 'resubmitted', 'under_review')");
    $q->execute([$versionId, $projectId]);
    $version = $q->fetch();

    if (!$version || !in_array($decision, $allowedDecisions, true) || $comment === '' || ($decision === 'correction_required' && $correctionDetail === '')) {
        $error = 'Choose a current submission awaiting review and enter feedback. A correction request also needs the specific changes required.';
    } else {
        $db = db();
        $db->beginTransaction();
        try {
            $db->prepare('INSERT INTO submission_reviews (submission_version_id, reviewer_id, decision, comment) VALUES (?, ?, ?, ?)')
                ->execute([$versionId, $u['id'], $decision, $comment]);
            $reviewId = (int)$db->lastInsertId();
            $submissionStatus = match ($decision) {
                'correction_required' => 'correction_required',
                'approved' => 'approved',
                'rejected' => 'rejected',
                default => 'under_review',
            };
            $db->prepare('UPDATE submissions SET status = ? WHERE id = ?')->execute([$submissionStatus, $version['submission_id']]);

            if ($decision === 'correction_required') {
                $db->prepare('INSERT INTO corrections (submission_review_id, assigned_to, description) VALUES (?, ?, ?)')
                    ->execute([$reviewId, $version['student_id'], $correctionDetail]);
            }

            $milestone = match ($version['document_type']) {
                'proposal' => 'proposal', 'chapter_1' => 'chapter_1', 'chapter_2' => 'chapter_2',
                'chapter_3' => 'chapter_3', 'chapter_4' => 'chapter_4', 'chapter_5' => 'chapter_5',
                'final_project' => 'final_submission', default => null,
            };
            if ($milestone) {
                $milestoneStatus = $decision === 'approved' ? 'completed'
                    : ($decision === 'correction_required' ? 'correction_required' : 'in_progress');
                $db->prepare('UPDATE milestones SET status = ?, completed_at = IF(? = "completed", NOW(), NULL), updated_by = ? WHERE project_id = ? AND milestone_key = ?')
                    ->execute([$milestoneStatus, $milestoneStatus, $u['id'], $projectId, $milestone]);
            }

            if ($decision === 'correction_required') {
                $db->prepare('UPDATE projects SET status = "correction_required" WHERE id = ?')->execute([$projectId]);
            } elseif ($decision === 'approved' && $version['document_type'] === 'final_project') {
                $db->prepare('UPDATE projects SET status = "final_submission" WHERE id = ?')->execute([$projectId]);
            } elseif ($decision === 'approved') {
                $db->prepare('UPDATE projects SET status = "in_progress" WHERE id = ?')->execute([$projectId]);
            }
            if ($decision === 'approved') {
                $db->prepare("UPDATE corrections c JOIN submission_reviews r ON r.id = c.submission_review_id
                    JOIN submission_versions sv ON sv.id = r.submission_version_id
                    SET c.status = 'resolved', c.resolved_at = NOW()
                    WHERE sv.submission_id = ? AND c.status IN ('open', 'in_progress', 'resubmitted')")
                    ->execute([$version['submission_id']]);
            }
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            error_log($e->getMessage());
            $error = 'The review could not be saved. Please try again.';
        }

        if ($error === '') {
            notify_user((int)$version['student_id'], 'Submission review updated', $comment,
                $decision === 'approved' ? 'success' : 'action', 'submission', (int)$version['submission_id']);
            audit('submission_reviewed', 'submission', (int)$version['submission_id'], 'Decision: ' . $decision);
            flash('success', 'Review recorded and the student notified.');
            redirect('supervisor/reviews.php?project_id=' . $projectId);
        }
    }
}

$q = db()->prepare('SELECT p.title,p.reference_no,p.description,d.name department,pr.name programme,s.id student_id,s.matric_number,s.first_name,s.last_name,s.email,f.name faculty FROM projects p JOIN project_members pm ON pm.project_id=p.id JOIN users s ON s.id=pm.student_id LEFT JOIN departments d ON d.id=p.department_id LEFT JOIN faculties f ON f.id=d.faculty_id LEFT JOIN programmes pr ON pr.id=p.programme_id WHERE p.id=? ORDER BY pm.member_role="leader" DESC LIMIT 1');
$q->execute([$projectId]);
$project = $q->fetch();
    $q = db()->prepare('SELECT s.id submission_id, s.document_type, s.status, s.current_version,
        v.id version_id, v.version_number, v.original_filename, v.file_size_bytes,v.mime_type,v.uploaded_at, u.id student_id,u.first_name, u.last_name,u.matric_number
    FROM submissions s JOIN submission_versions v ON v.submission_id = s.id
    JOIN users u ON u.id = s.student_id WHERE s.project_id = ?
    ORDER BY s.submitted_at DESC, v.version_number DESC');
$q->execute([$projectId]);
$rows = $q->fetchAll();
$q=db()->prepare('SELECT r.submission_version_id,r.decision,r.comment,r.reviewed_at,u.first_name,u.last_name FROM submission_reviews r JOIN submission_versions v ON v.id=r.submission_version_id JOIN submissions s ON s.id=v.submission_id JOIN users u ON u.id=r.reviewer_id WHERE s.project_id=? ORDER BY r.reviewed_at DESC');$q->execute([$projectId]);$reviewHistory=[];foreach($q->fetchAll() as $history){$reviewHistory[(int)$history['submission_version_id']][]=$history;}
$pageTitle = 'Review Active Submission';$crumbTrail=['Submissions','Review Draft'];
require dirname(__DIR__) . '/includes/header.php';
?>
<?php $studentName=trim(($project['first_name']??'').' '.($project['last_name']??''))?:'Student profile incomplete'; ?>
<?php if ($error): ?><div class="alert alert-danger" role="alert"><?= e($error) ?></div><?php endif; ?>
<section class="card p-4 mb-3"><div class="row g-3 align-items-center"><div class="col-lg-6"><span class="mini-heading d-block">Student details</span><div class="person person-lg"><span class="person-avatar"><?= e(ui_initials($studentName)) ?></span><div><strong><?= e($studentName) ?></strong><small><?= e(($project['matric_number']??'Matric number not recorded').' | '.($project['department']??'Department not recorded')) ?></small><?php if(!empty($project['email'])): ?><a class="mail-link" href="mailto:<?= e($project['email']) ?>"><?= e($project['email']) ?></a><?php endif; ?></div></div></div><div class="col-lg-6"><span class="mini-heading d-block">Project record</span><h2 class="milestone-title mt-0"><?= e($project['title']??'Project title') ?></h2><p class="meta-line mb-0"><?= e($project['reference_no']??'') ?><?= !empty($project['programme'])?' · '.e($project['programme']):'' ?></p></div></div></section>
<div class="section-row mb-3"><h2 class="section-heading mb-0">Submission history</h2><span class="text-muted small"><?= count($rows) ?> version<?= count($rows)===1?'':'s' ?></span></div>
<?php if (!$rows): ?><div class="card empty-state">No project documents have been submitted yet. New submissions will appear here for review.</div><?php endif; ?>
<?php foreach ($rows as $r):
    $canReview = (int)$r['version_number'] === (int)$r['current_version'] && in_array($r['status'], ['submitted', 'resubmitted', 'under_review'], true);
    $stageLabel = ui_doc_label($r['document_type']);
    $fileMb = number_format(((int)$r['file_size_bytes'])/1048576, 1);
?>
<?php if(!$canReview): ?><details class="card record-card mb-3"><summary><span><strong><?= e($stageLabel) ?></strong> <span class="text-muted">· Version <?= (int)$r['version_number'] ?> · <?= e(ui_date($r['uploaded_at'])) ?></span></span><span class="d-flex align-items-center gap-3"><?= ui_pill($r['status']) ?><span class="chev"><?= ui_icon('chevron',16) ?></span></span></summary><div class="p-4 pt-2"><?php endif; ?>
<div class="row g-3 mb-4 review-row">
<div class="col-xl-6">
  <section class="card p-4 mb-3 review-submission-card <?= $canReview?'awaiting-review':'' ?>"><div class="section-row"><span class="mini-heading mb-0">Submission details</span><div class="d-flex gap-2"><?= ui_pill($r['status']) ?><?php if(!$canReview): ?><span class="ui-pill is-muted">History</span><?php endif; ?></div></div>
    <h3 class="doc-heading mt-3"><?= e($stageLabel) ?></h3><p class="meta-line">Version: <strong class="text-ink"><?= (int)$r['version_number'] ?>.0</strong> &nbsp; Submitted on: <strong class="text-ink"><?= e(ui_date($r['uploaded_at'])) ?></strong></p></section>
  <section class="card p-4"><div class="review-file-card"><span class="review-file-icon" aria-hidden="true"><?= ui_icon('file',22) ?></span><div class="review-file-meta"><strong><?= e($r['original_filename']) ?></strong><small><?= e(strtoupper(pathinfo($r['original_filename'],PATHINFO_EXTENSION))) ?> Document • <?= e($fileMb) ?> MB</small></div></div>
    <div class="d-flex gap-2 mt-3 flex-wrap"><a class="btn btn-primary flex-fill" href="<?= e(url('download.php?version=' . (int)$r['version_id'])) ?>">Download Document</a></div></section>
</div>
<div class="col-xl-6">
<?php if ($canReview): ?>
  <section class="card p-4 mb-3"><h2 class="section-heading">Supervisor Evaluation Form</h2>
  <form method="post" class="review-feedback-form"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="version_id" value="<?= (int)$r['version_id'] ?>">
    <div class="mb-3"><label class="form-label" for="review-<?= (int)$r['version_id'] ?>">Supervisor Comments &amp; Feedback</label><textarea class="form-control" id="review-<?= (int)$r['version_id'] ?>" name="comment" rows="3" maxlength="5000" placeholder="Enter your overall review findings here..." required></textarea></div>
    <div class="correction-detail-field mb-3"><label class="form-label" for="correction-<?= (int)$r['version_id'] ?>">Specific Required Corrections <span class="text-muted fw-normal">(if any)</span></label><textarea class="form-control" id="correction-<?= (int)$r['version_id'] ?>" name="correction_detail" rows="3" maxlength="5000" placeholder="List individual bullet points the student must resolve..."></textarea></div>
    <div class="decision-grid"><button class="btn btn-success" name="decision" value="approved" data-confirm="Approve this current submission version?">Approve Submission</button><button class="btn btn-warning" name="decision" value="correction_required" data-confirm="Send these correction requirements to the student?">Request Correction</button></div>
    <div class="decision-secondary"><button class="btn btn-sm btn-outline-secondary" name="decision" value="commented">Save feedback only</button><button class="btn btn-sm btn-outline-danger" name="decision" value="rejected" data-confirm="Reject this current submission version?">Reject submission</button></div>
  </form></section>
<?php else: ?><section class="card p-4 mb-3"><p class="text-muted mb-0">Review actions are available only for the current version awaiting review.</p></section><?php endif; ?>
<?php if(!empty($reviewHistory[(int)$r['version_id']])): ?>
  <section class="card p-4"><h2 class="mini-heading">Previous version reviews</h2>
  <?php foreach($reviewHistory[(int)$r['version_id']] as $history): ?><div class="review-history-entry"><div class="section-row"><strong>Review for Version <?= (int)$r['version_number'] ?>.0</strong><small class="text-muted"><?= e(ui_date($history['reviewed_at'])) ?></small></div><div class="status-line is-<?= $history['decision']==='approved'?'ok':'bad' ?>">Status: <?= e(ui_label($history['decision'])) ?></div><p class="mb-0">"<?= nl2br(e($history['comment'])) ?>"</p><small class="text-muted"><?= e(trim(($history['first_name']??'').' '.($history['last_name']??''))) ?></small></div><?php endforeach; ?></section>
<?php endif; ?>
</div></div>
<?php if(!$canReview): ?></div></details><?php endif; ?>
<?php endforeach; ?>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
