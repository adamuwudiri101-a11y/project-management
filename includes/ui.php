<?php
/* Presentation helpers only. Read-only queries and string formatting for views; no writes. */

function ui_date(?string $value, string $format = 'M j, Y'): string
{
    if (!$value) return '—';
    $t = strtotime($value);
    return $t ? date($format, $t) : '—';
}

function ui_label(?string $key): string
{
    return ucwords(str_replace('_', ' ', (string)$key));
}

function ui_doc_label(?string $type): string
{
    return match ($type) {
        'proposal' => 'Proposal',
        'chapter_1' => 'Chapter 1',
        'chapter_2' => 'Chapter 2',
        'chapter_3' => 'Chapter 3',
        'chapter_4' => 'Chapter 4',
        'chapter_5' => 'Chapter 5',
        'final_project' => 'Final Project',
        'supporting_document' => 'Supporting Document',
        default => ui_label($type),
    };
}

function ui_initials(string $name): string
{
    $out = '';
    foreach (preg_split('/\s+/', trim($name)) ?: [] as $part) {
        if ($part !== '') $out .= mb_strtoupper(mb_substr($part, 0, 1));
        if (mb_strlen($out) >= 2) break;
    }
    return $out ?: 'S';
}

/** Milestones with the same status rules the progress page uses (read-only). */
function ui_student_milestones(int $projectId, string $projectStatus): array
{
    $q = db()->prepare('SELECT * FROM milestones WHERE project_id=? ORDER BY id');
    $q->execute([$projectId]);
    $rows = $q->fetchAll();
    $documentFor = ['proposal' => 'proposal', 'chapter_1' => 'chapter_1', 'chapter_2' => 'chapter_2', 'chapter_3' => 'chapter_3', 'chapter_4' => 'chapter_4', 'chapter_5' => 'chapter_5', 'final_submission' => 'final_project'];
    foreach ($rows as &$m) {
        if ($m['milestone_key'] === 'idea') {
            $ideaCheck = db()->prepare("SELECT COUNT(*),SUM(review_status='approved') FROM project_ideas WHERE project_id=?");
            $ideaCheck->execute([$projectId]);
            [$ideaCount, $approvedIdeas] = $ideaCheck->fetch(PDO::FETCH_NUM);
            if ((int)$ideaCount > 0) $m['status'] = (int)$approvedIdeas > 0 ? 'completed' : 'in_progress';
            elseif (in_array($projectStatus, ['proposal_submitted', 'in_progress', 'final_submission', 'under_evaluation', 'approved', 'completed', 'archived'], true)) $m['status'] = 'completed';
        }
        if (isset($documentFor[$m['milestone_key']])) {
            $sq = db()->prepare('SELECT status FROM submissions WHERE project_id=? AND document_type=? ORDER BY submitted_at DESC LIMIT 1');
            $sq->execute([$projectId, $documentFor[$m['milestone_key']]]);
            $s = $sq->fetchColumn();
            if ($s === 'approved' || $s === 'final') $m['status'] = 'completed';
            elseif ($s === 'correction_required') $m['status'] = 'correction_required';
            elseif ($s) $m['status'] = 'in_progress';
        }
        if (in_array($m['milestone_key'], ['evaluation', 'final_approval'], true) && in_array($projectStatus, ['completed', 'archived'], true)) $m['status'] = 'completed';
    }
    unset($m);
    return $rows;
}

function ui_milestone_done(array $m): bool
{
    return in_array($m['status'], ['completed', 'waived'], true);
}

function ui_progress_percent(array $milestones): int
{
    if (!$milestones) return 0;
    return (int)round(count(array_filter($milestones, 'ui_milestone_done')) / count($milestones) * 100);
}

/** First milestone that is not finished. */
function ui_current_milestone(array $milestones): ?array
{
    foreach ($milestones as $m) {
        if (!ui_milestone_done($m)) return $m;
    }
    return null;
}

/** Horizontal roadmap stepper (done = check, current = gold number, rest = grey number). */
function ui_stepper(array $milestones): string
{
    $current = ui_current_milestone($milestones);
    $html = '<ol class="ui-stepper">';
    $prevDone = true;
    foreach ($milestones as $i => $m) {
        $done = ui_milestone_done($m);
        $isCurrent = $current && $current['id'] === $m['id'];
        $state = $done ? 'is-done' : ($isCurrent ? 'is-current' : 'is-pending');
        if ($i > 0) $html .= '<li class="ui-step-line ' . ($prevDone ? 'is-done' : '') . '" aria-hidden="true"></li>';
        $dot = $done
            ? '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12.5 4.5 4.5L19 7.5"/></svg>'
            : (string)($i + 1);
        $html .= '<li class="ui-step ' . $state . '"' . ($isCurrent ? ' aria-current="step"' : '') . '><span class="ui-step-dot">' . $dot . '</span><span class="ui-step-label">' . e($m['label']) . '</span></li>';
        $prevDone = $done;
    }
    return $html . '</ol>';
}

/** Small inline icon set used in view markup. */
function ui_icon(string $name, int $size = 18): string
{
    $paths = [
        'file' => '<path d="M14 3H7.5A2.5 2.5 0 0 0 5 5.5v13A2.5 2.5 0 0 0 7.5 21h9a2.5 2.5 0 0 0 2.5-2.5V8l-5-5Z"/><path d="M14 3v5h5M9 13h6M9 17h6"/>',
        'upload' => '<path d="M7 18a4.5 4.5 0 0 1-.9-8.9 6 6 0 0 1 11.7 1.4A3.8 3.8 0 0 1 17.5 18"/><path d="m9 13.5 3-3 3 3M12 10.5V20"/>',
        'chat' => '<path d="M20.5 12a8 8 0 0 1-11.8 7L3.5 20.5 5 15.8A8 8 0 1 1 20.5 12Z"/>',
        'alert' => '<circle cx="12" cy="12" r="9"/><path d="M12 7.5v5.5M12 16.5h.01"/>',
        'check' => '<circle cx="12" cy="12" r="9"/><path d="m8.5 12.5 2.5 2.5 4.5-5"/>',
        'eye' => '<path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/>',
        'book' => '<path d="M12 6.5C10.3 5 7.8 4.3 3.5 4.5v13.2c4.3-.2 6.8.5 8.5 2 1.7-1.5 4.2-2.2 8.5-2V4.5C16.2 4.3 13.7 5 12 6.5Z"/><path d="M12 6.5v13.2"/>',
        'user' => '<circle cx="12" cy="8" r="4"/><path d="M4.5 20.5c.6-4 3.6-6 7.5-6s6.9 2 7.5 6"/>',
        'search' => '<circle cx="11" cy="11" r="6.5"/><path d="m20.5 20.5-4.8-4.8"/>',
        'chevron' => '<path d="m6 9 6 6 6-6"/>',
        'download' => '<path d="M12 4v11M7 11l5 5 5-5M5 20h14"/>',
        'users' => '<circle cx="9" cy="8" r="3.4"/><path d="M2.5 20c.4-3.6 3-5.5 6.5-5.5s6.1 1.9 6.5 5.5"/><path d="M16 4.7a3.4 3.4 0 0 1 0 6.6M18 14.8c2 .7 3.2 2.4 3.5 5.2"/>',
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'bell' => '<path d="M6 9a6 6 0 1 1 12 0c0 5 2 6.5 2 6.5H4S6 14 6 9Z"/><path d="M10 19a2.2 2.2 0 0 0 4 0"/>',
    ];
    $d = $paths[$name] ?? '';
    return '<svg class="ui-icon" viewBox="0 0 24 24" width="' . $size . '" height="' . $size . '" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $d . '</svg>';
}

/** Sentence-case status pill used inside tables. */
function ui_pill(string $status): string
{
    $class = match ($status) {
        'approved', 'completed', 'resolved', 'final', 'idea_approved' => 'ok',
        'rejected', 'correction_required', 'overdue', 'idea_rejected' => 'bad',
        'submitted', 'under_review', 'idea_under_review', 'pending', 'open' => 'warn',
        'resubmitted', 'in_progress', 'scheduled' => 'info',
        default => 'muted',
    };
    return '<span class="ui-pill is-' . $class . '">' . e(ui_label($status)) . '</span>';
}
