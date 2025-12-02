<?php
session_name('oss_portal');
session_start();
require_once 'includes/auth.php';
require_roles(['admin', 'network_manager']);
require_once 'includes/db.php';

// DELETE handler
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $stmt = $pdo->prepare("DELETE FROM third_party_details WHERE id = ?");
    $stmt->execute([intval($_POST['delete_id'])]);
    header("Location: ".$_SERVER['PHP_SELF']); exit;
}

// ADD handler
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_third_party'])) {
    $stmt = $pdo->prepare("INSERT INTO third_party_details (
        circuit_id, Third_party_type, tp_circuit_id, Third_party_name, tp_type_service, link_type,
        end_a, end_b, bandwidth, switch_name, switch_ip, switch_port, vlan, last_mile_media,
        operator_name, operator_contact_no, operator_email_id, remarks
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $_POST['circuit_id'], $_POST['Third_party_type'], $_POST['tp_circuit_id'], $_POST['Third_party_name'],
        $_POST['tp_type_service'], $_POST['link_type'], $_POST['end_a'], $_POST['end_b'], $_POST['bandwidth'],
        $_POST['switch_name'], $_POST['switch_ip'], $_POST['switch_port'], $_POST['vlan'], $_POST['last_mile_media'],
        $_POST['operator_name'], $_POST['operator_contact_no'], $_POST['operator_email_id'], $_POST['remarks']
    ]);
    header("Location: ".$_SERVER['PHP_SELF']); exit;
}

// EDIT handler
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_id'])) {
    $stmt = $pdo->prepare("UPDATE third_party_details SET
        circuit_id = ?, Third_party_type = ?, tp_circuit_id = ?, Third_party_name = ?, tp_type_service = ?,
        link_type = ?, end_a = ?, end_b = ?, bandwidth = ?, switch_name = ?, switch_ip = ?, switch_port = ?,
        vlan = ?, last_mile_media = ?, operator_name = ?, operator_contact_no = ?, operator_email_id = ?, remarks = ?
        WHERE id = ?");
    $stmt->execute([
        $_POST['circuit_id'], $_POST['Third_party_type'], $_POST['tp_circuit_id'], $_POST['Third_party_name'],
        $_POST['tp_type_service'], $_POST['link_type'], $_POST['end_a'], $_POST['end_b'], $_POST['bandwidth'],
        $_POST['switch_name'], $_POST['switch_ip'], $_POST['switch_port'], $_POST['vlan'], $_POST['last_mile_media'],
        $_POST['operator_name'], $_POST['operator_contact_no'], $_POST['operator_email_id'], $_POST['remarks'],
        intval($_POST['edit_id'])
    ]);
    header("Location: ".$_SERVER['PHP_SELF']); exit;
}

// Fetch all rows
$stmt = $pdo->query("
    SELECT t.*, c.organization_name
    FROM third_party_details t
    LEFT JOIN customer_basic_information c ON t.circuit_id = c.circuit_id
    ORDER BY t.id DESC
");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Third Party Details</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>body { font-size: 13px; background: #f8fafc; }</style>
</head>
<body>
<div class="container-fluid mt-3">
    <div class="card shadow-sm">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <h6 class="mb-0">Third Party Details</h6>
            <button class="btn btn-light btn-sm" data-bs-toggle="collapse" data-bs-target="#addForm">
                <i class="bi bi-plus-circle"></i> Add New
            </button>
        </div>
        <div class="collapse border p-3" id="addForm">
            <form method="post">
                <input type="hidden" name="add_third_party" value="1">
                <div class="row g-2">
                    <?php
                    $fields = [
                        'circuit_id', 'Third_party_type', 'tp_circuit_id', 'Third_party_name', 'tp_type_service',
                        'link_type', 'end_a', 'end_b', 'bandwidth', 'switch_name', 'switch_ip', 'switch_port',
                        'vlan', 'last_mile_media', 'operator_name', 'operator_contact_no', 'operator_email_id', 'remarks'
                    ];
                    foreach ($fields as $field) {
                        echo '<div class="col-md-3"><input name="'.$field.'" class="form-control form-control-sm" placeholder="'.ucwords(str_replace('_', ' ', $field)).'" required></div>';
                    }
                    ?>
                </div>
                <div class="mt-2"><button type="submit" class="btn btn-sm btn-success"><i class="bi bi-check-circle"></i> Submit</button></div>
            </form>
        </div>
        <div class="card-body">
            <table id="thirdPartyTable" class="table table-bordered table-striped table-sm">
                <thead><tr>
                    <th>ID</th><th>Circuit ID</th><th>Org</th><th>Type</th><th>TP Circuit</th><th>Provider</th><th>Service</th>
                    <th>Link</th><th>End A</th><th>End B</th><th>BW</th><th>Switch</th><th>IP</th><th>Port</th><th>VLAN</th>
                    <th>Media</th><th>Op Name</th><th>Contact</th><th>Email</th><th>Remarks</th><th>Created</th><th>Action</th>
                </tr></thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><?= $row['id'] ?></td>
                        <td><?= htmlspecialchars($row['circuit_id']) ?></td>
                        <td><?= htmlspecialchars($row['organization_name']) ?></td>
                        <td><?= htmlspecialchars($row['Third_party_type']) ?></td>
                        <td><?= htmlspecialchars($row['tp_circuit_id']) ?></td>
                        <td><?= htmlspecialchars($row['Third_party_name']) ?></td>
                        <td><?= htmlspecialchars($row['tp_type_service']) ?></td>
                        <td><?= htmlspecialchars($row['link_type']) ?></td>
                        <td><?= htmlspecialchars($row['end_a']) ?></td>
                        <td><?= htmlspecialchars($row['end_b']) ?></td>
                        <td><?= htmlspecialchars($row['bandwidth']) ?></td>
                        <td><?= htmlspecialchars($row['switch_name']) ?></td>
                        <td><?= htmlspecialchars($row['switch_ip']) ?></td>
                        <td><?= htmlspecialchars($row['switch_port']) ?></td>
                        <td><?= htmlspecialchars($row['vlan']) ?></td>
                        <td><?= htmlspecialchars($row['last_mile_media']) ?></td>
                        <td><?= htmlspecialchars($row['operator_name']) ?></td>
                        <td><?= htmlspecialchars($row['operator_contact_no']) ?></td>
                        <td><?= htmlspecialchars($row['operator_email_id']) ?></td>
                        <td><?= htmlspecialchars($row['remarks']) ?></td>
                        <td><?= htmlspecialchars($row['created_at']) ?></td>
                        <td class="d-flex gap-1">
                            <form method="post" onsubmit="return confirm('Delete this entry?');">
                                <input type="hidden" name="delete_id" value="<?= $row['id'] ?>">
                                <button class="btn btn-sm btn-danger"><i class="bi bi-trash"></i></button>
                            </form>
                            <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editModal<?= $row['id'] ?>"><i class="bi bi-pencil-square"></i></button>
                        </td>
                    </tr>

                    <!-- Modal for Editing -->
                    <div class="modal fade" id="editModal<?= $row['id'] ?>" tabindex="-1">
                        <div class="modal-dialog modal-xl"><div class="modal-content">
                            <form method="post">
                                <input type="hidden" name="edit_id" value="<?= $row['id'] ?>">
                                <div class="modal-header bg-dark text-white"><h5 class="modal-title">Edit Entry #<?= $row['id'] ?></h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="row g-2">
                                        <?php foreach ($fields as $field): ?>
                                            <div class="col-md-3">
                                                <input name="<?= $field ?>" class="form-control form-control-sm"
                                                       value="<?= htmlspecialchars($row[$field]) ?>" placeholder="<?= ucwords(str_replace('_',' ',$field)) ?>" required>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button class="btn btn-success btn-sm"><i class="bi bi-save"></i> Update</button>
                                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                                </div>
                            </form>
                        </div></div>
                    </div>
                <?php endforeach; ?>
                </tbody>
            </table>
            <a href="dashboard.php" class="btn btn-outline-secondary btn-sm mt-2"><i class="bi bi-arrow-left"></i> Back</a>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
<script>
$(document).ready(function () {
    $('#thirdPartyTable').DataTable({ pageLength: 25, order: [[0, 'desc']] });
});
</script>
</body>
</html>
