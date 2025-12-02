<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/includes/db.php';

if (!isset($pdo)) {
    die("Database connection (\$pdo) not found. Check /includes/db.php!");
}

echo "<h2>Device Discovery and SNMP Polling</h2>";

$devices = $pdo->query("SELECT * FROM devices")->fetchAll(PDO::FETCH_ASSOC);
echo "Device count: " . count($devices) . "<br>\n";
foreach ($devices as $device) {
    echo "<hr><b>Discovering ports for Device:</b> {$device['device_name']} ({$device['mgmt_ip']})<br>";
    echo "<b>SNMP walk (PHP SNMP extension):</b><br><pre>";
    $descrs = @snmpwalk($device['mgmt_ip'], $device['snmp_community'], 'IF-MIB::ifDescr');
    if ($descrs === false || empty($descrs)) {
        echo "PHP SNMP extension failed or returned nothing.<br>";
        $descrs = [];
    } else {
        echo htmlspecialchars(implode("\n", $descrs));
    }
    echo "</pre>";

    if (empty($descrs)) {
        echo "<b>SNMP walk (CLI):</b><br>";
        $cmd = "snmpwalk -v2c -c " . escapeshellarg($device['snmp_community']) . " " . escapeshellarg($device['mgmt_ip']) . " 1.3.6.1.2.1.2.2.1.2";
        exec($cmd, $output, $ret);
        echo "<pre>" . htmlspecialchars(implode("\n", $output)) . "</pre>";
        if ($ret !== 0 || empty($output)) {
            echo "<span style='color: red;'>snmpwalk CLI failed (rc=$ret). Command: <code>$cmd</code></span><br>";
            continue;
        }
        $descrs = $output;
    }

    $discovered = 0;
    $ifIndex = 1;
    foreach ($descrs as $entry) {
        // Try to match full OID output
        if (preg_match('/(?:IF-MIB::ifDescr|\.)?[\.:]?(\d+)\s*=\s*STRING:\s*(.+)/', $entry, $m)) {
            $ifIndex = (int)$m[1];
            $label = trim($m[2]);
        } elseif (preg_match('/STRING:\s*(.+)/', $entry, $m)) {
            // Fallback: no index in output, guess index by order
            $label = trim($m[1]);
        } else {
            $ifIndex++;
            continue;
        }
        // Insert into DB
        $stmt = $pdo->prepare("INSERT INTO device_ports (device_id, port_label, ifindex) VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE ifindex=VALUES(ifindex)");
        $stmt->execute([$device['id'], $label, $ifIndex]);
        echo "Discovered port <b>{$label}</b> (ifIndex <b>$ifIndex</b>)<br>\n";
        $discovered++;
        $ifIndex++;
    }
    if ($discovered == 0) {
        echo "<span style='color: orange;'>No ports discovered for this device!</span><br>";
    }
}

// POLLING
echo "<hr><h3>Polling all device ports...</h3>";
$sql = "SELECT p.id AS port_id, d.device_name, d.mgmt_ip, d.snmp_community, d.snmp_version, p.port_label, p.ifindex
        FROM device_ports p
        JOIN devices d ON p.device_id = d.id";
$res = $pdo->query($sql);
$polled = 0;
foreach ($res as $row) {
    $oid = "IF-MIB::ifOperStatus.{$row['ifindex']}";
    $status_raw = @snmpget($row['mgmt_ip'], $row['snmp_community'], $oid, 1000000, 2);
    $status = 'UNKNOWN';
    $error = '';
    if ($status_raw && preg_match('/INTEGER: (\w+)/', $status_raw, $m)) {
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
    } elseif (!$status_raw) {
        $error = "SNMP get failed for port: {$row['port_label']} (ifIndex {$row['ifindex']} on {$row['mgmt_ip']})";
    }
    $stmt = $pdo->prepare("UPDATE device_ports SET last_status=?, last_polled=NOW() WHERE id=?");
    $stmt->execute([$status, $row['port_id']]);
    echo "Polled <b>{$row['device_name']}</b> / <b>{$row['port_label']}</b> (ifIndex <b>{$row['ifindex']}</b>): <span style='color: " . ($status == 'UP' ? 'green' : ($status == 'DOWN' ? 'red' : 'gray')) . ";'>$status</span>";
    if ($error) echo " <span style='color: orange;'>$error</span>";
    echo "<br>\n";
    $polled++;
}
if ($polled === 0) {
    echo "<span style='color: orange;'>No ports to poll! Is your device_ports table empty?</span><br>";
}

echo "<hr><h3>Current device_ports table:</h3>";
$sql = "SELECT d.device_name, d.mgmt_ip, p.port_label, p.ifindex, p.last_status, p.last_polled
        FROM device_ports p
        JOIN devices d ON p.device_id = d.id
        ORDER BY d.device_name, p.port_label";
$res = $pdo->query($sql);
echo "<table border=1 cellpadding=3>";
echo "<tr><th>Device</th><th>IP</th><th>Port Label</th><th>ifIndex</th><th>Status</th><th>Last Polled</th></tr>";
foreach($res as $row) {
    echo "<tr>
        <td>{$row['device_name']}</td>
        <td>{$row['mgmt_ip']}</td>
        <td>{$row['port_label']}</td>
        <td>{$row['ifindex']}</td>
        <td>{$row['last_status']}</td>
        <td>{$row['last_polled']}</td>
    </tr>";
}
echo "</table>";

echo "<br><b>Discovery and polling complete.</b>";
?>