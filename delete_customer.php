<?php
// file: delete_customer.php

session_name('oss_portal');
session_start();

if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit;
}

require_once 'includes/db.php';
require_once 'includes/audit.php';

// Toggle soft delete here:
$softDelete = false;

if ($_SERVER['REQUEST_METHOD'] === 'GET' && !empty($_GET['circuit_id'])) {
    $circuit_id = $_GET['circuit_id'];

    try {
        $pdo->beginTransaction();

        if ($softDelete) {
            // Example: add `deleted_at` column of type DATETIME in tables first!
            $now = date('Y-m-d H:i:s');
            $tables = ['circuit_ips', 'circuit_network_details', 'customer_contacts', 'customer_emails', 'network_details', 'customer_basic_information'];
            foreach ($tables as $table) {
                $stmt = $pdo->prepare("UPDATE $table SET deleted_at = ? WHERE circuit_id = ?");
                $stmt->execute([$now, $circuit_id]);
            }
        } else {
            // Hard delete, child tables first
            $tables = ['circuit_ips', 'circuit_network_details', 'customer_contacts', 'customer_emails'];
            foreach ($tables as $table) {
                $stmt = $pdo->prepare("DELETE FROM $table WHERE circuit_id = ?");
                $stmt->execute([$circuit_id]);
            }

            // Then main tables
            $pdo->prepare("DELETE FROM network_details WHERE circuit_id = ?")->execute([$circuit_id]);
            $pdo->prepare("DELETE FROM customer_basic_information WHERE circuit_id = ?")->execute([$circuit_id]);
        }

        // Log the deletion
        log_activity($pdo, $_SESSION['username'], 'delete', 'customer', $circuit_id, $softDelete ? 'Soft deleted circuit and related data' : 'Deleted circuit and related data');

        $pdo->commit();

        header('Location: view_customer.php?deleted=1');
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        // Pass error message back to view_customer.php for display
        header('Location: view_customer.php?delete_error=' . urlencode($e->getMessage()));
        exit;
    }
} else {
    header('Location: view_customer.php');
    exit;
}
