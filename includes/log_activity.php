<?php
function log_activity($action, $page = '') {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $username = $_SESSION['username'] ?? 'Guest';
    $ip = $_SERVER['REMOTE_ADDR'];
    $page = $page ?: basename($_SERVER['PHP_SELF']);

    require_once 'config.php'; // Ensure this defines $pdo

    if (!isset($pdo)) {
        throw new Exception('Database connection ($pdo) is not set.');
    }

    $stmt = $pdo->prepare("INSERT INTO activity_logs (username, ip_address, action, page) VALUES (?, ?, ?, ?)");
    $stmt->execute([$username, $ip, $action, $page]);
}
?>
