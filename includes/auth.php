<?php
function is_logged_in() {
    return isset($_SESSION['username']);
}

function require_role($role) {
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== $role) {
        header('Location: unauthorized.php');
        exit();
    }
}

function require_roles($roles = []) {
    if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], $roles)) {
        header('Location: unauthorized.php');
        exit();
    }
}
?>