-- Align active registration options with the user's confirmed 31-programme list.
USE fyp_portal;

INSERT IGNORE INTO departments(faculty_id,code,name)
SELECT id,'MATH','Mathematics' FROM faculties WHERE code='FNAS';

UPDATE departments SET is_active=1 WHERE code='MATH';
UPDATE programmes SET department_id=(SELECT id FROM departments WHERE code='MATH'),name='B.Sc. Mathematics',award='B.Sc.',is_active=1 WHERE code='BSC-MATH';
UPDATE programmes SET name='B.Sc. Peace and Conflict Relations',award='B.Sc.',is_active=1 WHERE code='BSC-PSCR';
UPDATE programmes SET name='B.Tech. Survey and Geo-Informatics',award='B.Tech.',is_active=1 WHERE code='BTECH-SURVEY';
