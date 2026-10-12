<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/database.php';

function e(?string $value): string { return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function url(string $path = ''): string { return rtrim(BASE_URL, '/') . '/' . ltrim($path, '/'); }
function redirect(string $path): never { header('Location: ' . url($path)); exit; }
function current_user(): ?array { return $_SESSION['user'] ?? null; }
function app_setting(string $key, string $default = ''): string
{
    static $cache = [];
    if (array_key_exists($key, $cache)) return $cache[$key];
    try {
        $q = db()->prepare('SELECT setting_value FROM system_settings WHERE setting_key = ?');
        $q->execute([$key]);
        $value = $q->fetchColumn();
        return $cache[$key] = ($value === false ? $default : (string)$value);
    } catch (Throwable $error) {
        return $cache[$key] = $default;
    }
}
function require_login(): array
{
    $user = current_user();
    if (!$user) { flash('warning', 'Your session has expired. Please log in again.'); redirect('auth/login.php'); }
    $check = db()->prepare('SELECT u.account_status,r.name role,u.must_change_password,u.profile_complete,u.faculty_id,u.department_id,u.email_verified_at FROM users u JOIN roles r ON r.id=u.role_id WHERE u.id=?');
    $check->execute([$user['id']]); $fresh = $check->fetch();
    if (!$fresh || $fresh['account_status'] !== 'active') { $_SESSION=[]; session_destroy(); session_start(); flash('warning','This account is inactive. Contact an administrator.'); redirect('auth/login.php'); }
    $user['role'] = $fresh['role']; $user['must_change_password'] = $fresh['must_change_password']; $user['profile_complete'] = $fresh['profile_complete']; $user['faculty_id'] = $fresh['faculty_id']; $user['department_id'] = $fresh['department_id']; $user['email_verified_at'] = $fresh['email_verified_at']; $_SESSION['user'] = $user;
    if ($user['role']==='coordinator' && empty($user['department_id'])) { http_response_code(403); exit('This HOD account has no department assignment. Contact the administrator.'); }
    if ($user['role']==='dean' && empty($user['faculty_id'])) { http_response_code(403); exit('This Dean account has no faculty assignment. Contact the administrator.'); }
    $sessionTimeoutSeconds = max(5, min(120, (int)app_setting('session_timeout_minutes', '30'))) * 60;
    if (time() - (int)($_SESSION['last_activity'] ?? time()) > $sessionTimeoutSeconds) {
        $_SESSION = []; session_destroy(); session_start(); flash('warning', 'Your session expired. Please log in again.'); redirect('auth/login.php');
    }
    $_SESSION['last_activity'] = time();
    if (!empty($user['must_change_password']) && !str_contains((string)($_SERVER['SCRIPT_NAME'] ?? ''), 'change-password.php') && !str_contains((string)($_SERVER['SCRIPT_NAME'] ?? ''), 'logout.php')) redirect('auth/change-password.php');
    if ($user['role']==='student' && empty($user['profile_complete']) && !str_contains((string)($_SERVER['SCRIPT_NAME'] ?? ''), '/student/profile.php') && !str_contains((string)($_SERVER['SCRIPT_NAME'] ?? ''), 'logout.php')) redirect('student/profile.php');
    if ($user['role']==='supervisor' && empty($user['profile_complete']) && !str_contains((string)($_SERVER['SCRIPT_NAME'] ?? ''), '/supervisor/profile.php') && !str_contains((string)($_SERVER['SCRIPT_NAME'] ?? ''), 'logout.php')) redirect('supervisor/profile.php');
    return $user;
}
function require_role(string ...$roles): array
{
    $u = require_login();
    if (!in_array($u['role'], $roles, true)) { http_response_code(403); exit('You are not authorized to access this page.'); }
    return $u;
}
function csrf_token(): string { if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32)); return $_SESSION['csrf']; }
function verify_csrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals(csrf_token(), $token)) { http_response_code(419); exit('Your form session expired. Please reload and try again.'); }
}
function flash(string $type, string $message): void { $_SESSION['flash'][] = ['type'=>$type, 'message'=>$message]; }
function take_flashes(): array { $items = $_SESSION['flash'] ?? []; unset($_SESSION['flash']); return $items; }
function audit(string $action, ?string $entityType = null, ?int $entityId = null, string $description = ''): void
{
    $u = current_user();
    $q = db()->prepare('INSERT INTO audit_logs (user_id, action, entity_type, entity_id, description, ip_address, user_agent) VALUES (?,?,?,?,?,?,?)');
    $q->execute([$u['id'] ?? null, $action, $entityType, $entityId, $description, $_SERVER['REMOTE_ADDR'] ?? null, substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500)]);
}
function notify_user(int $userId, string $title, string $message, string $type='info', ?string $entityType=null, ?int $entityId=null): void
{
    $q=db()->prepare('INSERT INTO notifications(user_id,title,message,type,entity_type,entity_id) VALUES(?,?,?,?,?,?)');
    $q->execute([$userId,$title,$message,$type,$entityType,$entityId]);
}
function role_home(string $role): string { return match($role){'administrator'=>'admin/dashboard.php','dean'=>'dean/dashboard.php','coordinator'=>'coordinator/dashboard.php',default=>$role.'/dashboard.php'}; }
function status_badge(string $status): string
{
    $class = match ($status) {
        'approved', 'completed', 'idea_approved', 'resolved', 'final' => 'success',
        'rejected', 'correction_required', 'overdue', 'idea_rejected' => 'danger',
        'archived', 'cancelled', 'withdrawn' => 'secondary',
        'submitted', 'resubmitted', 'under_review', 'idea_under_review', 'pending', 'open' => 'warning',
        'in_progress', 'supervisor_assigned', 'supervisor_pending', 'final_submission', 'under_evaluation' => 'primary',
        default => 'primary',
    };
    return '<span class="badge text-bg-' . $class . '">' . e(ucwords(str_replace('_',' ',$status))) . '</span>';
}
function post_string(string $key, int $max=5000): string { return trim(mb_substr((string)($_POST[$key] ?? ''), 0, $max)); }
function project_access(int $projectId, array $user): bool
{
    if ($user['role']==='administrator') return true;
    if ($user['role']==='coordinator') {
        if (empty($user['department_id'])) return false;
        $q=db()->prepare('SELECT 1 FROM projects WHERE id=? AND department_id=?');$q->execute([$projectId,$user['department_id']]);return (bool)$q->fetchColumn();
    }
    if ($user['role']==='student') { $q=db()->prepare('SELECT 1 FROM project_members WHERE project_id=? AND student_id=?'); $q->execute([$projectId,$user['id']]); return (bool)$q->fetchColumn(); }
    if ($user['role']==='supervisor') { $q=db()->prepare('SELECT 1 FROM project_supervisors WHERE project_id=? AND supervisor_id=? AND ended_at IS NULL'); $q->execute([$projectId,$user['id']]); return (bool)$q->fetchColumn(); }
    if ($user['role']==='examiner') { $q=db()->prepare('SELECT 1 FROM project_examiners WHERE project_id=? AND examiner_id=? AND status <> "withdrawn"'); $q->execute([$projectId,$user['id']]); return (bool)$q->fetchColumn(); }
    return false;
}
