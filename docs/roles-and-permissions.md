# Roles and permissions

The application has six roles. PHP checks the signed-in role on every protected page, and record queries apply the user's department, faculty, project, or assignment scope. Hiding a navigation item is only a usability measure; it is not the access-control check.

| Role | Scope | May do | Must not do |
|---|---|---|---|
| Administrator | Whole application | Manage user accounts and system settings; inspect projects, submissions, and audit records; reset accounts. | Make the supervisor's academic topic choice on behalf of the assigned supervisor. |
| Dean | One assigned faculty | Read faculty departments, students, supervisors, project records, and summary activity. | Create or change accounts, assignments, project decisions, or records. |
| HOD / Coordinator | One assigned department | Register matric numbers and programmes; create departmental supervisor profiles; assign or reassign supervisors; set deadlines and evaluation criteria; monitor work; run similarity checks; assign examiners; approve completion and archive. | See or change another department's records; approve or reject a student's idea. |
| Supervisor | Students and project records assigned by the HOD | Review the student's two project ideas, request revisions, reject an idea with feedback, select one topic, review versioned submissions, request corrections, approve work, and record supervision meetings/evaluation. | Open another supervisor's project or change department registration and assignment data. |
| Student | Own account and selected project | Complete a profile; submit up to two active project ideas; see supervisor feedback; after topic selection, submit the abstract and next chapter in order; see own versions, corrections, progress, meetings, and notifications. | Upload a chapter before the preceding stage is approved, access another student's files, or change HOD assignments. |
| Examiner | Explicitly assigned projects | Access assigned approved final submissions and submit configured evaluation scores/comments. | Access unassigned projects or change supervision, project-topic, or submission decisions. |

## Topic selection and document gates

1. The HOD registers the student and assigns an active supervisor in the same department.
2. The student submits two project ideas. Each idea is retained as an institutional project record with its own reference number.
3. The assigned supervisor can request a revision or reject an idea with feedback. A replacement can be submitted while no topic has been selected.
4. The supervisor can select a topic only after two ideas are awaiting review and no requested revision is outstanding. The selected project becomes the student's active project; the alternative is marked as not selected.
5. The student can then submit the abstract. Chapter 1 requires an approved abstract; each later chapter requires the prior chapter to be approved; the final project requires approved Chapter 5.
6. A student can upload a new version for a stage only when it has corrections or a rejection to address. A pending or approved stage cannot be overwritten.

Existing projects in the older HOD-approved workflow remain visible. Existing `proposal` submissions act as the abstract predecessor for those records. New student work uses the Abstract stage.

## Data access checks

- A Dean's records are selected through the Dean's `faculty_id` and the departments belonging to that faculty.
- An HOD's records are selected through the account's required `department_id`.
- Supervisors need an active `project_supervisors` assignment for a project; HOD assignment changes close the previous project assignment and retain its history.
- Students need membership in the project, and candidate or rejected topics are not accepted by the download route.
- Examiners need a non-withdrawn `project_examiners` assignment. The download route further restricts them to approved final-project submissions.
- All state-changing forms validate a session CSRF token. User-provided SQL values use PDO parameters; output is escaped with the shared `e()` helper.
