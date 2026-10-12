<?php
require_once dirname(__DIR__).'/includes/auth.php';
$u = require_role('administrator');
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'save_system') {
        $portalName = post_string('portal_name', 80);
        $institutionName = post_string('institution_name', 160);
        $maxUploadMb = filter_var($_POST['max_upload_mb'] ?? null, FILTER_VALIDATE_INT);
        $sessionTimeout = filter_var($_POST['session_timeout_minutes'] ?? null, FILTER_VALIDATE_INT);

        if (mb_strlen($portalName) < 3 || mb_strlen($institutionName) < 5) {
            $err = 'Enter a portal name and institution name.';
        } elseif ($maxUploadMb === false || $maxUploadMb < 1 || $maxUploadMb > 50) {
            $err = 'Maximum upload size must be between 1 and 50 MB.';
        } elseif ($sessionTimeout === false || $sessionTimeout < 5 || $sessionTimeout > 120) {
            $err = 'Session timeout must be between 5 and 120 minutes.';
        } else {
            try {
                db()->beginTransaction();
                $save = db()->prepare('INSERT INTO system_settings (setting_key, setting_value, updated_by) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by)');
                foreach ([
                    'portal_name' => $portalName,
                    'institution_name' => $institutionName,
                    'max_upload_mb' => (string)$maxUploadMb,
                    'session_timeout_minutes' => (string)$sessionTimeout,
                ] as $key => $value) {
                    $save->execute([$key, $value, $u['id']]);
                }
                db()->commit();
                audit('system_settings_updated', 'system_settings', null, 'Portal identity and session/upload limits updated');
                flash('success', 'System settings saved.');
                redirect('admin/settings.php');
            } catch (Throwable $error) {
                if (db()->inTransaction()) db()->rollBack();
                error_log($error->getMessage());
                $err = 'System settings could not be saved. Please try again.';
            }
        }
    } elseif ($action === 'session') {
        $name = post_string('name', 20);
        $start = (string)($_POST['starts_on'] ?? '');
        $end = (string)($_POST['ends_on'] ?? '');
        $startDate = DateTime::createFromFormat('Y-m-d', $start);
        $endDate = DateTime::createFromFormat('Y-m-d', $end);
        if (!$name || !$startDate || !$endDate || $end < $start) {
            $err = 'Provide a valid session name and date range.';
        } else {
            try {
                db()->beginTransaction();
                $current = isset($_POST['is_current']);
                if ($current) db()->exec('UPDATE academic_sessions SET is_current = 0');
                db()->prepare('INSERT INTO academic_sessions(name, starts_on, ends_on, is_current) VALUES(?,?,?,?)')->execute([$name, $start, $end, $current ? 1 : 0]);
                $sessionId = (int)db()->lastInsertId();
                db()->commit();
                audit('academic_session_created', 'academic_session', $sessionId, $name);
                flash('success', 'Academic session created.');
                redirect('admin/settings.php');
            } catch (Throwable $error) {
                if (db()->inTransaction()) db()->rollBack();
                error_log($error->getMessage());
                $err = 'Academic session could not be created. Check whether the session name already exists.';
            }
        }
    } elseif ($action === 'current') {
        $id = filter_var($_POST['session_id'] ?? null, FILTER_VALIDATE_INT);
        $check = db()->prepare('SELECT id FROM academic_sessions WHERE id = ?');
        $check->execute([$id ?: 0]);
        if (!$id || !$check->fetchColumn()) {
            $err = 'Choose a valid academic session.';
        } else {
            db()->beginTransaction();
            db()->exec('UPDATE academic_sessions SET is_current = 0');
            db()->prepare('UPDATE academic_sessions SET is_current = 1, is_closed = 0 WHERE id = ?')->execute([$id]);
            db()->commit();
            audit('academic_session_changed', 'academic_session', (int)$id, 'Current session changed');
            flash('success', 'Current academic session updated.');
            redirect('admin/settings.php');
        }
    }
}

$catalogue = db()->query('SELECT f.name faculty, d.name department, d.description department_note, p.name programme, p.code, p.award
    FROM faculties f JOIN departments d ON d.faculty_id = f.id
    LEFT JOIN programmes p ON p.department_id = d.id AND p.is_active = 1
    WHERE f.is_active = 1 AND d.is_active = 1
    ORDER BY f.name, d.name, p.name')->fetchAll();
$sessions = db()->query('SELECT * FROM academic_sessions ORDER BY starts_on DESC')->fetchAll();
$counts = db()->query('SELECT COUNT(DISTINCT f.id) faculties, COUNT(DISTINCT d.id) departments, COUNT(DISTINCT p.id) programmes
    FROM faculties f JOIN departments d ON d.faculty_id = f.id
    LEFT JOIN programmes p ON p.department_id = d.id AND p.is_active = 1
    WHERE f.is_active = 1 AND d.is_active = 1')->fetch();
$portalName = app_setting('portal_name', APP_NAME);
$institutionName = app_setting('institution_name', 'Nigerian Army University, Biu');
$maxUploadMb = (int)app_setting('max_upload_mb', '15');
$sessionTimeout = (int)app_setting('session_timeout_minutes', '30');
$smtpReady = (bool)(getenv('FYP_SMTP_HOST') && getenv('FYP_SMTP_USER') && getenv('FYP_SMTP_PASS'));
$currentSession = '';
foreach ($sessions as $session) if (!empty($session['is_current'])) $currentSession = $session['name'];
$pageTitle = 'System Settings';$crumbTrail=['Admin','Settings'];
require dirname(__DIR__).'/includes/header.php';
?>
<p class="page-intro">Manage portal identity and technical limits, maintain academic sessions, and review the preloaded NAUB faculty catalogue. These settings apply across the application.</p>
<nav class="d-flex flex-wrap gap-2 mb-3" aria-label="Settings sections"><a class="btn btn-sm btn-primary" href="#general-settings" aria-current="page">General settings</a><a class="btn btn-sm btn-outline-secondary" href="#academic-catalogue">Academic catalogue</a><a class="btn btn-sm btn-outline-secondary" href="#academic-sessions">Academic sessions</a><a class="btn btn-sm btn-outline-secondary" href="#email-settings">Email &amp; notifications</a><a class="btn btn-sm btn-outline-secondary" href="<?= e(url('admin/roles-permissions.php')) ?>">Roles &amp; permissions</a></nav>
<?php if ($err): ?><div class="alert alert-danger" role="alert"><?= e($err) ?></div><?php endif; ?>

<div class="row g-3 mb-4">
  <div class="col-sm-6 col-xl-3"><section class="card p-3 h-100 stat-mini"><div class="info-label">Institution</div><strong><?= e($institutionName) ?></strong></section></div>
  <div class="col-sm-6 col-xl-3"><section class="card p-3 h-100 stat-mini"><div class="info-label">Current session</div><strong><?= e($currentSession ?: 'Not selected') ?></strong></section></div>
  <div class="col-sm-6 col-xl-3"><section class="card p-3 h-100 stat-mini"><div class="info-label">Academic catalogue</div><strong><?= (int)$counts['faculties'] ?> faculties · <?= (int)$counts['departments'] ?> departments · <?= (int)$counts['programmes'] ?> programmes</strong></section></div>
  <div class="col-sm-6 col-xl-3"><section class="card p-3 h-100 stat-mini"><div class="info-label">Email delivery</div><strong class="<?= $smtpReady ? 'text-success' : 'text-warning' ?>"><?= $smtpReady ? 'SMTP configured' : 'SMTP needs configuration' ?></strong></section></div>
</div>

<div class="row g-3 mb-4">
  <div class="col-xl-7">
    <section class="card p-4 h-100" id="general-settings">
      <h2 class="section-heading">Portal and security settings</h2>
      <p class="text-muted">Changes here update the portal header, upload limit, and idle-session timeout.</p>
      <form method="post">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="save_system">
        <div class="mb-3"><label class="form-label" for="portal_name">Portal name</label><input class="form-control" id="portal_name" name="portal_name" maxlength="80" value="<?= e($portalName) ?>" required></div>
        <div class="mb-3"><label class="form-label" for="institution_name">Institution name</label><input class="form-control" id="institution_name" name="institution_name" maxlength="160" value="<?= e($institutionName) ?>" required></div>
        <div class="row g-3">
          <div class="col-md-6"><label class="form-label" for="max_upload_mb">Maximum document size</label><div class="input-group"><input class="form-control" id="max_upload_mb" name="max_upload_mb" type="number" min="1" max="50" value="<?= $maxUploadMb ?>" required><span class="input-group-text">MB</span></div><div class="form-text">Allowed types remain PDF, DOC, and DOCX.</div></div>
          <div class="col-md-6"><label class="form-label" for="session_timeout_minutes">Idle session timeout</label><div class="input-group"><input class="form-control" id="session_timeout_minutes" name="session_timeout_minutes" type="number" min="5" max="120" value="<?= $sessionTimeout ?>" required><span class="input-group-text">minutes</span></div></div>
        </div>
        <button class="btn btn-primary mt-4">Save system settings</button>
      </form>
    </section>
  </div>
  <div class="col-xl-5">
    <section class="card p-4 h-100" id="email-settings">
      <h2 class="section-heading">Email service</h2>
      <p><?= $smtpReady ? 'The portal has SMTP settings and can send verification and password recovery messages.' : 'Email forms are available, but messages cannot be delivered until SMTP environment settings are added to the local PHP/Apache environment.' ?></p>
      <div class="small text-muted mb-2">SMTP credentials are kept outside the database and are never displayed here.</div>
      <ul class="small mb-0"><li>Verification links expire after one day.</li><li>Password reset links expire after one hour.</li><li>Each link can be used once.</li></ul>
    </section>
  </div>
</div>

<div class="row g-3">
  <div class="col-xl-7">
    <section class="card p-3" id="academic-catalogue">
      <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2"><div><h2 class="h5 mb-1">NAUB academic catalogue</h2><p class="text-muted small mb-0">The supplied faculties, departments, and degree programmes are preloaded for registration.</p></div><span class="badge text-bg-secondary"><?= (int)$counts['programmes'] ?> programmes</span></div>
      <div class="table-responsive"><table class="table table-sm align-middle"><thead><tr><th>Faculty</th><th>Department</th><th>Programme</th><th>Code</th></tr></thead><tbody>
      <?php foreach ($catalogue as $row): ?><tr><td><?= e($row['faculty']) ?></td><td><?= e($row['department']) ?><?php if ($row['department_note']): ?><br><small class="text-muted"><?= e($row['department_note']) ?></small><?php endif; ?></td><td><?= e($row['programme'] ?: 'No degree programme') ?></td><td><?= e($row['code'] ?: '—') ?></td></tr><?php endforeach; ?>
      <?php if (!$catalogue): ?><tr><td colspan="4" class="text-muted">No active catalogue entries.</td></tr><?php endif; ?>
      </tbody></table></div>
      <p class="form-text mb-0">The catalogue is preloaded as the institution’s reference structure; contact the system maintainer for corrections.</p>
    </section>
  </div>
  <div class="col-xl-5">
    <section class="card p-4 mb-3">
      <h2 class="section-heading">Add academic session</h2>
      <form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="session">
        <label class="form-label" for="session_name">Session name</label><input class="form-control mb-2" id="session_name" name="name" placeholder="2026/2027" maxlength="20" required>
        <div class="row g-2"><div class="col"><label class="form-label" for="starts_on">Starts</label><input class="form-control" id="starts_on" type="date" name="starts_on" required></div><div class="col"><label class="form-label" for="ends_on">Ends</label><input class="form-control" id="ends_on" type="date" name="ends_on" required></div></div>
        <div class="form-check my-3"><input class="form-check-input" id="is_current" name="is_current" type="checkbox"><label class="form-check-label" for="is_current">Set as current session</label></div>
        <button class="btn btn-primary">Create session</button>
      </form>
    </section>
    <section class="card p-4" id="academic-sessions">
      <h2 class="section-heading">Academic sessions</h2>
      <?php foreach ($sessions as $session): ?><div class="border-bottom py-2 d-flex flex-wrap justify-content-between align-items-center gap-2"><span><strong><?= e($session['name']) ?></strong><br><small class="text-muted"><?= e($session['starts_on'].' — '.$session['ends_on']) ?></small> <?= !empty($session['is_current']) ? '<span class="badge text-bg-success">Current</span>' : '' ?></span><?php if (empty($session['is_current'])): ?><form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="current"><input type="hidden" name="session_id" value="<?= (int)$session['id'] ?>"><button class="btn btn-sm btn-outline-primary">Set current</button></form><?php endif; ?></div><?php endforeach; ?>
      <?php if (!$sessions): ?><p class="text-muted mb-0">No academic sessions have been added.</p><?php endif; ?>
    </section>
  </div>
</div>
<?php require dirname(__DIR__).'/includes/footer.php'; ?>
