<?php
// File: includes/logger.php

function log_activity($action, $page = '') {
    // Start session if not already started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $username = $_SESSION['username'] ?? 'Guest';
    $ip       = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    $page     = $page ?: basename($_SERVER['PHP_SELF']);

    // Use PDO from db.php
    require_once __DIR__ . '/db.php'; // make sure $pdo is available

    try {
        $stmt = $pdo->prepare("
            INSERT INTO activity_logs (username, ip_address, action, page)
            VALUES (:username, :ip, :action, :page)
        ");
        $stmt->execute([
            ':username' => $username,
            ':ip'       => $ip,
            ':action'   => $action,
            ':page'     => $page,
        ]);
    } catch (Exception $e) {
        // Log errors silently or handle as needed
        error_log("Activity log failed: " . $e->getMessage());
    }
}
?>
