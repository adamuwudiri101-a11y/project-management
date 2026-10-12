-- Additive workflow and account recovery support for the existing NAUB portal.
-- Apply once to an existing fyp_portal database.
USE fyp_portal;

ALTER TABLE users
  ADD COLUMN email_verified_at DATETIME NULL AFTER email;

ALTER TABLE submissions
  MODIFY document_type ENUM(
    'abstract','proposal','chapter_1','chapter_2','chapter_3','chapter_4',
    'chapter_5','final_project','supporting_document'
  ) NOT NULL;

ALTER TABLE milestones
  MODIFY milestone_key ENUM(
    'idea','abstract','proposal','chapter_1','chapter_2','chapter_3','chapter_4',
    'chapter_5','final_submission','evaluation','final_approval'
  ) NOT NULL;

CREATE TABLE email_verification_tokens (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL UNIQUE,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_email_verification_user FOREIGN KEY (user_id)
    REFERENCES users(id) ON UPDATE CASCADE ON DELETE CASCADE,
  INDEX idx_email_verification_user_expiry (user_id, expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE password_reset_tokens (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL UNIQUE,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_password_reset_user FOREIGN KEY (user_id)
    REFERENCES users(id) ON UPDATE CASCADE ON DELETE CASCADE,
  INDEX idx_password_reset_user_expiry (user_id, expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Keep the existing proposal stage as a backward-compatible predecessor for
-- projects already using the older workflow. New projects use Abstract.
INSERT INTO milestones (project_id, milestone_key, label, status, completed_at)
SELECT p.id, 'abstract', 'Abstract',
       IF(p.status IN ('supervisor_pending','supervisor_assigned','proposal_submitted','in_progress',
                       'correction_required','final_submission','under_evaluation','approved','completed','archived')
          OR EXISTS (
         SELECT 1 FROM submissions s
         WHERE s.project_id = p.id AND s.document_type = 'proposal'
           AND s.status = 'approved'
       ), 'completed', 'pending'),
       IF(p.status IN ('supervisor_pending','supervisor_assigned','proposal_submitted','in_progress',
                       'correction_required','final_submission','under_evaluation','approved','completed','archived')
          OR EXISTS (
         SELECT 1 FROM submissions s
         WHERE s.project_id = p.id AND s.document_type = 'proposal'
           AND s.status = 'approved'
       ), NOW(), NULL)
FROM projects p
WHERE NOT EXISTS (
  SELECT 1 FROM milestones m WHERE m.project_id = p.id AND m.milestone_key = 'abstract'
);
