<?php
// ============================================================================
// HOSPITAL MANAGEMENT SYSTEM - LOGOUT
// ============================================================================

session_start();

// Log the logout activity if user is logged in
if (isset($_SESSION['user_id']) && isset($_SESSION['logged_in'])) {
    require_once 'config/database.php';
    require_once 'includes/functions.php';
    
    $userId = $_SESSION['user_id'];
    $username = $_SESSION['username'] ?? 'Unknown';
    logUserActivity($conn, $userId, 'Logout', "User {$username} logged out");
}

// Clear all session variables
$_SESSION = array();

// Destroy the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy the session
session_destroy();

// Redirect to login page with success message
header('Location: login.php?logout=success');
exit();
?>