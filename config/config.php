<?php
declare(strict_types=1);

define('APP_NAME', 'NAUB FYP Portal');
define('BASE_URL', '/fyp-portal'); // Set to '' when the project is Apache's document root.
define('APP_ROOT', dirname(__DIR__));
// Keep uploaded documents outside Apache's document root; downloads go through download.php.
define('UPLOAD_ROOT', dirname(dirname(APP_ROOT)) . DIRECTORY_SEPARATOR . 'fyp-portal-storage');
define('MAX_UPLOAD_BYTES', 15 * 1024 * 1024);
date_default_timezone_set('Africa/Lagos');

if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ini_set('session.cookie_secure', '1');
    session_start();
}

if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
}

define('APP_DEBUG', false);
error_reporting(E_ALL);
ini_set('display_errors', APP_DEBUG ? '1' : '0');
ini_set('log_errors', '1');
