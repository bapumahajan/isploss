<?php
require_once 'includes/db.php';
session_start();


// Get filter values for Year/Month table (from POST or default to current)
$monthFilter = $_POST['month'] ?? 'all';
$yearFilter = $_POST['year'] ?? date('Y');
$productTypeFilter = $_POST['product_type'] ?? '';

// If export requested, output CSV and exit
if (isset($_POST['export']) && $_POST['export'] == "added_circuits") {
    $exportQuery = "
        SELECT 
            nd.circuit_id,
            cbi.organization_name,
            cbi.customer_address,
            cbi.City,
            cbi.contact_person_name,
            nd.*,
            cnd.*
        FROM network_details nd
        LEFT JOIN circuit_network_details cnd ON nd.circuit_id = cnd.circuit_id
        LEFT JOIN customer_basic_information cbi ON nd.circuit_id = cbi.circuit_id
        WHERE cnd.installation_date IS NOT NULL
          AND (:product_type = '' OR nd.product_type = :product_type)
          AND (:year = '' OR YEAR(cnd.installation_date) = :year)
          AND (:month = 'all' OR MONTH(cnd.installation_date) = :month)
        ORDER BY YEAR(cnd.installation_date) DESC, MONTH(cnd.installation_date) DESC, nd.product_type
    ";
    $exportStmt = $pdo->prepare($exportQuery);
    $exportStmt->execute([
        ':product_type' => $productTypeFilter,
        ':year' => $yearFilter,
        ':month' => $monthFilter
    ]);

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment;filename=added_circuits_export.csv');
    $out = fopen('php://output', 'w');
    $firstRow = true;
    $rowCount = 0;
    $numCols = 0;

    while ($row = $exportStmt->fetch(PDO::FETCH_ASSOC)) {
        // Remove duplicate circuit_id from nd.*
        if ($firstRow) {
            // Place desired columns first, then the rest without duplicates
            $header = [];
            $header[] = 'circuit_id';
            $header[] = 'organization_name';
            $header[] = 'customer_address';
            $header[] = 'City';
            $header[] = 'contact_person_name';
            foreach ($row as $key => $value) {
                if (!in_array($key, $header)) {
                    $header[] = $key;
                }
            }
            fputcsv($out, $header);
            $numCols = count($header);
            $firstRow = false;
        }

        // Organize data to match header order
        $csvRow = [];
        foreach ($header as $col) {
            $csvRow[] = isset($row[$col]) ? $row[$col] : '';
        }
        fputcsv($out, $csvRow);
        $rowCount++;
    }
    // Add total row if there is data
    if ($rowCount > 0) {
        $footer = array_fill(0, $numCols-1, '');
        $footer[] = "Total Rows: $rowCount";
        fputcsv($out, $footer);
    } else {
        fputcsv($out, ['No data available']);
    }
    fclose($out);
    exit;
}

// --- UI and summary queries below are UNCHANGED from your old code ---

// Query for Product Types (for filter dropdown, optional)
$productTypesStmt = $pdo->query("SELECT DISTINCT product_type FROM network_details WHERE product_type IS NOT NULL ORDER BY product_type");
$productTypes = $productTypesStmt->fetchAll(PDO::FETCH_COLUMN);

// Main dashboard query (no filters)
$statusCountsQuery = "
    SELECT
        nd.product_type,
        SUM(nd.circuit_status = 'Active') AS active_count,
        SUM(nd.circuit_status = 'Terminated') AS terminated_count,
        SUM(
            cnd.installation_date BETWEEN DATE_FORMAT(CURRENT_DATE(), '%Y-%m-01') AND LAST_DAY(CURRENT_DATE())
        ) AS newly_added_this_month
    FROM network_details nd
    LEFT JOIN circuit_network_details cnd ON nd.circuit_id = cnd.circuit_id
    GROUP BY nd.product_type
    ORDER BY nd.product_type
";
$statusStmt = $pdo->prepare($statusCountsQuery);
$statusStmt->execute();
$statusRows = $statusStmt->fetchAll(PDO::FETCH_ASSOC);

// Filtered Year/Month-wise Added Circuits Table
$addedByMonthQuery = "
    SELECT
        YEAR(cnd.installation_date) AS year,
        MONTH(cnd.installation_date) AS month,
        nd.product_type,
        COUNT(nd.circuit_id) AS added_count
    FROM network_details nd
    LEFT JOIN circuit_network_details cnd ON nd.circuit_id = cnd.circuit_id
    WHERE cnd.installation_date IS NOT NULL
      AND (:product_type = '' OR nd.product_type = :product_type)
      AND (:year = '' OR YEAR(cnd.installation_date) = :year)
      AND (:month = 'all' OR MONTH(cnd.installation_date) = :month)
    GROUP BY year, month, nd.product_type
    ORDER BY year DESC, month DESC, nd.product_type
";
$addedStmt = $pdo->prepare($addedByMonthQuery);
$addedStmt->execute([
    ':product_type' => $productTypeFilter,
    ':year' => $yearFilter,
    ':month' => $monthFilter
]);
$addedRows = $addedStmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate totals for the filtered table
$totalAdded = 0;
foreach ($addedRows as $row) {
    $totalAdded += $row['added_count'];
}
?>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<style>
.dashboard-section {margin-top: 30px; margin-bottom: 30px;}
.dashboard-table th, .dashboard-table td {text-align: center;vertical-align: middle;}
.dashboard-table thead th {background-color: #f2f6fc;color: #305080;font-weight: 600;font-size: 15px;border-bottom: 2px solid #c3d0ea;}
.dashboard-table tbody tr:nth-child(even) {background-color: #f8fafc;}
.dashboard-table tbody td {font-size: 14px;}
.card-title {color: #305080;font-size: 20px;font-weight: 700;letter-spacing: 0.5px;}
.card {box-shadow: 0 2px 10px 0 rgba(48,80,128,0.07);border-radius: 12px;border: 1px solid #c3d0ea;}
@media (max-width:767px) {
    .dashboard-table th, .dashboard-table td {font-size: 12px;padding: 7px;}
    .card-title {font-size: 16px;}
}
.dashboard-table tfoot td {background:#f7f7f7;font-weight:600;color:#305080;}
</style>

<div class="container dashboard-section">
    <div class="row">
        <div class="col-lg-8 mx-auto">

            <!-- Dashboard Button -->
            <div class="mb-3">
                <a href="dashboard.php" class="btn btn-secondary btn-sm">
                    <i class="bi bi-grid"></i> Dashboard
                </a>
            </div>

            <!-- Customer Status Dashboard (no filters) -->
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title mb-3">
                        <i class="bi bi-bar-chart-line-fill me-2"></i>Customer Status Overview (by Product Type)
                    </h5>
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped dashboard-table">
                            <thead>
                                <tr>
                                    <th>Product Type</th>
                                    <th><i class="bi bi-check-circle-fill text-success"></i> Active</th>
                                    <th><i class="bi bi-x-circle-fill text-danger"></i> Terminated</th>
                                    <th><i class="bi bi-plus-square-fill text-primary"></i> Newly Added (<?= date('F Y') ?>)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($statusRows as $row): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($row['product_type']) ?></td>
                                        <td><?= $row['active_count'] ?? 0 ?></td>
                                        <td><?= $row['terminated_count'] ?? 0 ?></td>
                                        <td><?= $row['newly_added_this_month'] ?? 0 ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (count($statusRows) === 0): ?>
                                    <tr>
                                        <td colspan="4" class="text-center text-muted">No data available.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Filter Controls for Second Table Only -->
            <form id="added-circuits-filter-form" method="post" class="mb-4">
                <div class="row g-2 align-items-end">
                    <div class="col-4">
                        <label for="productType" class="form-label">Product Type</label>
                        <select class="form-select" name="product_type" id="productType">
                            <option value="">All</option>
                            <?php foreach ($productTypes as $pt): ?>
                                <option value="<?= htmlspecialchars($pt) ?>" <?= ($pt == $productTypeFilter) ? 'selected' : '' ?>><?= htmlspecialchars($pt) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-4">
                        <label for="year" class="form-label">Year</label>
                        <select class="form-select" name="year" id="year">
                            <?php for ($y = date('Y')-5; $y <= date('Y')+1; $y++): ?>
                                <option value="<?= $y ?>" <?= ($y == $yearFilter) ? 'selected' : '' ?>><?= $y ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="col-4">
                        <label for="month" class="form-label">Month</label>
                        <select class="form-select" name="month" id="month">
                            <option value="all" <?= ($monthFilter == 'all') ? 'selected' : '' ?>>All</option>
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                                <option value="<?= str_pad($m,2,'0',STR_PAD_LEFT) ?>" <?= ($m == $monthFilter) ? 'selected' : '' ?>><?= date('F', mktime(0,0,0,$m,10)) ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="col-12 mt-3 text-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-funnel-fill"></i> Apply Filter
                        </button>
                        <button type="button" class="btn btn-success ms-2" id="exportBtn">
                            <i class="bi bi-file-earmark-arrow-down"></i> Export CSV
                        </button>
                        <span id="added-circuits-loading" style="display:none;" class="ms-2"><i class="bi bi-arrow-repeat"></i> Loading...</span>
                    </div>
                </div>
            </form>

            <!-- Year/Month Wise Added Circuits Table (no action column) -->
            <div id="added-circuits-table">
                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title mb-3"><i class="bi bi-calendar-plus me-2"></i>Year/Month-wise Added Circuits</h5>
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped dashboard-table" id="addedCircuitsTable">
                                <thead>
                                    <tr>
                                        <th>Year</th>
                                        <th>Month</th>
                                        <th>Product Type</th>
                                        <th>Added Circuits</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($addedRows as $row): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($row['year']) ?></td>
                                            <td><?= str_pad($row['month'], 2, '0', STR_PAD_LEFT) ?> - <?= date('F', mktime(0,0,0,$row['month'],10)) ?></td>
                                            <td><?= htmlspecialchars($row['product_type']) ?></td>
                                            <td><?= htmlspecialchars($row['added_count']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if (count($addedRows) === 0): ?>
                                        <tr>
                                            <td colspan="4" class="text-center text-muted">No data available.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td colspan="3" class="text-end">Total</td>
                                        <td><?= $totalAdded ?></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            </div><!-- End added-circuits-table -->
        </div>
    </div>
</div>

<script>
$('#added-circuits-filter-form').on('submit', function(e){
    e.preventDefault();
    $('#added-circuits-loading').show();
    $.ajax({
        url: '', // same PHP file
        type: 'POST',
        data: $(this).serialize(),
        success: function(res){
            var html = $(res).find('#added-circuits-table').html();
            $('#added-circuits-table').html(html);
        },
        error: function(){ alert('Error loading filtered data.'); },
        complete: function(){ $('#added-circuits-loading').hide(); }
    });
});

$('#exportBtn').on('click', function(){
    // Create a form and submit for export
    var $form = $('#added-circuits-filter-form');
    var formData = $form.serializeArray();
    formData.push({name: 'export', value: 'added_circuits'});
    var $exportForm = $('<form>', {
        method: 'POST',
        action: ''
    });
    $.each(formData, function(i, field) {
        $exportForm.append($('<input>', {
            type: 'hidden',
            name: field.name,
            value: field.value
        }));
    });
    $('body').append($exportForm);
    $exportForm.submit();
    $exportForm.remove();
});
</script>