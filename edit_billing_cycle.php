<?php
// edit_billing_cycle.php
session_name('oss_portal');
session_start();
if (!isset($_SESSION['username'])) {
    exit('Unauthorized');
}
include 'includes/db.php';

$id = $_POST['id'];
$activation_date = $_POST['activation_date'];
$start_billing_date = $_POST['start_billing_date'];
$cost = $_POST['cost'];
$billing_type = $_POST['billing_type'];
$next_billing_date = $_POST['next_billing_date'];
$remarks = $_POST['remarks'];
$circuit_status = $_POST['circuit_status'];
$payment_status = $_POST['payment_status'];

$stmt = $conn->prepare("UPDATE billing_cycles SET activation_date=?, start_billing_date=?, cost=?, billing_type=?, next_billing_date=?, remarks=?, circuit_status=?, payment_status=? WHERE id=?");
$stmt->bind_param("ssdsssssi", $activation_date, $start_billing_date, $cost, $billing_type, $next_billing_date, $remarks, $circuit_status, $payment_status, $id);
$stmt->execute();

echo "Success";
