<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_name('oss_portal');
session_start();

if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

require_once 'includes/db.php';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=billing_export_' . date('Ymd_His') . '.csv');

$output = fopen('php://output', 'w');
fputcsv($output, [
    'Billing ID',
    'Circuit ID',
    'Activation Date',
    'Start Billing Date',
    'Cost',
    'Billing Type',
    'Next Billing Date',
    'Circuit Status',
    'Payment Status',
    'Remarks'
]);

try {
    $sql = "
        SELECT 
            id, circuit_id, activation_date, start_billing_date, cost, billing_type,
            next_billing_date, circuit_status, payment_status, remarks
        FROM billing_cycles
        ORDER BY activation_date DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, $row);
    }
} catch (PDOException $e) {
    error_log("Export failed: " . $e->getMessage());
    fputcsv($output, ['Error: ' . $e->getMessage()]);
}

fclose($output);
exit;
?>