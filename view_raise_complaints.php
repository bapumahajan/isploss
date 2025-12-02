<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once 'includes/db.php';

function get_circuit_details($pdo, $circuit_id) {
    $stmt = $pdo->prepare("
        SELECT 
            cbi.circuit_id,
            cbi.circuit_status,
            cbi.organization_name,
            nd.bandwidth,
            nd.link_type
        FROM customer_basic_information cbi
        LEFT JOIN network_details nd ON cbi.circuit_id = nd.circuit_id
        WHERE cbi.circuit_id = ?
    ");
    $stmt->execute([$circuit_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Handle complaint submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['raise_complaint'])) {
    $circuit_id = $_POST['circuit_id'];
    $organization_name = $_POST['organization_name'];
    $bandwidth = $_POST['bandwidth'];
    $link_type = $_POST['link_type'];
    $docket_no = $circuit_id . '-' . date('Ymd-His') . '-' . rand(100,999);
    $incident_status = $_POST['incident_status'];
    $docket_booking_time = date('Y-m-d H:i:s');
    $next_update_time = $_POST['next_update_time'];
    $remarks = $_POST['remarks'];

    // Insert into complaints
    $insert = $pdo->prepare("INSERT INTO complaints 
        (circuit_id, organization_name, bandwidth, link_type, docket_no, incident_status, docket_booking_time, next_update_time, remarks)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $insert->execute([
        $circuit_id, $organization_name, $bandwidth, $link_type,
        $docket_no, $incident_status, $docket_booking_time, $next_update_time, $remarks
    ]);

    $complaint_id = $pdo->lastInsertId();

    // Optionally, insert into complaint_history
    $insert_history = $pdo->prepare("INSERT INTO complaint_history
        (rid, incident_status, remarks, next_update_time, updated_time)
        VALUES (?, ?, ?, ?, ?)
    ");
    $insert_history->execute([
        $complaint_id, $incident_status, $remarks, $next_update_time, $docket_booking_time
    ]);

    $success = "Complaint raised successfully. Docket No: " . htmlspecialchars($docket_no);
}

// Handle circuit search
$circuit = null;
if (isset($_GET['search_circuit'])) {
    $cid = trim($_GET['search_circuit']);
    if ($cid) {
        $circuit = get_circuit_details($pdo, $cid);
        if (!$circuit) $error = "Circuit not found.";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Raise Complaint</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
</head>
<body class="p-4">
<div class="container">
    <h2>Find Circuit & Raise Complaint</h2>
    <form class="mb-4" method="get">
        <label for="search_circuit" class="form-label">Enter Circuit ID:</label>
        <div class="input-group mb-3">
            <input type="text" class="form-control" name="search_circuit" id="search_circuit" value="<?= htmlspecialchars($_GET['search_circuit'] ?? '') ?>" required>
            <button class="btn btn-primary" type="submit">Search</button>
        </div>
    </form>
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if (!empty($success)): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <?php if ($circuit): ?>
        <div class="card mb-3">
            <div class="card-header">Circuit Details</div>
            <div class="card-body">
                <table class="table">
                    <tr><th>Circuit ID</th><td><?= htmlspecialchars($circuit['circuit_id']) ?></td></tr>
                    <tr><th>Status</th><td><?= htmlspecialchars($circuit['circuit_status']) ?></td></tr>
                    <tr><th>Organization Name</th><td><?= htmlspecialchars($circuit['organization_name']) ?></td></tr>
                    <tr><th>Bandwidth</th><td><?= htmlspecialchars($circuit['bandwidth']) ?></td></tr>
                    <tr><th>Link Type</th><td><?= htmlspecialchars($circuit['link_type']) ?></td></tr>
                </table>
                <button class="btn btn-success" type="button" data-bs-toggle="collapse" data-bs-target="#raiseComplaintForm">Raise Complaint</button>
                <div class="collapse mt-3" id="raiseComplaintForm">
                    <form method="post">
                        <input type="hidden" name="circuit_id" value="<?= htmlspecialchars($circuit['circuit_id']) ?>">
                        <input type="hidden" name="organization_name" value="<?= htmlspecialchars($circuit['organization_name']) ?>">
                        <input type="hidden" name="bandwidth" value="<?= htmlspecialchars($circuit['bandwidth']) ?>">
                        <input type="hidden" name="link_type" value="<?= htmlspecialchars($circuit['link_type']) ?>">
                        <div class="mb-2">
                            <label for="incident_status" class="form-label">Incident Status</label>
                            <select class="form-select" name="incident_status" id="incident_status" required>
                                <option value="open">Open</option>
                                <option value="pending">Pending</option>
                                <option value="closed">Closed</option>
                            </select>
                        </div>
                        <div class="mb-2">
                            <label for="next_update_time" class="form-label">Next Update Time</label>
                            <input type="datetime-local" class="form-control" name="next_update_time" id="next_update_time" required>
                        </div>
                        <div class="mb-2">
                            <label for="remarks" class="form-label">Remarks</label>
                            <textarea class="form-control" name="remarks" id="remarks" required></textarea>
                        </div>
                        <button class="btn btn-primary" type="submit" name="raise_complaint" value="1">Submit Complaint</button>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>