<?php
// filename: includes/auth_functions.php

// RBAC: Define role hierarchy and constants
define('ROLE_ADMIN', 'admin');
define('ROLE_MANAGER', 'manager');
define('ROLE_USER', 'user');

// Role hierarchy array for access levels
$roleHierarchy = [
    ROLE_USER => 1,
    ROLE_MANAGER => 2,
    ROLE_ADMIN => 3,
];

// Role checking function: userRole must have at least requiredRole level
function hasRole($userRole, $requiredRole) {
    global $roleHierarchy;
    if (!isset($roleHierarchy[$userRole]) || !isset($roleHierarchy[$requiredRole])) {
        return false;
    }
    return $roleHierarchy[$userRole] >= $roleHierarchy[$requiredRole];
}

// Helper: active nav class
function activeNav($page) {
    return basename($_SERVER['PHP_SELF']) === $page ? 'active' : '';
}