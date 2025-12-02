<?php
// operator_portal_ui.php — Modern UI template for operator portal
// Use as a base for add/view pages, adapt form fields/table as needed

session_name('oss_portal');
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}
$username = $_SESSION['username'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Operator Portal</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet" />
    <style>
        body { background-color: #f9fafb; font-family: 'Segoe UI', 'Roboto', Arial, sans-serif; }
        .navbar-custom { background-color: #003049; }
        .navbar-custom .navbar-brand, .navbar-custom .nav-link { color: #f1faee !important; }
        .navbar-custom .nav-link.active { background-color: #f77f00; border-radius: 5px; color: #fff !important; }
        .card { border: none; box-shadow: 0 4px 12px rgba(0,0,0,0.08); margin-top: 32px; }
        .card-title { color: #003049; }
        .form-label .text-danger { font-weight: bold; }
        .form-text { font-size: 0.97em; }
        .table thead th { position: sticky; top: 0; background: #fff; z-index: 1; }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark navbar-custom fixed-top">
    <div class="container-fluid px-4">
        <a class="navbar-brand" href="#"><i class="bi bi-diagram-3 me-2"></i>Operator Portal</a>
        <div class="ms-auto d-flex align-items-center">
            <span class="me-3 text-light"><i class="bi bi-person-circle"></i> <?= htmlspecialchars($username) ?></span>
            <a href="logout.php" class="btn btn-sm btn-outline-light">Logout</a>
        </div>
    </div>
</nav>
<main class="container">
    <div class="row justify-content-center">
        <div class="col-lg-7 col-md-10">
            <div class="card p-4">
                <h4 class="card-title mb-4"><i class="bi bi-plug"></i> Add Third Party Details</h4>
                <!-- Success/Error Message Example -->
                <div class="mb-3">
                    <div class="alert alert-success d-none" id="successMsg"><i class="bi bi-check-circle"></i> Saved successfully!</div>
                    <div class="alert alert-danger d-none" id="errorMsg"><i class="bi bi-exclamation-triangle"></i> Error: ...</div>
                </div>
                <!-- Data Entry Form -->
                <form id="thirdPartyForm" autocomplete="off">
                    <div class="mb-3">
                        <label class="form-label">Circuit ID <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="circuit_id" required placeholder="Enter or search Circuit ID">
                        <div class="form-text">Alphanumeric, max 255 chars.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Operator <span class="text-danger">*</span></label>
                        <select class="form-select" name="operator_id" required>
                            <option value="">Select operator</option>
                            <!-- Dynamically fill options from DB -->
                            <option value="1">Operator 1</option>
                            <option value="2">Operator 2</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">PO Date</label>
                        <input type="date" class="form-control" name="PO_Date">
                        <div class="form-text">Format: YYYY-MM-DD. If blank, today's date will be used.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">ISPL Circuit ID <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="ISPL_Ckt_Id" required maxlength="255">
                        <div class="form-text">Alphanumeric, max 255 chars.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">VI Circuit ID <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="VI_Ckt_Id" required maxlength="255">
                        <div class="form-text">Alphanumeric, max 255 chars.</div>
                    </div>
                    <button type="submit" class="btn btn-success mt-2"><i class="bi bi-save"></i> Save Details</button>
                    <a href="view_third_party_details.php" class="btn btn-info mt-2 ms-2"><i class="bi bi-table"></i> View Details</a>
                </form>
            </div>
        </div>
    </div>
    <!-- Table: Example Data View -->
    <div class="row justify-content-center">
        <div class="col-lg-10 col-md-12">
            <div class="card p-4 mt-4">
                <h4 class="card-title mb-3"><i class="bi bi-table"></i> Recent Third Party Details</h4>
                <input type="search" class="form-control mb-3" placeholder="Search by Circuit ID, Operator..." id="tableSearch">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped align-middle" id="detailsTable">
                        <thead>
                            <tr>
                                <th>Circuit ID</th>
                                <th>Operator</th>
                                <th>PO Date</th>
                                <th>ISPL Circuit ID</th>
                                <th>VI Circuit ID</th>
                                <th>Created At</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Dynamically fill rows from DB -->
                            <tr>
                                <td>BB-00001</td>
                                <td>Operator 2</td>
                                <td>2025-08-31</td>
                                <td>VI-2003</td>
                                <td>ENT32PUNPUN107348</td>
                                <td>2025-08-31 09:00:13</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <nav>
                    <ul class="pagination justify-content-end mt-3">
                        <li class="page-item disabled"><a class="page-link">Prev</a></li>
                        <li class="page-item active"><a class="page-link">1</a></li>
                        <li class="page-item"><a class="page-link">2</a></li>
                        <li class="page-item"><a class="page-link">Next</a></li>
                    </ul>
                </nav>
            </div>
        </div>
    </div>
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Example: Instant search for data table
document.getElementById('tableSearch').addEventListener('input', function(){
    let filter = this.value.toLowerCase();
    document.querySelectorAll('#detailsTable tbody tr').forEach(function(row){
        row.style.display = row.textContent.toLowerCase().indexOf(filter) > -1 ? '' : 'none';
    });
});
</script>
</body>
</html>