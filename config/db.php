<?php
// ============================================================
// Database Configuration
// ============================================================
define('DB_HOST', 'localhost');
define('DB_USER', 'root');        // Change as per your XAMPP setup
define('DB_PASS', '');            // XAMPP default is empty
define('DB_NAME', 'healthcare_db');

define('ML_API_URL', 'http://localhost:5000');  // Python Flask API

// Create connection
function getDB() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        // BUG FIX: Don't expose raw DB error to client; log it server-side
        error_log('DB Connection failed: ' . $conn->connect_error);
        http_response_code(500);
        die(json_encode(['error' => 'Database connection failed. Please try again later.']));
    }
    $conn->set_charset("utf8");
    return $conn;
}

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Auth check
function requireLogin() {
    if (!isset($_SESSION['user_id'])) {
        // BUG FIX: Use relative path that works regardless of subfolder depth
        header('Location: ' . str_repeat('../', substr_count($_SERVER['PHP_SELF'], '/') - 2) . 'login.php');
        exit();
    }
}

function requireRole($role) {
    requireLogin();
    if ($_SESSION['role'] !== $role) {
        header('Location: ' . str_repeat('../', substr_count($_SERVER['PHP_SELF'], '/') - 2) . 'index.php?error=unauthorized');
        exit();
    }
}
?>
