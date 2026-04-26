<?php
// ── config/db.php ──────────────────────────────────────
// Shared PDO connection for the SmartEdu project.
// Include this file wherever you need a DB handle.

define('DB_HOST', 'localhost');
define('DB_NAME', 'smartedu');
define('DB_USER', 'root');       // ← change to your DB user
define('DB_PASS', '');           // ← change to your DB password
define('DB_CHARSET', 'utf8mb4');

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            DB_HOST, DB_NAME, DB_CHARSET
        );
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    }
    return $pdo;
}