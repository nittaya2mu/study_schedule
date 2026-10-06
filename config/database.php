<?php
/**
 * การตั้งค่าฐานข้อมูล — ค่าเริ่มต้นตรงกับ XAMPP (user: root, ไม่มีรหัสผ่าน)
 * สามารถ override ได้ด้วย environment variables: DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS
 */
define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'smart_study_scheduler');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');

date_default_timezone_set(getenv('APP_TZ') ?: 'Asia/Bangkok');

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME);
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        // ให้ MySQL ใช้ timezone เดียวกับ PHP (CURDATE() ตรงกัน)
        $pdo->exec("SET time_zone = '" . date('P') . "'");
    }
    return $pdo;
}
