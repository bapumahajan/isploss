<?php
// fetch_complaints.php
session_name('oss_portal');
session_start();
require_once 'includes/db.php';

$stmt = $pdo->prepare("
    SELECT c.*, f.fault_name 
    FROM complaints c
    LEFT JOIN fault_type f ON c.fault_type_id = f.id
    WHERE c.incident_status != 'closed'
    ORDER BY c.docket_booking_time ASC
");
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($rows) {
    foreach ($rows as $index => $row) {
        $status = strtolower($row['incident_status']);
        $bookingTime = strtotime($row['docket_booking_time']) * 1000;

        echo "<tr data-booking-time='{$bookingTime}'>";
        echo "<td><b>" . htmlspecialchars($row['docket_no']) . "</b></td>";
        echo "<td>" . htmlspecialchars($row['organization_name']) . "</td>";
        echo "<td>" . htmlspecialchars($row['circuit_id']) . "</td>";
        echo "<td>" . htmlspecialchars($row['fault_name']) . "</td>";
        echo "<td>";

        if ($status == 'open') echo '<span class="badge bg-success">Open</span>';
        elseif ($status == 'hold') echo '<span class="badge bg-warning text-dark">Hold</span>';
        elseif ($status == 'pending with customer') echo '<span class="badge bg-info text-dark">Pending</span>';

        echo "</td>";
        echo "<td>" . htmlspecialchars($row['docket_booking_time']) . "</td>";
        echo "<td><span id='timer{$index}' class='timer green'>00:00</span></td>";
        echo "<td style='max-width:220px;white-space:pre-wrap;'>" . htmlspecialchars($row['remarks']) . "</td>";
        echo "<td><a class='btn btn-outline-primary btn-sm' onclick=\"openComplaintHandle('{$row['circuit_id']}')\"><i class='bi bi-pencil-square'></i> Handle</a></td>";
        echo "</tr>";

        echo "<script>document.addEventListener('DOMContentLoaded', function() { startTimer('timer{$index}', {$bookingTime}); });</script>";
    }
} else {
    echo "<tr><td colspan='9' class='text-center text-muted'>No complaints found.</td></tr>";
}
?>
