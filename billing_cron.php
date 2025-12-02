<?php
require 'includes/db.php';

$today = date('Y-m-d');

// Mark invoices overdue if past end date and unpaid
$stmt = $pdo->prepare("UPDATE invoices SET invoice_status='Overdue' WHERE invoice_status='Sent' AND billing_period_end < ?");
$stmt->execute([$today]);

// Optionally, email reminder or alert (if SMTP set up)
?>
