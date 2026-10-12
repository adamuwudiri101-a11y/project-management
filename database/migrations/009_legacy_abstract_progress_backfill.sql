-- Preserve progress for projects already approved through the earlier HOD review flow.
-- The reviewer-role condition avoids changing topics selected under the supervisor workflow.
USE fyp_portal;

UPDATE milestones m
JOIN projects p ON p.id = m.project_id
SET m.status = 'completed', m.completed_at = COALESCE(m.completed_at, NOW())
WHERE m.milestone_key = 'abstract'
  AND p.status IN ('supervisor_pending','supervisor_assigned','proposal_submitted','in_progress',
                   'correction_required','final_submission','under_evaluation','approved','completed','archived')
  AND EXISTS (
    SELECT 1
    FROM project_ideas i
    JOIN users reviewer ON reviewer.id = i.reviewed_by
    JOIN roles r ON r.id = reviewer.role_id
    WHERE i.project_id = p.id AND i.review_status = 'approved' AND r.name = 'coordinator'
  );
