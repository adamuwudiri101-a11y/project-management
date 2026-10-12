<?php
// Run daily from Windows Task Scheduler or cron: php scripts/send_deadline_notifications.php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__).'/includes/functions.php';
$deadlines=db()->query('SELECT d.id,d.title,d.milestone_key,d.academic_session_id,d.department_id,d.due_at FROM deadlines d WHERE d.due_at>NOW() AND d.due_at<=DATE_ADD(NOW(),INTERVAL 48 HOUR)')->fetchAll();
$notify=db()->prepare('SELECT DISTINCT recipient.id FROM projects p JOIN project_members pm ON pm.project_id=p.id JOIN users recipient ON recipient.id=pm.student_id WHERE p.academic_session_id=? AND (? IS NULL OR p.department_id=?) AND recipient.account_status="active" UNION SELECT DISTINCT ps.supervisor_id FROM projects p JOIN project_supervisors ps ON ps.project_id=p.id AND ps.ended_at IS NULL WHERE p.academic_session_id=? AND (? IS NULL OR p.department_id=?) UNION SELECT DISTINCT pe.examiner_id FROM projects p JOIN project_examiners pe ON pe.project_id=p.id WHERE p.academic_session_id=? AND (? IS NULL OR p.department_id=?)');
$exists=db()->prepare('SELECT id FROM notifications WHERE user_id=? AND entity_type="deadline" AND entity_id=? LIMIT 1');$created=0;
foreach($deadlines as $d){$notify->execute([$d['academic_session_id'],$d['department_id'],$d['department_id'],$d['academic_session_id'],$d['department_id'],$d['department_id'],$d['academic_session_id'],$d['department_id'],$d['department_id']]);foreach($notify->fetchAll(PDO::FETCH_COLUMN) as $recipient){$exists->execute([$recipient,$d['id']]);if(!$exists->fetchColumn()){notify_user((int)$recipient,'Upcoming deadline',$d['title'].' is due '.$d['due_at'].'.','warning','deadline',(int)$d['id']);$created++;}}}
echo "Deadline notifications created: {$created}".PHP_EOL;
