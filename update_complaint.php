<?php
// update_complaint.php
echo '<pre>';
print_r($_POST);
echo '</pre>';
exit;
session_name('oss_portal');
session_start();
require_once 'includes/db.php';

if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $circuit_id = trim($_POST['circuit_id']);
    $incident_status = trim($_POST['incident_status']);
    $remarks = trim($_POST['remarks']);
    $next_update_time = !empty($_POST['next_update_time']) ? $_POST['next_update_time'] : null;
    $customer_communications_call = trim($_POST['customer_communications_call']);
    $Fault = trim($_POST['Fault']);
    $RFO = trim($_POST['RFO']);

    if (empty($circuit_id) || empty($incident_status) || empty($remarks)) {
        die('Required fields are missing.');
    }

    // Fetch the latest complaint to verify status
    $stmt = $pdo->prepare("SELECT * FROM complaints WHERE id = ?");
    $stmt->execute([$id]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing && $existing['incident_status'] == 'closed') {
        die('Closed complaints cannot be updated.');
    }

    // Prepare update
    $update_stmt = $pdo->prepare("UPDATE complaints SET
        incident_status = ?,
        remarks = ?,
        next_update_time = ?,
        customer_communications_call = ?,
        Fault = ?,
        RFO = ?,
        updated_by = ?,
        updated_time = NOW()
        WHERE id = ?
    ");

    $update_stmt->execute([
        $incident_status,
        $remarks,
        $next_update_time,
        $customer_communications_call,
        $Fault,
        $RFO,
        $_SESSION['username'],
        $id
    ]);

    // Insert into history
    $history_stmt = $pdo->prepare("INSERT INTO complaint_history
        (rid, incident_status, remarks, next_update_time, customer_communications_call, alarm_status, updated_by, Fault, RFO)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");

    $history_stmt->execute([
        $id,
        $incident_status,
        $remarks,
        $next_update_time,
        $customer_communications_call,
        null,
        $_SESSION['username'],
        $Fault,
        $RFO
    ]);

    header('Location: complaints_dashboard.php');
    exit;
} else {
    die('Invalid request method.');
}
?>
