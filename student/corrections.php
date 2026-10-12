<?php
require_once dirname(__DIR__).'/includes/auth.php';$u=require_role('student');$status=(string)($_GET['status']??'');
$params=[$u['id']];$filter='';if(in_array($status,['open','in_progress','resubmitted','resolved'],true)){$filter=' AND c.status=?';$params[]=$status;}
$q=db()->prepare('SELECT c.*,r.comment review_comment,v.id version_id,v.version_number,s.document_type,p.title,p.reference_no FROM corrections c JOIN submission_reviews r ON r.id=c.submission_review_id JOIN submission_versions v ON v.id=r.submission_version_id JOIN submissions s ON s.id=v.submission_id JOIN projects p ON p.id=s.project_id WHERE c.assigned_to=?'.$filter.' ORDER BY c.created_at DESC');$q->execute($params);$rows=$q->fetchAll();
$pageTitle='Supervisor Corrections';$crumbTrail=['Corrections'];require dirname(__DIR__).'/includes/header.php';
?>
<?php $statusCounts=[];foreach($rows as $r){$statusCounts[$r['status']]=($statusCounts[$r['status']]??0)+1;} ?>
<section class="card filter-bar mb-3"><form method="get" class="filter-form" id="correctionFilter"><label class="search-box"><?= ui_icon('search') ?><input type="search" class="form-control" placeholder="Search corrections..." aria-label="Search corrections" data-live-filter="#correctionList [data-filter-text]"></label><div class="filter-selects"><select class="form-select" id="status" name="status" aria-label="Correction status" data-autosubmit><option value="">All Statuses</option><?php foreach(['open','in_progress','resubmitted','resolved'] as $s2): ?><option value="<?= e($s2) ?>" <?= $status===$s2?'selected':'' ?>><?= e(ui_label($s2)) ?></option><?php endforeach; ?></select><noscript><button class="btn btn-primary btn-sm">Filter</button></noscript></div></form></section>
<div id="correctionList">
<?php if(!$rows): ?><section class="card empty-state">No correction requests match this filter.</section><?php else: foreach($rows as $r): $isOpen=in_array($r['status'],['open','in_progress','resubmitted'],true); ?>
<?php if($isOpen): ?>
<article class="card correction-card-new p-4 mb-3" data-filter-text="<?= e(strtolower(ui_doc_label($r['document_type']).' '.$r['description'].' '.$r['title'])) ?>">
  <div class="correction-head"><div><span class="stage-pill is-warn"><?= e(ui_doc_label($r['document_type'])) ?></span><span class="issued">Issued: <?= e(ui_date($r['created_at'])) ?></span><h2><?= e(ui_doc_label($r['document_type'])) ?> Correction</h2></div><div class="supervisor-chip"><div><strong><?= e($r['reference_no']) ?></strong><small>Project record</small></div></div></div>
  <hr>
  <?php if($r['review_comment']&&$r['review_comment']!==$r['description']): ?><p class="feedback-label">Supervisor feedback &amp; remarks:</p><p class="feedback-quote">"<?= nl2br(e($r['review_comment'])) ?>"</p><?php endif; ?>
  <div class="required-box"><h3>Required academic corrections</h3><p><?= nl2br(e($r['description'])) ?></p></div>
  <div class="correction-foot"><a class="related-link" href="<?= e(url('student/submission-details.php?version='.(int)$r['version_id'])) ?>"><?= ui_icon('file',16) ?>Related Submission: version <?= (int)$r['version_number'] ?></a><div class="d-flex gap-2 flex-wrap align-items-center"><small class="text-muted">Due <?= e(ui_date($r['due_at'])) ?></small><a class="btn btn-outline-primary" href="<?= e(url('student/submission-details.php?version='.(int)$r['version_id'])) ?>">View feedback</a><a class="btn btn-danger" href="<?= e(url('student/submissions.php?new=1')) ?>"><?= ui_icon('upload',16) ?>Submit Corrected Version</a></div></div>
</article>
<?php else: ?>
<details class="card correction-collapsed mb-3" data-filter-text="<?= e(strtolower(ui_doc_label($r['document_type']).' '.$r['description'].' '.$r['title'])) ?>"><summary><div><span class="stage-pill is-ok"><?= e(ui_doc_label($r['document_type'])) ?></span><span class="issued">Issued: <?= e(ui_date($r['created_at'])) ?></span><h2><?= e(ui_doc_label($r['document_type'])) ?> Correction</h2></div><span class="resolved-note">Resolved &amp; Approved<span class="chev"><?= ui_icon('chevron',16) ?></span></span></summary><div class="collapsed-body"><div class="required-box"><h3>Corrections requested</h3><p><?= nl2br(e($r['description'])) ?></p></div><a class="related-link" href="<?= e(url('student/submission-details.php?version='.(int)$r['version_id'])) ?>"><?= ui_icon('file',16) ?>View submission and feedback</a></div></details>
<?php endif; ?>
<?php endforeach; endif; ?>
</div>
<?php require dirname(__DIR__).'/includes/footer.php'; ?>
