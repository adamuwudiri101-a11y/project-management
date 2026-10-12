-- Existing data was checked for duplicate logical submission keys before adding this constraint.
-- This enforces one versioned submission record per project, student, and document stage.
USE fyp_portal;

ALTER TABLE submissions
  ADD UNIQUE KEY uq_project_student_document (project_id, student_id, document_type);
