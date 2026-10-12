<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';

function db(): PDO
{
    static $pdo;
    if ($pdo instanceof PDO) return $pdo;
    $host = getenv('FYP_DB_HOST') ?: '127.0.0.1';
    $name = getenv('FYP_DB_NAME') ?: 'fyp_portal';
    $user = getenv('FYP_DB_USER') ?: 'root';
    $pass = getenv('FYP_DB_PASS') ?: '';
    $dsn = "mysql:host={$host};dbname={$name};charset=utf8mb4";
    try {
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    } catch (PDOException $e) {
        error_log('Database connection failed: ' . $e->getMessage());
        http_response_code(500);
        exit('The application is temporarily unavailable. Check database configuration.');
    }
    return $pdo;
}
