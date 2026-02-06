<?php
// Prevent direct access
if (!defined('APP_ACCESS')) {
    die('Direct access not permitted');
}

// ====== DATABASE CONFIGURATION ======
define('DB_HOST', 'localhost');
define('DB_NAME', 'lab_management');
define('DB_USER', 'maikel');
define('DB_PASS', 'vGtUCGuPvYvhIU0');
define('DB_CHARSET', 'utf8mb4');

// ====== APPLICATION CONFIGURATION ======
define('APP_NAME', 'Lab Management System');
define('APP_VERSION', '1.0.0');
define('APP_TIMEZONE', 'Asia/Jakarta');

// Set timezone
date_default_timezone_set(APP_TIMEZONE);

// ====== DATABASE CONNECTION ======
function getDB() {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            die("Database connection failed. Please check your configuration.");
        }
    }
    
    return $pdo;
}

// ====== INCLUDE HELPER FUNCTIONS ======
require_once __DIR__ . '/functions.php';