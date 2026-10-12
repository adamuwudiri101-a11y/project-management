-- NAUB Final Year Project Management and Supervision System
-- MySQL 8.0+ / MariaDB 10.4+; import this file before seed.sql.
CREATE DATABASE IF NOT EXISTS fyp_portal
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE fyp_portal;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;


CREATE TABLE IF NOT EXISTS roles (
  id TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(40) NOT NULL UNIQUE,
  description VARCHAR(255) NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS faculties (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(20) NOT NULL UNIQUE,
  name VARCHAR(160) NOT NULL UNIQUE,
  is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS departments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  faculty_id INT UNSIGNED NOT NULL,
  code VARCHAR(20) NOT NULL UNIQUE,
  name VARCHAR(120) NOT NULL UNIQUE,
  description VARCHAR(500) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_departments_faculty FOREIGN KEY (faculty_id) REFERENCES faculties(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS programmes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  department_id INT UNSIGNED NOT NULL,
  code VARCHAR(30) NOT NULL UNIQUE,
  name VARCHAR(120) NOT NULL,
  award VARCHAR(80) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY uq_programme_dept_name (department_id, name),
  CONSTRAINT fk_programmes_department FOREIGN KEY (department_id) REFERENCES departments(id)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS academic_sessions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(20) NOT NULL UNIQUE,
  starts_on DATE NOT NULL,
  ends_on DATE NOT NULL,
  is_current TINYINT(1) NOT NULL DEFAULT 0,
  is_closed TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT chk_session_dates CHECK (ends_on >= starts_on),
  INDEX idx_sessions_current (is_current, is_closed)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  role_id TINYINT UNSIGNED NOT NULL,
  faculty_id INT UNSIGNED NULL,
  department_id INT UNSIGNED NULL,
  programme_id INT UNSIGNED NULL,
  matric_number VARCHAR(40) NULL UNIQUE,
  staff_number VARCHAR(40) NULL UNIQUE,
  first_name VARCHAR(80) NULL,
  last_name VARCHAR(80) NULL,
  email VARCHAR(190) NULL UNIQUE,
  email_verified_at DATETIME NULL,
  phone VARCHAR(30) NULL,
  profile_complete TINYINT(1) NOT NULL DEFAULT 1,
  password_hash VARCHAR(255) NOT NULL,
  account_status ENUM('active','inactive','locked') NOT NULL DEFAULT 'active',
  must_change_password TINYINT(1) NOT NULL DEFAULT 1,
  last_login_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles(id) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_users_faculty FOREIGN KEY (faculty_id) REFERENCES faculties(id) ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT fk_users_department FOREIGN KEY (department_id) REFERENCES departments(id) ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT fk_users_programme FOREIGN KEY (programme_id) REFERENCES programmes(id) ON UPDATE CASCADE ON DELETE SET NULL,
  INDEX idx_users_role_status (role_id, account_status),
  INDEX idx_users_department_role (department_id, role_id),
  INDEX idx_users_faculty_role (faculty_id, role_id),
  INDEX idx_users_name (last_name, first_name)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS email_verification_tokens (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL UNIQUE,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_email_verification_user FOREIGN KEY (user_id)
    REFERENCES users(id) ON UPDATE CASCADE ON DELETE CASCADE,
  INDEX idx_email_verification_user_expiry (user_id, expires_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS password_reset_tokens (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL UNIQUE,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_password_reset_user FOREIGN KEY (user_id)
    REFERENCES users(id) ON UPDATE CASCADE ON DELETE CASCADE,
  INDEX idx_password_reset_user_expiry (user_id, expires_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS system_settings (
  setting_key VARCHAR(64) PRIMARY KEY,
  setting_value VARCHAR(255) NOT NULL,
  updated_by BIGINT UNSIGNED NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_system_settings_updater FOREIGN KEY (updated_by)
    REFERENCES users(id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS student_supervisors (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  student_id BIGINT UNSIGNED NOT NULL,
  supervisor_id BIGINT UNSIGNED NOT NULL,
  assigned_by BIGINT UNSIGNED NOT NULL,
  assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ended_at DATETIME NULL,
  INDEX idx_student_supervisor_active (student_id, ended_at),
  INDEX idx_supervisor_student_active (supervisor_id, ended_at),
  CONSTRAINT fk_student_supervisor_student FOREIGN KEY (student_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_student_supervisor_supervisor FOREIGN KEY (supervisor_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_student_supervisor_assigner FOREIGN KEY (assigned_by) REFERENCES users(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS projects (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  reference_no VARCHAR(40) NOT NULL UNIQUE,
  title VARCHAR(255) NOT NULL,
  description TEXT NULL,
  department_id INT UNSIGNED NOT NULL,
  programme_id INT UNSIGNED NOT NULL,
  academic_session_id INT UNSIGNED NOT NULL,
  status ENUM('idea_submitted','idea_under_review','idea_approved','idea_rejected','supervisor_pending','supervisor_assigned','proposal_submitted','in_progress','correction_required','final_submission','under_evaluation','approved','completed','archived') NOT NULL DEFAULT 'idea_submitted',
  registered_at DATETIME NULL,
  approved_at DATETIME NULL,
  completed_at DATETIME NULL,
  archived_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_projects_department FOREIGN KEY (department_id) REFERENCES departments(id) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_projects_programme FOREIGN KEY (programme_id) REFERENCES programmes(id) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_projects_session FOREIGN KEY (academic_session_id) REFERENCES academic_sessions(id) ON UPDATE CASCADE ON DELETE RESTRICT,
  INDEX idx_projects_status_session (status, academic_session_id),
  INDEX idx_projects_department (department_id),
  INDEX idx_projects_title (title)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS project_members (
  project_id BIGINT UNSIGNED NOT NULL,
  student_id BIGINT UNSIGNED NOT NULL,
  member_role ENUM('leader','member') NOT NULL DEFAULT 'leader',
  joined_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (project_id, student_id),
  KEY idx_members_student_project (student_id, project_id),
  CONSTRAINT fk_members_project FOREIGN KEY (project_id) REFERENCES projects(id) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_members_student FOREIGN KEY (student_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE RESTRICT,
  INDEX idx_members_student (student_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS project_ideas (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  project_id BIGINT UNSIGNED NOT NULL,
  submitted_by BIGINT UNSIGNED NOT NULL,
  title VARCHAR(255) NOT NULL,
  description TEXT NOT NULL,
  keywords VARCHAR(500) NULL,
  review_status ENUM('submitted','under_review','approved','rejected','revision_requested') NOT NULL DEFAULT 'submitted',
  reviewed_by BIGINT UNSIGNED NULL,
  reviewer_comment TEXT NULL,
  submitted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  reviewed_at DATETIME NULL,
  CONSTRAINT fk_ideas_project FOREIGN KEY (project_id) REFERENCES projects(id) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_ideas_submitter FOREIGN KEY (submitted_by) REFERENCES users(id) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_ideas_reviewer FOREIGN KEY (reviewed_by) REFERENCES users(id) ON UPDATE CASCADE ON DELETE SET NULL,
  INDEX idx_ideas_status (review_status, submitted_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS project_similarity_checks (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  project_id BIGINT UNSIGNED NOT NULL,
  compared_project_id BIGINT UNSIGNED NOT NULL,
  checked_by BIGINT UNSIGNED NOT NULL,
  algorithm_version VARCHAR(40) NOT NULL DEFAULT 'token-jaccard-v1',
  similarity_score DECIMAL(5,2) NOT NULL,
  reviewer_decision ENUM('pending','distinct','potential_duplicate','needs_discussion') NOT NULL DEFAULT 'pending',
  reviewer_note VARCHAR(1000) NULL,
  checked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT chk_similarity_range CHECK (similarity_score >= 0 AND similarity_score <= 100),
  CONSTRAINT chk_similarity_not_self CHECK (project_id <> compared_project_id),
  CONSTRAINT fk_similarity_project FOREIGN KEY (project_id) REFERENCES projects(id) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_similarity_compared FOREIGN KEY (compared_project_id) REFERENCES projects(id) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_similarity_reviewer FOREIGN KEY (checked_by) REFERENCES users(id) ON UPDATE CASCADE ON DELETE RESTRICT,
  KEY idx_similarity_pair_check (project_id, compared_project_id, checked_at),
  INDEX idx_similarity_project_score (project_id, similarity_score)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS project_supervisors (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  project_id BIGINT UNSIGNED NOT NULL,
  supervisor_id BIGINT UNSIGNED NOT NULL,
  assigned_by BIGINT UNSIGNED NOT NULL,
  assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ended_at DATETIME NULL,
  is_primary TINYINT(1) NOT NULL DEFAULT 1,
  assignment_note VARCHAR(500) NULL,
  CONSTRAINT fk_supervisors_project FOREIGN KEY (project_id) REFERENCES projects(id) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_supervisors_user FOREIGN KEY (supervisor_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_supervisors_assigner FOREIGN KEY (assigned_by) REFERENCES users(id) ON UPDATE CASCADE ON DELETE RESTRICT,
  INDEX idx_supervisor_active (supervisor_id, ended_at),
  INDEX idx_project_supervisor_active (project_id, ended_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS submissions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  project_id BIGINT UNSIGNED NOT NULL,
  student_id BIGINT UNSIGNED NOT NULL,
  document_type ENUM('abstract','proposal','chapter_1','chapter_2','chapter_3','chapter_4','chapter_5','final_project','supporting_document') NOT NULL,
  title VARCHAR(200) NULL,
  status ENUM('draft','submitted','under_review','correction_required','resubmitted','approved','rejected','final') NOT NULL DEFAULT 'submitted',
  current_version INT UNSIGNED NOT NULL DEFAULT 1,
  submitted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_submissions_project FOREIGN KEY (project_id) REFERENCES projects(id) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_submissions_student FOREIGN KEY (student_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE RESTRICT,
  UNIQUE KEY uq_project_student_document (project_id, student_id, document_type),
  INDEX idx_submissions_project_type (project_id, document_type),
  INDEX idx_submissions_status_date (status, submitted_at),
  INDEX idx_submissions_student (student_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS submission_versions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  submission_id BIGINT UNSIGNED NOT NULL,
  version_number INT UNSIGNED NOT NULL,
  original_filename VARCHAR(255) NOT NULL,
  stored_filename VARCHAR(255) NOT NULL UNIQUE,
  storage_path VARCHAR(500) NOT NULL,
  file_size_bytes BIGINT UNSIGNED NOT NULL,
  mime_type VARCHAR(120) NOT NULL,
  file_sha256 CHAR(64) NULL,
  uploaded_by BIGINT UNSIGNED NOT NULL,
  uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  change_note VARCHAR(1000) NULL,
  UNIQUE KEY uq_submission_version (submission_id, version_number),
  CONSTRAINT fk_versions_submission FOREIGN KEY (submission_id) REFERENCES submissions(id) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_versions_uploader FOREIGN KEY (uploaded_by) REFERENCES users(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS submission_reviews (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  submission_version_id BIGINT UNSIGNED NOT NULL,
  reviewer_id BIGINT UNSIGNED NOT NULL,
  decision ENUM('commented','correction_required','approved','rejected') NOT NULL,
  comment TEXT NOT NULL,
  reviewed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_reviews_version FOREIGN KEY (submission_version_id) REFERENCES submission_versions(id) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_reviews_reviewer FOREIGN KEY (reviewer_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE RESTRICT,
  INDEX idx_reviews_version_date (submission_version_id, reviewed_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS corrections (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  submission_review_id BIGINT UNSIGNED NOT NULL,
  assigned_to BIGINT UNSIGNED NOT NULL,
  description TEXT NOT NULL,
  status ENUM('open','in_progress','resubmitted','resolved','waived') NOT NULL DEFAULT 'open',
  due_at DATETIME NULL,
  resolved_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_corrections_review FOREIGN KEY (submission_review_id) REFERENCES submission_reviews(id) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_corrections_student FOREIGN KEY (assigned_to) REFERENCES users(id) ON UPDATE CASCADE ON DELETE RESTRICT,
  INDEX idx_corrections_assignee_status (assigned_to, status)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS milestones (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  project_id BIGINT UNSIGNED NOT NULL,
  milestone_key ENUM('idea','abstract','proposal','chapter_1','chapter_2','chapter_3','chapter_4','chapter_5','final_submission','evaluation','final_approval') NOT NULL,
  label VARCHAR(120) NOT NULL,
  status ENUM('pending','in_progress','correction_required','completed','waived') NOT NULL DEFAULT 'pending',
  completed_at DATETIME NULL,
  updated_by BIGINT UNSIGNED NULL,
  notes VARCHAR(1000) NULL,
  UNIQUE KEY uq_project_milestone (project_id, milestone_key),
  CONSTRAINT fk_milestones_project FOREIGN KEY (project_id) REFERENCES projects(id) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_milestones_user FOREIGN KEY (updated_by) REFERENCES users(id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS deadlines (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  academic_session_id INT UNSIGNED NOT NULL,
  department_id INT UNSIGNED NULL,
  milestone_key ENUM('idea','proposal','chapter_1','chapter_2','chapter_3','chapter_4','chapter_5','final_submission','evaluation') NOT NULL,
  title VARCHAR(160) NOT NULL,
  due_at DATETIME NOT NULL,
  created_by BIGINT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_deadlines_session FOREIGN KEY (academic_session_id) REFERENCES academic_sessions(id) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_deadlines_department FOREIGN KEY (department_id) REFERENCES departments(id) ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT fk_deadlines_creator FOREIGN KEY (created_by) REFERENCES users(id) ON UPDATE CASCADE ON DELETE RESTRICT,
  INDEX idx_deadlines_due (due_at),
  INDEX idx_deadlines_scope (academic_session_id, department_id, milestone_key)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS supervision_meetings (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  project_id BIGINT UNSIGNED NOT NULL,
  supervisor_id BIGINT UNSIGNED NOT NULL,
  meeting_at DATETIME NOT NULL,
  meeting_type ENUM('in_person','online','email','other') NOT NULL DEFAULT 'in_person',
  discussion_notes TEXT NOT NULL,
  tasks_assigned TEXT NULL,
  next_meeting_at DATETIME NULL,
  status ENUM('scheduled','completed','cancelled') NOT NULL DEFAULT 'completed',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_meetings_project FOREIGN KEY (project_id) REFERENCES projects(id) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_meetings_supervisor FOREIGN KEY (supervisor_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE RESTRICT,
  INDEX idx_meetings_project_date (project_id, meeting_at),
  INDEX idx_meetings_supervisor (supervisor_id, meeting_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS examiners (
  user_id BIGINT UNSIGNED PRIMARY KEY,
  expertise VARCHAR(500) NULL,
  is_available TINYINT(1) NOT NULL DEFAULT 1,
  CONSTRAINT fk_examiners_user FOREIGN KEY (user_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS project_examiners (
  project_id BIGINT UNSIGNED NOT NULL,
  examiner_id BIGINT UNSIGNED NOT NULL,
  assigned_by BIGINT UNSIGNED NOT NULL,
  assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  evaluation_due_at DATETIME NULL,
  status ENUM('assigned','in_progress','submitted','withdrawn') NOT NULL DEFAULT 'assigned',
  PRIMARY KEY (project_id, examiner_id),
  CONSTRAINT fk_project_examiners_project FOREIGN KEY (project_id) REFERENCES projects(id) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_project_examiners_examiner FOREIGN KEY (examiner_id) REFERENCES examiners(user_id) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_project_examiners_assigner FOREIGN KEY (assigned_by) REFERENCES users(id) ON UPDATE CASCADE ON DELETE RESTRICT,
  INDEX idx_examiner_assignments (examiner_id, status)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS evaluation_criteria (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  academic_session_id INT UNSIGNED NULL,
  department_id INT UNSIGNED NULL,
  name VARCHAR(120) NOT NULL,
  description VARCHAR(500) NULL,
  max_score DECIMAL(7,2) NOT NULL,
  weight DECIMAL(7,3) NOT NULL DEFAULT 1.000,
  evaluator_role ENUM('examiner','supervisor','coordinator') NOT NULL DEFAULT 'examiner',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  CONSTRAINT chk_criteria_max_score CHECK (max_score > 0),
  CONSTRAINT chk_criteria_weight CHECK (weight > 0),
  CONSTRAINT fk_criteria_session FOREIGN KEY (academic_session_id) REFERENCES academic_sessions(id) ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT fk_criteria_department FOREIGN KEY (department_id) REFERENCES departments(id) ON UPDATE CASCADE ON DELETE SET NULL,
  INDEX idx_criteria_active_order (is_active, sort_order)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS evaluations (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  project_id BIGINT UNSIGNED NOT NULL,
  evaluator_id BIGINT UNSIGNED NOT NULL,
  evaluator_role ENUM('examiner','supervisor','coordinator') NOT NULL,
  overall_comment TEXT NULL,
  status ENUM('draft','submitted') NOT NULL DEFAULT 'draft',
  total_score DECIMAL(8,2) NULL,
  submitted_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_evaluations_project FOREIGN KEY (project_id) REFERENCES projects(id) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_evaluations_evaluator FOREIGN KEY (evaluator_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE RESTRICT,
  UNIQUE KEY uq_project_evaluator_evaluation (project_id, evaluator_id),
  INDEX idx_evaluations_status (status, submitted_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS evaluation_scores (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  evaluation_id BIGINT UNSIGNED NOT NULL,
  criterion_id INT UNSIGNED NOT NULL,
  score DECIMAL(7,2) NOT NULL,
  comment VARCHAR(1500) NULL,
  CONSTRAINT fk_scores_evaluation FOREIGN KEY (evaluation_id) REFERENCES evaluations(id) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_scores_criterion FOREIGN KEY (criterion_id) REFERENCES evaluation_criteria(id) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT chk_score_nonnegative CHECK (score >= 0),
  UNIQUE KEY uq_evaluation_criterion (evaluation_id, criterion_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS notifications (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  title VARCHAR(180) NOT NULL,
  message VARCHAR(1000) NOT NULL,
  type ENUM('info','success','warning','action') NOT NULL DEFAULT 'info',
  entity_type VARCHAR(50) NULL,
  entity_id BIGINT UNSIGNED NULL,
  read_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE CASCADE,
  INDEX idx_notifications_user_read_date (user_id, read_at, created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS audit_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NULL,
  action VARCHAR(80) NOT NULL,
  entity_type VARCHAR(60) NULL,
  entity_id BIGINT UNSIGNED NULL,
  description VARCHAR(1500) NOT NULL,
  ip_address VARCHAR(45) NULL,
  user_agent VARCHAR(500) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE SET NULL,
  INDEX idx_audit_created (created_at),
  INDEX idx_audit_user_action (user_id, action),
  INDEX idx_audit_entity (entity_type, entity_id)
) ENGINE=InnoDB;

SET FOREIGN_KEY_CHECKS = 1;
