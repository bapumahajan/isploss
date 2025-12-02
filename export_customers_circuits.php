<?php
// export_customers_circuits.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_name('oss_portal');
session_start();
if (session_status() === PHP_SESSION_NONE) session_start();

require_once 'includes/db.php';        // Your PDO connection in $pdo
require_once 'includes/auth.php';      // Your auth & role check functions
require_roles(['admin', 'manager']);   // Access control

require 'vendor/autoload.php';         // PhpSpreadsheet and Dompdf autoload

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Dompdf\Dompdf;

// Validate date format YYYY-MM-DD
function validate_date($date) {
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $date);
}

// Mapping of all allowed columns by table (must match your DB schema exactly)
$allowedFields = [
    'customer_basic_information' => [
        'circuit_id', 'organization_name', 'customer_address', 'City', 'contact_person_name',
    ],
    'network_details' => [
        'product_type','link_type','pop_name', 'pop_ip', 'switch_name', 'switch_ip', 'switch_port',
        'bandwidth', 'circuit_status', 'vlan',
    ],
    'circuit_network_details' => [
        'installation_date', 'wan_ip', 'wan_gateway', 'dns1', 'dns2', 'auth_type',
        'PPPoE_auth_username', 'PPPoE_auth_password', 'cacti_url', 'cacti_username', 'cacti_password',
    ],
    // These three are special aggregated fields from subqueries:
    'customer_contacts' => ['contact_numbers'],
    'customer_emails' => ['ce_email_ids'],
    'circuit_ips' => ['ip_addresses'],
];

// Human-friendly headers for columns (you can customize)
$headersMap = [
    'circuit_id' => 'Circuit ID',
    'organization_name' => 'Organization Name',
    'customer_address' => 'Customer Address',
    'City' => 'City',
    'contact_person_name' => 'Contact Person',
    'product_type' => 'Product Type',
	'link_type' => 'link_type',
    'pop_name' => 'POP Name',
    'pop_ip' => 'POP IP',
    'switch_name' => 'Switch Name',
    'switch_ip' => 'Switch IP',
    'switch_port' => 'Switch Port',
    'bandwidth' => 'Bandwidth',
    'circuit_status' => 'Circuit Status',
    'vlan' => 'VLAN',
    'installation_date' => 'Installation Date',
    'wan_ip' => 'WAN IP',
    'wan_gateway' => 'WAN Gateway',
    'dns1' => 'DNS 1',
    'dns2' => 'DNS 2',
    'auth_type' => 'Auth Type',
    'PPPoE_auth_username' => 'PPPoE Username',
    'PPPoE_auth_password' => 'PPPoE Password',
    'cacti_url' => 'Cacti URL',
    'cacti_username' => 'Cacti Username',
    'cacti_password' => 'Cacti Password',
    'contact_numbers' => 'Contact Numbers',
    'ce_email_ids' => 'Contact Emails',
    'ip_addresses' => 'IP Addresses',
];

// Get and sanitize inputs
$format = strtolower($_GET['format'] ?? 'csv');
$search = trim($_GET['search'] ?? '');
$start_date = $_GET['from_date'] ?? '';
$end_date = $_GET['to_date'] ?? '';

// Validate dates, if provided
if ($start_date !== '' && !validate_date($start_date)) {
    die('Invalid start date format.');
}
if ($end_date !== '' && !validate_date($end_date)) {
    die('Invalid end date format.');
}

// Get selected fields
$selectedFieldsRaw = $_GET['fields'] ?? [];
if (!is_array($selectedFieldsRaw) || count($selectedFieldsRaw) === 0) {
    die('No fields selected for export.');
}

// Validate and prepare list of fields for SQL select and headers
$selectFields = [];
$headerLabels = [];
foreach ($selectedFieldsRaw as $field) {
    // Expect field in format table.column
    if (strpos($field, '.') === false) continue;
    list($table, $column) = explode('.', $field, 2);

    // Validate table and column
    if (!isset($allowedFields[$table])) continue;
    if (!in_array($column, $allowedFields[$table], true)) continue;

    // Build SQL select field depending on the table/column:
    // For main tables just prefix with alias
    // For special aggregated fields, add subquery instead

    if ($table === 'customer_contacts' && $column === 'contact_numbers') {
        $selectFields[$field] = "(SELECT GROUP_CONCAT(DISTINCT contact_number ORDER BY id SEPARATOR '; ') FROM customer_contacts WHERE circuit_id = cbi.circuit_id) AS contact_numbers";
        $headerLabels[$field] = $headersMap[$column];
    } elseif ($table === 'customer_emails' && $column === 'ce_email_ids') {
        $selectFields[$field] = "(SELECT GROUP_CONCAT(DISTINCT ce_email_id ORDER BY id SEPARATOR '; ') FROM customer_emails WHERE circuit_id = cbi.circuit_id) AS ce_email_ids";
        $headerLabels[$field] = $headersMap[$column];
    } elseif ($table === 'circuit_ips' && $column === 'ip_addresses') {
        $selectFields[$field] = "(SELECT GROUP_CONCAT(DISTINCT ip_address ORDER BY ip_address SEPARATOR '; ') FROM circuit_ips WHERE circuit_id = cbi.circuit_id) AS ip_addresses";
        $headerLabels[$field] = $headersMap[$column];
    } else {
        // Normal fields from main tables:
        // Alias tables as:
        // customer_basic_information => cbi
        // network_details => nd
        // circuit_network_details => cnd

        $aliasMap = [
            'customer_basic_information' => 'cbi',
            'network_details' => 'nd',
            'circuit_network_details' => 'cnd',
        ];
        if (!isset($aliasMap[$table])) continue; // Skip unknown tables here

        $alias = $aliasMap[$table];
        $selectFields[$field] = "$alias.`$column` AS `$column`";
        $headerLabels[$field] = $headersMap[$column] ?? $column;
    }
}

if (empty($selectFields)) {
    die('No valid fields selected for export.');
}

// Build WHERE clause with params for PDO
$where = [];
$params = [];

if ($start_date !== '') {
    $where[] = 'cnd.installation_date >= :start_date';
    $params[':start_date'] = $start_date;
}
if ($end_date !== '') {
    $where[] = 'cnd.installation_date <= :end_date';
    $params[':end_date'] = $end_date;
}
if ($search !== '') {
    $where[] = '(cbi.organization_name LIKE :search OR cbi.contact_person_name LIKE :search OR EXISTS (
        SELECT 1 FROM circuit_ips ci WHERE ci.circuit_id = cbi.circuit_id AND ci.ip_address LIKE :search
    ))';
    $params[':search'] = '%' . $search . '%';
}

$where_clause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Build SQL Query dynamically
$sql = "
SELECT
    " . implode(",\n    ", $selectFields) . "
FROM customer_basic_information cbi
LEFT JOIN network_details nd ON cbi.circuit_id = nd.circuit_id
LEFT JOIN circuit_network_details cnd ON cbi.circuit_id = cnd.circuit_id
$where_clause
ORDER BY cbi.circuit_id
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Replace null/empty fields with 'NA' and mask passwords
foreach ($data as &$row) {
    foreach ($row as $key => $val) {
        $val_trim = trim((string)$val);
        $row[$key] = ($val_trim === '' || is_null($val)) ? 'NA' : $val_trim;
    }
    // Mask passwords if present in selected fields
    if (array_key_exists('PPPoE_auth_password', $row)) {
        $row['PPPoE_auth_password'] = '******';
    }
    if (array_key_exists('cacti_password', $row)) {
        $row['cacti_password'] = '******';
    }
}
unset($row);

$totalRows = count($data);

// Headers for export (ordered by selected fields)
$exportHeaders = [];
foreach ($selectedFieldsRaw as $field) {
    if (isset($headerLabels[$field])) {
        $exportHeaders[] = $headerLabels[$field];
    }
}

// Export logic
if ($format === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=export_customers_circuits.csv');

    $output = fopen('php://output', 'w');
    // Output headers
    fputcsv($output, $exportHeaders);

    foreach ($data as $row) {
        $line = [];
        foreach ($selectedFieldsRaw as $field) {
            // Column key is after the dot (table.column)
            $col = explode('.', $field, 2)[1];
            $line[] = $row[$col] ?? 'NA';
        }
        fputcsv($output, $line);
    }
    fclose($output);
    exit;
} elseif ($format === 'excel' || $format === 'xlsx') {
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    // Write header row
    $colIndex = 1;
    foreach ($exportHeaders as $header) {
        $sheet->setCellValueByColumnAndRow($colIndex++, 1, $header);
    }

    // Write data rows
    $rowIndex = 2;
    foreach ($data as $row) {
        $colIndex = 1;
        foreach ($selectedFieldsRaw as $field) {
            $col = explode('.', $field, 2)[1];
            $sheet->setCellValueByColumnAndRow($colIndex++, $rowIndex, $row[$col] ?? 'NA');
        }
        $rowIndex++;
    }

    // Prepare XLSX writer and output
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="export_customers_circuits.xlsx"');
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
} elseif ($format === 'pdf') {
    // Generate HTML for PDF
    $html = '<h2>Customer Circuits Export</h2>';
    $html .= "<p>Total Rows: $totalRows</p>";
    $html .= '<table border="1" cellpadding="4" cellspacing="0" style="border-collapse: collapse; width: 100%;">';
    $html .= '<thead><tr>';
    foreach ($exportHeaders as $header) {
        $html .= '<th style="background-color: #eee;">' . htmlspecialchars($header) . '</th>';
    }
    $html .= '</tr></thead><tbody>';

    foreach ($data as $row) {
        $html .= '<tr>';
        foreach ($selectedFieldsRaw as $field) {
            $col = explode('.', $field, 2)[1];
            $html .= '<td>' . htmlspecialchars($row[$col] ?? 'NA') . '</td>';
        }
        $html .= '</tr>';
    }

    $html .= '</tbody></table>';

    $dompdf = new Dompdf();
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'landscape');
    $dompdf->render();

    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="export_customers_circuits.pdf"');
    echo $dompdf->output();
    exit;
} else {
    die('Invalid export format specified.');
}
