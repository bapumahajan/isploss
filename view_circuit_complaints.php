<?php
require_once 'includes/db.php';
$circuit_id = trim($_GET['circuit_id'] ?? '');
?>
<div class="container-fluid px-2 py-2">
    <h5 class="mb-3">View Complaints & History for Circuit ID</h5>
    <form method="get" class="mb-3" id="circuitForm" action="">
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
                        <div class="col-sm-6"><b>Booking Time:</b> <?= !empty($comp['docket_booking_time']) ? htmlspecialchars($comp['docket_booking_time']) : 'N/A' ?></div>
                    </div>
                    <div class="row mb-2">
                        <div class="col-sm-6"><b>Remarks:</b> <?= nl2br(htmlspecialchars($comp['remarks'])) ?></div>
                        <div class="col-sm-6"><b>Closed Time:</b> <?= !empty($comp['docket_closed_time']) ? htmlspecialchars($comp['docket_closed_time']) : 'N/A' ?></div>
                    </div>
                    <div class="row mb-2">
                        <div class="col-sm-6"><b>Raised By:</b> <?= !empty($comp['created_by']) ? htmlspecialchars($comp['created_by']) : 'N/A' ?></div>
                        <div class="col-sm-6"><b>Closed By:</b> <?= !empty($comp['closed_by']) ? htmlspecialchars($comp['closed_by']) : 'N/A' ?></div>
                    </div>
                    <h6 class="mt-3">Complaint History:</h6>
                    <?php
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
                                    <td><?= !empty($h['incident_status']) ? htmlspecialchars($h['incident_status']) : 'N/A' ?></td>
                                    <td><?= !empty($h['remarks']) ? nl2br(htmlspecialchars($h['remarks'])) : 'N/A' ?></td>
                                    <td><?= !empty($h['next_update_time']) ? htmlspecialchars($h['next_update_time']) : 'N/A' ?></td>
                                    <td><?= !empty($h['customer_communications_call']) ? htmlspecialchars($h['customer_communications_call']) : 'N/A' ?></td>
                                    <td><?= !empty($h['alarm_status']) ? htmlspecialchars($h['alarm_status']) : 'N/A' ?></td>
                                    <td><?= !empty($h['rfo']) ? nl2br(htmlspecialchars($h['rfo'])) : 'N/A' ?></td>
                                    <td><?= !empty($h['updated_by']) ? htmlspecialchars($h['updated_by']) : 'N/A' ?></td>
                                    <td><?= !empty($h['updated_time']) ? htmlspecialchars($h['updated_time']) : 'N/A' ?></td>
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