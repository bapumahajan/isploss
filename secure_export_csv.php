<?php
// export_csv.php — full cleaned-up export with BOM, IP join, and proper column headers
ob_start();
require_once 'includes/db.php';

// Set CSV headers
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="full_customer_export.csv"');
header('Pragma: no-cache');
header('Expires: 0');

// Open output stream
$output = fopen('php://output', 'w');
if (!$output) {
    die("Unable to open output stream.");
}

// ✅ Write UTF-8 BOM to ensure Excel compatibility
fwrite($output, "\xEF\xBB\xBF");

// Tables to include in the report
$tables = ['customer_basic_information', 'network_details', 'circuit_network_details'];
$selects = [];
$joins = [];
$columnMap = [];
$columnSeen = [];

// Columns to exclude (IDs or internal)
$excludedColumns = ['id', 'customer_id'];

foreach ($tables as $table) {
    $stmt = $pdo->query("DESCRIBE $table");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

    foreach ($columns as $col) {
        // Skip repeated circuit_id from joined tables
        if ($col === 'circuit_id' && $table !== 'customer_basic_information') {
            continue;
        }

        // Skip excluded columns
        if (in_array($col, $excludedColumns)) {
            continue;
        }

        // Handle repeated column names
        $alias = $col;
        if (isset($columnSeen[$col])) {
            $columnSeen[$col]++;
            $alias = $col . $columnSeen[$col];
        } else {
            $columnSeen[$col] = 1;
        }

        $selects[] = "$table.$col AS `$alias`";

        // Create readable header label
        $label = ucwords(str_replace('_', ' ', $col));
        if ($columnSeen[$col] > 1) {
            $label .= ' ' . $columnSeen[$col];
        }

        $columnMap[$alias] = $label;
    }

    if ($table !== 'customer_basic_information') {
        $joins[] = "LEFT JOIN $table ON customer_basic_information.circuit_id = $table.circuit_id";
    }
}

// Add IP Address aggregation (comma separated)
$selects[] = "(SELECT GROUP_CONCAT(ip_address SEPARATOR ', ') FROM circuit_ips WHERE circuit_id = customer_basic_information.circuit_id) AS ip_address";
$columnMap['ip_address'] = 'IP Address';

// Final SQL Query
$sql = "SELECT " . implode(", ", $selects) . " FROM customer_basic_information " . implode("\n", $joins);

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($rows && count($rows) > 0) {
        // Create final CSV headers
        $headers = array_merge(['Sr. No', 'Circuit Id'], array_filter(array_values($columnMap), fn($h) => $h !== 'Circuit Id'));
        fputcsv($output, $headers, ',', '"', '\\');

        // Write data rows
        $srNo = 1;
        foreach ($rows as $row) {
            $circuitId = isset($row['circuit_id']) ? $row['circuit_id'] : 'NA';
            unset($row['circuit_id']); // Remove to avoid duplicate

            $cleaned = array_map(function ($val) {
                return (is_null($val) || $val === '') ? 'NA' : $val;
            }, $row);

            $exportRow = array_merge([$srNo++, $circuitId], array_values($cleaned));
            fputcsv($output, $exportRow, ',', '"', '\\');
        }
    } else {
        fputcsv($output, ['No records found'], ',', '"', '\\');
    }

} catch (PDOException $e) {
    fputcsv($output, ['Error: ' . $e->getMessage()], ',', '"', '\\');
}

fclose($output);
ob_end_flush();
exit;
