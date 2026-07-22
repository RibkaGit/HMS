<?php
// ============================================================================
// DATABASE CONFIGURATION
// ============================================================================

define('DB_HOST', 'localhost');
define('DB_NAME', 'hms_db');
define('DB_USER', 'root');
define('DB_PASS', '');

try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    
    $conn->set_charset("utf8mb4");
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}