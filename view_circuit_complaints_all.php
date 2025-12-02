<?php
session_name('oss_portal');
session_start();
require_once 'includes/db.php';

if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

// Get circuit id from GET parameter
$circuit_id = trim($_GET['circuit_id'] ?? '');

?>
<!DOCTYPE html>
<html>
<head>
    <title>Circuit Complaints & History</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
</head>
<body>
<div class="container">
    <h3 class="mt-3 mb-4">View Complaints & History for Circuit ID</h3>
    <form method="get" class="mb-4">
        <div class="row g-2 align-items-center">
            <div class="col-auto">
                <input type="text" name="circuit_id" class="form-control" placeholder="Enter Circuit ID" required value="<?= htmlspecialchars($circuit_id) ?>">
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-primary">Show Complaints</button>
            </div>
        </div>
    </form>
    <?php if ($circuit_id): ?>
        <?php
        // Fetch complaints for this circuit
        $stmt = $pdo->prepare("SELECT * FROM complaints WHERE circuit_id = ? ORDER BY docket_booking_time DESC");
        $stmt->execute([$circuit_id]);
        $complaints = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($complaints):
            foreach ($complaints as $comp):
        ?>
            <div class="card mb-4">
                <div class="card-header">
                    <b>Docket No:</b> <?= htmlspecialchars($comp['docket_no']) ?>
                    | <b>Status:</b> <?= htmlspecialchars($comp['incident_status']) ?>
                </div>
                <div class="card-body">
                    <div class="row mb-2">
                        <div class="col-sm-6"><b>Organization:</b> <?= htmlspecialchars($comp['organization_name']) ?></div>
                        <div class="col-sm-6"><b>Booking Time:</b> <?= htmlspecialchars($comp['docket_booking_time']) ?></div>
                    </div>
                    <div class="row mb-2">
                        <div class="col-sm-6"><b>Remarks:</b> <?= nl2br(htmlspecialchars($comp['remarks'])) ?></div>
                        <div class="col-sm-6"><b>Closed Time:</b> <?= htmlspecialchars($comp['docket_closed_time']) ?></div>
                    </div>
                    <div class="row mb-2">
                        <div class="col-sm-6"><b>Raised By:</b> <?= htmlspecialchars($comp['created_by']) ?></div>
                        <div class="col-sm-6"><b>Closed By:</b> <?= htmlspecialchars($comp['closed_by']) ?></div>
                    </div>
                    <h6 class="mt-3">Complaint History:</h6>
                    <?php
                    // Fetch history for this complaint
                    $hist_stmt = $pdo->prepare("SELECT * FROM complaint_history WHERE rid = ? ORDER BY updated_time ASC");
                    $hist_stmt->execute([$comp['id']]);
                    $histories = $hist_stmt->fetchAll(PDO::FETCH_ASSOC);
                    if ($histories):
                    ?>
                        <table class="table table-bordered table-sm">
                            <thead class="table-light">
                                <tr>
                                    <th>Status</th>
                                    <th>Remarks</th>
                                    <th>Next Update</th>
                                    <th>Customer Call</th>
                                    <th>Alarm Status</th>
                                    <th>RFO</th>
                                    <th>Updated By</th>
                                    <th>Update Time</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($histories as $h): ?>
                                <tr>
                                    <td><?= htmlspecialchars($h['incident_status']) ?></td>
                                    <td><?= nl2br(htmlspecialchars($h['remarks'])) ?></td>
                                    <td><?= htmlspecialchars($h['next_update_time']) ?></td>
                                    <td><?= nl2br(htmlspecialchars($h['customer_communications_call'])) ?></td>
                                    <td><?= htmlspecialchars($h['alarm_status']) ?></td>
                                    <td><?= nl2br(htmlspecialchars($h['rfo'])) ?></td>
                                    <td><?= htmlspecialchars($h['updated_by']) ?></td>
                                    <td><?= htmlspecialchars($h['updated_time']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p class="text-muted">No history available.</p>
                    <?php endif; ?>
                </div>
            </div>
        <?php
            endforeach;
        else:
            echo '<div class="alert alert-info">No complaints found for Circuit ID <b>' . htmlspecialchars($circuit_id) . '</b>.</div>';
        endif;
        ?>
    <?php endif; ?>
</div>
</body>
</html>