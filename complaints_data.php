<?php
require_once 'includes/db.php';

$q = $_GET['q'] ?? '';

$query = "SELECT * FROM complaints WHERE circuit_id LIKE ? OR status LIKE ? ORDER BY id DESC";
$stmt = $conn->prepare($query);
$search = "%$q%";
$stmt->bind_param('ss', $search, $search);
$stmt->execute();
$result = $stmt->get_result();

$rows = [];
$stats = [];

while ($row = $result->fetch_assoc()) {
    $rows[] = [
        'id' => $row['id'],
        'circuit_id' => $row['circuit_id'],
        'docket_no' => $row['docket_no'],
        'status' => $row['current_status'],
        'complaint_id' => $row['complaint_id'],
    ];
    $status = $row['current_status'];
    $stats[$status] = ($stats[$status] ?? 0) + 1;
}

echo json_encode(['rows' => $rows, 'stats' => $stats]);
?>
