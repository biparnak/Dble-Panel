<?php
session_start();

define('DB_HOST', 'sql113.alchosting.xyz');
define('DB_NAME', 'alcy_39944298_kineix_org_db');
define('DB_USER', 'alcy_39944298');
define('DB_PASS', 'qX8ta9G4PoWTzp1');

define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('MAX_STORAGE_BYTES', 500 * 1024 * 1024);

try {
    $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

require_once __DIR__ . '/functions.php';
?>
