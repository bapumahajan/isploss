<?php
// logs/log_activity.php

function log_activity($message, $source = 'unknown') {
    // Ensure logs directory exists
    $logDir = __DIR__;
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }

    // Log file path
    $logFile = $logDir . '/activity.log';

    // Generate log entry
    $timestamp = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'CLI';
    $user = $_SESSION['username'] ?? 'UnknownUser';
    $entry = "[$timestamp][$ip][$user][$source] $message" . PHP_EOL;

    //
