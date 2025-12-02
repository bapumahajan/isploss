<?php
// File: logout.php
session_name('oss_portal');
session_start();
require_once 'log_activity.php';

// Capture username before destroying session
$username = $_SESSION['username'] ?? 'Unknown';

// Log the logout activity (if function exists)
if (function_exists('logActivity')) {
    logActivity("User '{$username}' logged out");
}

// Unset all session variables
$_SESSION = [];

// Delete the session cookie if set
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy the session
session_destroy();

// Redirect to login page with message
header("Location: login.php?message=You have been logged out successfully.");
exit();
?>