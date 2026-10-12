-- Development seed data only. Every seeded account uses password: password
-- Change these credentials immediately if the database is used beyond a local demo.
USE fyp_portal;

INSERT INTO roles (id, name, description) VALUES
  (1, 'administrator', 'System and account administration'),
  (2, 'coordinator', 'Final year project cycle coordination'),
  (3, 'supervisor', 'Project supervision and submission review'),
  (4, 'student', 'Student project registration and submissions'),
  (5, 'examiner', 'Assigned project evaluation'),
  (6, 'dean', 'Read-only faculty oversight');

INSERT INTO faculties (id, code, name) VALUES
  (1,'FAMSS','Faculty of Arts, Management and Social Sciences'),
  (2,'FCOM','Faculty of Computing'),
  (3,'FENG','Faculty of Engineering Technology'),
  (4,'FEVS','Faculty of Environmental Sciences'),
  (5,'FNAS','Faculty of Natural & Applied Sciences');

INSERT INTO departments (id, faculty_id, code, name) VALUES
  (1,2,'COMP','Computing'),
  (2,2,'CYBER','Cyber Security'),
  (3,2,'IS','Information System'),
  (23,2,'SOFTENG','Software Engineering'),
  (24,2,'IT','Information Technology'),
  (4,1,'ACC','Accounting'),
  (5,1,'LANG','Languages'),
  (6,1,'MHIST','Military History'),
  (7,1,'MGMT','Management'),
  (8,1,'POL','Political Sciences'),
  (9,1,'SOC','Sociology'),
  (10,1,'GEO','Geography'),
  (11,3,'CIVIL','Civil Engineering'),
  (12,3,'ELECT','Electrical and Electronic Engineering'),
  (13,3,'MECH','Mechanical Engineering'),
  (14,4,'BUILD','Building'),
  (15,4,'ENVM','Environmental Management'),
  (16,4,'ESTATE','Estate Management'),
  (17,4,'SURVEY','Surveying and Geoinformatics'),
  (18,4,'URP','Urban and Regional Planning'),
  (19,5,'BIO','Biology'),
  (20,5,'CHEM','Chemistry'),
  (21,5,'MATH','Mathematics'),
  (22,5,'PHYS','Physics');

UPDATE departments SET description='French is a servicing unit for intra-faculty requirements; it is not a degree programme.' WHERE code='LANG';

INSERT INTO programmes (id, department_id, code, name, award) VALUES
  (1, 1, 'BSC-CS', 'B.Sc. Computer Science', 'B.Sc.'),
  (2,2,'BSC-CYBER','B.Sc. Cyber Security','B.Sc.'),
  (3,3,'BSC-IS','B.Sc. Information System','B.Sc.'),
  (4,24,'BSC-IT','B.Sc. Information Technology','B.Sc.'),
  (5,23,'BSC-SE','B.Sc. Software Engineering','B.Sc.'),
  (6,4,'BSC-ACC','B.Sc. Accounting','B.Sc.'),
  (7,5,'BA-ARABIC','B.A. Arabic','B.A.'),
  (8,5,'BA-ENGLISH','B.A. English','B.A.'),
  (9,6,'BA-MHIST','B.A. Military History','B.A.'),
  (10,7,'BSC-ECON','B.Sc. Economics','B.Sc.'),
  (11,7,'BSC-MGMT','B.Sc. Management','B.Sc.'),
  (12,7,'BSC-TLM','B.Sc. Transport and Logistics','B.Sc.'),
  (13,9,'BSC-CRIM','B.Sc. Criminology and Security Studies','B.Sc.'),
  (14,10,'BSC-GEO','B.Sc. Geography','B.Sc.'),
  (15,8,'BSC-IR','B.Sc. International Relations','B.Sc.'),
  (16,8,'BSC-PSCR','B.Sc. Peace and Conflict Relations','B.Sc.'),
  (17,8,'BSC-POLS','B.Sc. Political Science','B.Sc.'),
  (18,9,'BSC-PSY','B.Sc. Psychology','B.Sc.'),
  (19,9,'BSC-SOC','B.Sc. Sociology','B.Sc.'),
  (20,11,'BENG-CIVIL','B.Eng. Civil Engineering','B.Eng.'),
  (21,12,'BENG-EEE','B.Eng. Electrical and Electronic Engineering','B.Eng.'),
  (22,13,'BENG-MECH','B.Eng. Mechanical Engineering','B.Eng.'),
  (23,14,'BTECH-BUILD','B.Tech. Building','B.Tech.'),
  (24,15,'BTECH-ENVM','B.Tech. Environmental Management','B.Tech.'),
  (25,16,'BTECH-ESTATE','B.Tech. Estate Management','B.Tech.'),
  (26,17,'BTECH-SURVEY','B.Tech. Survey and Geo-Informatics','B.Tech.'),
  (27,18,'BTECH-URP','B.Tech. Urban and Regional Planning','B.Tech.'),
  (28,19,'BSC-BIO','B.Sc. Biology','B.Sc.'),
  (29,20,'BSC-CHEM','B.Sc. Chemistry','B.Sc.'),
  (30,21,'BSC-MATH','B.Sc. Mathematics','B.Sc.'),
  (31,22,'BSC-PHYS','B.Sc. Physics','B.Sc.');

INSERT INTO academic_sessions (id, name, starts_on, ends_on, is_current) VALUES
  (1, '2026/2027', '2026-09-01', '2027-08-31', 1);

-- This PHP-compatible bcrypt hash is for the deliberately simple local demo password above.
INSERT INTO users
  (id, role_id, faculty_id, department_id, programme_id, matric_number, staff_number,
   first_name, last_name, email, password_hash, must_change_password)
VALUES
  (1, 1, NULL, NULL, NULL, NULL, 'DEV-ADMIN-001', 'Demo', 'Administrator', 'admin@example.test', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1),
  (2, 2, NULL, 1, NULL, NULL, 'DEV-COORD-001', 'Demo', 'HOD', 'coordinator@example.test', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1),
  (3, 3, NULL, 1, NULL, NULL, 'DEV-SUP-001', 'Demo', 'Supervisor', 'supervisor@example.test', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1),
  (4, 4, NULL, 1, 1, 'DEMO/2022/001', NULL, 'Demo', 'Student', 'student@example.test', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1),
  (5, 5, NULL, 1, NULL, NULL, 'DEV-EXAM-001', 'Demo', 'Examiner', 'examiner@example.test', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1),
  (6, 6, 2, NULL, NULL, NULL, 'DEV-DEAN-001', 'Demo', 'Dean', 'dean@example.test', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1);

INSERT INTO examiners (user_id, expertise) VALUES
  (5, 'Software engineering; information systems');

INSERT INTO evaluation_criteria
  (academic_session_id, name, description, max_score, weight, evaluator_role, sort_order)
VALUES
  (1, 'Problem definition', 'Clarity and relevance of the identified problem', 10, 1.000, 'examiner', 1),
  (1, 'Literature review', 'Coverage and critical use of relevant literature', 15, 1.000, 'examiner', 2),
  (1, 'Methodology', 'Suitability and clarity of the adopted method', 15, 1.000, 'examiner', 3),
  (1, 'System implementation', 'Functionality and quality of the implemented system', 25, 1.000, 'examiner', 4),
  (1, 'Documentation', 'Organization, clarity, and completeness of the report', 15, 1.000, 'examiner', 5),
  (1, 'Presentation', 'Clarity and command of the project during presentation', 10, 1.000, 'examiner', 6),
  (1, 'Technical quality', 'Technical soundness, testing, and security considerations', 10, 1.000, 'examiner', 7);
