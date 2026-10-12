<?php
require_once dirname(__DIR__).'/includes/auth.php';$u=require_role('student');$versionId=filter_input(INPUT_GET,'version',FILTER_VALIDATE_INT)?:0;
$q=db()->prepare('SELECT v.*,s.id submission_id,s.document_type,s.status submission_status,p.id project_id,p.reference_no,p.title FROM submission_versions v JOIN submissions s ON s.id=v.submission_id JOIN projects p ON p.id=s.project_id JOIN project_members pm ON pm.project_id=p.id WHERE v.id=? AND pm.student_id=?');$q->execute([$versionId,$u['id']]);$version=$q->fetch();if(!$version){http_response_code(404);exit('Submission record not found.');}
$q=db()->prepare('SELECT r.decision,r.comment,r.reviewed_at,CONCAT_WS(" ",u.first_name,u.last_name) reviewer FROM submission_reviews r JOIN users u ON u.id=r.reviewer_id WHERE r.submission_version_id=? ORDER BY r.reviewed_at DESC');$q->execute([$versionId]);$reviews=$q->fetchAll();
$q=db()->prepare('SELECT c.description,c.status,c.due_at,c.created_at FROM corrections c JOIN submission_reviews r ON r.id=c.submission_review_id WHERE r.submission_version_id=? AND c.assigned_to=? ORDER BY c.created_at DESC');$q->execute([$versionId,$u['id']]);$corrections=$q->fetchAll();
$pageTitle='Submission Specifications';require_once dirname(__DIR__).'/includes/ui.php';$crumbTrail=['Submissions',ui_doc_label($version['document_type']).' Review'];require dirname(__DIR__).'/includes/header.php';
?>
<?php $q=db()->prepare('SELECT v.id,v.version_number,v.uploaded_at,s2.status FROM submission_versions v JOIN submissions s2 ON s2.id=v.submission_id WHERE v.submission_id=? ORDER BY v.version_number DESC');$q->execute([(int)$version['submission_id']]);$allVersions=$q->fetchAll();$hasOpenCorrection=false;foreach($corrections as $c0){if(in_array($c0['status'],['open','in_progress'],true))$hasOpenCorrection=true;} ?>
<div class="row g-3"><div class="col-xl-6">
  <section class="card p-4 mb-3"><h2 class="section-heading">Submission Information</h2>
    <div class="section-row"><h3 class="doc-heading"><?= e(ui_doc_label($version['document_type'])) ?></h3><?= ui_pill($version['submission_status']) ?></div>
    <p class="meta-line">Submission instance v<?= (int)$version['version_number'] ?> | Sent <?= e(ui_date($version['uploaded_at'])) ?></p><p class="meta-line mb-0"><?= e($version['reference_no']) ?> · <?= e($version['title']) ?></p></section>
  <section class="card p-4 mb-3"><h2 class="mini-heading">Submitted manuscript</h2>
    <div class="file-row"><span class="file-name"><?= ui_icon('file',20) ?><?= e($version['original_filename']) ?></span><a class="file-download" href="<?= e(url('download.php?version='.(int)$versionId)) ?>">Download (<?= e(number_format((int)$version['file_size_bytes']/1048576,1)) ?> MB)</a></div></section>
  <section class="card p-4"><h2 class="mini-heading">Revision records history</h2>
    <?php foreach($allVersions as $av): ?><div class="revision-row"><span><strong>Version v<?= (int)$av['version_number'] ?></strong> <span class="text-muted">— <?= e(ui_date($av['uploaded_at'])) ?></span></span><span class="revision-actions"><?= ui_pill($av['status']) ?><a href="<?= e(url('download.php?version='.(int)$av['id'])) ?>" class="plain-link">Download v<?= (int)$av['version_number'] ?></a></span></div><?php endforeach; ?></section>
</div><div class="col-xl-6">
  <section class="card p-4 mb-3"><h2 class="section-heading">Supervisor Feedback Comments</h2>
    <?php foreach($reviews as $r): ?><article class="feedback-entry"><div class="person"><span class="person-avatar"><?= e(ui_initials($r['reviewer']?:'Supervisor')) ?></span><div><strong><?= e($r['reviewer']?:'Project supervisor') ?></strong><small>Assigned Supervisor • <?= e(ui_date($r['reviewed_at'])) ?></small></div><span class="ms-auto"><?= ui_pill($r['decision']) ?></span></div><p class="feedback-quote">"<?= nl2br(e($r['comment'])) ?>"</p></article><?php endforeach; ?>
    <?php if(!$reviews): ?><div class="empty-state">This version has not been reviewed yet.</div><?php endif; ?></section>
  <?php if($corrections): ?><section class="card p-4 mb-3"><h2 class="mini-heading">Correction checklist compliance</h2>
    <?php foreach($corrections as $c): $resolved=in_array($c['status'],['resolved','waived'],true); ?><label class="check-row <?= $resolved?'':'is-open' ?>"><span class="check-box <?= $resolved?'is-checked':'' ?>"><?= $resolved?'<svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="#fff" stroke-width="3.4" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12.5 4.5 4.5L19 7.5"/></svg>':'' ?></span><span><?= nl2br(e($c['description'])) ?><small>Issued <?= e(ui_date($c['created_at'])) ?> · Due <?= e(ui_date($c['due_at'])) ?></small></span></label><?php endforeach; ?></section><?php endif; ?>
  <?php if($hasOpenCorrection): ?><a class="btn btn-danger btn-block-full" href="<?= e(url('student/submissions.php?new=1')) ?>">Resubmit Corrected Draft</a><?php endif; ?>
</div></div>
<?php require dirname(__DIR__).'/includes/footer.php'; ?>
