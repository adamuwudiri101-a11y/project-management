-- Apply once to scope HOD-created evaluation criteria to their department.
USE fyp_portal;
ALTER TABLE evaluation_criteria ADD COLUMN department_id INT UNSIGNED NULL AFTER academic_session_id;
ALTER TABLE evaluation_criteria ADD CONSTRAINT fk_criteria_department FOREIGN KEY(department_id) REFERENCES departments(id) ON UPDATE CASCADE ON DELETE SET NULL;
ALTER TABLE evaluation_criteria ADD INDEX idx_criteria_department_active(department_id,is_active,sort_order);
