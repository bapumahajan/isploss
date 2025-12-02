<?php
// File: includes/session_check.php
session_start();

if (!isset($_SESSION['username'])) {
    header("Location: login.php?message=Login required.");
    exit;
}

if (!isset($_SESSION['last_activity'])) {
    $_SESSION['last_activity'] = time();
} elseif (time() - $_SESSION['last_activity'] > 300) {
    session_unset();
    session_destroy();
    header("Location: login.php?message=Session expired due to inactivity.");
    exit;
}

$_SESSION['last_activity'] = time();
?>
