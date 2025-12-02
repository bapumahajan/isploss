<?php
// ----------- CONFIGURE THIS SECTION -------------
$rrd_dir = '/var/lib/cacti/rra'; // Path to your Cacti RRD files (change if needed)
$rrd_file = $_GET['file'] ?? ''; // e.g., "localhost_traffic_in_1.rrd"
if (!preg_match('/^[a-zA-Z0-9_\-\.]+\.rrd$/', $rrd_file)) {
    die('Invalid RRD file name');
}
$full_path = realpath($rrd_dir . '/' . $rrd_file);
if (strpos($full_path, realpath($rrd_dir)) !== 0 || !file_exists($full_path)) {
    die('RRD file not found');
}
// -----------------------------------------------

$range = $_GET['range'] ?? 'day'; // day, week, month, year

switch ($range) {
    case 'year': $start = '-1y'; break;
    case 'month': $start = '-1m'; break;
    case 'week': $start = '-1w'; break;
    default: $start = '-1d'; $range = 'day'; break;
}

// Graph output file (temporary)
$tmp_img = tempnam(sys_get_temp_dir(), 'rrd') . ".png";

// RRDtool command (you can adjust DS names, colors, etc.)
$ret = rrd_graph(
    $tmp_img,
    [
        "--start", $start,
        "--end", "now",
        "--title=Custom RRD Graph ($range)",
        "--width=700",
        "--height=200",
        "--vertical-label=Bits/sec",
        "DEF:in={$full_path}:traffic_in:AVERAGE",
        "DEF:out={$full_path}:traffic_out:AVERAGE",
        "LINE1:in#00FF00:Inbound",
        "LINE1:out#0000FF:Outbound"
    ]
);

if (!$ret) {
    $err = rrd_error();
    die("RRD graph error: $err");
}

header('Content-Type: image/png');
readfile($tmp_img);
unlink($tmp_img);
exit;
?>