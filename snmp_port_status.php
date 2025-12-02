<?php
// snmp_port_status_up.php
header('Content-Type: application/json');

if (!isset($_GET['ip']) || !isset($_GET['port'])) {
    echo json_encode(['status' => 'error', 'message' => 'Missing parameters']);
    exit;
}

$ip = $_GET['ip'];
$port = $_GET['port'];

// ---- SNMP LOGIC ----
// Example: Use 'public' as the community string (change as needed)
$community = 'public';

// If switch_port is like "GE0/0/4", you may need to map this to ifIndex for SNMP.
// For demo, let's assume port is already an ifIndex (integer). Adjust as needed.
if (!is_numeric($port)) {
    // Try to extract ifIndex if port is in format like "GE0/0/4"
    // You must implement your own mapping (from description to ifIndex) if needed.
    echo json_encode(['status' => 'error', 'message' => 'Port must be SNMP ifIndex (integer)']);
    exit;
}

$oid = "IF-MIB::ifOperStatus.$port";

if (!function_exists('snmpget')) {
    echo json_encode(['status' => 'error', 'message' => 'PHP SNMP extension not enabled']);
    exit;
}

$status_raw = @snmpget($ip, $community, $oid, 1000000, 2);

$status = 'UNKNOWN';
if ($status_raw !== false) {
    if (preg_match('/INTEGER: (\w+)/', $status_raw, $m)) {
        $map = [
            'up' => 'UP',
            'down' => 'DOWN',
            'testing' => 'TESTING',
            'unknown' => 'UNKNOWN',
            'dormant' => 'DORMANT',
            'notPresent' => 'NOT PRESENT',
            'lowerLayerDown' => 'LOWER LAYER DOWN'
        ];
        $status = $map[strtolower($m[1])] ?? strtoupper($m[1]);
    } else {
        $status = $status_raw;
    }
}

echo json_encode([
    'status' => 'ok',
    'result' => [
        'ip' => $ip,
        'port' => $port,
        'snmp_status' => $status
    ]
]);