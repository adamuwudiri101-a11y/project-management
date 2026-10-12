-- Apply once to the existing fyp_portal database to align it with the user's confirmed catalogue.
USE fyp_portal;
ALTER TABLE departments ADD COLUMN description VARCHAR(500) NULL AFTER name;

UPDATE faculties SET name='Faculty of Engineering Technology' WHERE code='FENG';
UPDATE faculties SET name='Faculty of Natural & Applied Sciences' WHERE code='FNAS';
UPDATE departments SET code='COMP',name='Computing' WHERE code='CS';
UPDATE departments SET name='Surveying and Geoinformatics' WHERE code='SURVEY';
UPDATE departments SET description='French is a servicing unit for intra-faculty requirements; it is not a degree programme.' WHERE code='LANG';

INSERT IGNORE INTO departments(faculty_id,code,name)
 SELECT id,'SOFTENG','Software Engineering' FROM faculties WHERE code='FCOM';
INSERT IGNORE INTO departments(faculty_id,code,name)
 SELECT id,'IT','Information Technology' FROM faculties WHERE code='FCOM';

UPDATE programmes SET department_id=(SELECT id FROM departments WHERE code='IT') WHERE code='BSC-IT';
UPDATE programmes SET department_id=(SELECT id FROM departments WHERE code='SOFTENG') WHERE code='BSC-SE';
UPDATE users u JOIN programmes p ON p.id=u.programme_id SET u.department_id=p.department_id WHERE p.code IN ('BSC-IT','BSC-SE');
UPDATE projects pr JOIN programmes p ON p.id=pr.programme_id SET pr.department_id=p.department_id WHERE p.code IN ('BSC-IT','BSC-SE');
UPDATE programmes SET name='B.Sc. Transport and Logistics',award='B.Sc.' WHERE code='BSC-TLM';
UPDATE programmes SET code='BENG-CIVIL',name='B.Eng. Civil Engineering',award='B.Eng.' WHERE code='BTECH-CIVIL';
UPDATE programmes SET code='BENG-EEE',name='B.Eng. Electrical and Electronic Engineering',award='B.Eng.' WHERE code='BTECH-EEE';
UPDATE programmes SET code='BENG-MECH',name='B.Eng. Mechanical Engineering',award='B.Eng.' WHERE code='BTECH-MECH';
UPDATE programmes SET name='B.Tech. Surveying and Geoinformatics',award='B.Tech.' WHERE code='BTECH-SURVEY';

-- Keep historical Mathematics records linked, but remove Mathematics from active registration choices.
UPDATE programmes SET is_active=0 WHERE code='BSC-MATH';
UPDATE departments SET is_active=0 WHERE code='MATH';
