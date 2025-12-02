<?php
// File: log_activity.php

function logActivity($message) {
    try {
        $pdo = new PDO("mysql:host=localhost;dbname=your_database", "your_user", "your_password");
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, username, activity, timestamp) VALUES (?, ?, ?, NOW())");

        // Assuming you're logging from a session
        $userId = $_SESSION['user_id'] ?? 0;
        $username = $_SESSION['username'] ?? 'Unknown';

        $stmt->execute([$userId, $username, $message]);
    } catch (PDOException $e) {
        error_log("Activity log error: " . $e->getMessage());
    }
}
