<?php
// edit_circuit_id.php
session_name('oss_portal');
if (session_status() === PHP_SESSION_NONE) session_start();

require_once 'includes/auth.php';
require_roles(['admin', 'manager']);
require_once 'includes/db.php'; // $pdo PDO instance

// Fetch all existing circuit_ids from main table (network_details)
$stmt = $pdo->query("SELECT DISTINCT circuit_id FROM network_details ORDER BY circuit_id ASC");
$existingCircuitIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

$message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old_circuit_id = $_POST['old_circuit_id'] ?? '';
    $new_circuit_id = $_POST['new_circuit_id'] ?? '';

    if (!$old_circuit_id || !$new_circuit_id) {
        $message = '<div class="alert alert-danger">Both Circuit IDs are required.</div>';
    } else {
        try {
            // Check old circuit_id exists in main table (network_details)
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM network_details WHERE circuit_id = ?");
            $stmt->execute([$old_circuit_id]);
            if (!$stmt->fetchColumn()) {
                $message = '<div class="alert alert-danger">Current Circuit ID does not exist.</div>';
            } else {
                // Check new circuit_id does NOT exist
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM network_details WHERE circuit_id = ?");
                $stmt->execute([$new_circuit_id]);
                if ($stmt->fetchColumn()) {
                    $message = '<div class="alert alert-danger">New Circuit ID already exists. Choose a different one.</div>';
                } else {
                    // Begin transaction
                    $pdo->beginTransaction();

                    // Tables where circuit_id needs update
                    $tables = [
                        'customer_basic_information',
                        'network_details',
                        'circuit_network_details',
                        'circuit_ips'
                        // add more if needed
                    ];

                    foreach ($tables as $table) {
                        $sql = "UPDATE $table SET circuit_id = ? WHERE circuit_id = ?";
                        $update = $pdo->prepare($sql);
                        $update->execute([$new_circuit_id, $old_circuit_id]);
                    }

                    $pdo->commit();
                    $message = '<div class="alert alert-success">Circuit ID updated successfully in all tables.</div>';

                    // Refresh existing circuit IDs list (after update)
                    $stmt = $pdo->query("SELECT DISTINCT circuit_id FROM network_details ORDER BY circuit_id ASC");
                    $existingCircuitIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
                }
            }
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $message = '<div class="alert alert-danger">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Circuit ID</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
<div class="container mt-5">
    <h2>Edit Circuit ID Across All Tables</h2>
    <?= $message ?>

    <form method="POST" id="editCircuitIdForm" class="mt-4">
        <div class="mb-3">
            <label for="old_circuit_id" class="form-label">Select Current Circuit ID</label>
            <select id="old_circuit_id" name="old_circuit_id" class="form-select" required>
                <option value="" selected disabled>-- Select Circuit ID --</option>
                <?php foreach ($existingCircuitIds as $circuitId): ?>
                    <option value="<?= htmlspecialchars($circuitId) ?>"><?= htmlspecialchars($circuitId) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="mb-3">
            <label for="new_circuit_id" class="form-label">New Circuit ID</label>
            <input type="text" id="new_circuit_id" name="new_circuit_id" class="form-control" required>
            <div id="newCircuitIdFeedback" class="form-text text-danger" style="display:none;"></div>
        </div>

        <button type="button" id="btnConfirmUpdate" class="btn btn-primary" disabled>Update Circuit ID</button>
    </form>
</div>

<!-- Confirmation Modal -->
<div class="modal fade" id="confirmModal" tabindex="-1" aria-labelledby="confirmModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form id="confirmForm" method="POST">
        <div class="modal-header">
          <h5 class="modal-title" id="confirmModalLabel">Confirm Circuit ID Update</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          Are you sure you want to change the Circuit ID from
          <strong id="confirmOldId"></strong> to <strong id="confirmNewId"></strong> ?
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-danger">Yes, Update</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
<script>
$(document).ready(function() {
    // Disable submit button initially
    let $btnConfirmUpdate = $('#btnConfirmUpdate');
    let $newCircuitId = $('#new_circuit_id');
    let $oldCircuitId = $('#old_circuit_id');
    let $feedback = $('#newCircuitIdFeedback');

    function validateNewCircuitId() {
        let newId = $newCircuitId.val().trim();
        if (!newId) {
            $feedback.hide();
            $btnConfirmUpdate.prop('disabled', true);
            return;
        }
        // AJAX call to check if new ID already exists
        $.ajax({
            url: 'check_circuit_id.php',
            type: 'POST',
            data: {circuit_id: newId},
            success: function(response) {
                if (response.exists) {
                    $feedback.text('This Circuit ID already exists. Please choose another.').show();
                    $btnConfirmUpdate.prop('disabled', true);
                } else {
                    $feedback.hide();
                    if ($oldCircuitId.val()) {
                        $btnConfirmUpdate.prop('disabled', false);
                    }
                }
            },
            error: function() {
                $feedback.text('Error checking Circuit ID.').show();
                $btnConfirmUpdate.prop('disabled', true);
            }
        });
    }

    $newCircuitId.on('input', validateNewCircuitId);

    // Also enable/disable button when old circuit ID changes
    $oldCircuitId.on('change', function() {
        if ($newCircuitId.val().trim() && !$feedback.is(':visible')) {
            $btnConfirmUpdate.prop('disabled', false);
        } else {
            $btnConfirmUpdate.prop('disabled', true);
        }
    });

    // Show confirmation modal on click
    $btnConfirmUpdate.on('click', function() {
        let oldId = $oldCircuitId.val();
        let newId = $newCircuitId.val().trim();

        if (!oldId || !newId) return;

        $('#confirmOldId').text(oldId);
        $('#confirmNewId').text(newId);
        let confirmModal = new bootstrap.Modal(document.getElementById('confirmModal'));
        confirmModal.show();
    });

    // On modal form submit, copy values to hidden inputs and submit actual form
    $('#confirmForm').on('submit', function(e) {
        e.preventDefault();

        // Set values in the original form and submit
        $('#editCircuitIdForm').off('submit'); // remove previous submit handlers if any
        $('#editCircuitIdForm').submit();
    });
});
</script>
</body>
</html>
