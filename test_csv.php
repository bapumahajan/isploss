<?php
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="test_export.csv"');
$output = fopen('php://output', 'w');
fputcsv($output, ['Test', 'Works', 'Now']);
fclose($output);
