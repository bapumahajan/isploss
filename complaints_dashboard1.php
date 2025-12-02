<?php
// File: complaints_dashboard.php

session_name('oss_portal');
session_start();
require_once 'includes/db.php';

if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit;
}

$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'docket_booking_time DESC';
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

$where = '1=1';
$params = [];
if ($status_filter) {
    $where .= ' AND c.incident_status = ?';
    $params[] = $status_filter;
}
if ($search) {
    $where .= ' AND (c.docket_no LIKE ? OR c.organization_name LIKE ? OR c.circuit_id LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$count_stmt = $pdo->prepare("SELECT COUNT(*) FROM complaints c WHERE $where");
$count_stmt->execute($params);
$total_rows = $count_stmt->fetchColumn();
$total_pages = ceil($total_rows / $per_page);

$stmt = $pdo->prepare("
    SELECT c.*, f.fault_name 
    FROM complaints c
    LEFT JOIN fault_type f ON c.fault_type_id = f.id
    WHERE $where
    ORDER BY $sort
    LIMIT $per_page OFFSET $offset
");
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$status_count_stmt = $pdo->query("SELECT incident_status, COUNT(*) as cnt FROM complaints GROUP BY incident_status");
$status_counts = [];
foreach ($status_count_stmt as $row) {
    $status_counts[$row['incident_status']] = $row['cnt'];
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Complaints Dashboard</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body>
<div class="container py-3">
    <div class="d-flex justify-content-between mb-3">
        <h4><i class="bi bi-list-task"></i> Complaints Dashboard</h4>
        <button class="btn btn-success btn-sm" onclick="openDocketPortal();return false;"><i class="bi bi-flag"></i> Raise Docket</button>
    </div>

    <div class="mb-3">
        <span class="badge bg-success">Open: <?= $status_counts['open'] ?? 0 ?></span>
        <span class="badge bg-warning text-dark">Hold: <?= $status_counts['Hold'] ?? 0 ?></span>
        <span class="badge bg-info text-dark">Pending: <?= $status_counts['Pending With Customer'] ?? 0 ?></span>
        <span class="badge bg-secondary">Closed: <?= $status_counts['closed'] ?? 0 ?></span>
    </div>

    <form method="get" class="row row-cols-auto g-2 align-items-end mb-3">
        <div class="col">
            <label>Status</label>
            <select name="status" class="form-select form-select-sm">
                <option value="">All</option>
                <option value="open" <?= $status_filter == 'open' ? 'selected' : '' ?>>Open</option>
                <option value="Hold" <?= $status_filter == 'Hold' ? 'selected' : '' ?>>Hold</option>
                <option value="Pending With Customer" <?= $status_filter == 'Pending With Customer' ? 'selected' : '' ?>>Pending</option>
                <option value="closed" <?= $status_filter == 'closed' ? 'selected' : '' ?>>Closed</option>
            </select>
        </div>
        <div class="col">
            <label>Search</label>
            <input type="text" name="search" class="form-control form-control-sm" value="<?= htmlspecialchars($search) ?>">
        </div>
        <div class="col">
            <button class="btn btn-primary btn-sm" type="submit"><i class="bi bi-search"></i> Filter</button>
        </div>
        <div class="col">
            <a href="complaints_dashboard.php" class="btn btn-outline-secondary btn-sm">Reset</a>
        </div>
    </form>

    <div class="table-responsive">
        <table class="table table-bordered table-hover table-sm">
            <thead class="table-light">
                <tr>
                    <th>Docket No</th>
                    <th>Org</th>
                    <th>Circuit</th>
                    <th>Type</th>
                    <th>Status</th>
                    <th>Booking</th>
                    <th>Last Update</th>
                    <th>Remarks</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                <tr>
                    <td><b><?= htmlspecialchars($row['docket_no']) ?></b></td>
                    <td><?= htmlspecialchars($row['organization_name']) ?></td>
                    <td><?= htmlspecialchars($row['circuit_id']) ?></td>
                    <td><?= htmlspecialchars($row['fault_name']) ?></td>
                    <td>
                        <?php
                        $status = $row['incident_status'];
                        if ($status == 'open') echo '<span class="badge bg-success">Open</span>';
                        elseif ($status == 'Hold') echo '<span class="badge bg-warning text-dark">Hold</span>';
                        elseif ($status == 'Pending With Customer') echo '<span class="badge bg-info text-dark">Pending</span>';
                        elseif ($status == 'closed') echo '<span class="badge bg-secondary">Closed</span>';
                        else echo htmlspecialchars($status);
                        ?>
                    </td>
                    <td><?= htmlspecialchars($row['docket_booking_time']) ?></td>
                    <td><?= htmlspecialchars($row['updated_time']) ?></td>
                    <td style="max-width:220px;white-space:pre-wrap;"> <?= htmlspecialchars($row['remarks']) ?></td>
                    <td>
                        <?php if ($status != 'closed'): ?>
                            <a class="btn btn-outline-primary btn-sm" onclick="openComplaintHandle('<?= $row['id'] ?>')"><i class="bi bi-pencil-square"></i> Handle</a>
                        <?php else: ?>
                            <span class="badge bg-secondary">Closed</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (!$rows): ?>
                    <tr><td colspan="9" class="text-center text-muted">No complaints found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($total_pages > 1): ?>
    <nav>
        <ul class="pagination justify-content-end">
            <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                <li class="page-item<?= $p == $page ? ' active' : '' ?>">
                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $p])) ?>"> <?= $p ?> </a>
                </li>
            <?php endfor; ?>
        </ul>
    </nav>
    <?php endif; ?>
</div>

<div class="modal fade" id="complaintModal" tabindex="-1" aria-labelledby="complaintModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="complaintModalLabel">Complaint Portal</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-0" style="height:80vh;">
        <iframe id="complaintModalFrame" class="modal-iframe" style="width:100%; height:100%; border:0;"></iframe>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
function openDocketPortal() {
    var url = "complaints_portal.php";
    showModal(url, 'Raise Docket');
}

function openComplaintHandle(id) {
    var url = "complaints_portal.php?id=" + encodeURIComponent(id);
    showModal(url, 'Handle Complaint');
}

function showModal(url, title) {
    document.getElementById('complaintModalFrame').src = url;
    document.getElementById('complaintModalLabel').innerText = title;
    var myModal = new bootstrap.Modal(document.getElementById('complaintModal'));
    myModal.show();
}
</script>

</body>
</html>