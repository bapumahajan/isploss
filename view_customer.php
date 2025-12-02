<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_name('oss_portal');
session_start();

if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit;
}
include 'includes/db.php';

$successMsg = isset($_GET['deleted']) && $_GET['deleted'] == 1 ? 'Customer circuit deleted successfully.' : '';
$errorMsg = isset($_GET['delete_error']) ? 'Error deleting customer circuit: ' . htmlspecialchars($_GET['delete_error']) : '';

$query = "
    SELECT cbi.circuit_id, cbi.organization_name, cbi.customer_address, nd.product_type, nd.circuit_status
    FROM customer_basic_information AS cbi
    JOIN network_details AS nd ON cbi.circuit_id = nd.circuit_id
    ORDER BY cbi.circuit_id DESC
";
$stmt = $pdo->prepare($query);
$stmt->execute();
$customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Customer Circuits</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css" rel="stylesheet" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet" />
    <style>
        body {
            background-color: #f8f9fa;
            font-size: 0.75rem;
            padding: 5px;
        }
        .container-fluid {
            max-width: 100%;
            padding: 10px;
        }
        .table-container {
            background: white;
            padding: 15px;
            border-radius: 10px;
            box-shadow: 0 1px 6px rgba(0,0,0,0.1);
            overflow-x: auto;
        }
		
        h4 {
            font-size: 1rem;
            margin-bottom: 10px;
        }
        .btn-sm {
            padding: 2px 6px;
            font-size: 0.7rem;
        }
        table.dataTable td,
        table.dataTable th {
            padding: 0.3rem 0.5rem;
            vertical-align: middle;
            white-space: nowrap;
        }
        .modal-content {
            font-size: 0.75rem;
        }
        .dataTables_wrapper .dataTables_filter input {
            font-size: 0.75rem;
            height: 1.5rem;
        }
    </style>
</head>
<body>
<div class="container-fluid mt-2">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap">
        <h4 class="text-primary mb-2"><i class="fas fa-network-wired me-2"></i>Customer Circuits</h4>
        <div>
            <a href="dashboard.php" class="btn btn-outline-secondary btn-sm me-2 mb-1"><i class="fas fa-home"></i> Dashboard</a>
        </div>
    </div>

    <?php if ($successMsg): ?>
    <div class="alert alert-success alert-dismissible fade show py-1" role="alert" style="font-size:0.8rem;">
        <?= $successMsg ?>
        <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>

    <?php if ($errorMsg): ?>
    <div class="alert alert-danger alert-dismissible fade show py-1" role="alert" style="font-size:0.8rem;">
        <?= $errorMsg ?>
        <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>

    <div class="table-container">
        <table id="customerTable" class="table table-striped table-bordered table-hover table-sm" style="width:100%;">
            <thead class="table-light text-center">
                <tr>
                    <th>#</th>
                    <th>Circuit ID</th>
                    <th>Organization</th>
                    <th>Address</th>
                    <th>Product Type</th>
                    <th>Circuit Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($customers as $index => $row): ?>
                    <tr>
                        <td></td>
                        <td><?= htmlspecialchars($row['circuit_id']) ?></td>
                        <td><?= htmlspecialchars($row['organization_name']) ?></td>
                        <td class="address-column"><?= nl2br(htmlspecialchars($row['customer_address'])) ?></td>
                        <td class="text-center">
                            <span class="badge bg-info text-dark">
                                <?= htmlspecialchars($row['product_type'] ?? 'N/A') ?>
                            </span>
                        </td>
                        <td class="text-center">
							<?php
							$status = strtolower($row['circuit_status']);
							$statusClass = 'bg-secondary'; // Default

							if ($status == 'active') {
								$statusClass = 'bg-success'; // Green
							} elseif ($status == 'terminated') {
								$statusClass = 'bg-danger'; // Red
							} elseif ($status == 'inactive') {
								$statusClass = 'bg-warning text-dark'; // Yellow
							} elseif ($status == 'pending') {
								$statusClass = 'bg-info text-dark'; // Light Blue
							}
							?>
							<span class="badge <?= $statusClass ?>">
								<?= htmlspecialchars($row['circuit_status'] ?? 'N/A') ?>
							</span>
						</td>
                        <td class="text-center">
                            <a href="customer_details.php?circuit_id=<?= urlencode($row['circuit_id']) ?>" 
                               class="btn btn-info btn-sm me-1" title="View">
                               <i class="fas fa-eye"></i> View
                            </a>
                            <a href="edit_customer.php?circuit_id=<?= urlencode($row['circuit_id']) ?>" 
                               class="btn btn-warning btn-sm me-1" title="Edit">
                               <i class="fas fa-edit"></i> Edit
                            </a>
                            <button class="btn btn-danger btn-sm delete-btn" data-circuit="<?= htmlspecialchars($row['circuit_id']) ?>" title="Delete">
                               <i class="fas fa-trash-alt"></i> Delete
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="confirmDeleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-sm modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-danger text-white py-2">
        <h5 class="modal-title" id="deleteModalLabel"><i class="fas fa-exclamation-triangle me-1"></i>Confirm Delete</h5>
        <button type="button" class="btn-close btn-close-white btn-sm" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body text-center">
        Are you sure you want to delete this customer?
      </div>
      <div class="modal-footer justify-content-center py-2">
        <a href="#" id="confirmDeleteBtn" class="btn btn-danger btn-sm">Yes, Delete</a>
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
      </div>
    </div>
  </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>

<script>
$(document).ready(function () {
    var t = $('#customerTable').DataTable({
        responsive: true,
        pageLength: 15,
        lengthChange: false,
        language: {
            search: "_INPUT_",
            searchPlaceholder: "Search...",
            emptyTable: "No customer records found."
        },
        columnDefs: [
            { searchable: false, orderable: false, targets: 0 },
            { orderable: false, targets: 6 }
        ],
        order: [[1, 'desc']]
    });

    // Add row numbers
    t.on('order.dt search.dt draw.dt', function () {
        let pageInfo = t.page.info();
        t.column(0, { search: 'applied', order: 'applied', page: 'current' })
         .nodes()
         .each(function (cell, i) {
            cell.innerHTML = pageInfo.start + i + 1;
        });
    }).draw();

    // Delete button logic
    $('.delete-btn').on('click', function () {
        let circuitId = $(this).data('circuit');
        $('#confirmDeleteBtn').attr('href', 'delete_customer.php?circuit_id=' + encodeURIComponent(circuitId));
        var modal = new bootstrap.Modal(document.getElementById('confirmDeleteModal'));
        modal.show();
    });
});
</script>
</body>
</html>
