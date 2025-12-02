<?php
session_name('oss_portal');
session_start();
require 'includes/config.php';

// CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle Add Model
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_model') {
    if ($_POST['csrf_token'] !== $_SESSION['csrf_token']) die('CSRF Mismatch');
    $model_name = trim($_POST['model_name'] ?? '');
    $vendor = trim($_POST['vendor'] ?? '');
    $model_cost = floatval($_POST['model_cost'] ?? 0);
    if ($model_name && $vendor && $model_cost >= 0) {
        $stmt = $conn->prepare("INSERT INTO device_models (model_name, vendor, model_cost) VALUES (?, ?, ?)");
        $stmt->bind_param("ssd", $model_name, $vendor, $model_cost);
        $stmt->execute();
        $stmt->close();
        $_SESSION['message'] = "Model added successfully.";
    }
    header("Location: manage_models.php");
    exit;
}

// Handle Edit Model
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit_model') {
    if ($_POST['csrf_token'] !== $_SESSION['csrf_token']) die('CSRF Mismatch');
    $model_id = intval($_POST['model_id']);
    $model_name = trim($_POST['model_name'] ?? '');
    $vendor = trim($_POST['vendor'] ?? '');
    $model_cost = floatval($_POST['model_cost'] ?? 0);
    if ($model_name && $vendor && $model_cost >= 0) {
        $stmt = $conn->prepare("UPDATE device_models SET model_name = ?, vendor = ?, model_cost = ? WHERE model_id = ?");
        $stmt->bind_param("ssdi", $model_name, $vendor, $model_cost, $model_id);
        $stmt->execute();
        $stmt->close();
        $_SESSION['message'] = "Model updated successfully.";
    }
    header("Location: manage_models.php");
    exit;
}

// Handle Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_model') {
    if ($_POST['csrf_token'] !== $_SESSION['csrf_token']) die('CSRF Mismatch');
    $model_id = intval($_POST['model_id']);
    $stmt = $conn->prepare("DELETE FROM device_models WHERE model_id = ?");
    $stmt->bind_param("i", $model_id);
    $stmt->execute();
    $stmt->close();
    $_SESSION['message'] = "Model deleted.";
    header("Location: manage_models.php");
    exit;
}

// Fetch all models
$models = [];
$result = $conn->query("SELECT * FROM device_models ORDER BY model_name ASC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $models[] = $row;
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Manage Device Models</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4><i class="bi bi-gear-fill"></i> Manage Device Models</h4>
        <a href="device_inventory.php" class="btn btn-sm btn-secondary">← Back to Inventory</a>
    </div>

    <?php if (!empty($_SESSION['message'])): ?>
        <div class="alert alert-success"><?= $_SESSION['message']; unset($_SESSION['message']); ?></div>
    <?php endif; ?>

    <button class="btn btn-primary btn-sm mb-3" data-bs-toggle="modal" data-bs-target="#addModelModal">+ Add Model</button>

    <table id="modelTable" class="table table-bordered table-sm table-striped">
        <thead>
            <tr>
                <th>#</th>
                <th>Model Name</th>
                <th>Vendor</th>
                <th>Cost (₹)</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php $i=1; foreach ($models as $m): ?>
            <tr>
                <td><?= $i++ ?></td>
                <td><?= htmlspecialchars($m['model_name']) ?></td>
                <td><?= htmlspecialchars($m['vendor']) ?></td>
                <td><?= number_format($m['model_cost'], 2) ?></td>
                <td>
                    <!-- Edit -->
                    <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editModelModal<?= $m['model_id'] ?>">Edit</button>

                    <!-- Delete -->
                    <form method="post" action="manage_models.php" style="display:inline-block" onsubmit="return confirm('Delete this model?')">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <input type="hidden" name="model_id" value="<?= $m['model_id'] ?>">
                        <input type="hidden" name="action" value="delete_model">
                        <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                    </form>
                </td>
            </tr>

            <!-- Edit Modal -->
            <div class="modal fade" id="editModelModal<?= $m['model_id'] ?>" tabindex="-1">
              <div class="modal-dialog">
                <form method="post" action="manage_models.php" class="modal-content">
                  <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                  <input type="hidden" name="model_id" value="<?= $m['model_id'] ?>">
                  <input type="hidden" name="action" value="edit_model">
                  <div class="modal-header"><h5 class="modal-title">Edit Model</h5></div>
                  <div class="modal-body">
                      <div class="mb-2">
                          <label class="form-label">Model Name</label>
                          <input type="text" name="model_name" class="form-control" required value="<?= htmlspecialchars($m['model_name']) ?>">
                      </div>
                      <div class="mb-2">
                          <label class="form-label">Vendor</label>
                          <input type="text" name="vendor" class="form-control" required value="<?= htmlspecialchars($m['vendor']) ?>">
                      </div>
                      <div class="mb-2">
                          <label class="form-label">Cost (₹)</label>
                          <input type="number" name="model_cost" step="0.01" min="0" class="form-control" value="<?= htmlspecialchars($m['model_cost']) ?>">
                      </div>
                  </div>
                  <div class="modal-footer">
                      <button type="submit" class="btn btn-success">Update</button>
                      <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                  </div>
                </form>
              </div>
            </div>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Add Modal -->
<div class="modal fade" id="addModelModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="post" action="manage_models.php" class="modal-content">
      <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
      <input type="hidden" name="action" value="add_model">
      <div class="modal-header"><h5 class="modal-title">Add New Model</h5></div>
      <div class="modal-body">
          <div class="mb-2">
              <label class="form-label">Model Name</label>
              <input type="text" name="model_name" class="form-control" required>
          </div>
          <div class="mb-2">
              <label class="form-label">Vendor</label>
              <input type="text" name="vendor" class="form-control" required>
          </div>
          <div class="mb-2">
              <label class="form-label">Cost (₹)</label>
              <input type="number" name="model_cost" step="0.01" min="0" class="form-control" required>
          </div>
      </div>
      <div class="modal-footer">
          <button type="submit" class="btn btn-primary">Add Model</button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
      </div>
    </form>
  </div>
</div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
<script>
    $(document).ready(function() {
        $('#modelTable').DataTable();
    });
</script>
</body>
</html>
