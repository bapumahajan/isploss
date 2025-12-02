<?php
require_once 'includes/db.php';

$format = $_GET['format'] ?? 'csv';

header_remove("Content-Type");

if ($format === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="complaints.csv"');
    $output = fopen("php://output", "w");
    fputcsv($output, ['ID', 'Circuit ID', 'Docket No', 'Status']);

    $result = $conn->query("SELECT id, circuit_id, docket_no, current_status FROM complaints");
    while ($row = $result->fetch_assoc()) {
        fputcsv($output, $row);
    }
    fclose($output);
} else {
    require 'vendor/autoload.php';
    use PhpOffice\PhpSpreadsheet\Spreadsheet;
    use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->fromArray(['ID', 'Circuit ID', 'Docket No', 'Status'], NULL, 'A1');

    $result = $conn->query("SELECT id, circuit_id, docket_no, current_status FROM complaints");
    $i = 2;
    while ($row = $result->fetch_assoc()) {
        $sheet->fromArray(array_values($row), NULL, "A$i");
        $i++;
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="complaints.xlsx"');
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
}
exit;
?>
