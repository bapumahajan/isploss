<?php
session_name('oss_portal');
session_start();

if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

require_once 'includes/db.php';

// Filtering logic
$status_filter = $_GET['status'] ?? '';
$search = trim($_GET['search'] ?? '');

$where = '1=1';
$params = [];
if ($status_filter) {
    $where .= ' AND c.incident_status = ?';
    $params[] = $status_filter;
}
if ($search) {
    $where .= ' AND (c.docket_no LIKE ? OR c.organization_name LIKE ? OR c.circuit_id LIKE ?)';
    $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%";
}

// Main complaints query
$stmt = $pdo->prepare("
    SELECT 
        c.id,
        c.docket_no,
        c.organization_name,
        c.circuit_id,
        c.bandwidth,
        c.link_type,
        c.incident_status,
        c.docket_booking_time,
        c.next_update_time,
        c.remarks,
        c.docket_closed_time,
        c.created_by,
        c.closed_by,
        c.assigned_to
    FROM complaints c
    WHERE $where
    ORDER BY c.docket_booking_time DESC
");
$stmt->execute($params);
$complaints = $stmt->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: text/csv');
header('Content-Disposition: attachment;filename=complaints_export.csv');

$output = fopen('php://output', 'w');
// CSV header
fputcsv($output, array(
    'Docket', 'Organization', 'Circuit ID', 'Bandwidth', 'Link Type', 'Status',
    'Booking Time', 'Next Update', 'Remarks', 'Closed Time',
    'Raised By', 'Assigned To', 'Closed By', 'Complaint History'
));

foreach ($complaints as $row) {
    // Fetch complaint history for this complaint
    $history_stmt = $pdo->prepare("SELECT incident_status, remarks, next_update_time, customer_communications_call, alarm_status, rfo, updated_by, updated_time FROM complaint_history WHERE rid = ? ORDER BY updated_time ASC");
    $history_stmt->execute([$row['id']]);
    $histories = $history_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format history as multiline text
    $history_lines = [];
    foreach ($histories as $h) {
        $history_lines[] =
            "Status: {$h['incident_status']}; ".
            "Remarks: {$h['remarks']}; ".
            "Next Update: {$h['next_update_time']}; ".
            "Call: {$h['customer_communications_call']}; ".
            "Alarm: {$h['alarm_status']}; ".
            "RFO: {$h['rfo']}; ".
            "Updated By: {$h['updated_by']}; ".
            "Update Time: {$h['updated_time']}";
    }
    $history_cell = implode("\n---\n", $history_lines);

    fputcsv($output, array(
        $row['docket_no'],
        $row['organization_name'],
        $row['circuit_id'],
        $row['bandwidth'],
        $row['link_type'],
        $row['incident_status'],
        $row['docket_booking_time'],
        $row['next_update_time'],
        $row['remarks'],
        $row['docket_closed_time'],
        $row['created_by'],
        $row['assigned_to'],
        $row['closed_by'],
        $history_cell
    ));
}
fclose($output);
exit;
?>