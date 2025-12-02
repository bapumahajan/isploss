<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once 'includes/db.php';

// --- Fetch circuits (existing logic reused) ---
function get_circuits($pdo, $circuit_id = null) {
    $where = $circuit_id ? "WHERE cbi.circuit_id = :circuit_id" : "";
    $sql = "SELECT cbi.circuit_id, cbi.organization_name, nd.bandwidth, nd.link_type
            FROM customer_basic_information cbi
            LEFT JOIN network_details nd ON cbi.circuit_id = nd.circuit_id
            $where";
    $stmt = $pdo->prepare($sql);
    if ($circuit_id) $stmt->execute(['circuit_id' => $circuit_id]);
    else $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// --- Fetch complaints for a circuit ---
function get_complaints($pdo, $circuit_id) {
    $sql = "SELECT * FROM complaints WHERE circuit_id = ? ORDER BY docket_booking_time DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$circuit_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// --- Fetch history for a docket ---
function get_complaint_history($pdo, $rid) {
    $sql = "SELECT * FROM complaint_history WHERE rid = ? ORDER BY updated_time DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$rid]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// --- Main logic ---
if (isset($_GET['circuit_id'])) {
    $circuit_id = $_GET['circuit_id'];
    $complaints = get_complaints($pdo, $circuit_id);
    ?>
    <div class="tab-pane fade show active" id="complaints<?= htmlspecialchars($circuit_id) ?>">
        <h5>Complaints / Dockets</h5>
        <?php if ($complaints): ?>
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Docket No</th>
                        <th>Booking Time</th>
                        <th>Status</th>
                        <th>Updated</th>
                        <th>Next Update</th>
                        <th>Remarks</th>
                        <th>History</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($complaints as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['docket_no']) ?></td>
                        <td><?= htmlspecialchars($row['docket_booking_time']) ?></td>
                        <td><?= htmlspecialchars($row['incident_status']) ?></td>
                        <td><?= htmlspecialchars($row['updated_time']) ?></td>
                        <td><?= htmlspecialchars($row['next_update_time']) ?></td>
                        <td><?= htmlspecialchars($row['remarks']) ?></td>
                        <td>
                            <button class="btn btn-sm btn-info" type="button" data-bs-toggle="collapse" data-bs-target="#history<?= $row['id'] ?>">Show</button>
                        </td>
                    </tr>
                    <tr class="collapse" id="history<?= $row['id'] ?>">
                        <td colspan="7">
                            <strong>History:</strong>
                            <ul>
                                <?php
                                    $history = get_complaint_history($pdo, $row['id']);
                                    foreach ($history as $h) {
                                        echo "<li>Status: ".htmlspecialchars($h['incident_status']).", Remark: ".htmlspecialchars($h['remarks']).", Updated: ".htmlspecialchars($h['updated_time'])."</li>";
                                    }
                                ?>
                            </ul>
                        </td>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="alert alert-info">No complaints/dockets for this circuit.</div>
        <?php endif; ?>
    </div>
    <?php
} else {
    // Show all circuits as links
    $circuits = get_circuits($pdo);
    echo "<h4>Select a Circuit to view complaints:</h4><ul>";
    foreach ($circuits as $c) {
        echo "<li><a href='?circuit_id=" . urlencode($c['circuit_id']) . "'>"
            . htmlspecialchars($c['circuit_id'] . " - " . $c['organization_name']) . "</a></li>";
    }
    echo "</ul>";
}
?>