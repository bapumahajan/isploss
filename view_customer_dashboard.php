<?php
//view_customer_dashboard.php
require_once 'config/database.php';
session_name('oss_portal');
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

// Helper: Find column by possible names (case-insensitive)
function findColumn($colnames, $options) {
    foreach ($options as $option) {
        foreach ($colnames as $col) {
            if (strcasecmp($col, $option) == 0) return $col;
        }
    }
    return null;
}

// Helper: Find the operator circuit id column in a flexible way
function findOperatorCktCol($colnames, $operator) {
    // Try to match columns like OPERATOR_CKT_ID, OPERATOR_Ckt_Id, etc.
    foreach ($colnames as $col) {
        if (stripos($col, $operator) !== false && stripos($col, 'CKT') !== false) {
            return $col;
        }
    }
    // Fallback: any column with CKT in its name
    foreach ($colnames as $col) {
        if (stripos($col, 'CKT') !== false) {
            return $col;
        }
    }
    return null;
}

$operator_tables = $pdo->query(
    "SELECT operator_tables.table_name, operators.name AS operator_name
     FROM operator_tables
     JOIN operators ON operator_tables.operator_id = operators.id"
)->fetchAll(PDO::FETCH_ASSOC);

$dashboard_fields = [
    'SrNo',
    'NNI_Partner',
    'Customer_Name',
    'ISPL_circuit_id',
    'Operator_circuit_id',
    'Address',
    'Customer_Contact',
    'Bandwidth',
    'Lat_Long'
];

$selects = [];
foreach ($operator_tables as $ot) {
    $table = $ot['table_name'];
    $operator = $ot['operator_name'];
    $cols = $pdo->query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
    $colnames = array_column($cols, 'Field');

    $circuit_id_col   = findColumn($colnames, ['circuit_id', 'CIRCUIT_ID']);
    $bandwidth_col    = findColumn($colnames, ['Bandwidth', 'bandwidth', 'BW', 'BANDWIDTH_MBPS']);
    $latitude_col     = findColumn($colnames, ['latitude', 'lat', 'LAT']);
    $longitude_col    = findColumn($colnames, ['longitude', 'long', 'LON', 'lng']);

    // Use flexible function to find operator circuit id column
    $operator_colname = findOperatorCktCol($colnames, $operator);

    // Only require circuit_id and operator_circuit_id
    if ($circuit_id_col && $operator_colname) {
        $bandwidth_expr = $bandwidth_col ? "ot.`$bandwidth_col`" : "NULL";
        $lat_expr = $latitude_col ? "ot.`$latitude_col`" : "NULL";
        $long_expr = $longitude_col ? "ot.`$longitude_col`" : "NULL";

        $selects[] = "
            SELECT
                '$operator' AS NNI_Partner,
                cb.organization_name AS Customer_Name,
                cb.circuit_id AS ISPL_circuit_id,
                ot.`$operator_colname` AS Operator_circuit_id,
                cb.customer_address AS Address,
                cb.contact_person_name AS Customer_Contact,
                $bandwidth_expr AS Bandwidth,
                CONCAT($lat_expr, ',', $long_expr) AS Lat_Long,
                '$table' AS Operator_Table
            FROM `$table` ot
            LEFT JOIN customer_basic_information cb ON ot.`$circuit_id_col` = cb.circuit_id
        ";
    }
}

$rows = [];
if (!empty($selects)) {
    $union_query = implode(" UNION ALL ", $selects) . " ORDER BY NNI_Partner, Customer_Name";
    $stmt = $pdo->query($union_query);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>All Customers Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <style>
        body {
            min-height: 100vh;
            background: linear-gradient(135deg,#f9fafb 0%, #eaf0fb 100%);
            margin: 0;
        }
        .main-title {
            background: linear-gradient(90deg,#003049 0%, #f77f00 100%);
            color: #fff;
            padding: 18px 28px;
            border-radius: 10px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06);
            margin-bottom: 28px;
        }
        .search-box {
            float: right;
            margin-bottom: 10px;
        }
        .search-input {
            width: 260px;
            border-radius: 20px;
            padding-left: 16px;
        }
        .card {
            border: none;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
            background: #fff;
            border-radius: 14px;
        }
        .table-responsive {
            overflow-x: auto;
            min-height: 350px;
        }
        .table {
            margin-bottom: 0;
            font-size: 15px;
        }
        .table th {
            background: #003049;
            color: #fff;
            font-weight: 500;
        }
        .table td {
            background: #f9fafb;
        }
        th.customer_address, td.customer_address {
            max-width: 220px;
            overflow: hidden;
            white-space: nowrap;
            text-overflow: ellipsis;
        }
        td, th {
            vertical-align: middle !important;
        }
        tr.clickable-row {
            cursor: pointer;
        }
        tr.clickable-row:hover {
            background-color: #ffe5b4 !important;
            transition: background 0.2s;
        }
        @media (max-width: 900px) {
            .main-title { font-size: 1.1rem; padding: 12px 10px; }
            .card { border-radius: 6px; }
            th.customer_address, td.customer_address { max-width: 120px; font-size: 12px; }
            .search-input { width: 160px; }
        }
        @media (max-width: 600px) {
            .main-title { font-size: 1rem; padding: 8px 5px; }
            .table th, .table td { font-size: 12px; }
            th.customer_address, td.customer_address { max-width: 90px; }
            .search-input { width: 100px; }
        }
    </style>
</head>
<body>
<div class="container-fluid" style="max-width: 98vw;">
    <div class="main-title mt-4">
        <span class="h4">All Customer Details Dashboard</span>
    </div>
    <div class="card p-3 mb-4">
        <div class="search-box">
            <input type="text" id="searchInput" class="form-control search-input" placeholder="Search by Customer, ISPL ID, Operator CKT ID..." />
        </div>
        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle" id="customerTable">
                <thead>
                    <tr>
                        <?php foreach ($dashboard_fields as $field): ?>
                            <?php
                                $th_class = $field === 'Address' ? 'customer_address' : '';
                            ?>
                            <th class="<?= $th_class ?>">
                                <?= htmlspecialchars($field) ?>
                            </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php $serial = 1; ?>
                    <?php foreach ($rows as $row): 
                        $details_url = "view_nni_single_customer.php?table=" . urlencode($row['Operator_Table']) . "&circuit_id=" . urlencode($row['Operator_circuit_id']);
                    ?>
                        <tr class="clickable-row">
                            <td>
                                <a href="<?= $details_url ?>" target="_blank" style="text-decoration:none; color:inherit;">
                                    <?= $serial++ ?>
                                </a>
                            </td>
                            <td>
                                <a href="<?= $details_url ?>" target="_blank" style="text-decoration:none; color:inherit;">
                                    <?= htmlspecialchars($row['NNI_Partner']) ?>
                                </a>
                            </td>
                            <td>
                                <a href="<?= $details_url ?>" target="_blank" style="text-decoration:none; color:inherit;">
                                    <?= htmlspecialchars($row['Customer_Name']) ?>
                                </a>
                            </td>
                            <td>
                                <a href="<?= $details_url ?>" target="_blank" style="text-decoration:none; color:inherit;">
                                    <?= htmlspecialchars($row['ISPL_circuit_id']) ?>
                                </a>
                            </td>
                            <td>
                                <a href="<?= $details_url ?>" target="_blank" style="text-decoration:none; color:inherit;">
                                    <?= htmlspecialchars($row['Operator_circuit_id']) ?>
                                </a>
                            </td>
                            <td class="customer_address" title="<?= htmlspecialchars($row['Address']) ?>">
                                <a href="<?= $details_url ?>" target="_blank" style="text-decoration:none; color:inherit;">
                                    <?= htmlspecialchars(mb_strimwidth($row['Address'] ?? '', 0, 38, '...')) ?>
                                </a>
                            </td>
                            <td>
                                <a href="<?= $details_url ?>" target="_blank" style="text-decoration:none; color:inherit;">
                                    <?= htmlspecialchars($row['Customer_Contact']) ?>
                                </a>
                            </td>
                            <td>
                                <a href="<?= $details_url ?>" target="_blank" style="text-decoration:none; color:inherit;">
                                    <?= htmlspecialchars($row['Bandwidth']) ?>
                                </a>
                            </td>
                            <td>
                                <a href="<?= $details_url ?>" target="_blank" style="text-decoration:none; color:inherit;">
                                    <?= htmlspecialchars($row['Lat_Long']) ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($rows)): ?>
                        <tr>
                            <td colspan="<?= count($dashboard_fields) ?>" class="text-danger text-center">No data found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <a href="operator_portal.php" class="btn btn-secondary mt-3"><i class="bi bi-arrow-left"></i> Back to NNI portal</a>
</div>
<script>
$(document).ready(function(){
    $("#searchInput").on("keyup", function() {
        var value = $(this).val().toLowerCase();
        $("#customerTable tbody tr").filter(function() {
            let tds = $(this).find("td");
            let customerName = tds.eq(2).text().toLowerCase();
            let isplId = tds.eq(3).text().toLowerCase();
            let operatorCktId = tds.eq(4).text().toLowerCase();
            $(this).toggle(
                customerName.indexOf(value) > -1 ||
                isplId.indexOf(value) > -1 ||
                operatorCktId.indexOf(value) > -1
            );
        });
    });
});
</script>
</body>
</html>