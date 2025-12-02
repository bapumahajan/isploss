<?php

require_once 'config/database.php';
session_name('oss_portal');
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

$table = $_GET['table'] ?? '';
$circuit_id = $_GET['circuit_id'] ?? '';
$ispl_circuit_id = $_GET['ispl_circuit_id'] ?? '';

if (!$table || (!$circuit_id && !$ispl_circuit_id)) {
    echo "<div class='alert alert-danger'>Invalid request: missing table or circuit ID.</div>";
    exit();
}

// Get columns from table
$cols = $pdo->query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
$colnames = array_column($cols, 'Field');

// Helper to find column (case-insensitive)
function findColumn($colnames, $options) {
    foreach ($options as $option) {
        foreach ($colnames as $col) {
            if (strcasecmp($col, $option) == 0) return $col;
        }
    }
    return null;
}

$ispl_circuit_id_col = findColumn($colnames, ['ISPL_circuit_id', 'ispl_circuit_id', 'CIRCUIT_ID', 'circuit_id']);
$operator_circuit_id_col = null;

if (isset($_GET['operator'])) {
    $operator = $_GET['operator'];
    $operator_circuit_id_col = findColumn($colnames, [
        $operator . '_CKT_ID',
        $operator . '_CKTID',
        $operator . '_CKT_D'
    ]);
} else {
    $operator_circuit_id_col = findColumn($colnames, [
        'Operator_circuit_id', 'operator_circuit_id', 'OPERATOR_CKT_ID', 'operator_ckt_id'
    ]);
    if (!$operator_circuit_id_col) {
        foreach ($colnames as $col) {
            if (preg_match('/_CKT_ID$/i', $col) || preg_match('/_CKT_D$/i', $col)) {
                $operator_circuit_id_col = $col;
                break;
            }
        }
    }
}

$row = false;
$method_used = '';
if ($circuit_id && $operator_circuit_id_col) {
    $query = "SELECT * FROM `$table` WHERE `$operator_circuit_id_col` = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$circuit_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $method_used = 'Operator circuit ID';
}
if (!$row && $ispl_circuit_id && $ispl_circuit_id_col) {
    $query = "SELECT * FROM `$table` WHERE `$ispl_circuit_id_col` = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$ispl_circuit_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $method_used = 'ISPL circuit ID';
}

if (!$row) {
    echo "<div class='alert alert-danger'>No details found for this circuit ID in selected table.</div>";
    exit();
}

// Beautify field names for display
function beautify($field) {
    return ucwords(str_replace("_", " ", $field));
}

// Filter out id fields except circuit ids
function filterFields($fields) {
    $show = [];
    foreach ($fields as $field) {
        if (
            preg_match('/^id$/i', $field) ||
            (preg_match('/_id$/i', $field) && !preg_match('/circuit_id$/i', $field) && !preg_match('/CKT_ID$/i', $field) && !preg_match('/ISPL_circuit_id$/i', $field))
        ) {
            continue;
        }
        $show[] = $field;
    }
    return $show;
}
$fields_to_show = filterFields(array_keys($row));

$cols_per_row = 2;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Customer Details</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
      body {
            font-family: "Segoe UI", Arial, sans-serif;
            background-color: #f8f9fa;
            margin: 0;
            padding: 20px;
            font-size: 14px;
        }
        .header {
            background-color: #00334d;
            color: #fff;
            padding: 12px 18px;
            border-radius: 6px 6px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .header .title {
            font-size: 16px;
            font-weight: bold;
        }
        .header .meta {
            font-size: 13px;
            margin-left: 15px;
            color: #ffcc00;
        }
        .details-card {
            background: #fff;
            padding: 15px;
            border-radius: 0 0 6px 6px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
            table-layout: fixed;
            word-wrap: break-word;
        }
        th, td {
            text-align: left;
            padding: 8px 10px;
            border: 1px solid #dee2e6;
            vertical-align: top;
        }
        th {
            background-color: #f1f1f1;
            font-weight: 600;
            width: 20%;
            white-space: nowrap;
        }
        td {
            width: 30%;
        }
        tr:nth-child(even) {
            background-color: #fafafa;
        }
        .btn-close-custom {
            background: #fff;
            color: #00334d;
            border: 1px solid #ccc;
            padding: 4px 10px;
            font-size: 13px;
            border-radius: 4px;
            text-decoration: none;
        }
        .btn-close-custom:hover {
            background: #00334d;
            color: #fff;
        }

    </style>
</head>
<body>
    <div class="header">
        <div>
            <span class="title">Customer Details</span>
            <span class="meta">: <?= htmlspecialchars($table) ?></span>
            <span class="meta">Search Method: <?= htmlspecialchars($method_used) ?></span>
        </div>
        <a href="javascript:window.close();" class="btn-close-custom">Close</a>
    </div>

    <div class="details-card">
        <table class="details-table">
            <?php
            $total_fields = count($fields_to_show);
            for ($i = 0; $i < $total_fields; $i += $cols_per_row) {
                echo "<tr>";
                for ($j = 0; $j < $cols_per_row; $j++) {
                    $field_idx = $i + $j;
                    if ($field_idx < $total_fields) {
                        $field = $fields_to_show[$field_idx];
                        echo "<th>" . beautify($field) . "</th>";
                        echo "<td>" . htmlspecialchars($row[$field]) . "</td>";
                    } else {
                        echo "<th></th><td></td>";
                    }
                }
                echo "</tr>";
            }
            ?>
        </table>
    </div>
</body>
</html>