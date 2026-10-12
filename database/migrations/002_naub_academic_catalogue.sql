-- Apply once to an existing fyp_portal database after the original schema.sql.
USE fyp_portal;
CREATE TABLE IF NOT EXISTS faculties (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, code VARCHAR(20) NOT NULL UNIQUE,
 name VARCHAR(160) NOT NULL UNIQUE, is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;
INSERT IGNORE INTO faculties(code,name) VALUES
 ('FAMSS','Faculty of Arts, Management and Social Sciences'),('FCOM','Faculty of Computing'),
 ('FENG','Faculty of Engineering'),('FEVS','Faculty of Environmental Sciences'),
 ('FNAS','Faculty of Natural and Applied Sciences');

ALTER TABLE departments ADD COLUMN faculty_id INT UNSIGNED NULL AFTER id;
UPDATE departments SET faculty_id=(SELECT id FROM faculties WHERE code='FCOM') WHERE faculty_id IS NULL;
ALTER TABLE departments MODIFY faculty_id INT UNSIGNED NOT NULL;
ALTER TABLE departments ADD CONSTRAINT fk_departments_faculty FOREIGN KEY(faculty_id) REFERENCES faculties(id) ON UPDATE CASCADE ON DELETE RESTRICT;

UPDATE departments SET code='CS',name='Computer Science' WHERE code='CSE';
INSERT IGNORE INTO departments(faculty_id,code,name)
 SELECT f.id,x.code,x.name FROM (SELECT 'FCOM' faculty,'CYBER' code,'Cyber Security' name UNION ALL SELECT 'FCOM','IS','Information Systems' UNION ALL
 SELECT 'FAMSS','ACC','Accounting' UNION ALL SELECT 'FAMSS','LANG','Languages' UNION ALL SELECT 'FAMSS','MHIST','Military History' UNION ALL SELECT 'FAMSS','MGMT','Management' UNION ALL SELECT 'FAMSS','POL','Political Sciences' UNION ALL SELECT 'FAMSS','SOC','Sociology' UNION ALL SELECT 'FAMSS','GEO','Geography' UNION ALL
 SELECT 'FENG','CIVIL','Civil Engineering' UNION ALL SELECT 'FENG','ELECT','Electrical and Electronic Engineering' UNION ALL SELECT 'FENG','MECH','Mechanical Engineering' UNION ALL
 SELECT 'FEVS','BUILD','Building' UNION ALL SELECT 'FEVS','ENVM','Environmental Management' UNION ALL SELECT 'FEVS','ESTATE','Estate Management' UNION ALL SELECT 'FEVS','SURVEY','Survey and Geo-Informatics' UNION ALL SELECT 'FEVS','URP','Urban and Regional Planning' UNION ALL
 SELECT 'FNAS','BIO','Biology' UNION ALL SELECT 'FNAS','CHEM','Chemistry' UNION ALL SELECT 'FNAS','MATH','Mathematics' UNION ALL SELECT 'FNAS','PHYS','Physics') x JOIN faculties f ON f.code=x.faculty;
UPDATE programmes SET code='BSC-CS',name='B.Sc. Computer Science',award='B.Sc.' WHERE code='BSC-CSE';
INSERT IGNORE INTO programmes(department_id,code,name,award)
 SELECT d.id,x.code,x.name,x.award FROM (SELECT 'CYBER' dept,'BSC-CYBER' code,'B.Sc. Cyber Security' name,'B.Sc.' award UNION ALL SELECT 'IS','BSC-IS','B.Sc. Information System','B.Sc.' UNION ALL SELECT 'IS','BSC-IT','B.Sc. Information Technology','B.Sc.' UNION ALL SELECT 'CS','BSC-SE','B.Sc. Software Engineering','B.Sc.' UNION ALL
 SELECT 'ACC','BSC-ACC','B.Sc. Accounting','B.Sc.' UNION ALL SELECT 'LANG','BA-ARABIC','B.A. Arabic','B.A.' UNION ALL SELECT 'LANG','BA-ENGLISH','B.A. English','B.A.' UNION ALL SELECT 'MHIST','BA-MHIST','B.A. Military History','B.A.' UNION ALL SELECT 'MGMT','BSC-ECON','B.Sc. Economics','B.Sc.' UNION ALL SELECT 'MGMT','BSC-MGMT','B.Sc. Management','B.Sc.' UNION ALL SELECT 'MGMT','BSC-TLM','B.Sc. Transport and Logistics Management','B.Sc.' UNION ALL SELECT 'SOC','BSC-CRIM','B.Sc. Criminology and Security Studies','B.Sc.' UNION ALL SELECT 'GEO','BSC-GEO','B.Sc. Geography','B.Sc.' UNION ALL SELECT 'POL','BSC-IR','B.Sc. International Relations','B.Sc.' UNION ALL SELECT 'POL','BSC-PSCR','B.Sc. Peace Studies and Conflict Resolution','B.Sc.' UNION ALL SELECT 'POL','BSC-POLS','B.Sc. Political Science','B.Sc.' UNION ALL SELECT 'SOC','BSC-PSY','B.Sc. Psychology','B.Sc.' UNION ALL SELECT 'SOC','BSC-SOC','B.Sc. Sociology','B.Sc.' UNION ALL
 SELECT 'CIVIL','BTECH-CIVIL','B.Tech. Civil Engineering','B.Tech.' UNION ALL SELECT 'ELECT','BTECH-EEE','B.Tech. Electrical and Electronic Engineering','B.Tech.' UNION ALL SELECT 'MECH','BTECH-MECH','B.Tech. Mechanical Engineering','B.Tech.' UNION ALL SELECT 'BUILD','BTECH-BUILD','B.Tech. Building','B.Tech.' UNION ALL SELECT 'ENVM','BTECH-ENVM','B.Tech. Environmental Management','B.Tech.' UNION ALL SELECT 'ESTATE','BTECH-ESTATE','B.Tech. Estate Management','B.Tech.' UNION ALL SELECT 'SURVEY','BTECH-SURVEY','B.Tech. Survey and Geo-Informatics','B.Tech.' UNION ALL SELECT 'URP','BTECH-URP','B.Tech. Urban and Regional Planning','B.Tech.' UNION ALL SELECT 'BIO','BSC-BIO','B.Sc. Biology','B.Sc.' UNION ALL SELECT 'CHEM','BSC-CHEM','B.Sc. Chemistry','B.Sc.' UNION ALL SELECT 'MATH','BSC-MATH','B.Sc. Mathematics','B.Sc.' UNION ALL SELECT 'PHYS','BSC-PHYS','B.Sc. Physics','B.Sc.') x JOIN departments d ON d.code=x.dept;

-- Merge an earlier local spelling of the Information System department without dropping linked records.
UPDATE programmes SET department_id=(SELECT id FROM departments WHERE code='IS') WHERE department_id IN (SELECT id FROM departments WHERE code='INS');
UPDATE users SET department_id=(SELECT id FROM departments WHERE code='IS') WHERE department_id IN (SELECT id FROM departments WHERE code='INS');
UPDATE projects SET department_id=(SELECT id FROM departments WHERE code='IS') WHERE department_id IN (SELECT id FROM departments WHERE code='INS');
UPDATE deadlines SET department_id=(SELECT id FROM departments WHERE code='IS') WHERE department_id IN (SELECT id FROM departments WHERE code='INS');
DELETE FROM departments WHERE code='INS';
UPDATE departments SET name='Information System' WHERE code='IS';

ALTER TABLE users MODIFY first_name VARCHAR(80) NULL, MODIFY last_name VARCHAR(80) NULL, MODIFY email VARCHAR(190) NULL;
ALTER TABLE users ADD COLUMN profile_complete TINYINT(1) NOT NULL DEFAULT 1 AFTER phone;
CREATE TABLE IF NOT EXISTS student_supervisors (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, student_id BIGINT UNSIGNED NOT NULL,
 supervisor_id BIGINT UNSIGNED NOT NULL, assigned_by BIGINT UNSIGNED NOT NULL,
 assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, ended_at DATETIME NULL,
 INDEX idx_student_supervisor_active(student_id,ended_at), INDEX idx_supervisor_student_active(supervisor_id,ended_at),
 CONSTRAINT fk_student_supervisor_student FOREIGN KEY(student_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE CASCADE,
 CONSTRAINT fk_student_supervisor_supervisor FOREIGN KEY(supervisor_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE RESTRICT,
 CONSTRAINT fk_student_supervisor_assigner FOREIGN KEY(assigned_by) REFERENCES users(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;
