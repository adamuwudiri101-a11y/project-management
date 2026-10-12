-- Apply once after migrations 002 and 003. Adds faculty-scoped read-only Dean accounts.
USE fyp_portal;
ALTER TABLE users ADD COLUMN faculty_id INT UNSIGNED NULL AFTER role_id;
ALTER TABLE users ADD CONSTRAINT fk_users_faculty FOREIGN KEY(faculty_id) REFERENCES faculties(id) ON UPDATE CASCADE ON DELETE SET NULL;
ALTER TABLE users ADD INDEX idx_users_faculty_role(faculty_id,role_id);
INSERT IGNORE INTO roles(name,description) VALUES('dean','Read-only faculty oversight');

-- Fictional development-only faculty Dean. Same development password as other seed accounts.
INSERT INTO users(role_id,faculty_id,staff_number,first_name,last_name,email,password_hash,must_change_password)
SELECT r.id,f.id,'DEV-DEAN-001','Demo','Dean','dean@example.test',
 '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',1
FROM roles r JOIN faculties f ON f.code='FCOM'
WHERE r.name='dean' AND NOT EXISTS(SELECT 1 FROM users WHERE email='dean@example.test');
