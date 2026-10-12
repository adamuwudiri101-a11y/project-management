# NAUB Final Year Project Management and Supervision System

An undergraduate Information Systems project implementation using **HTML5, CSS3, Bootstrap 5, vanilla JavaScript, PHP 8+, PDO, MySQL/MariaDB, and Apache**. It is a local academic prototype; it has not been deployed or security-certified for university-wide production use.

## Local setup (XAMPP/WAMP)

1. Install XAMPP or WAMP with PHP 8+ and MySQL/MariaDB.
2. Copy this project folder to Apache's web directory and name it `fyp-portal` (for example, `C:\xampp\htdocs\fyp-portal`).
3. Start Apache and MySQL from the control panel.
4. In phpMyAdmin, import `database/schema.sql`, then import `database/seed.sql`. The schema file creates/selects `fyp_portal` itself. For an existing installation created from the earlier project version, apply `database/migrations/002_naub_academic_catalogue.sql` once instead of re-importing the schema or seed.
5. Edit `config/config.php` and set `BASE_URL` to `/fyp-portal` (or `''` if installed as the web root).
6. Configure connection settings in environment variables `FYP_DB_HOST`, `FYP_DB_NAME`, `FYP_DB_USER`, and `FYP_DB_PASS`. Defaults are `127.0.0.1`, `fyp_portal`, `root`, and an empty password for a local XAMPP setup.
7. Make the sibling directory `fyp-portal-storage` writable by Apache. Uploaded documents are stored there, outside the public project directory.
8. Open `http://localhost/fyp-portal/` in Chrome or Edge.

## System Settings and email

Administrators can open **System Settings** to change the portal and institution names, the maximum upload size, and the idle-session timeout. The faculty, department, and programme list is preloaded and displayed as the registration reference. Academic sessions are managed on the same page. The upload size must also fit PHP's `upload_max_filesize` and `post_max_size` values.

Email verification and password recovery use PHPMailer over SMTP. Install the locked dependency with `composer install`, then configure these environment variables for Apache/PHP: `FYP_SMTP_HOST`, `FYP_SMTP_USER`, `FYP_SMTP_PASS`, `FYP_SMTP_FROM`, `FYP_SMTP_SECURE` (`tls` or `ssl`), and `FYP_SMTP_PORT`. An optional `FYP_SMTP_FROM_NAME` controls the sender name. Keep SMTP credentials outside the project directory. The Admin System Settings page shows whether SMTP is configured without displaying credentials.

The interface loads Bootstrap 5 from jsDelivr, so local internet access is needed for Bootstrap styling unless the stylesheet and bundled JS are downloaded into `assets/` and the two CDN references in `includes/header.php` and `includes/footer.php` are changed to local paths.

**Current workstation note:** With your authorization, the old incompatible `fyp_portal` database was replaced with this application's schema and seed data. The catalogue contains five faculties, 24 active departments, and the 31 active programmes in the confirmed list. Import `seed.sql` only once; the schema itself uses `CREATE TABLE IF NOT EXISTS` and does not drop existing tables.

## Development demo accounts

`seed.sql` creates six fictional accounts using the shared development password `password`:

| Role | Email |
|---|---|
| Administrator | `admin@example.test` |
| Dean (Faculty of Computing) | `dean@example.test` |
| HOD / Coordinator | `coordinator@example.test` |
| Supervisor | `supervisor@example.test` |
| Student | `student@example.test` |
| Examiner | `examiner@example.test` |

The seeded student may sign in using matric number `DEMO/2022/001` instead of email. Every seeded account requires a password change at first sign-in. The demo password is intentionally weak and must never be used outside a local demonstration. HOD-created staff and student accounts start with the shared temporary password `password` and must change it at first sign-in.

## Application structure

- `config/`: environment configuration and PDO connection.
- `includes/`: authentication, authorization, CSRF, escaping, audit and notification helpers, shared layout, and dashboards.
- `admin/`, `coordinator/`, `supervisor/`, `student/`, `examiner/`: role-specific server-rendered pages.
- `auth/`: sign-in, sign-out, and mandatory password change.
- `database/`: MySQL schema and fictional seed records.
- `assets/`: Bootstrap integration, small project stylesheet, and vanilla JavaScript.
- `download.php`: access-checked download endpoint; the storage directory is outside the public tree.

## Main workflow

The administrator manages the application globally. A Dean can view the data for their assigned faculty, but cannot change it. Each HOD/coordinator account is scoped to one department. The HOD registers students by matric number and programme, creates supervisor profiles, and assigns supervisors in that department. The student signs in with the matric number and temporary password `password`, changes the password, and completes their profile. Faculty, department, programme, and assigned supervisor details are visible to the student and assigned supervisor. When the student registers an idea, the HOD's supervisor assignment is carried into the project. The HOD reviews the idea and advisory title/description similarity matches, then approves or rejects it. Students upload proposal, chapters, and final document versions; supervisors review each version, add comments, request corrections, or approve it. HODs assign examiners and department evaluation criteria. Examiners submit criterion scores; the HOD's final approval requires an approved final document and submitted evaluations from assigned examiners. Completed work can then be archived.

Similarity uses a documented token Jaccard score (`token-jaccard-v1`): lowercase and normalize text, tokenize Unicode letters/numbers, remove short/common terms, then divide shared unique tokens by the union of unique tokens. The coordinator sees matches at or above 20% as a review aid. Every comparison is stored. Similarity never automatically rejects a project.

Evaluation totals are calculated as the sum of each entered score multiplied by that criterion's configured weight. The system does not convert totals into grades.

## Assumptions

- A student registers one project in the current MVP; group membership is supported in the schema but student group creation is not exposed in the interface.
- HOD/coordinator accounts with a department are restricted to that department; an unscoped coordinator is a university-wide coordinator account.
- The five-faculty, 24-department, 31-programme catalogue follows the faculty and programme breakdown supplied for this project. French is recorded as a Languages department note, not a degree programme. Confirm the catalogue with NAUB before using it for real records.
- Evaluation scores are stored as entered. The application does not map totals to grades.
- Upcoming current-session deadlines appear in the dashboards. `scripts/send_deadline_notifications.php` creates in-app reminders for participating students, active supervisors, and assigned examiners with deadlines due within 48 hours; schedule it daily with Windows Task Scheduler or cron. Email/SMS delivery is not part of this local MVP.

## Operational notes

- Change `BASE_URL` if the Apache folder name differs.
- Change database credentials from the local defaults for any shared environment.
- Set PHP `upload_max_filesize` and `post_max_size` to at least 16 MB for the 15 MB application limit.
- Import `seed.sql` only once into a fresh database. The schema uses `CREATE TABLE IF NOT EXISTS` and does not drop existing tables.
- Set `display_errors=Off` and keep PHP error logging enabled for any non-local deployment.
- Back up `fyp_portal` and the sibling upload-storage directory together.
- For a live local copy, apply each numbered migration once in order. `002` adds the academic hierarchy and student-supervisor table; `003` aligns the faculty catalogue; `004` adds Dean faculty scope; `005` scopes evaluation criteria to departments; `006` aligns all 31 programme choices to the confirmed list; `007` adds email verification and recovery; `008` adds submission workflow constraints; `009` preserves legacy project progress; `010` adds configurable system settings. Existing project, user, submission, and review records remain linked.
- Use HTTPS, strong account provisioning, scheduled backups, and a professional security review before any real institutional use.

## Project design and verification checklist

See [`docs/system-design.md`](docs/system-design.md) for functional requirements, entities/relationships, architecture, security decisions, testing checklist, and known implementation limitations.
