<?php
require_once dirname(__DIR__).'/includes/auth.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('Use the sign-out button to end your session.'); }
verify_csrf();
if (current_user()) audit('logout','user',(int)current_user()['id'],'User signed out');
$_SESSION=[]; if(ini_get('session.use_cookies')) { $p=session_get_cookie_params(); setcookie(session_name(),'',(time()-42000),$p['path'],$p['domain'],$p['secure'],$p['httponly']); } session_destroy(); session_start(); flash('success','You have signed out.'); redirect('auth/login.php');
